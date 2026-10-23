<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/resultado_notificar.php';
exigir_privilegio('resultados.gestionar');

$ordenId = (int) ($_GET['orden_id'] ?? $_POST['orden_id'] ?? 0);
$conexion = conexion_bd();

$stmt = $conexion->prepare(
    "SELECT o.codigo AS orden_codigo, o.fecha_orden, o.estado, o.medico,
            p.codigo AS paciente_codigo, p.apellido, p.nombre, p.dni, p.fecha_nacimiento, p.correo,
            os.nombre AS obra_social,
            pos.nro_credencial, pos.cobertura, pos.porcentaje_cobertura
     FROM ordenes o
     JOIN pacientes p ON p.codigo = o.paciente_codigo
     LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
     LEFT JOIN obras_sociales os ON os.codigo = pos.obra_social_codigo
     WHERE o.codigo = :codigo"
);
$stmt->execute(['codigo' => $ordenId]);
$orden = $stmt->fetch();

if (!$orden) {
    http_response_code(404);
    exit('Orden no encontrada.');
}

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

$stmt = $conexion->prepare('SELECT codigo, resultado, fecha_resultado FROM resultados WHERE orden_codigo = :codigo LIMIT 1');
$stmt->execute(['codigo' => $ordenId]);
$resultadoActual = $stmt->fetch();
$resultadoCodigo = (int) ($resultadoActual['codigo'] ?? 0);

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

$totalEstudios = count($estudios);
$cantidadPendientes = count($estudiosPendientes);

if ($totalEstudios > 0 && $cantidadPendientes === $totalEstudios) {
    if (isset($_GET['_modal']) && $_GET['_modal'] === '1') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Esta orden no tiene ninguna muestra validada todavía.']);
        exit;
    }
    mensaje_flash('Esta orden no tiene ninguna muestra validada todavía.');
    header('Location: recepcion_muestras.php');
    exit;
}

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

        if (isset($estudiosPendientes[$nombreEst])) continue;
        if (isset($estudiosRechazados[$nombreEst])) continue;

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

    foreach ($estudiosRechazados as $nombre => $err) {
        $motivo = trim(preg_replace('/.*RECHAZADO[:\s-]*/i', '', $err));
        $lineas[] = $nombre . ': RECHAZADO (' . $motivo . ')';
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
            } else {
                $stmtIns = $conexion->prepare('INSERT INTO resultados (resultado, fecha_resultado, orden_codigo) VALUES (:resultado, :fecha, :orden)');
                $stmtIns->execute([
                    'resultado' => $textoResultado,
                    'fecha' => $fechaHoy,
                    'orden' => $ordenId,
                ]);
                $resultadoCodigo = (int) $conexion->lastInsertId();
            }

            if (!empty($erroresRechazo)) {
                $stmtErr = $conexion->prepare('INSERT INTO historial_errores (error_muestra, resultado_codigo) VALUES (:error, :resultado)');
                foreach ($erroresRechazo as $err) {
                    $stmtErr->execute(['error' => $err, 'resultado' => $resultadoCodigo]);
                }
            }

            $conexion->commit();

            // Actualizar el estado de la orden
            actualizar_estado_orden($ordenId);

            // ✅ NUEVO: Intentar enviar el email de resultados si están listos
            $resultadoEnvio = ['enviados' => [], 'errores' => [], 'info' => []];
            if (function_exists('notificar_orden')) {
                $resultadoEnvio = notificar_orden($ordenId, false, false);
            }

            if (isset($_POST['_modal']) && $_POST['_modal'] === '1') {
                header('Content-Type: application/json');
                echo json_encode([
                    'ok' => true,
                    'mensaje' => 'Resultado guardado correctamente.',
                    'envio' => $resultadoEnvio,
                ]);
                exit;
            }

            // Mensaje al usuario según el resultado del envío
            $mensaje = 'Resultado guardado correctamente.';
            if (!empty($resultadoEnvio['enviados'])) {
                $mensaje .= ' Se enviaron los resultados por email al paciente.';
            } elseif (!empty($resultadoEnvio['info'])) {
                $mensaje .= ' ' . implode(' ', $resultadoEnvio['info']);
            } elseif (!empty($resultadoEnvio['errores'])) {
                $mensaje .= ' Aviso: ' . implode(' ', $resultadoEnvio['errores']);
            }

            mensaje_flash($mensaje);
            header('Location: resultado_formulario.php?orden_id=' . $ordenId);
            exit;
        } catch (Throwable $e) {
            if ($conexion->inTransaction()) $conexion->rollBack();
            $error = 'No se pudo guardar el resultado: ' . $e->getMessage();
        }
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
    <form method="post" id="resultado-modal-form">
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
                    <div class="col-md-5">
                        <small class="text-muted d-block">Obra social / Fecha</small>
                        <strong><?= escapar_html($orden['obra_social'] ?? 'Particular') ?> · <?= escapar_html($orden['fecha_orden']) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= escapar_html($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($estudiosRechazados)): ?>
            <div class="alert alert-warning">
                <h6 class="fw-bold"><i class="bi bi-exclamation-triangle me-1"></i>Estudios rechazados:</h6>
                <ul class="mb-0 small">
                    <?php foreach ($estudiosRechazados as $nombre => $err): ?>
                        <li><strong><?= escapar_html($nombre) ?></strong></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($estudiosPendientes)): ?>
            <div class="alert alert-warning small">
                <strong>⏳ Pendientes:</strong> <?= escapar_html(implode(', ', array_keys($estudiosPendientes))) ?>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Estudio</th>
                        <th>Parámetros</th>
                        <th class="text-end">Precio</th>
                        <th>Resultado</th>
                        <th class="text-center">Rechazar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($estudios as $est): ?>
                        <?php
                        $codigoEst = $est['codigo'];
                        $nombreEst = $est['estudio'];
                        $estaPendiente = isset($estudiosPendientes[$nombreEst]);
                        $estaRechazado = isset($estudiosRechazados[$nombreEst]);
                        $valorActual = $valoresActuales[$nombreEst] ?? '';
                        $claseFila = '';
                        if ($estaPendiente) $claseFila = 'table-warning';
                        if ($estaRechazado) $claseFila = 'table-danger';
                        ?>
                        <tr class="<?= $claseFila ?>">
                            <td>
                                <strong><?= escapar_html($nombreEst) ?></strong>
                                <small class="text-muted d-block"><?= escapar_html($codigoEst) ?></small>
                            </td>
                            <td><small><?= escapar_html($est['parametros'] ?? '—') ?></small></td>
                            <td class="text-end">$ <?= number_format((float) ($est['precio'] ?? 0), 2, ',', '.') ?></td>
                            <td>
                                <?php if ($estaPendiente): ?>
                                    <span class="text-warning fst-italic small">⏳ Sin validar</span>
                                <?php elseif ($estaRechazado): ?>
                                    <span class="text-danger fw-bold small">❌ Rechazado</span>
                                <?php else: ?>
                                    <input type="text" name="valor[<?= escapar_html($codigoEst) ?>]" value="<?= escapar_html($valorActual) ?>" placeholder="Ej: 36" class="form-control form-control-sm input-valor" data-cod="<?= escapar_html($codigoEst) ?>">
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($estaPendiente || $estaRechazado): ?>
                                    <span class="text-muted">—</span>
                                <?php else: ?>
                                    <input type="checkbox" class="form-check-input rechazo-check"
                                           name="rechazado[<?= escapar_html($codigoEst) ?>]"
                                           value="1"
                                           data-cod="<?= escapar_html($codigoEst) ?>"
                                           onchange="toggleMotivo('<?= escapar_html($codigoEst) ?>', this.checked)">
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!$estaPendiente && !$estaRechazado): ?>
                            <tr class="result-motivo-row" data-motivo="<?= escapar_html($codigoEst) ?>" style="display: none;">
                                <td colspan="5" class="bg-danger bg-opacity-10">
                                    <label class="form-label small text-danger fw-bold mb-1">Motivo del rechazo *</label>
                                    <input type="text" name="motivo_rechazo[<?= escapar_html($codigoEst) ?>]" placeholder="Ej: muestra hemolizada" class="form-control form-control-sm motivo-rechazo">
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>

    <script>
    function toggleMotivo(codigo, marcado) {
        var row = document.querySelector('tr[data-motivo="' + codigo + '"]');
        if (!row) return;

        row.style.display = marcado ? 'table-row' : 'none';

        var input = row.querySelector('.motivo-rechazo');
        if (input) {
            input.required = marcado;
            if (marcado) input.focus();
        }

        var valor = document.querySelector('input.input-valor[data-cod="' + codigo + '"]');
        if (valor) {
            valor.disabled = marcado;
            if (marcado) valor.value = '';
        }
    }
    </script>
    <?php
    exit;
}

$tituloPagina = 'Resultado de orden ' . formatear_codigo_orden($orden['orden_codigo']) . ' | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="main-content">
    <h1>Resultado</h1>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>