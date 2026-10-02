<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('resultados.gestionar');

$conexion = conexion_bd();

$consultaSql = "SELECT o.id AS orden_id,
                       o.codigo AS orden_codigo,
                       o.fecha_orden,
                       o.estado,
                       p.apellido,
                       p.nombre,
                       p.dni,
                       COUNT(oe.id) AS cantidad_estudios,
                       COALESCE(SUM(oe.precio), 0) AS total_orden,
                       o.total_adeudado,
                       o.monto_pagado,
                       SUM(CASE WHEN COALESCE(m.estado, 'Pendiente') IN ('Pendiente', 'Rechazada') THEN 1 ELSE 0 END) AS estudios_bloqueados,
                       SUM(CASE WHEN COALESCE(m.estado, 'Pendiente') = 'Rechazada' THEN 1 ELSE 0 END) AS estudios_rechazados,
                       SUM(CASE WHEN r.estado IN ('Cargado', 'Entregado') THEN 1 ELSE 0 END) AS resultados_cargados,
                       SUM(CASE WHEN r.estado = 'Entregado' THEN 1 ELSE 0 END) AS resultados_entregados
                FROM ordenes o
                JOIN pacientes p ON p.id = o.paciente_id
                LEFT JOIN ordenes_estudios oe ON oe.orden_id = o.id
                LEFT JOIN muestras m ON m.orden_estudio_id = oe.id
                LEFT JOIN resultados r ON r.orden_estudio_id = oe.id
                GROUP BY o.id
                ORDER BY o.fecha_orden DESC, o.id DESC
                LIMIT 100";
$consulta = $conexion->query($consultaSql);
$ordenes = $consulta->fetchAll();

$mensaje = mensaje_flash();
$tituloPagina = 'Resultados | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container">
<div class="page-heading">
    <div>
        <h1>Resultados</h1>
        <p class="muted">Carga y gestión de resultados por orden médica.</p>
    </div>
</div>
<?php if ($mensaje): ?><div class="alert success-alert"><?= escapar_html($mensaje) ?></div><?php endif; ?>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Orden</th>
                <th>Paciente</th>
                <th>Fecha</th>
                <th>Estudios</th>
                <th>A cargo pac.</th>
                <th>Pagado</th>
                <th>Muestras</th>
                <th>Resultados</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ordenes as $o): ?>
                <?php
                $bloqueados = (int) $o['estudios_bloqueados'];
                $rechazados = (int) $o['estudios_rechazados'];
                $cargados = (int) $o['resultados_cargados'];
                $entregados = (int) $o['resultados_entregados'];
                $cantidadEstudios = (int) $o['cantidad_estudios'];
                ?>
                <tr>
                    <td>
                        <strong><?= formatear_codigo_orden($o['orden_codigo']) ?></strong><br>
                        <small class="muted"><?= escapar_html($o['estado']) ?></small>
                    </td>
                    <td>
                        <?= escapar_html($o['apellido'] . ', ' . $o['nombre']) ?><br>
                        <small class="muted"><?= escapar_html($o['dni']) ?></small>
                    </td>
                    <td><?= escapar_html($o['fecha_orden']) ?></td>
                    <td><?= $cantidadEstudios ?></td>
                    <td>$ <?= number_format((float) $o['total_adeudado'], 2, ',', '.') ?></td>
                    <td>$ <?= number_format((float) $o['monto_pagado'], 2, ',', '.') ?></td>
                    <td>
                        <?php if ($bloqueados > 0): ?>
                            <span class="badge warning"><?= $bloqueados ?> bloqueada(s)</span>
                            <?php if ($rechazados > 0): ?>
                                <br><small class="muted"><?= $rechazados ?> rechazada(s)</small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge success">Habilitadas</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($cargados > 0): ?>
                            <span class="badge success"><?= $cargados ?> cargado(s)</span>
                            <?php if ($entregados > 0): ?>
                                <br><small class="muted"><?= $entregados ?> entregado(s)</small>
                            <?php endif; ?>
                        <?php else: ?>
                            <small class="muted">Sin cargar</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="button-small" href="resultado_formulario.php?orden_id=<?= (int) $o['orden_id'] ?>">
                            <?= $bloqueados > 0 ? 'Ver bloqueo / Cargar parcial' : 'Cargar / Editar' ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$ordenes): ?>
                <tr><td colspan="9" class="muted">No hay órdenes registradas.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
