<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('obras_sociales.gestionar');

$id = (int) ($_GET['id'] ?? 0);
$activo = (int) ($_GET['activo'] ?? 0) === 1 ? 1 : 0;
if ($id > 0) {
    $consulta = conexion_bd()->prepare('UPDATE obras_sociales SET activo = :activo WHERE id = :id');
    $consulta->execute(['activo' => $activo, 'id' => $id]);
    mensaje_flash($activo ? 'Obra social reactivada correctamente.' : 'Obra social dada de baja correctamente.');
}
header('Location: obras_sociales.php');
exit;








