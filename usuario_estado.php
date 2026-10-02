<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('usuarios.gestionar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: usuarios.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$activo = (int) ($_POST['activo'] ?? 0) === 1 ? 1 : 0;

if ($id > 0 && $id !== (int) usuario_actual()['codigo']) {
    $consulta = conexion_bd()->prepare('UPDATE usuarios SET activo = :activo WHERE codigo = :codigo');
    $consulta->execute(['activo' => $activo, 'codigo' => $id]);
    mensaje_flash($activo ? 'Usuario activado correctamente.' : 'Usuario desactivado correctamente.');
}
header('Location: usuarios.php');
exit;