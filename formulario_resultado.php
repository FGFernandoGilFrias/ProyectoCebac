<?php
require_once __DIR__ . '/includes/autenticacion.php';
exigir_privilegio('resultados.manage');

$ordenId = (int) ($_GET['orden_id'] ?? $_POST['orden_id'] ?? 0);
$pdo = db();
$stmt = $pdo->prepare(
    "SELECT o.id AS orden_id, o.codigo AS codigo_orden, o.estado_pago, o.total_adeudado, o.monto_pagado,
            p.apellido, p.nombre, os.id AS orden_estudio_id, s.codigo AS estudio_code,
            s.nombre AS estudio_name, s.parametros AS parametros_estudios, sa.estado AS muestra_status, r.id AS resultado_id,
            r.texto_resultado, r.estado AS resultado_status
     FROM ordenes o
     JOIN pacientes p ON p.id = o.paciente_id
     JOIN ordenes_estudios os ON os.orden_id = o.id
     JOIN estudios s ON s.id = os.estudio_id
     LEFT JOIN muestras sa ON sa.orden_estudio_id = os.id
     LEFT JOIN resultados r ON r.orden_estudio_id = os.id
     WHERE o.id = :id ORDER BY os.id"
);
$stmt->execute(['id' => $ordenId]);
$estudios = $stmt->fetchAll();
if (!$estudios) { http_response_code(404); exit('Orden no encontrada.'); }
$paid = (float) $estudios[0]['total_adeudado'] <= 0 || (float) $estudios[0]['monto_pagado'] >= (float) $estudios[0]['total_adeudado'];
$parameterRows = [];
foreach ($estudios as $estudio) {
    $consultaParametro = $pdo->prepare('SELECT nombre_seccion, nombre, minimo, maximo, texto_referencia, descripcion, rango_min, rango_max FROM parametros_estudios WHERE estudio_id = (SELECT estudio_id FROM ordenes_estudios WHERE id = :orden_estudio_id) ORDER BY orden, id');
    $consultaParametro->execute(['orden_estudio_id' => $estudio['orden_estudio_id']]);
    $parameterRows[(int) $estudio['orden_estudio_id']] = $consultaParametro->fetchAll();
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ordenEstudioId = (int) ($_POST['orden_estudio_id'] ?? 0);
    $values = $_POST['parameter_value'] ?? [];
    $text = trim((string) ($_POST['texto_resultado'] ?? ''));
    $targetStmt = $pdo->prepare(
        "SELECT os.id, sa.estado AS muestra_status, r.estado AS resultado_status
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
        foreach ($values[$ordenEstudioId] as $nombre => $valor) {
            $valor = trim((string) $valor);
            if ($valor === '') {
                $missingParameter = true;
            } else {
                $lines[] = trim((string) $nombre) . ': ' . $valor;
            }
        }
        $text = implode("\n", $lines);
    }
    $action = (string) ($_POST['action'] ?? 'save');
    if (!$target) {
        $error = 'El estudio seleccionado no pertenece a esta orden.';
    } elseif ($target['resultado_status'] === 'Entregado') {
        $error = 'El resultado ya fue entregado y no puede modificarse.';
    } elseif ($missingParameter || $text === '') {
        $error = 'Ingresa el resultado del estudio.';
    } elseif (!in_array($action, ['save', 'deliver'], true)) {
        $error = 'Acción de resultado no válida.';
    } elseif ($action === 'deliver' && !$paid) {
        $error = 'No se pueden entregar resultados mientras exista saldo pendiente.';
    } elseif ($action === 'deliver' && $target['muestra_status'] !== 'Finalizada') {
        $error = 'La muestra debe estar finalizada antes de entregar el resultado.';
    } else {
        $estado = $action === 'deliver' ? 'Entregado' : 'Cargado';
        $save = $pdo->prepare(
            "INSERT INTO resultados (orden_estudio_id, texto_resultado, estado, entregado_en)
             VALUES (:orden_estudio_id, :texto_resultado, :estado, CASE WHEN :estado = 'Entregado' THEN NOW() ELSE NULL END)
             ON DUPLICATE KEY UPDATE texto_resultado = VALUES(texto_resultado), estado = VALUES(estado), entregado_en = VALUES(entregado_en)"
        );
        $save->execute(['orden_estudio_id' => $ordenEstudioId, 'texto_resultado' => $text, 'estado' => $estado]);
        actualizar_finalizacion_orden($ordenId);
        flash($action === 'deliver' ? 'Resultado entregado correctamente.' : 'Resultado guardado correctamente.');
        header('Location: formulario_resultado.php?orden_id=' . $ordenId);
        exit;
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Resultados de orden | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/barra_lateral.php'; ?><main class="container"><p><a href="resultados.php">← Volver a resultados</a></p><h1>Resultados de orden <?= e($estudios[0]['codigo_orden']) ?></h1><p class="muted">Paciente: <?= e($estudios[0]['apellido'] . ', ' . $estudios[0]['nombre']) ?> · Estado de pago: <?= e($estudios[0]['estado_pago']) ?></p><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><?php foreach ($estudios as $estudio): $estudioId = (int) $estudio['orden_estudio_id']; $resultadoValue = $estudio['texto_resultado'] ?? ''; $existingValues = []; foreach (preg_split('/\R/', $resultadoValue) as $line) { $parts = explode(':', $line, 2); if (count($parts) === 2) { $existingValues[trim($parts[0])] = trim($parts[1]); } } ?><form method="post" class="resultado-card module"><input type="hidden" name="orden_id" value="<?= $ordenId ?>"><input type="hidden" name="orden_estudio_id" value="<?= $estudioId ?>"><h2><?= e($estudio['estudio_name']) ?> <small><?= e($estudio['estudio_code']) ?></small></h2><p class="muted">Muestra: <?= e($estudio['muestra_status'] ?? 'Pendiente') ?> · Resultado: <?= e($estudio['resultado_status'] ?? 'Pendiente') ?></p><?php if ($parameterRows[$estudioId]): ?><div class="parametro-resultado-table"><div class="parametro-resultado-head"><span>Sección / parámetro</span><span>Referencia</span><span>Descripción</span><span>Valor obtenido</span></div><?php $lastSection = null; foreach ($parameterRows[$estudioId] as $parametro): if ($parametro['nombre_seccion'] !== $lastSection): $lastSection = $parametro['nombre_seccion']; if ($lastSection): ?><div class="parametro-section"><?= e($lastSection) ?></div><?php endif; endif; $rangeMin = $parametro['rango_min'] !== null && $parametro['rango_min'] !== '' ? $parametro['rango_min'] : ($parametro['minimo'] ?? ''); $rangeMax = $parametro['rango_max'] !== null && $parametro['rango_max'] !== '' ? $parametro['rango_max'] : ($parametro['maximo'] ?? ''); $range = trim($rangeMin . ' - ' . $rangeMax, ' -'); $reference = $parametro['texto_referencia'] ?: ($range !== '' ? $range : 'No definido'); ?><div class="parametro-resultado-row"><strong><?= e($parametro['nombre']) ?></strong><span><?= e($reference) ?></span><span><?= e($parametro['descripcion'] ?? '') ?></span><input name="parameter_value[<?= $estudioId ?>][<?= e($parametro['nombre']) ?>]" value="<?= e($existingValues[$parametro['nombre']] ?? '') ?>" required <?= $estudio['resultado_status'] === 'Entregado' ? 'readonly' : '' ?>></div><?php endforeach; ?></div><?php else: ?><label>Resultado</label><textarea name="texto_resultado" rows="8" required <?= $estudio['resultado_status'] === 'Entregado' ? 'readonly' : '' ?>><?= e($resultadoValue) ?></textarea><?php endif; ?><div class="resultado-actions"><button type="submit" name="action" value="save" <?= $estudio['resultado_status'] === 'Entregado' ? 'disabled' : '' ?>>Guardar resultado</button><button type="submit" name="action" value="deliver" class="deliver-button" <?= !$paid || $estudio['resultado_status'] === 'Entregado' ? 'disabled' : '' ?>>Entregar resultado</button></div></form><?php endforeach; ?></main></body></html>



