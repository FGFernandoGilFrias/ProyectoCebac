<?php
$paginaActual = basename($_SERVER['PHP_SELF']);

$gruposMenu = [
    'Principal' => [
        ['label' => 'Panel', 'href' => 'panel.php', 'icon' => 'speedometer2', 'privilegio' => 'panel.ver'],
    ],
    'Gestión de Pacientes' => [
        ['label' => 'Pacientes', 'href' => 'pacientes.php', 'icon' => 'people', 'privilegio' => 'pacientes.gestionar'],
        ['label' => 'Órdenes médicas', 'href' => 'ordenes.php', 'icon' => 'file-earmark-medical', 'privilegio' => 'ordenes.gestionar'],
        ['label' => 'Caja', 'href' => 'caja.php', 'icon' => 'cash-coin', 'privilegio' => 'caja.gestionar'],
    ],
    'Gestión de Muestras' => [
        ['label' => 'Check-in de Muestras', 'href' => 'recepcion_muestras.php', 'icon' => 'clipboard-check', 'privilegio' => 'muestras.gestionar'],
        ['label' => 'Resultados', 'href' => 'resultados.php', 'icon' => 'file-earmark-text', 'privilegio' => 'resultados.gestionar'],
    ],
    'Gestión de Obra Social' => [
        ['label' => 'Obras sociales', 'href' => 'obras_sociales.php', 'icon' => 'shield-check', 'privilegio' => 'obras_sociales.gestionar'],
    ],
    'Gestión Administrativa' => [
        ['label' => 'Estudios', 'href' => 'estudios.php', 'icon' => 'journal-medical', 'privilegio' => 'estudios.gestionar'],
        ['label' => 'Precios de prácticas', 'href' => 'precios_practicas.php', 'icon' => 'tag', 'privilegio' => 'practicas.gestionar'],
        ['label' => 'Reportes', 'href' => 'reportes.php', 'icon' => 'bar-chart', 'privilegio' => 'reportes.gestionar'],
    ],
    'Seguridad' => [
        ['label' => 'Usuarios', 'href' => 'usuarios.php', 'icon' => 'person-badge', 'privilegio' => 'usuarios.gestionar'],
        ['label' => 'Roles y privilegios', 'href' => 'roles.php', 'icon' => 'key', 'privilegio' => 'usuarios.gestionar'],
    ],
];

$logoPath = __DIR__ . '/../public/assets/logo.png';
$logoExiste = file_exists($logoPath);
?>
<div class="offcanvas offcanvas-start sidebar-offcanvas" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
    <div class="offcanvas-header sidebar-header">
        <?php if ($logoExiste): ?>
            <img src="public/assets/logo.png" alt="CEBAC" class="sidebar-logo">
        <?php else: ?>
            <h5 class="offcanvas-title text-white fw-bold" id="sidebarMenuLabel">
                <i class="bi bi-droplet-fill me-2"></i>CEBAC
            </h5>
        <?php endif; ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>

    <div class="offcanvas-body sidebar-body p-0">
        <nav class="sidebar-nav">
            <?php foreach ($gruposMenu as $nombreGrupo => $enlaces): ?>
                <?php
                // Verificar si hay al menos un enlace visible
                $hayVisibles = false;
                foreach ($enlaces as $e) {
                    if (tiene_privilegio($e['privilegio'])) { $hayVisibles = true; break; }
                }
                if (!$hayVisibles) continue;
                ?>
                <div class="sidebar-group"><?= escapar_html($nombreGrupo) ?></div>
                <?php foreach ($enlaces as $enlace): ?>
                    <?php if (!tiene_privilegio($enlace['privilegio'])) continue; ?>
                    <a class="sidebar-link <?= $paginaActual === $enlace['href'] ? 'active' : '' ?>" href="<?= escapar_html($enlace['href']) ?>">
                        <i class="bi bi-<?= escapar_html($enlace['icon']) ?>"></i>
                        <span><?= escapar_html($enlace['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <strong><?= escapar_html(usuario_actual()['nombre_completo'] ?? '') ?></strong>
                <small><?= escapar_html(usuario_actual()['rol'] ?? '') ?></small>
            </div>
            <a class="sidebar-logout" href="cerrar_sesion.php">
                <i class="bi bi-box-arrow-right me-1"></i> Cerrar sesión
            </a>
        </div>
    </div>
</div>