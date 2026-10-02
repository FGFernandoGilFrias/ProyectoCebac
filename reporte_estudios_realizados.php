<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('reportes.gestionar');

$conexion = conexion_bd();

$desde = trim((string) ($_GET['desde'] ?? date('Y-m-01')));
$hasta = trim((string) ($_GET['hasta'] ?? date('Y-m-d')));
$obraSocialId = (int) ($_GET['obra_social_id'] ?? 0);
$estudioId = (int) ($_GET['estudio_id'] ?? 0);

$error = null;
if ($desde === '' || $hasta === '') {
    $error = 'Ingresá ambas fechas.';
}
if (!$error && $desde > $hasta) {
    $error = 'La fecha "desde" no puede ser mayor que "hasta".';
}

function fecha_ddmmyyyy(?string $fecha): string
{
    if (!$fecha) return '—';
    $partes = explode('-', substr($fecha, 0, 10));
    return count($partes) === 3 ? $partes[2] . '-' . $partes[1] . '-' . $partes[0] : $fecha;
}

$estudios = [];
$totales = ['cantidad' => 0, 'facturado' => 0];

if (!$error) {
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

    foreach ($estudios as $e) {
        $totales['cantidad'] += (int) $e['cantidad_estudios'];
        $totales['facturado'] += (float) $e['total_estudios'];
    }
}

$obrasSociales = $conexion->query('SELECT codigo, nombre FROM obras_sociales WHERE fecha_baja IS NULL ORDER BY nombre')->fetchAll();
$listaEstudios = $conexion->query('SELECT codigo, estudio FROM estudios ORDER BY estudio')->fetchAll();

$tituloPagina = 'Reporte: Estudios realizados | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container">
<p><a href="reportes.php">← Volver a reportes</a></p>

<div class="page-heading">
    <div>
        <span class="eyebrow">Reporte</span>
        <h1>Estudios realizados</h1>
        <p class="muted">Cantidad de veces que se realizó cada estudio en el período.</p>
    </div>
    <?php if (!$error && $estudios): ?>
        <a class="button-link" href="reporte_estudios_realizados_pdf.php?<?= http_build_query([
            'desde' => $desde,
            'hasta' => $hasta,
            'obra_social_id' => $obraSocialId,
            'estudio_id' => $estudioId,
        ]) ?>" target="_blank">📄 Ver PDF</a>
    <?php endif; ?>
</div>

<?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?>

<form method="get" class="report-filters module">
    <div class="filter-grid">
        <div>
            <label for="desde">Desde</label>
            <input type="date" id="desde" name="desde" value="<?= escapar_html($desde) ?>" required>
        </div>
        <div>
            <label for="hasta">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="<?= escapar_html($hasta) ?>" required>
        </div>
        <div>
            <label for="obra_social_id">Obra social</label>
            <select id="obra_social_id" name="obra_social_id">
                <option value="0">Todas</option>
                <?php foreach ($obrasSociales as $os): ?>
                    <option value="<?= (int) $os['codigo'] ?>" <?= $obraSocialId === (int) $os['codigo'] ? 'selected' : '' ?>>
                        <?= escapar_html($os['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="estudio_id">Estudio</label>
            <select id="estudio_id" name="estudio_id">
                <option value="0">Todos</option>
                <?php foreach ($listaEstudios as $e): ?>
                    <option value="<?= escapar_html($e['codigo']) ?>" <?= $estudioId === $e['codigo'] ? 'selected' : '' ?>>
                        <?= escapar_html($e['estudio']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit">Generar reporte</button>
            <a href="reporte_estudios_realizados.php" class="link-secondary">Limpiar</a>
        </div>
    </div>
</form>

<?php if (!$error): ?>
    <section class="report-summary">
        <div class="summary-card">
            <span class="eyebrow">Total estudios</span>
            <strong class="summary-number"><?= (int) $totales['cantidad'] ?></strong>
            <small class="muted">Entre <?= escapar_html(fecha_ddmmyyyy($desde)) ?> y <?= escapar_html(fecha_ddmmyyyy($hasta)) ?></small>
        </div>
        <div class="summary-card">
            <span class="eyebrow">Facturación total</span>
            <strong class="summary-number">$ <?= number_format($totales['facturado'], 0, ',', '.') ?></strong>
            <small class="muted">Suma de precios de estudios</small>
        </div>
        <div class="summary-card">
            <span class="eyebrow">Estudios distintos</span>
            <strong class="summary-number"><?= count($estudios) ?></strong>
            <small class="muted">En el período</small>
        </div>
    </section>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Estudio</th>
                    <th>Referencia</th>
                    <th style="text-align: center;">Órdenes</th>
                    <th style="text-align: center;">Cantidad</th>
                    <th style="text-align: right;">Facturado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($estudios as $e): ?>
                    <tr>
                        <td><strong><?= escapar_html($e['codigo']) ?></strong></td>
                        <td><?= escapar_html($e['estudio']) ?></td>
                        <td><?= escapar_html($e['parametros'] ?? '—') ?></td>
                        <td style="text-align: center;"><?= (int) $e['cantidad_ordenes'] ?></td>
                        <td style="text-align: center;"><strong><?= (int) $e['cantidad_estudios'] ?></strong></td>
                        <td style="text-align: right;">$ <?= number_format((float) $e['total_estudios'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$estudios): ?>
                    <tr><td colspan="6" class="muted">No hay estudios realizados en el período.</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if ($estudios): ?>
                <tfoot>
                    <tr style="background: #f1f5f9; font-weight: bold;">
                        <td colspan="4" style="text-align: right;">TOTALES</td>
                        <td style="text-align: center;"><?= (int) $totales['cantidad'] ?></td>
                        <td style="text-align: right;">$ <?= number_format((float) $totales['facturado'], 2, ',', '.') ?></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
<?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>