<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('resultados.gestionar');

$mensaje = mensaje_flash();
$rows = conexion_bd()->query(
    "SELECT o.id AS orden_id, o.codigo AS codigo_orden, o.fecha_orden, o.estado_pago,
            o.total_adeudado, o.monto_pagado, p.dni, p.apellido, p.nombre,
            os.id AS orden_estudio_id, s.codigo AS codigo_estudio, s.nombre AS nombre_estudio,
            sa.estado AS estado_muestra, r.texto_resultado, r.estado AS estado_resultado
     FROM ordenes o
     JOIN pacientes p ON p.id = o.paciente_id
     JOIN ordenes_estudios os ON os.orden_id = o.id
     JOIN estudios s ON s.id = os.estudio_id
     LEFT JOIN muestras sa ON sa.orden_estudio_id = os.id
     LEFT JOIN resultados r ON r.orden_estudio_id = os.id
     ORDER BY o.fecha_orden DESC, o.id DESC, os.id ASC"
)->fetchAll();
$ordenes = [];
foreach ($rows as $row) {
    $id = (int) $row['orden_id'];
    $ordenes[$id]['order'] = $row;
    $ordenes[$id]['estudios'][] = $row;
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Resultados | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main class="container"><div class="page-heading"><div><h1>Resultados</h1><p class="muted">Carga y entrega de resultados por orden médica.</p></div></div><?php if ($mensaje): ?><div class="alert success-alert"><?= escapar_html($mensaje) ?></div><?php endif; ?><div class="table-wrap"><table><thead><tr><th>Orden</th><th>Paciente</th><th>Fecha</th><th>Pago</th><th>Entrega</th></tr></thead><tbody><?php foreach ($ordenes as $group): $orden = $group['order']; $paid = $orden['total_adeudado'] <= 0 || $orden['monto_pagado'] >= $orden['total_adeudado']; $ready = $paid; foreach ($group['estudios'] as $estudio) { if (($estudio['estado_muestra'] ?? '') !== 'Finalizada' || ($estudio['estado_resultado'] ?? '') !== 'Cargado') { $ready = false; } } ?><tr><td><strong><?= escapar_html($orden['codigo_orden']) ?></strong></td><td><?= escapar_html($orden['apellido'] . ', ' . $orden['nombre']) ?><br><small>DNI <?= escapar_html($orden['dni']) ?></small></td><td><?= escapar_html($orden['fecha_orden']) ?></td><td><span class="badge <?= $paid ? 'success' : '' ?>"><?= $paid ? 'Pagado' : 'Saldo pendiente' ?></span></td><td><a class="button-small" href="resultado_formulario.php?orden_id=<?= (int) $orden['orden_id'] ?>">Ver estudios</a><?php if (!$paid): ?><br><small class="muted">No entregable hasta cancelar</small><?php elseif (!$ready): ?><br><small class="muted">Pendiente de completar</small><?php else: ?><br><span class="badge success">Listo para entregar</span><?php endif; ?></td></tr><?php endforeach; ?><?php if (!$ordenes): ?><tr><td colspan="5" class="muted">No hay órdenes registradas.</td></tr><?php endif; ?></tbody></table></div></main></body></html>








