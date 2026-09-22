<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('parametros.gestionar');

$mensaje = mensaje_flash();
$estudios = conexion_bd()->query(
    "SELECT s.id, s.codigo, s.nombre, COUNT(sp.id) AS cantidad_parametros
     FROM estudios s
     LEFT JOIN parametros_estudios sp ON sp.estudio_id = s.id
     GROUP BY s.id, s.codigo, s.nombre
     ORDER BY s.nombre"
)->fetchAll();
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Parámetros | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main class="container"><div class="page-heading"><div><h1>Parámetros por estudio</h1><p class="muted">Selecciona un estudio para cargar todos los parámetros que forman su resultado.</p></div></div><?php if ($mensaje): ?><div class="alert success-alert"><?= escapar_html($mensaje) ?></div><?php endif; ?><div class="table-wrap"><table><thead><tr><th>Código</th><th>Estudio</th><th>Parámetros cargados</th><th>Acción</th></tr></thead><tbody><?php foreach ($estudios as $estudio): ?><tr><td><?= escapar_html($estudio['codigo']) ?></td><td><strong><?= escapar_html($estudio['nombre']) ?></strong></td><td><?= (int) $estudio['cantidad_parametros'] ?></td><td><a class="button-small" href="parametro_formulario.php?estudio_id=<?= (int) $estudio['id'] ?>">Administrar parámetros</a></td></tr><?php endforeach; ?><?php if (!$estudios): ?><tr><td colspan="4" class="muted">Primero debes crear un estudio.</td></tr><?php endif; ?></tbody></table></div></main></body></html>








