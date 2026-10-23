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

$stmtCred = $conexion->prepare(
    "SELECT os.nombre AS obra_social, pos.nro_credencial, pos.cobertura, pos.porcentaje_cobertura
     FROM paciente_obra_social pos
     LEFT JOIN obras_sociales os ON os.codigo = pos.obra_social_codigo
     WHERE pos.paciente_codigo = :pc"
);
$stmtCred->execute(['pc' => $pacienteCodigo]);
$credencial = $stmtCred->fetch();

$porcentaje = (float) ($credencial['porcentaje_cobertura'] ?? 0);

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

            $stmtOrdenes = $conexion->prepare('SELECT codigo FROM ordenes WHERE paciente_codigo = :pc');
            $stmtOrdenes->execute(['pc' => $pacienteCodigo]);
            foreach ($stmtOrdenes->fetchAll() as $o) {
                actualizar_estado_orden((int) $o['codigo']);
            }

            if (isset($_POST['_modal']) && $_POST['_modal'] === '1') {
                header('Content-Type: application/json');
                echo json_encode([
                    'ok' => true,
                    'mensaje' => 'Pago registrado correctamente.',
                ]);
                exit;
            }

            mensaje_flash('Pago registrado correctamente.');
            header('Location: caja.php');
            exit;
        } catch (Throwable $e) {
            $error = 'No se pudo registrar el pago.';
        }
    }

    if (isset($_POST['_modal']) && $_POST['_modal'] === '1') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $error]);
        exit;
    }
}

$esModal = isset($_GET['_modal']) && $_GET['_modal'] === '1';

if ($esModal) {
    ?>
    <form method="post" id="caja-modal-form">
        <input type="hidden" name="_modal" value="1">
        <input type="hidden" name="paciente_codigo" value="<?= $pacienteCodigo ?>">

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= escapar_html($error) ?></div>
        <?php endif; ?>

        <!-- DATOS DEL PACIENTE -->
        <div class="card bg-light border-0 mb-3">
            <div class="card-body">
                <p class="mb-1"><strong><?= escapar_html($paciente['apellido'] . ', ' . $paciente['nombre']) ?></strong></p>
                <p class="text-muted mb-2">DNI: <?= escapar_html($paciente['dni']) ?></p>
                <?php if ($credencial): ?>
                    <p class="mb-1 small"><strong>Obra social:</strong> <?= escapar_html($credencial['obra_social'] ?? 'Particular') ?></p>
                    <p class="mb-2 small"><strong>Cobertura:</strong> <span class="badge bg-info text-dark"><?= escapar_html($credencial['cobertura'] ?? '—') ?></span> (<?= number_format($porcentaje, 2, ',', '.') ?>%)</p>
                <?php else: ?>
                    <p class="mb-2 small"><strong>Particular</strong> (sin obra social)</p>
                <?php endif; ?>
                <hr class="my-2">
                <p class="mb-1 small"><strong>Total bruto:</strong> $ <?= number_format($totalBruto, 2, ',', '.') ?></p>
                <p class="mb-1 small"><strong>A cargo del paciente:</strong> $ <?= number_format($aCargoPaciente, 2, ',', '.') ?></p>
                <p class="mb-1 small"><strong>Pagado:</strong> $ <?= number_format($totalPagado, 2, ',', '.') ?></p>
                <p class="mb-0"><strong class="text-danger">Saldo pendiente:</strong> <span class="fw-bold">$ <?= number_format($saldo, 2, ',', '.') ?></span></p>
            </div>
        </div>

        <!-- FORMULARIO DE PAGO -->
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Importe a abonar *</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" name="monto" min="0.01" max="<?= escapar_html((string) $saldo) ?>" step="0.01" required value="<?= escapar_html($monto) ?>" class="form-control">
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Medio de pago *</label>
                <select name="metodo" class="form-select" required>
                    <option value="">Seleccionar</option>
                    <option <?= $metodo === 'Efectivo' ? 'selected' : '' ?>>Efectivo</option>
                    <option <?= $metodo === 'Tarjeta' ? 'selected' : '' ?>>Tarjeta</option>
                    <option <?= $metodo === 'Transferencia' ? 'selected' : '' ?>>Transferencia</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Fecha de pago</label>
                <input type="date" name="fecha_pago" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
        </div>
    </form>
    <?php
    exit;
}

$tituloPagina = 'Registrar pago | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="main-content">
    <h1>Registrar pago</h1>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>