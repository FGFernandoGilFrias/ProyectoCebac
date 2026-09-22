<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('caja.gestionar');
$items = conexion_bd()->query(
    "SELECT o.id, o.codigo AS codigo_orden, o.estado_pago, o.total_adeudado, o.monto_pagado,
            p.apellido, p.nombre
     FROM ordenes o
     JOIN pacientes p ON p.id = o.paciente_id
     WHERE o.estado_pago IN ('Pendiente','Parcial','Pagar al retirar')
     ORDER BY o.fecha_orden DESC, o.id DESC"
)->fetchAll();
$mensaje = mensaje_flash();
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Caja | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main class="container"><div class="page-heading"><div><h1>Caja</h1><p class="muted">Cobro acumulado de estudios particulares por orden.</p></div></div><?php if ($mensaje): ?><div class="alert success-alert"><?= escapar_html($mensaje) ?></div><?php endif; ?><div class="table-wrap"><table><thead><tr><th>Orden</th><th>Paciente</th><th>Total</th><th>Abonado</th><th>Saldo</th><th>Estado</th><th>Acción</th></tr></thead><tbody><?php foreach ($items as $item): ?><tr><td><?= escapar_html($item['codigo_orden']) ?></td><td><?= escapar_html($item['apellido'] . ', ' . $item['nombre']) ?></td><td>$ <?= number_format((float) $item['total_adeudado'], 2, ',', '.') ?></td><td>$ <?= number_format((float) $item['monto_pagado'], 2, ',', '.') ?></td><td>$ <?= number_format(max(0, (float) $item['total_adeudado'] - (float) $item['monto_pagado']), 2, ',', '.') ?></td><td><?= escapar_html($item['estado_pago']) ?></td><td><a class="button-small" href="caja_formulario.php?orden_id=<?= (int) $item['id'] ?>">Registrar pago</a></td></tr><?php endforeach; ?><?php if (!$items): ?><tr><td colspan="7" class="muted">No hay órdenes con saldo pendiente.</td></tr><?php endif; ?></tbody></table></div></main></body></html>








