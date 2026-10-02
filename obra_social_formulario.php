<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('obras_sociales.gestionar');

$id = (int) ($_GET['id'] ?? 0);
$esEdicion = $id > 0;

$obraSocial = [
    'nombre' => '',
    'cuit' => '',
    'condiciones_convenio' => '',
    'fecha_alta' => date('Y-m-d'),
    'fecha_baja' => null,
];

$credenciales = [];

if ($esEdicion) {
    $consulta = conexion_bd()->prepare('SELECT * FROM obras_sociales WHERE codigo = :codigo');
    $consulta->execute(['codigo' => $id]);
    $fila = $consulta->fetch();
    if ($fila) {
        $obraSocial = $fila;
    }

    // Traer credenciales de la obra
    $stmtCred = conexion_bd()->prepare('SELECT codigo, nro_credencial, cobertura, porcentaje_cobertura FROM obras_sociales_credenciales WHERE obra_social_codigo = :os ORDER BY nro_credencial');
    $stmtCred->execute(['os' => $id]);
    $credenciales = $stmtCred->fetchAll();
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $obraSocial['nombre'] = trim((string) ($_POST['nombre'] ?? ''));
    $obraSocial['cuit'] = trim((string) ($_POST['cuit'] ?? ''));
    $obraSocial['condiciones_convenio'] = trim((string) ($_POST['condiciones_convenio'] ?? ''));
    $obraSocial['fecha_alta'] = trim((string) ($_POST['fecha_alta'] ?? ''));
    $fechaBaja = trim((string) ($_POST['fecha_baja'] ?? ''));
    $obraSocial['fecha_baja'] = $fechaBaja !== '' ? $fechaBaja : null;

    // Credenciales enviadas desde el formulario
    $credencialesPost = $_POST['credenciales'] ?? [];

    if ($obraSocial['nombre'] === '' || $obraSocial['cuit'] === '') {
        $error = 'Nombre y CUIT son obligatorios.';
    } else {
        try {
            $conexion = conexion_bd();
            $conexion->beginTransaction();

            if ($esEdicion) {
                $consulta = $conexion->prepare('UPDATE obras_sociales SET nombre=:nombre, cuit=:cuit, condiciones_convenio=:condiciones, fecha_alta=:fecha_alta, fecha_baja=:fecha_baja WHERE codigo=:codigo');
                $consulta->execute([
                    'nombre' => $obraSocial['nombre'],
                    'cuit' => $obraSocial['cuit'],
                    'condiciones' => $obraSocial['condiciones_convenio'] !== '' ? $obraSocial['condiciones_convenio'] : null,
                    'fecha_alta' => $obraSocial['fecha_alta'] !== '' ? $obraSocial['fecha_alta'] : null,
                    'fecha_baja' => $obraSocial['fecha_baja'],
                    'codigo' => $id,
                ]);
            } else {
                $consulta = $conexion->prepare('INSERT INTO obras_sociales (nombre, cuit, condiciones_convenio, fecha_alta) VALUES (:nombre, :cuit, :condiciones, :fecha_alta)');
                $consulta->execute([
                    'nombre' => $obraSocial['nombre'],
                    'cuit' => $obraSocial['cuit'],
                    'condiciones' => $obraSocial['condiciones_convenio'] !== '' ? $obraSocial['condiciones_convenio'] : null,
                    'fecha_alta' => $obraSocial['fecha_alta'] !== '' ? $obraSocial['fecha_alta'] : date('Y-m-d'),
                ]);
                $id = (int) $conexion->lastInsertId();
                mensaje_flash('Obra social registrada correctamente.');
            }

            // Procesar credenciales (actualizar/insertar)
            $credencialesProcesadas = [];

            foreach ($credencialesPost as $cred) {
                $nro = trim((string) ($cred['nro_credencial'] ?? ''));
                $cob = (string) ($cred['cobertura'] ?? 'Total');
                $porc = (float) ($cred['porcentaje_cobertura'] ?? 100);
                $credCodigo = (int) ($cred['codigo'] ?? 0);

                if ($nro === '') continue;

                if (!in_array($cob, ['Total', 'Parcial', 'Sin Cobertura'], true)) {
                    $cob = 'Total';
                }
                if ($porc < 0) $porc = 0;
                if ($porc > 100) $porc = 100;

                if ($credCodigo > 0) {
                    $stmtCred = $conexion->prepare('UPDATE obras_sociales_credenciales SET nro_credencial=:nro, cobertura=:cob, porcentaje_cobertura=:porc WHERE codigo=:codigo AND obra_social_codigo=:os');
                    $stmtCred->execute([
                        'nro' => $nro,
                        'cob' => $cob,
                        'porc' => $porc,
                        'codigo' => $credCodigo,
                        'os' => $id,
                    ]);
                    $credencialesProcesadas[] = $credCodigo;
                } else {
                    $stmtCred = $conexion->prepare('INSERT INTO obras_sociales_credenciales (obra_social_codigo, nro_credencial, cobertura, porcentaje_cobertura) VALUES (:os, :nro, :cob, :porc)');
                    $stmtCred->execute([
                        'os' => $id,
                        'nro' => $nro,
                        'cob' => $cob,
                        'porc' => $porc,
                    ]);
                    $credencialesProcesadas[] = (int) $conexion->lastInsertId();
                }
            }

            // Eliminar credenciales que ya no están
            if (!empty($credencialesProcesadas)) {
                $placeholders = implode(',', array_fill(0, count($credencialesProcesadas), '?'));
                $stmtDel = $conexion->prepare("DELETE FROM obras_sociales_credenciales WHERE obra_social_codigo = ? AND codigo NOT IN ($placeholders)");
                $stmtDel->execute(array_merge([$id], $credencialesProcesadas));
            } else {
                // Si no hay credenciales, borrar todas las de esta obra
                $stmtDel = $conexion->prepare('DELETE FROM obras_sociales_credenciales WHERE obra_social_codigo = :os');
                $stmtDel->execute(['os' => $id]);
            }

            $conexion->commit();
            if ($esEdicion) {
                mensaje_flash('Obra social actualizada correctamente.');
            }
            header('Location: obras_sociales.php');
            exit;
        } catch (PDOException $exception) {
            if ($conexion->inTransaction()) $conexion->rollBack();
            if ($exception->getCode() === '23000') {
                $error = 'Ya existe una obra social con ese CUIT o una credencial duplicada.';
            } else {
                $error = 'No se pudo guardar la obra social.';
            }
        }
    }
}

$tituloPagina = ($esEdicion ? 'Editar' : 'Nueva') . ' obra social | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container narrow">
<p><a href="obras_sociales.php">← Volver a obras sociales</a></p>
<h1><?= $esEdicion ? 'Editar obra social' : 'Nueva obra social' ?></h1>

<?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?>

<form method="post" class="form-grid">
    <div class="full">
        <label>Nombre *</label>
        <input name="nombre" required value="<?= escapar_html($obraSocial['nombre']) ?>" placeholder="Ej: CONSALUD">
    </div>
    <div class="full">
        <label>CUIT *</label>
        <input name="cuit" required value="<?= escapar_html($obraSocial['cuit']) ?>" placeholder="Ej: 33-707043569-9">
    </div>
    <div class="full">
        <label>Condiciones del convenio</label>
        <textarea name="condiciones_convenio" rows="3" placeholder="Ej: Convenio general con cobertura según plan del paciente"><?= escapar_html($obraSocial['condiciones_convenio'] ?? '') ?></textarea>
    </div>

    <div>
        <label>Fecha de alta</label>
        <input type="date" name="fecha_alta" value="<?= escapar_html($obraSocial['fecha_alta'] ?? '') ?>">
    </div>
    <div>
        <label>Fecha de baja</label>
        <input type="date" name="fecha_baja" value="<?= escapar_html($obraSocial['fecha_baja'] ?? '') ?>">
        <small class="muted">Dejar vacío si está activa.</small>
    </div>

    <!-- CREDENCIALES -->
    <div class="full">
        <div class="credenciales-box">
            <h3>Credenciales y cobertura</h3>
            <p class="muted">Cada credencial tiene un nivel de cobertura. Al asignar la obra a un paciente, se elige una de estas credenciales y la cobertura se aplica automáticamente.</p>

            <table class="credenciales-table">
                <thead>
                    <tr>
                        <th style="width: 35%">Nro. credencial</th>
                        <th style="width: 30%">Cobertura</th>
                        <th style="width: 25%">Porcentaje (%)</th>
                        <th style="width: 10%"></th>
                    </tr>
                </thead>
                <tbody id="lista_credenciales">
                    <?php if (empty($credenciales)): ?>
                        <tr class="cred-row">
                            <td><input name="credenciales[0][nro_credencial]" placeholder="Ej: 12345" maxlength="30"></td>
                            <td>
                                <select name="credenciales[0][cobertura]" class="select-cobertura">
                                    <option value="Total">Total</option>
                                    <option value="Parcial">Parcial</option>
                                    <option value="Sin Cobertura">Sin Cobertura</option>
                                </select>
                            </td>
                            <td><input type="number" min="0" max="100" step="0.01" name="credenciales[0][porcentaje_cobertura]" value="100" class="input-porcentaje"></td>
                            <td><button type="button" class="icon-button remove-cred" title="Eliminar">×</button></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($credenciales as $i => $cred): ?>
                            <tr class="cred-row">
                                <td>
                                    <input type="hidden" name="credenciales[<?= $i ?>][codigo]" value="<?= (int) $cred['codigo'] ?>">
                                    <input name="credenciales[<?= $i ?>][nro_credencial]" value="<?= escapar_html($cred['nro_credencial']) ?>" maxlength="30">
                                </td>
                                <td>
                                    <select name="credenciales[<?= $i ?>][cobertura]" class="select-cobertura">
                                        <option value="Total" <?= $cred['cobertura'] === 'Total' ? 'selected' : '' ?>>Total</option>
                                        <option value="Parcial" <?= $cred['cobertura'] === 'Parcial' ? 'selected' : '' ?>>Parcial</option>
                                        <option value="Sin Cobertura" <?= $cred['cobertura'] === 'Sin Cobertura' ? 'selected' : '' ?>>Sin Cobertura</option>
                                    </select>
                                </td>
                                <td><input type="number" min="0" max="100" step="0.01" name="credenciales[<?= $i ?>][porcentaje_cobertura]" value="<?= escapar_html((string) $cred['porcentaje_cobertura']) ?>" class="input-porcentaje"></td>
                                <td><button type="button" class="icon-button remove-cred" title="Eliminar">×</button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <button type="button" class="button-add-row" id="add-credencial">
                <span>+</span> Agregar credencial
            </button>
        </div>
    </div>

    <div class="full actions"><button type="submit">Guardar obra social</button></div>
</form>
</main>

<script>
(function () {
    'use strict';

    const lista = document.getElementById('lista_credenciales');
    const addBtn = document.getElementById('add-credencial');
    let position = lista.children.length;

    addBtn.addEventListener('click', function () {
        const tr = document.createElement('tr');
        tr.className = 'cred-row';
        tr.innerHTML =
            '<td><input name="credenciales[' + position + '][nro_credencial]" placeholder="Ej: 12345" maxlength="30"></td>' +
            '<td><select name="credenciales[' + position + '][cobertura]" class="select-cobertura">' +
                '<option value="Total">Total</option>' +
                '<option value="Parcial">Parcial</option>' +
                '<option value="Sin Cobertura">Sin Cobertura</option>' +
            '</select></td>' +
            '<td><input type="number" min="0" max="100" step="0.01" name="credenciales[' + position + '][porcentaje_cobertura]" value="100" class="input-porcentaje"></td>' +
            '<td><button type="button" class="icon-button remove-cred" title="Eliminar">×</button></td>';
        lista.appendChild(tr);
        position++;
    });

    // Eliminar credencial
    document.addEventListener('click', function (event) {
        if (event.target.classList.contains('remove-cred')) {
            const row = event.target.closest('.cred-row');
            if (row && lista.children.length > 1) {
                row.remove();
            }
        }
    });

    // Actualizar porcentaje según cobertura
    function actualizarPorcentajeFila(row) {
        const select = row.querySelector('.select-cobertura');
        const input = row.querySelector('.input-porcentaje');
        if (!select || !input) return;

        const tipo = select.value;
        if (tipo === 'Total') {
            input.value = 100;
            input.readOnly = true;
        } else if (tipo === 'Sin Cobertura') {
            input.value = 0;
            input.readOnly = true;
        } else {
            input.readOnly = false;
        }
    }

    document.addEventListener('change', function (event) {
        if (event.target.classList.contains('select-cobertura')) {
            actualizarPorcentajeFila(event.target.closest('.cred-row'));
        }
    });

    // Inicializar
    lista.querySelectorAll('.cred-row').forEach(actualizarPorcentajeFila);
})();
</script>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>