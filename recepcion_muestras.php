<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('muestras.gestionar');

$conexion = conexion_bd();

// Traer órdenes en estado Pendiente + contar estudios y errores PENDIENTE
$stmt = $conexion->query(
    "SELECT o.codigo AS orden_codigo, o.fecha_orden, o.medico, o.estado,
            p.codigo AS paciente_codigo, p.apellido, p.nombre, p.dni,
            COUNT(DISTINCT eo.estudio_codigo) AS cantidad_estudios,
            GROUP_CONCAT(DISTINCT e.estudio SEPARATOR ', ') AS estudios,
            (SELECT COUNT(*) FROM historial_errores he
                JOIN resultados r ON r.codigo = he.resultado_codigo
                WHERE r.orden_codigo = o.codigo AND he.error_muestra LIKE 'PENDIENTE:%') AS cantidad_pendientes
     FROM ordenes o
     JOIN pacientes p ON p.codigo = o.paciente_codigo
     LEFT JOIN estudios_orden eo ON eo.orden_codigo = o.codigo
     LEFT JOIN estudios e ON e.codigo = eo.estudio_codigo
     WHERE o.estado = 'Pendiente'
     GROUP BY o.codigo
     ORDER BY o.fecha_orden ASC, o.codigo ASC"
);
$ordenesPendientes = $stmt->fetchAll();

$mensaje = mensaje_flash();
$tituloPagina = 'Check-in de Muestras | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container">
<div class="page-heading">
    <div>
        <h1>Check-in de Muestras</h1>
        <p class="muted">Órdenes pendientes de validación de muestra. Una vez que todos los estudios estén validados, se habilita la carga de resultados.</p>
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
                <th>Médico</th>
                <th>Estudios</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ordenesPendientes as $o): ?>
                <?php
                $cantidadEstudios = (int) $o['cantidad_estudios'];
                $cantidadPendientes = (int) $o['cantidad_pendientes'];
                // Parcial: si algunos están pendientes pero no todos
                $esParcial = $cantidadPendientes > 0 && $cantidadPendientes < $cantidadEstudios;
                ?>
                <tr>
                    <td>
                        <strong><?= formatear_codigo_orden($o['orden_codigo']) ?></strong>
                    </td>
                    <td>
                        <?= escapar_html($o['apellido'] . ', ' . $o['nombre']) ?><br>
                        <small class="muted"><?= escapar_html($o['dni']) ?></small>
                    </td>
                    <td><?= escapar_html($o['fecha_orden']) ?></td>
                    <td><?= escapar_html($o['medico']) ?></td>
                    <td>
                        <?= $cantidadEstudios ?> estudio(s)<br>
                        <small class="muted"><?= escapar_html($o['estudios'] ?? '—') ?></small>
                    </td>
                    <td>
                        <span class="badge"><?= escapar_html($o['estado']) ?></span>
                        <?php if ($esParcial): ?>
                            <br><span class="badge warning">Parcial</span>
                            <br><small class="muted"><?= $cantidadPendientes ?> sin recibir</small>
                        <?php elseif ($cantidadPendientes > 0 && $cantidadPendientes === $cantidadEstudios): ?>
                            <br><span class="badge warning">Sin recibir muestra</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="button-small" href="checkin_muestra_formulario.php?orden_id=<?= (int) $o['orden_codigo'] ?>">
                            <?= $esParcial ? 'Completar check-in' : 'Hacer check-in' ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$ordenesPendientes): ?>
                <tr><td colspan="7" class="muted">No hay órdenes pendientes de check-in.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>