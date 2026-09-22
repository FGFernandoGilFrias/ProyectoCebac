<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('ordenes.gestionar');
$ordenId = (int) ($_GET['id'] ?? $_POST['orden_id'] ?? 0);
$modoEdicion = $ordenId > 0;
$conexion = conexion_bd();
$ordenExistente = null;
if ($modoEdicion) { $stmt = $conexion->prepare('SELECT * FROM ordenes WHERE id=:id'); $stmt->execute(['id'=>$ordenId]); $ordenExistente=$stmt->fetch(); if (!$ordenExistente) { http_response_code(404); exit('Orden no encontrada.'); } }
$pacientes = $conexion->query('SELECT id,codigo,dni,apellido,nombre,fecha_nacimiento,telefono,correo FROM pacientes ORDER BY apellido,nombre')->fetchAll();
$obraSocials = $conexion->query('SELECT id,nombre FROM obras_sociales WHERE activo=1 ORDER BY nombre')->fetchAll();
$estudios = $conexion->query('SELECT s.id,s.codigo,s.nombre,p.codigo_practica,p.precio FROM estudios s LEFT JOIN precios_practicas p ON p.id=s.precio_practica_id WHERE s.activo=1 ORDER BY s.nombre')->fetchAll();
$authorizations = $conexion->query('SELECT obra_social_id,estudio_id FROM obras_sociales_estudios')->fetchAll(); $authorizedBySocialWork=[]; foreach($authorizations as $a)$authorizedBySocialWork[(int)$a['obra_social_id']][]=(int)$a['estudio_id'];
$error=null; $form=$ordenExistente?['paciente_id'=>(int)$ordenExistente['paciente_id'],'obra_social_id'=>(int)$ordenExistente['obra_social_id'],'fecha_orden'=>$ordenExistente['fecha_orden'],'medico'=>$ordenExistente['medico']]:['paciente_id'=>'','obra_social_id'=>'','fecha_orden'=>date('Y-m-d'),'medico'=>''];
$estudiosSeleccionados=[]; if($modoEdicion){$s=$conexion->prepare('SELECT estudio_id FROM ordenes_estudios WHERE orden_id=:id');$s->execute(['id'=>$ordenId]);$estudiosSeleccionados=array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));}
$paymentMode='Pagar al retirar';$metodoPago='Efectivo';$paymentAmount='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $form['paciente_id']=(int)($_POST['paciente_id']??0);$form['obra_social_id']=(int)($_POST['obra_social_id']??0);$form['fecha_orden']=trim((string)($_POST['fecha_orden']??''));$form['medico']=trim((string)($_POST['medico']??''));$paymentMode=(string)($_POST['payment_mode']??'Pagar al retirar');$metodoPago=(string)($_POST['payment_method']??'Efectivo');$paymentAmount=trim((string)($_POST['payment_amount']??''));$estudiosSeleccionados=array_values(array_unique(array_map('intval',$_POST['estudios_ids']??[])));
 $validModes=['Abonar ahora','Pagar al retirar'];$validMethods=['Efectivo','Tarjeta','Transferencia'];
 if(!$form['paciente_id']||!$form['obra_social_id']||$form['fecha_orden']===''||$form['medico']===''||!$estudiosSeleccionados)$error='Paciente, obra social, fecha, médico y al menos un estudio son obligatorios.';
 elseif(!$modoEdicion&&(!in_array($paymentMode,$validModes,true)||($paymentMode==='Abonar ahora'&&(!in_array($metodoPago,$validMethods,true)||!is_numeric($paymentAmount)||(float)$paymentAmount<=0))))$error='Selecciona una opción de pago válida.';
 else try{$conexion->beginTransaction();$auth=$conexion->prepare('SELECT COUNT(*) FROM obras_sociales_estudios WHERE obra_social_id=:social AND estudio_id=:study');$studyStmt=$conexion->prepare('SELECT s.id,s.precio_practica_id,p.precio FROM estudios s LEFT JOIN precios_practicas p ON p.id=s.precio_practica_id WHERE s.id=:id AND s.activo=1');$totalDue=0.0;
  if(!$modoEdicion){$next=(int)$conexion->query("SELECT COALESCE(MAX(CAST(SUBSTRING(codigo,5) AS UNSIGNED)),0)+1 FROM ordenes WHERE codigo REGEXP '^ORD-[0-9]+$'")->fetchColumn();$codigo='ORD-'.str_pad((string)$next,3,'0',STR_PAD_LEFT);$ins=$conexion->prepare('INSERT INTO ordenes(codigo,paciente_id,obra_social_id,fecha_orden,medico) VALUES(:code,:patient,:social,:date,:doctor)');$ins->execute(['code'=>$codigo,'patient'=>$form['paciente_id'],'social'=>$form['obra_social_id'],'date'=>$form['fecha_orden'],'doctor'=>$form['medico']]);$ordenId=(int)$conexion->lastInsertId();}
  else{$old=$conexion->prepare('SELECT estudio_id,id FROM ordenes_estudios WHERE orden_id=:id');$old->execute(['id'=>$ordenId]);$newSet=array_flip($estudiosSeleccionados);$check=$conexion->prepare('SELECT (SELECT COUNT(*) FROM muestras WHERE orden_estudio_id=:os) samples,(SELECT COUNT(*) FROM resultados WHERE orden_estudio_id=:os) results,(SELECT COUNT(*) FROM pagos WHERE orden_estudio_id=:os OR (orden_id=:order_id AND orden_estudio_id IS NULL)) payments');foreach($old->fetchAll() as $row)if(!isset($newSet[(int)$row['estudio_id']])){$check->execute(['os'=>(int)$row['id'],'order_id'=>$ordenId]);$blocked=$check->fetch();if($blocked['samples']||$blocked['results']||$blocked['payments'])throw new RuntimeException('No se puede quitar un estudio que ya tiene muestra, resultado o pagos registrados.');}$up=$conexion->prepare('UPDATE ordenes SET paciente_id=:patient,obra_social_id=:social,fecha_orden=:date,medico=:doctor WHERE id=:id');$up->execute(['patient'=>$form['paciente_id'],'social'=>$form['obra_social_id'],'date'=>$form['fecha_orden'],'doctor'=>$form['medico'],'id'=>$ordenId]);}
  $es=$conexion->prepare('SELECT id,estudio_id FROM ordenes_estudios WHERE orden_id=:id');$es->execute(['id'=>$ordenId]);$existing=[];foreach($es->fetchAll() as $row){$key=(int)$row['estudio_id'];$existing[$key]=$row;}$insItem=$conexion->prepare('INSERT INTO ordenes_estudios(orden_id,estudio_id,precio_practica_id,precio,estado_pago) VALUES(:order_id,:study,:practice,:price,:status)');$upItem=$conexion->prepare('UPDATE ordenes_estudios SET precio_practica_id=:practice,precio=:price,estado_pago=:status WHERE id=:id');
  foreach ($estudiosSeleccionados as $studyId) {
      $studyStmt->execute(['id' => $studyId]);
      $study = $studyStmt->fetch();
      if (!$study) throw new RuntimeException('Uno de los estudios seleccionados no está disponible.');
      $auth->execute(['social' => $form['obra_social_id'], 'study' => $studyId]);
      $authorized = (bool) $auth->fetchColumn();
      $status = $authorized ? 'No requiere' : 'Pendiente';
      $price = (float) ($study['precio'] ?? 0);
      if (isset($existing[$studyId])) {
          $upItem->execute(['practice' => $study['precio_practica_id'], 'price' => $price, 'status' => $status, 'id' => $existing[$studyId]['id']]);
      } else {
          $insItem->execute(['order_id' => $ordenId, 'study' => $studyId, 'practice' => $study['precio_practica_id'], 'price' => $price, 'status' => $status]);
      }
      if (!$authorized) $totalDue += $price;
  }
  if($modoEdicion){$paidAmount=(float)$ordenExistente['monto_pagado'];if($paidAmount>$totalDue)throw new RuntimeException('Los estudios seleccionados generan un total inferior a los pagos ya registrados; no se modificó la orden.');$delete=$conexion->prepare('DELETE FROM ordenes_estudios WHERE orden_id=:order_id AND estudio_id=:study');foreach($existing as $oldStudyId=>$oldStudy)if(!in_array($oldStudyId,$estudiosSeleccionados,true))$delete->execute(['order_id'=>$ordenId,'study'=>$oldStudyId]);}else{$paidAmount=$paymentMode==='Abonar ahora'?(float)$paymentAmount:0;if($paidAmount>$totalDue)throw new RuntimeException('El importe abonado no puede superar el total particular de la orden.');}
  $status=$totalDue<=0?'No requiere':($paidAmount>=$totalDue?'Pagado':($paidAmount>0?'Parcial':'Pagar al retirar'));$ou=$conexion->prepare('UPDATE ordenes SET estado_pago=:status,total_adeudado=:total,monto_pagado=:paid WHERE id=:id');$ou->execute(['status'=>$status,'total'=>$totalDue,'paid'=>$paidAmount,'id'=>$ordenId]);if(!$modoEdicion&&$paidAmount>0){$pay=$conexion->prepare("INSERT INTO pagos(orden_id,amount,metodo,pagado_en,estado) VALUES(:id,:amount,:method,:date,'Pagado')");$pay->execute(['id'=>$ordenId,'amount'=>$paidAmount,'method'=>$metodoPago,'date'=>date('Y-m-d H:i:s')]);}$conexion->commit();mensaje_flash($modoEdicion?'Orden '.$ordenExistente['codigo'].' actualizada correctamente.':'Orden '.$codigo.' registrada correctamente.');header('Location: ordenes.php');exit;
 }catch(Throwable $e){if($conexion->inTransaction())$conexion->rollBack();$error=$e->getMessage();}
}
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $modoEdicion ? 'Editar orden' : 'Nueva orden' ?> | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head>
<body><?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="container order-admission"><p><a href="ordenes.php">Volver a órdenes</a></p>
<div class="module-heading admission-header"><div><span class="eyebrow">Admisión de pacientes</span><h1><?= $modoEdicion ? 'Editar orden médica' : 'Nueva orden médica' ?></h1><p><?= $modoEdicion ? 'Actualizá los datos sin modificar pagos, muestras ni resultados registrados.' : 'Registrá la orden, los estudios y el pago inicial en un solo circuito.' ?></p></div><span class="order-code-preview">Código de orden<br><strong><?= escapar_html($modoEdicion ? $ordenExistente['codigo'] : 'ORD-' . str_pad((string) ((int) conexion_bd()->query("SELECT COALESCE(MAX(id), 0) + 1 FROM ordenes")->fetchColumn()), 3, '0', STR_PAD_LEFT)) ?></strong></span></div>
<?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?>
<form method="post" class="order-layout"><input type="hidden" name="orden_id" value="<?= (int) $ordenId ?>">
<div class="order-main">
<section class="module patient-card"><div class="section-heading"><div><span class="eyebrow">Datos del paciente</span><h2>Identificación</h2></div><span class="required-hint">* Campos obligatorios</span></div>
<div class="patient-grid"><div class="field-wide"><label for="paciente_id">Buscar y seleccionar paciente *</label><select name="paciente_id" id="paciente_id" required><option value="">Seleccionar paciente</option><?php foreach ($pacientes as $paciente): ?><option value="<?= (int) $paciente['id'] ?>" data-dni="<?= escapar_html($paciente['dni']) ?>" data-apellido="<?= escapar_html($paciente['apellido']) ?>" data-nombre="<?= escapar_html($paciente['nombre']) ?>" data-fecha="<?= escapar_html($paciente['fecha_nacimiento'] ?? '') ?>" data-telefono="<?= escapar_html($paciente['telefono'] ?? '') ?>" data-correo="<?= escapar_html($paciente['correo'] ?? '') ?>" <?= (string) $form['paciente_id'] === (string) $paciente['id'] ? 'selected' : '' ?>><?= escapar_html($paciente['apellido'] . ', ' . $paciente['nombre'] . ' - DNI ' . $paciente['dni']) ?></option><?php endforeach; ?></select></div>
<div><label for="paciente_dni">DNI</label><input id="paciente_dni" readonly></div><div><label for="paciente_apellido">Apellido</label><input id="paciente_apellido" readonly></div><div><label for="paciente_nombre">Nombre</label><input id="paciente_nombre" readonly></div><div><label for="paciente_fecha">Fecha de nacimiento</label><input id="paciente_fecha" readonly></div><div><label for="paciente_telefono">Teléfono</label><input id="paciente_telefono" readonly></div><div class="field-wide"><label for="paciente_correo">Correo electrónico</label><input id="paciente_correo" readonly></div></div></section>
<section class="module order-card"><div class="tabs" role="tablist"><button type="button" class="tab active" data-tab="datos">Datos de la orden</button><button type="button" class="tab" data-tab="adicionales">Adicionales</button><button type="button" class="tab" data-tab="facturacion">Facturación</button></div>
<div class="tab-panel active" data-panel="datos"><div class="form-grid embedded-grid"><div><label for="fecha_orden">Fecha de orden *</label><input type="date" name="fecha_orden" id="fecha_orden" required value="<?= escapar_html($form['fecha_orden']) ?>"></div><div><label for="medico">Médico solicitante *</label><input name="medico" id="medico" required value="<?= escapar_html($form['medico']) ?>" placeholder="Nombre del profesional"></div><div class="full"><label for="obra_social_id">Obra social *</label><select name="obra_social_id" id="obra_social_id" required><option value="">Seleccionar obra social</option><?php foreach ($obraSocials as $obraSocial): ?><option value="<?= (int) $obraSocial['id'] ?>" <?= (string) $form['obra_social_id'] === (string) $obraSocial['id'] ? 'selected' : '' ?>><?= escapar_html($obraSocial['nombre']) ?></option><?php endforeach; ?></select><small class="muted">Los estudios no autorizados se registran como particulares y se cobran en Caja.</small></div><div class="full"><label for="study_search">Estudios solicitados *</label><input id="study_search" class="study-search" type="search" placeholder="Buscar por código o nombre"><div id="authorization_notice" class="alert warning-alert" hidden></div><div class="study-picker"><?php foreach ($estudios as $estudio): ?><label class="study-option" data-study-text="<?= escapar_html(strtolower($estudio['codigo'] . ' ' . $estudio['nombre'] . ' ' . ($estudio['codigo_practica'] ?? ''))) ?>" data-study-precio="<?= escapar_html((string) ($estudio['precio'] ?? 0)) ?>"><input type="checkbox" name="estudios_ids[]" value="<?= (int) $estudio['id'] ?>" <?= in_array((int) $estudio['id'], $estudiosSeleccionados, true) ? 'checked' : '' ?>><span><?= escapar_html($estudio['nombre']) ?> (<?= escapar_html($estudio['codigo']) ?>) - $ <?= number_format((float) ($estudio['precio'] ?? 0), 2, ',', '.') ?> <strong class="authorization-label" data-estudio-id="<?= (int) $estudio['id'] ?>"></strong></span></label><?php endforeach; ?></div></div></div></div>
<div class="tab-panel" data-panel="adicionales"><label for="observaciones">Observaciones</label><textarea id="observaciones" rows="5" placeholder="Indicaciones u observaciones para el laboratorio"></textarea><p class="muted">Las observaciones son informativas y no modifican la orden ni sus importes.</p></div>
<div class="tab-panel" data-panel="facturacion"><div class="payment-choice"><span class="eyebrow">Pago de Caja</span><h2>Facturación de la orden</h2><?php if ($modoEdicion): ?><p class="muted">Los pagos ya registrados no se modifican aquí. Los nuevos pagos se registran desde Caja.</p><p id="order_total_notice" class="payment-total">Total particular: $ <?= number_format((float)$ordenExistente['total_adeudado'], 2, ',', '.') ?></p><p>Abonado: $ <?= number_format((float)$ordenExistente['monto_pagado'], 2, ',', '.') ?></p><p>Saldo: $ <?= number_format(max(0, (float)$ordenExistente['total_adeudado'] - (float)$ordenExistente['monto_pagado']), 2, ',', '.') ?></p><?php else: ?><p class="muted">El pago inicial se registra en la misma orden y queda disponible para pagos parciales posteriores en Caja.</p><label for="payment_mode">Forma de pago *</label><select name="payment_mode" id="payment_mode" required><option <?= $paymentMode === 'Abonar ahora' ? 'selected' : '' ?>>Abonar ahora</option><option <?= $paymentMode === 'Pagar al retirar' ? 'selected' : '' ?>>Pagar al retirar</option></select><p id="order_total_notice" class="payment-total">Total particular: $ 0,00</p><div id="payment_now_container"><label for="payment_amount">Importe abonado inicialmente</label><input type="number" name="payment_amount" id="payment_amount" min="0.01" step="0.01" value="<?= escapar_html($paymentAmount) ?>" placeholder="Puede ser total o parcial"></div><div id="payment_method_container"><label for="payment_method">Medio de pago</label><select name="payment_method" id="payment_method"><option <?= $metodoPago === 'Efectivo' ? 'selected' : '' ?>>Efectivo</option><option <?= $metodoPago === 'Tarjeta' ? 'selected' : '' ?>>Tarjeta</option><option <?= $metodoPago === 'Transferencia' ? 'selected' : '' ?>>Transferencia</option></select></div><?php endif; ?></div></div></div>
</section><div class="order-actions"><a class="button-link secondary-button" href="ordenes.php">Cancelar</a><button type="submit"><?= $modoEdicion ? 'Guardar cambios' : 'Registrar orden' ?></button></div>
</div><aside class="order-summary module"><div class="section-heading"><div><span class="eyebrow">Resumen</span><h2>Detalle de orden</h2></div></div><label for="summary_search">Buscar estudio</label><input id="summary_search" type="search" placeholder="Filtrar estudios"><div id="selected_studies" class="selected-studies"><p class="muted">Aún no hay estudios seleccionados.</p></div><div class="summary-total"><div><span>Fecha</span><strong><?= escapar_html($form['fecha_orden']) ?></strong></div><div><span>Obra social</span><strong id="summary_social">Sin seleccionar</strong></div><div><span>Importe particular</span><strong id="summary_total">$ 0,00</strong></div><div><span>Abonado</span><strong id="summary_paid">$ 0,00</strong></div><div class="balance"><span>Saldo</span><strong id="summary_balance">$ 0,00</strong></div></div></aside>
</form>
<script>
const authorizations = <?= json_encode($authorizedBySocialWork, JSON_UNESCAPED_UNICODE) ?>;
const socialWork = document.getElementById('obra_social_id');
const notice = document.getElementById('authorization_notice');
const paciente = document.getElementById('paciente_id');
const resumenEstudios = document.getElementById('selected_studies');
const importePagadoOrden = <?= $modoEdicion ? json_encode((float) $ordenExistente['monto_pagado']) : '0' ?>;
const formatoMoneda = valor => '$ ' + Number(valor).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
function actualizarPaciente() {
    const opcion = paciente.options[paciente.selectedIndex];
    ['dni', 'apellido', 'nombre', 'fecha', 'telefono', 'correo'].forEach(campo => {
        document.getElementById('paciente_' + campo).value = opcion && opcion.dataset ? (opcion.dataset[campo] || '') : '';
    });
}
function actualizarResumen() {
    const seleccionados = Array.from(document.querySelectorAll('input[name="estudios_ids[]"]:checked'));
    let total = 0;
    resumenEstudios.innerHTML = '';
    seleccionados.forEach(checkbox => {
        const opcion = checkbox.closest('.study-option');
        const etiqueta = opcion.querySelector('span').textContent.split(' - ')[0];
        const precio = Number(opcion.dataset.studyPrecio || 0);
        const autorizado = opcion.querySelector('.authorization-label').classList.contains('authorized');
        if (!autorizado) total += precio;
        const item = document.createElement('div');
        item.className = 'selected-study';
        item.innerHTML = '<span>' + etiqueta + '</span><strong>' + formatoMoneda(autorizado ? 0 : precio) + '</strong>';
        resumenEstudios.appendChild(item);
    });
    if (!seleccionados.length) resumenEstudios.innerHTML = '<p class="muted">Aún no hay estudios seleccionados.</p>';
    document.getElementById('summary_total').textContent = formatoMoneda(total);
    const modoPago = document.getElementById('payment_mode');
    const abonado = modoPago ? (modoPago.value === 'Abonar ahora' ? Number(document.getElementById('payment_amount').value || 0) : 0) : importePagadoOrden;
    document.getElementById('summary_paid').textContent = formatoMoneda(abonado);
    document.getElementById('summary_balance').textContent = formatoMoneda(Math.max(0, total - abonado));
    document.getElementById('summary_social').textContent = socialWork.selectedIndex > 0 ? socialWork.options[socialWork.selectedIndex].text : 'Sin seleccionar';
}
function updateAuthorizationLabels() {
    const authorized = (authorizations[socialWork.value] || []).map(Number);
    const notAuthorized = [];
    let total = 0;
    document.querySelectorAll('.authorization-label').forEach(label => {
        const id = Number(label.dataset.estudioId);
        const allowed = socialWork.value !== '' && authorized.includes(id);
        label.textContent = socialWork.value === '' ? '' : (allowed ? 'Autorizado' : 'No autorizado - particular');
        label.className = 'authorization-label ' + (allowed ? 'authorized' : 'not-authorized');
        const checkbox = document.querySelector('input[value="' + id + '"]');
        if (checkbox && checkbox.checked && socialWork.value !== '' && !allowed) {
            notAuthorized.push(label.parentElement.textContent.split(' - ')[0]);
            total += Number(checkbox.closest('.study-option').dataset.studyPrecio || 0);
        }
    });
    notice.hidden = notAuthorized.length === 0;
    notice.textContent = notAuthorized.length ? 'No autorizados por la obra social: ' + notAuthorized.join(', ') + '. Puede ofrecerse al paciente como particular.' : '';
    document.getElementById('order_total_notice').textContent = 'Total particular: $ ' + total.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    actualizarResumen();
}
paciente.addEventListener('change', actualizarPaciente);
socialWork.addEventListener('change', updateAuthorizationLabels);
document.querySelectorAll('input[name="estudios_ids[]"]').forEach(input => input.addEventListener('change', updateAuthorizationLabels));
document.getElementById('study_search').addEventListener('input', function () {
    const term = this.value.toLowerCase().trim();
    const options = Array.from(document.querySelectorAll('.study-option'));
    const matches = options.filter(option => option.dataset.studyText.includes(term));
    options.forEach(option => { option.hidden = term !== '' && !option.dataset.studyText.includes(term); });
    if (term !== '' && matches.length === 1) {
        matches[0].querySelector('input[type="checkbox"]').checked = true;
        updateAuthorizationLabels();
    }
});
updateAuthorizationLabels();
const paymentMode = document.getElementById('payment_mode');
const paymentNowContainer = document.getElementById('payment_now_container');
const paymentMethodContainer = document.getElementById('payment_method_container');
function updatePaymentFields() { if (!paymentMode) return; paymentNowContainer.hidden = paymentMode.value !== 'Abonar ahora'; paymentMethodContainer.hidden = paymentMode.value !== 'Abonar ahora'; document.getElementById('payment_amount').required = paymentMode.value === 'Abonar ahora'; actualizarResumen(); }
if (paymentMode) paymentMode.addEventListener('change', updatePaymentFields);
if (document.getElementById('payment_amount')) document.getElementById('payment_amount').addEventListener('input', actualizarResumen);
document.getElementById('summary_search').addEventListener('input', function () {
    const termino = this.value.toLowerCase().trim();
    document.querySelectorAll('.selected-study').forEach(item => { item.hidden = termino !== '' && !item.textContent.toLowerCase().includes(termino); });
});
document.querySelectorAll('.tab').forEach(tab => tab.addEventListener('click', function () {
    document.querySelectorAll('.tab, .tab-panel').forEach(element => element.classList.remove('active'));
    this.classList.add('active');
    document.querySelector('[data-panel="' + this.dataset.tab + '"]').classList.add('active');
}));
actualizarPaciente();
if (paymentMode) updatePaymentFields();
</script></main></body></html>
