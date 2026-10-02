<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('estudios.gestionar');

$search = trim((string) ($_GET['q'] ?? ''));
$consultaSql = 'SELECT e.*, p.precio
                FROM estudios e
                LEFT JOIN precio_practica p ON p.codigo = e.practica_codigo';
$params = [];
if ($search !== '') {
    $consultaSql .= ' WHERE e.codigo LIKE :q OR e.estudio LIKE :q';
    $params['q'] = '%' . $search . '%';
}
$consultaSql .= ' ORDER BY e.estudio LIMIT 100';
$consulta = conexion_bd()->prepare($consultaSql);
$consulta->execute($params);
$estudios = $consulta->fetchAll();
$mensaje = mensaje_flash();
$tituloPagina = 'Estudios | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container">
<div class="page-heading">
    <div>
        <h1>Estudios</h1>
        <p class="muted">Catálogo de estudios del laboratorio.</p>
    </div>
    <a class="button-link" href="estudio_formulario.php">Nuevo estudio</a>
</div>
<?php if ($mensaje): ?><div class="alert success-alert"><?= escapar_html($mensaje) ?></div><?php endif; ?>
<form class="search-form" method="get">
    <input name="q" value="<?= escapar_html($search) ?>" placeholder="Buscar por código o nombre">
    <button type="submit">Buscar</button>
</form>
<div class="table-wrap">
    <table>
        <thead>
            <tr><th>Código</th><th>Estudio</th><th>Parámetros</th><th>Precio</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($estudios as $estudio): ?>
                <tr>
                    <td><strong><?= escapar_html($estudio['codigo']) ?></strong></td>
                    <td><?= escapar_html($estudio['estudio']) ?></td>
                    <td><?= escapar_html($estudio['parametros'] ?? '—') ?></td>
                    <td>$ <?= number_format((float) ($estudio['precio'] ?? 0), 2, ',', '.') ?></td>
                    <td><a href="estudio_formulario.php?id=<?= urlencode($estudio['codigo']) ?>">Editar</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$estudios): ?>
                <tr><td colspan="5" class="muted">No hay estudios registrados.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>