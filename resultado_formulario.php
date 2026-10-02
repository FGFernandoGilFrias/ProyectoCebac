<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('resultados.gestionar');

$ordenId = (int) ($_GET['orden_id'] ?? $_POST['orden_id'] ?? 0);
$conexion = conexion_bd();

$stmt = $conexion->prepare(
    "SELECT o.codigo AS orden_codigo, o.fecha_orden, o.estado, o.medico,
            p.codigo AS paciente_codigo, p.apellido, p.nombre, p.dni, p.fecha_nacimiento,
            os.nombre AS obra_social,
            c.nro_credencial, c.cobertura, c.porcentaje_cobertura
     FROM ordenes o
     JOIN pacientes p ON p.codigo = o.paciente_codigo
     LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
     LEFT JOIN obras_sociales_credenciales c ON c.codigo = pos.credencial_codigo
     LEFT JOIN obras_sociales os ON os.codigo = c.obra_social_codigo
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
    "SELECT e.codigo, e.estudio, e.parametros, pp.precio
     FROM estudios_orden eo
     JOIN estudios e ON e.codigo = eo.estudio_codigo
     LEFT JOIN precio_practica pp ON pp.codigo = e.practica_codigo
     WHERE eo.orden_codigo = :codigo
     ORDER BY e.estudio"
);
$stmt->execute(['codigo' => $ordenId]);
$estudios = $stmt->fetchAll();

// Resultado actual
$stmt = $conexion->prepare('SELECT codigo, resultado, fecha_resultado FROM resultados WHERE orden_codigo = :codigo LIMIT 1');
$stmt->execute(['codigo' => $ordenId]);
$resultadoActual = $stmt->fetch();
$resultadoCodigo = (int) ($resultadoActual['codigo'] ?? 0);

// Estudios pendientes (con error "PENDIENTE:")
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

// Verificar si TODOS los estudios están pendientes
$totalEstudios = count($estudios);
$cantidadPendientes = count($estudiosPendientes);

if ($totalEstudios > 0 && $cantidadPendientes === $totalEstudios) {
    mensaje_flash('Esta orden no tiene ninguna muestra validada todavía. Completá el check-in primero.');
    header('Location: recepcion_muestras.php');
    exit;
}

// Parsear valores actuales
$valoresActuales = [];
if ($resultadoActual && !empty($resultadoActual['resultado'])) {
    foreach (preg_split('/\R/', $resultadoActual['resultado']) as $linea) {
        $partes = explode(':', $linea, 2);
        if (count($partes) === 2) {
            $valoresActuales[trim($partes[0])] = trim($partes[1]);
        }
    }
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valores = $_POST['valor'] ?? [];
    $rechazados = $_POST['rechazado'] ?? [];
    $motivos = $_POST['motivo_rechazo'] ?? [];

    $lineas = [];
    $erroresRechazo = [];

    foreach ($estudios as $est) {
        $codigoEst = $est['codigo'];
        $nombreEst = $est['estudio'];

        // Si está pendiente de validación, se salta
        if (isset($estudiosPendientes[$nombreEst])) {
            continue;
        }

        // Si está rechazado
        if (!empty($rechazados[$codigoEst])) {
            $motivo = trim((string) ($motivos[$codigoEst] ?? ''));
            if ($motivo === '') {
                $error = 'Debés indicar el motivo del rechazo para "' . $nombreEst . '".';
                break;
            }
            $erroresRechazo[] = $nombreEst . ': RECHAZADO - ' . $motivo;
            $lineas[] = $nombreEst . ': RECHAZADO (' . $motivo . ')';
            continue;
        }

        // Si tiene valor cargado
        $valor = trim((string) ($valores[$codigoEst] ?? ''));
        if ($valor === '') {
            $valorPrevio = $valoresActuales[$nombreEst] ?? '';
            if ($valorPrevio !== '') {
                $lineas[] = $nombreEst . ': ' . $valorPrevio;
            }
            continue;
        }
        $lineas[] = $nombreEst . ': ' . $valor;
    }

    if (!$error) {
        try {
            $conexion->beginTransaction();

            $textoResultado = implode("\n", $lineas);
            $fechaHoy = date('Y-m-d');

            if ($resultadoActual) {
                $stmtUpd = $conexion->prepare('UPDATE resultados SET resultado = :resultado, fecha_resultado = :fecha WHERE codigo = :codigo');
                $stmtUpd->execute([
                    'resultado' => $textoResultado,
                    'fecha' => $fechaHoy,
                    'codigo' => $resultadoCodigo,
                ]);
                // Borrar rechazos previos
                $conexion->prepare("DELETE FROM historial_errores WHERE resultado_codigo = :codigo AND error_muestra LIKE '%RECHAZADO%'")->execute(['codigo' => $resultadoCodigo]);
            } else {
                $stmtIns = $conexion->prepare('INSERT INTO resultados (resultado, fecha_resultado, orden_codigo) VALUES (:resultado, :fecha, :orden)');
                $stmtIns->execute([
                    'resultado' => $textoResultado,
                    'fecha' => $fechaHoy,
                    'orden' => $ordenId,
                ]);
                $resultadoCodigo = (int) $conexion->lastInsertId();
            }

            // Insertar errores de rechazo
            if (!empty($erroresRechazo)) {
                $stmtErr = $conexion->prepare('INSERT INTO historial_errores (error_muestra, resultado_codigo) VALUES (:error, :resultado)');
                foreach ($erroresRechazo as $err) {
                    $stmtErr->execute(['error' => $err, 'resultado' => $resultadoCodigo]);
                }
            }

            $conexion->commit();

            actualizar_estado_orden($ordenId);

            mensaje_flash('Resultado guardado correctamente.');
            header('Location: resultado_formulario.php?orden_id=' . $ordenId);
            exit;
        } catch (Throwable $e) {
            if ($conexion->inTransaction()) $conexion->rollBack();
            $error = 'No se pudo guardar el resultado: ' . $e->getMessage();
        }
    }
}

$tituloPagina = 'Resultado de orden ' . formatear_codigo_orden($orden['orden_codigo']) . ' | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container">
<p><a href="resultados.php">← Volver a resultados</a></p>

<div class="page-heading">
    <div>
        <h1>Resultado de orden <?= formatear_codigo_orden($orden['orden_codigo']) ?></h1>
        <p class="muted">
            Paciente: <?= escapar_html($orden['apellido'] . ', ' . $orden['nombre']) ?> ·
            DNI: <?= escapar_html($orden['dni']) ?> ·
            Obra social: <?= escapar_html($orden['obra_social'] ?? 'Particular') ?>
        </p>
    </div>
    <?php if ($resultadoActual && !empty($resultadoActual['resultado'])): ?>
        <a class="button-secondary" href="resultado_pdf.php?orden_id=<?= $ordenId ?>" target="_blank">📄 Imprimir resultado</a>
    <?php endif; ?>
</div>

<?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?>

<?php if ($resultadoActual && !empty($resultadoActual['resultado'])): ?>
    <div class="envios-historial">
        <strong>Resultado guardado el:</strong>
        <span class="badge success"><?= escapar_html($resultadoActual['fecha_resultado']) ?></span>
    </div>
<?php endif; ?>

<?php if (!empty($estudiosPendientes)): ?>
    <div class="alert warning-alert">
        <strong>⏳ Hay estudios pendientes de muestra:</strong>
        <ul>
            <?php foreach ($estudiosPendientes as $nombre => $err): ?>
                <li><?= escapar_html($nombre) ?></li>
            <?php endforeach; ?>
        </ul>
        Podés cargar los resultados de los estudios validados. Los pendientes se habilitarán cuando llegue la muestra.
    </div>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="orden_id" value="<?= $ordenId ?>">

    <div class="result-grid-wrap">
        <table class="result-grid">
            <thead>
                <tr>
                    <th>Estudio</th>
                    <th>Parámetros</th>
                    <th>Precio</th>
                    <th>Valor / Resultado</th>
                    <th class="col-rechazo">Rechazar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($estudios as $est): ?>
                    <?php
                    $codigoEst = $est['codigo'];
                    $nombreEst = $est['estudio'];
                    $estaPendiente = isset($estudiosPendientes[$nombreEst]);
                    $valorActual = $valoresActuales[$nombreEst] ?? '';
                    ?>
                    <tr class="result-param-row <?= $estaPendiente ? 'fila-pendiente' : '' ?>">
                        <td>
                            <strong><?= escapar_html($nombreEst) ?></strong><br>
                            <small class="muted"><?= escapar_html($codigoEst) ?></small>
                            <?php if ($estaPendiente): ?>
                                <br><span class="badge warning">Pendiente de muestra</span>
                            <?php endif; ?>
                        </td>
                        <td><?= escapar_html($est['parametros'] ?? '—') ?></td>
                        <td>$ <?= number_format((float) ($est['precio'] ?? 0), 2, ',', '.') ?></td>
                        <td>
                            <?php if ($estaPendiente): ?>
                                <span class="texto-pendiente">⏳ Muestra sin validar. No se puede cargar el resultado hasta que llegue la muestra.</span>
                            <?php else: ?>
                                <input type="text" name="valor[<?= escapar_html($codigoEst) ?>]" value="<?= escapar_html($valorActual) ?>" placeholder="Ej: 36" data-cod="<?= escapar_html($codigoEst) ?>" class="input-valor">
                            <?php endif; ?>
                        </td>
                        <td class="col-rechazo">
                            <?php if ($estaPendiente): ?>
                                <span class="texto-pendiente">—</span>
                            <?php else: ?>
                                <input type="checkbox" class="rechazo-check" name="rechazado[<?= escapar_html($codigoEst) ?>]" value="1" data-cod="<?= escapar_html($codigoEst) ?>">
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (!$estaPendiente): ?>
                        <tr class="result-motivo-row" data-motivo="<?= escapar_html($codigoEst) ?>" hidden>
                            <td colspan="5">
                                <label class="motivo-rechazo-label">
                                    Motivo del rechazo *
                                    <input type="text" name="motivo_rechazo[<?= escapar_html($codigoEst) ?>]" placeholder="Ej: muestra hemolizada, cantidad insuficiente" class="motivo-rechazo">
                                </label>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (!$estudios): ?>
                    <tr><td colspan="5" class="muted">Esta orden no tiene estudios asociados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($estudios): ?>
        <div class="result-actions-bar">
            <button type="submit" class="button-save">Guardar resultado</button>
        </div>
    <?php endif; ?>
</form>
</main>

<script>
(function () {
    'use strict';

    function actualizarEstadoEstudio(codigo) {
        const checkbox = document.querySelector('input.rechazo-check[data-cod="' + codigo + '"]');
        const motivoRow = document.querySelector('tr[data-motivo="' + codigo + '"]');
        const inputValor = document.querySelector('input.input-valor[data-cod="' + codigo + '"]');

        if (!checkbox) return;
        const marcado = checkbox.checked;

        if (motivoRow) {
            motivoRow.hidden = !marcado;
            const motivoInput = motivoRow.querySelector('.motivo-rechazo');
            if (motivoInput) {
                motivoInput.required = marcado;
                if (marcado) motivoInput.focus();
            }
        }

        if (inputValor) {
            inputValor.disabled = marcado;
            if (marcado) inputValor.value = '';
        }
    }

    document.querySelectorAll('input.rechazo-check').forEach(function (cb) {
        actualizarEstadoEstudio(cb.dataset.cod);
        cb.addEventListener('change', function () {
            actualizarEstadoEstudio(this.dataset.cod);
        });
    });
})();
</script>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>