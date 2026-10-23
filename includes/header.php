<?php
/**
 * includes/header.php
 * Cabecera HTML común para todas las páginas del sistema.
 * Usa Bootstrap 5.3 por CDN.
 */
if (!isset($tituloPagina)) {
    $tituloPagina = 'CEBAC';
}

$cssPath = __DIR__ . '/../public/assets/app.css';
$cssVersion = file_exists($cssPath) ? filemtime($cssPath) : time();

$jsPath = __DIR__ . '/../public/assets/app.js';
$jsVersion = file_exists($jsPath) ? filemtime($jsPath) : time();
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= escapar_html($tituloPagina) ?></title>

<!-- Bootstrap 5.3 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<!-- CSS propio (después de Bootstrap para poder sobrescribir) -->
<link rel="stylesheet" href="public/assets/app.css?v=<?= $cssVersion ?>">
</head>
<body>

<!-- Navbar superior (fija) -->
<nav class="navbar navbar-dark bg-primary fixed-top shadow-sm" id="topbar">
    <div class="container-fluid">
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-label="Abrir menú">
            <span class="navbar-toggler-icon"></span>
        </button>

        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="panel.php">
            <i class="bi bi-droplet-fill"></i>
            <span>CEBAC</span>
        </a>

        <div class="dropdown">
            <button class="btn btn-sm btn-outline-light dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle"></i>
                <span class="d-none d-md-inline"><?= escapar_html(usuario_actual()['nombre_completo'] ?? 'Usuario') ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header"><?= escapar_html(usuario_actual()['nombre_completo'] ?? '') ?></h6></li>
                <li><small class="dropdown-item-text text-muted"><?= escapar_html(usuario_actual()['rol'] ?? '') ?></small></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="usuarios.php"><i class="bi bi-people me-2"></i>Usuarios</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="cerrar_sesion.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
            </ul>
        </div>
    </div>
</nav>

<?php include __DIR__ . '/sidebar.php'; ?>

<!-- Contenido principal -->
<main class="main-content" id="mainContent">