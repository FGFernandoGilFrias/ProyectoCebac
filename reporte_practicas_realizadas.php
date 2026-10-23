<?php
/**
 * reporte_practicas_realizadas.php
 * Listado de prácticas realizadas con filtros.
 */
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('reportes.gestionar');

$conexion = conexion_bd();

$desde = trim((string) ($_GET['desde'] ?? date('Y-m-01')));
$hasta = trim((string) ($_GET['hasta'] ?? date('Y-m-d')));
$obraSocialId = (int) ($_GET['obra_social_id'] ?? 0);
$medico = trim((string) ($_GET['medico'] ?? ''));

$error = null;
if ($desde === '' || $hasta === '') {
    $error = 'Ingresá ambas fechas.';
}
if (!$error && $desde > $hasta) {
    $error = 'La fecha "desde" no puede ser mayor que "hasta".';
}

$ordenes = [];
$totales = ['cantidad' => 0, 'facturado' => 0, 'paciente' => 0];

if (!$error) {
    $sql = "SELECT o.codigo AS orden_codigo, o.fecha_orden, o.medico, o.estado,
                   p.codigo AS paciente_codigo, p.apellido, p.nombre, p.dni,
                   os.nombre AS obra_social,
                   pos.nro_credencial, pos.cobertura, pos.porcentaje_cobertura,
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

    foreach ($ordenes as $o) {
        $totales['cantidad']++;
        $totales['facturado'] += (float) $o['total_orden'];
        $porcentaje = (float) ($o['porcentaje_cobertura'] ?? 0);
        $totales['paciente'] += (float) $o['total_orden'] * (1 - $porcentaje / 100);
    }
}

$obrasSociales = $conexion->query('SELECT codigo, nombre FROM obras_sociales WHERE fecha_baja IS NULL ORDER BY nombre')->fetchAll();

$tituloPagina = 'Reporte: Prácticas realizadas | CEBAC';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Prácticas realizadas</h1>
        <p class="text-muted mb-0">Órdenes con estudios realizados en el período.</p>
    </div>
    <?php if (!$error && $ordenes): ?>
        <a class="btn btn-outline-primary" href="reporte_practicas_realizadas_pdf.php?<?= http_build_query([
            'desde' => $desde,
            'hasta' => $hasta,
            'obra_social_id' => $obraSocialId,
            'medico' => $medico,
        ]) ?>" target="_blank">
            <i class="bi bi-printer me-1"></i>Imprimir PDF
        </a>
    <?php endif; ?>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= escapar_html($error) ?></div><?php endif; ?>

<form method="get" class="row g-2 mb-4">
    <div class="col-md-2">
        <label class="form-label small">Desde</label>
        <input type="date" name="desde" class="form-control" value="<?= escapar_html($desde) ?>" required>
    </div>
    <div class="col-md-2">
        <label class="form-label small">Hasta</label>
        <input type="date" name="hasta" class="form-control" value="<?= escapar_html($hasta) ?>" required>
    </div>
    <div class="col-md-3">
        <label class="form-label small">Obra social</label>
        <select name="obra_social_id" class="form-select">
            <option value="0">Todas</option>
            <?php foreach ($obrasSociales as $os): ?>
                <option value="<?= (int) $os['codigo'] ?>" <?= $obraSocialId === (int) $os['codigo'] ? 'selected' : '' ?>>
                    <?= escapar_html($os['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label small">Médico</label>
        <input type="text" name="medico" class="form-control" value="<?= escapar_html($medico) ?>" placeholder="Nombre del médico">
    </div>
    <div class="col-md-2 d-flex align-items-end gap-2">
        <button type="submit" class="btn btn-primary w-100">Generar</button>
        <a href="reporte_practicas_realizadas.php" class="btn btn-outline-secondary">Limpiar</a>
    </div>
</form>

<?php if (!$error): ?>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <small class="text-muted text-uppercase fw-bold">Total órdenes</small>
                    <div class="fs-3 fw-bold"><?= $totales['cantidad'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <small class="text-muted text-uppercase fw-bold">Facturación total</small>
                    <div class="fs-3 fw-bold">$ <?= number_format($totales['facturado'], 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <small class="text-muted text-uppercase fw-bold">A cargo pacientes</small>
                    <div class="fs-3 fw-bold">$ <?= number_format($totales['paciente'], 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Orden</th>
                        <th>Fecha</th>
                        <th>Paciente</th>
                        <th>DNI</th>
                        <th>Obra social</th>
                        <th>Credencial</th>
                        <th>Médico</th>
                        <th>Estudios</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ordenes as $o): ?>
                        <tr>
                            <td><strong>#<?= formatear_codigo_orden($o['orden_codigo']) ?></strong></td>
                            <td><?= escapar_html(fecha_ddmmyyyy($o['fecha_orden'], '-')) ?></td>
                            <td><?= escapar_html($o['apellido'] . ', ' . $o['nombre']) ?></td>
                            <td><?= escapar_html($o['dni']) ?></td>
                            <td><?= escapar_html($o['obra_social'] ?? 'Particular') ?></td>
                            <td><?= escapar_html($o['nro_credencial'] ?? '—') ?></td>
                            <td><?= escapar_html($o['medico']) ?></td>
                            <td><small><?= escapar_html($o['estudios'] ?? '—') ?></small></td>
                            <td class="text-end">$ <?= number_format((float) $o['total_orden'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$ordenes): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No hay prácticas realizadas en el período.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>