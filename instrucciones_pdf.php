<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pdf_helper.php';
exigir_privilegio('ordenes.gestionar');

$ordenId = (int) ($_GET['orden_id'] ?? 0);
$conexion = conexion_bd();

$stmt = $conexion->prepare(
    "SELECT o.codigo AS orden_codigo, o.fecha_orden, o.medico,
            p.apellido, p.nombre, p.dni, p.fecha_nacimiento, p.telefono,
            os.nombre AS obra_social
     FROM ordenes o
     JOIN pacientes p ON p.codigo = o.paciente_codigo
     LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
     LEFT JOIN obras_sociales_credenciales c ON c.codigo = pos.credencial_codigo
     LEFT JOIN obras_sociales os ON os.codigo = c.obra_social_codigo
     WHERE o.codigo = :codigo"
);
$stmt->execute(['codigo' => $ordenId]);
$orden = $stmt->fetch();

if (!$orden) {
    http_response_code(404);
    exit('Orden no encontrada.');
}

// Estudios solicitados con sus parámetros
$stmt = $conexion->prepare(
    "SELECT e.codigo, e.estudio, e.parametros
     FROM estudios_orden eo
     JOIN estudios e ON e.codigo = eo.estudio_codigo
     WHERE eo.orden_codigo = :codigo
     ORDER BY e.estudio"
);
$stmt->execute(['codigo' => $ordenId]);
$estudios = $stmt->fetchAll();

$config = require __DIR__ . '/includes/config_lab.php';

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

    .instrucciones-box { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 14px 18px; margin-top: 16px; }
    .instrucciones-box h3 { color: #92400e; margin: 0 0 10px; font-size: 12px; }
    .instrucciones-box ul { margin: 0; padding-left: 20px; }
    .instrucciones-box li { margin-bottom: 6px; font-size: 10px; color: #78350f; }

    .info-contacto { background: #f0f9ff; border: 1px solid #bae6fd; padding: 14px 18px; margin-top: 20px; text-align: center; }
    .info-contacto p { margin: 3px 0; font-size: 10px; color: #075985; }
    .info-contacto strong { color: #0b234a; }

    .firma-box { margin-top: 40px; padding-top: 15px; border-top: 1px solid #cbd5e1; text-align: right; font-size: 9px; color: #475569; }
</style>

<div class="titulo">INSTRUCCIONES PARA EL PACIENTE</div>
<div class="subtitulo">Orden <?= formatear_codigo_orden($orden['orden_codigo']) ?> · Fecha <?= htmlspecialchars(fecha_ddmmyyyy($orden['fecha_orden']), ENT_QUOTES, 'UTF-8') ?></div>

<table class="paciente-box">
    <tr>
        <td style="width: 50%;"><strong>Paciente:</strong> <?= htmlspecialchars($orden['apellido'] . ', ' . $orden['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
        <td style="width: 25%;"><strong>DNI:</strong> <?= htmlspecialchars($orden['dni'], ENT_QUOTES, 'UTF-8') ?></td>
        <td style="width: 25%;"><strong>Edad:</strong> <?= htmlspecialchars($edadTexto, ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <tr>
        <td><strong>Obra social:</strong> <?= htmlspecialchars($orden['obra_social'] ?: 'Particular', ENT_QUOTES, 'UTF-8') ?></td>
        <td colspan="2"><strong>Médico:</strong> <?= htmlspecialchars($orden['medico'], ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
</table>

<div class="seccion">
    <h2>Estudios solicitados</h2>
    <table class="estudios-tabla">
        <thead>
            <tr>
                <th style="width: 20%;">Código</th>
                <th style="width: 55%;">Estudio</th>
                <th style="width: 25%;">Valores de referencia</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($estudios as $e): ?>
                <tr>
                    <td class="codigo"><?= htmlspecialchars($e['codigo'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><strong><?= htmlspecialchars($e['estudio'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td><?= htmlspecialchars($e['parametros'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$estudios): ?>
                <tr><td colspan="3" style="text-align: center; color: #94a3b8; font-style: italic;">Sin estudios asociados</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="instrucciones-box">
    <h3>⚠ Instrucciones para la preparación</h3>
    <ul>
        <li><strong>Presentarse con:</strong> esta orden impresa + DNI original.</li>
        <li><strong>Ayuno:</strong> 8 a 12 horas (salvo indicación médica contraria). Solo se permite beber agua.</li>
        <li><strong>Orina:</strong> si el estudio lo requiere, traer la <em>primera orina de la mañana</em> en frasco estéril.</li>
        <li><strong>Medicamentos:</strong> no suspender la medicación habitual salvo indicación médica.</li>
        <li><strong>Horario de atención:</strong> <?= htmlspecialchars($config['horario'], ENT_QUOTES, 'UTF-8') ?></li>
    </ul>
</div>

<div class="info-contacto">
    <p><strong><?= htmlspecialchars($config['nombre'], ENT_QUOTES, 'UTF-8') ?></strong></p>
    <p><?= htmlspecialchars($config['direccion'], ENT_QUOTES, 'UTF-8') ?></p>
    <p>Tel: <?= htmlspecialchars($config['telefono'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($config['email'], ENT_QUOTES, 'UTF-8') ?></p>
    <p style="margin-top: 10px; font-size: 9px; color: #64748b;">Ante cualquier duda, comunicarse con el laboratorio antes de la extracción.</p>
</div>

<div class="firma-box">
    Documento generado el <?= date('d/m/Y H:i') ?><br>
    Sistema CEBAC
</div>

<?php
$html = ob_get_clean();

$mpdf = crear_pdf('Instrucciones - Orden ' . formatear_codigo_orden($orden['orden_codigo']));
$mpdf->WriteHTML($html);
$mpdf->Output('Instrucciones_' . formatear_codigo_orden($orden['orden_codigo']) . '_' . $orden['dni'] . '.pdf', \Mpdf\Output\Destination::INLINE);
exit;