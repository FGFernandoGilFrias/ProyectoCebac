<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('resultados.gestionar');

$ordenId = (int) ($_GET['orden_id'] ?? $_POST['orden_id'] ?? 0);
$conexion = conexion_bd();
$consulta = $conexion->prepare(
    "SELECT o.id AS orden_id, o.codigo AS codigo_orden, o.estado_pago, o.total_adeudado, o.monto_pagado,
            p.apellido, p.nombre, os.id AS orden_estudio_id, s.codigo AS codigo_estudio,
            s.nombre AS nombre_estudio, s.parametros AS parametros_estudios, sa.estado AS estado_muestra, r.id AS result_id,
            r.texto_resultado, r.estado AS estado_resultado
     FROM ordenes o
     JOIN pacientes p ON p.id = o.paciente_id
     JOIN ordenes_estudios os ON os.orden_id = o.id
     JOIN estudios s ON s.id = os.estudio_id
     LEFT JOIN muestras sa ON sa.orden_estudio_id = os.id
     LEFT JOIN resultados r ON r.orden_estudio_id = os.id
     WHERE o.id = :id ORDER BY os.id"
);
$consulta->execute(['id' => $ordenId]);
$estudios = $consulta->fetchAll();
if (!$estudios) { http_response_code(404); exit('Orden no encontrada.'); }
$paid = (float) $estudios[0]['total_adeudado'] <= 0 || (float) $estudios[0]['monto_pagado'] >= (float) $estudios[0]['total_adeudado'];
$parameterRows = [];
foreach ($estudios as $estudio) {
    $parameterStmt = $conexion->prepare('SELECT nombre_seccion, nombre, minimo, maximo, texto_referencia, descripcion, rango_min, rango_max FROM parametros_estudios WHERE estudio_id = (SELECT estudio_id FROM ordenes_estudios WHERE id = :orden_estudio_id) ORDER BY orden, id');
    $parameterStmt->execute(['orden_estudio_id' => $estudio['orden_estudio_id']]);
    $parameterRows[(int) $estudio['orden_estudio_id']] = $parameterStmt->fetchAll();
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ordenEstudioId = (int) ($_POST['orden_estudio_id'] ?? 0);
    $values = $_POST['parameter_value'] ?? [];
    $text = trim((string) ($_POST['texto_resultado'] ?? ''));
    $targetStmt = $conexion->prepare(
        "SELECT os.id, sa.estado AS estado_muestra, r.estado AS estado_resultado
         FROM ordenes_estudios os
         LEFT JOIN muestras sa ON sa.orden_estudio_id = os.id
         LEFT JOIN resultados r ON r.orden_estudio_id = os.id
         WHERE os.id = :orden_estudio_id AND os.orden_id = :orden_id"
    );
    $targetStmt->execute(['orden_estudio_id' => $ordenEstudioId, 'orden_id' => $ordenId]);
    $target = $targetStmt->fetch();
    $missingParameter = false;
    if (isset($values[$ordenEstudioId]) && is_array($values[$ordenEstudioId])) {
        $lines = [];
        foreach ($values[$ordenEstudioId] as $nombre => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                $missingParameter = true;
            } else {
                $lines[] = trim((string) $nombre) . ': ' . $value;
            }
        }
        $text = implode("\n", $lines);
    }
    $action = (string) ($_POST['action'] ?? 'save');
    if (!$target) {
        $error = 'El estudio seleccionado no pertenece a esta orden.';
    } elseif ($target['estado_resultado'] === 'Entregado') {
        $error = 'El resultado ya fue entregado y no puede modificarse.';
    } elseif ($missingParameter || $text === '') {
        $error = 'Ingresa el resultado del estudio.';
    } elseif (!in_array($action, ['save', 'deliver'], true)) {
        $error = 'Acción de resultado no válida.';
    } elseif ($action === 'deliver' && !$paid) {
        $error = 'No se pueden entregar resultados mientras exista saldo pendiente.';
    } elseif ($action === 'deliver' && $target['estado_muestra'] !== 'Finalizada') {
        $error = 'La muestra debe estar finalizada antes de entregar el resultado.';
    } else {
        $estado = $action === 'deliver' ? 'Entregado' : 'Cargado';
        $save = $conexion->prepare(
            "INSERT INTO resultados (orden_estudio_id, texto_resultado, estado, entregado_en)
             VALUES (:study, :text, :estado, CASE WHEN :estado = 'Entregado' THEN NOW() ELSE NULL END)
             ON DUPLICATE KEY UPDATE texto_resultado = VALUES(texto_resultado), estado = VALUES(estado), entregado_en = VALUES(entregado_en)"
        );
        $save->execute(['study' => $ordenEstudioId, 'text' => $text, 'estado' => $estado]);
        actualizar_finalizacion_orden($ordenId);
        mensaje_flash($action === 'deliver' ? 'Resultado entregado correctamente.' : 'Resultado guardado correctamente.');
        header('Location: resultado_formulario.php?orden_id=' . $ordenId);
        exit;
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Resultados de orden | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main class="container"><p><a href="resultados.php">← Volver a resultados</a></p><h1>Resultados de orden <?= escapar_html($estudios[0]['codigo_orden']) ?></h1><p class="muted">Paciente: <?= escapar_html($estudios[0]['apellido'] . ', ' . $estudios[0]['nombre']) ?> · Estado de pago: <?= escapar_html($estudios[0]['estado_pago']) ?></p><?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?><?php foreach ($estudios as $estudio): $estudioId = (int) $estudio['orden_estudio_id']; $resultadoValue = $estudio['texto_resultado'] ?? ''; $existingValues = []; foreach (preg_split('/\R/', $resultadoValue) as $line) { $parts = explode(':', $line, 2); if (count($parts) === 2) { $existingValues[trim($parts[0])] = trim($parts[1]); } } ?><form method="post" class="result-card module"><input type="hidden" name="orden_id" value="<?= $ordenId ?>"><input type="hidden" name="orden_estudio_id" value="<?= $estudioId ?>"><h2><?= escapar_html($estudio['nombre_estudio']) ?> <small><?= escapar_html($estudio['codigo_estudio']) ?></small></h2><p class="muted">Muestra: <?= escapar_html($estudio['estado_muestra'] ?? 'Pendiente') ?> · Resultado: <?= escapar_html($estudio['estado_resultado'] ?? 'Pendiente') ?></p><?php if ($parameterRows[$estudioId]): ?><div class="parameter-result-table"><div class="parameter-result-head"><span>Sección / parámetro</span><span>Referencia</span><span>Descripción</span><span>Valor obtenido</span></div><?php $lastSection = null; foreach ($parameterRows[$estudioId] as $parameter): if ($parameter['nombre_seccion'] !== $lastSection): $lastSection = $parameter['nombre_seccion']; if ($lastSection): ?><div class="parameter-section"><?= escapar_html($lastSection) ?></div><?php endif; endif; $rangeMin = $parameter['rango_min'] !== null && $parameter['rango_min'] !== '' ? $parameter['rango_min'] : ($parameter['minimo'] ?? ''); $rangeMax = $parameter['rango_max'] !== null && $parameter['rango_max'] !== '' ? $parameter['rango_max'] : ($parameter['maximo'] ?? ''); $range = trim($rangeMin . ' - ' . $rangeMax, ' -'); $reference = $parameter['texto_referencia'] ?: ($range !== '' ? $range : 'No definido'); ?><div class="parameter-result-row"><strong><?= escapar_html($parameter['nombre']) ?></strong><span><?= escapar_html($reference) ?></span><span><?= escapar_html($parameter['descripcion'] ?? '') ?></span><input name="parameter_value[<?= $estudioId ?>][<?= escapar_html($parameter['nombre']) ?>]" value="<?= escapar_html($existingValues[$parameter['nombre']] ?? '') ?>" required <?= $estudio['estado_resultado'] === 'Entregado' ? 'readonly' : '' ?>></div><?php endforeach; ?></div><?php else: ?><label>Resultado</label><textarea name="texto_resultado" rows="8" required <?= $estudio['estado_resultado'] === 'Entregado' ? 'readonly' : '' ?>><?= escapar_html($resultadoValue) ?></textarea><?php endif; ?><div class="result-actions"><button type="submit" name="action" value="save" <?= $estudio['estado_resultado'] === 'Entregado' ? 'disabled' : '' ?>>Guardar resultado</button><button type="submit" name="action" value="deliver" class="deliver-button" <?= !$paid || $estudio['estado_resultado'] === 'Entregado' ? 'disabled' : '' ?>>Entregar resultado</button></div></form><?php endforeach; ?></main></body></html>








