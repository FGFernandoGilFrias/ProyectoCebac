<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('estudios.gestionar');

$search = trim((string) ($_GET['q'] ?? ''));
$consultaSql = 'SELECT s.*, p.codigo_practica, p.descripcion AS descripcion_practica, p.precio FROM estudios s LEFT JOIN precios_practicas p ON p.id = s.precio_practica_id';
$params = [];
if ($search !== '') {
    $consultaSql .= ' WHERE s.codigo LIKE :q OR s.nombre LIKE :q OR p.codigo_practica LIKE :q';
    $params['q'] = '%' . $search . '%';
}
$consultaSql .= ' ORDER BY activo DESC, nombre LIMIT 100';
$consulta = conexion_bd()->prepare($consultaSql);
$consulta->execute($params);
$estudios = $consulta->fetchAll();
$mensaje = mensaje_flash();
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Estudios | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main class="container"><div class="page-heading"><div><h1>Estudios y precios</h1><p class="muted">Catálogo de prácticas del laboratorio.</p></div><a class="button-link" href="estudio_formulario.php">Nuevo estudio</a></div><?php if ($mensaje): ?><div class="alert success-alert"><?= escapar_html($mensaje) ?></div><?php endif; ?><form class="search-form" method="get"><input name="q" value="<?= escapar_html($search) ?>" placeholder="Buscar por código, práctica o nombre"><button type="submit">Buscar</button></form><div class="table-wrap"><table><thead><tr><th>Código</th><th>Estudio</th><th>Práctica asociada</th><th>Precio</th><th>Estado</th><th></th></tr></thead><tbody><?php foreach ($estudios as $estudio): ?><tr><td><?= escapar_html($estudio['codigo']) ?></td><td><?= escapar_html($estudio['nombre']) ?></td><td><?= escapar_html($estudio['codigo_practica'] . ' - ' . ($estudio['descripcion_practica'] ?? '')) ?></td><td>$ <?= number_format((float) $estudio['precio'], 2, ',', '.') ?></td><td><span class="badge <?= $estudio['activo'] ? 'success' : '' ?>"><?= $estudio['activo'] ? 'Activo' : 'Inactivo' ?></span></td><td><a href="estudio_formulario.php?id=<?= (int) $estudio['id'] ?>">Editar</a></td></tr><?php endforeach; ?><?php if (!$estudios): ?><tr><td colspan="6" class="muted">No hay estudios registrados.</td></tr><?php endif; ?></tbody></table></div></main></body></html>








