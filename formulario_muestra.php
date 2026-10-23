<?php
require_once __DIR__ . '/includes/autenticacion.php';
exigir_privilegio('muestras.manage');

$ordenEstudioId = (int) ($_GET['orden_estudio_id'] ?? $_POST['orden_estudio_id'] ?? 0);
$stmt = db()->prepare(
    "SELECT os.id, o.id AS orden_id, o.codigo AS codigo_orden, p.apellido, p.nombre, s.nombre AS estudio_name,
            sa.id AS muestra_id, sa.codigo AS muestra_code, sa.recolectado_en, sa.estado, sa.motivo_rechazo
     FROM ordenes_estudios os
     JOIN ordenes o ON o.id = os.orden_id
     JOIN pacientes p ON p.id = o.paciente_id
     JOIN estudios s ON s.id = os.estudio_id
     LEFT JOIN muestras sa ON sa.orden_estudio_id = os.id
     WHERE os.id = :id"
);
$stmt->execute(['id' => $ordenEstudioId]);
$item = $stmt->fetch();
$error = null;
if (!$item) {
    http_response_code(404);
    exit('Estudio de orden no encontrado.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $estado = (string) ($_POST['estado'] ?? 'Pendiente');
    $permitidos = ['Pendiente', 'Validada', 'Finalizada', 'Rechazada'];
    $motivo = trim((string) ($_POST['motivo_rechazo'] ?? ''));
    if (!in_array($estado, $permitidos, true)) {
        $error = 'Estado de muestra no válido.';
    } elseif ($estado === 'Rechazada' && $motivo === '') {
        $error = 'Indica el motivo del rechazo.';
    } else {
        try {
            $pdo = db();
            if ($item['muestra_id']) {
                $save = $pdo->prepare('UPDATE muestras SET estado=:estado, motivo_rechazo=:motivo_rechazo, recolectado_en=COALESCE(recolectado_en, NOW()) WHERE id=:id');
                $save->execute(['estado' => $estado, 'motivo_rechazo' => $estado === 'Rechazada' ? $motivo : null, 'id' => $item['muestra_id']]);
            } else {
                $siguienteCodigo = (int) $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)), 0) + 1 FROM muestras WHERE codigo REGEXP '^MUE-[0-9]+$'")->fetchColumn();
                $codigo = 'MUE-' . str_pad((string) $siguienteCodigo, 3, '0', STR_PAD_LEFT);
                $save = $pdo->prepare('INSERT INTO muestras (codigo, orden_estudio_id, recolectado_en, estado, motivo_rechazo) VALUES (:codigo,:orden_estudio_id,NOW(),:estado,:motivo_rechazo)');
                $save->execute(['codigo' => $codigo, 'orden_estudio_id' => $ordenEstudioId, 'estado' => $estado, 'motivo_rechazo' => $estado === 'Rechazada' ? $motivo : null]);
            }
            actualizar_finalizacion_orden((int) $item['orden_id']);
            flash('Muestra actualizada correctamente.');
            header('Location: recepcion_muestra.php');
            exit;
        } catch (PDOException $excepcion) {
            $error = 'No se pudo guardar la muestra.';
        }
    }
}
$estadoActual = $_POST['estado'] ?? $item['estado'] ?? 'Pendiente';
$motivoActual = $_POST['motivo_rechazo'] ?? $item['motivo_rechazo'] ?? '';
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Check-in de muestra | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/barra_lateral.php'; ?><main class="container narrow"><p><a href="recepcion_muestra.php">← Volver al check-in</a></p><h1>Check-in de muestra</h1><div class="module"><p><strong>Orden:</strong> <?= e($item['codigo_orden']) ?></p><p><strong>Paciente:</strong> <?= e($item['apellido'] . ', ' . $item['nombre']) ?></p><p><strong>Estudio:</strong> <?= e($item['estudio_name']) ?></p><?php if ($item['muestra_code']): ?><p><strong>Código de muestra:</strong> <?= e($item['muestra_code']) ?></p><?php endif; ?></div><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><form method="post" class="form-grid"><input type="hidden" name="orden_estudio_id" value="<?= $ordenEstudioId ?>"><div class="full"><label>Estado de muestra *</label><select name="estado" required><option <?= $estadoActual === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option><option <?= $estadoActual === 'Validada' ? 'selected' : '' ?>>Validada</option><option <?= $estadoActual === 'Finalizada' ? 'selected' : '' ?>>Finalizada</option><option <?= $estadoActual === 'Rechazada' ? 'selected' : '' ?>>Rechazada</option></select></div><div class="full"><label>Motivo de rechazo</label><textarea name="motivo_rechazo" rows="4"><?= e($motivoActual) ?></textarea></div><div class="full"><button type="submit">Guardar muestra</button></div></form></main></body></html>



