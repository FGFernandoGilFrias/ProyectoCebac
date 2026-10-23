<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pdf_helper.php';
exigir_privilegio('ordenes.gestionar');

$ordenId = (int) ($_GET['orden_id'] ?? 0);
$conexion = conexion_bd();

$stmt = $conexion->prepare(
    "SELECT o.codigo AS orden_codigo, o.fecha_orden, o.medico, o.estado,
            p.apellido, p.nombre, p.dni, p.fecha_nacimiento, p.telefono,
            os.nombre AS obra_social,
            pos.nro_credencial, pos.cobertura, pos.porcentaje_cobertura
     FROM ordenes o
     JOIN pacientes p ON p.codigo = o.paciente_codigo
     LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
     LEFT JOIN obras_sociales os ON os.codigo = pos.obra_social_codigo
     WHERE o.codigo = :codigo"
);
$stmt->execute(['codigo' => $ordenId]);
$orden = $stmt->fetch();

if (!$orden) {
    http_response_code(404);
    exit('Orden no encontrada.');
}

// Estudios de la orden
$stmt = $conexion->prepare(
    "SELECT e.codigo, e.estudio, e.parametros, pp.precio
     FROM estudios_orden eo
     JOIN estudios e ON e.codigo = eo.estudio_codigo
     LEFT JOIN precio_practica pp ON pp.codigo = e.practica_codigo
     WHERE eo.orden_codigo = :codigo
     ORDER BY e.estudio"
);
$stmt->execute(['codigo' => $ordenId]);
$estudios = $stmt->fetchAll();

$total = 0;
foreach ($estudios as $e) {
    $total += (float) ($e['precio'] ?? 0);
}

$porcentaje = (float) ($orden['porcentaje_cobertura'] ?? 0);
$montoCubre = $total * ($porcentaje / 100);
$montoPaciente = $total - $montoCubre;

$config = require __DIR__ . '/includes/config_lab.php';

$edadTexto = '—';
if (!empty($orden['fecha_nacimiento'])) {
    try { $edadTexto = (new DateTime())->diff(new DateTime($orden['fecha_nacimiento']))->y . ' años'; }
    catch (Throwable $e) {}
}

ob_start();
?>
<style>
    body { font-family: Arial, sans-serif; color: #1f2937; font-size: 11px; }
    .titulo { text-align: center; color: #0b234a; font-size: 16px; font-weight: bold; letter-spacing: 2px; margin: 0 0 4px; }
    .subtitulo { text-align: center; color: #64748b; font-size: 10px; margin: 0 0 18px; }

    .paciente-box { width: 100%; border-collapse: collapse; margin-bottom: 20px; background: #f8fafc; border: 1px solid #e2e8f0; }
    .paciente-box td { padding: 8px 12px; font-size: 10px; }
    .paciente-box td strong { color: #0b234a; }

    .seccion { margin-top: 20px; }
    .seccion h2 { color: #0b234a; font-size: 13px; border-bottom: 2px solid #1769aa; padding-bottom: 5px; margin: 0 0 12px; }

    .estudios-tabla { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    .estudios-tabla th { background: #0b234a; color: #fff; padding: 8px 10px; text-align: left; font-size: 10px; text-transform: uppercase; }
    .estudios-tabla td { padding: 7px 10px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
    .estudios-tabla .codigo { font-family: monospace; color: #075985; font-weight: bold; }
    .estudios-tabla .right { text-align: right; }

    .resumen-box { background: #f0f9ff; border: 1px solid #bae6fd; padding: 14px 18px; margin-top: 20px; }
    .resumen-box .fila { display: flex; justify-content: space-between; padding: 4px 0; font-size: 10px; }
    .resumen-box .fila.total { border-top: 2px solid #0b234a; margin-top: 6px; padding-top: 8px; font-weight: bold; color: #0b234a; font-size: 12px; }

    .info-contacto { background: #f0f9ff; border: 1px solid #bae6fd; padding: 14px 18px; margin-top: 20px; text-align: center; }
    .info-contacto p { margin: 3px 0; font-size: 10px; color: #075985; }
    .info-contacto strong { color: #0b234a; }

    .firma-box { margin-top: 40px; padding-top: 15px; border-top: 1px solid #cbd5e1; text-align: right; font-size: 9px; color: #475569; }
</style>

<div class="titulo">ORDEN MÉDICA</div>
<div class="subtitulo">Orden <?= formatear_codigo_orden($orden['orden_codigo']) ?> · Fecha <?= htmlspecialchars(fecha_ddmmyyyy($orden['fecha_orden']), ENT_QUOTES, 'UTF-8') ?></div>

<table class="paciente-box">
    <tr>
        <td style="width: 50%;"><strong>Paciente:</strong> <?= htmlspecialchars($orden['apellido'] . ', ' . $orden['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
        <td style="width: 25%;"><strong>DNI:</strong> <?= htmlspecialchars($orden['dni'], ENT_QUOTES, 'UTF-8') ?></td>
        <td style="width: 25%;"><strong>Edad:</strong> <?= htmlspecialchars($edadTexto, ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <tr>
        <td><strong>Obra social:</strong> <?= htmlspecialchars($orden['obra_social'] ?: 'Particular', ENT_QUOTES, 'UTF-8') ?></td>
        <td><strong>Credencial:</strong> <?= htmlspecialchars($orden['nro_credencial'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><strong>Médico:</strong> <?= htmlspecialchars($orden['medico'], ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
</table>

<div class="seccion">
    <h2>Estudios solicitados</h2>
    <table class="estudios-tabla">
        <thead>
            <tr>
                <th style="width: 15%;">Código</th>
                <th style="width: 55%;">Estudio</th>
                <th class="right" style="width: 15%;">Precio</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($estudios as $e): ?>
                <tr>
                    <td class="codigo"><?= htmlspecialchars($e['codigo'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><strong><?= htmlspecialchars($e['estudio'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td class="right"><?= htmlspecialchars(formato_moneda((float) ($e['precio'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$estudios): ?>
                <tr><td colspan="3" style="text-align: center; color: #94a3b8; font-style: italic;">Sin estudios asociados</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="resumen-box">
    <div class="fila">
        <span>Total estudios:</span>
        <strong><?= htmlspecialchars(formato_moneda($total), ENT_QUOTES, 'UTF-8') ?></strong>
    </div>
    <?php if ($porcentaje > 0): ?>
    <div class="fila">
        <span>Obra cubre (<?= number_format($porcentaje, 0, ',', '.') ?>%):</span>
        <strong><?= htmlspecialchars(formato_moneda($montoCubre), ENT_QUOTES, 'UTF-8') ?></strong>
    </div>
    <?php endif; ?>
    <div class="fila total">
        <span>Paciente abona:</span>
        <span><?= htmlspecialchars(formato_moneda($montoPaciente), ENT_QUOTES, 'UTF-8') ?></span>
    </div>
</div>

<div class="info-contacto">
    <p><strong><?= htmlspecialchars($config['nombre'], ENT_QUOTES, 'UTF-8') ?></strong></p>
    <p><?= htmlspecialchars($config['direccion'], ENT_QUOTES, 'UTF-8') ?></p>
    <p>Tel: <?= htmlspecialchars($config['telefono'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($config['email'], ENT_QUOTES, 'UTF-8') ?></p>
</div>

<div class="firma-box">
    Documento generado el <?= date('d/m/Y H:i') ?><br>
    Sistema CEBAC
</div>

<?php
$html = ob_get_clean();

$mpdf = crear_pdf('Orden - ' . formatear_codigo_orden($orden['orden_codigo']));
$mpdf->WriteHTML($html);
$mpdf->Output('Orden_' . formatear_codigo_orden($orden['orden_codigo']) . '_' . $orden['dni'] . '.pdf', \Mpdf\Output\Destination::INLINE);
exit;