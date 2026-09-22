<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('muestras.gestionar');

$mensaje = mensaje_flash();
$pending = conexion_bd()->query(
    "SELECT o.id AS orden_id, os.id AS orden_estudio_id, o.codigo AS codigo_orden, o.fecha_orden,
            p.dni, p.apellido, p.nombre, s.nombre AS nombre_estudio,
            s.codigo AS codigo_estudio, sa.id AS muestra_id, sa.codigo AS codigo_muestra,
            sa.recolectado_en, sa.estado, sa.motivo_rechazo
     FROM ordenes_estudios os
     JOIN ordenes o ON o.id = os.orden_id
     JOIN pacientes p ON p.id = o.paciente_id
     JOIN estudios s ON s.id = os.estudio_id
     LEFT JOIN muestras sa ON sa.orden_estudio_id = os.id
     ORDER BY o.fecha_orden DESC, o.id DESC, os.id ASC"
)->fetchAll();
$ordenes = [];
foreach ($pending as $item) {
    $ordenId = (int) $item['orden_id'];
    $ordenes[$ordenId]['order'] = $item;
    $ordenes[$ordenId]['estudios'][] = $item;
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Check-in de muestras | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main class="container"><div class="page-heading"><div><h1>Check-in de muestras</h1><p class="muted">Selecciona una orden para ver y validar sus estudios.</p></div></div><?php if ($mensaje): ?><div class="alert success-alert"><?= escapar_html($mensaje) ?></div><?php endif; ?><div class="table-wrap order-list"><table><thead><tr><th>Orden</th><th>Paciente</th><th>Fecha</th><th>Estudios</th><th>Estado</th><th></th></tr></thead><tbody><?php foreach ($ordenes as $group): $orden = $group['order']; $allFinished = true; foreach ($group['estudios'] as $estudio) { if (($estudio['estado'] ?? 'Pendiente') !== 'Finalizada') { $allFinished = false; break; } } ?><tr class="order-row" data-order-toggle="<?= (int) $orden['orden_id'] ?>"><td><button type="button" class="order-toggle" aria-expanded="false" aria-controls="order-details-<?= (int) $orden['orden_id'] ?>"><span class="chevron">▸</span><?= escapar_html($orden['codigo_orden']) ?></button></td><td><?= escapar_html($orden['apellido'] . ', ' . $orden['nombre']) ?><br><small>DNI <?= escapar_html($orden['dni']) ?></small></td><td><?= escapar_html($orden['fecha_orden']) ?></td><td><?= count($group['estudios']) ?></td><td><span class="badge <?= $allFinished ? 'success' : '' ?>"><?= $allFinished ? 'Completada' : 'Pendiente' ?></span></td><td><button type="button" class="button-small order-details-button" data-order-toggle="<?= (int) $orden['orden_id'] ?>">Ver detalles</button></td></tr><tr id="order-details-<?= (int) $orden['orden_id'] ?>" class="order-details" hidden><td colspan="6"><div class="order-details-box"><h2>Estudios de la orden <?= escapar_html($orden['codigo_orden']) ?></h2><div class="table-wrap"><table><thead><tr><th>Estudio</th><th>Muestra</th><th>Estado</th><th>Acción</th></tr></thead><tbody><?php foreach ($group['estudios'] as $item): ?><tr><td><?= escapar_html($item['nombre_estudio']) ?><br><small><?= escapar_html($item['codigo_estudio']) ?></small></td><td><?= escapar_html($item['codigo_muestra'] ?? 'Se generará al confirmar') ?></td><td><span class="badge <?= $item['estado'] === 'Finalizada' ? 'success' : '' ?>"><?= escapar_html($item['estado'] ?? 'Pendiente') ?></span><?php if ($item['motivo_rechazo']): ?><br><small><?= escapar_html($item['motivo_rechazo']) ?></small><?php endif; ?></td><td><a class="button-small" href="muestra_formulario.php?orden_estudio_id=<?= (int) $item['orden_estudio_id'] ?>"><?= $item['muestra_id'] ? 'Actualizar' : 'Validar muestra' ?></a></td></tr><?php endforeach; ?></tbody></table></div></div></td></tr><?php endforeach; ?><?php if (!$ordenes): ?><tr><td colspan="6" class="muted">No hay órdenes registradas.</td></tr><?php endif; ?></tbody></table></div></main><script>document.querySelectorAll('[data-order-toggle]').forEach(function (button) { button.addEventListener('click', function (event) { event.stopPropagation(); const id = button.dataset.orderToggle; const details = document.getElementById('order-details-' + id); const toggle = document.querySelector('.order-toggle[aria-controls="order-details-' + id + '"]'); const open = details.hidden; details.hidden = !open; toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); toggle.querySelector('.chevron').textContent = open ? '▾' : '▸'; }); });</script></body></html>








