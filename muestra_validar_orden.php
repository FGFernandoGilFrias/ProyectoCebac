<?php
/**
 * muestra_validar_orden.php
 * Procesa el guardado de estados de muestras de una orden entera.
 */
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('muestras.gestionar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: recepcion_muestras.php');
    exit;
}

$ordenId = (int) ($_POST['orden_id'] ?? 0);
$estados = $_POST['estado'] ?? [];
$motivos = $_POST['motivo'] ?? [];

if ($ordenId <= 0 || empty($estados)) {
    mensaje_flash('No se recibieron cambios.');
    header('Location: recepcion_muestras.php');
    exit;
}

$conexion = conexion_bd();
$permitidos = ['Pendiente', 'Validada', 'Rechazada'];
$actualizados = 0;
$errores = [];
$usuarioId = (int) (usuario_actual()['id'] ?? 0);

try {
    $conexion->beginTransaction();

    // ✅ NUEVO: preparar inserción de historial
    $insertHistorial = $conexion->prepare(
        'INSERT INTO historial_errores (orden_estudio_id, muestra_id, motivo, usuario_id) VALUES (:orden_estudio_id, :muestra_id, :motivo, :usuario_id)'
    );

    foreach ($estados as $oeId => $estado) {
        $oeId = (int) $oeId;
        $estado = (string) $estado;

        if (!in_array($estado, $permitidos, true)) {
            continue;
        }

        $check = $conexion->prepare(
            "SELECT os.id, sa.id AS muestra_id, sa.estado AS estado_actual
             FROM ordenes_estudios os
             LEFT JOIN muestras sa ON sa.orden_estudio_id = os.id
             WHERE os.id = :oe_id AND os.orden_id = :orden_id"
        );
        $check->execute(['oe_id' => $oeId, 'orden_id' => $ordenId]);
        $row = $check->fetch();

        if (!$row) {
            continue;
        }

        if (($row['estado_actual'] ?? '') === 'Finalizada') {
            continue;
        }

        $motivo = trim((string) ($motivos[$oeId] ?? ''));
        if ($estado === 'Rechazada' && $motivo === '') {
            $errores[] = "Estudio #$oeId sin motivo de rechazo";
            continue;
        }

        $muestraId = null;

        if (!empty($row['muestra_id'])) {
            $muestraId = (int) $row['muestra_id'];
            $update = $conexion->prepare(
                'UPDATE muestras SET estado=:estado, motivo_rechazo=:motivo, recolectado_en=COALESCE(recolectado_en, NOW()) WHERE id=:id'
            );
            $update->execute([
                'estado' => $estado,
                'motivo' => $estado === 'Rechazada' ? $motivo : null,
                'id' => $muestraId,
            ]);
        } else {
            $siguienteCodigo = (int) $conexion->query(
                "SELECT COALESCE(MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)), 0) + 1 FROM muestras WHERE codigo REGEXP '^MUE-[0-9]+$'"
            )->fetchColumn();
            $codigo = 'MUE-' . str_pad((string) $siguienteCodigo, 3, '0', STR_PAD_LEFT);

            $insert = $conexion->prepare(
                'INSERT INTO muestras (codigo, orden_estudio_id, recolectado_en, estado, motivo_rechazo)
                 VALUES (:codigo, :oe_id, NOW(), :estado, :motivo)'
            );
            $insert->execute([
                'codigo' => $codigo,
                'oe_id' => $oeId,
                'estado' => $estado,
                'motivo' => $estado === 'Rechazada' ? $motivo : null,
            ]);
            $muestraId = (int) $conexion->lastInsertId();
        }

        // ✅ NUEVO: si es rechazo, guardar en historial
        if ($estado === 'Rechazada') {
            $insertHistorial->execute([
                'orden_estudio_id' => $oeId,
                'muestra_id' => $muestraId,
                'motivo' => $motivo,
                'usuario_id' => $usuarioId ?: null,
            ]);
        }

        $actualizados++;
    }

    if (!empty($errores)) {
        throw new RuntimeException('Faltan datos: ' . implode(', ', $errores));
    }

    actualizar_finalizacion_orden($ordenId);
    $conexion->commit();

    mensaje_flash($actualizados > 0
        ? "Se actualizaron $actualizados estudio(s) correctamente."
        : 'No había cambios para guardar.');

    header('Location: recepcion_muestras.php');
    exit;
} catch (Throwable $e) {
    if ($conexion->inTransaction()) $conexion->rollBack();
    mensaje_flash('Error: ' . $e->getMessage());
    header('Location: recepcion_muestras.php');
    exit;
}