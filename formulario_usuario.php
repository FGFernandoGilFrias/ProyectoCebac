<?php
require_once __DIR__ . '/includes/autenticacion.php';
exigir_privilegio('usuarios.manage');
$id = (int) ($_GET['id'] ?? 0);
$esEdicion = $id > 0;
$usuario = ['nombre_usuario' => '', 'nombre_completo' => '', 'activo' => 1];
$rolesSeleccionados = [];
$pdo = db();
if ($esEdicion) {
    $stmt = $pdo->prepare('SELECT id, nombre_usuario, nombre_completo, activo FROM usuarios WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $usuario = $stmt->fetch() ?: $usuario;
    $consultaRol = $pdo->prepare('SELECT rol_id FROM usuarios_roles WHERE usuario_id = :id');
    $consultaRol->execute(['id' => $id]);
    $rolesSeleccionados = array_map('intval', $consultaRol->fetchAll(PDO::FETCH_COLUMN));
}
$roles = $pdo->query('SELECT id, nombre, descripcion FROM roles WHERE activo = 1 ORDER BY nombre')->fetchAll();
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
            $pdo->beginTransaction();
            if ($esEdicion) {
                $sql = 'UPDATE usuarios SET nombre_usuario=:nombre_usuario, nombre_completo=:nombre_completo WHERE id=:id';
                $params = ['nombre_usuario' => $usuario['nombre_usuario'], 'nombre_completo' => $usuario['nombre_completo'], 'id' => $id];
                if ($contrasena !== '') {
                    $sql = 'UPDATE usuarios SET nombre_usuario=:nombre_usuario, nombre_completo=:nombre_completo, hash_contrasena=:hash_contrasena WHERE id=:id';
                    $params['hash_contrasena'] = password_hash($contrasena, PASSWORD_DEFAULT);
                }
                $pdo->prepare($sql)->execute($params);
                $pdo->prepare('DELETE FROM usuarios_roles WHERE usuario_id = :id')->execute(['id' => $id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO usuarios (nombre_usuario, nombre_completo, hash_contrasena, rol) VALUES (:nombre_usuario, :nombre_completo, :hash_contrasena, \'operador\')');
                $stmt->execute(['nombre_usuario' => $usuario['nombre_usuario'], 'nombre_completo' => $usuario['nombre_completo'], 'hash_contrasena' => password_hash($contrasena, PASSWORD_DEFAULT)]);
                $id = (int) $pdo->lastInsertId();
            }
            $insertarRol = $pdo->prepare('INSERT INTO usuarios_roles (usuario_id, rol_id) VALUES (:usuario_id, :rol_id)');
            foreach ($rolesSeleccionados as $rolId) {
                $insertarRol->execute(['usuario_id' => $id, 'rol_id' => $rolId]);
            }
            $pdo->commit();
            flash($esEdicion ? 'Usuario actualizado correctamente.' : 'Usuario creado correctamente.');
            header('Location: usuarios.php');
            exit;
        } catch (PDOException $excepcion) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $excepcion->getCode() === '23000' ? 'El nombre de usuario ya existe o el rol no es válido.' : 'No se pudo guardar el usuario.';
        }
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $esEdicion ? 'Editar' : 'Nuevo' ?> usuario | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/barra_lateral.php'; ?><main class="container narrow"><p><a href="usuarios.php">← Volver a usuarios</a></p><h1><?= $esEdicion ? 'Editar usuario' : 'Nuevo usuario' ?></h1><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><form method="post" class="form-grid"><div><label>Usuario *</label><input name="nombre_usuario" required value="<?= e($usuario['nombre_usuario']) ?>"></div><div><label>Nombre completo *</label><input name="nombre_completo" required value="<?= e($usuario['nombre_completo']) ?>"></div><div class="full"><label><?= $esEdicion ? 'Nueva contraseña (opcional)' : 'Contraseña *' ?></label><input type="password" name="contrasena" <?= $esEdicion ? '' : 'required' ?> minlength="8"></div><div class="full"><label>Roles *</label><div class="estudio-picker"><?php foreach ($roles as $rol): ?><label class="estudio-option"><input type="checkbox" name="roles_ids[]" value="<?= (int) $rol['id'] ?>" <?= in_array((int) $rol['id'], $rolesSeleccionados, true) ? 'checked' : '' ?>><span><?= e($rol['nombre']) ?> — <?= e($rol['descripcion']) ?></span></label><?php endforeach; ?></div></div><div class="full"><button type="submit">Guardar usuario</button></div></form></main></body></html>



