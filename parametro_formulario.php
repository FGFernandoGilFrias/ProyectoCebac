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
$parametros[] = ['nombre_seccion' => '', 'nombre' => '', 'unidad' => '', 'minimo' => '', 'maximo' => '', 'texto_referencia' => '', 'descripcion' => '', 'rango_min' => '', 'rango_max' => ''];

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parametros = [];
    foreach ((array) ($_POST['parameter'] ?? []) as $parameter) {
        $parameter = array_map(static fn ($value) => trim((string) $value), (array) $parameter);
        if (($parameter['nombre'] ?? '') !== '') {
            $parametros[] = [
                'nombre_seccion' => $parameter['nombre_seccion'] ?? '',
                'nombre' => $parameter['nombre'],
                'unidad' => $parameter['unidad'] ?? '',
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
            'INSERT INTO parametros_estudios (estudio_id, nombre_seccion, nombre, unidad, minimo, maximo, texto_referencia, descripcion, rango_min, rango_max, orden)
             VALUES (:estudio_id, :nombre_seccion, :nombre, :unidad, :minimo, :maximo, :texto_referencia, :descripcion, :rango_min, :rango_max, :orden)'
        );
        foreach ($parametros as $position => $parameter) {
            $save->execute([
                'estudio_id' => $estudioId,
                'nombre_seccion' => $parameter['nombre_seccion'] !== '' ? $parameter['nombre_seccion'] : null,
                'nombre' => $parameter['nombre'],
                'unidad' => $parameter['unidad'] !== '' ? $parameter['unidad'] : null,
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

$tituloPagina = 'Parámetros de ' . $estudio['nombre'] . ' | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container">
<p><a href="parametros.php">← Volver a parámetros</a></p>

<div class="page-heading">
    <div>
        <span class="eyebrow">Informe</span>
        <h1>Parámetros de <?= escapar_html($estudio['nombre']) ?></h1>
        <p class="muted">Estudio <?= escapar_html($estudio['codigo']) ?>. Todos los parámetros cargados aquí forman su informe.</p>
    </div>
</div>

<?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?>

<form method="post" class="estudio-form">
    <input type="hidden" name="estudio_id" value="<?= $estudioId ?>">

    <section class="module form-section">
        <div class="form-section-heading">
            <div>
                <span class="eyebrow">Parámetros</span>
                <h2>Parámetros del informe</h2>
                <p class="muted">Cada parámetro forma una fila del informe final. Podés agruparlos por sección.</p>
            </div>
        </div>

        <div class="params-table-wrap">
            <table class="params-table">
                <thead>
                    <tr>
                        <th style="width: 14%">Sección</th>
                        <th style="width: 20%">Parámetro *</th>
                        <th style="width: 10%">Unidad</th>
                        <th style="width: 9%">Mín.</th>
                        <th style="width: 9%">Máx.</th>
                        <th style="width: 16%">Referencia</th>
                        <th style="width: 15%">Descripción</th>
                        <th style="width: 7%" class="col-actions"></th>
                    </tr>
                </thead>
                <tbody id="parameter-list">
                    <?php foreach ($parametros as $position => $parameter): ?>
                        <tr class="param-row">
                            <td><input name="parameter[<?= $position ?>][nombre_seccion]" placeholder="Ej: PROTEINAS" value="<?= escapar_html($parameter['nombre_seccion'] ?? '') ?>"></td>
                            <td><input name="parameter[<?= $position ?>][nombre]" placeholder="Ej: GLUCOSA" value="<?= escapar_html($parameter['nombre'] ?? '') ?>"></td>
                            <td><input name="parameter[<?= $position ?>][unidad]" placeholder="Ej: mg/dl" value="<?= escapar_html($parameter['unidad'] ?? '') ?>"></td>
                            <td><input name="parameter[<?= $position ?>][minimo]" placeholder="0" value="<?= escapar_html($parameter['minimo'] ?? '') ?>"></td>
                            <td><input name="parameter[<?= $position ?>][maximo]" placeholder="100" value="<?= escapar_html($parameter['maximo'] ?? '') ?>"></td>
                            <td><input name="parameter[<?= $position ?>][texto_referencia]" placeholder="Ej: 70 - 110" value="<?= escapar_html($parameter['texto_referencia'] ?? '') ?>"></td>
                            <td><input name="parameter[<?= $position ?>][descripcion]" placeholder="Ej: en ayunas" value="<?= escapar_html($parameter['descripcion'] ?? '') ?>"></td>
                            <td class="col-actions">
                                <button type="button" class="icon-button remove-parameter" title="Eliminar parámetro">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                                        <path d="M10 11v6"></path>
                                        <path d="M14 11v6"></path>
                                        <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <button type="button" class="button-add-row" id="add-parameter">
            <span>+</span> Agregar parámetro
        </button>
    </section>

    <div class="form-actions-bar">
        <a class="button-link secondary-button" href="parametros.php">Cancelar</a>
        <button type="submit">Guardar parámetros del estudio</button>
    </div>
</form>
</main>

<script>
(function () {
    'use strict';

    const list = document.getElementById('parameter-list');
    const addBtn = document.getElementById('add-parameter');
    if (!list || !addBtn) return;

    let position = list.children.length;

    addBtn.addEventListener('click', function () {
        const tr = document.createElement('tr');
        tr.className = 'param-row';
        tr.innerHTML =
            '<td><input name="parameter[' + position + '][nombre_seccion]" placeholder="Ej: PROTEINAS"></td>' +
            '<td><input name="parameter[' + position + '][nombre]" placeholder="Ej: GLUCOSA"></td>' +
            '<td><input name="parameter[' + position + '][unidad]" placeholder="Ej: mg/dl"></td>' +
            '<td><input name="parameter[' + position + '][minimo]" placeholder="0"></td>' +
            '<td><input name="parameter[' + position + '][maximo]" placeholder="100"></td>' +
            '<td><input name="parameter[' + position + '][texto_referencia]" placeholder="Ej: 70 - 110"></td>' +
            '<td><input name="parameter[' + position + '][descripcion]" placeholder="Ej: en ayunas"></td>' +
            '<td class="col-actions"><button type="button" class="icon-button remove-parameter" title="Eliminar parámetro">' +
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
            '<polyline points="3 6 5 6 21 6"></polyline>' +
            '<path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>' +
            '<path d="M10 11v6"></path>' +
            '<path d="M14 11v6"></path>' +
            '<path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path>' +
            '</svg></button></td>';
        list.appendChild(tr);
        position++;
        tr.querySelector('input').focus();
    });

    document.addEventListener('click', function (event) {
        if (event.target.closest('.remove-parameter')) {
            const row = event.target.closest('.param-row');
            if (row && list.children.length > 1) {
                row.remove();
            } else if (row && list.children.length === 1) {
                row.querySelectorAll('input').forEach((i) => i.value = '');
            }
        }
    });
})();
</script>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>