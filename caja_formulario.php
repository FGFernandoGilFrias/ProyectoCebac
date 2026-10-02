<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('caja.gestionar');

$pacienteCodigo = (int) ($_GET['paciente_codigo'] ?? $_POST['paciente_codigo'] ?? 0);
$conexion = conexion_bd();

$stmt = $conexion->prepare('SELECT codigo, apellido, nombre, dni FROM pacientes WHERE codigo = :codigo');
$stmt->execute(['codigo' => $pacienteCodigo]);
$paciente = $stmt->fetch();

if (!$paciente) {
    http_response_code(404);
    exit('Paciente no encontrado.');
}

// Traer cobertura del paciente
$stmtCred = $conexion->prepare(
    "SELECT os.nombre AS obra_social, c.nro_credencial, c.cobertura, c.porcentaje_cobertura
     FROM paciente_obra_social pos
     JOIN obras_sociales_credenciales c ON c.codigo = pos.credencial_codigo
     LEFT JOIN obras_sociales os ON os.codigo = c.obra_social_codigo
     WHERE pos.paciente_codigo = :pc"
);
$stmtCred->execute(['pc' => $pacienteCodigo]);
$credencial = $stmtCred->fetch();

$porcentaje = (float) ($credencial['porcentaje_cobertura'] ?? 0);

// Calcular el total a cargo del paciente
$stmt = $conexion->prepare(
    "SELECT COALESCE(SUM(pp.precio), 0) AS total_orden
     FROM ordenes o
     JOIN estudios_orden eo ON eo.orden_codigo = o.codigo
     JOIN estudios e ON e.codigo = eo.estudio_codigo
     JOIN precio_practica pp ON pp.codigo = e.practica_codigo
     WHERE o.paciente_codigo = :pc"
);
$stmt->execute(['pc' => $pacienteCodigo]);
$totalBruto = (float) $stmt->fetchColumn();

$aCargoPaciente = $totalBruto * (1 - $porcentaje / 100);

$stmt = $conexion->prepare('SELECT COALESCE(SUM(monto), 0) FROM caja WHERE paciente_codigo = :pc');
$stmt->execute(['pc' => $pacienteCodigo]);
$totalPagado = (float) $stmt->fetchColumn();

$saldo = max(0, $aCargoPaciente - $totalPagado);

$error = null;
$monto = (string) $saldo;
$metodo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $monto = trim((string) ($_POST['monto'] ?? ''));
    $metodo = (string) ($_POST['metodo'] ?? '');
    $fechaPago = trim((string) ($_POST['fecha_pago'] ?? date('Y-m-d')));

    $allowed = ['Efectivo', 'Tarjeta', 'Transferencia'];

    if (!is_numeric($monto) || (float) $monto <= 0 || (float) $monto > $saldo + 0.001) {
        $error = 'El importe debe ser mayor a cero y no superar el saldo pendiente.';
    } elseif (!in_array($metodo, $allowed, true)) {
        $error = 'Selecciona un medio de pago válido.';
    } else {
        try {
            $stmt = $conexion->prepare('INSERT INTO caja (monto, metodo_pago, fecha_pago, paciente_codigo) VALUES (:monto, :metodo, :fecha, :pc)');
            $stmt->execute([
                'monto' => (float) $monto,
                'metodo' => $metodo,
                'fecha' => $fechaPago,
                'pc' => $pacienteCodigo,
            ]);

            // Actualizar el estado de las órdenes del paciente
            $stmtOrdenes = $conexion->prepare('SELECT codigo FROM ordenes WHERE paciente_codigo = :pc');
            $stmtOrdenes->execute(['pc' => $pacienteCodigo]);
            foreach ($stmtOrdenes->fetchAll() as $o) {
                actualizar_estado_orden((int) $o['codigo']);
            }

            mensaje_flash('Pago registrado correctamente.');
            header('Location: caja.php');
            exit;
        } catch (Throwable $e) {
            $error = 'No se pudo registrar el pago.';
        }
    }
}

$tituloPagina = 'Registrar pago | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container narrow">
<p><a href="caja.php">← Volver a Caja</a></p>
<h1>Registrar pago</h1>

<div class="module">
    <p><strong>Paciente:</strong> <?= escapar_html($paciente['apellido'] . ', ' . $paciente['nombre']) ?></p>
    <p><strong>DNI:</strong> <?= escapar_html($paciente['dni']) ?></p>
    <hr>
    <?php if ($credencial): ?>
        <p><strong>Obra social:</strong> <?= escapar_html($credencial['obra_social']) ?></p>
        <p><strong>Credencial:</strong> <?= escapar_html($credencial['nro_credencial']) ?></p>
        <p><strong>Cobertura:</strong> <span class="badge"><?= escapar_html($credencial['cobertura']) ?></span> (<?= number_format($porcentaje, 2, ',', '.') ?>%)</p>
        <hr>
    <?php else: ?>
        <p><strong>Particular</strong> (sin obra social)</p>
        <hr>
    <?php endif; ?>
    <p><strong>Total bruto de sus órdenes:</strong> $ <?= number_format($totalBruto, 2, ',', '.') ?></p>
    <p><strong>A cargo del paciente:</strong> $ <?= number_format($aCargoPaciente, 2, ',', '.') ?></p>
    <p><strong>Pagado:</strong> $ <?= number_format($totalPagado, 2, ',', '.') ?></p>
    <p><strong>Saldo pendiente:</strong> <span style="color:#991b1b; font-weight:700;">$ <?= number_format($saldo, 2, ',', '.') ?></span></p>
</div>

<?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?>

<form method="post" class="form-grid">
    <input type="hidden" name="paciente_codigo" value="<?= $pacienteCodigo ?>">

    <div>
        <label>Importe a abonar *</label>
        <input type="number" name="monto" min="0.01" max="<?= escapar_html((string) $saldo) ?>" step="0.01" required value="<?= escapar_html($monto) ?>">
    </div>
    <div>
        <label>Medio de pago *</label>
        <select name="metodo" required>
            <option value="">Seleccionar</option>
            <option <?= $metodo === 'Efectivo' ? 'selected' : '' ?>>Efectivo</option>
            <option <?= $metodo === 'Tarjeta' ? 'selected' : '' ?>>Tarjeta</option>
            <option <?= $metodo === 'Transferencia' ? 'selected' : '' ?>>Transferencia</option>
        </select>
    </div>
    <div>
        <label>Fecha de pago</label>
        <input type="date" name="fecha_pago" value="<?= date('Y-m-d') ?>">
    </div>
    <div class="full"><button type="submit">Registrar pago</button></div>
</form>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>