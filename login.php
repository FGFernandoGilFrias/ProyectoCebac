<?php
require_once __DIR__ . '/includes/auth.php';

if (esta_autenticado()) {
    header('Location: ordenes.php');
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
            "SELECT u.codigo, u.nombre_usuario, u.nombre_completo, u.hash_contrasena, u.matricula,
                    COALESCE(GROUP_CONCAT(DISTINCT r.nombre ORDER BY r.nombre SEPARATOR ', '), 'Sin rol') AS rol
             FROM usuarios u
             LEFT JOIN usuarios_roles ur ON ur.usuario_codigo = u.codigo
             LEFT JOIN roles r ON r.codigo = ur.rol_codigo AND r.activo = 1
             WHERE u.nombre_usuario = :nombre_usuario AND u.activo = 1
             GROUP BY u.codigo LIMIT 1"
        );
        $consulta->execute(['nombre_usuario' => $nombre_usuario]);
        $usuario = $consulta->fetch();
        if ($usuario && password_verify($contrasena, $usuario['hash_contrasena'])) {
            session_regenerate_id(true);
            unset($usuario['hash_contrasena']);
            $_SESSION['usuario'] = $usuario;
            conexion_bd()->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE codigo = :codigo')
                ->execute(['codigo' => (int) $usuario['codigo']]);
            header('Location: ordenes.php');
            exit;
        }
        $error = 'Las credenciales no son válidas.';
    }
}

$cssPath = __DIR__ . '/public/assets/app.css';
$cssVersion = file_exists($cssPath) ? filemtime($cssPath) : time();
$logoPath = __DIR__ . '/public/assets/logo.png';
$logoExiste = file_exists($logoPath);
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Acceso | CEBAC</title>
<link rel="stylesheet" href="public/assets/app.css?v=<?= $cssVersion ?>">
</head>
<body class="login-page">
<main class="login-card">
    <?php if ($logoExiste): ?>
        <img src="public/assets/logo.png" alt="CEBAC" class="login-logo">
    <?php else: ?>
        <div class="brand">CEBAC</div>
    <?php endif; ?>
    <h1>Laboratorio CEBAC S.R.L.</h1>
    <p class="muted">Acceso al sistema de gestión</p>
    <?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="on">
        <label for="nombre_usuario">Usuario</label>
        <input id="nombre_usuario" name="nombre_usuario" required autofocus>
        <label for="contrasena">Contraseña</label>
        <input id="contrasena" name="contrasena" type="password" required>
        <button type="submit">Iniciar sesión</button>
    </form>
</main>
</body>
</html>