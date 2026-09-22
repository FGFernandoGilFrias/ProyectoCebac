<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('caja.gestionar');
$id = (int) ($_GET['orden_id'] ?? $_POST['orden_id'] ?? 0);
$consulta = conexion_bd()->prepare("SELECT o.id, o.codigo AS codigo_orden, o.total_adeudado, o.monto_pagado, o.estado_pago, p.apellido, p.nombre FROM ordenes o JOIN pacientes p ON p.id=o.paciente_id WHERE o.id=:id");
$consulta->execute(['id' => $id]);
$orden = $consulta->fetch();
if (!$orden) { http_response_code(404); exit('Orden de caja no encontrada.'); }
$balance = max(0, (float) $orden['total_adeudado'] - (float) $orden['monto_pagado']);
$error = null;
$amount = (string) $balance;
$metodo = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = trim((string) ($_POST['amount'] ?? ''));
    $metodo = (string) ($_POST['metodo'] ?? '');
    $allowed = ['Efectivo','Tarjeta','Transferencia'];
    if (!is_numeric($amount) || (float) $amount <= 0 || (float) $amount > $balance) {
        $error = 'El importe debe ser mayor a cero y no superar el saldo pendiente.';
    } elseif (!in_array($metodo, $allowed, true)) {
        $error = 'Selecciona un medio de pago válido.';
    } else {
        $conexion = conexion_bd();
        try {
            $conexion->beginTransaction();
            $newPaid = (float) $orden['monto_pagado'] + (float) $amount;
            $newStatus = $newPaid >= (float) $orden['total_adeudado'] ? 'Pagado' : 'Parcial';
            $actualizacion = $conexion->prepare('UPDATE ordenes SET monto_pagado=:monto_pagado,estado_pago=:estado_pago WHERE id=:id');
            $actualizacion->execute(['monto_pagado' => $newPaid, 'estado_pago' => $newStatus, 'id' => $id]);
            actualizar_finalizacion_orden($id);
            $payment = $conexion->prepare("INSERT INTO pagos (orden_id, amount, metodo, pagado_en, estado) VALUES (:orden_id,:amount,:metodo,NOW(),'Pagado')");
            $payment->execute(['orden_id' => $id, 'amount' => $amount, 'metodo' => $metodo]);
            $conexion->commit();
            mensaje_flash($newStatus === 'Pagado' ? 'Orden pagada en su totalidad.' : 'Pago parcial registrado. Saldo restante: $ ' . number_format((float) $orden['total_adeudado'] - $newPaid, 2, ',', '.'));
            header('Location: caja.php'); exit;
        } catch (Throwable $exception) {
            if ($conexion->inTransaction()) $conexion->rollBack();
            $error = 'No se pudo registrar el pago.';
        }
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Registrar pago | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main class="container narrow"><p><a href="caja.php">← Volver a Caja</a></p><h1>Registrar pago de orden</h1><div class="module"><p><strong>Orden:</strong> <?= escapar_html($orden['codigo_orden']) ?></p><p><strong>Paciente:</strong> <?= escapar_html($orden['apellido'] . ', ' . $orden['nombre']) ?></p><p><strong>Total particular:</strong> $ <?= number_format((float) $orden['total_adeudado'], 2, ',', '.') ?></p><p><strong>Abonado:</strong> $ <?= number_format((float) $orden['monto_pagado'], 2, ',', '.') ?></p><p><strong>Saldo pendiente:</strong> $ <?= number_format($balance, 2, ',', '.') ?></p></div><?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?><form method="post" class="form-grid"><input type="hidden" name="orden_id" value="<?= $id ?>"><div><label>Importe a abonar *</label><input type="number" name="amount" min="0.01" max="<?= escapar_html((string) $balance) ?>" step="0.01" required value="<?= escapar_html($amount) ?>"></div><div><label>Medio de pago *</label><select name="metodo" required><option value="">Seleccionar</option><option <?= $metodo === 'Efectivo' ? 'selected' : '' ?>>Efectivo</option><option <?= $metodo === 'Tarjeta' ? 'selected' : '' ?>>Tarjeta</option><option <?= $metodo === 'Transferencia' ? 'selected' : '' ?>>Transferencia</option></select></div><div class="full"><button type="submit">Registrar abono</button></div></form></main></body></html>








