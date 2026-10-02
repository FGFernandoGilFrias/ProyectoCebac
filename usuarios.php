<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('usuarios.gestionar');

$conexion = conexion_bd();
$usuarios = $conexion->query(
    "SELECT u.codigo, u.nombre_usuario, u.nombre_completo, u.email, u.telefono, u.cargo, u.matricula,
            u.activo, u.ultimo_acceso,
            COALESCE(GROUP_CONCAT(DISTINCT r.nombre ORDER BY r.nombre SEPARATOR ', '), 'Sin rol') AS roles
     FROM usuarios u
     LEFT JOIN usuarios_roles ur ON ur.usuario_codigo = u.codigo
     LEFT JOIN roles r ON r.codigo = ur.rol_codigo
     GROUP BY u.codigo ORDER BY u.nombre_completo"
)->fetchAll();

$mensaje = mensaje_flash();

function iniciales_listado(string $nombre): string
{
    $partes = preg_split('/\s+/', trim($nombre));
    $a = mb_substr($partes[0] ?? '', 0, 1, 'UTF-8');
    $b = isset($partes[1]) ? mb_substr($partes[1], 0, 1, 'UTF-8') : '';
    return mb_strtoupper($a . $b, 'UTF-8');
}

$tituloPagina = 'Usuarios | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container">
<div class="page-heading">
    <div>
        <h1>Usuarios y accesos</h1>
        <p class="muted">Administrá usuarios y los roles que determinan sus privilegios.</p>
    </div>
    <a class="button-link" href="usuario_formulario.php">Nuevo usuario</a>
</div>
<?php if ($mensaje): ?><div class="alert success-alert"><?= escapar_html($mensaje) ?></div><?php endif; ?>
<div class="table-wrap">
    <table>
        <thead>
            <tr><th></th><th>Usuario</th><th>Contacto</th><th>Roles</th><th>Estado</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $usuario): ?>
                <tr>
                    <td>
                        <div class="user-mini-avatar"><?= escapar_html(iniciales_listado($usuario['nombre_completo'])) ?></div>
                    </td>
                    <td>
                        <strong><?= escapar_html($usuario['nombre_completo']) ?></strong><br>
                        <small class="muted">@<?= escapar_html($usuario['nombre_usuario']) ?></small>
                        <?php if (!empty($usuario['cargo'])): ?>
                            <br><small class="muted"><?= escapar_html($usuario['cargo']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($usuario['email'])): ?>
                            <small><?= escapar_html($usuario['email']) ?></small><br>
                        <?php endif; ?>
                        <?php if (!empty($usuario['telefono'])): ?>
                            <small class="muted"><?= escapar_html($usuario['telefono']) ?></small>
                        <?php endif; ?>
                        <?php if (empty($usuario['email']) && empty($usuario['telefono'])): ?>
                            <small class="muted">—</small>
                        <?php endif; ?>
                    </td>
                    <td><?= escapar_html($usuario['roles']) ?></td>
                    <td>
                        <span class="badge <?= $usuario['activo'] ? 'success' : 'danger' ?>"><?= $usuario['activo'] ? 'Activo' : 'Inactivo' ?></span>
                        <?php if (!empty($usuario['ultimo_acceso'])): ?>
                            <br><small class="muted">Acceso: <?= escapar_html($usuario['ultimo_acceso']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="usuario_formulario.php?id=<?= (int) $usuario['codigo'] ?>">Editar</a>
                        <?php if ((int) $usuario['codigo'] !== (int) usuario_actual()['codigo']): ?>
                            · <form method="post" action="usuario_estado.php" style="display:inline">
                                <input type="hidden" name="id" value="<?= (int) $usuario['codigo'] ?>">
                                <input type="hidden" name="activo" value="<?= $usuario['activo'] ? 0 : 1 ?>">
                                <button type="submit" class="link-button" data-confirm="¿<?= $usuario['activo'] ? 'Desactivar' : 'Activar' ?> este usuario?">
                                    <?= $usuario['activo'] ? 'Desactivar' : 'Activar' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$usuarios): ?>
                <tr><td colspan="6" class="muted">No hay usuarios registrados.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>