<?php
require_once __DIR__ . '/includes/autenticacion.php';
exigir_privilegio('parametros.manage');

$estudioId = (int) ($_GET['estudio_id'] ?? $_POST['estudio_id'] ?? 0);
$pdo = db();
$consultaEstudio = $pdo->prepare('SELECT id, codigo, nombre FROM estudios WHERE id = :id');
$consultaEstudio->execute(['id' => $estudioId]);
$estudio = $consultaEstudio->fetch();
if (!$estudio) {
    http_response_code(404);
    exit('Estudio no encontrado.');
}
$consultaParametro = $pdo->prepare('SELECT * FROM parametros_estudios WHERE estudio_id = :estudio_id ORDER BY orden, id');
$consultaParametro->execute(['estudio_id' => $estudioId]);
$parametros = $consultaParametro->fetchAll();
$parametros[] = ['nombre_seccion' => '', 'nombre' => '', 'minimo' => '', 'maximo' => '', 'texto_referencia' => '', 'descripcion' => '', 'rango_min' => '', 'rango_max' => ''];
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parametros = [];
    foreach ((array) ($_POST['parametro'] ?? []) as $parametro) {
        $parametro = array_map(static fn ($valor) => trim((string) $valor), (array) $parametro);
        if (($parametro['nombre'] ?? '') !== '') {
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
    try {
        $pdo->beginTransaction();
        $delete = $pdo->prepare('DELETE FROM parametros_estudios WHERE estudio_id = :estudio_id');
        $delete->execute(['estudio_id' => $estudioId]);
        $save = $pdo->prepare(
            'INSERT INTO parametros_estudios (estudio_id, nombre_seccion, nombre, minimo, maximo, texto_referencia, descripcion, rango_min, rango_max, orden)
             VALUES (:estudio_id, :nombre_seccion, :nombre, :minimo, :maximo, :texto_referencia, :descripcion, :rango_min, :rango_max, :orden)'
        );
        foreach ($parametros as $posicion => $parametro) {
            $save->execute([
                'estudio_id' => $estudioId,
                'nombre_seccion' => $parametro['nombre_seccion'] !== '' ? $parametro['nombre_seccion'] : null,
                'nombre' => $parametro['nombre'],
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
        flash('Parámetros del estudio guardados correctamente.');
        header('Location: parametros.php');
        exit;
    } catch (PDOException $excepcion) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'No se pudieron guardar los parámetros.';
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Parámetros de <?= e($estudio['nombre']) ?> | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/barra_lateral.php'; ?><main class="container"><p><a href="parametros.php">← Volver a parámetros</a></p><h1>Parámetros de <?= e($estudio['nombre']) ?></h1><p class="muted">Estudio <?= e($estudio['codigo']) ?>. Todos los parámetros cargados aquí forman su informe.</p><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><form method="post" class="module parametro-editor"><input type="hidden" name="estudio_id" value="<?= $estudioId ?>"><div id="parametro-list"><?php foreach ($parametros as $posicion => $parametro): ?><div class="parametro-row"><input name="parametro[<?= $posicion ?>][nombre_seccion]" placeholder="Sección" value="<?= e($parametro['nombre_seccion'] ?? '') ?>"><input name="parametro[<?= $posicion ?>][nombre]" placeholder="Parámetro *" value="<?= e($parametro['nombre'] ?? '') ?>"><input name="parametro[<?= $posicion ?>][minimo]" placeholder="Mínimo" value="<?= e($parametro['minimo'] ?? '') ?>"><input name="parametro[<?= $posicion ?>][maximo]" placeholder="Máximo" value="<?= e($parametro['maximo'] ?? '') ?>"><input name="parametro[<?= $posicion ?>][texto_referencia]" placeholder="Referencia" value="<?= e($parametro['texto_referencia'] ?? '') ?>"><input name="parametro[<?= $posicion ?>][descripcion]" placeholder="Descripción (ej. sexo o condición)" value="<?= e($parametro['descripcion'] ?? '') ?>"><input name="parametro[<?= $posicion ?>][rango_min]" placeholder="Rango desde" value="<?= e($parametro['rango_min'] ?? '') ?>"><input name="parametro[<?= $posicion ?>][rango_max]" placeholder="Rango hasta" value="<?= e($parametro['rango_max'] ?? '') ?>"><button type="button" class="remove-parametro">Quitar</button></div><?php endforeach; ?></div><button type="button" class="button-small add-parametro" id="add-parametro">+ Agregar parámetro</button><div class="parametro-actions"><button type="submit">Guardar parámetros del estudio</button></div></form></main><script>const list=document.getElementById('parametro-list');let position=list.children.length;document.getElementById('add-parametro').addEventListener('click',function(){const row=document.createElement('div');row.className='parametro-row';row.innerHTML='<input name="parametro['+position+'][nombre_seccion]" placeholder="Sección"><input name="parametro['+position+'][nombre]" placeholder="Parámetro *"><input name="parametro['+position+'][minimo]" placeholder="Mínimo"><input name="parametro['+position+'][maximo]" placeholder="Máximo"><input name="parametro['+position+'][texto_referencia]" placeholder="Referencia"><input name="parametro['+position+'][descripcion]" placeholder="Descripción (ej. sexo o condición)"><input name="parametro['+position+'][rango_min]" placeholder="Rango desde"><input name="parametro['+position+'][rango_max]" placeholder="Rango hasta"><button type="button" class="remove-parametro">Quitar</button>';list.appendChild(row);position++;});document.addEventListener('click',function(event){if(event.target.classList.contains('remove-parametro')&&list.children.length>1){event.target.parentElement.remove();}});</script></body></html>



