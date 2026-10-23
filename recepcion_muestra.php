<?php
require_once __DIR__ . '/includes/autenticacion.php';
exigir_privilegio('muestras.manage');

$mensaje = flash();
$pending = db()->query(
    "SELECT o.id AS orden_id, os.id AS orden_estudio_id, o.codigo AS codigo_orden, o.fecha_orden,
            p.dni, p.apellido, p.nombre, s.nombre AS estudio_name,
            s.codigo AS estudio_code, sa.id AS muestra_id, sa.codigo AS muestra_code,
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
    $ordenes[$ordenId]['orden'] = $item;
    $ordenes[$ordenId]['estudios'][] = $item;
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Check-in de muestras | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/barra_lateral.php'; ?><main class="container"><div class="page-heading"><div><h1>Check-in de muestras</h1><p class="muted">Selecciona una orden para ver y validar sus estudios.</p></div></div><?php if ($mensaje): ?><div class="alert success-alert"><?= e($mensaje) ?></div><?php endif; ?><div class="table-wrap orden-list"><table><thead><tr><th>Orden</th><th>Paciente</th><th>Fecha</th><th>Estudios</th><th>Estado</th><th></th></tr></thead><tbody><?php foreach ($ordenes as $group): $orden = $group['orden']; $allFinished = true; foreach ($group['estudios'] as $estudio) { if (($estudio['estado'] ?? 'Pendiente') !== 'Finalizada') { $allFinished = false; break; } } ?><tr class="orden-row" data-orden-toggle="<?= (int) $orden['orden_id'] ?>"><td><button type="button" class="orden-toggle" aria-expanded="false" aria-controls="orden-details-<?= (int) $orden['orden_id'] ?>"><span class="chevron">▸</span><?= e($orden['codigo_orden']) ?></button></td><td><?= e($orden['apellido'] . ', ' . $orden['nombre']) ?><br><small>DNI <?= e($orden['dni']) ?></small></td><td><?= e($orden['fecha_orden']) ?></td><td><?= count($group['estudios']) ?></td><td><span class="badge <?= $allFinished ? 'success' : '' ?>"><?= $allFinished ? 'Completada' : 'Pendiente' ?></span></td><td><button type="button" class="button-small orden-details-button" data-orden-toggle="<?= (int) $orden['orden_id'] ?>">Ver detalles</button></td></tr><tr id="orden-details-<?= (int) $orden['orden_id'] ?>" class="orden-details" hidden><td colspan="6"><div class="orden-details-box"><h2>Estudios de la orden <?= e($orden['codigo_orden']) ?></h2><div class="table-wrap"><table><thead><tr><th>Estudio</th><th>Muestra</th><th>Estado</th><th>Acción</th></tr></thead><tbody><?php foreach ($group['estudios'] as $item): ?><tr><td><?= e($item['estudio_name']) ?><br><small><?= e($item['estudio_code']) ?></small></td><td><?= e($item['muestra_code'] ?? 'Se generará al confirmar') ?></td><td><span class="badge <?= $item['estado'] === 'Finalizada' ? 'success' : '' ?>"><?= e($item['estado'] ?? 'Pendiente') ?></span><?php if ($item['motivo_rechazo']): ?><br><small><?= e($item['motivo_rechazo']) ?></small><?php endif; ?></td><td><a class="button-small" href="formulario_muestra.php?orden_estudio_id=<?= (int) $item['orden_estudio_id'] ?>"><?= $item['muestra_id'] ? 'Actualizar' : 'Validar muestra' ?></a></td></tr><?php endforeach; ?></tbody></table></div></div></td></tr><?php endforeach; ?><?php if (!$ordenes): ?><tr><td colspan="6" class="muted">No hay órdenes registradas.</td></tr><?php endif; ?></tbody></table></div></main><script>document.querySelectorAll('[data-orden-toggle]').forEach(function (button) { button.addEventListener('click', function (event) { event.stopPropagation(); const id = button.dataset.orderToggle; const details = document.getElementById('orden-details-' + id); const toggle = document.querySelector('.orden-toggle[aria-controls="orden-details-' + id + '"]'); const open = details.hidden; details.hidden = !open; toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); toggle.querySelector('.chevron').textContent = open ? '▾' : '▸'; }); });</script></body></html>



