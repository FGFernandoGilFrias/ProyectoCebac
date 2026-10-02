<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pdf_helper.php';
exigir_privilegio('resultados.gestionar');

$ordenId = (int) ($_GET['orden_id'] ?? 0);
$conexion = conexion_bd();

$stmt = $conexion->prepare(
    "SELECT o.codigo AS orden_codigo, o.fecha_orden, o.estado, o.medico,
            p.apellido, p.nombre, p.dni, p.fecha_nacimiento,
            os.nombre AS obra_social, c.nro_credencial, c.cobertura,
            r.codigo AS resultado_codigo, r.resultado, r.fecha_resultado
     FROM ordenes o
     JOIN pacientes p ON p.codigo = o.paciente_codigo
     LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
     LEFT JOIN obras_sociales_credenciales c ON c.codigo = pos.credencial_codigo
     LEFT JOIN obras_sociales os ON os.codigo = c.obra_social_codigo
     LEFT JOIN resultados r ON r.orden_codigo = o.codigo
     WHERE o.codigo = :codigo"
);
$stmt->execute(['codigo' => $ordenId]);
$orden = $stmt->fetch();

if (!$orden) {
    http_response_code(404);
    exit('Orden no encontrada.');
}

if (!$orden['resultado_codigo'] || empty($orden['resultado'])) {
    exit('Esta orden todavía no tiene resultados cargados.');
}

$estudios = $conexion->prepare(
    "SELECT e.codigo, e.estudio, e.parametros, pp.precio
     FROM estudios_orden eo
     JOIN estudios e ON e.codigo = eo.estudio_codigo
     LEFT JOIN precio_practica pp ON pp.codigo = e.practica_codigo
     WHERE eo.orden_codigo = :codigo
     ORDER BY e.estudio"
);
$estudios->execute(['codigo' => $ordenId]);
$estudios = $estudios->fetchAll();

// Parsear el resultado
$valores = [];
foreach (preg_split('/\R/', $orden['resultado']) as $linea) {
    $partes = explode(':', $linea, 2);
    if (count($partes) === 2) {
        $valores[trim($partes[0])] = trim($partes[1]);
    }
}

// Errores (rechazos) asociados
$errores = [];
if ($orden['resultado_codigo']) {
    $stmtErr = $conexion->prepare('SELECT error_muestra FROM historial_errores WHERE resultado_codigo = :codigo');
    $stmtErr->execute(['codigo' => (int) $orden['resultado_codigo']]);
    foreach ($stmtErr->fetchAll() as $e) {
        $errores[] = $e['error_muestra'];
    }
}

$config = require __DIR__ . '/includes/config_lab.php';
$usuarioLogueado = usuario_actual();
$verificadoNombre = $usuarioLogueado['nombre_completo'] ?? '';
$verificadoMatricula = $usuarioLogueado['matricula'] ?? '';

$edadTexto = '—';
if (!empty($orden['fecha_nacimiento'])) {
    try { $edadTexto = (new DateTime())->diff(new DateTime($orden['fecha_nacimiento']))->y . ' años'; }
    catch (Throwable $e) {}
}

function fecha_ddmmyyyy(?string $fecha): string
{
    if (!$fecha) return '—';
    $partes = explode('-', substr($fecha, 0, 10));
    return count($partes) === 3 ? $partes[2] . '/' . $partes[1] . '/' . $partes[0] : $fecha;
}

ob_start();
?>
<style>
    body { font-family: Arial, sans-serif; color: #1f2937; font-size: 10px; }
    .titulo { text-align: center; color: #0b234a; font-size: 15px; font-weight: bold; letter-spacing: 2px; margin: 0 0 4px; }
    .subtitulo { text-align: center; color: #64748b; font-size: 9px; margin: 0 0 14px; }
    .info-box { width: 100%; border-collapse: collapse; margin-bottom: 14px; background: #f8fafc; border: 1px solid #e2e8f0; }
    .info-box td { padding: 6px 9px; font-size: 9px; }
    .info-box td strong { color: #0b234a; }
    .tabla { width: 100%; border-collapse: collapse; margin-top: 6px; }
    .tabla th { background: #0b234a; color: #fff; padding: 7px 9px; text-align: left; font-size: 9px; text-transform: uppercase; }
    .tabla td { padding: 7px 9px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
    .tabla .codigo { font-family: monospace; color: #075985; font-weight: bold; }
    .tabla .valor { font-weight: bold; color: #0b234a; }
    .tabla .rechazado { color: #991b1b; font-style: italic; }
    .nota { margin-top: 14px; padding: 9px; background: #f8fafc; border-left: 3px solid #1769aa; font-size: 9px; }
</style>

<div class="titulo">INFORME DE RESULTADOS</div>
<div class="subtitulo">Orden <?= formatear_codigo_orden($orden['orden_codigo']) ?> · Fecha: <?= htmlspecialchars(fecha_ddmmyyyy($orden['fecha_resultado']), ENT_QUOTES, 'UTF-8') ?></div>

<table class="info-box">
    <tr>
        <td style="width: 50%;"><strong>Paciente:</strong> <?= htmlspecialchars($orden['apellido'] . ', ' . $orden['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
        <td style="width: 25%;"><strong>DNI:</strong> <?= htmlspecialchars($orden['dni'], ENT_QUOTES, 'UTF-8') ?></td>
        <td style="width: 25%;"><strong>Edad:</strong> <?= htmlspecialchars($edadTexto, ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <tr>
        <td><strong>Fecha orden:</strong> <?= htmlspecialchars(fecha_ddmmyyyy($orden['fecha_orden']), ENT_QUOTES, 'UTF-8') ?></td>
        <td colspan="2"><strong>Médico:</strong> <?= htmlspecialchars($orden['medico'], ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <tr>
        <td><strong>Obra social:</strong> <?= htmlspecialchars($orden['obra_social'] ?: 'Particular', ENT_QUOTES, 'UTF-8') ?></td>
        <td colspan="2">
            <?php if ($orden['nro_credencial']): ?>
                <strong>Credencial:</strong> <?= htmlspecialchars($orden['nro_credencial'], ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
        </td>
    </tr>
</table>

<table class="tabla">
    <thead>
        <tr>
            <th style="width: 15%;">Código</th>
            <th style="width: 45%;">Estudio</th>
            <th style="width: 40%;">Resultado</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($estudios as $est): ?>
            <?php
            $valor = $valores[$est['estudio']] ?? '—';
            $esRechazo = stripos($valor, 'RECHAZADO') !== false;
            ?>
            <tr>
                <td class="codigo"><?= htmlspecialchars($est['codigo'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <strong><?= htmlspecialchars($est['estudio'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php if (!empty($est['parametros'])): ?>
                        <br><small><?= htmlspecialchars($est['parametros'], ENT_QUOTES, 'UTF-8') ?></small>
                    <?php endif; ?>
                </td>
                <td class="<?= $esRechazo ? 'rechazado' : 'valor' ?>">
                    <?= htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if (!empty($errores)): ?>
    <div class="nota">
        <strong>Observaciones:</strong><br>
        <?php foreach ($errores as $err): ?>
            • <?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?><br>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($verificadoNombre): ?>
    <div style="margin-top: 16px; padding-top: 8px; border-top: 1px solid #e2e8f0; font-size: 8px; color: #475569;">
        Validado electrónicamente por: <strong><?= htmlspecialchars($verificadoNombre, ENT_QUOTES, 'UTF-8') ?></strong>
        <?php if ($verificadoMatricula): ?> - Matrícula: <?= htmlspecialchars($verificadoMatricula, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
    </div>
<?php endif; ?>

<?php
$html = ob_get_clean();
$mpdf = crear_pdf('Resultado ' . formatear_codigo_orden($orden['orden_codigo']));
$mpdf->WriteHTML($html);
$mpdf->Output('Resultado_' . formatear_codigo_orden($orden['orden_codigo']) . '_' . $orden['dni'] . '.pdf', \Mpdf\Output\Destination::INLINE);
exit;