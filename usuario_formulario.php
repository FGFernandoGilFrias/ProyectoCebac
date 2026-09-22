<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('usuarios.gestionar');
$id = (int) ($_GET['id'] ?? 0);
$esEdicion = $id > 0;
$usuario = ['nombre_usuario' => '', 'nombre_completo' => '', 'activo' => 1];
$rolesSeleccionados = [];
$conexion = conexion_bd();
if ($esEdicion) {
    $consulta = $conexion->prepare('SELECT id, nombre_usuario, nombre_completo, activo FROM usuarios WHERE id = :id');
    $consulta->execute(['id' => $id]);
    $usuario = $consulta->fetch() ?: $usuario;
    $consultaRoles = $conexion->prepare('SELECT rol_id FROM usuarios_roles WHERE usuario_id = :id');
    $consultaRoles->execute(['id' => $id]);
    $rolesSeleccionados = array_map('intval', $consultaRoles->fetchAll(PDO::FETCH_COLUMN));
}
$roles = $conexion->query('SELECT id, nombre, descripcion FROM roles WHERE activo = 1 ORDER BY nombre')->fetchAll();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario['nombre_usuario'] = trim((string) ($_POST['nombre_usuario'] ?? ''));
    $usuario['nombre_completo'] = trim((string) ($_POST['nombre_completo'] ?? ''));
    $contrasena = (string) ($_POST['contrasena'] ?? '');
    $rolesSeleccionados = array_values(array_unique(array_map('intval', $_POST['roles_ids'] ?? [])));
    if ($usuario['nombre_usuario'] === '' || $usuario['nombre_completo'] === '' || (!$esEdicion && $contrasena === '') || !$rolesSeleccionados) {
        $error = 'Usuario, nombre, contraseña para usuarios nuevos y al menos un rol son obligatorios.';
    } else {
        try {
            $conexion->beginTransaction();
            if ($esEdicion) {
                $consultaSql = 'UPDATE usuarios SET nombre_usuario=:nombre_usuario, nombre_completo=:nombre_completo WHERE id=:id';
                $params = ['nombre_usuario' => $usuario['nombre_usuario'], 'nombre_completo' => $usuario['nombre_completo'], 'id' => $id];
                if ($contrasena !== '') {
                    $consultaSql = 'UPDATE usuarios SET nombre_usuario=:nombre_usuario, nombre_completo=:nombre_completo, hash_contrasena=:hash_contrasena WHERE id=:id';
                    $params['hash_contrasena'] = password_hash($contrasena, PASSWORD_DEFAULT);
                }
                $conexion->prepare($consultaSql)->execute($params);
                $conexion->prepare('DELETE FROM usuarios_roles WHERE usuario_id = :id')->execute(['id' => $id]);
            } else {
                $consulta = $conexion->prepare('INSERT INTO usuarios (nombre_usuario, nombre_completo, hash_contrasena, rol) VALUES (:nombre_usuario, :nombre_completo, :hash_contrasena, \'operador\')');
                $consulta->execute(['nombre_usuario' => $usuario['nombre_usuario'], 'nombre_completo' => $usuario['nombre_completo'], 'hash_contrasena' => password_hash($contrasena, PASSWORD_DEFAULT)]);
                $id = (int) $conexion->lastInsertId();
            }
            $roleInsert = $conexion->prepare('INSERT INTO usuarios_roles (usuario_id, rol_id) VALUES (:usuario_id, :rol_id)');
            foreach ($rolesSeleccionados as $rolId) {
                $roleInsert->execute(['usuario_id' => $id, 'rol_id' => $rolId]);
            }
            $conexion->commit();
            mensaje_flash($esEdicion ? 'Usuario actualizado correctamente.' : 'Usuario creado correctamente.');
            header('Location: usuarios.php');
            exit;
        } catch (PDOException $exception) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            $error = $exception->getCode() === '23000' ? 'El nombre de usuario ya existe o el rol no es válido.' : 'No se pudo guardar el usuario.';
        }
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $esEdicion ? 'Editar' : 'Nuevo' ?> usuario | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main class="container narrow"><p><a href="usuarios.php">← Volver a usuarios</a></p><h1><?= $esEdicion ? 'Editar usuario' : 'Nuevo usuario' ?></h1><?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?><form method="post" class="form-grid"><div><label>Usuario *</label><input name="nombre_usuario" required value="<?= escapar_html($usuario['nombre_usuario']) ?>"></div><div><label>Nombre completo *</label><input name="nombre_completo" required value="<?= escapar_html($usuario['nombre_completo']) ?>"></div><div class="full"><label><?= $esEdicion ? 'Nueva contraseña (opcional)' : 'Contraseña *' ?></label><input type="password" name="contrasena" <?= $esEdicion ? '' : 'required' ?> minlength="8"></div><div class="full"><label>Roles *</label><div class="study-picker"><?php foreach ($roles as $rol): ?><label class="study-option"><input type="checkbox" name="roles_ids[]" value="<?= (int) $rol['id'] ?>" <?= in_array((int) $rol['id'], $rolesSeleccionados, true) ? 'checked' : '' ?>><span><?= escapar_html($rol['nombre']) ?> — <?= escapar_html($rol['descripcion']) ?></span></label><?php endforeach; ?></div></div><div class="full"><button type="submit">Guardar usuario</button></div></form></main></body></html>








