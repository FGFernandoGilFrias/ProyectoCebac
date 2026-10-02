<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('ordenes.gestionar');

$q = trim((string) ($_GET['q'] ?? ''));

$consultaSql = "SELECT o.codigo, o.fecha_orden, o.medico, o.estado,
                       p.codigo AS paciente_codigo, p.apellido, p.nombre, p.dni,
                       os.nombre AS obra_social,
                       c.nro_credencial, c.cobertura, c.porcentaje_cobertura,
                       COUNT(eo.estudio_codigo) AS cantidad_estudios,
                       COALESCE(SUM(pp.precio), 0) AS total_orden
                FROM ordenes o
                JOIN pacientes p ON p.codigo = o.paciente_codigo
                LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
                LEFT JOIN obras_sociales_credenciales c ON c.codigo = pos.credencial_codigo
                LEFT JOIN obras_sociales os ON os.codigo = c.obra_social_codigo
                LEFT JOIN estudios_orden eo ON eo.orden_codigo = o.codigo
                LEFT JOIN estudios e ON e.codigo = eo.estudio_codigo
                LEFT JOIN precio_practica pp ON pp.codigo = e.practica_codigo";

$params = [];
if ($q !== '') {
    $consultaSql .= ' WHERE CAST(o.codigo AS CHAR) LIKE :q OR p.dni LIKE :q OR p.apellido LIKE :q OR p.nombre LIKE :q';
    $params['q'] = '%' . $q . '%';
}

$consultaSql .= ' GROUP BY o.codigo ORDER BY o.fecha_orden ASC, o.codigo ASC';

$consulta = conexion_bd()->prepare($consultaSql);
$consulta->execute($params);
$ordenesRaw = $consulta->fetchAll();

// Agrupar órdenes por paciente
$ordenesPorPaciente = [];
foreach ($ordenesRaw as $o) {
    $pc = (int) $o['paciente_codigo'];
    if (!isset($ordenesPorPaciente[$pc])) $ordenesPorPaciente[$pc] = [];
    $ordenesPorPaciente[$pc][] = $o;
}

// Total pagado por paciente
$pagosPorPaciente = [];
$stmt = conexion_bd()->query('SELECT paciente_codigo, SUM(monto) AS total_pagado FROM caja GROUP BY paciente_codigo');
foreach ($stmt->fetchAll() as $row) {
    $pagosPorPaciente[(int) $row['paciente_codigo']] = (float) $row['total_pagado'];
}

// Imputar pagos a órdenes cronológicamente
$saldosPorOrden = [];
$estadoPagoPorOrden = [];

foreach ($ordenesPorPaciente as $pacienteCodigo => $ordenes) {
    $pagoDisponible = $pagosPorPaciente[$pacienteCodigo] ?? 0;

    foreach ($ordenes as $o) {
        $totalOrden = (float) $o['total_orden'];
        $porcentaje = (float) ($o['porcentaje_cobertura'] ?? 0);
        $montoPaciente = $totalOrden * (1 - $porcentaje / 100);

        if ($pagoDisponible <= 0) {
            $saldosPorOrden[(int) $o['codigo']] = $montoPaciente;
            $estadoPagoPorOrden[(int) $o['codigo']] = 'Pendiente';
            continue;
        }

        if ($pagoDisponible >= $montoPaciente) {
            $pagoDisponible -= $montoPaciente;
            $saldosPorOrden[(int) $o['codigo']] = 0;
            $estadoPagoPorOrden[(int) $o['codigo']] = 'Pagado';
        } else {
            $saldosPorOrden[(int) $o['codigo']] = $montoPaciente - $pagoDisponible;
            $pagoDisponible = 0;
            $estadoPagoPorOrden[(int) $o['codigo']] = 'Parcial';
        }
    }
}

$ordenes = array_reverse($ordenesRaw);

$mensaje = mensaje_flash();
$tituloPagina = 'Órdenes | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container">
<div class="page-heading">
    <div>
        <h1>Órdenes médicas</h1>
        <p class="muted">Registro de órdenes y estudios solicitados.</p>
    </div>
    <a class="button-link" href="orden_formulario.php">Nueva orden</a>
</div>
<?php if ($mensaje): ?><div class="alert success-alert"><?= escapar_html($mensaje) ?></div><?php endif; ?>

<form class="search-form" method="get">
    <input name="q" value="<?= escapar_html($q) ?>" placeholder="Buscar por orden, DNI o paciente">
    <button type="submit">Buscar</button>
</form>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Orden</th>
                <th>Paciente</th>
                <th>Obra social</th>
                <th>Cobertura</th>
                <th>Fecha</th>
                <th>Médico</th>
                <th>Total</th>
                <th>Paciente paga</th>
                <th>Saldo orden</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ordenes as $o): ?>
                <?php
                $ordenCodigo = (int) $o['codigo'];
                $totalOrden = (float) $o['total_orden'];
                $porcentaje = (float) ($o['porcentaje_cobertura'] ?? 0);
                $montoPaciente = $totalOrden * (1 - $porcentaje / 100);
                $saldoOrden = $saldosPorOrden[$ordenCodigo] ?? $montoPaciente;
                $estadoPago = $estadoPagoPorOrden[$ordenCodigo] ?? 'Pendiente';
                ?>
                <tr>
                    <td><a href="orden_detalle.php?id=<?= $ordenCodigo ?>"><strong><?= formatear_codigo_orden($o['codigo']) ?></strong></a></td>
                    <td>
                        <?= escapar_html($o['apellido'] . ', ' . $o['nombre']) ?><br>
                        <small class="muted"><?= escapar_html($o['dni']) ?></small>
                    </td>
                    <td><?= escapar_html($o['obra_social'] ?? 'Particular') ?></td>
                    <td>
                        <?php if ($o['cobertura']): ?>
                            <span class="badge"><?= escapar_html($o['cobertura']) ?></span><br>
                            <small class="muted"><?= number_format($porcentaje, 0, ',', '.') ?>%</small>
                        <?php else: ?>
                            <small class="muted">—</small>
                        <?php endif; ?>
                    </td>
                    <td><?= escapar_html($o['fecha_orden']) ?></td>
                    <td><?= escapar_html($o['medico']) ?></td>
                    <td>$ <?= number_format($totalOrden, 2, ',', '.') ?></td>
                    <td>$ <?= number_format($montoPaciente, 2, ',', '.') ?></td>
                    <td>
                        <?php if ($estadoPago === 'Pagado'): ?>
                            <span style="color:#166534; font-weight:700;">Pagado</span>
                        <?php elseif ($estadoPago === 'Parcial'): ?>
                            <span style="color:#92400e; font-weight:700;">$ <?= number_format($saldoOrden, 2, ',', '.') ?></span>
                            <br><small class="muted">Parcial</small>
                        <?php else: ?>
                            <span style="color:#991b1b; font-weight:700;">$ <?= number_format($saldoOrden, 2, ',', '.') ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge"><?= escapar_html($o['estado']) ?></span></td>
                    <td>
                        <a class="button-small" href="orden_detalle.php?id=<?= $ordenCodigo ?>">Ver</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$ordenes): ?>
                <tr><td colspan="11" class="muted">No hay órdenes registradas.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>