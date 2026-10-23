<?php
require_once __DIR__ . '/includes/autenticacion.php';
exigir_privilegio('usuarios.manage');
$id = (int) ($_GET['id'] ?? 0);
$activo = (int) ($_GET['activo'] ?? 0) === 1 ? 1 : 0;
if ($id > 0 && $id !== (int) usuario_actual()['id']) {
    $stmt = db()->prepare('UPDATE usuarios SET activo = :activo WHERE id = :id');
    $stmt->execute(['activo' => $activo, 'id' => $id]);
    flash($activo ? 'Usuario activado correctamente.' : 'Usuario desactivado correctamente.');
}
header('Location: usuarios.php');
exit;



