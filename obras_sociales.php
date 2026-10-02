<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('obras_sociales.gestionar');

$search = trim((string) ($_GET['q'] ?? ''));
$consultaSql = 'SELECT s.codigo, s.nombre, s.cuit, s.condiciones_convenio, s.fecha_alta, s.fecha_baja,
                       COUNT(DISTINCT c.codigo) AS cantidad_credenciales,
                       COUNT(DISTINCT pos.paciente_codigo) AS cantidad_pacientes
                FROM obras_sociales s
                LEFT JOIN obras_sociales_credenciales c ON c.obra_social_codigo = s.codigo
                LEFT JOIN paciente_obra_social pos ON pos.credencial_codigo = c.codigo';
$params = [];
if ($search !== '') {
    $consultaSql .= ' WHERE s.nombre LIKE :q OR s.cuit LIKE :q';
    $params['q'] = '%' . $search . '%';
}
$consultaSql .= ' GROUP BY s.codigo ORDER BY s.fecha_baja ASC, s.nombre LIMIT 100';
$consulta = conexion_bd()->prepare($consultaSql);
$consulta->execute($params);
$obrasSociales = $consulta->fetchAll();
$mensaje = mensaje_flash();
$tituloPagina = 'Obras sociales | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container">
<div class="page-heading">
    <div>
        <h1>Obras sociales</h1>
        <p class="muted">Convenios con obras sociales y prepagas, con su catálogo de credenciales.</p>
    </div>
    <a class="button-link" href="obra_social_formulario.php">Nueva obra social</a>
</div>
<?php if ($mensaje): ?><div class="alert success-alert"><?= escapar_html($mensaje) ?></div><?php endif; ?>
<form class="search-form" method="get">
    <input name="q" value="<?= escapar_html($search) ?>" placeholder="Buscar por nombre o CUIT">
    <button type="submit">Buscar</button>
</form>
<div class="table-wrap">
    <table>
        <thead>
            <tr><th>Código</th><th>Nombre</th><th>CUIT</th><th>Credenciales</th><th>Pacientes</th><th>Fecha alta</th><th>Estado</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($obrasSociales as $os): ?>
                <tr>
                    <td><?= (int) $os['codigo'] ?></td>
                    <td><strong><?= escapar_html($os['nombre']) ?></strong></td>
                    <td><?= escapar_html($os['cuit']) ?></td>
                    <td><?= (int) $os['cantidad_credenciales'] ?></td>
                    <td><?= (int) $os['cantidad_pacientes'] ?></td>
                    <td><?= escapar_html($os['fecha_alta'] ?? '—') ?></td>
                    <td>
                        <?php if ($os['fecha_baja']): ?>
                            <span class="badge danger">Dada de baja</span>
                            <br><small class="muted"><?= escapar_html($os['fecha_baja']) ?></small>
                        <?php else: ?>
                            <span class="badge success">Activa</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="obra_social_formulario.php?id=<?= (int) $os['codigo'] ?>">Editar</a>
                        · <form method="post" action="obra_social_baja.php" style="display:inline">
                            <input type="hidden" name="id" value="<?= (int) $os['codigo'] ?>">
                            <input type="hidden" name="accion" value="<?= $os['fecha_baja'] ? 'reactivar' : 'baja' ?>">
                            <button type="submit" class="link-button" data-confirm="¿<?= $os['fecha_baja'] ? 'Reactivar' : 'Dar de baja' ?> esta obra social?">
                                <?= $os['fecha_baja'] ? 'Reactivar' : 'Dar de baja' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$obrasSociales): ?>
                <tr><td colspan="8" class="muted">No hay obras sociales registradas.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>