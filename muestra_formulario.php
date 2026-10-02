<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('muestras.gestionar');

$ordenEstudioId = (int) ($_GET['orden_estudio_id'] ?? $_POST['orden_estudio_id'] ?? 0);
$consulta = conexion_bd()->prepare(
    "SELECT os.id, o.id AS orden_id, o.codigo AS codigo_orden, p.apellido, p.nombre, s.nombre AS nombre_estudio,
            sa.id AS muestra_id, sa.codigo AS codigo_muestra, sa.recolectado_en, sa.estado, sa.motivo_rechazo
     FROM ordenes_estudios os
     JOIN ordenes o ON o.id = os.orden_id
     JOIN pacientes p ON p.id = o.paciente_id
     JOIN estudios s ON s.id = os.estudio_id
     LEFT JOIN muestras sa ON sa.orden_estudio_id = os.id
     WHERE os.id = :id"
);
$consulta->execute(['id' => $ordenEstudioId]);
$item = $consulta->fetch();
$error = null;
if (!$item) {
    http_response_code(404);
    exit('Estudio de orden no encontrado.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $estado = (string) ($_POST['estado'] ?? 'Pendiente');
    $allowed = ['Pendiente', 'Validada', 'Finalizada', 'Rechazada'];
    $reason = trim((string) ($_POST['motivo_rechazo'] ?? ''));
    if (!in_array($estado, $allowed, true)) {
        $error = 'Estado de muestra no válido.';
    } elseif ($estado === 'Rechazada' && $reason === '') {
        $error = 'Indica el motivo del rechazo.';
    } else {
        try {
            $conexion = conexion_bd();
            if ($item['muestra_id']) {
                $save = $conexion->prepare('UPDATE muestras SET estado=:estado, motivo_rechazo=:reason, recolectado_en=COALESCE(recolectado_en, NOW()) WHERE id=:id');
                $save->execute(['estado' => $estado, 'reason' => $estado === 'Rechazada' ? $reason : null, 'id' => $item['muestra_id']]);
            } else {
                $siguienteCodigo = (int) $conexion->query("SELECT COALESCE(MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)), 0) + 1 FROM muestras WHERE codigo REGEXP '^MUE-[0-9]+$'")->fetchColumn();
                $codigo = 'MUE-' . str_pad((string) $siguienteCodigo, 3, '0', STR_PAD_LEFT);
                $save = $conexion->prepare('INSERT INTO muestras (codigo, orden_estudio_id, recolectado_en, estado, motivo_rechazo) VALUES (:codigo,:orden_estudio_id,NOW(),:estado,:reason)');
                $save->execute(['codigo' => $codigo, 'orden_estudio_id' => $ordenEstudioId, 'estado' => $estado, 'reason' => $estado === 'Rechazada' ? $reason : null]);
            }
            $estadoOrdenEstudio = $estado === 'Finalizada' ? 'Finalizada' : ($estado === 'Validada' ? 'Validada' : 'Pendiente');
            $sync = $conexion->prepare('UPDATE ordenes_estudios SET estado = :estado WHERE id = :id');
            $sync->execute(['estado' => $estadoOrdenEstudio, 'id' => $ordenEstudioId]);
            actualizar_finalizacion_orden((int) $item['orden_id']);
            mensaje_flash('Muestra actualizada correctamente.');
            header('Location: recepcion_muestras.php');
            exit;
        } catch (PDOException $exception) {
            $error = 'No se pudo guardar la muestra.';
        }
    }
}
$currentStatus = $_POST['estado'] ?? $item['estado'] ?? 'Pendiente';
$currentReason = $_POST['motivo_rechazo'] ?? $item['motivo_rechazo'] ?? '';
$tituloPagina = 'Check-in de muestra | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container narrow">
<p><a href="recepcion_muestras.php">← Volver al check-in</a></p>
<h1>Check-in de muestra</h1>
<div class="module">
    <p><strong>Orden:</strong> <?= escapar_html($item['codigo_orden']) ?></p>
    <p><strong>Paciente:</strong> <?= escapar_html($item['apellido'] . ', ' . $item['nombre']) ?></p>
    <p><strong>Estudio:</strong> <?= escapar_html($item['nombre_estudio']) ?></p>
    <?php if ($item['codigo_muestra']): ?><p><strong>Código de muestra:</strong> <?= escapar_html($item['codigo_muestra']) ?></p><?php endif; ?>
</div>
<?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?>
<form method="post" class="form-grid">
    <input type="hidden" name="orden_estudio_id" value="<?= $ordenEstudioId ?>">
    <div class="full">
        <label>Estado de muestra *</label>
        <select name="estado" required>
            <option <?= $currentStatus === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
            <option <?= $currentStatus === 'Validada' ? 'selected' : '' ?>>Validada</option>
            <option <?= $currentStatus === 'Finalizada' ? 'selected' : '' ?>>Finalizada</option>
            <option <?= $currentStatus === 'Rechazada' ? 'selected' : '' ?>>Rechazada</option>
        </select>
    </div>
    <div class="full">
        <label>Motivo de rechazo</label>
        <textarea name="motivo_rechazo" rows="4"><?= escapar_html($currentReason) ?></textarea>
    </div>
    <div class="full"><button type="submit">Guardar muestra</button></div>
</form>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>