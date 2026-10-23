<?php
require_once __DIR__ . '/includes/autenticacion.php';
exigir_privilegio('ordenes.manage');

$pacientes = db()->query('SELECT id, dni, apellido, nombre FROM pacientes ORDER BY apellido, nombre')->fetchAll();
$obrasSociales = db()->query('SELECT id, nombre FROM obras_sociales WHERE activo = 1 ORDER BY nombre')->fetchAll();
$estudios = db()->query('SELECT s.id, s.codigo, s.nombre, p.codigo_practica, p.precio FROM estudios s LEFT JOIN precios_practicas p ON p.id = s.precio_practica_id WHERE s.activo = 1 ORDER BY s.nombre')->fetchAll();
$autorizaciones = db()->query('SELECT obra_social_id, estudio_id FROM obras_sociales_estudios')->fetchAll();
$autorizadosPorObraSocial = [];
foreach ($autorizaciones as $autorizacion) {
    $autorizadosPorObraSocial[(int) $autorizacion['obra_social_id']][] = (int) $autorizacion['estudio_id'];
}
$error = null;
$form = ['paciente_id' => '', 'obra_social_id' => '', 'fecha_orden' => date('Y-m-d'), 'medico' => ''];
$selectedStudies = [];
$modoPago = 'Pagar al retirar';
$metodoPago = 'Efectivo';
$importePago = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['paciente_id'] = (int) ($_POST['paciente_id'] ?? 0);
    $form['obra_social_id'] = (int) ($_POST['obra_social_id'] ?? 0);
    $form['fecha_orden'] = trim((string) ($_POST['fecha_orden'] ?? ''));
    $form['medico'] = trim((string) ($_POST['medico'] ?? ''));
    $modoPago = (string) ($_POST['modo_pago'] ?? 'Pagar al retirar');
    $metodoPago = (string) ($_POST['metodo_pago'] ?? 'Efectivo');
    $importePago = trim((string) ($_POST['importe_pago'] ?? ''));
    $selectedStudies = array_values(array_unique(array_map('intval', $_POST['estudios_ids'] ?? [])));
    $modosValidos = ['Abonar ahora', 'Pagar al retirar'];
    $metodosValidos = ['Efectivo', 'Tarjeta', 'Transferencia'];
    if (!$form['paciente_id'] || !$form['obra_social_id'] || $form['fecha_orden'] === '' || $form['medico'] === '' || !$selectedStudies) {
        $error = 'Paciente, obra social, fecha, médico y al menos un estudio son obligatorios.';
    } elseif (!in_array($modoPago, $modosValidos, true) || ($modoPago === 'Abonar ahora' && (!in_array($metodoPago, $metodosValidos, true) || !is_numeric($importePago) || (float) $importePago <= 0))) {
        $error = 'Selecciona una opción de pago válida.';
    } else {
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $siguienteCodigo = (int) $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)), 0) + 1 FROM ordenes WHERE codigo REGEXP '^ORD-[0-9]+$'")->fetchColumn();
            $codigo = 'ORD-' . str_pad((string) $siguienteCodigo, 3, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare('INSERT INTO ordenes (codigo,paciente_id,obra_social_id,fecha_orden,medico) VALUES (:codigo,:paciente_id,:obra_social_id,:fecha_orden,:medico)');
            $stmt->execute(['codigo' => $codigo, 'paciente_id' => $form['paciente_id'], 'obra_social_id' => $form['obra_social_id'], 'fecha_orden' => $form['fecha_orden'], 'medico' => $form['medico']]);
            $ordenId = (int) $pdo->lastInsertId();
            $consultaEstudio = $pdo->prepare('SELECT s.id, s.precio_practica_id, p.precio FROM estudios s LEFT JOIN precios_practicas p ON p.id=s.precio_practica_id WHERE s.id=:id AND s.activo=1');
            $autorizacion = $pdo->prepare('SELECT COUNT(*) FROM obras_sociales_estudios WHERE obra_social_id = :obra_social_id AND estudio_id = :estudio_id');
            $insertarEstudioOrden = $pdo->prepare('INSERT INTO ordenes_estudios (orden_id,estudio_id,precio_practica_id,precio,estado_pago) VALUES (:orden_id,:estudio_id,:precio_practica_id,:precio,:estado_pago)');
            $totalAdeudado = 0.0;
            foreach ($selectedStudies as $estudioId) {
                $autorizacion->execute(['obra_social_id' => $form['obra_social_id'], 'estudio_id' => $estudioId]);
                $estadoPago = $autorizacion->fetchColumn() ? 'No requiere' : 'Pendiente';
                $consultaEstudio->execute(['id' => $estudioId]);
                $estudio = $consultaEstudio->fetch();
                if (!$estudio) {
                    throw new RuntimeException('Uno de los estudios seleccionados no está disponible.');
                }
                $insertarEstudioOrden->execute(['orden_id' => $ordenId, 'estudio_id' => $estudio['id'], 'precio_practica_id' => $estudio['precio_practica_id'], 'precio' => $estudio['precio'] ?? 0, 'estado_pago' => $estadoPago]);
                $ordenEstudioId = (int) $pdo->lastInsertId();
                if ($estadoPago !== 'No requiere') {
                    $totalAdeudado += (float) ($estudio['precio'] ?? 0);
                }
            }
            $montoPagado = $modoPago === 'Abonar ahora' ? (float) $importePago : 0;
            if ($montoPagado > $totalAdeudado) {
                throw new RuntimeException('El importe abonado no puede superar el total particular de la orden.');
            }
            $estadoPagoOrden = $totalAdeudado <= 0 ? 'No requiere' : ($montoPagado >= $totalAdeudado ? 'Pagado' : ($montoPagado > 0 ? 'Parcial' : 'Pagar al retirar'));
            $updateOrder = $pdo->prepare('UPDATE ordenes SET estado_pago=:estado_pago,total_adeudado=:total_adeudado,monto_pagado=:monto_pagado WHERE id=:id');
            $updateOrder->execute(['estado_pago' => $estadoPagoOrden, 'total_adeudado' => $totalAdeudado, 'monto_pagado' => $montoPagado, 'id' => $ordenId]);
            if ($montoPagado > 0) {
                $pago = $pdo->prepare("INSERT INTO pagos (orden_id, importe, metodo, pagado_en, estado) VALUES (:orden_id, :importe, :metodo, :pagado_en, :estado)");
                $pago->execute([
                    'orden_id' => $ordenId,
                    'importe' => $montoPagado,
                    'metodo' => $modoPago === 'Abonar ahora' ? $metodoPago : 'Pagar al retirar',
                    'pagado_en' => $modoPago === 'Abonar ahora' ? date('Y-m-d H:i:s') : null,
                    'estado' => $modoPago === 'Abonar ahora' ? 'Pagado' : 'Pagar al retirar',
                ]);
            }
            $pdo->commit();
            $idsAutorizados = $autorizadosPorObraSocial[$form['obra_social_id']] ?? [];
            $noAutorizados = [];
            foreach ($estudios as $estudio) {
                if (in_array((int) $estudio['id'], $selectedStudies, true) && !in_array((int) $estudio['id'], $idsAutorizados, true)) {
                    $noAutorizados[] = $estudio['nombre'];
                }
            }
            $mensaje = 'Orden ' . $codigo . ' registrada correctamente.';
            if ($modoPago === 'Abonar ahora') {
                $mensaje .= ' Abonado: $ ' . number_format($montoPagado, 2, ',', '.') . '.';
            } else {
                $mensaje .= ' Los estudios particulares quedaron para pagar al retirar. Total particular: $ ' . number_format($totalAdeudado, 2, ',', '.') . '.';
            }
            flash($mensaje);
            header('Location: ordenes.php');
            exit;
        } catch (Throwable $excepcion) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $excepcion instanceof PDOException && $excepcion->getCode() === '23000' ? 'La orden contiene datos no válidos o un estudio duplicado.' : $excepcion->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Nueva orden | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head>
<body><?php include __DIR__ . '/includes/barra_lateral.php'; ?>
<main class="container narrow"><p><a href="ordenes.php">← Volver a órdenes</a></p><h1>Nueva orden médica</h1>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="form-grid">
<div class="full"><label>Paciente *</label><select name="paciente_id" required><option value="">Seleccionar paciente</option><?php foreach ($pacientes as $paciente): ?><option value="<?= (int) $paciente['id'] ?>" <?= (string) $form['paciente_id'] === (string) $paciente['id'] ? 'selected' : '' ?>><?= e($paciente['apellido'] . ', ' . $paciente['nombre'] . ' - DNI ' . $paciente['dni']) ?></option><?php endforeach; ?></select></div>
<div class="full"><label>Obra social *</label><select name="obra_social_id" id="obra_social_id" required><option value="">Seleccionar obra social</option><?php foreach ($obrasSociales as $obraSocial): ?><option value="<?= (int) $obraSocial['id'] ?>" <?= (string) $form['obra_social_id'] === (string) $obraSocial['id'] ? 'selected' : '' ?>><?= e($obraSocial['nombre']) ?></option><?php endforeach; ?></select><small class="muted">Los estudios no autorizados pueden ofrecerse como particulares.</small></div>
<div><label>Fecha de orden *</label><input type="date" name="fecha_orden" required value="<?= e($form['fecha_orden']) ?>"></div><div><label>Médico solicitante *</label><input name="medico" required value="<?= e($form['medico']) ?>"></div>
<div class="full"><label>Estudios solicitados *</label><input id="estudio_search" class="estudio-busqueda" type="search" placeholder="Buscar por código o nombre"><div id="authorization_notice" class="alert warning-alert" hidden></div><div class="estudio-picker"><?php foreach ($estudios as $estudio): ?><label class="estudio-option" data-estudio-text="<?= e(strtolower($estudio['codigo'] . ' ' . $estudio['nombre'] . ' ' . ($estudio['codigo_practica'] ?? ''))) ?>" data-estudio-precio="<?= e((string) ($estudio['precio'] ?? 0)) ?>"><input type="checkbox" name="estudios_ids[]" value="<?= (int) $estudio['id'] ?>" <?= in_array((int) $estudio['id'], $selectedStudies, true) ? 'checked' : '' ?>><span><?= e($estudio['nombre']) ?> (<?= e($estudio['codigo']) ?>) — <?= e($estudio['codigo_practica'] ?? 'Sin práctica') ?> — $ <?= number_format((float) ($estudio['precio'] ?? 0), 2, ',', '.') ?> <strong class="authorization-label" data-estudio-id="<?= (int) $estudio['id'] ?>"></strong></span></label><?php endforeach; ?></div></div>
<div class="full payment-choice"><label>Pago de estudios no autorizados *</label><select name="modo_pago" id="modo_pago" required><option <?= $modoPago === 'Abonar ahora' ? 'selected' : '' ?>>Abonar ahora</option><option <?= $modoPago === 'Pagar al retirar' ? 'selected' : '' ?>>Pagar al retirar</option></select><p id="order_total_notice" class="payment-total">Total particular: $ 0,00</p><div id="payment_now_container"><label>Importe que abona ahora</label><input type="number" name="importe_pago" id="importe_pago" min="0.01" step="0.01" value="<?= e($importePago) ?>" placeholder="Puede ser total o parcial"></div><div id="payment_method_container"><label>Medio de pago</label><select name="metodo_pago"><option <?= $metodoPago === 'Efectivo' ? 'selected' : '' ?>>Efectivo</option><option <?= $metodoPago === 'Tarjeta' ? 'selected' : '' ?>>Tarjeta</option><option <?= $metodoPago === 'Transferencia' ? 'selected' : '' ?>>Transferencia</option></select></div></div>
<div class="full"><button type="submit">Registrar orden</button></div></form>
<p class="muted">La obra social se informa para validar cobertura; el precio de una práctica no autorizada se gestiona en Caja.</p>
<script>
const autorizaciones = <?= json_encode($autorizadosPorObraSocial, JSON_UNESCAPED_UNICODE) ?>;
const obraSocial = document.getElementById('obra_social_id');
const notice = document.getElementById('authorization_notice');
function updateAuthorizationLabels() {
    const authorized = (autorizaciones[obraSocial.value] || []).map(Number);
    const notAuthorized = [];
    let total = 0;
    document.querySelectorAll('.authorization-label').forEach(label => {
        const id = Number(label.dataset.estudioId);
        const allowed = obraSocial.value !== '' && authorized.includes(id);
        label.textContent = obraSocial.value === '' ? '' : (allowed ? 'Autorizado' : 'No autorizado - particular');
        label.className = 'authorization-label ' + (allowed ? 'authorized' : 'not-authorized');
        const checkbox = document.querySelector('input[value="' + id + '"]');
        if (checkbox && checkbox.checked && obraSocial.value !== '' && !allowed) {
            notAuthorized.push(label.parentElement.textContent.split(' — ')[0]);
            total += Number(checkbox.closest('.estudio-option').dataset.estudioPrice || 0);
        }
    });
    notice.hidden = notAuthorized.length === 0;
    notice.textContent = notAuthorized.length ? 'No autorizados por la obra social: ' + notAuthorized.join(', ') + '. Puede ofrecerse al paciente como particular.' : '';
    document.getElementById('order_total_notice').textContent = 'Total particular: $ ' + total.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}
obraSocial.addEventListener('change', updateAuthorizationLabels);
document.querySelectorAll('input[name="estudios_ids[]"]').forEach(input => input.addEventListener('change', updateAuthorizationLabels));
document.getElementById('estudio_search').addEventListener('input', function () {
    const term = this.value.toLowerCase().trim();
    const options = Array.from(document.querySelectorAll('.estudio-option'));
    const matches = options.filter(option => option.dataset.estudioText.includes(term));
    options.forEach(option => { option.hidden = term !== '' && !option.dataset.estudioText.includes(term); });
    if (term !== '' && matches.length === 1) {
        matches[0].querySelector('input[type="checkbox"]').checked = true;
        updateAuthorizationLabels();
    }
});
updateAuthorizationLabels();
const paymentMode = document.getElementById('modo_pago');
const paymentNowContainer = document.getElementById('payment_now_container');
const paymentMethodContainer = document.getElementById('payment_method_container');
function updatePaymentFields() { paymentNowContainer.hidden = paymentMode.value !== 'Abonar ahora'; paymentMethodContainer.hidden = paymentMode.value !== 'Abonar ahora'; document.getElementById('importe_pago').required = paymentMode.value === 'Abonar ahora'; }
paymentMode.addEventListener('change', updatePaymentFields);
updatePaymentFields();
</script></main></body></html>



