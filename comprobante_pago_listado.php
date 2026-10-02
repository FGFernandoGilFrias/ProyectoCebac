<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('reportes.gestionar');

$conexion = conexion_bd();

$desde = trim((string) ($_GET['desde'] ?? date('Y-m-01')));
$hasta = trim((string) ($_GET['hasta'] ?? date('Y-m-d')));
$pacienteId = (int) ($_GET['paciente_id'] ?? 0);

$error = null;
if ($desde === '' || $hasta === '') {
    $error = 'Ingresá ambas fechas.';
}
if (!$error && $desde > $hasta) {
    $error = 'La fecha "desde" no puede ser mayor que "hasta".';
}

function fecha_ddmmyyyy(?string $fecha): string
{
    if (!$fecha) return '—';
    $partes = explode('-', substr($fecha, 0, 10));
    return count($partes) === 3 ? $partes[2] . '-' . $partes[1] . '-' . $partes[0] : $fecha;
}

$pagos = [];
$totalPeriodo = 0;

if (!$error) {
    $sql = "SELECT c.codigo AS pago_codigo, c.monto, c.metodo_pago, c.fecha_pago,
                   p.codigo AS paciente_codigo, p.apellido, p.nombre, p.dni
            FROM caja c
            JOIN pacientes p ON p.codigo = c.paciente_codigo
            WHERE c.fecha_pago BETWEEN :desde AND :hasta";
    $params = ['desde' => $desde, 'hasta' => $hasta];

    if ($pacienteId > 0) {
        $sql .= ' AND c.paciente_codigo = :paciente';
        $params['paciente'] = $pacienteId;
    }

    $sql .= ' ORDER BY c.fecha_pago DESC, c.codigo DESC LIMIT 500';

    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $pagos = $stmt->fetchAll();

    foreach ($pagos as $p) {
        $totalPeriodo += (float) $p['monto'];
    }
}

$pacientes = $conexion->query('SELECT codigo, apellido, nombre, dni FROM pacientes ORDER BY apellido, nombre')->fetchAll();

$tituloPagina = 'Comprobantes de pago | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container">
<p><a href="reportes.php">← Volver a reportes</a></p>

<div class="page-heading">
    <div>
        <span class="eyebrow">Reporte</span>
        <h1>Comprobantes de pago</h1>
        <p class="muted">Listado de pagos registrados. Hacé clic en 🖨️ para imprimir el comprobante.</p>
    </div>
</div>

<?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?>

<form method="get" class="report-filters module">
    <div class="filter-grid">
        <div>
            <label for="desde">Desde</label>
            <input type="date" id="desde" name="desde" value="<?= escapar_html($desde) ?>" required>
        </div>
        <div>
            <label for="hasta">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="<?= escapar_html($hasta) ?>" required>
        </div>
        <div>
            <label for="paciente_id">Paciente</label>
            <select id="paciente_id" name="paciente_id">
                <option value="0">Todos</option>
                <?php foreach ($pacientes as $p): ?>
                    <option value="<?= (int) $p['codigo'] ?>" <?= $pacienteId === (int) $p['codigo'] ? 'selected' : '' ?>>
                        <?= escapar_html($p['apellido'] . ', ' . $p['nombre']) ?> — DNI <?= escapar_html($p['dni']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit">Generar</button>
            <a href="comprobante_pago_listado.php" class="link-secondary">Limpiar</a>
        </div>
    </div>
</form>

<?php if (!$error): ?>
    <section class="report-summary">
        <div class="summary-card">
            <span class="eyebrow">Cantidad de pagos</span>
            <strong class="summary-number"><?= count($pagos) ?></strong>
            <small class="muted">Entre <?= escapar_html(fecha_ddmmyyyy($desde)) ?> y <?= escapar_html(fecha_ddmmyyyy($hasta)) ?></small>
        </div>
        <div class="summary-card">
            <span class="eyebrow">Total cobrado</span>
            <strong class="summary-number">$ <?= number_format($totalPeriodo, 0, ',', '.') ?></strong>
            <small class="muted">Suma de los pagos</small>
        </div>
        <div class="summary-card">
            <span class="eyebrow">Promedio</span>
            <strong class="summary-number">$ <?= count($pagos) > 0 ? number_format($totalPeriodo / count($pagos), 0, ',', '.') : '0' ?></strong>
            <small class="muted">Por pago</small>
        </div>
    </section>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Fecha</th>
                    <th>Paciente</th>
                    <th>DNI</th>
                    <th>Método</th>
                    <th style="text-align: right;">Monto</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pagos as $p): ?>
                    <tr>
                        <td><strong><?= str_pad((string) $p['pago_codigo'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                        <td><?= escapar_html(fecha_ddmmyyyy($p['fecha_pago'])) ?></td>
                        <td><?= escapar_html($p['apellido'] . ', ' . $p['nombre']) ?></td>
                        <td><?= escapar_html($p['dni']) ?></td>
                        <td><?= escapar_html($p['metodo_pago']) ?></td>
                        <td style="text-align: right; font-weight: 700;">$ <?= number_format((float) $p['monto'], 2, ',', '.') ?></td>
                        <td>
                            <a class="button-small" href="comprobante_pago_pdf.php?pago_id=<?= (int) $p['pago_codigo'] ?>" target="_blank">🖨️ Imprimir</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$pagos): ?>
                    <tr><td colspan="7" class="muted">No hay pagos registrados en el período.</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if ($pagos): ?>
                <tfoot>
                    <tr style="background: #f1f5f9; font-weight: bold;">
                        <td colspan="5" style="text-align: right;">TOTAL</td>
                        <td style="text-align: right;">$ <?= number_format($totalPeriodo, 2, ',', '.') ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
<?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>