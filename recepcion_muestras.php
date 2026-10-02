<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('muestras.gestionar');

$conexion = conexion_bd();

$stmt = $conexion->query(
    "SELECT os.id AS orden_estudio_id,
            o.id AS orden_id,
            o.codigo AS orden_codigo,
            o.fecha_orden,
            o.estado AS estado_orden,
            o.medico,
            p.apellido,
            p.nombre,
            p.dni,
            e.nombre AS estudio,
            sa.codigo AS codigo_muestra,
            sa.recolectado_en,
            sa.estado AS estado_muestra,
            sa.motivo_rechazo,
            r.estado AS estado_resultado
     FROM ordenes_estudios os
     JOIN ordenes o ON o.id = os.orden_id
     JOIN pacientes p ON p.id = o.paciente_id
     JOIN estudios e ON e.id = os.estudio_id
     LEFT JOIN muestras sa ON sa.orden_estudio_id = os.id
     LEFT JOIN resultados r ON r.orden_estudio_id = os.id
     ORDER BY o.fecha_orden DESC, o.id DESC, e.nombre ASC"
);
$items = $stmt->fetchAll();

$mensaje = mensaje_flash();
$tituloPagina = 'Check-in de Muestras | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container">
<div class="page-heading">
    <div>
        <h1>Check-in de Muestras</h1>
        <p class="muted">Listado por estudio para validar muestras pendientes y reingresar muestras rechazadas.</p>
    </div>
</div>
<?php if ($mensaje): ?><div class="alert success-alert"><?= escapar_html($mensaje) ?></div><?php endif; ?>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Orden</th>
                <th>Paciente</th>
                <th>Estudio</th>
                <th>Muestra</th>
                <th>Estado muestra</th>
                <th>Resultado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <?php
                $estadoMuestra = $item['estado_muestra'] ?? 'Pendiente';
                $requiereAccion = in_array($estadoMuestra, ['Pendiente', 'Rechazada'], true);
                ?>
                <tr>
                    <td>
                        <strong><?= formatear_codigo_orden($item['orden_codigo']) ?></strong><br>
                        <small class="muted"><?= escapar_html($item['fecha_orden']) ?> · <?= escapar_html($item['estado_orden']) ?></small>
                    </td>
                    <td>
                        <?= escapar_html($item['apellido'] . ', ' . $item['nombre']) ?><br>
                        <small class="muted"><?= escapar_html($item['dni']) ?></small>
                    </td>
                    <td>
                        <strong><?= escapar_html($item['estudio']) ?></strong><br>
                        <small class="muted">Médico: <?= escapar_html($item['medico']) ?></small>
                    </td>
                    <td>
                        <?php if (!empty($item['codigo_muestra'])): ?>
                            <?= escapar_html($item['codigo_muestra']) ?><br>
                            <small class="muted">Recolectada: <?= escapar_html($item['recolectado_en'] ?? '—') ?></small>
                        <?php else: ?>
                            <span class="muted">Sin muestra recibida</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge"><?= escapar_html($estadoMuestra) ?></span>
                        <?php if ($estadoMuestra === 'Pendiente'): ?>
                            <br><small class="muted">Pendiente de validación</small>
                        <?php elseif ($estadoMuestra === 'Rechazada'): ?>
                            <br><span class="badge warning">Requiere nueva muestra</span>
                            <?php if (!empty($item['motivo_rechazo'])): ?>
                                <br><small class="muted">Motivo: <?= escapar_html($item['motivo_rechazo']) ?></small>
                            <?php endif; ?>
                        <?php elseif ($estadoMuestra === 'Validada'): ?>
                            <br><small class="muted">Habilitada para resultados</small>
                        <?php elseif ($estadoMuestra === 'Finalizada'): ?>
                            <br><small class="muted">Muestra cerrada</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge"><?= escapar_html($item['estado_resultado'] ?? 'Pendiente') ?></span>
                    </td>
                    <td>
                        <?php if ($requiereAccion): ?>
                            <a class="button-small" href="muestra_formulario.php?orden_estudio_id=<?= (int) $item['orden_estudio_id'] ?>">
                                <?= $estadoMuestra === 'Rechazada' ? 'Recibir nueva muestra' : 'Validar muestra' ?>
                            </a>
                        <?php else: ?>
                            <a class="button-small" href="muestra_formulario.php?orden_estudio_id=<?= (int) $item['orden_estudio_id'] ?>">
                                Ver check-in
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?>
                <tr><td colspan="7" class="muted">No hay estudios para check-in.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
