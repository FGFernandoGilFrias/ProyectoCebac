<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('pacientes.gestionar');

$id = (int) ($_GET['id'] ?? 0);
$volverAOrden = (($_GET['volver'] ?? '') === 'orden');
$dniPrecargado = trim((string) ($_GET['dni'] ?? ''));

$paciente = [
    'apellido' => '',
    'nombre' => '',
    'dni' => '',
    'fecha_nacimiento' => '',
    'direccion' => '',
    'email' => '',
    'telefono' => '',
    'fecha_alta' => date('Y-m-d'),
];

$credencialSeleccionada = 0;

if ($id > 0) {
    $consulta = conexion_bd()->prepare('SELECT * FROM pacientes WHERE codigo = :codigo');
    $consulta->execute(['codigo' => $id]);
    $fila = $consulta->fetch();
    if ($fila) {
        $paciente = $fila;
    }

    $stmtPos = conexion_bd()->prepare('SELECT credencial_codigo FROM paciente_obra_social WHERE paciente_codigo = :pc');
    $stmtPos->execute(['pc' => $id]);
    $credencialSeleccionada = (int) ($stmtPos->fetchColumn() ?: 0);
} elseif ($dniPrecargado !== '') {
    $paciente['dni'] = $dniPrecargado;
}

// Traer obras sociales con sus credenciales (agrupadas)
$obrasSociales = conexion_bd()->query('SELECT codigo, nombre FROM obras_sociales WHERE fecha_baja IS NULL ORDER BY nombre')->fetchAll();

// Traer todas las credenciales agrupadas por obra
$credencialesPorObra = [];
$stmtCred = conexion_bd()->query('SELECT codigo, obra_social_codigo, nro_credencial, cobertura, porcentaje_cobertura FROM obras_sociales_credenciales ORDER BY nro_credencial');
foreach ($stmtCred->fetchAll() as $c) {
    $credencialesPorObra[(int) $c['obra_social_codigo']][] = $c;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($paciente) as $key) {
        if (array_key_exists($key, $_POST)) {
            $paciente[$key] = trim((string) $_POST[$key]);
        }
    }
    $credencialSeleccionada = (int) ($_POST['credencial_codigo'] ?? 0);
    $volverAOrden = (($_POST['volver'] ?? '') === 'orden');

    if ($paciente['dni'] === '' || $paciente['apellido'] === '' || $paciente['nombre'] === '') {
        $error = 'DNI, apellido y nombre son obligatorios.';
    } else {
        try {
            $paciente['fecha_nacimiento'] = $paciente['fecha_nacimiento'] !== '' ? $paciente['fecha_nacimiento'] : null;
            $paciente['fecha_alta'] = $paciente['fecha_alta'] !== '' ? $paciente['fecha_alta'] : date('Y-m-d');

            $conexion = conexion_bd();
            $conexion->beginTransaction();

            $nuevoId = $id;

            if ($id > 0) {
                $consulta = $conexion->prepare('UPDATE pacientes SET apellido=:apellido, nombre=:nombre, dni=:dni, fecha_nacimiento=:fecha_nacimiento, direccion=:direccion, email=:email, telefono=:telefono, fecha_alta=:fecha_alta WHERE codigo=:codigo');
                $consulta->execute([
                    'apellido' => $paciente['apellido'],
                    'nombre' => $paciente['nombre'],
                    'dni' => $paciente['dni'],
                    'fecha_nacimiento' => $paciente['fecha_nacimiento'],
                    'direccion' => $paciente['direccion'] !== '' ? $paciente['direccion'] : null,
                    'email' => $paciente['email'] !== '' ? $paciente['email'] : null,
                    'telefono' => $paciente['telefono'] !== '' ? $paciente['telefono'] : null,
                    'fecha_alta' => $paciente['fecha_alta'],
                    'codigo' => $id,
                ]);
                mensaje_flash('Paciente actualizado correctamente.');
            } else {
                $consulta = $conexion->prepare('INSERT INTO pacientes (apellido, nombre, dni, fecha_nacimiento, direccion, email, telefono, fecha_alta) VALUES (:apellido, :nombre, :dni, :fecha_nacimiento, :direccion, :email, :telefono, :fecha_alta)');
                $consulta->execute([
                    'apellido' => $paciente['apellido'],
                    'nombre' => $paciente['nombre'],
                    'dni' => $paciente['dni'],
                    'fecha_nacimiento' => $paciente['fecha_nacimiento'],
                    'direccion' => $paciente['direccion'] !== '' ? $paciente['direccion'] : null,
                    'email' => $paciente['email'] !== '' ? $paciente['email'] : null,
                    'telefono' => $paciente['telefono'] !== '' ? $paciente['telefono'] : null,
                    'fecha_alta' => $paciente['fecha_alta'],
                ]);
                $nuevoId = (int) $conexion->lastInsertId();
                mensaje_flash('Paciente registrado correctamente.');
            }

            // Manejar la relación con la credencial
            if ($credencialSeleccionada > 0) {
                $stmtCheck = $conexion->prepare('SELECT COUNT(*) FROM paciente_obra_social WHERE paciente_codigo = :pc');
                $stmtCheck->execute(['pc' => $nuevoId]);
                $existe = (int) $stmtCheck->fetchColumn() > 0;

                if ($existe) {
                    $stmtUpd = $conexion->prepare('UPDATE paciente_obra_social SET credencial_codigo = :cred WHERE paciente_codigo = :pc');
                    $stmtUpd->execute(['cred' => $credencialSeleccionada, 'pc' => $nuevoId]);
                } else {
                    $stmtIns = $conexion->prepare('INSERT INTO paciente_obra_social (paciente_codigo, credencial_codigo) VALUES (:pc, :cred)');
                    $stmtIns->execute(['pc' => $nuevoId, 'cred' => $credencialSeleccionada]);
                }
            } else {
                $stmtDel = $conexion->prepare('DELETE FROM paciente_obra_social WHERE paciente_codigo = :pc');
                $stmtDel->execute(['pc' => $nuevoId]);
            }

            $conexion->commit();

            if ($volverAOrden) {
                header('Location: orden_formulario.php?paciente_id=' . $nuevoId);
            } else {
                header('Location: pacientes.php');
            }
            exit;
        } catch (PDOException $exception) {
            if (isset($conexion) && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            if ($exception->getCode() === '23000') {
                $error = str_contains($exception->getMessage(), 'dni') ? 'Ya existe un paciente con ese DNI.' : 'Los datos ya existen o no son válidos.';
            } else {
                $error = 'No se pudo guardar el paciente.';
            }
        }
    }
}

// Datos de credenciales para el JS (para autocompletar cobertura al elegir credencial)
$credencialesPorCodigo = [];
foreach ($credencialesPorObra as $osCodigo => $creds) {
    foreach ($creds as $c) {
        $credencialesPorCodigo[(int) $c['codigo']] = [
            'obra_social_codigo' => (int) $c['obra_social_codigo'],
            'nro_credencial' => $c['nro_credencial'],
            'cobertura' => $c['cobertura'],
            'porcentaje_cobertura' => (float) $c['porcentaje_cobertura'],
        ];
    }
}

$tituloPagina = ($id ? 'Editar' : 'Nuevo') . ' paciente | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container narrow">
<?php if ($volverAOrden): ?>
    <p><a href="orden_formulario.php">← Volver a la orden</a></p>
<?php else: ?>
    <p><a href="pacientes.php">← Volver a pacientes</a></p>
<?php endif; ?>

<h1><?= $id ? 'Editar paciente' : 'Nuevo paciente' ?></h1>

<?php if ($volverAOrden && !$id): ?>
    <div class="alert warning-alert">Estás creando un paciente nuevo desde la orden médica. Al guardar, volverás a la orden.</div>
<?php endif; ?>

<?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?>

<form method="post" class="form-grid">
    <?php if ($volverAOrden): ?>
        <input type="hidden" name="volver" value="orden">
    <?php endif; ?>

    <div><label>DNI *</label><input name="dni" required value="<?= escapar_html($paciente['dni']) ?>"></div>
    <div><label>Apellido *</label><input name="apellido" required value="<?= escapar_html($paciente['apellido']) ?>"></div>
    <div><label>Nombre *</label><input name="nombre" required value="<?= escapar_html($paciente['nombre']) ?>"></div>
    <div><label>Fecha de nacimiento</label><input type="date" name="fecha_nacimiento" value="<?= escapar_html($paciente['fecha_nacimiento'] ?? '') ?>"></div>
    <div class="full"><label>Domicilio</label><input name="direccion" value="<?= escapar_html($paciente['direccion'] ?? '') ?>"></div>
    <div><label>Email</label><input type="email" name="email" value="<?= escapar_html($paciente['email'] ?? '') ?>"></div>
    <div><label>Teléfono</label><input name="telefono" value="<?= escapar_html($paciente['telefono'] ?? '') ?>"></div>
    <div><label>Fecha de alta</label><input type="date" name="fecha_alta" value="<?= escapar_html($paciente['fecha_alta']) ?>"></div>

    <div class="full">
        <div class="obra-social-box">
            <h3>Cobertura de obra social</h3>
            <div class="obra-social-grid">
                <div>
                    <label for="obra_social_codigo">Obra social</label>
                    <select id="obra_social_codigo">
                        <option value="">Particular (sin obra social)</option>
                        <?php foreach ($obrasSociales as $os): ?>
                            <option value="<?= (int) $os['codigo'] ?>"><?= escapar_html($os['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="credencial_codigo">Credencial</label>
                    <select name="credencial_codigo" id="credencial_codigo">
                        <option value="">—</option>
                    </select>
                </div>
                <div>
                    <label>Cobertura (autocompletada)</label>
                    <input id="cobertura_display" readonly value="—">
                </div>
                <div>
                    <label>Porcentaje (autocompletado)</label>
                    <input id="porcentaje_display" readonly value="—">
                </div>
            </div>
            <small class="muted">La cobertura se determina automáticamente según la credencial elegida.</small>
        </div>
    </div>

    <div class="full actions"><button type="submit">Guardar paciente</button></div>
</form>
</main>

<script>
(function () {
    'use strict';

    const credencialesPorObra = <?= json_encode($credencialesPorObra, JSON_UNESCAPED_UNICODE) ?>;
    const credencialesPorCodigo = <?= json_encode($credencialesPorCodigo, JSON_UNESCAPED_UNICODE) ?>;
    const credencialSeleccionadaInicial = <?= (int) $credencialSeleccionada ?>;

    const selectObra = document.getElementById('obra_social_codigo');
    const selectCred = document.getElementById('credencial_codigo');
    const inputCobertura = document.getElementById('cobertura_display');
    const inputPorcentaje = document.getElementById('porcentaje_display');

    function cargarCredenciales() {
        const obraCodigo = selectObra.value;

        selectCred.innerHTML = '<option value="">—</option>';

        if (!obraCodigo || !credencialesPorObra[obraCodigo]) {
            inputCobertura.value = '—';
            inputPorcentaje.value = '—';
            return;
        }

        credencialesPorObra[obraCodigo].forEach(function (c) {
            const opt = document.createElement('option');
            opt.value = c.codigo;
            opt.textContent = c.nro_credencial + ' — ' + c.cobertura + ' (' + c.porcentaje_cobertura + '%)';
            selectCred.appendChild(opt);
        });
    }

    function mostrarCobertura() {
        const credCodigo = selectCred.value;
        if (!credCodigo || !credencialesPorCodigo[credCodigo]) {
            inputCobertura.value = '—';
            inputPorcentaje.value = '—';
            return;
        }
        const c = credencialesPorCodigo[credCodigo];
        inputCobertura.value = c.cobertura;
        inputPorcentaje.value = c.porcentaje_cobertura + '%';
    }

    selectObra.addEventListener('change', function () {
        cargarCredenciales();
        mostrarCobertura();
    });

    selectCred.addEventListener('change', mostrarCobertura);

    // Inicializar: si estamos editando, precargar
    if (credencialSeleccionadaInicial > 0 && credencialesPorCodigo[credencialSeleccionadaInicial]) {
        const cred = credencialesPorCodigo[credencialSeleccionadaInicial];
        selectObra.value = cred.obra_social_codigo;
        cargarCredenciales();
        selectCred.value = credencialSeleccionadaInicial;
        mostrarCobertura();
    }
})();
</script>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>