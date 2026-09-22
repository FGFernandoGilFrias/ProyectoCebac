<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('practicas.gestionar');
$prices = conexion_bd()->query('SELECT * FROM precios_practicas ORDER BY activo DESC, codigo_practica')->fetchAll();
$mensaje = mensaje_flash();
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Precios de prácticas | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main class="container"><div class="page-heading"><div><h1>Precios de prácticas</h1><p class="muted">Catálogo independiente de códigos, descripciones y valores.</p></div><a class="button-link" href="precio_practica_formulario.php">Nueva práctica</a></div><?php if ($mensaje): ?><div class="alert success-alert"><?= escapar_html($mensaje) ?></div><?php endif; ?><div class="table-wrap"><table><thead><tr><th>Código</th><th>Descripción</th><th>Monto</th><th>Estado</th><th></th></tr></thead><tbody><?php foreach ($prices as $precio): ?><tr><td><?= escapar_html($precio['codigo_practica']) ?></td><td><?= escapar_html($precio['descripcion']) ?></td><td>$ <?= number_format((float) $precio['precio'], 2, ',', '.') ?></td><td><?= $precio['activo'] ? 'Activo' : 'Inactivo' ?></td><td><a href="precio_practica_formulario.php?id=<?= (int) $precio['id'] ?>">Editar</a></td></tr><?php endforeach; ?><?php if (!$prices): ?><tr><td colspan="5" class="muted">No hay prácticas registradas.</td></tr><?php endif; ?></tbody></table></div></main></body></html>








