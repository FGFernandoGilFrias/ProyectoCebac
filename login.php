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
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Acceso | CEBAC</title>

<!-- Bootstrap 5.3 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<!-- CSS propio -->
<link rel="stylesheet" href="public/assets/app.css?v=<?= $cssVersion ?>">
</head>
<body class="login-page">

<div class="container-fluid min-vh-100 d-flex align-items-center justify-content-center p-3">
    <div class="login-card shadow-lg">

        <?php if ($logoExiste): ?>
            <img src="public/assets/logo.png" alt="CEBAC" class="login-logo">
        <?php else: ?>
            <div class="text-center mb-3">
                <i class="bi bi-droplet-fill text-primary" style="font-size: 3rem;"></i>
            </div>
        <?php endif; ?>

        <h1 class="text-center h4 fw-bold mb-1">Laboratorio CEBAC S.R.L.</h1>
        <p class="text-center text-muted small mb-4">Acceso al sistema de gestión</p>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= escapar_html($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="on">
            <div class="mb-3">
                <label for="nombre_usuario" class="form-label">Usuario</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="nombre_usuario" name="nombre_usuario" required autofocus autocomplete="username">
                </div>
            </div>

            <div class="mb-4">
                <label for="contrasena" class="form-label">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="contrasena" name="contrasena" required autocomplete="current-password">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar sesión
            </button>
        </form>

        <p class="text-center text-muted small mt-4 mb-0">
            Sistema CEBAC &copy; <?= date('Y') ?>
        </p>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>