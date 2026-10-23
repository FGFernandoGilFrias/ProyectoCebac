<?php
/**
 * reporte_resultados_paciente.php
 * Historial de resultados por paciente.
 */
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('reportes.gestionar');

$conexion = conexion_bd();

$pacienteId = (int) ($_GET['paciente_id'] ?? 0);
$desde = trim((string) ($_GET['desde'] ?? date('Y-m-01')));
$hasta = trim((string) ($_GET['hasta'] ?? date('Y-m-d')));

$error = null;
if ($desde === '' || $hasta === '') {
    $error = 'Ingresá ambas fechas.';
}
if (!$error && $desde > $hasta) {
    $error = 'La fecha "desde" no puede ser mayor que "hasta".';
}

// Lista de pacientes
$pacientes = $conexion->query(
    'SELECT codigo, apellido, nombre, dni FROM pacientes ORDER BY apellido, nombre'
)->fetchAll();

// Datos del paciente seleccionado
$paciente = null;
if ($pacienteId > 0) {
    $stmt = $conexion->prepare('SELECT codigo, apellido, nombre, dni FROM pacientes WHERE codigo = :codigo');
    $stmt->execute(['codigo' => $pacienteId]);
    $paciente = $stmt->fetch();

    if (!$paciente) {
        $error = 'Paciente no encontrado.';
    }
}

// Órdenes con resultados del paciente
$ordenes = [];

if (!$error && $pacienteId > 0) {
    $sql = "SELECT o.codigo AS orden_codigo, o.fecha_orden, o.medico, o.estado,
                   r.codigo AS resultado_codigo, r.resultado, r.fecha_resultado,
                   os.nombre AS obra_social
            FROM ordenes o
            LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = o.paciente_codigo
            LEFT JOIN obras_sociales os ON os.codigo = pos.obra_social_codigo
            LEFT JOIN resultados r ON r.orden_codigo = o.codigo
            WHERE o.paciente_codigo = :paciente
              AND o.fecha_orden BETWEEN :desde AND :hasta
            ORDER BY o.fecha_orden DESC, o.codigo DESC";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([
        'paciente' => $pacienteId,
        'desde' => $desde,
        'hasta' => $hasta,
    ]);
    $ordenes = $stmt->fetchAll();
}

$tituloPagina = 'Reporte: Resultados por paciente | CEBAC';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Resultados por paciente</h1>
        <p class="text-muted mb-0">Historial de resultados de un paciente en el período.</p>
    </div>
    <?php if (!$error && $paciente && $ordenes): ?>
        <a class="btn btn-outline-primary" href="reporte_resultados_paciente_pdf.php?<?= http_build_query([
            'paciente_id' => $pacienteId,
            'desde' => $desde,
            'hasta' => $hasta,
        ]) ?>" target="_blank">
            <i class="bi bi-printer me-1"></i>Imprimir PDF
        </a>
    <?php endif; ?>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= escapar_html($error) ?></div><?php endif; ?>

<form method="get" class="row g-2 mb-4">
    <div class="col-md-4">
        <label class="form-label small">Paciente *</label>
        <select name="paciente_id" class="form-select" required>
            <option value="">Seleccionar paciente</option>
            <?php foreach ($pacientes as $p): ?>
                <option value="<?= (int) $p['codigo'] ?>" <?= $pacienteId === (int) $p['codigo'] ? 'selected' : '' ?>>
                    <?= escapar_html($p['apellido'] . ', ' . $p['nombre']) ?> - DNI <?= escapar_html($p['dni']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label small">Desde</label>
        <input type="date" name="desde" class="form-control" value="<?= escapar_html($desde) ?>">
    </div>
    <div class="col-md-2">
        <label class="form-label small">Hasta</label>
        <input type="date" name="hasta" class="form-control" value="<?= escapar_html($hasta) ?>">
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100">Generar</button>
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <a href="reporte_resultados_paciente.php" class="btn btn-outline-secondary w-100">Limpiar</a>
    </div>
</form>

<?php if (!$error && $paciente): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 fw-bold mb-1"><?= escapar_html($paciente['apellido'] . ', ' . $paciente['nombre']) ?></h2>
            <p class="text-muted mb-0">DNI: <?= escapar_html($paciente['dni']) ?></p>
        </div>
    </div>

    <?php if ($ordenes): ?>
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha resultado</th>
                            <th>Orden</th>
                            <th>Fecha orden</th>
                            <th>Médico</th>
                            <th>Obra social</th>
                            <th>Resultado</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ordenes as $o): ?>
                            <tr>
                                <td><?= escapar_html(fecha_ddmmyyyy($o['fecha_resultado'], '-')) ?></td>
                                <td><strong>#<?= formatear_codigo_orden($o['orden_codigo']) ?></strong></td>
                                <td><?= escapar_html(fecha_ddmmyyyy($o['fecha_orden'], '-')) ?></td>
                                <td><?= escapar_html($o['medico']) ?></td>
                                <td><?= escapar_html($o['obra_social'] ?? 'Particular') ?></td>
                                <td>
                                    <?php if (!empty($o['resultado'])): ?>
                                        <small><?= nl2br(escapar_html($o['resultado'])) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Sin cargar</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $badgeEstado = 'bg-secondary';
                                    if ($o['estado'] === 'Validado') $badgeEstado = 'bg-info text-dark';
                                    elseif ($o['estado'] === 'Finalizado') $badgeEstado = 'bg-success';
                                    ?>
                                    <span class="badge <?= $badgeEstado ?>"><?= escapar_html($o['estado']) ?></span>
                                </td>
                                <td class="text-end">
                                    <?php if (!empty($o['resultado_codigo'])): ?>
                                        <a class="btn btn-sm btn-outline-primary" href="resultado_pdf.php?orden_id=<?= (int) $o['orden_codigo'] ?>" target="_blank">
                                            <i class="bi bi-file-pdf"></i> PDF
                                        </a>
                                    <?php endif; ?>
                                    <a class="btn btn-sm btn-outline-secondary" href="orden_detalle.php?id=<?= (int) $o['orden_codigo'] ?>">
                                        <i class="bi bi-eye"></i> Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body text-center text-muted py-4">
                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                No hay resultados para ese paciente en el período.
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>