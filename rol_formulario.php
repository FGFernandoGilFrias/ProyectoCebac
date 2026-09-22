<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('usuarios.gestionar');
$id = (int) ($_GET['id'] ?? 0);
$esEdicion = $id > 0;
$rol = ['nombre' => '', 'descripcion' => ''];
$conexion = conexion_bd();
$privilegiosSeleccionados = [];
if ($esEdicion) {
    $consulta = $conexion->prepare('SELECT id, nombre, descripcion FROM roles WHERE id = :id');
    $consulta->execute(['id' => $id]);
    $rol = $consulta->fetch() ?: $rol;
    $privilegioStmt = $conexion->prepare('SELECT privilegio_id FROM roles_privilegios WHERE rol_id = :id');
    $privilegioStmt->execute(['id' => $id]);
    $privilegiosSeleccionados = array_map('intval', $privilegioStmt->fetchAll(PDO::FETCH_COLUMN));
}
$privilegios = $conexion->query('SELECT id, codigo, nombre, descripcion FROM privilegios ORDER BY codigo')->fetchAll();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rol['nombre'] = trim((string) ($_POST['nombre'] ?? ''));
    $rol['descripcion'] = trim((string) ($_POST['descripcion'] ?? ''));
    $privilegiosSeleccionados = array_values(array_unique(array_map('intval', $_POST['privilegios_ids'] ?? [])));
    if ($rol['nombre'] === '') {
        $error = 'El nombre del rol es obligatorio.';
    } else {
        try {
            $conexion->beginTransaction();
            if ($esEdicion) {
                $conexion->prepare('UPDATE roles SET name=:nombre, descripcion=:descripcion WHERE id=:id')->execute(['nombre' => $rol['nombre'], 'descripcion' => $rol['descripcion'], 'id' => $id]);
                $conexion->prepare('DELETE FROM roles_privilegios WHERE rol_id = :id')->execute(['id' => $id]);
            } else {
                $consulta = $conexion->prepare('INSERT INTO roles (nombre, descripcion) VALUES (:nombre, :descripcion)');
                $consulta->execute(['nombre' => $rol['nombre'], 'descripcion' => $rol['descripcion']]);
                $id = (int) $conexion->lastInsertId();
            }
            $save = $conexion->prepare('INSERT INTO roles_privilegios (rol_id, privilegio_id) VALUES (:rol_id, :privilegio_id)');
            foreach ($privilegiosSeleccionados as $privilegioId) {
                $save->execute(['rol_id' => $id, 'privilegio_id' => $privilegioId]);
            }
            $conexion->commit();
            mensaje_flash($esEdicion ? 'Rol actualizado correctamente.' : 'Rol creado correctamente.');
            header('Location: roles.php');
            exit;
        } catch (PDOException $exception) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            $error = $exception->getCode() === '23000' ? 'Ya existe un rol con ese nombre o hay un privilegio inválido.' : 'No se pudo guardar el rol.';
        }
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $esEdicion ? 'Editar' : 'Nuevo' ?> rol | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main class="container narrow"><p><a href="roles.php">← Volver a roles</a></p><h1><?= $esEdicion ? 'Editar rol' : 'Nuevo rol' ?></h1><?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?><form method="post" class="form-grid"><div class="full"><label>Nombre del rol *</label><input name="nombre" required value="<?= escapar_html($rol['nombre']) ?>"></div><div class="full"><label>Descripción</label><textarea name="descripcion" rows="3"><?= escapar_html($rol['descripcion']) ?></textarea></div><div class="full"><label>Privilegios *</label><div class="study-picker"><?php foreach ($privilegios as $privilegio): ?><label class="study-option"><input type="checkbox" name="privilegios_ids[]" value="<?= (int) $privilegio['id'] ?>" <?= in_array((int) $privilegio['id'], $privilegiosSeleccionados, true) ? 'checked' : '' ?>><span><strong><?= escapar_html($privilegio['nombre']) ?></strong> — <?= escapar_html($privilegio['descripcion']) ?></span></label><?php endforeach; ?></div></div><div class="full"><button type="submit">Guardar rol</button></div></form></main></body></html>








