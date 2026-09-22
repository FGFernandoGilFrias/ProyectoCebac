<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('estudios.gestionar');

$id = (int) ($_GET['id'] ?? 0);
$estudio = ['codigo' => '', 'nombre' => '', 'parametros' => '', 'precio_practica_id' => '', 'instrucciones' => ''];
if ($id > 0) {
    $consulta = conexion_bd()->prepare('SELECT * FROM estudios WHERE id = :id');
    $consulta->execute(['id' => $id]);
    $estudio = $consulta->fetch() ?: $estudio;
}
$error = null;
$precioPracticas = conexion_bd()->query('SELECT id, codigo_practica, descripcion, precio FROM precios_practicas WHERE activo = 1 ORDER BY codigo_practica')->fetchAll();
$parametros = $id > 0 ? conexion_bd()->prepare('SELECT nombre_seccion, nombre, minimo, maximo, texto_referencia, descripcion, rango_min, rango_max FROM parametros_estudios WHERE estudio_id = :id ORDER BY orden, id') : null;
if ($parametros) {
    $parametros->execute(['id' => $id]);
    $parametros = $parametros->fetchAll();
}
$parametros = $parametros ?: [['nombre_seccion' => '', 'nombre' => '', 'minimo' => '', 'maximo' => '', 'texto_referencia' => '', 'descripcion' => '', 'rango_min' => '', 'rango_max' => '']];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($estudio) as $key) {
        if (array_key_exists($key, $_POST)) {
            $estudio[$key] = trim((string) $_POST[$key]);
        }
    }
    $estudio['precio_practica_id'] = $estudio['precio_practica_id'] !== '' ? (int) $estudio['precio_practica_id'] : null;
    $postedParameters = $_POST['parameter'] ?? [];
    $parametros = [];
    foreach ($postedParameters as $parameter) {
        $parameter = array_map(static fn ($value) => trim((string) $value), (array) $parameter);
        if ($parameter['nombre'] !== '') {
            $parametros[] = [
                'nombre_seccion' => $parameter['nombre_seccion'] ?? '',
                'nombre' => $parameter['nombre'],
                'minimo' => $parameter['minimo'] ?? '',
                'maximo' => $parameter['maximo'] ?? '',
                'texto_referencia' => $parameter['texto_referencia'] ?? '',
                'descripcion' => $parameter['descripcion'] ?? '',
                'rango_min' => $parameter['rango_min'] ?? '',
                'rango_max' => $parameter['rango_max'] ?? '',
            ];
        }
    }
    if ($estudio['codigo'] === '' || $estudio['nombre'] === '' || !$estudio['precio_practica_id']) {
        $error = 'Código, nombre y práctica son obligatorios.';
    } else {
        $conexion = conexion_bd();
        try {
            $conexion->beginTransaction();
            if ($id > 0) {
                $consulta = $conexion->prepare('UPDATE estudios SET codigo=:codigo,nombre=:nombre,parametros=:parametros,precio_practica_id=:precio_practica_id,instrucciones=:instrucciones WHERE id=:id');
                $consulta->execute([
                    'codigo' => $estudio['codigo'], 'nombre' => $estudio['nombre'], 'parametros' => $estudio['parametros'],
                    'precio_practica_id' => $estudio['precio_practica_id'],
                    'instrucciones' => $estudio['instrucciones'], 'id' => $id,
                ]);
                $deleteParameters = $conexion->prepare('DELETE FROM parametros_estudios WHERE estudio_id = :id');
                $deleteParameters->execute(['id' => $id]);
            } else {
                $consulta = $conexion->prepare('INSERT INTO estudios (codigo,nombre,parametros,precio_practica_id,instrucciones) VALUES (:codigo,:nombre,:parametros,:precio_practica_id,:instrucciones)');
                $consulta->execute([
                    'codigo' => $estudio['codigo'], 'nombre' => $estudio['nombre'], 'parametros' => $estudio['parametros'],
                    'precio_practica_id' => $estudio['precio_practica_id'],
                    'instrucciones' => $estudio['instrucciones'],
                ]);
                $id = (int) $conexion->lastInsertId();
            }
            $saveParameter = $conexion->prepare('INSERT INTO parametros_estudios (estudio_id, nombre_seccion, nombre, minimo, maximo, texto_referencia, descripcion, rango_min, rango_max, orden) VALUES (:estudio_id, :nombre_seccion, :nombre, :minimo, :maximo, :texto_referencia, :descripcion, :rango_min, :rango_max, :orden)');
            foreach ($parametros as $position => $parameter) {
                $saveParameter->execute([
                    'estudio_id' => $id, 'nombre_seccion' => $parameter['nombre_seccion'] !== '' ? $parameter['nombre_seccion'] : null, 'nombre' => $parameter['nombre'],
                    'minimo' => $parameter['minimo'] !== '' ? $parameter['minimo'] : null,
                    'maximo' => $parameter['maximo'] !== '' ? $parameter['maximo'] : null,
                    'texto_referencia' => $parameter['texto_referencia'] !== '' ? $parameter['texto_referencia'] : null,
                    'descripcion' => $parameter['descripcion'] !== '' ? $parameter['descripcion'] : null,
                    'rango_min' => $parameter['rango_min'] !== '' ? $parameter['rango_min'] : null,
                    'rango_max' => $parameter['rango_max'] !== '' ? $parameter['rango_max'] : null,
                    'orden' => $position,
                ]);
            }
            $conexion->commit();
            mensaje_flash($id > 0 && isset($_GET['id']) ? 'Estudio actualizado correctamente.' : 'Estudio registrado correctamente.');
            header('Location: estudios.php');
            exit;
        } catch (PDOException $exception) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            $error = $exception->getCode() === '23000' ? 'Ya existe otro estudio con ese código.' : 'No se pudo guardar el estudio.';
        }
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $id ? 'Editar' : 'Nuevo' ?> estudio | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main class="container narrow"><p><a href="estudios.php">← Volver a estudios</a></p><h1><?= $id ? 'Editar estudio' : 'Nuevo estudio' ?></h1><?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?><form method="post" class="form-grid"><div><label>Código del estudio *</label><input name="codigo" required value="<?= escapar_html($estudio['codigo']) ?>"></div><div><label>Práctica asociada *</label><select name="precio_practica_id" required><option value="">Seleccionar práctica</option><?php foreach ($precioPracticas as $precioPractica): ?><option value="<?= (int) $precioPractica['id'] ?>" <?= (string) $estudio['precio_practica_id'] === (string) $precioPractica['id'] ? 'selected' : '' ?>><?= escapar_html($precioPractica['codigo_practica'] . ' - ' . $precioPractica['descripcion']) ?> — $ <?= number_format((float) $precioPractica['precio'], 2, ',', '.') ?></option><?php endforeach; ?></select></div><div class="full"><label>Nombre del estudio *</label><input name="nombre" required value="<?= escapar_html($estudio['nombre']) ?>"></div><div class="full"><label>Parámetros del resultado</label><div id="parameter-list"><?php foreach ($parametros as $position => $parameter): ?><div class="parameter-row"><input name="parameter[<?= $position ?>][nombre_seccion]" placeholder="Sección" value="<?= escapar_html($parameter['nombre_seccion']) ?>"><input name="parameter[<?= $position ?>][nombre]" placeholder="Parámetro" value="<?= escapar_html($parameter['nombre']) ?>"><input name="parameter[<?= $position ?>][minimo]" placeholder="Mínimo" value="<?= escapar_html($parameter['minimo']) ?>"><input name="parameter[<?= $position ?>][maximo]" placeholder="Máximo" value="<?= escapar_html($parameter['maximo']) ?>"><input name="parameter[<?= $position ?>][texto_referencia]" placeholder="Referencia" value="<?= escapar_html($parameter['texto_referencia']) ?>"><input name="parameter[<?= $position ?>][descripcion]" placeholder="Descripción (ej. sexo o condición)" value="<?= escapar_html($parameter['descripcion']) ?>"><input name="parameter[<?= $position ?>][rango_min]" placeholder="Rango desde" value="<?= escapar_html($parameter['rango_min']) ?>"><input name="parameter[<?= $position ?>][rango_max]" placeholder="Rango hasta" value="<?= escapar_html($parameter['rango_max']) ?>"><button type="button" class="remove-parameter">Quitar</button></div><?php endforeach; ?></div><button type="button" class="button-small add-parameter" id="add-parameter">+ Agregar parámetro</button><small class="muted">El rango puede ser numérico o textual. Usa la descripción para aclarar a qué corresponde.</small></div><div class="full"><label>Instrucciones para el paciente</label><textarea name="instrucciones" rows="4"><?= escapar_html($estudio['instrucciones']) ?></textarea></div><div class="full"><button type="submit">Guardar estudio</button></div></form></main><script>const list=document.getElementById('parameter-list');let position=list.children.length;document.getElementById('add-parameter').addEventListener('click',function(){const row=document.createElement('div');row.className='parameter-row';row.innerHTML='<input name="parameter['+position+'][nombre_seccion]" placeholder="Sección"><input name="parameter['+position+'][nombre]" placeholder="Parámetro"><input name="parameter['+position+'][minimo]" placeholder="Mínimo"><input name="parameter['+position+'][maximo]" placeholder="Máximo"><input name="parameter['+position+'][texto_referencia]" placeholder="Referencia"><input name="parameter['+position+'][descripcion]" placeholder="Descripción (ej. sexo o condición)"><input name="parameter['+position+'][rango_min]" placeholder="Rango desde"><input name="parameter['+position+'][rango_max]" placeholder="Rango hasta"><button type="button" class="remove-parameter">Quitar</button>';list.appendChild(row);position++;});document.addEventListener('click',function(event){if(event.target.classList.contains('remove-parameter')&&list.children.length>1){event.target.parentElement.remove();}});</script></body></html>








