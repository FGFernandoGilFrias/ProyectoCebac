<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pdf_helper.php';
exigir_privilegio('reportes.gestionar');

$conexion = conexion_bd();

$desde = trim((string) ($_GET['desde'] ?? date('Y-m-01')));
$hasta = trim((string) ($_GET['hasta'] ?? date('Y-m-d')));
$obraSocialId = (int) ($_GET['obra_social_id'] ?? 0);
$estudioId = (int) ($_GET['estudio_id'] ?? 0);

function fecha_ddmmyyyy(?string $fecha): string
{
    if (!$fecha) return '—';
    $partes = explode('-', substr($fecha, 0, 10));
    return count($partes) === 3 ? $partes[2] . '/' . $partes[1] . '/' . $partes[0] : $fecha;
}

$sql = "SELECT e.codigo, e.estudio, e.parametros,
               COUNT(DISTINCT o.codigo) AS cantidad_ordenes,
               COUNT(eo.orden_codigo) AS cantidad_estudios,
               COALESCE(SUM(pp.precio), 0) AS total_estudios
        FROM estudios e
        JOIN estudios_orden eo ON eo.estudio_codigo = e.codigo
        JOIN ordenes o ON o.codigo = eo.orden_codigo
        LEFT JOIN precio_practica pp ON pp.codigo = e.practica_codigo
        WHERE o.fecha_orden BETWEEN :desde AND :hasta";
$params = ['desde' => $desde, 'hasta' => $hasta];

if ($estudioId > 0) {
    $sql .= ' AND e.codigo = :estudio_codigo';
    $params['estudio_codigo'] = $estudioId;
}

if ($obraSocialId > 0) {
    $sql .= ' AND EXISTS (
        SELECT 1 FROM paciente_obra_social pos
        JOIN obras_sociales_credenciales c ON c.codigo = pos.credencial_codigo
        WHERE pos.paciente_codigo = o.paciente_codigo
          AND c.obra_social_codigo = :obra_social
    )';
    $params['obra_social'] = $obraSocialId;
}

$sql .= ' GROUP BY e.codigo ORDER BY cantidad_estudios DESC, e.estudio';

$stmt = $conexion->prepare($sql);
$stmt->execute($params);
$estudios = $stmt->fetchAll();

$totalCantidad = 0;
$totalFacturado = 0;
foreach ($estudios as $e) {
    $totalCantidad += (int) $e['cantidad_estudios'];
    $totalFacturado += (float) $e['total_estudios'];
}

$obraSocialNombre = 'Todas';
if ($obraSocialId > 0) {
    $s = $conexion->prepare('SELECT nombre FROM obras_sociales WHERE codigo = :c');
    $s->execute(['c' => $obraSocialId]);
    $obraSocialNombre = (string) ($s->fetchColumn() ?: '—');
}

$estudioNombre = 'Todos';
if ($estudioId > 0) {
    $s = $conexion->prepare('SELECT estudio FROM estudios WHERE codigo = :c');
    $s->execute(['c' => $estudioId]);
    $estudioNombre = (string) ($s->fetchColumn() ?: '—');
}

$config = require __DIR__ . '/includes/config_lab.php';

ob_start();
?>
<style>
    body { font-family: Arial, sans-serif; color: #1f2937; font-size: 10px; }
    .titulo { text-align: center; color: #0b234a; font-size: 15px; font-weight: bold; letter-spacing: 2px; margin: 0 0 4px; }
    .subtitulo { text-align: center; color: #64748b; font-size: 9px; margin: 0 0 14px; }
    .filtros-box { width: 100%; border-collapse: collapse; margin-bottom: 14px; background: #f8fafc; border: 1px solid #e2e8f0; }
    .filtros-box td { padding: 6px 9px; font-size: 9px; }
    .filtros-box td strong { color: #0b234a; }
    .resumen { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .resumen td { padding: 10px; text-align: center; border: 1px solid #e2e8f0; background: #f0f9ff; }
    .resumen .numero { font-size: 20px; font-weight: bold; color: #1769aa; display: block; }
    .resumen .label { font-size: 8px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; }
    .tabla { width: 100%; border-collapse: collapse; }
    .tabla th { background: #0b234a; color: #fff; font-size: 8px; padding: 6px 7px; text-align: left; text-transform: uppercase; }
    .tabla td { padding: 5px 7px; font-size: 9px; border-bottom: 1px solid #e2e8f0; }
    .tabla tr:nth-child(even) td { background: #f8fafc; }
    .tabla .codigo { font-family: monospace; color: #075985; font-weight: bold; }
    .tabla .center { text-align: center; }
    .tabla .right { text-align: right; }
    .tabla tfoot td { background: #f1f5f9; font-weight: bold; font-size: 9px; padding: 7px; border-top: 2px solid #0b234a; }
    .sin-datos { text-align: center; color: #94a3b8; font-style: italic; padding: 25px; }
</style>

<div class="titulo">REPORTE DE ESTUDIOS REALIZADOS</div>
<div class="subtitulo">Período: <?= htmlspecialchars(fecha_ddmmyyyy($desde), ENT_QUOTES, 'UTF-8') ?> al <?= htmlspecialchars(fecha_ddmmyyyy($hasta), ENT_QUOTES, 'UTF-8') ?></div>

<table class="filtros-box">
    <tr>
        <td style="width: 33%;"><strong>Desde:</strong> <?= htmlspecialchars(fecha_ddmmyyyy($desde), ENT_QUOTES, 'UTF-8') ?></td>
        <td style="width: 33%;"><strong>Hasta:</strong> <?= htmlspecialchars(fecha_ddmmyyyy($hasta), ENT_QUOTES, 'UTF-8') ?></td>
        <td style="width: 34%;"><strong>Generado:</strong> <?= date('d-m-Y H:i') ?></td>
    </tr>
    <tr>
        <td><strong>Obra social:</strong> <?= htmlspecialchars($obraSocialNombre, ENT_QUOTES, 'UTF-8') ?></td>
        <td colspan="2"><strong>Estudio:</strong> <?= htmlspecialchars($estudioNombre, ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
</table>

<table class="resumen">
    <tr>
        <td>
            <span class="numero"><?= $totalCantidad ?></span>
            <span class="label">Total estudios</span>
        </td>
        <td>
            <span class="numero"><?= count($estudios) ?></span>
            <span class="label">Estudios distintos</span>
        </td>
        <td>
            <span class="numero">$ <?= number_format($totalFacturado, 0, ',', '.') ?></span>
            <span class="label">Facturación</span>
        </td>
    </tr>
</table>

<?php if ($estudios): ?>
    <table class="tabla">
        <thead>
            <tr>
                <th style="width: 12%;">Código</th>
                <th style="width: 38%;">Estudio</th>
                <th style="width: 15%;">Referencia</th>
                <th class="center" style="width: 10%;">Órdenes</th>
                <th class="center" style="width: 10%;">Cantidad</th>
                <th class="right" style="width: 15%;">Facturado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($estudios as $e): ?>
                <tr>
                    <td class="codigo"><?= htmlspecialchars($e['codigo'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($e['estudio'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($e['parametros'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="center"><?= (int) $e['cantidad_ordenes'] ?></td>
                    <td class="center"><strong><?= (int) $e['cantidad_estudios'] ?></strong></td>
                    <td class="right">$ <?= number_format((float) $e['total_estudios'], 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="right">TOTALES</td>
                <td class="center"><?= $totalCantidad ?></td>
                <td class="right">$ <?= number_format($totalFacturado, 2, ',', '.') ?></td>
            </tr>
        </tfoot>
    </table>
<?php else: ?>
    <div class="sin-datos">No hay estudios realizados en el período.</div>
<?php endif; ?>

<?php
$html = ob_get_clean();
$mpdf = crear_pdf('Reporte de estudios realizados');
$mpdf->WriteHTML($html);
$mpdf->Output('Reporte_Estudios_' . $desde . '_' . $hasta . '.pdf', \Mpdf\Output\Destination::INLINE);
exit;