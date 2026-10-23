<?php
$paginaActual = basename($_SERVER['PHP_SELF']);
$gruposMenu = [
    'Operaciones' => [
        ['label' => 'Pacientes', 'href' => 'pacientes.php', 'icon' => 'P', 'privilegio' => 'pacientes.manage'],
        ['label' => 'Órdenes médicas', 'href' => 'ordenes.php', 'icon' => 'O', 'privilegio' => 'ordenes.manage'],
        ['label' => 'Caja', 'href' => 'caja.php', 'icon' => '$', 'privilegio' => 'cash.manage'],
        ['label' => 'Muestras', 'href' => 'recepcion_muestra.php', 'icon' => 'M', 'privilegio' => 'muestras.manage'],
        ['label' => 'Resultados', 'href' => 'resultados.php', 'icon' => 'R', 'privilegio' => 'resultados.manage'],
    ],
    'Catálogos' => [
        ['label' => 'Obras sociales', 'href' => 'obras_sociales.php', 'icon' => 'S', 'privilegio' => 'obras_sociales.manage'],
        ['label' => 'Estudios', 'href' => 'estudios.php', 'icon' => 'E', 'privilegio' => 'estudios.manage'],
        ['label' => 'Parámetros', 'href' => 'parametros.php', 'icon' => 'T', 'privilegio' => 'parametros.manage'],
        ['label' => 'Precios de prácticas', 'href' => 'precios_practicas.php', 'icon' => 'P', 'privilegio' => 'practices.manage'],
        ['label' => 'Usuarios', 'href' => 'usuarios.php', 'icon' => 'U', 'privilegio' => 'usuarios.manage'],
        ['label' => 'Roles y privilegios', 'href' => 'roles.php', 'icon' => 'A', 'privilegio' => 'usuarios.manage'],
    ],
];
?>
<aside class="sidebar">
    <div class="sidebar-brand"><span class="brand-mark">C</span><span>CEBAC<small>Laboratorio</small></span></div>
    <nav class="sidebar-nav" aria-label="Navegación principal">
        <?php foreach ($gruposMenu as $nombreGrupo => $enlaces): ?>
            <p class="sidebar-group"><?= e($nombreGrupo) ?></p>
            <?php foreach ($enlaces as $enlace): ?>
                <?php if (!tiene_privilegio($enlace['privilegio'])) { continue; } ?>
                <a class="sidebar-link <?= $paginaActual === $enlace['href'] ? 'active' : '' ?>" href="<?= e($enlace['href']) ?>">
                    <span class="sidebar-icon"><?= e($enlace['icon']) ?></span><span><?= e($enlace['label']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user"><strong><?= e(usuario_actual()['nombre_completo'] ?? '') ?></strong><span><?= e(usuario_actual()['rol'] ?? '') ?></span></div>
        <a class="sidebar-logout" href="cerrar_sesion.php">Cerrar sesión</a>
    </div>
</aside>


