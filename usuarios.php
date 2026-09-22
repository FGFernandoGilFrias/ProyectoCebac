<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('usuarios.gestionar');
$usuarios = conexion_bd()->query(
    "SELECT u.id, u.nombre_usuario, u.nombre_completo, u.activo,
            COALESCE(GROUP_CONCAT(DISTINCT r.nombre ORDER BY r.nombre SEPARATOR ', '), u.rol) AS roles
     FROM usuarios u
     LEFT JOIN usuarios_roles ur ON ur.usuario_id = u.id
     LEFT JOIN roles r ON r.id = ur.rol_id
     GROUP BY u.id ORDER BY u.nombre_completo"
)->fetchAll();
$mensaje = mensaje_flash();
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Usuarios | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main class="container"><div class="page-heading"><div><h1>Usuarios y accesos</h1><p class="muted">Administra usuarios y los roles que determinan sus privilegios.</p></div><a class="button-link" href="usuario_formulario.php">Nuevo usuario</a></div><?php if ($mensaje): ?><div class="alert success-alert"><?= escapar_html($mensaje) ?></div><?php endif; ?><div class="table-wrap"><table><thead><tr><th>Usuario</th><th>Nombre</th><th>Roles</th><th>Estado</th><th></th></tr></thead><tbody><?php foreach ($usuarios as $usuario): ?><tr><td><?= escapar_html($usuario['nombre_usuario']) ?></td><td><?= escapar_html($usuario['nombre_completo']) ?></td><td><?= escapar_html($usuario['roles']) ?></td><td><span class="badge <?= $usuario['activo'] ? 'success' : '' ?>"><?= $usuario['activo'] ? 'Activo' : 'Inactivo' ?></span></td><td><a href="usuario_formulario.php?id=<?= (int) $usuario['id'] ?>">Editar</a><?php if ((int) $usuario['id'] !== (int) usuario_actual()['id']): ?> · <a href="usuario_estado.php?id=<?= (int) $usuario['id'] ?>&activo=<?= $usuario['activo'] ? 0 : 1 ?>"><?= $usuario['activo'] ? 'Desactivar' : 'Activar' ?></a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></main></body></html>








