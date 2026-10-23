<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('muestras.gestionar');

$ordenId = (int) ($_GET['orden_id'] ?? $_POST['orden_id'] ?? 0);
$conexion = conexion_bd();

$stmt = $conexion->prepare(
    "SELECT o.codigo AS orden_codigo, o.fecha_orden, o.medico, o.estado,
            p.apellido, p.nombre, p.dni
     FROM ordenes o
     JOIN pacientes p ON p.codigo = o.paciente_codigo
     WHERE o.codigo = :codigo"
);
$stmt->execute(['codigo' => $ordenId]);
$orden = $stmt->fetch();

if (!$orden) {
    http_response_code(404);
    exit('Orden no encontrada.');
}

$stmt = $conexion->prepare(
    "SELECT e.codigo, e.estudio, e.parametros
     FROM estudios_orden eo
     JOIN estudios e ON e.codigo = eo.estudio_codigo
     WHERE eo.orden_codigo = :codigo
     ORDER BY e.estudio"
);
$stmt->execute(['codigo' => $ordenId]);
$estudios = $stmt->fetchAll();

$stmt = $conexion->prepare('SELECT codigo FROM resultados WHERE orden_codigo = :codigo LIMIT 1');
$stmt->execute(['codigo' => $ordenId]);
$resultado = $stmt->fetch();
$resultadoCodigo = (int) ($resultado['codigo'] ?? 0);

$errores = [];
if ($resultadoCodigo > 0) {
    $stmtErr = $conexion->prepare("SELECT error_muestra FROM historial_errores WHERE resultado_codigo = :codigo");
    $stmtErr->execute(['codigo' => $resultadoCodigo]);
    $errores = $stmtErr->fetchAll(PDO::FETCH_COLUMN);
}

$estudiosPendientes = [];
$estudiosRechazados = [];
foreach ($estudios as $est) {
    $nombreEst = $est['estudio'];
    foreach ($errores as $err) {
        if (stripos($err, $nombreEst) === false) continue;
        if (stripos($err, 'RECHAZADO') !== false) {
            $estudiosRechazados[$nombreEst] = $err;
        } elseif (stripos($err, 'PENDIENTE') !== false) {
            $estudiosPendientes[$nombreEst] = $err;
        }
    }
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $estados = $_POST['estado'] ?? [];

    try {
        $conexion->beginTransaction();

        if ($resultadoCodigo === 0) {
            $stmtRes = $conexion->prepare('INSERT INTO resultados (resultado, fecha_resultado, orden_codigo) VALUES (NULL, :fecha, :orden)');
            $stmtRes->execute(['fecha' => date('Y-m-d'), 'orden' => $ordenId]);
            $resultadoCodigo = (int) $conexion->lastInsertId();
        }

        $stmtDel = $conexion->prepare("DELETE FROM historial_errores WHERE resultado_codigo = :codigo");
        $stmtDel->execute(['codigo' => $resultadoCodigo]);

        $stmtInsErr = $conexion->prepare('INSERT INTO historial_errores (error_muestra, resultado_codigo) VALUES (:error, :resultado)');

        $hayPendientes = false;
        foreach ($estudios as $est) {
            $codigoEst = $est['codigo'];
            $nombreEst = $est['estudio'];
            $estadoEst = $estados[$codigoEst] ?? 'pendiente';

            if ($estadoEst !== 'validar') {
                $texto = 'PENDIENTE: ' . $nombreEst;
                $stmtInsErr->execute(['error' => $texto, 'resultado' => $resultadoCodigo]);
                $hayPendientes = true;
            }
        }

        actualizar_estado_orden($ordenId);
        $conexion->commit();

        if (isset($_POST['_modal']) && $_POST['_modal'] === '1') {
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => true,
                'mensaje' => $hayPendientes ? 'Check-in guardado. Quedan muestras pendientes.' : 'Check-in completo.',
            ]);
            exit;
        }

        mensaje_flash($hayPendientes ? 'Check-in guardado. Quedan muestras pendientes.' : 'Check-in completo.');
        header('Location: recepcion_muestras.php');
        exit;
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        $error = 'No se pudo procesar el check-in: ' . $e->getMessage();
    }

    if (isset($_POST['_modal']) && $_POST['_modal'] === '1') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $error]);
        exit;
    }
}

$esModal = isset($_GET['_modal']) && $_GET['_modal'] === '1';

if ($esModal) {
    ?>
    <form method="post" id="checkin-modal-form">
        <input type="hidden" name="_modal" value="1">
        <input type="hidden" name="orden_id" value="<?= $ordenId ?>">

        <div class="card bg-light border-0 mb-3">
            <div class="card-body py-2">
                <div class="row g-2">
                    <div class="col-md-3">
                        <small class="text-muted d-block">Orden</small>
                        <strong>#<?= formatear_codigo_orden($orden['orden_codigo']) ?></strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Paciente</small>
                        <strong><?= escapar_html($orden['apellido'] . ', ' . $orden['nombre']) ?></strong>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted d-block">DNI</small>
                        <strong><?= escapar_html($orden['dni']) ?></strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Fecha / Médico</small>
                        <strong><?= escapar_html($orden['fecha_orden']) ?> · <?= escapar_html($orden['medico']) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($estudiosRechazados)): ?>
            <div class="alert alert-warning">
                <h6 class="fw-bold"><i class="bi bi-exclamation-triangle me-1"></i>Estudios rechazados:</h6>
                <ul class="mb-0">
                    <?php foreach ($estudiosRechazados as $nombre => $err): ?>
                        <li><strong><?= escapar_html($nombre) ?></strong></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= escapar_html($error) ?></div>
        <?php endif; ?>

        <p class="text-muted small">Marcá cada muestra como <strong>Validada</strong> o dejala <strong>Pendiente</strong>.</p>

        <div class="checkin-estudios-lista">
            <?php foreach ($estudios as $est): ?>
                <?php
                $codigoEst = $est['codigo'];
                $nombreEst = $est['estudio'];
                $estaRechazado = isset($estudiosRechazados[$nombreEst]);
                $estaPendiente = isset($estudiosPendientes[$nombreEst]);

                $estadoInicial = 'pendiente';
                if (!$estaRechazado && !$estaPendiente && $resultadoCodigo > 0) {
                    $estadoInicial = 'validar';
                }
                ?>
                <div class="checkin-estudio border rounded p-3 mb-2">
                    <div class="d-flex flex-column gap-1 mb-2">
                        <strong><?= escapar_html($nombreEst) ?></strong>
                        <small class="text-muted"><?= escapar_html($codigoEst) ?> · Parámetros: <?= escapar_html($est['parametros'] ?? '—') ?></small>
                        <?php if ($estaRechazado): ?>
                            <span class="badge bg-danger align-self-start">Rechazado — requiere segundo análisis</span>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex flex-wrap gap-3 align-items-center">
                        <label class="checkin-radio d-inline-flex align-items-center gap-2 mb-0">
                            <input type="radio" name="estado[<?= escapar_html($codigoEst) ?>]" value="validar" <?= $estadoInicial === 'validar' ? 'checked' : '' ?>>
                            <span class="radio-label radio-validar">✓ Validar muestra</span>
                        </label>
                        <label class="checkin-radio d-inline-flex align-items-center gap-2 mb-0">
                            <input type="radio" name="estado[<?= escapar_html($codigoEst) ?>]" value="pendiente" <?= $estadoInicial === 'pendiente' ? 'checked' : '' ?>>
                            <span class="radio-label radio-pendiente">— Pendiente</span>
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </form>
    <?php
    exit;
}

$tituloPagina = 'Check-in de orden ' . formatear_codigo_orden($orden['orden_codigo']) . ' | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="main-content">
    <h1>Check-in</h1>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>