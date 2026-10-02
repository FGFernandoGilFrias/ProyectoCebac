<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pdf_helper.php';
exigir_privilegio('ordenes.gestionar');

$ordenId = (int) ($_GET['orden_id'] ?? 0);
$conexion = conexion_bd();

$stmt = $conexion->prepare(
    "SELECT o.*, p.apellido, p.nombre, p.dni, p.fecha_nacimiento, p.telefono, p.email,
            os.nombre AS obra_social,
            c.nro_credencial, c.cobertura, c.porcentaje_cobertura
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

$totalOrden = 0;
foreach ($estudios as $e) {
    $totalOrden += (float) ($e['precio'] ?? 0);
}

$porcentaje = (float) ($orden['porcentaje_cobertura'] ?? 0);
$montoCubre = $totalOrden * ($porcentaje / 100);
$montoPaciente = $totalOrden - $montoCubre;

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

function formato_moneda(float $v): string
{
    return '$ ' . number_format($v, 2, ',', '.');
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
    .tabla { width: 100%; border-collapse: collapse; }
    .tabla th { background: #0b234a; color: #fff; padding: 7px 9px; text-align: left; font-size: 9px; text-transform: uppercase; }
    .tabla td { padding: 6px 9px; border-bottom: 1px solid #e2e8f0; font-size: 9px; }
    .codigo { font-family: monospace; color: #075985; font-weight: bold; }
    .total-box { margin-top: 12px; padding: 10px 14px; background: #f0f9ff; border: 1px solid #bae6fd; font-size: 10px; }
    .total-box table { width: 100%; border-collapse: collapse; }
    .total-box td { padding: 4px 0; font-size: 10px; }
    .total-box td:last-child { text-align: right; font-weight: bold; }
    .total-box .highlight td { border-top: 1px solid #1769aa; padding-top: 7px; color: #075985; font-size: 11px; }
    .obs-box { margin-top: 14px; padding: 9px; background: #fef3c7; border-left: 3px solid #f59e0b; font-size: 9px; }
</style>

<div class="titulo">ORDEN DE ESTUDIOS</div>
<div class="subtitulo">Orden <?= formatear_codigo_orden($orden['codigo']) ?></div>

<table class="info-box">
    <tr>
        <td style="width: 50%;"><strong>Paciente:</strong> <?= htmlspecialchars($orden['apellido'] . ', ' . $orden['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
        <td style="width: 25%;"><strong>DNI:</strong> <?= htmlspecialchars($orden['dni'], ENT_QUOTES, 'UTF-8') ?></td>
        <td style="width: 25%;"><strong>Edad:</strong> <?= htmlspecialchars($edadTexto, ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <tr>
        <td><strong>Fecha:</strong> <?= htmlspecialchars(fecha_ddmmyyyy($orden['fecha_orden']), ENT_QUOTES, 'UTF-8') ?></td>
        <td colspan="2"><strong>Médico:</strong> <?= htmlspecialchars($orden['medico'], ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <tr>
        <td><strong>Obra social:</strong> <?= htmlspecialchars($orden['obra_social'] ?: 'Particular', ENT_QUOTES, 'UTF-8') ?></td>
        <td colspan="2">
            <?php if ($orden['nro_credencial']): ?>
                <strong>Credencial:</strong> <?= htmlspecialchars($orden['nro_credencial'], ENT_QUOTES, 'UTF-8') ?>
                · <strong>Cobertura:</strong> <?= htmlspecialchars($orden['cobertura'], ENT_QUOTES, 'UTF-8') ?> (<?= number_format($porcentaje, 2, ',', '.') ?>%)
            <?php endif; ?>
        </td>
    </tr>
</table>

<table class="tabla">
    <thead>
        <tr><th style="width: 20%;">Código</th><th style="width: 50%;">Estudio</th><th style="width: 30%;">Precio</th></tr>
    </thead>
    <tbody>
        <?php foreach ($estudios as $e): ?>
            <tr>
                <td class="codigo"><?= htmlspecialchars($e['codigo'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <strong><?= htmlspecialchars($e['estudio'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php if (!empty($e['parametros'])): ?>
                        <br><small><?= htmlspecialchars($e['parametros'], ENT_QUOTES, 'UTF-8') ?></small>
                    <?php endif; ?>
                </td>
                <td><?= formato_moneda((float) ($e['precio'] ?? 0)) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="total-box">
    <table>
        <tr>
            <td>Total estudios:</td>
            <td><?= formato_moneda($totalOrden) ?></td>
        </tr>
        <?php if ($porcentaje > 0): ?>
            <tr>
                <td>Cobertura obra (<?= number_format($porcentaje, 2, ',', '.') ?>%):</td>
                <td>- <?= formato_moneda($montoCubre) ?></td>
            </tr>
        <?php endif; ?>
        <tr class="highlight">
            <td>A abonar por el paciente:</td>
            <td><?= formato_moneda($montoPaciente) ?></td>
        </tr>
    </table>
</div>

<div class="obs-box">
    <strong>Importante:</strong> Presentarse en el laboratorio con esta orden y DNI.
</div>

<?php
$html = ob_get_clean();
$mpdf = crear_pdf('Orden ' . formatear_codigo_orden($orden['codigo']));
$mpdf->WriteHTML($html);
$mpdf->Output('Orden_' . formatear_codigo_orden($orden['codigo']) . '_' . $orden['dni'] . '.pdf', \Mpdf\Output\Destination::INLINE);
exit;