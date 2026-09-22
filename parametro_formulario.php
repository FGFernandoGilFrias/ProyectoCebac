<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('parametros.gestionar');

$estudioId = (int) ($_GET['estudio_id'] ?? $_POST['estudio_id'] ?? 0);
$conexion = conexion_bd();
$consultaEstudios = $conexion->prepare('SELECT id, codigo, nombre FROM estudios WHERE id = :id');
$consultaEstudios->execute(['id' => $estudioId]);
$estudio = $consultaEstudios->fetch();
if (!$estudio) {
    http_response_code(404);
    exit('Estudio no encontrado.');
}
$parameterStmt = $conexion->prepare('SELECT * FROM parametros_estudios WHERE estudio_id = :estudio_id ORDER BY orden, id');
$parameterStmt->execute(['estudio_id' => $estudioId]);
$parametros = $parameterStmt->fetchAll();
$parametros[] = ['nombre_seccion' => '', 'nombre' => '', 'minimo' => '', 'maximo' => '', 'texto_referencia' => '', 'descripcion' => '', 'rango_min' => '', 'rango_max' => ''];
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parametros = [];
    foreach ((array) ($_POST['parameter'] ?? []) as $parameter) {
        $parameter = array_map(static fn ($value) => trim((string) $value), (array) $parameter);
        if (($parameter['nombre'] ?? '') !== '') {
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
    try {
        $conexion->beginTransaction();
        $delete = $conexion->prepare('DELETE FROM parametros_estudios WHERE estudio_id = :estudio_id');
        $delete->execute(['estudio_id' => $estudioId]);
        $save = $conexion->prepare(
            'INSERT INTO parametros_estudios (estudio_id, nombre_seccion, nombre, minimo, maximo, texto_referencia, descripcion, rango_min, rango_max, orden)
             VALUES (:estudio_id, :nombre_seccion, :nombre, :minimo, :maximo, :texto_referencia, :descripcion, :rango_min, :rango_max, :orden)'
        );
        foreach ($parametros as $position => $parameter) {
            $save->execute([
                'estudio_id' => $estudioId,
                'nombre_seccion' => $parameter['nombre_seccion'] !== '' ? $parameter['nombre_seccion'] : null,
                'nombre' => $parameter['nombre'],
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
        mensaje_flash('Parámetros del estudio guardados correctamente.');
        header('Location: parametros.php');
        exit;
    } catch (PDOException $exception) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        $error = 'No se pudieron guardar los parámetros.';
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Parámetros de <?= escapar_html($estudio['nombre']) ?> | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main class="container"><p><a href="parametros.php">← Volver a parámetros</a></p><h1>Parámetros de <?= escapar_html($estudio['nombre']) ?></h1><p class="muted">Estudio <?= escapar_html($estudio['codigo']) ?>. Todos los parámetros cargados aquí forman su informe.</p><?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?><form method="post" class="module parameter-editor"><input type="hidden" name="estudio_id" value="<?= $estudioId ?>"><div id="parameter-list"><?php foreach ($parametros as $position => $parameter): ?><div class="parameter-row"><input name="parameter[<?= $position ?>][nombre_seccion]" placeholder="Sección" value="<?= escapar_html($parameter['nombre_seccion'] ?? '') ?>"><input name="parameter[<?= $position ?>][nombre]" placeholder="Parámetro *" value="<?= escapar_html($parameter['nombre'] ?? '') ?>"><input name="parameter[<?= $position ?>][minimo]" placeholder="Mínimo" value="<?= escapar_html($parameter['minimo'] ?? '') ?>"><input name="parameter[<?= $position ?>][maximo]" placeholder="Máximo" value="<?= escapar_html($parameter['maximo'] ?? '') ?>"><input name="parameter[<?= $position ?>][texto_referencia]" placeholder="Referencia" value="<?= escapar_html($parameter['texto_referencia'] ?? '') ?>"><input name="parameter[<?= $position ?>][descripcion]" placeholder="Descripción (ej. sexo o condición)" value="<?= escapar_html($parameter['descripcion'] ?? '') ?>"><input name="parameter[<?= $position ?>][rango_min]" placeholder="Rango desde" value="<?= escapar_html($parameter['rango_min'] ?? '') ?>"><input name="parameter[<?= $position ?>][rango_max]" placeholder="Rango hasta" value="<?= escapar_html($parameter['rango_max'] ?? '') ?>"><button type="button" class="remove-parameter">Quitar</button></div><?php endforeach; ?></div><button type="button" class="button-small add-parameter" id="add-parameter">+ Agregar parámetro</button><div class="parameter-actions"><button type="submit">Guardar parámetros del estudio</button></div></form></main><script>const list=document.getElementById('parameter-list');let position=list.children.length;document.getElementById('add-parameter').addEventListener('click',function(){const row=document.createElement('div');row.className='parameter-row';row.innerHTML='<input name="parameter['+position+'][nombre_seccion]" placeholder="Sección"><input name="parameter['+position+'][nombre]" placeholder="Parámetro *"><input name="parameter['+position+'][minimo]" placeholder="Mínimo"><input name="parameter['+position+'][maximo]" placeholder="Máximo"><input name="parameter['+position+'][texto_referencia]" placeholder="Referencia"><input name="parameter['+position+'][descripcion]" placeholder="Descripción (ej. sexo o condición)"><input name="parameter['+position+'][rango_min]" placeholder="Rango desde"><input name="parameter['+position+'][rango_max]" placeholder="Rango hasta"><button type="button" class="remove-parameter">Quitar</button>';list.appendChild(row);position++;});document.addEventListener('click',function(event){if(event.target.classList.contains('remove-parameter')&&list.children.length>1){event.target.parentElement.remove();}});</script></body></html>








