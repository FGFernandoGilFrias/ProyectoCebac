<?php
/**
 * reporte_practicas_realizadas_pdf.php
 * PDF del listado de prácticas realizadas.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pdf_helper.php';
exigir_privilegio('reportes.gestionar');

$conexion = conexion_bd();

$desde = trim((string) ($_GET['desde'] ?? date('Y-m-01')));
$hasta = trim((string) ($_GET['hasta'] ?? date('Y-m-d')));
$obraSocialId = (int) ($_GET['obra_social_id'] ?? 0);
$medico = trim((string) ($_GET['medico'] ?? ''));

$sql = "SELECT o.codigo AS orden_codigo, o.fecha_orden, o.medico, o.estado,
               p.apellido, p.nombre, p.dni,
               os.nombre AS obra_social,
               pos.nro_credencial,
               GROUP_CONCAT(DISTINCT e.estudio ORDER BY e.estudio SEPARATOR ', ') AS estudios,
               COALESCE(SUM(pp.precio), 0) AS total_orden
        FROM ordenes o
        JOIN pacientes p ON p.codigo = o.paciente_codigo
        LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
        LEFT JOIN obras_sociales os ON os.codigo = pos.obra_social_codigo
        LEFT JOIN estudios_orden eo ON eo.orden_codigo = o.codigo
        LEFT JOIN estudios e ON e.codigo = eo.estudio_codigo
        LEFT JOIN precio_practica pp ON pp.codigo = e.practica_codigo
        WHERE o.fecha_orden BETWEEN :desde AND :hasta";
$params = ['desde' => $desde, 'hasta' => $hasta];

if ($obraSocialId > 0) {
    $sql .= ' AND pos.obra_social_codigo = :obra_social';
    $params['obra_social'] = $obraSocialId;
}
if ($medico !== '') {
    $sql .= ' AND o.medico LIKE :medico';
    $params['medico'] = '%' . $medico . '%';
}

$sql .= ' GROUP BY o.codigo ORDER BY o.fecha_orden DESC, o.codigo DESC';

$stmt = $conexion->prepare($sql);
$stmt->execute($params);
$ordenes = $stmt->fetchAll();

$totalFacturado = 0;
foreach ($ordenes as $o) {
    $totalFacturado += (float) $o['total_orden'];
}

$obraSocialNombre = 'Todas';
if ($obraSocialId > 0) {
    $s = $conexion->prepare('SELECT nombre FROM obras_sociales WHERE codigo = :c');
    $s->execute(['c' => $obraSocialId]);
    $obraSocialNombre = (string) ($s->fetchColumn() ?: '—');
}

$config = require __DIR__ . '/includes/config_lab.php';

ob_start();
?>
<style>
    body { font-family: Arial, sans-serif; color: #1f2937; font-size: 9px; }
    .titulo { text-align: center; color: #0b234a; font-size: 15px; font-weight: bold; letter-spacing: 2px; margin: 0 0 4px; }
    .subtitulo { text-align: center; color: #64748b; font-size: 9px; margin: 0 0 14px; }
    .filtros-box { width: 100%; border-collapse: collapse; margin-bottom: 14px; background: #f8fafc; border: 1px solid #e2e8f0; }
    .filtros-box td { padding: 6px 9px; font-size: 9px; }
    .filtros-box td strong { color: #0b234a; }
    .tabla { width: 100%; border-collapse: collapse; }
    .tabla th { background: #0b234a; color: #fff; font-size: 7px; padding: 5px 6px; text-align: left; text-transform: uppercase; }
    .tabla td { padding: 4px 6px; font-size: 8px; border-bottom: 1px solid #e2e8f0; }
    .tabla tr:nth-child(even) td { background: #f8fafc; }
    .tabla .codigo { font-family: monospace; color: #075985; font-weight: bold; }
    .tabla tfoot td { background: #f1f5f9; font-weight: bold; font-size: 9px; padding: 6px; border-top: 2px solid #0b234a; }
    .sin-datos { text-align: center; color: #94a3b8; font-style: italic; padding: 25px; }
</style>

<div class="titulo">PRÁCTICAS REALIZADAS</div>
<div class="subtitulo">Período: <?= htmlspecialchars(fecha_ddmmyyyy($desde), ENT_QUOTES, 'UTF-8') ?> al <?= htmlspecialchars(fecha_ddmmyyyy($hasta), ENT_QUOTES, 'UTF-8') ?></div>

<table class="filtros-box">
    <tr>
        <td style="width: 33%;"><strong>Desde:</strong> <?= htmlspecialchars(fecha_ddmmyyyy($desde), ENT_QUOTES, 'UTF-8') ?></td>
        <td style="width: 33%;"><strong>Hasta:</strong> <?= htmlspecialchars(fecha_ddmmyyyy($hasta), ENT_QUOTES, 'UTF-8') ?></td>
        <td style="width: 34%;"><strong>Generado:</strong> <?= date('d-m-Y H:i') ?></td>
    </tr>
    <tr>
        <td><strong>Obra social:</strong> <?= htmlspecialchars($obraSocialNombre, ENT_QUOTES, 'UTF-8') ?></td>
        <td colspan="2"><strong>Médico:</strong> <?= $medico !== '' ? htmlspecialchars($medico, ENT_QUOTES, 'UTF-8') : 'Todos' ?></td>
    </tr>
</table>

<?php if ($ordenes): ?>
    <table class="tabla">
        <thead>
            <tr>
                <th style="width: 7%;">Orden</th>
                <th style="width: 8%;">Fecha</th>
                <th style="width: 17%;">Paciente</th>
                <th style="width: 8%;">DNI</th>
                <th style="width: 12%;">Obra social</th>
                <th style="width: 8%;">Credencial</th>
                <th style="width: 12%;">Médico</th>
                <th style="width: 20%;">Estudios</th>
                <th style="width: 8%;" class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ordenes as $o): ?>
                <tr>
                    <td class="codigo">#<?= htmlspecialchars(formatear_codigo_orden($o['orden_codigo']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(fecha_ddmmyyyy($o['fecha_orden']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($o['apellido'] . ', ' . $o['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($o['dni'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($o['obra_social'] ?? 'Particular', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($o['nro_credencial'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($o['medico'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($o['estudios'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="text-end">$ <?= number_format((float) $o['total_orden'], 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="8" class="text-end">TOTAL</td>
                <td class="text-end">$ <?= number_format($totalFacturado, 2, ',', '.') ?></td>
            </tr>
        </tfoot>
    </table>
<?php else: ?>
    <div class="sin-datos">No hay prácticas realizadas en el período.</div>
<?php endif; ?>

<?php
$html = ob_get_clean();
$mpdf = crear_pdf('Prácticas realizadas');
$mpdf->WriteHTML($html);
$mpdf->Output('Practicas_Realizadas_' . $desde . '_' . $hasta . '.pdf', \Mpdf\Output\Destination::INLINE);
exit;