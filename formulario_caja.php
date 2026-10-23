<?php
require_once __DIR__ . '/includes/autenticacion.php';
exigir_privilegio('cash.manage');
$id = (int) ($_GET['orden_id'] ?? $_POST['orden_id'] ?? 0);
$stmt = db()->prepare("SELECT o.id, o.codigo AS codigo_orden, o.total_adeudado, o.monto_pagado, o.estado_pago, p.apellido, p.nombre FROM ordenes o JOIN pacientes p ON p.id=o.paciente_id WHERE o.id=:id");
$stmt->execute(['id' => $id]);
$orden = $stmt->fetch();
if (!$orden) { http_response_code(404); exit('Orden de caja no encontrada.'); }
$balance = max(0, (float) $orden['total_adeudado'] - (float) $orden['monto_pagado']);
$error = null;
$importe = (string) $balance;
$metodo = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $importe = trim((string) ($_POST['importe'] ?? ''));
    $metodo = (string) ($_POST['metodo'] ?? '');
    $permitidos = ['Efectivo','Tarjeta','Transferencia'];
    if (!is_numeric($importe) || (float) $importe <= 0 || (float) $importe > $balance) {
        $error = 'El importe debe ser mayor a cero y no superar el saldo pendiente.';
    } elseif (!in_array($metodo, $permitidos, true)) {
        $error = 'Selecciona un medio de pago válido.';
    } else {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $nuevoMontoPagado = (float) $orden['monto_pagado'] + (float) $importe;
            $nuevoEstado = $nuevoMontoPagado >= (float) $orden['total_adeudado'] ? 'Pagado' : 'Parcial';
            $update = $pdo->prepare('UPDATE ordenes SET monto_pagado=:monto_pagado,estado_pago=:estado_pago WHERE id=:id');
            $update->execute(['monto_pagado' => $nuevoMontoPagado, 'estado_pago' => $nuevoEstado, 'id' => $id]);
            actualizar_finalizacion_orden($id);
            $pago = $pdo->prepare("INSERT INTO pagos (orden_id, importe, metodo, pagado_en, estado) VALUES (:orden_id,:importe,:metodo,NOW(),'Pagado')");
            $pago->execute(['orden_id' => $id, 'importe' => $importe, 'metodo' => $metodo]);
            $pdo->commit();
            flash($nuevoEstado === 'Pagado' ? 'Orden pagada en su totalidad.' : 'Pago parcial registrado. Saldo restante: $ ' . number_format((float) $orden['total_adeudado'] - $nuevoMontoPagado, 2, ',', '.'));
            header('Location: caja.php'); exit;
        } catch (Throwable $excepcion) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'No se pudo registrar el pago.';
        }
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Registrar pago | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/barra_lateral.php'; ?><main class="container narrow"><p><a href="caja.php">← Volver a Caja</a></p><h1>Registrar pago de orden</h1><div class="module"><p><strong>Orden:</strong> <?= e($orden['codigo_orden']) ?></p><p><strong>Paciente:</strong> <?= e($orden['apellido'] . ', ' . $orden['nombre']) ?></p><p><strong>Total particular:</strong> $ <?= number_format((float) $orden['total_adeudado'], 2, ',', '.') ?></p><p><strong>Abonado:</strong> $ <?= number_format((float) $orden['monto_pagado'], 2, ',', '.') ?></p><p><strong>Saldo pendiente:</strong> $ <?= number_format($balance, 2, ',', '.') ?></p></div><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><form method="post" class="form-grid"><input type="hidden" name="orden_id" value="<?= $id ?>"><div><label>Importe a abonar *</label><input type="number" name="importe" min="0.01" max="<?= e((string) $balance) ?>" step="0.01" required value="<?= e($importe) ?>"></div><div><label>Medio de pago *</label><select name="metodo" required><option value="">Seleccionar</option><option <?= $metodo === 'Efectivo' ? 'selected' : '' ?>>Efectivo</option><option <?= $metodo === 'Tarjeta' ? 'selected' : '' ?>>Tarjeta</option><option <?= $metodo === 'Transferencia' ? 'selected' : '' ?>>Transferencia</option></select></div><div class="full"><button type="submit">Registrar abono</button></div></form></main></body></html>



