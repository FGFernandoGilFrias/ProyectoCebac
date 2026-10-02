<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('resultados.gestionar');

$ordenId = (int) ($_GET['orden_id'] ?? $_POST['orden_id'] ?? 0);
$conexion = conexion_bd();

$stmt = $conexion->prepare(
    "SELECT o.id, o.codigo AS orden_codigo, o.fecha_orden, o.estado, o.medico,
            p.apellido, p.nombre, p.dni,
            os.nombre AS obra_social
     FROM ordenes o
     JOIN pacientes p ON p.id = o.paciente_id
     LEFT JOIN obras_sociales os ON os.id = o.obra_social_id
     WHERE o.id = :id"
);
$stmt->execute(['id' => $ordenId]);
$orden = $stmt->fetch();

if (!$orden) {
    http_response_code(404);
    exit('Orden no encontrada.');
}

$stmt = $conexion->prepare(
    "SELECT oe.id AS orden_estudio_id,
            oe.estado AS estado_orden_estudio,
            e.codigo AS estudio_codigo,
            e.nombre AS estudio,
            e.parametros,
            oe.precio,
            m.id AS muestra_id,
            m.codigo AS codigo_muestra,
            m.estado AS estado_muestra,
            m.motivo_rechazo,
            r.id AS resultado_id,
            r.texto_resultado,
            r.estado AS estado_resultado
     FROM ordenes_estudios oe
     JOIN estudios e ON e.id = oe.estudio_id
     LEFT JOIN muestras m ON m.orden_estudio_id = oe.id
     LEFT JOIN resultados r ON r.orden_estudio_id = oe.id
     WHERE oe.orden_id = :orden_id
     ORDER BY e.nombre"
);
$stmt->execute(['orden_id' => $ordenId]);
$estudios = $stmt->fetchAll();

$itemsPendientesMuestra = [];
foreach ($estudios as $est) {
    $estadoMuestra = $est['estado_muestra'] ?? 'Pendiente';
    if ($estadoMuestra === 'Pendiente' || $estadoMuestra === 'Rechazada') {
        $itemsPendientesMuestra[] = $est;
    }
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valores = $_POST['valor'] ?? [];
    $rechazados = $_POST['rechazado'] ?? [];
    $motivos = $_POST['motivo_rechazo'] ?? [];

    try {
        $conexion->beginTransaction();

        foreach ($estudios as $est) {
            $ordenEstudioId = (int) $est['orden_estudio_id'];
            $estadoMuestra = $est['estado_muestra'] ?? 'Pendiente';
            $muestraId = (int) ($est['muestra_id'] ?? 0);

            if (!empty($rechazados[$ordenEstudioId])) {
                $motivo = trim((string) ($motivos[$ordenEstudioId] ?? ''));
                if ($motivo === '') {
                    throw new RuntimeException('Debés indicar el motivo del rechazo para "' . $est['estudio'] . '".');
                }

                if ($muestraId > 0) {
                    $stmtRechazoMuestra = $conexion->prepare('UPDATE muestras SET estado = :estado, motivo_rechazo = :motivo WHERE id = :id');
                    $stmtRechazoMuestra->execute(['estado' => 'Rechazada', 'motivo' => $motivo, 'id' => $muestraId]);
                } else {
                    $siguienteCodigo = (int) $conexion->query("SELECT COALESCE(MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)), 0) + 1 FROM muestras WHERE codigo REGEXP '^MUE-[0-9]+$'")->fetchColumn();
                    $codigo = 'MUE-' . str_pad((string) $siguienteCodigo, 3, '0', STR_PAD_LEFT);
                    $stmtRechazoMuestra = $conexion->prepare('INSERT INTO muestras (codigo, orden_estudio_id, recolectado_en, estado, motivo_rechazo) VALUES (:codigo, :orden_estudio_id, NOW(), :estado, :motivo)');
                    $stmtRechazoMuestra->execute([
                        'codigo' => $codigo,
                        'orden_estudio_id' => $ordenEstudioId,
                        'estado' => 'Rechazada',
                        'motivo' => $motivo,
                    ]);
                }

                $conexion->prepare('UPDATE ordenes_estudios SET estado = :estado WHERE id = :id')
                    ->execute(['estado' => 'Pendiente', 'id' => $ordenEstudioId]);

                $stmtResultadoPendiente = $conexion->prepare(
                    'INSERT INTO resultados (orden_estudio_id, texto_resultado, estado, entregado_en)
                     VALUES (:orden_estudio_id, NULL, :estado, NULL)
                     ON DUPLICATE KEY UPDATE texto_resultado = VALUES(texto_resultado), estado = VALUES(estado), entregado_en = NULL'
                );
                $stmtResultadoPendiente->execute([
                    'orden_estudio_id' => $ordenEstudioId,
                    'estado' => 'Pendiente',
                ]);

                continue;
            }

            if ($estadoMuestra === 'Pendiente' || $estadoMuestra === 'Rechazada') {
                continue;
            }

            $valor = trim((string) ($valores[$ordenEstudioId] ?? ''));
            if ($valor === '') {
                continue;
            }

            $stmtResultado = $conexion->prepare(
                'INSERT INTO resultados (orden_estudio_id, texto_resultado, estado)
                 VALUES (:orden_estudio_id, :texto_resultado, :estado)
                 ON DUPLICATE KEY UPDATE texto_resultado = VALUES(texto_resultado), estado = VALUES(estado)'
            );
            $stmtResultado->execute([
                'orden_estudio_id' => $ordenEstudioId,
                'texto_resultado' => $valor,
                'estado' => 'Cargado',
            ]);
        }

        $conexion->commit();

        actualizar_finalizacion_orden($ordenId);

        mensaje_flash('Resultado guardado correctamente.');
        header('Location: resultado_formulario.php?orden_id=' . $ordenId);
        exit;
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        $error = 'No se pudo guardar el resultado: ' . $e->getMessage();
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
</div>

<?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?>

<?php if (!empty($itemsPendientesMuestra)): ?>
    <div class="alert warning-alert">
        <strong>⏳ Hay estudios bloqueados por muestra pendiente o rechazada:</strong>
        <ul>
            <?php foreach ($itemsPendientesMuestra as $itemPendiente): ?>
                <li>
                    <?= escapar_html($itemPendiente['estudio']) ?>
                    (<?= escapar_html($itemPendiente['estado_muestra'] ?? 'Pendiente') ?>)
                    <?php if (!empty($itemPendiente['motivo_rechazo'])): ?>
                        - <?= escapar_html($itemPendiente['motivo_rechazo']) ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        Completá el check-in de esas muestras para habilitar la carga de resultados.
    </div>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="orden_id" value="<?= $ordenId ?>">

    <div class="result-grid-wrap">
        <table class="result-grid">
            <thead>
                <tr>
                    <th>Estudio</th>
                    <th>Muestra</th>
                    <th>Parámetros</th>
                    <th>Precio</th>
                    <th>Resultado</th>
                    <th class="col-rechazo">Rechazar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($estudios as $est): ?>
                    <?php
                    $ordenEstudioId = (int) $est['orden_estudio_id'];
                    $estadoMuestra = $est['estado_muestra'] ?? 'Pendiente';
                    $bloqueado = $estadoMuestra === 'Pendiente' || $estadoMuestra === 'Rechazada';
                    ?>
                    <tr class="result-param-row <?= $bloqueado ? 'fila-pendiente' : '' ?>">
                        <td>
                            <strong><?= escapar_html($est['estudio']) ?></strong><br>
                            <small class="muted"><?= escapar_html($est['estudio_codigo']) ?></small>
                        </td>
                        <td>
                            <span class="badge"><?= escapar_html($estadoMuestra) ?></span>
                            <?php if (!empty($est['codigo_muestra'])): ?>
                                <br><small class="muted"><?= escapar_html($est['codigo_muestra']) ?></small>
                            <?php endif; ?>
                            <?php if ($estadoMuestra === 'Rechazada' && !empty($est['motivo_rechazo'])): ?>
                                <br><small class="muted">Motivo: <?= escapar_html($est['motivo_rechazo']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= escapar_html($est['parametros'] ?? '—') ?></td>
                        <td>$ <?= number_format((float) ($est['precio'] ?? 0), 2, ',', '.') ?></td>
                        <td>
                            <?php if ($bloqueado): ?>
                                <span class="texto-pendiente">⏳ Muestra no habilitada para cargar resultado.</span>
                            <?php else: ?>
                                <input type="text" name="valor[<?= $ordenEstudioId ?>]" value="<?= escapar_html((string) ($est['texto_resultado'] ?? '')) ?>" placeholder="Ingresar resultado" data-cod="<?= $ordenEstudioId ?>" class="input-valor">
                            <?php endif; ?>
                        </td>
                        <td class="col-rechazo">
                            <?php if ($bloqueado): ?>
                                <a class="button-small" href="muestra_formulario.php?orden_estudio_id=<?= $ordenEstudioId ?>">Check-in</a>
                            <?php else: ?>
                                <input type="checkbox" class="rechazo-check" name="rechazado[<?= $ordenEstudioId ?>]" value="1" data-cod="<?= $ordenEstudioId ?>">
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (!$bloqueado): ?>
                        <tr class="result-motivo-row" data-motivo="<?= $ordenEstudioId ?>" hidden>
                            <td colspan="6">
                                <label class="motivo-rechazo-label">
                                    Motivo del rechazo *
                                    <input type="text" name="motivo_rechazo[<?= $ordenEstudioId ?>]" placeholder="Ej: muestra hemolizada, cantidad insuficiente" class="motivo-rechazo">
                                </label>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (!$estudios): ?>
                    <tr><td colspan="6" class="muted">Esta orden no tiene estudios asociados.</td></tr>
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
<?php include __DIR__ . '/includes/footer.php'; ?>
