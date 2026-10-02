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

// Estudios de la orden
$stmt = $conexion->prepare(
    "SELECT e.codigo, e.estudio, e.parametros
     FROM estudios_orden eo
     JOIN estudios e ON e.codigo = eo.estudio_codigo
     WHERE eo.orden_codigo = :codigo
     ORDER BY e.estudio"
);
$stmt->execute(['codigo' => $ordenId]);
$estudios = $stmt->fetchAll();

// Resultado asociado (si existe)
$stmt = $conexion->prepare('SELECT codigo, resultado FROM resultados WHERE orden_codigo = :codigo LIMIT 1');
$stmt->execute(['codigo' => $ordenId]);
$resultado = $stmt->fetch();
$resultadoCodigo = (int) ($resultado['codigo'] ?? 0);

// Estudios pendientes (con error "PENDIENTE: X")
$estudiosPendientes = [];
if ($resultadoCodigo > 0) {
    $stmtErr = $conexion->prepare("SELECT error_muestra FROM historial_errores WHERE resultado_codigo = :codigo AND error_muestra LIKE 'PENDIENTE:%'");
    $stmtErr->execute(['codigo' => $resultadoCodigo]);
    foreach ($stmtErr->fetchAll() as $e) {
        $nombre = trim(substr($e['error_muestra'], strlen('PENDIENTE:')));
        $nombre = preg_replace('/\s*\(.*\)$/', '', $nombre);
        $estudiosPendientes[$nombre] = $e['error_muestra'];
    }
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $estados = $_POST['estado'] ?? [];

    try {
        $conexion->beginTransaction();

        // Asegurar registro en resultados
        if ($resultadoCodigo === 0) {
            $stmtRes = $conexion->prepare('INSERT INTO resultados (resultado, fecha_resultado, orden_codigo) VALUES (NULL, :fecha, :orden)');
            $stmtRes->execute(['fecha' => date('Y-m-d'), 'orden' => $ordenId]);
            $resultadoCodigo = (int) $conexion->lastInsertId();
        }

        // Borrar errores previos tipo PENDIENTE (se vuelven a insertar según corresponda)
        $stmtDel = $conexion->prepare("DELETE FROM historial_errores WHERE resultado_codigo = :codigo AND error_muestra LIKE 'PENDIENTE:%'");
        $stmtDel->execute(['codigo' => $resultadoCodigo]);

        // ✅ CORREGIDO: INSERT INTO (no FROM)
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

        if ($hayPendientes) {
            mensaje_flash('Check-in guardado. La orden queda pendiente hasta que lleguen todas las muestras.');
        } else {
            mensaje_flash('Check-in completo. Todas las muestras fueron validadas.');
        }
        header('Location: recepcion_muestras.php');
        exit;
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        $error = 'No se pudo procesar el check-in: ' . $e->getMessage();
    }
}

$tituloPagina = 'Check-in de orden ' . formatear_codigo_orden($orden['orden_codigo']) . ' | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container">
<p><a href="recepcion_muestras.php">← Volver a Check-in de Muestras</a></p>
<h1>Check-in de muestra</h1>

<div class="module">
    <p><strong>Orden:</strong> <?= formatear_codigo_orden($orden['orden_codigo']) ?></p>
    <p><strong>Paciente:</strong> <?= escapar_html($orden['apellido'] . ', ' . $orden['nombre']) ?> · DNI <?= escapar_html($orden['dni']) ?></p>
    <p><strong>Fecha orden:</strong> <?= escapar_html($orden['fecha_orden']) ?></p>
    <p><strong>Médico:</strong> <?= escapar_html($orden['medico']) ?></p>
</div>

<?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?>

<form method="post" id="form-checkin">
    <input type="hidden" name="orden_id" value="<?= $ordenId ?>">

    <h2>Estudios a validar</h2>
    <p class="muted">Marcá cada muestra como <strong>Validada</strong> o dejala <strong>Pendiente</strong>. Por defecto todas arrancan en Pendiente.</p>

    <div class="checkin-estudios-lista">
        <?php foreach ($estudios as $est): ?>
            <?php
            $codigoEst = $est['codigo'];
            $nombreEst = $est['estudio'];

            $estadoInicial = 'pendiente';
            if ($resultadoCodigo > 0 && !isset($estudiosPendientes[$nombreEst])) {
                $estadoInicial = 'validar';
            }
            ?>
            <div class="checkin-estudio" data-cod="<?= escapar_html($codigoEst) ?>">
                <div class="checkin-estudio-info">
                    <strong><?= escapar_html($nombreEst) ?></strong>
                    <small class="muted"><?= escapar_html($codigoEst) ?> · Parámetros: <?= escapar_html($est['parametros'] ?? '—') ?></small>
                </div>

                <div class="checkin-estudio-acciones">
                    <label class="checkin-radio">
                        <input type="radio" name="estado[<?= escapar_html($codigoEst) ?>]" value="validar" data-cod="<?= escapar_html($codigoEst) ?>" <?= $estadoInicial === 'validar' ? 'checked' : '' ?>>
                        <span class="radio-label radio-validar">✓ Validar muestra</span>
                    </label>
                    <label class="checkin-radio">
                        <input type="radio" name="estado[<?= escapar_html($codigoEst) ?>]" value="pendiente" data-cod="<?= escapar_html($codigoEst) ?>" <?= $estadoInicial === 'pendiente' ? 'checked' : '' ?>>
                        <span class="radio-label radio-pendiente">— Pendiente</span>
                    </label>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="checkin-acciones-finales">
        <button type="button" class="button-validar-todo" id="btn-validar-todo">✓ Validar todas las muestras</button>
        <button type="submit" class="button-save">Guardar check-in</button>
    </div>
</form>
</main>

<script>
(function () {
    'use strict';

    const btnValidarTodo = document.getElementById('btn-validar-todo');
    if (btnValidarTodo) {
        btnValidarTodo.addEventListener('click', function () {
            document.querySelectorAll('.checkin-estudio').forEach(function (box) {
                const radioValidar = box.querySelector('input[type="radio"][value="validar"]');
                if (radioValidar) radioValidar.checked = true;
            });
        });
    }
})();
</script>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>