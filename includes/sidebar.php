<?php
$paginaActual = basename($_SERVER['PHP_SELF']);
$gruposMenu = [
    'Gestión de pacientes' => [
        ['label' => 'Pacientes', 'href' => 'pacientes.php', 'icon' => 'P', 'privilegio' => 'pacientes.gestionar'],
        ['label' => 'Órdenes médicas', 'href' => 'ordenes.php', 'icon' => 'O', 'privilegio' => 'ordenes.gestionar'],
        ['label' => 'Caja', 'href' => 'caja.php', 'icon' => '$', 'privilegio' => 'caja.gestionar'],
    ],
    'Gestión de muestras' => [
        ['label' => 'Resultados', 'href' => 'resultados.php', 'icon' => 'R', 'privilegio' => 'resultados.gestionar'],
        ['label' => 'Muestras', 'href' => 'recepcion_muestras.php', 'icon' => 'M', 'privilegio' => 'muestras.gestionar'],
    ],
    'Gestión de obra social' => [
        ['label' => 'Obras sociales', 'href' => 'obras_sociales.php', 'icon' => 'S', 'privilegio' => 'obras_sociales.gestionar'],
    ],
    'Gestión administrativa' => [
        ['label' => 'Precios de prácticas', 'href' => 'precios_practicas.php', 'icon' => 'P', 'privilegio' => 'practicas.gestionar'],
        ['label' => 'Estudios', 'href' => 'estudios.php', 'icon' => 'E', 'privilegio' => 'estudios.gestionar'],
        ['label' => 'Parámetros', 'href' => 'parametros.php', 'icon' => 'T', 'privilegio' => 'parametros.gestionar'],
        ['label' => 'Usuarios', 'href' => 'usuarios.php', 'icon' => 'U', 'privilegio' => 'usuarios.gestionar'],
        ['label' => 'Roles y privilegios', 'href' => 'roles.php', 'icon' => 'A', 'privilegio' => 'usuarios.gestionar'],
    ],
];
?>
<aside class="sidebar">
    <div class="sidebar-brand"><span class="brand-mark">C</span><span>CEBAC<small>Laboratorio</small></span></div>
    <nav class="sidebar-nav" aria-label="Navegación principal">
        <?php $enlacePanel = ['label' => 'Panel principal', 'href' => 'panel.php', 'icon' => '⌂', 'privilegio' => 'panel.ver']; ?>
        <?php if (tiene_privilegio($enlacePanel['privilegio'])): ?>
            <a class="sidebar-link <?= $paginaActual === $enlacePanel['href'] ? 'activo' : '' ?>" href="<?= escapar_html($enlacePanel['href']) ?>">
                <span class="sidebar-icon"><?= escapar_html($enlacePanel['icon']) ?></span><span><?= escapar_html($enlacePanel['label']) ?></span>
            </a>
        <?php endif; ?>
        <?php foreach ($gruposMenu as $nombreGrupo => $enlaces): ?>
            <p class="sidebar-group"><?= escapar_html($nombreGrupo) ?></p>
            <?php foreach ($enlaces as $enlace): ?>
                <?php if (!tiene_privilegio($enlace['privilegio'])) { continue; } ?>
                <a class="sidebar-link <?= $paginaActual === $enlace['href'] ? 'activo' : '' ?>" href="<?= escapar_html($enlace['href']) ?>">
                    <span class="sidebar-icon"><?= escapar_html($enlace['icon']) ?></span><span><?= escapar_html($enlace['label']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user"><strong><?= escapar_html(usuario_actual()['nombre_completo'] ?? '') ?></strong><span><?= escapar_html(usuario_actual()['rol'] ?? '') ?></span></div>
        <a class="sidebar-logout" href="cerrar_sesion.php">Cerrar sesión</a>
    </div>
</aside>







