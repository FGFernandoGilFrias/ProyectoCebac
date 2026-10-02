<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('usuarios.gestionar');

$roles = conexion_bd()->query(
    "SELECT r.codigo, r.nombre, r.descripcion, r.activo,
            COUNT(DISTINCT ur.usuario_codigo) AS cantidad_usuarios,
            COUNT(DISTINCT rp.privilegio_codigo) AS cantidad_privilegios
     FROM roles r
     LEFT JOIN usuarios_roles ur ON ur.rol_codigo = r.codigo
     LEFT JOIN roles_privilegios rp ON rp.rol_codigo = r.codigo
     GROUP BY r.codigo ORDER BY r.nombre"
)->fetchAll();
$mensaje = mensaje_flash();
$tituloPagina = 'Roles | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container">
<div class="page-heading">
    <div>
        <h1>Roles y privilegios</h1>
        <p class="muted">Define a qué partes del sistema puede acceder cada rol.</p>
    </div>
    <a class="button-link" href="rol_formulario.php">Nuevo rol</a>
</div>
<?php if ($mensaje): ?><div class="alert success-alert"><?= escapar_html($mensaje) ?></div><?php endif; ?>
<div class="table-wrap">
    <table>
        <thead>
            <tr><th>Rol</th><th>Descripción</th><th>Usuarios</th><th>Privilegios</th><th>Estado</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($roles as $rol): ?>
                <tr>
                    <td><strong><?= escapar_html($rol['nombre']) ?></strong></td>
                    <td><?= escapar_html($rol['descripcion']) ?></td>
                    <td><?= (int) $rol['cantidad_usuarios'] ?></td>
                    <td><?= (int) $rol['cantidad_privilegios'] ?></td>
                    <td><span class="badge <?= $rol['activo'] ? 'success' : '' ?>"><?= $rol['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
                    <td><a href="rol_formulario.php?id=<?= (int) $rol['codigo'] ?>">Administrar</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>