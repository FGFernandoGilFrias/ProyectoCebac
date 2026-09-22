<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('ordenes.gestionar');
$q = trim((string) ($_GET['q'] ?? ''));
$consultaSql = "SELECT o.*, p.dni, p.apellido, p.nombre,
        COUNT(os.id) AS cantidad_estudios, o.total_adeudado AS total
        FROM ordenes o JOIN pacientes p ON p.id = o.paciente_id
        LEFT JOIN ordenes_estudios os ON os.orden_id = o.id";
$params = [];
if ($q !== '') {
    $consultaSql .= ' WHERE o.codigo LIKE :q OR p.dni LIKE :q OR p.apellido LIKE :q OR p.nombre LIKE :q';
    $params['q'] = '%' . $q . '%';
}
$consultaSql .= ' GROUP BY o.id ORDER BY o.fecha_orden DESC, o.id DESC LIMIT 100';
$consulta = conexion_bd()->prepare($consultaSql);
$consulta->execute($params);
$ordenes = $consulta->fetchAll();
$mensaje = mensaje_flash();
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Órdenes | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main class="container"><div class="page-heading"><div><h1>Órdenes médicas</h1><p class="muted">Registro de órdenes y estudios solicitados.</p></div><a class="button-link" href="orden_formulario.php">Nueva orden</a></div><?php if ($mensaje): ?><div class="alert success-alert"><?= escapar_html($mensaje) ?></div><?php endif; ?><form class="search-form" method="get"><input name="q" value="<?= escapar_html($q) ?>" placeholder="Buscar por orden, DNI o paciente"><button type="submit">Buscar</button></form><div class="table-wrap"><table><thead><tr><th>Orden</th><th>Paciente</th><th>Fecha</th><th>Médico</th><th>Estudios</th><th>Total</th><th>Estado</th></tr></thead><tbody><?php foreach ($ordenes as $orden): ?><tr><td><a href="orden_detalle.php?id=<?= (int) $orden['id'] ?>"><?= escapar_html($orden['codigo']) ?></a></td><td><?= escapar_html($orden['apellido'] . ', ' . $orden['nombre']) ?><br><small><?= escapar_html($orden['dni']) ?></small></td><td><?= escapar_html($orden['fecha_orden']) ?></td><td><?= escapar_html($orden['medico']) ?></td><td><?= (int) $orden['cantidad_estudios'] ?></td><td>$ <?= number_format((float) $orden['total'], 2, ',', '.') ?></td><td><span class="badge"><?= escapar_html($orden['estado']) ?></span><br><a class="button-small" href="orden_detalle.php?id=<?= (int) $orden['id'] ?>">Ver detalle</a></td></tr><?php endforeach; ?><?php if (!$ordenes): ?><tr><td colspan="7" class="muted">No hay órdenes registradas.</td></tr><?php endif; ?></tbody></table></div></main></body></html>








