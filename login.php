<?php
require_once __DIR__ . '/includes/auth.php';

if (esta_autenticado()) {
    header('Location: panel.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_usuario = trim((string) ($_POST['nombre_usuario'] ?? ''));
    $contrasena = (string) ($_POST['contrasena'] ?? '');
    if ($nombre_usuario === '' || $contrasena === '') {
        $error = 'Ingresa tu usuario y contraseña.';
    } else {
        $consulta = conexion_bd()->prepare(
            "SELECT u.id, u.nombre_usuario, u.nombre_completo, u.hash_contrasena,
                    COALESCE(GROUP_CONCAT(DISTINCT r.nombre ORDER BY r.nombre SEPARATOR ', '), u.rol) AS rol
             FROM usuarios u
             LEFT JOIN usuarios_roles ur ON ur.usuario_id = u.id
             LEFT JOIN roles r ON r.id = ur.rol_id AND r.activo = 1
             WHERE u.nombre_usuario = :nombre_usuario AND u.activo = 1
             GROUP BY u.id LIMIT 1"
        );
        $consulta->execute(['nombre_usuario' => $nombre_usuario]);
        $usuario = $consulta->fetch();
        if ($usuario && password_verify($contrasena, $usuario['hash_contrasena'])) {
            session_regenerate_id(true);
            unset($usuario['hash_contrasena']);
            $_SESSION['usuario'] = $usuario;
            header('Location: panel.php');
            exit;
        }
        $error = 'Las credenciales no son válidas.';
    }
}
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Acceso | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head>
<body class="login-page"><main class="login-card"><div class="brand">CEBAC</div><h1>Laboratorio CEBAC S.R.L.</h1><p class="muted">Acceso al sistema de gestión</p><?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?><form method="post" autocomplete="on"><label for="nombre_usuario">Usuario</label><input id="nombre_usuario" name="nombre_usuario" required autofocus><label for="contrasena">Contraseña</label><input id="contrasena" name="contrasena" type="password" required><button type="submit">Iniciar sesión</button></form></main></body></html>








