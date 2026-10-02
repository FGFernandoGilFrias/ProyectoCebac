<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('reportes.gestionar');

$conexion = conexion_bd();

// Filtros
$obraSocialId = (int) ($_GET['obra_social_id'] ?? 0);
$desde = trim((string) ($_GET['desde'] ?? date('Y-m-01')));
$hasta = trim((string) ($_GET['hasta'] ?? date('Y-m-d')));
$modo = (string) ($_GET['modo'] ?? 'obra');

$error = null;
if ($desde === '' || $hasta === '') {
    $error = 'Ingresá ambas fechas.';
}
if (!$error && $desde > $hasta) {
    $error = 'La fecha "desde" no puede ser mayor que "hasta".';
}
if (!$error && $modo === 'obra' && $obraSocialId <= 0) {
    $error = 'Seleccioná una obra social o usá "Liquidación total".';
}

function fecha_ddmmyyyy(?string $fecha): string
{
    if (!$fecha) return '—';
    $partes = explode('-', substr($fecha, 0, 10));
    return count($partes) === 3 ? $partes[2] . '-' . $partes[1] . '-' . $partes[0] : $fecha;
}

$obraSocial = null;
$ordenes = [];
$liquidacionTotal = [];
$totalGeneral = 0;

if (!$error) {
    if ($modo === 'total') {
        // ============================================================
        // LIQUIDACIÓN TOTAL: total calculado con subconsulta (sin duplicados)
        // ============================================================
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

        foreach ($liquidacionTotal as $fila) {
            $totalGeneral += (float) $fila['total_obra'];
        }

    } else {
        // ============================================================
        // LIQUIDACIÓN POR OBRA SOCIAL (una sola, con detalle)
        // ============================================================
        $stmt = $conexion->prepare('SELECT codigo, nombre, cuit, condiciones_convenio FROM obras_sociales WHERE codigo = :codigo');
        $stmt->execute(['codigo' => $obraSocialId]);
        $obraSocial = $stmt->fetch();

        if (!$obraSocial) {
            $error = 'Obra social no encontrada.';
        } else {
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
                 ORDER BY o.fecha_orden ASC, o.codigo ASC"
            );
            $stmt->execute([
                'obra_social' => $obraSocialId,
                'desde' => $desde,
                'hasta' => $hasta,
            ]);
            $ordenes = $stmt->fetchAll();
        }
    }
}

// Obras sociales para el select
$obrasSociales = $conexion->query('SELECT codigo, nombre FROM obras_sociales WHERE fecha_baja IS NULL ORDER BY nombre')->fetchAll();

$tituloPagina = 'Liquidación mensual | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container">
<p><a href="reportes.php">← Volver a reportes</a></p>

<div class="page-heading">
    <div>
        <span class="eyebrow">Reporte</span>
        <h1>Liquidación mensual</h1>
        <p class="muted">Planilla de órdenes médicas para enviar a la obra social a fin de mes.</p>
    </div>
    <?php if (!$error): ?>
        <?php if ($modo === 'total' && !empty($liquidacionTotal)): ?>
            <a class="button-link" href="reporte_liquidacion_mensual_pdf.php?<?= http_build_query([
                'modo' => 'total',
                'desde' => $desde,
                'hasta' => $hasta,
            ]) ?>" target="_blank">📄 Ver PDF</a>
        <?php elseif ($modo === 'obra' && $obraSocial && !empty($ordenes)): ?>
            <a class="button-link" href="reporte_liquidacion_mensual_pdf.php?<?= http_build_query([
                'modo' => 'obra',
                'obra_social_id' => $obraSocialId,
                'desde' => $desde,
                'hasta' => $hasta,
            ]) ?>" target="_blank">📄 Ver PDF</a>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?>

<form method="get" class="report-filters module">
    <input type="hidden" name="modo" id="modo_input" value="<?= escapar_html($modo) ?>">

    <div class="filter-grid">
        <div>
            <label for="obra_social_id">Obra social</label>
            <select id="obra_social_id" name="obra_social_id">
                <option value="">Todas</option>
                <?php foreach ($obrasSociales as $os): ?>
                    <option value="<?= (int) $os['codigo'] ?>" <?= $obraSocialId === (int) $os['codigo'] ? 'selected' : '' ?>>
                        <?= escapar_html($os['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="desde">Desde</label>
            <input type="date" id="desde" name="desde" value="<?= escapar_html($desde) ?>" required>
        </div>
        <div>
            <label for="hasta">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="<?= escapar_html($hasta) ?>" required>
        </div>
        <div class="filter-actions">
            <button type="submit" id="btn-obra" class="button-save">Ver obra</button>
            <button type="submit" id="btn-total" class="button-validar-todo">📊 Liquidación total</button>
            <a href="reporte_liquidacion_mensual.php" class="link-secondary">Limpiar</a>
        </div>
    </div>
</form>

<?php if (!$error): ?>

    <?php if ($modo === 'total'): ?>
        <!-- ============================================================
             VISTA: LIQUIDACIÓN TOTAL (una fila por obra social)
             ============================================================ -->
        <section class="report-summary">
            <div class="summary-card">
                <span class="eyebrow">Período</span>
                <strong><?= escapar_html(fecha_ddmmyyyy($desde)) ?> al <?= escapar_html(fecha_ddmmyyyy($hasta)) ?></strong>
            </div>
            <div class="summary-card">
                <span class="eyebrow">Obras sociales</span>
                <strong class="summary-number"><?= count($liquidacionTotal) ?></strong>
            </div>
            <div class="summary-card">
                <span class="eyebrow">Total general</span>
                <strong class="summary-number">$ <?= number_format($totalGeneral, 0, ',', '.') ?></strong>
            </div>
        </section>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Obra social</th>
                        <th>CUIT</th>
                        <th style="text-align: center;">Órdenes</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($liquidacionTotal as $os): ?>
                        <tr>
                            <td><strong><?= escapar_html($os['os_nombre']) ?></strong></td>
                            <td><?= escapar_html($os['cuit']) ?></td>
                            <td style="text-align: center;"><?= (int) $os['cantidad_ordenes'] ?></td>
                            <td style="text-align: right; font-weight: 700;">$ <?= number_format((float) $os['total_obra'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$liquidacionTotal): ?>
                        <tr><td colspan="4" class="muted">No hay órdenes registradas en ese período.</td></tr>
                    <?php endif; ?>
                </tbody>
                <?php if ($liquidacionTotal): ?>
                    <tfoot>
                        <tr style="background: #f1f5f9; font-weight: bold; font-size: 1.05rem;">
                            <td colspan="3" style="text-align: right;">TOTAL GENERAL</td>
                            <td style="text-align: right; color: #0b234a;">$ <?= number_format($totalGeneral, 2, ',', '.') ?></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>

    <?php else: ?>
        <!-- ============================================================
             VISTA: LIQUIDACIÓN POR OBRA SOCIAL (con detalle)
             ============================================================ -->
        <?php if ($obraSocial): ?>
            <section class="report-summary">
                <div class="summary-card">
                    <span class="eyebrow">Obra social</span>
                    <strong><?= escapar_html($obraSocial['nombre']) ?></strong>
                    <small class="muted">CUIT: <?= escapar_html($obraSocial['cuit']) ?></small>
                </div>
                <div class="summary-card">
                    <span class="eyebrow">Período</span>
                    <strong><?= escapar_html(fecha_ddmmyyyy($desde)) ?> al <?= escapar_html(fecha_ddmmyyyy($hasta)) ?></strong>
                    <small class="muted"><?= count($ordenes) ?> orden(es)</small>
                </div>
                <div class="summary-card">
                    <span class="eyebrow">Total facturado</span>
                    <?php
                    $totalPeriodo = 0;
                    foreach ($ordenes as $o) $totalPeriodo += (float) $o['total_orden'];
                    ?>
                    <strong class="summary-number">$ <?= number_format($totalPeriodo, 0, ',', '.') ?></strong>
                    <small class="muted">Bruto de las órdenes</small>
                </div>
            </section>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Orden</th>
                            <th>Fecha</th>
                            <th>Paciente</th>
                            <th>DNI</th>
                            <th>Credencial</th>
                            <th>Médico</th>
                            <th>Estudios</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ordenes as $o): ?>
                            <tr>
                                <td><strong><?= formatear_codigo_orden($o['orden_codigo']) ?></strong></td>
                                <td><?= escapar_html(fecha_ddmmyyyy($o['fecha_orden'])) ?></td>
                                <td><?= escapar_html($o['apellido'] . ', ' . $o['nombre']) ?></td>
                                <td><?= escapar_html($o['dni']) ?></td>
                                <td><?= escapar_html($o['nro_credencial']) ?></td>
                                <td><?= escapar_html($o['medico']) ?></td>
                                <td><small><?= escapar_html($o['estudios'] ?? '—') ?></small></td>
                                <td>$ <?= number_format((float) $o['total_orden'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$ordenes): ?>
                            <tr><td colspan="8" class="muted">No hay órdenes para esa obra social en el período.</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if ($ordenes): ?>
                        <tfoot>
                            <tr style="background: #f1f5f9; font-weight: bold;">
                                <td colspan="7" style="text-align: right;">TOTAL</td>
                                <td>$ <?= number_format($totalPeriodo, 2, ',', '.') ?></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>

<?php endif; ?>
</main>

<script>
(function () {
    'use strict';
    const modoInput = document.getElementById('modo_input');
    const btnObra = document.getElementById('btn-obra');
    const btnTotal = document.getElementById('btn-total');

    if (btnObra) {
        btnObra.addEventListener('click', function () {
            modoInput.value = 'obra';
        });
    }
    if (btnTotal) {
        btnTotal.addEventListener('click', function () {
            modoInput.value = 'total';
        });
    }
})();
</script>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>