<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('obras_sociales.gestionar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: obras_sociales.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$accion = (string) ($_POST['accion'] ?? '');

if ($id <= 0 || !in_array($accion, ['baja', 'reactivar'], true)) {
    mensaje_flash('Acción no válida.');
    header('Location: obras_sociales.php');
    exit;
}

$conexion = conexion_bd();

if ($accion === 'baja') {
    $stmt = $conexion->prepare('UPDATE obras_sociales SET fecha_baja = CURDATE() WHERE codigo = :codigo');
    $stmt->execute(['codigo' => $id]);
    mensaje_flash('Obra social dada de baja correctamente.');
} else {
    $stmt = $conexion->prepare('UPDATE obras_sociales SET fecha_baja = NULL WHERE codigo = :codigo');
    $stmt->execute(['codigo' => $id]);
    mensaje_flash('Obra social reactivada correctamente.');
}

header('Location: obras_sociales.php');
exit;