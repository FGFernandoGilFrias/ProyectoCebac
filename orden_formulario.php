<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('ordenes.gestionar');

$id = (int) ($_GET['id'] ?? $_POST['orden_id'] ?? 0);
$esEdicion = $id > 0;
$pacientePreSeleccionado = (int) ($_GET['paciente_id'] ?? 0);

$orden = null;
if ($esEdicion) {
    $stmt = conexion_bd()->prepare('SELECT * FROM ordenes WHERE codigo = :codigo');
    $stmt->execute(['codigo' => $id]);
    $orden = $stmt->fetch();
    if (!$orden) {
        http_response_code(404);
        exit('Orden no encontrada.');
    }
}

// Traer pacientes SIN obra social (se lee de paciente_obra_social)
$pacientes = conexion_bd()->query(
    'SELECT p.codigo, p.apellido, p.nombre, p.dni
     FROM pacientes p
     ORDER BY p.apellido, p.nombre'
)->fetchAll();

// Traer obras sociales activas
$obrasSociales = conexion_bd()->query(
    'SELECT codigo, nombre FROM obras_sociales WHERE fecha_baja IS NULL ORDER BY nombre'
)->fetchAll();

// Traer TODAS las credenciales agrupadas por obra
$credencialesPorObra = [];
$stmtCred = conexion_bd()->query(
    'SELECT codigo, obra_social_codigo, nro_credencial, cobertura, porcentaje_cobertura
     FROM obras_sociales_credenciales
     ORDER BY nro_credencial'
);
foreach ($stmtCred->fetchAll() as $c) {
    $credencialesPorObra[(int) $c['obra_social_codigo']][] = $c;
}

// Credencial actual del paciente (para autocompletar)
$credencialDelPaciente = [];
$stmtPos = conexion_bd()->query('SELECT paciente_codigo, credencial_codigo FROM paciente_obra_social');
foreach ($stmtPos->fetchAll() as $row) {
    $credencialDelPaciente[(int) $row['paciente_codigo']] = (int) $row['credencial_codigo'];
}

// Estudios
$estudios = conexion_bd()->query(
    'SELECT e.codigo, e.estudio, e.parametros, pp.precio
     FROM estudios e
     LEFT JOIN precio_practica pp ON pp.codigo = e.practica_codigo
     ORDER BY e.estudio'
)->fetchAll();

$estudiosSeleccionados = [];
if ($esEdicion) {
    $stmt = conexion_bd()->prepare('SELECT estudio_codigo FROM estudios_orden WHERE orden_codigo = :codigo');
    $stmt->execute(['codigo' => $id]);
    $estudiosSeleccionados = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$form = $orden
    ? [
        'paciente_codigo' => (int) $orden['paciente_codigo'],
        'fecha_orden' => $orden['fecha_orden'],
        'medico' => $orden['medico'],
        'estado' => $orden['estado'],
    ]
    : [
        'paciente_codigo' => $pacientePreSeleccionado,
        'fecha_orden' => date('Y-m-d'),
        'medico' => '',
        'estado' => 'Pendiente',
    ];

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['paciente_codigo'] = (int) ($_POST['paciente_codigo'] ?? 0);
    $form['fecha_orden'] = trim((string) ($_POST['fecha_orden'] ?? ''));
    $form['medico'] = trim((string) ($_POST['medico'] ?? ''));
    $form['estado'] = trim((string) ($_POST['estado'] ?? 'Pendiente'));
    $estudiosSeleccionados = array_values(array_unique($_POST['estudios_ids'] ?? []));

    // Credencial (puede cambiarla desde acá)
    $credencialCodigo = (int) ($_POST['credencial_codigo'] ?? 0);

    if (!$form['paciente_codigo'] || $form['fecha_orden'] === '' || $form['medico'] === '' || !$estudiosSeleccionados) {
        $error = 'Paciente, fecha, médico y al menos un estudio son obligatorios.';
    } else {
        $conexion = conexion_bd();
        try {
            $conexion->beginTransaction();

            // Actualizar la credencial del paciente si cambió
            if ($credencialCodigo > 0) {
                $stmtCheck = $conexion->prepare('SELECT COUNT(*) FROM paciente_obra_social WHERE paciente_codigo = :pc');
                $stmtCheck->execute(['pc' => $form['paciente_codigo']]);
                $existe = (int) $stmtCheck->fetchColumn() > 0;

                if ($existe) {
                    $stmtUpd = $conexion->prepare('UPDATE paciente_obra_social SET credencial_codigo = :cred WHERE paciente_codigo = :pc');
                    $stmtUpd->execute(['cred' => $credencialCodigo, 'pc' => $form['paciente_codigo']]);
                } else {
                    $stmtIns = $conexion->prepare('INSERT INTO paciente_obra_social (paciente_codigo, credencial_codigo) VALUES (:pc, :cred)');
                    $stmtIns->execute(['pc' => $form['paciente_codigo'], 'cred' => $credencialCodigo]);
                }
            }

            // Crear o actualizar la orden
            if ($esEdicion) {
                $up = $conexion->prepare('UPDATE ordenes SET paciente_codigo=:pac, fecha_orden=:fecha, medico=:medico, estado=:estado WHERE codigo=:codigo');
                $up->execute([
                    'pac' => $form['paciente_codigo'],
                    'fecha' => $form['fecha_orden'],
                    'medico' => $form['medico'],
                    'estado' => $form['estado'],
                    'codigo' => $id,
                ]);
                $conexion->prepare('DELETE FROM estudios_orden WHERE orden_codigo = :codigo')->execute(['codigo' => $id]);
            } else {
                $ins = $conexion->prepare('INSERT INTO ordenes (fecha_orden, medico, estado, paciente_codigo) VALUES (:fecha, :medico, :estado, :pac)');
                $ins->execute([
                    'fecha' => $form['fecha_orden'],
                    'medico' => $form['medico'],
                    'estado' => $form['estado'],
                    'pac' => $form['paciente_codigo'],
                ]);
                $id = (int) $conexion->lastInsertId();
            }

            $insEstudio = $conexion->prepare('INSERT INTO estudios_orden (orden_codigo, estudio_codigo) VALUES (:orden, :estudio)');
            foreach ($estudiosSeleccionados as $estudioCodigo) {
                $insEstudio->execute(['orden' => $id, 'estudio' => $estudioCodigo]);
            }

            $conexion->commit();
            mensaje_flash($esEdicion ? 'Orden actualizada correctamente.' : 'Orden creada correctamente.');
            header('Location: ordenes.php');
            exit;
        } catch (Throwable $e) {
            if ($conexion->inTransaction()) $conexion->rollBack();
            $error = 'No se pudo guardar la orden: ' . $e->getMessage();
        }
    }
}

// Datos de pacientes para el JS
$datosPacientes = [];
foreach ($pacientes as $p) {
    $datosPacientes[(int) $p['codigo']] = [
        'credencial_codigo' => $credencialDelPaciente[(int) $p['codigo']] ?? 0,
    ];
}

// Datos de credenciales para el JS
$datosCredenciales = [];
foreach ($credencialesPorObra as $osCodigo => $creds) {
    foreach ($creds as $c) {
        $datosCredenciales[(int) $c['codigo']] = [
            'obra_social_codigo' => (int) $c['obra_social_codigo'],
            'nro_credencial' => $c['nro_credencial'],
            'cobertura' => $c['cobertura'],
            'porcentaje_cobertura' => (float) $c['porcentaje_cobertura'],
        ];
    }
}

$tituloPagina = ($esEdicion ? 'Editar' : 'Nueva') . ' orden | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container narrow">
<p><a href="ordenes.php">← Volver a órdenes</a></p>
<h1><?= $esEdicion ? 'Editar orden' : 'Nueva orden' ?></h1>

<?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?>

<form method="post" class="form-grid">
    <input type="hidden" name="orden_id" value="<?= (int) $id ?>">

    <div class="full">
        <label for="paciente_busqueda">Buscar paciente *</label>
        <input type="text" id="paciente_busqueda" placeholder="Escribí DNI, apellido o nombre..." autocomplete="off">
        <div id="paciente_resultados" class="paciente-resultados" hidden></div>
    </div>

    <div class="full">
        <label for="paciente_codigo">Paciente seleccionado *</label>
        <select name="paciente_codigo" id="paciente_codigo" required>
            <option value="">Sin seleccionar</option>
            <?php foreach ($pacientes as $p): ?>
                <option value="<?= (int) $p['codigo'] ?>" <?= (string) $form['paciente_codigo'] === (string) $p['codigo'] ? 'selected' : '' ?>>
                    <?= escapar_html($p['apellido'] . ', ' . $p['nombre']) ?> - DNI <?= escapar_html($p['dni']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small class="muted">
            ¿No existe el paciente?
            <a href="paciente_formulario.php?volver=orden">Crear paciente nuevo</a>
        </small>
    </div>

    <!-- DATOS DE COBERTURA (EDITABLES SOLO OBRA Y CREDENCIAL) -->
    <div class="full">
        <div class="obra-social-box">
            <h3>Cobertura del paciente</h3>
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
                    <label>Cobertura</label>
                    <input id="cobertura_display" readonly value="—">
                </div>
                <div>
                    <label>Porcentaje</label>
                    <input id="porcentaje_display" readonly value="—">
                </div>
            </div>
            <small class="muted">Si cambiás la obra o la credencial, se actualiza también en la ficha del paciente.</small>
        </div>
    </div>

    <div><label>Fecha de orden *</label><input type="date" name="fecha_orden" required value="<?= escapar_html($form['fecha_orden']) ?>"></div>
    <div><label>Médico solicitante *</label><input name="medico" required value="<?= escapar_html($form['medico']) ?>" placeholder="Nombre del profesional"></div>

    <div class="full">
        <label>Estado</label>
        <select name="estado">
            <option value="Pendiente" <?= $form['estado'] === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
            <option value="Validado" <?= $form['estado'] === 'Validado' ? 'selected' : '' ?>>Validado</option>
            <option value="Finalizado" <?= $form['estado'] === 'Finalizado' ? 'selected' : '' ?>>Finalizado</option>
        </select>
    </div>

    <div class="full">
        <label for="estudio_busqueda">Buscar estudio por código o nombre</label>
        <input type="text" id="estudio_busqueda" placeholder="Escribí código o nombre y presioná Enter..." autocomplete="off">
        <small class="muted">Si hay una sola coincidencia, presioná <strong>Enter</strong> para marcarla.</small>
    </div>

    <div class="full">
        <label>Estudios solicitados *</label>
        <div class="study-picker" id="lista_estudios">
            <?php foreach ($estudios as $e): ?>
                <label class="study-option" data-codigo="<?= escapar_html($e['codigo']) ?>" data-precio="<?= escapar_html((string) ($e['precio'] ?? 0)) ?>" data-texto="<?= escapar_html(strtolower($e['codigo'] . ' ' . $e['estudio'])) ?>">
                    <input type="checkbox" name="estudios_ids[]" value="<?= escapar_html($e['codigo']) ?>" <?= in_array($e['codigo'], $estudiosSeleccionados, true) ? 'checked' : '' ?>>
                    <span>
                        <strong><?= escapar_html($e['codigo']) ?></strong> —
                        <?= escapar_html($e['estudio']) ?>
                        <?php if (!empty($e['parametros'])): ?><small class="muted">(<?= escapar_html($e['parametros']) ?>)</small><?php endif; ?>
                        — $ <?= number_format((float) ($e['precio'] ?? 0), 2, ',', '.') ?>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- RESUMEN DE COBRO -->
    <div class="full">
        <div class="resumen-cobro">
            <div class="resumen-row">
                <span>Total estudios:</span>
                <strong id="resumen_total">$ 0,00</strong>
            </div>
            <div class="resumen-row">
                <span>Cobertura obra (<span id="resumen_porcentaje">0</span>%):</span>
                <strong id="resumen_cubre">$ 0,00</strong>
            </div>
            <div class="resumen-row highlight">
                <span>Paciente paga:</span>
                <strong id="resumen_paciente">$ 0,00</strong>
            </div>
        </div>
    </div>

    <div class="full actions"><button type="submit"><?= $esEdicion ? 'Guardar cambios' : 'Crear orden' ?></button></div>
</form>
</main>

<script>
(function () {
    'use strict';

    const datosPacientes = <?= json_encode($datosPacientes, JSON_UNESCAPED_UNICODE) ?>;
    const credencialesPorObra = <?= json_encode($credencialesPorObra, JSON_UNESCAPED_UNICODE) ?>;
    const datosCredenciales = <?= json_encode($datosCredenciales, JSON_UNESCAPED_UNICODE) ?>;

    const inputPaciente = document.getElementById('paciente_busqueda');
    const selectPaciente = document.getElementById('paciente_codigo');
    const resultadosPaciente = document.getElementById('paciente_resultados');

    const selectObra = document.getElementById('obra_social_codigo');
    const selectCred = document.getElementById('credencial_codigo');
    const inputCobertura = document.getElementById('cobertura_display');
    const inputPorcentaje = document.getElementById('porcentaje_display');

    // ============================================
    // Actualizar credenciales al cambiar paciente
    // ============================================
    function actualizarDatosPaciente() {
        const pacCodigo = selectPaciente.value;
        if (!pacCodigo || !datosPacientes[pacCodigo]) {
            selectObra.value = '';
            selectCred.innerHTML = '<option value="">—</option>';
            inputCobertura.value = '—';
            inputPorcentaje.value = '—';
            calcularCobro();
            return;
        }
        const credCodigo = datosPacientes[pacCodigo].credencial_codigo || 0;
        if (credCodigo > 0 && datosCredenciales[credCodigo]) {
            const cred = datosCredenciales[credCodigo];
            selectObra.value = cred.obra_social_codigo;
            cargarCredencialesDeObra(cred.obra_social_codigo);
            selectCred.value = credCodigo;
            mostrarCoberturaDeCredencial(credCodigo);
        } else {
            selectObra.value = '';
            selectCred.innerHTML = '<option value="">—</option>';
            inputCobertura.value = '—';
            inputPorcentaje.value = '—';
        }
        calcularCobro();
    }

    // ============================================
    // Cargar credenciales de la obra seleccionada
    // ============================================
    function cargarCredencialesDeObra(obraCodigo) {
        selectCred.innerHTML = '<option value="">—</option>';
        if (!obraCodigo || !credencialesPorObra[obraCodigo]) return;
        credencialesPorObra[obraCodigo].forEach(function (c) {
            const opt = document.createElement('option');
            opt.value = c.codigo;
            opt.textContent = c.nro_credencial + ' — ' + c.cobertura + ' (' + c.porcentaje_cobertura + '%)';
            selectCred.appendChild(opt);
        });
    }

    function mostrarCoberturaDeCredencial(credCodigo) {
        if (!credCodigo || !datosCredenciales[credCodigo]) {
            inputCobertura.value = '—';
            inputPorcentaje.value = '—';
            return;
        }
        const c = datosCredenciales[credCodigo];
        inputCobertura.value = c.cobertura;
        inputPorcentaje.value = c.porcentaje_cobertura + '%';
    }

    selectObra.addEventListener('change', function () {
        cargarCredencialesDeObra(this.value);
        inputCobertura.value = '—';
        inputPorcentaje.value = '—';
        calcularCobro();
    });

    selectCred.addEventListener('change', function () {
        mostrarCoberturaDeCredencial(this.value);
        calcularCobro();
    });

    selectPaciente.addEventListener('change', actualizarDatosPaciente);

    // ============================================
    // Calcular cobro
    // ============================================
    function calcularCobro() {
        const seleccionados = document.querySelectorAll('input[name="estudios_ids[]"]:checked');
        let total = 0;
        seleccionados.forEach(cb => {
            const opcion = cb.closest('.study-option');
            total += Number(opcion.dataset.precio || 0);
        });

        // Leer porcentaje de la credencial seleccionada
        const credCodigo = selectCred.value;
        let porcentaje = 0;
        if (credCodigo && datosCredenciales[credCodigo]) {
            porcentaje = Number(datosCredenciales[credCodigo].porcentaje_cobertura) || 0;
        }
        if (porcentaje < 0) porcentaje = 0;
        if (porcentaje > 100) porcentaje = 100;

        const cubre = total * (porcentaje / 100);
        const paciente = total - cubre;

        const formato = v => '$ ' + Number(v).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        document.getElementById('resumen_total').textContent = formato(total);
        document.getElementById('resumen_porcentaje').textContent = porcentaje.toFixed(2).replace(/\.00$/, '');
        document.getElementById('resumen_cubre').textContent = formato(cubre);
        document.getElementById('resumen_paciente').textContent = formato(paciente);
    }

    document.querySelectorAll('input[name="estudios_ids[]"]').forEach(cb => {
        cb.addEventListener('change', calcularCobro);
    });

    // ============================================
    // Buscador de pacientes
    // ============================================
    function buscarPacientes() {
        const term = inputPaciente.value.toLowerCase().trim();
        if (term === '') {
            resultadosPaciente.hidden = true;
            resultadosPaciente.innerHTML = '';
            return;
        }
        const opciones = Array.from(selectPaciente.options).filter(o => o.value !== '');
        const matches = opciones.filter(o => o.text.toLowerCase().includes(term));

        if (matches.length === 0) {
            resultadosPaciente.innerHTML = '<div class="paciente-no-encontrado">No se encontró ningún paciente con "<strong>' + inputPaciente.value + '</strong>". <a href="paciente_formulario.php?volver=orden">Crear paciente nuevo</a></div>';
            resultadosPaciente.hidden = false;
            return;
        }

        resultadosPaciente.innerHTML = '';
        matches.slice(0, 10).forEach(op => {
            const div = document.createElement('div');
            div.className = 'paciente-resultado';
            div.textContent = op.text;
            div.onclick = function () {
                selectPaciente.value = op.value;
                inputPaciente.value = op.text;
                resultadosPaciente.hidden = true;
                actualizarDatosPaciente();
            };
            resultadosPaciente.appendChild(div);
        });
        resultadosPaciente.hidden = false;
    }

    if (inputPaciente) {
        inputPaciente.addEventListener('input', buscarPacientes);
    }

    document.addEventListener('click', function (e) {
        if (!inputPaciente.contains(e.target) && !resultadosPaciente.contains(e.target)) {
            resultadosPaciente.hidden = true;
        }
    });

    // ============================================
    // Buscador de estudios
    // ============================================
    const inputEstudio = document.getElementById('estudio_busqueda');
    const listaEstudios = document.getElementById('lista_estudios');

    function filtrarEstudios(term) {
        listaEstudios.querySelectorAll('.study-option').forEach(function (opt) {
            const texto = opt.dataset.texto || '';
            opt.hidden = term !== '' && !texto.includes(term);
        });
    }

    if (inputEstudio && listaEstudios) {
        inputEstudio.addEventListener('input', function () {
            filtrarEstudios(this.value.toLowerCase().trim());
        });

        inputEstudio.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();

            const term = this.value.toLowerCase().trim();
            if (term === '') return;

            const visibles = Array.from(listaEstudios.querySelectorAll('.study-option')).filter(o => !o.hidden);
            if (visibles.length === 0) return;

            let elegido = visibles.find(o => (o.dataset.codigo || '').toLowerCase() === term);
            if (!elegido && visibles.length === 1) {
                elegido = visibles[0];
            }

            if (elegido) {
                const checkbox = elegido.querySelector('input[type="checkbox"]');
                if (checkbox) {
                    checkbox.checked = true;
                    elegido.style.background = '#dcfce7';
                    setTimeout(() => elegido.style.background = '', 600);
                    this.value = '';
                    filtrarEstudios('');
                    calcularCobro();
                }
            }
        });
    }

    // Inicializar
    actualizarDatosPaciente();
    calcularCobro();
})();
</script>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>