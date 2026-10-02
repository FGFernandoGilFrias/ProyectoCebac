<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('resultados.gestionar');

$conexion = conexion_bd();

// Traer órdenes con info de resultado, cobertura y pagos
$consultaSql = "SELECT o.codigo AS orden_codigo, o.fecha_orden, o.medico, o.estado,
                       p.codigo AS paciente_codigo, p.apellido, p.nombre, p.dni,
                       os.nombre AS obra_social,
                       c.nro_credencial, c.cobertura, c.porcentaje_cobertura,
                       COUNT(DISTINCT eo.estudio_codigo) AS cantidad_estudios,
                       COALESCE(SUM(pp.precio), 0) AS total_orden,
                       r.codigo AS resultado_codigo, r.resultado, r.fecha_resultado
                FROM ordenes o
                JOIN pacientes p ON p.codigo = o.paciente_codigo
                LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
                LEFT JOIN obras_sociales_credenciales c ON c.codigo = pos.credencial_codigo
                LEFT JOIN obras_sociales os ON os.codigo = c.obra_social_codigo
                LEFT JOIN estudios_orden eo ON eo.orden_codigo = o.codigo
                LEFT JOIN estudios e ON e.codigo = eo.estudio_codigo
                LEFT JOIN precio_practica pp ON pp.codigo = e.practica_codigo
                LEFT JOIN resultados r ON r.orden_codigo = o.codigo
                GROUP BY o.codigo
                ORDER BY o.fecha_orden DESC, o.codigo DESC
                LIMIT 100";
$consulta = $conexion->query($consultaSql);
$ordenes = $consulta->fetchAll();

// Pagos por paciente
$pagosPorPaciente = [];
$stmt = $conexion->query('SELECT paciente_codigo, SUM(monto) AS total_pagado FROM caja GROUP BY paciente_codigo');
foreach ($stmt->fetchAll() as $row) {
    $pagosPorPaciente[(int) $row['paciente_codigo']] = (float) $row['total_pagado'];
}

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
                <th>Cobertura</th>
                <th>Estudios</th>
                <th>A cargo pac.</th>
                <th>Pagado</th>
                <th>Estado</th>
                <th>Resultado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ordenes as $o): ?>
                <?php
                $totalOrden = (float) $o['total_orden'];
                $porcentaje = (float) ($o['porcentaje_cobertura'] ?? 0);
                $aCargo = $totalOrden * (1 - $porcentaje / 100);
                $pagado = $pagosPorPaciente[(int) $o['paciente_codigo']] ?? 0;
                $tieneResultado = !empty($o['resultado_codigo']);
                ?>
                <tr>
                    <td><strong><?= formatear_codigo_orden($o['orden_codigo']) ?></strong></td>
                    <td>
                        <?= escapar_html($o['apellido'] . ', ' . $o['nombre']) ?><br>
                        <small class="muted"><?= escapar_html($o['dni']) ?></small>
                    </td>
                    <td><?= escapar_html($o['fecha_orden']) ?></td>
                    <td>
                        <?php if ($o['cobertura']): ?>
                            <span class="badge"><?= escapar_html($o['cobertura']) ?></span>
                        <?php else: ?>
                            <small class="muted">Particular</small>
                        <?php endif; ?>
                    </td>
                    <td><?= (int) $o['cantidad_estudios'] ?></td>
                    <td>$ <?= number_format($aCargo, 2, ',', '.') ?></td>
                    <td>$ <?= number_format($pagado, 2, ',', '.') ?></td>
                    <td><span class="badge"><?= escapar_html($o['estado']) ?></span></td>
                    <td>
                        <?php if ($tieneResultado): ?>
                            <span class="badge success">Cargado</span>
                            <?php if ($o['fecha_resultado']): ?>
                                <br><small class="muted"><?= escapar_html($o['fecha_resultado']) ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <small class="muted">Sin cargar</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="button-small" href="resultado_formulario.php?orden_id=<?= (int) $o['orden_codigo'] ?>">
                            <?= $tieneResultado ? 'Ver / Editar' : 'Cargar' ?>
                        </a>
                        <?php if ($tieneResultado): ?>
                            <br>
                            <a class="button-small" href="resultado_pdf.php?orden_id=<?= (int) $o['orden_codigo'] ?>" target="_blank">📄 PDF</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$ordenes): ?>
                <tr><td colspan="10" class="muted">No hay órdenes registradas.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>