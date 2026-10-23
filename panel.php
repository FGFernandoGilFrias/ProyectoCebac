<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('panel.ver');

$conexion = conexion_bd();

// ============================================================
// ESTADÍSTICAS
// ============================================================

$stmt = $conexion->query('SELECT COUNT(*) FROM pacientes');
$totalPacientes = (int) $stmt->fetchColumn();

$stmt = $conexion->prepare('SELECT COUNT(*) FROM ordenes WHERE fecha_orden = :hoy');
$stmt->execute(['hoy' => date('Y-m-d')]);
$ordenesHoy = (int) $stmt->fetchColumn();

$stmt = $conexion->query(
    "SELECT COUNT(DISTINCT o.codigo) FROM ordenes o
     WHERE EXISTS (
         SELECT 1 FROM resultados r_x
         JOIN historial_errores he_x ON he_x.resultado_codigo = r_x.codigo
         WHERE r_x.orden_codigo = o.codigo
           AND (he_x.error_muestra LIKE '%PENDIENTE%' OR he_x.error_muestra LIKE '%RECHAZADO%')
     )"
);
$ordenesPendientes = (int) $stmt->fetchColumn();

$stmt = $conexion->prepare(
    "SELECT COUNT(*) FROM historial_errores he
     JOIN resultados r ON r.codigo = he.resultado_codigo
     WHERE he.error_muestra LIKE '%RECHAZADO%'
       AND r.fecha_resultado = :hoy"
);
$stmt->execute(['hoy' => date('Y-m-d')]);
$rechazosHoy = (int) $stmt->fetchColumn();

$stmt = $conexion->prepare('SELECT COALESCE(SUM(monto), 0) FROM caja WHERE fecha_pago = :hoy');
$stmt->execute(['hoy' => date('Y-m-d')]);
$cobradoHoy = (float) $stmt->fetchColumn();

$stmt = $conexion->query(
    "SELECT
        COALESCE(SUM(sub.total_orden), 0) AS total_ordenes,
        (SELECT COALESCE(SUM(monto), 0) FROM caja) AS total_pagado
     FROM (
        SELECT o.codigo,
               COALESCE(SUM(pp.precio), 0) AS total_orden
        FROM ordenes o
        JOIN estudios_orden eo ON eo.orden_codigo = o.codigo
        JOIN estudios e ON e.codigo = eo.estudio_codigo
        JOIN precio_practica pp ON pp.codigo = e.practica_codigo
        GROUP BY o.codigo
     ) AS sub"
);
$totales = $stmt->fetch();
$saldoPendiente = max(0, (float) $totales['total_ordenes'] - (float) $totales['total_pagado']);

// ✅ Query simplificada: órdenes con estudios pero SIN resultado cargado
$stmt = $conexion->query(
    "SELECT COUNT(*) FROM ordenes o
     WHERE EXISTS (SELECT 1 FROM estudios_orden eo WHERE eo.orden_codigo = o.codigo)
       AND NOT EXISTS (
           SELECT 1 FROM resultados r
           WHERE r.orden_codigo = o.codigo
             AND r.resultado IS NOT NULL
             AND r.resultado <> ''
       )"
);
$resultadosPendientes = (int) $stmt->fetchColumn();

// ============================================================
// LISTADOS
// ============================================================

$stmt = $conexion->query(
    "SELECT o.codigo AS orden_codigo, o.fecha_orden, o.estado, o.medico,
            p.apellido, p.nombre, p.dni
     FROM ordenes o
     JOIN pacientes p ON p.codigo = o.paciente_codigo
     ORDER BY o.codigo DESC
     LIMIT 8"
);
$ultimasOrdenes = $stmt->fetchAll();

$stmt = $conexion->query(
    "SELECT c.codigo, c.monto, c.metodo_pago, c.fecha_pago,
            p.apellido, p.nombre
     FROM caja c
     JOIN pacientes p ON p.codigo = c.paciente_codigo
     ORDER BY c.codigo DESC
     LIMIT 5"
);
$ultimosPagos = $stmt->fetchAll();

$stmt = $conexion->query(
    "SELECT he.error_muestra, r.fecha_resultado,
            o.codigo AS orden_codigo,
            p.apellido, p.nombre
     FROM historial_errores he
     JOIN resultados r ON r.codigo = he.resultado_codigo
     JOIN ordenes o ON o.codigo = r.orden_codigo
     JOIN pacientes p ON p.codigo = o.paciente_codigo
     WHERE he.error_muestra LIKE '%RECHAZADO%'
     ORDER BY he.codigo DESC
     LIMIT 5"
);
$ultimosRechazos = $stmt->fetchAll();

$tituloPagina = 'Panel principal | CEBAC';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Panel principal</h1>
        <p class="text-muted mb-0">Resumen del día en el laboratorio.</p>
    </div>
</div>

<!-- TARJETAS DE ESTADÍSTICAS -->
<div class="row g-3 mb-4">
    <div class="col-md-4 col-lg-2">
        <a href="pacientes.php" class="card h-100 text-decoration-none shadow-sm border-0 border-start border-4 border-primary">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-people fs-2 text-primary"></i>
                    <div>
                        <small class="text-muted text-uppercase fw-bold">Pacientes</small>
                        <div class="fs-3 fw-bold text-dark"><?= $totalPacientes ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4 col-lg-2">
        <a href="ordenes.php" class="card h-100 text-decoration-none shadow-sm border-0 border-start border-4 border-primary">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-file-earmark-medical fs-2 text-primary"></i>
                    <div>
                        <small class="text-muted text-uppercase fw-bold">Órdenes hoy</small>
                        <div class="fs-3 fw-bold text-dark"><?= $ordenesHoy ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4 col-lg-2">
        <a href="recepcion_muestras.php" class="card h-100 text-decoration-none shadow-sm border-0 border-start border-4 border-warning">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-clipboard-check fs-2 text-warning"></i>
                    <div>
                        <small class="text-muted text-uppercase fw-bold">Pendientes</small>
                        <div class="fs-3 fw-bold text-dark"><?= $ordenesPendientes ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4 col-lg-2">
        <a href="reporte_muestras_rechazadas.php" class="card h-100 text-decoration-none shadow-sm border-0 border-start border-4 border-danger">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-exclamation-triangle fs-2 text-danger"></i>
                    <div>
                        <small class="text-muted text-uppercase fw-bold">Rechazos hoy</small>
                        <div class="fs-3 fw-bold text-dark"><?= $rechazosHoy ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4 col-lg-2">
        <a href="caja.php" class="card h-100 text-decoration-none shadow-sm border-0 border-start border-4 border-success">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-cash-coin fs-2 text-success"></i>
                    <div>
                        <small class="text-muted text-uppercase fw-bold">Cobrado hoy</small>
                        <div class="fs-6 fw-bold text-dark">$ <?= number_format($cobradoHoy, 0, ',', '.') ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4 col-lg-2">
        <a href="resultados.php" class="card h-100 text-decoration-none shadow-sm border-0 border-start border-4 border-info">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-file-earmark-text fs-2 text-info"></i>
                    <div>
                        <small class="text-muted text-uppercase fw-bold">Resultados</small>
                        <div class="fs-3 fw-bold text-dark"><?= $resultadosPendientes ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- ACCESOS RÁPIDOS -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 fw-bold mb-3">Accesos rápidos</h2>
        <div class="row g-2">
            <div class="col-6 col-md-4 col-lg-2">
                <a href="paciente_formulario.php" class="btn btn-outline-primary w-100 py-3">
                    <i class="bi bi-person-plus fs-4 d-block mb-1"></i>
                    <small>Nuevo paciente</small>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="orden_formulario.php" class="btn btn-outline-primary w-100 py-3">
                    <i class="bi bi-file-earmark-plus fs-4 d-block mb-1"></i>
                    <small>Nueva orden</small>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="recepcion_muestras.php" class="btn btn-outline-primary w-100 py-3">
                    <i class="bi bi-clipboard-check fs-4 d-block mb-1"></i>
                    <small>Check-in muestras</small>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="caja.php" class="btn btn-outline-primary w-100 py-3">
                    <i class="bi bi-cash-coin fs-4 d-block mb-1"></i>
                    <small>Registrar pago</small>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="obras_sociales.php" class="btn btn-outline-primary w-100 py-3">
                    <i class="bi bi-shield-check fs-4 d-block mb-1"></i>
                    <small>Obras sociales</small>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="reportes.php" class="btn btn-outline-primary w-100 py-3">
                    <i class="bi bi-bar-chart fs-4 d-block mb-1"></i>
                    <small>Reportes</small>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- LISTADOS -->
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h3 class="h6 fw-bold mb-0">Últimas órdenes</h3>
                <a href="ordenes.php" class="small text-decoration-none">Ver todas →</a>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($ultimasOrdenes as $o): ?>
                    <a href="orden_detalle.php?id=<?= (int) $o['orden_codigo'] ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong>#<?= formatear_codigo_orden($o['orden_codigo']) ?></strong>
                            <span class="badge bg-secondary"><?= escapar_html($o['estado']) ?></span>
                        </div>
                        <small class="text-muted"><?= escapar_html($o['apellido'] . ', ' . $o['nombre']) ?> · <?= escapar_html($o['fecha_orden']) ?></small>
                    </a>
                <?php endforeach; ?>
                <?php if (!$ultimasOrdenes): ?>
                    <div class="list-group-item text-muted text-center py-4">No hay órdenes.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h3 class="h6 fw-bold mb-0">Últimos pagos</h3>
                <a href="caja.php" class="small text-decoration-none">Ver todos →</a>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($ultimosPagos as $p): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong class="text-success">$ <?= number_format((float) $p['monto'], 2, ',', '.') ?></strong>
                            <span class="badge bg-secondary"><?= escapar_html($p['metodo_pago']) ?></span>
                        </div>
                        <small class="text-muted"><?= escapar_html($p['apellido'] . ', ' . $p['nombre']) ?> · <?= escapar_html($p['fecha_pago']) ?></small>
                    </div>
                <?php endforeach; ?>
                <?php if (!$ultimosPagos): ?>
                    <div class="list-group-item text-muted text-center py-4">No hay pagos.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h3 class="h6 fw-bold mb-0">Últimos rechazos</h3>
                <a href="reporte_muestras_rechazadas.php" class="small text-decoration-none">Ver todos →</a>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($ultimosRechazos as $r): ?>
                    <div class="list-group-item">
                        <strong class="text-danger small"><?= escapar_html($r['error_muestra']) ?></strong>
                        <small class="text-muted d-block"><?= escapar_html($r['apellido'] . ', ' . $r['nombre']) ?> · #<?= formatear_codigo_orden($r['orden_codigo']) ?></small>
                    </div>
                <?php endforeach; ?>
                <?php if (!$ultimosRechazos): ?>
                    <div class="list-group-item text-muted text-center py-4">No hay rechazos.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>