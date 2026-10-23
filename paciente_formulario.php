<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('pacientes.gestionar');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$volverAOrden = (($_GET['volver'] ?? '') === 'orden');
$dniPrecargado = trim((string) ($_GET['dni'] ?? ''));

$paciente = [
    'apellido' => '',
    'nombre' => '',
    'dni' => '',
    'fecha_nacimiento' => '',
    'direccion' => '',
    'correo' => '',
    'telefono' => '',
    'fecha_alta' => date('Y-m-d'),
];

$obraSocialCodigo = 0;
$nroCredencial = '';
$cobertura = 'Total';
$porcentajeCobertura = 100.00;

if ($id > 0) {
    $consulta = conexion_bd()->prepare('SELECT * FROM pacientes WHERE codigo = :codigo');
    $consulta->execute(['codigo' => $id]);
    $fila = $consulta->fetch();
    if ($fila) {
        $paciente = $fila;
    }

    $stmtPos = conexion_bd()->prepare(
        'SELECT obra_social_codigo, nro_credencial, cobertura, porcentaje_cobertura
         FROM paciente_obra_social WHERE paciente_codigo = :pc'
    );
    $stmtPos->execute(['pc' => $id]);
    $pos = $stmtPos->fetch();
    if ($pos) {
        $obraSocialCodigo    = (int) $pos['obra_social_codigo'];
        $nroCredencial       = (string) $pos['nro_credencial'];
        $cobertura           = (string) $pos['cobertura'];
        $porcentajeCobertura = (float) $pos['porcentaje_cobertura'];
    }
} elseif ($dniPrecargado !== '') {
    $paciente['dni'] = $dniPrecargado;
}

$obrasSociales = conexion_bd()
    ->query('SELECT codigo, nombre FROM obras_sociales WHERE fecha_baja IS NULL ORDER BY nombre')
    ->fetchAll();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);

    foreach (array_keys($paciente) as $key) {
        if (array_key_exists($key, $_POST)) {
            $paciente[$key] = trim((string) $_POST[$key]);
        }
    }

    $obraSocialCodigo    = (int) ($_POST['obra_social_codigo'] ?? 0);
    $nroCredencial       = trim((string) ($_POST['nro_credencial'] ?? ''));
    $cobertura           = (string) ($_POST['cobertura'] ?? 'Total');
    $porcentajeCobertura = (float) ($_POST['porcentaje_cobertura'] ?? 100);

    if (!in_array($cobertura, ['Total', 'Parcial', 'Sin Cobertura'], true)) {
        $cobertura = 'Total';
    }
    if ($cobertura === 'Total') {
        $porcentajeCobertura = 100.00;
    } elseif ($cobertura === 'Sin Cobertura') {
        $porcentajeCobertura = 0.00;
    } else {
        if ($porcentajeCobertura < 0) $porcentajeCobertura = 0;
        if ($porcentajeCobertura > 100) $porcentajeCobertura = 100;
    }

    if ($paciente['dni'] === '' || $paciente['apellido'] === '' || $paciente['nombre'] === '') {
        $error = 'DNI, apellido y nombre son obligatorios.';
    } else {
        $dniLimpio = trim($paciente['dni']);

        if ($id > 0) {
            $stmtCheck = conexion_bd()->prepare(
                'SELECT codigo FROM pacientes WHERE dni = :dni AND codigo <> :codigo LIMIT 1'
            );
            $stmtCheck->execute(['dni' => $dniLimpio, 'codigo' => $id]);
        } else {
            $stmtCheck = conexion_bd()->prepare(
                'SELECT codigo FROM pacientes WHERE dni = :dni LIMIT 1'
            );
            $stmtCheck->execute(['dni' => $dniLimpio]);
        }

        $existente = $stmtCheck->fetch();

        if ($existente) {
            $error = 'Ya existe un paciente registrado con el DNI ' . escapar_html($dniLimpio) . '.';
        } else {
            try {
                $paciente['fecha_nacimiento'] = $paciente['fecha_nacimiento'] !== '' ? $paciente['fecha_nacimiento'] : null;
                $paciente['fecha_alta'] = $paciente['fecha_alta'] !== '' ? $paciente['fecha_alta'] : date('Y-m-d');

                $conexion = conexion_bd();
                $conexion->beginTransaction();

                $nuevoId = $id;

                if ($id > 0) {
                    $consulta = $conexion->prepare(
                        'UPDATE pacientes SET apellido=:apellido, nombre=:nombre, dni=:dni,
                            fecha_nacimiento=:fecha_nacimiento, direccion=:direccion,
                            correo=:correo, telefono=:telefono, fecha_alta=:fecha_alta
                         WHERE codigo=:codigo'
                    );
                    $consulta->execute([
                        'apellido' => $paciente['apellido'],
                        'nombre' => $paciente['nombre'],
                        'dni' => $paciente['dni'],
                        'fecha_nacimiento' => $paciente['fecha_nacimiento'],
                        'direccion' => $paciente['direccion'] !== '' ? $paciente['direccion'] : null,
                        'correo' => $paciente['correo'] !== '' ? $paciente['correo'] : null,
                        'telefono' => $paciente['telefono'] !== '' ? $paciente['telefono'] : null,
                        'fecha_alta' => $paciente['fecha_alta'],
                        'codigo' => $id,
                    ]);
                    mensaje_flash('Paciente actualizado correctamente.');
                } else {
                    $consulta = $conexion->prepare(
                        'INSERT INTO pacientes (apellido, nombre, dni, fecha_nacimiento, direccion, correo, telefono, fecha_alta)
                         VALUES (:apellido, :nombre, :dni, :fecha_nacimiento, :direccion, :correo, :telefono, :fecha_alta)'
                    );
                    $consulta->execute([
                        'apellido' => $paciente['apellido'],
                        'nombre' => $paciente['nombre'],
                        'dni' => $paciente['dni'],
                        'fecha_nacimiento' => $paciente['fecha_nacimiento'],
                        'direccion' => $paciente['direccion'] !== '' ? $paciente['direccion'] : null,
                        'correo' => $paciente['correo'] !== '' ? $paciente['correo'] : null,
                        'telefono' => $paciente['telefono'] !== '' ? $paciente['telefono'] : null,
                        'fecha_alta' => $paciente['fecha_alta'],
                    ]);
                    $nuevoId = (int) $conexion->lastInsertId();
                    mensaje_flash('Paciente registrado correctamente.');
                }

                if ($obraSocialCodigo > 0) {
                    $stmtCheck2 = $conexion->prepare('SELECT COUNT(*) FROM paciente_obra_social WHERE paciente_codigo = :pc');
                    $stmtCheck2->execute(['pc' => $nuevoId]);
                    $existe = (int) $stmtCheck2->fetchColumn() > 0;

                    if ($existe) {
                        $stmtUpd = $conexion->prepare(
                            'UPDATE paciente_obra_social
                             SET obra_social_codigo = :os, nro_credencial = :nro,
                                 cobertura = :cob, porcentaje_cobertura = :porc
                             WHERE paciente_codigo = :pc'
                        );
                        $stmtUpd->execute([
                            'os' => $obraSocialCodigo,
                            'nro' => $nroCredencial,
                            'cob' => $cobertura,
                            'porc' => $porcentajeCobertura,
                            'pc' => $nuevoId,
                        ]);
                    } else {
                        $stmtIns = $conexion->prepare(
                            'INSERT INTO paciente_obra_social
                             (paciente_codigo, obra_social_codigo, nro_credencial, cobertura, porcentaje_cobertura)
                             VALUES (:pc, :os, :nro, :cob, :porc)'
                        );
                        $stmtIns->execute([
                            'pc' => $nuevoId,
                            'os' => $obraSocialCodigo,
                            'nro' => $nroCredencial,
                            'cob' => $cobertura,
                            'porc' => $porcentajeCobertura,
                        ]);
                    }
                } else {
                    $stmtDel = $conexion->prepare('DELETE FROM paciente_obra_social WHERE paciente_codigo = :pc');
                    $stmtDel->execute(['pc' => $nuevoId]);
                }

                $conexion->commit();

                if (isset($_POST['_modal']) && $_POST['_modal'] === '1') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'ok' => true,
                        'mensaje' => $id > 0 ? 'Paciente actualizado correctamente.' : 'Paciente registrado correctamente.',
                        'id' => $nuevoId,
                    ]);
                    exit;
                }

                if ($volverAOrden) {
                    header('Location: ordenes.php?nuevo_paciente=' . $nuevoId);
                } else {
                    header('Location: pacientes.php');
                }
                exit;
            } catch (PDOException $exception) {
                if (isset($conexion) && $conexion->inTransaction()) {
                    $conexion->rollBack();
                }
                if ($exception->getCode() === '23000') {
                    $error = 'Ya existe un paciente con ese DNI.';
                } else {
                    $error = 'No se pudo guardar el paciente: ' . $exception->getMessage();
                }
            }
        }
    }

    if (isset($_POST['_modal']) && $_POST['_modal'] === '1') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $error]);
        exit;
    }
}

$esModal = isset($_GET['_modal']) && $_GET['_modal'] === '1';

function renderFormularioPaciente($paciente, $obrasSociales, $obraSocialCodigo, $nroCredencial, $cobertura, $porcentajeCobertura, $error, $esModal, $id, $volverAOrden) {
    ?>
    <form method="post" id="paciente-modal-form">
        <?php if ($esModal): ?>
            <input type="hidden" name="_modal" value="1">
        <?php endif; ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <?php if ($volverAOrden): ?>
            <input type="hidden" name="volver" value="orden">
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= escapar_html($error) ?></div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">DNI *</label>
                <input type="text" name="dni" class="form-control" required value="<?= escapar_html($paciente['dni']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Apellido *</label>
                <input type="text" name="apellido" class="form-control" required value="<?= escapar_html($paciente['apellido']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Nombre *</label>
                <input type="text" name="nombre" class="form-control" required value="<?= escapar_html($paciente['nombre']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Fecha de nacimiento</label>
                <input type="date" name="fecha_nacimiento" class="form-control" value="<?= escapar_html($paciente['fecha_nacimiento'] ?? '') ?>">
            </div>
            <div class="col-md-8">
                <label class="form-label">Domicilio</label>
                <input type="text" name="direccion" class="form-control" value="<?= escapar_html($paciente['direccion'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="correo" class="form-control" value="<?= escapar_html($paciente['correo'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Teléfono</label>
                <input type="text" name="telefono" class="form-control" value="<?= escapar_html($paciente['telefono'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Fecha de alta</label>
                <input type="date" name="fecha_alta" class="form-control" value="<?= escapar_html($paciente['fecha_alta']) ?>">
            </div>

            <div class="col-12"><hr></div>

            <div class="col-md-6">
                <label class="form-label">Obra social</label>
                <select name="obra_social_codigo" id="obra_social_codigo" class="form-select">
                    <option value="">Particular (sin obra social)</option>
                    <?php foreach ($obrasSociales as $os): ?>
                        <option value="<?= (int) $os['codigo'] ?>" <?= $obraSocialCodigo === (int) $os['codigo'] ? 'selected' : '' ?>>
                            <?= escapar_html($os['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Nro. de credencial</label>
                <input type="text" name="nro_credencial" class="form-control" value="<?= escapar_html($nroCredencial) ?>" maxlength="30" placeholder="Ej: 12345">
            </div>
            <div class="col-md-6">
                <label class="form-label">Cobertura</label>
                <select name="cobertura" id="select_cobertura" class="form-select">
                    <option value="Total" <?= $cobertura === 'Total' ? 'selected' : '' ?>>Total (100%)</option>
                    <option value="Parcial" <?= $cobertura === 'Parcial' ? 'selected' : '' ?>>Parcial</option>
                    <option value="Sin Cobertura" <?= $cobertura === 'Sin Cobertura' ? 'selected' : '' ?>>Sin Cobertura (0%)</option>
                </select>
            </div>
            <div class="col-md-6" id="bloque_porcentaje">
                <label class="form-label">Porcentaje (%)</label>
                <input type="number" min="0" max="100" step="0.01" name="porcentaje_cobertura" id="input_porcentaje" class="form-control" value="<?= escapar_html((string) $porcentajeCobertura) ?>">
            </div>
        </div>

        <?php if (!$esModal): ?>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <a href="<?= $volverAOrden ? 'ordenes.php' : 'pacientes.php' ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i>Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Guardar paciente
                </button>
            </div>
        <?php endif; ?>
    </form>

    <script>
    (function () {
        'use strict';
        var select = document.getElementById('select_cobertura');
        var input = document.getElementById('input_porcentaje');
        var bloque = document.getElementById('bloque_porcentaje');
        var selectObra = document.getElementById('obra_social_codigo');

        if (!select || !input || !bloque || !selectObra) return;

        function actualizar() {
            var tipo = select.value;
            var sinObra = selectObra.value === '';
            if (sinObra) {
                bloque.style.opacity = '0.5';
                input.readOnly = true;
                return;
            }
            if (tipo === 'Total') {
                input.value = 100;
                input.readOnly = true;
                bloque.style.opacity = '0.7';
            } else if (tipo === 'Sin Cobertura') {
                input.value = 0;
                input.readOnly = true;
                bloque.style.opacity = '0.7';
            } else {
                input.readOnly = false;
                bloque.style.opacity = '1';
            }
        }

        select.addEventListener('change', actualizar);
        selectObra.addEventListener('change', actualizar);
        actualizar();
    })();
    </script>
    <?php
}

if ($esModal) {
    renderFormularioPaciente($paciente, $obrasSociales, $obraSocialCodigo, $nroCredencial, $cobertura, $porcentajeCobertura, $error, true, $id, $volverAOrden);
    exit;
}

$tituloPagina = ($id ? 'Editar' : 'Nuevo') . ' paciente | CEBAC';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1"><?= $id ? 'Editar paciente' : 'Nuevo paciente' ?></h1>
        <p class="text-muted mb-0">Completá los datos personales y de cobertura.</p>
    </div>
    <a href="<?= $volverAOrden ? 'ordenes.php' : 'pacientes.php' ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?php if ($volverAOrden && !$id): ?>
    <div class="alert alert-warning">
        <i class="bi bi-info-circle me-1"></i>
        Estás creando un paciente nuevo desde la orden médica. Al guardar, volverás al listado de órdenes.
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <?php renderFormularioPaciente($paciente, $obrasSociales, $obraSocialCodigo, $nroCredencial, $cobertura, $porcentajeCobertura, $error, false, $id, $volverAOrden); ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>