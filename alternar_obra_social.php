<?php
require_once __DIR__ . '/includes/autenticacion.php';
exigir_privilegio('obras_sociales.manage');

$id = (int) ($_GET['id'] ?? 0);
$activo = (int) ($_GET['activo'] ?? 0) === 1 ? 1 : 0;
if ($id > 0) {
    $stmt = db()->prepare('UPDATE obras_sociales SET activo = :activo WHERE id = :id');
    $stmt->execute(['activo' => $activo, 'id' => $id]);
    flash($activo ? 'Obra social reactivada correctamente.' : 'Obra social dada de baja correctamente.');
}
header('Location: obras_sociales.php');
exit;



