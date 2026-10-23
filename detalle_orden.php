<?php
require_once __DIR__ . '/includes/autenticacion.php';
exigir_privilegio('ordenes.manage');

$id = (int) ($_GET['id'] ?? 0);
$pdo = db();
$consultaOrden = $pdo->prepare(
    "SELECT o.*, p.codigo AS codigo_paciente, p.dni, p.apellido, p.nombre, p.fecha_nacimiento,
            p.telefono, p.correo, sw.nombre AS obra_social_nombre, sw.identificacion_fiscal
     FROM ordenes o
     JOIN pacientes p ON p.id = o.paciente_id
     LEFT JOIN obras_sociales sw ON sw.id = o.obra_social_id
     WHERE o.id = :id"
);
$consultaOrden->execute(['id' => $id]);
$orden = $consultaOrden->fetch();
if (!$orden) {
    http_response_code(404);
    exit('Orden no encontrada.');
}
$consultaEstudio = $pdo->prepare(
    "SELECT os.*, s.codigo AS estudio_code, s.nombre AS estudio_name,
            m.codigo AS muestra_code, m.estado AS muestra_status,
            r.estado AS resultado_status
     FROM ordenes_estudios os
     JOIN estudios s ON s.id = os.estudio_id
     LEFT JOIN muestras m ON m.orden_estudio_id = os.id
     LEFT JOIN resultados r ON r.orden_estudio_id = os.id
     WHERE os.orden_id = :orden_id
     ORDER BY os.id"
);
$consultaEstudio->execute(['orden_id' => $id]);
$estudios = $consultaEstudio->fetchAll();
$balance = max(0, (float) $orden['total_adeudado'] - (float) $orden['monto_pagado']);
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Detalle <?= e($orden['codigo']) ?> | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/barra_lateral.php'; ?><main class="container"><p><a href="ordenes.php">← Volver a órdenes</a></p><div class="page-heading"><div><h1>Detalle de orden <?= e($orden['codigo']) ?></h1><p class="muted">Información completa de la orden médica y su proceso.</p></div><span class="badge"><?= e($orden['estado']) ?></span></div><section class="detail-grid"><div class="module"><h2>Paciente</h2><p><strong><?= e($orden['apellido'] . ', ' . $orden['nombre']) ?></strong></p><p>Código: <?= e($orden['codigo_paciente']) ?> · DNI: <?= e($orden['dni']) ?></p><p>Fecha de nacimiento: <?= e($orden['fecha_nacimiento'] ?? 'No informada') ?></p><p>Teléfono: <?= e($orden['telefono'] ?? 'No informado') ?></p></div><div class="module"><h2>Orden</h2><p>Fecha: <?= e($orden['fecha_orden']) ?></p><p>Médico: <?= e($orden['medico']) ?></p><p>Obra social: <?= e($orden['obra_social_nombre'] ?? 'Particular') ?></p><p>Estado de pago: <span class="badge"><?= e($orden['estado_pago']) ?></span></p><p>Total: $ <?= number_format((float) $orden['total_adeudado'], 2, ',', '.') ?> · Abonado: $ <?= number_format((float) $orden['monto_pagado'], 2, ',', '.') ?> · Saldo: $ <?= number_format($balance, 2, ',', '.') ?></p></div></section><h2>Estudios solicitados</h2><div class="table-wrap"><table><thead><tr><th>Código</th><th>Estudio</th><th>Precio</th><th>Pago</th><th>Muestra</th><th>Resultado</th></tr></thead><tbody><?php foreach ($estudios as $estudio): ?><tr><td><?= e($estudio['estudio_code']) ?></td><td><?= e($estudio['estudio_name']) ?></td><td>$ <?= number_format((float) $estudio['precio'], 2, ',', '.') ?></td><td><?= e($estudio['estado_pago']) ?></td><td><?= e($estudio['muestra_status'] ?? 'Pendiente') ?><?php if ($estudio['muestra_code']): ?><br><small><?= e($estudio['muestra_code']) ?></small><?php endif; ?></td><td><?= e($estudio['resultado_status'] ?? 'Pendiente') ?></td></tr><?php endforeach; ?><?php if (!$estudios): ?><tr><td colspan="6" class="muted">La orden no tiene estudios asociados.</td></tr><?php endif; ?></tbody></table></div></main></body></html>



