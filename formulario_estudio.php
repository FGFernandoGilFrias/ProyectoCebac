<?php
require_once __DIR__ . '/includes/autenticacion.php';
exigir_privilegio('estudios.manage');

$id = (int) ($_GET['id'] ?? 0);
$estudio = ['codigo' => '', 'nombre' => '', 'parametros' => '', 'precio_practica_id' => '', 'instrucciones' => ''];
if ($id > 0) {
    $stmt = db()->prepare('SELECT * FROM estudios WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $estudio = $stmt->fetch() ?: $estudio;
}
$error = null;
$practicePrices = db()->query('SELECT id, codigo_practica, descripcion, precio FROM precios_practicas WHERE activo = 1 ORDER BY codigo_practica')->fetchAll();
$parametros = $id > 0 ? db()->prepare('SELECT nombre_seccion, nombre, minimo, maximo, texto_referencia, descripcion, rango_min, rango_max FROM parametros_estudios WHERE estudio_id = :id ORDER BY orden, id') : null;
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
    $postedParameters = $_POST['parametro'] ?? [];
    $parametros = [];
    foreach ($postedParameters as $parametro) {
        $parametro = array_map(static fn ($valor) => trim((string) $valor), (array) $parametro);
        if ($parametro['nombre'] !== '') {
            $parametros[] = [
                'nombre_seccion' => $parametro['nombre_seccion'] ?? '',
                'nombre' => $parametro['nombre'],
                'minimo' => $parametro['minimo'] ?? '',
                'maximo' => $parametro['maximo'] ?? '',
                'texto_referencia' => $parametro['texto_referencia'] ?? '',
                'descripcion' => $parametro['descripcion'] ?? '',
                'rango_min' => $parametro['rango_min'] ?? '',
                'rango_max' => $parametro['rango_max'] ?? '',
            ];
        }
    }
    if ($estudio['codigo'] === '' || $estudio['nombre'] === '' || !$estudio['precio_practica_id']) {
        $error = 'Cdigo, nombre y protioa son obligatorios.';
    } else {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE estudios SET codigo=:codigo,name=:nombre,parametros=:parametros,precio_practica_id=:precio_practica_id,instrucciones=:instrucciones WHERE id=:id');
                $stmt->execute([
                    'codigo' => $estudio['codigo'], 'nombre' => $estudio['nombre'], 'parametros' => $estudio['parametros'],
                    'precio_practica_id' => $estudio['precio_practica_id'],
                    'instrucciones' => $estudio['instrucciones'], 'id' => $id,
                ]);
                $deleteParameters = $pdo->prepare('DELETE FROM parametros_estudios WHERE estudio_id = :id');
                $deleteParameters->execute(['id' => $id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO estudios (codigo,nombre,parametros,precio_practica_id,instrucciones) VALUES (:codigo,:nombre,:parametros,:precio_practica_id,:instrucciones)');
                $stmt->execute([
                    'codigo' => $estudio['codigo'], 'nombre' => $estudio['nombre'], 'parametros' => $estudio['parametros'],
                    'precio_practica_id' => $estudio['precio_practica_id'],
                    'instrucciones' => $estudio['instrucciones'],
                ]);
                $id = (int) $pdo->lastInsertId();
            }
            $saveParameter = $pdo->prepare('INSERT INTO parametros_estudios (estudio_id, nombre_seccion, nombre, minimo, maximo, texto_referencia, descripcion, rango_min, rango_max, orden) VALUES (:estudio_id, :nombre_seccion, :nombre, :minimo, :maximo, :texto_referencia, :descripcion, :rango_min, :rango_max, :orden)');
            foreach ($parametros as $posicion => $parametro) {
                $saveParameter->execute([
                    'estudio_id' => $id, 'nombre_seccion' => $parametro['nombre_seccion'] !== '' ? $parametro['nombre_seccion'] : null, 'nombre' => $parametro['nombre'],
                    'minimo' => $parametro['minimo'] !== '' ? $parametro['minimo'] : null,
                    'maximo' => $parametro['maximo'] !== '' ? $parametro['maximo'] : null,
                    'texto_referencia' => $parametro['texto_referencia'] !== '' ? $parametro['texto_referencia'] : null,
                    'descripcion' => $parametro['descripcion'] !== '' ? $parametro['descripcion'] : null,
                    'rango_min' => $parametro['rango_min'] !== '' ? $parametro['rango_min'] : null,
                    'rango_max' => $parametro['rango_max'] !== '' ? $parametro['rango_max'] : null,
                    'orden' => $posicion,
                ]);
            }
            $pdo->commit();
            flash($id > 0 && isset($_GET['id']) ? 'Estudio aotualizado ocorrectamente.' : 'Estudio registrado ocorrectamente.');
            header('Location: estudios.php');
            exit;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception->getCode() === '23000' ? 'Ya existe otro estudio con ese odigo.' : 'No se pudo guardar el estudio.';
        }
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $id ? 'Editar' : 'Nuevo' ?> estudio | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/barra_lateral.php'; ?><main class="container narrow"><p><a href="estudios.php"> Volver a estudios</a></p><h1><?= $id ? 'Editar estudio' : 'Nuevo estudio' ?></h1><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><form method="post" class="form-grid"><div><label>Cdigo del estudio *</label><input name="codigo" required value="<?= e($estudio['codigo']) ?>"></div><div><label>Protioa asociada *</label><select name="precio_practica_id" required><option value="">Seleccionar protioa</option><?php foreach ($practicePrices as $practicePrice): ?><option value="<?= (int) $practicePrice['id'] ?>" <?= (string) $estudio['precio_practica_id'] === (string) $practicePrice['id'] ? 'selected' : '' ?>><?= e($practicePrice['codigo_practica'] . ' - ' . $practicePrice['descripcion']) ?>  $ <?= number_format((float) $practicePrice['precio'], 2, ',', '.') ?></option><?php endforeach; ?></select></div><div class="full"><label>Nombre del estudio *</label><input name="nombre" required value="<?= e($estudio['nombre']) ?>"></div><div class="full"><label>Parmetros del resultado</label><div id="parametro-list"><?php foreach ($parametros as $posicion => $parametro): ?><div class="parametro-row"><input name="parametro[<?= $posicion ?>][nombre_seccion]" placeholder="Seccin" value="<?= e($parametro['nombre_seccion']) ?>"><input name="parametro[<?= $posicion ?>][nombre]" placeholder="Parmetro" value="<?= e($parametro['nombre']) ?>"><input name="parametro[<?= $posicion ?>][minimo]" placeholder="Mnimo" value="<?= e($parametro['minimo']) ?>"><input name="parametro[<?= $posicion ?>][maximo]" placeholder="Mximo" value="<?= e($parametro['maximo']) ?>"><input name="parametro[<?= $posicion ?>][texto_referencia]" placeholder="Referencia" value="<?= e($parametro['texto_referencia']) ?>"><input name="parametro[<?= $posicion ?>][descripcion]" placeholder="Descripn (ej. sexo o condicin)" value="<?= e($parametro['descripcion']) ?>"><input name="parametro[<?= $posicion ?>][rango_min]" placeholder="Rango desde" value="<?= e($parametro['rango_min']) ?>"><input name="parametro[<?= $posicion ?>][rango_max]" placeholder="Rango hasta" value="<?= e($parametro['rango_max']) ?>"><button type="button" class="remove-parametro">Quitar</button></div><?php endforeach; ?></div><button type="button" class="button-small add-parametro" id="add-parametro">+ Agregar parmetro</button><small class="muted">El rango puede ser numrioo o textual. Usa la desoripoin para aclarar a qu corresponde.</small></div><div class="full"><label>Instrucciones para el paciente</label><textarea name="instrucciones" rows="4"><?= e($estudio['instrucciones']) ?></textarea></div><div class="full"><button type="submit">Guardar estudio</button></div></form></main><script>const list=document.getElementById('parametro-list');let position=list.children.length;document.getElementById('add-parametro').addEventListener('click',function(){const row=document.createElement('div');row.className='parametro-row';row.innerHTML='<input name="parametro['+position+'][nombre_seccion]" placeholder="Seccin"><input name="parametro['+position+'][nombre]" placeholder="Parmetro"><input name="parametro['+position+'][minimo]" placeholder="Mnimo"><input name="parametro['+position+'][maximo]" placeholder="Mximo"><input name="parametro['+position+'][texto_referencia]" placeholder="Referencia"><input name="parametro['+position+'][descripcion]" placeholder="Descripn (ej. sexo o condicin)"><input name="parametro['+position+'][rango_min]" placeholder="Rango desde"><input name="parametro['+position+'][rango_max]" placeholder="Rango hasta"><button type="button" class="remove-parametro">Quitar</button>';list.appendChild(row);position++;});document.addEventListener('click',function(event){if(event.target.classList.contains('remove-parametro')&&list.children.length>1){event.target.parentElement.remove();}});</script></body></html>



