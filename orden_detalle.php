<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('ordenes.gestionar');

$id = (int) ($_GET['id'] ?? 0);
$conexion = conexion_bd();
$consultaOrdenes = $conexion->prepare(
    "SELECT o.*, p.codigo AS codigo_paciente, p.dni, p.apellido, p.nombre, p.fecha_nacimiento,
            p.telefono, p.correo, sw.nombre AS nombre_obra_social, sw.identificador_fiscal
     FROM ordenes o
     JOIN pacientes p ON p.id = o.paciente_id
     LEFT JOIN obras_sociales sw ON sw.id = o.obra_social_id
     WHERE o.id = :id"
);
$consultaOrdenes->execute(['id' => $id]);
$orden = $consultaOrdenes->fetch();
if (!$orden) {
    http_response_code(404);
    exit('Orden no encontrada.');
}
$consultaEstudios = $conexion->prepare(
    "SELECT os.*, s.codigo AS codigo_estudio, s.nombre AS nombre_estudio,
            m.codigo AS codigo_muestra, m.estado AS estado_muestra,
            r.estado AS estado_resultado
     FROM ordenes_estudios os
     JOIN estudios s ON s.id = os.estudio_id
     LEFT JOIN muestras m ON m.orden_estudio_id = os.id
     LEFT JOIN resultados r ON r.orden_estudio_id = os.id
     WHERE os.orden_id = :orden_id
     ORDER BY os.id"
);
$consultaEstudios->execute(['orden_id' => $id]);
$estudios = $consultaEstudios->fetchAll();
$balance = max(0, (float) $orden['total_adeudado'] - (float) $orden['monto_pagado']);
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Detalle <?= escapar_html($orden['codigo']) ?> | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main class="container"><p><a href="ordenes.php">← Volver a órdenes</a></p><div class="page-heading"><div><h1>Detalle de orden <?= escapar_html($orden['codigo']) ?></h1><p class="muted">Información completa de la orden médica y su proceso.</p></div><div><a class="button-link" href="orden_formulario.php?id=<?= (int) $orden['id'] ?>">Editar orden</a> <span class="badge"><?= escapar_html($orden['estado']) ?></span></div></div><section class="detail-grid"><div class="module"><h2>Paciente</h2><p><strong><?= escapar_html($orden['apellido'] . ', ' . $orden['nombre']) ?></strong></p><p>Código: <?= escapar_html($orden['codigo_paciente']) ?> · DNI: <?= escapar_html($orden['dni']) ?></p><p>Fecha de nacimiento: <?= escapar_html($orden['fecha_nacimiento'] ?? 'No informada') ?></p><p>Teléfono: <?= escapar_html($orden['telefono'] ?? 'No informado') ?></p></div><div class="module"><h2>Orden</h2><p>Fecha: <?= escapar_html($orden['fecha_orden']) ?></p><p>Médico: <?= escapar_html($orden['medico']) ?></p><p>Obra social: <?= escapar_html($orden['nombre_obra_social'] ?? 'Particular') ?></p><p>Estado de pago: <span class="badge"><?= escapar_html($orden['estado_pago']) ?></span></p><p>Total: $ <?= number_format((float) $orden['total_adeudado'], 2, ',', '.') ?> · Abonado: $ <?= number_format((float) $orden['monto_pagado'], 2, ',', '.') ?> · Saldo: $ <?= number_format($balance, 2, ',', '.') ?></p></div></section><h2>Estudios solicitados</h2><div class="table-wrap"><table><thead><tr><th>Código</th><th>Estudio</th><th>Precio</th><th>Pago</th><th>Muestra</th><th>Resultado</th></tr></thead><tbody><?php foreach ($estudios as $estudio): ?><tr><td><?= escapar_html($estudio['codigo_estudio']) ?></td><td><?= escapar_html($estudio['nombre_estudio']) ?></td><td>$ <?= number_format((float) $estudio['precio'], 2, ',', '.') ?></td><td><?= escapar_html($estudio['estado_pago']) ?></td><td><?= escapar_html($estudio['estado_muestra'] ?? 'Pendiente') ?><?php if ($estudio['codigo_muestra']): ?><br><small><?= escapar_html($estudio['codigo_muestra']) ?></small><?php endif; ?></td><td><?= escapar_html($estudio['estado_resultado'] ?? 'Pendiente') ?></td></tr><?php endforeach; ?><?php if (!$estudios): ?><tr><td colspan="6" class="muted">La orden no tiene estudios asociados.</td></tr><?php endif; ?></tbody></table></div></main></body></html>







