<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('usuarios.gestionar');
$id = (int) ($_GET['id'] ?? 0);
$activo = (int) ($_GET['activo'] ?? 0) === 1 ? 1 : 0;
if ($id > 0 && $id !== (int) usuario_actual()['id']) {
    $consulta = conexion_bd()->prepare('UPDATE usuarios SET activo = :activo WHERE id = :id');
    $consulta->execute(['activo' => $activo, 'id' => $id]);
    mensaje_flash($activo ? 'Usuario activado correctamente.' : 'Usuario desactivado correctamente.');
}
header('Location: usuarios.php');
exit;








