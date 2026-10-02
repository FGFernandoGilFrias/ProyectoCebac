<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('caja.gestionar');

$conexion = conexion_bd();

// ============================================================
// Calcular el total a cargo de cada paciente (con cobertura)
// ============================================================

// 1. Traer todos los pacientes con su credencial y cobertura
$stmtPacientes = $conexion->query(
    "SELECT p.codigo AS paciente_codigo, p.apellido, p.nombre, p.dni,
            os.nombre AS obra_social,
            c.nro_credencial, c.cobertura, c.porcentaje_cobertura
     FROM pacientes p
     LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
     LEFT JOIN obras_sociales_credenciales c ON c.codigo = pos.credencial_codigo
     LEFT JOIN obras_sociales os ON os.codigo = c.obra_social_codigo
     ORDER BY p.apellido, p.nombre"
);
$pacientes = $stmtPacientes->fetchAll();

// 2. Calcular el total a cargo de cada paciente (suma de órdenes con cobertura)
$stmtOrdenes = $conexion->query(
    "SELECT o.codigo, o.paciente_codigo,
            COALESCE(SUM(pp.precio), 0) AS total_orden
     FROM ordenes o
     LEFT JOIN estudios_orden eo ON eo.orden_codigo = o.codigo
     LEFT JOIN estudios e ON e.codigo = eo.estudio_codigo
     LEFT JOIN precio_practica pp ON pp.codigo = e.practica_codigo
     GROUP BY o.codigo"
);

$totalPorPaciente = [];
foreach ($stmtOrdenes->fetchAll() as $o) {
    $pc = (int) $o['paciente_codigo'];
    $totalPorPaciente[$pc] = ($totalPorPaciente[$pc] ?? 0) + (float) $o['total_orden'];
}

// 3. Traer total pagado por paciente
$pagosPorPaciente = [];
$stmtPagos = $conexion->query('SELECT paciente_codigo, SUM(monto) AS total_pagado FROM caja GROUP BY paciente_codigo');
foreach ($stmtPagos->fetchAll() as $row) {
    $pagosPorPaciente[(int) $row['paciente_codigo']] = (float) $row['total_pagado'];
}

// 4. Calcular el monto a cargo del paciente aplicando cobertura, orden por orden
$stmtOrdenesCompleta = $conexion->query(
    "SELECT o.codigo, o.paciente_codigo, o.fecha_orden,
            COALESCE(SUM(pp.precio), 0) AS total_orden
     FROM ordenes o
     LEFT JOIN estudios_orden eo ON eo.orden_codigo = o.codigo
     LEFT JOIN estudios e ON e.codigo = eo.estudio_codigo
     LEFT JOIN precio_practica pp ON pp.codigo = e.practica_codigo
     GROUP BY o.codigo
     ORDER BY o.paciente_codigo, o.fecha_orden ASC, o.codigo ASC"
);

$acargoPorPaciente = [];
foreach ($stmtOrdenesCompleta->fetchAll() as $o) {
    $pc = (int) $o['paciente_codigo'];
    // Buscar el porcentaje del paciente
    $porcentaje = 0;
    foreach ($pacientes as $p) {
        if ((int) $p['paciente_codigo'] === $pc) {
            $porcentaje = (float) ($p['porcentaje_cobertura'] ?? 0);
            break;
        }
    }
    $montoPaciente = (float) $o['total_orden'] * (1 - $porcentaje / 100);
    $acargoPorPaciente[$pc] = ($acargoPorPaciente[$pc] ?? 0) + $montoPaciente;
}

// 5. Armar la lista de pacientes con saldo
$pacientesConSaldo = [];
foreach ($pacientes as $p) {
    $pc = (int) $p['paciente_codigo'];
    $aCargo = $acargoPorPaciente[$pc] ?? 0;
    $pagado = $pagosPorPaciente[$pc] ?? 0;
    $saldo = max(0, $aCargo - $pagado);
    if ($saldo > 0) {
        $pacientesConSaldo[] = array_merge($p, [
            'a_cargo' => $aCargo,
            'pagado' => $pagado,
            'saldo' => $saldo,
        ]);
    }
}

// 6. Histórico de últimos pagos
$stmtHistorial = $conexion->query(
    "SELECT c.codigo, c.monto, c.metodo_pago, c.fecha_pago,
            p.apellido, p.nombre, p.dni
     FROM caja c
     JOIN pacientes p ON p.codigo = c.paciente_codigo
     ORDER BY c.fecha_pago DESC, c.codigo DESC
     LIMIT 20"
);
$historial = $stmtHistorial->fetchAll();

$mensaje = mensaje_flash();
$tituloPagina = 'Caja | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container">
<div class="page-heading">
    <div>
        <h1>Caja</h1>
        <p class="muted">Saldos pendientes y registro de pagos.</p>
    </div>
</div>
<?php if ($mensaje): ?><div class="alert success-alert"><?= escapar_html($mensaje) ?></div><?php endif; ?>

<h2>Pacientes con saldo pendiente</h2>
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Paciente</th>
                <th>DNI</th>
                <th>Obra social</th>
                <th>Cobertura</th>
                <th>A cargo del paciente</th>
                <th>Pagado</th>
                <th>Saldo</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pacientesConSaldo as $p): ?>
                <tr>
                    <td><strong><?= escapar_html($p['apellido'] . ', ' . $p['nombre']) ?></strong></td>
                    <td><?= escapar_html($p['dni']) ?></td>
                    <td><?= escapar_html($p['obra_social'] ?? 'Particular') ?></td>
                    <td>
                        <?php if ($p['cobertura']): ?>
                            <span class="badge"><?= escapar_html($p['cobertura']) ?></span>
                            <br><small class="muted"><?= number_format((float) $p['porcentaje_cobertura'], 0, ',', '.') ?>%</small>
                        <?php else: ?>
                            <small class="muted">—</small>
                        <?php endif; ?>
                    </td>
                    <td>$ <?= number_format((float) $p['a_cargo'], 2, ',', '.') ?></td>
                    <td>$ <?= number_format((float) $p['pagado'], 2, ',', '.') ?></td>
                    <td>
                        <span style="color:#991b1b; font-weight:700;">$ <?= number_format((float) $p['saldo'], 2, ',', '.') ?></span>
                    </td>
                    <td>
                        <a class="button-small" href="caja_formulario.php?paciente_codigo=<?= (int) $p['paciente_codigo'] ?>">Registrar pago</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pacientesConSaldo): ?>
                <tr><td colspan="8" class="muted">No hay pacientes con saldo pendiente.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<h2 style="margin-top: 40px;">Últimos pagos registrados</h2>
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Nº</th>
                <th>Fecha</th>
                <th>Paciente</th>
                <th>DNI</th>
                <th>Monto</th>
                <th>Método de pago</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($historial as $h): ?>
                <tr>
                    <td><?= (int) $h['codigo'] ?></td>
                    <td><?= escapar_html($h['fecha_pago']) ?></td>
                    <td><?= escapar_html($h['apellido'] . ', ' . $h['nombre']) ?></td>
                    <td><?= escapar_html($h['dni']) ?></td>
                    <td>$ <?= number_format((float) $h['monto'], 2, ',', '.') ?></td>
                    <td><?= escapar_html($h['metodo_pago']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$historial): ?>
                <tr><td colspan="6" class="muted">No hay pagos registrados todavía.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>