<?php
require_once __DIR__ . '/includes/autenticacion.php';
exigir_privilegio('usuarios.manage');
$id = (int) ($_GET['id'] ?? 0);
$esEdicion = $id > 0;
$rol = ['nombre' => '', 'descripcion' => ''];
$pdo = db();
$selectedPrivileges = [];
if ($esEdicion) {
    $stmt = $pdo->prepare('SELECT id, nombre, descripcion FROM roles WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $rol = $stmt->fetch() ?: $rol;
    $privilegeEtmt = $pdo->prepare('SELECT privilegio_id FROM roles_privilegios WHERE rol_id = :id');
    $privilegeEtmt->execute(['id' => $id]);
    $selectedPrivileges = array_map('intval', $privilegeEtmt->fetchAll(PDO::FETCH_COLUMN));
}
$privilegios = $pdo->query('SELECT id, codigo, nombre, descripcion FROM privilegios ORDER BY codigo')->fetchAll();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rol['nombre'] = trim((string) ($_POST['nombre'] ?? ''));
    $rol['descripcion'] = trim((string) ($_POST['descripcion'] ?? ''));
    $selectedPrivileges = array_values(array_unique(array_map('intval', $_POST['privilege_ids'] ?? [])));
    if ($rol['nombre'] === '') {
        $error = 'El nombre del rol es obligatorio.';
    } else {
        try {
            $pdo->beginTransaction();
            if ($esEdicion) {
                $pdo->prepare('UPDATE roles SET nombre=:nombre, descripcion=:descripcion WHERE id=:id')->execute(['nombre' => $rol['nombre'], 'descripcion' => $rol['descripcion'], 'id' => $id]);
                $pdo->prepare('DELETE FROM roles_privilegios WHERE rol_id = :id')->execute(['id' => $id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO roles (nombre, descripcion) VALUES (:nombre, :descripcion)');
                $stmt->execute(['nombre' => $rol['nombre'], 'descripcion' => $rol['descripcion']]);
                $id = (int) $pdo->lastInsertId();
            }
            $save = $pdo->prepare('INSERT INTO roles_privilegios (rol_id, privilegio_id) VALUES (:rol_id, :privilegio_id)');
            foreach ($selectedPrivileges as $privilegioId) {
                $save->execute(['rol_id' => $id, 'privilegio_id' => $privilegioId]);
            }
            $pdo->commit();
            flash($esEdicion ? 'Rol actualizado correctamente.' : 'Rol creado correctamente.');
            header('Location: roles.php');
            exit;
        } catch (PDOException $excepcion) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $excepcion->getCode() === '23000' ? 'Ya existe un rol con ese nombre o hay un privilegio inválido.' : 'No se pudo guardar el rol.';
        }
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $esEdicion ? 'Editar' : 'Nuevo' ?> rol | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/barra_lateral.php'; ?><main class="container narrow"><p><a href="roles.php">← Volver a roles</a></p><h1><?= $esEdicion ? 'Editar rol' : 'Nuevo rol' ?></h1><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><form method="post" class="form-grid"><div class="full"><label>Nombre del rol *</label><input name="nombre" required value="<?= e($rol['nombre']) ?>"></div><div class="full"><label>Descripción</label><textarea name="descripcion" rows="3"><?= e($rol['descripcion']) ?></textarea></div><div class="full"><label>Privilegios *</label><div class="estudio-picker"><?php foreach ($privilegios as $privilegio): ?><label class="estudio-option"><input type="checkbox" name="privilege_ids[]" value="<?= (int) $privilegio['id'] ?>" <?= in_array((int) $privilegio['id'], $selectedPrivileges, true) ? 'checked' : '' ?>><span><strong><?= e($privilegio['nombre']) ?></strong> — <?= e($privilegio['descripcion']) ?></span></label><?php endforeach; ?></div></div><div class="full"><button type="submit">Guardar rol</button></div></form></main></body></html>



