<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('usuarios.gestionar');
$roles = conexion_bd()->query(
    "SELECT r.id, r.nombre, r.descripcion, r.activo, COUNT(DISTINCT ur.usuario_id) AS cantidad_usuarios,
            COUNT(DISTINCT rp.privilegio_id) AS cantidad_privilegios
     FROM roles r
     LEFT JOIN usuarios_roles ur ON ur.rol_id = r.id
     LEFT JOIN roles_privilegios rp ON rp.rol_id = r.id
     GROUP BY r.id ORDER BY r.nombre"
)->fetchAll();
$mensaje = mensaje_flash();
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Roles | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main class="container"><div class="page-heading"><div><h1>Roles y privilegios</h1><p class="muted">Define a qué partes del sistema puede acceder cada rol.</p></div><a class="button-link" href="rol_formulario.php">Nuevo rol</a></div><?php if ($mensaje): ?><div class="alert success-alert"><?= escapar_html($mensaje) ?></div><?php endif; ?><div class="table-wrap"><table><thead><tr><th>Rol</th><th>Descripción</th><th>Usuarios</th><th>Privilegios</th><th>Estado</th><th></th></tr></thead><tbody><?php foreach ($roles as $rol): ?><tr><td><?= escapar_html($rol['nombre']) ?></td><td><?= escapar_html($rol['descripcion']) ?></td><td><?= (int) $rol['cantidad_usuarios'] ?></td><td><?= (int) $rol['cantidad_privilegios'] ?></td><td><?= $rol['activo'] ? 'Activo' : 'Inactivo' ?></td><td><a href="rol_formulario.php?id=<?= (int) $rol['id'] ?>">Administrar</a></td></tr><?php endforeach; ?></tbody></table></div></main></body></html>








