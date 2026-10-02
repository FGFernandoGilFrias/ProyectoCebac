<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pdf_helper.php';
exigir_privilegio('reportes.gestionar');

$obraSocialId = (int) ($_GET['obra_social_id'] ?? 0);
$desde = trim((string) ($_GET['desde'] ?? date('Y-m-01')));
$hasta = trim((string) ($_GET['hasta'] ?? date('Y-m-d')));
$modo = (string) ($_GET['modo'] ?? 'obra');

$conexion = conexion_bd();

function fecha_ddmmyyyy(?string $fecha): string
{
    if (!$fecha) return '—';
    $partes = explode('-', substr($fecha, 0, 10));
    return count($partes) === 3 ? $partes[2] . '/' . $partes[1] . '/' . $partes[0] : $fecha;
}

$config = require __DIR__ . '/includes/config_lab.php';

ob_start();
?>
<style>
    body { font-family: Arial, sans-serif; color: #1f2937; font-size: 10px; }
    .titulo { text-align: center; color: #0b234a; font-size: 15px; font-weight: bold; letter-spacing: 2px; margin: 0 0 4px; }
    .subtitulo { text-align: center; color: #64748b; font-size: 9px; margin: 0 0 16px; }
    .info-box { width: 100%; border-collapse: collapse; margin-bottom: 16px; background: #f8fafc; border: 1px solid #e2e8f0; }
    .info-box td { padding: 7px 10px; font-size: 10px; }
    .info-box td strong { color: #0b234a; }
    .tabla { width: 100%; border-collapse: collapse; }
    .tabla th { background: #0b234a; color: #fff; padding: 7px 9px; text-align: left; font-size: 9px; text-transform: uppercase; }
    .tabla td { padding: 6px 9px; border-bottom: 1px solid #e2e8f0; font-size: 9px; }
    .tabla tr:nth-child(even) td { background: #f8fafc; }
    .tabla .codigo { font-family: monospace; color: #075985; font-weight: bold; }
    .tabla tfoot td { background: #f1f5f9; font-weight: bold; padding: 8px 9px; font-size: 10px; }
    .total-box { margin-top: 20px; padding: 16px 20px; background: #f0f9ff; border: 2px solid #1769aa; text-align: right; }
    .total-box .label { color: #075985; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
    .total-box .monto { color: #0b234a; font-size: 20px; font-weight: bold; }
    .sin-datos { text-align: center; color: #94a3b8; font-style: italic; padding: 30px; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
</style>

<?php if ($modo === 'total'): ?>
    <!-- ============ LIQUIDACIÓN TOTAL ============ -->
    <?php
    $stmt = $conexion->prepare(
        "SELECT os.codigo AS os_codigo, os.nombre AS os_nombre, os.cuit,
                COUNT(sub.codigo) AS cantidad_ordenes,
                COALESCE(SUM(sub.total_orden), 0) AS total_obra
         FROM obras_sociales os
         JOIN obras_sociales_credenciales c ON c.obra_social_codigo = os.codigo
         JOIN paciente_obra_social pos ON pos.credencial_codigo = c.codigo
         JOIN pacientes p ON p.codigo = pos.paciente_codigo
         JOIN (
            SELECT o.codigo, o.paciente_codigo,
                   COALESCE(SUM(pp.precio), 0) AS total_orden
            FROM ordenes o
            JOIN estudios_orden eo ON eo.orden_codigo = o.codigo
            JOIN estudios e ON e.codigo = eo.estudio_codigo
            JOIN precio_practica pp ON pp.codigo = e.practica_codigo
            WHERE o.fecha_orden BETWEEN :desde AND :hasta
            GROUP BY o.codigo
         ) AS sub ON sub.paciente_codigo = p.codigo
         GROUP BY os.codigo
         HAVING cantidad_ordenes > 0
         ORDER BY os.nombre ASC"
    );
    $stmt->execute(['desde' => $desde, 'hasta' => $hasta]);
    $liquidacionTotal = $stmt->fetchAll();

    $totalGeneral = 0;
    $totalOrdenes = 0;
    foreach ($liquidacionTotal as $fila) {
        $totalGeneral += (float) $fila['total_obra'];
        $totalOrdenes += (int) $fila['cantidad_ordenes'];
    }
    ?>

    <div class="titulo">LIQUIDACIÓN TOTAL - ÓRDENES MÉDICAS</div>
    <div class="subtitulo">Período: <?= htmlspecialchars(fecha_ddmmyyyy($desde), ENT_QUOTES, 'UTF-8') ?> al <?= htmlspecialchars(fecha_ddmmyyyy($hasta), ENT_QUOTES, 'UTF-8') ?></div>

    <table class="info-box">
        <tr>
            <td style="width: 33%;"><strong>Obras sociales:</strong> <?= count($liquidacionTotal) ?></td>
            <td style="width: 33%;"><strong>Total de órdenes:</strong> <?= $totalOrdenes ?></td>
            <td style="width: 34%;"><strong>Total general:</strong> $ <?= number_format($totalGeneral, 2, ',', '.') ?></td>
        </tr>
    </table>

    <?php if ($liquidacionTotal): ?>
        <table class="tabla">
            <thead>
                <tr>
                    <th style="width: 40%;">Obra social</th>
                    <th style="width: 25%;">CUIT</th>
                    <th style="width: 15%;" class="text-center">Órdenes</th>
                    <th style="width: 20%;" class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($liquidacionTotal as $os): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($os['os_nombre'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= htmlspecialchars($os['cuit'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-center"><?= (int) $os['cantidad_ordenes'] ?></td>
                        <td class="text-right">$ <?= number_format((float) $os['total_obra'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2"></td>
                    <td class="text-center"><?= $totalOrdenes ?></td>
                    <td class="text-right">$ <?= number_format($totalGeneral, 2, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="total-box">
            <div class="label">Total General</div>
            <div class="monto">$ <?= number_format($totalGeneral, 2, ',', '.') ?></div>
        </div>
    <?php else: ?>
        <div class="sin-datos">No hay órdenes registradas en el período.</div>
    <?php endif; ?>

<?php else: ?>
    <!-- ============ LIQUIDACIÓN POR OBRA SOCIAL ============ -->
    <?php
    $stmt = $conexion->prepare('SELECT codigo, nombre, cuit, condiciones_convenio FROM obras_sociales WHERE codigo = :codigo');
    $stmt->execute(['codigo' => $obraSocialId]);
    $obraSocial = $stmt->fetch();
    if (!$obraSocial) { exit('Obra social no encontrada.'); }

    $stmt = $conexion->prepare(
        "SELECT o.codigo AS orden_codigo, o.fecha_orden, o.medico, o.estado,
                p.apellido, p.nombre, p.dni,
                c.nro_credencial,
                GROUP_CONCAT(DISTINCT e.estudio ORDER BY e.estudio SEPARATOR ', ') AS estudios,
                COALESCE(SUM(pp.precio), 0) AS total_orden
         FROM ordenes o
         JOIN pacientes p ON p.codigo = o.paciente_codigo
         JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
         JOIN obras_sociales_credenciales c ON c.codigo = pos.credencial_codigo
         JOIN estudios_orden eo ON eo.orden_codigo = o.codigo
         JOIN estudios e ON e.codigo = eo.estudio_codigo
         JOIN precio_practica pp ON pp.codigo = e.practica_codigo
         WHERE c.obra_social_codigo = :obra_social
           AND o.fecha_orden BETWEEN :desde AND :hasta
         GROUP BY o.codigo
         ORDER BY o.fecha_orden ASC"
    );
    $stmt->execute(['obra_social' => $obraSocialId, 'desde' => $desde, 'hasta' => $hasta]);
    $ordenes = $stmt->fetchAll();
    $totalPeriodo = 0;
    foreach ($ordenes as $o) $totalPeriodo += (float) $o['total_orden'];
    ?>

    <div class="titulo">LIQUIDACIÓN MENSUAL - ÓRDENES MÉDICAS</div>
    <div class="subtitulo">Período: <?= htmlspecialchars(fecha_ddmmyyyy($desde), ENT_QUOTES, 'UTF-8') ?> al <?= htmlspecialchars(fecha_ddmmyyyy($hasta), ENT_QUOTES, 'UTF-8') ?></div>

    <table class="info-box">
        <tr>
            <td style="width: 50%;"><strong>Obra social:</strong> <?= htmlspecialchars($obraSocial['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
            <td style="width: 50%;"><strong>CUIT:</strong> <?= htmlspecialchars($obraSocial['cuit'], ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <td><strong>Total de órdenes:</strong> <?= count($ordenes) ?></td>
            <td><strong>Total facturado:</strong> $ <?= number_format($totalPeriodo, 2, ',', '.') ?></td>
        </tr>
    </table>

    <?php if ($ordenes): ?>
        <table class="tabla">
            <thead>
                <tr>
                    <th style="width: 10%;">Orden</th>
                    <th style="width: 10%;">Fecha</th>
                    <th style="width: 20%;">Paciente</th>
                    <th style="width: 10%;">DNI</th>
                    <th style="width: 10%;">Credencial</th>
                    <th style="width: 15%;">Médico</th>
                    <th style="width: 15%;">Estudios</th>
                    <th style="width: 10%;" class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ordenes as $o): ?>
                    <tr>
                        <td class="codigo"><?= htmlspecialchars(formatear_codigo_orden($o['orden_codigo']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(fecha_ddmmyyyy($o['fecha_orden']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($o['apellido'] . ', ' . $o['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($o['dni'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($o['nro_credencial'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($o['medico'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><small><?= htmlspecialchars($o['estudios'] ?? '—', ENT_QUOTES, 'UTF-8') ?></small></td>
                        <td class="text-right">$ <?= number_format((float) $o['total_orden'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="7" class="text-right">TOTAL</td>
                    <td class="text-right">$ <?= number_format($totalPeriodo, 2, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>
    <?php else: ?>
        <div class="sin-datos">No hay órdenes para esa obra social en el período.</div>
    <?php endif; ?>

<?php endif; ?>

<?php
$html = ob_get_clean();

$titulo = $modo === 'total' ? 'Liquidación total' : 'Liquidación ' . ($obraSocial['nombre'] ?? '');
$mpdf = crear_pdf($titulo);
$mpdf->WriteHTML($html);
$nombreArchivo = 'Liquidacion_' . ($modo === 'total' ? 'TOTAL' : preg_replace('/\s+/', '_', $obraSocial['nombre'])) . '_' . $desde . '_' . $hasta . '.pdf';
$mpdf->Output($nombreArchivo, \Mpdf\Output\Destination::INLINE);
exit;