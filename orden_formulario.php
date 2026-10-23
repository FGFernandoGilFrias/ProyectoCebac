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

$pacientes = conexion_bd()->query(
    'SELECT p.codigo, p.apellido, p.nombre, p.dni, p.correo,
            pos.obra_social_codigo, pos.nro_credencial, pos.cobertura, pos.porcentaje_cobertura,
            os.nombre AS obra_social_nombre
     FROM pacientes p
     LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
     LEFT JOIN obras_sociales os ON os.codigo = pos.obra_social_codigo
     ORDER BY p.apellido, p.nombre'
)->fetchAll();

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

    if (!$form['paciente_codigo'] || $form['fecha_orden'] === '' || $form['medico'] === '' || !$estudiosSeleccionados) {
        $error = 'Paciente, fecha, médico y al menos un estudio son obligatorios.';
    } else {
        $conexion = conexion_bd();
        try {
            $conexion->beginTransaction();

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
            $totalOrden = 0;
            $stmtPrecio = $conexion->prepare(
                'SELECT pp.precio
                 FROM estudios e
                 LEFT JOIN precio_practica pp ON pp.codigo = e.practica_codigo
                 WHERE e.codigo = :codigo'
            );
            foreach ($estudiosSeleccionados as $estudioCodigo) {
                $insEstudio->execute(['orden' => $id, 'estudio' => $estudioCodigo]);
                $stmtPrecio->execute(['codigo' => $estudioCodigo]);
                $totalOrden += (float) ($stmtPrecio->fetchColumn() ?: 0);
            }

            // Calcular total a cargo del paciente según cobertura
            $stmtPac = $conexion->prepare(
                'SELECT porcentaje_cobertura FROM paciente_obra_social WHERE paciente_codigo = :pc'
            );
            $stmtPac->execute(['pc' => $form['paciente_codigo']]);
            $porcentajeCob = (float) ($stmtPac->fetchColumn() ?: 0);
            $totalAPagar = $totalOrden * (1 - $porcentajeCob / 100);

            $upTotales = $conexion->prepare(
                'UPDATE ordenes SET total_adeudado = :total WHERE codigo = :codigo'
            );
            $upTotales->execute(['total' => $totalAPagar, 'codigo' => $id]);

            $conexion->commit();

            if (isset($_POST['_modal']) && $_POST['_modal'] === '1') {
                header('Content-Type: application/json');
                echo json_encode([
                    'ok' => true,
                    'mensaje' => $esEdicion ? 'Orden actualizada correctamente.' : 'Orden creada correctamente.',
                    'id' => $id,
                    'es_nueva' => !$esEdicion,
                ]);
                exit;
            }

            mensaje_flash($esEdicion ? 'Orden actualizada correctamente.' : 'Orden creada correctamente.');

            // ✅ Si es orden nueva, guardar el ID en sesión para abrir el PDF
            if (!$esEdicion) {
                $_SESSION['nueva_orden_instrucciones'] = $id;
            }

            header('Location: ordenes.php');
            exit;
        } catch (Throwable $e) {
            if ($conexion->inTransaction()) $conexion->rollBack();
            $error = 'No se pudo guardar la orden: ' . $e->getMessage();
        }
    }

    if (isset($_POST['_modal']) && $_POST['_modal'] === '1') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $error]);
        exit;
    }
}

$datosPacientes = [];
foreach ($pacientes as $p) {
    $datosPacientes[(int) $p['codigo']] = [
        'obra_social_codigo' => $p['obra_social_codigo'] ? (int) $p['obra_social_codigo'] : 0,
        'obra_social_nombre' => $p['obra_social_nombre'] ?? '',
        'nro_credencial' => $p['nro_credencial'] ?? '',
        'cobertura' => $p['cobertura'] ?? '',
        'porcentaje_cobertura' => (float) ($p['porcentaje_cobertura'] ?? 0),
    ];
}

$esModal = isset($_GET['_modal']) && $_GET['_modal'] === '1';

if ($esModal) {
    ?>
    <form method="post" id="orden-modal-form">
        <input type="hidden" name="_modal" value="1">
        <input type="hidden" name="orden_id" value="<?= (int) $id ?>">

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= escapar_html($error) ?></div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Buscar paciente</label>
                <input type="text" id="paciente_busqueda" class="form-control" placeholder="Escribí DNI, apellido o nombre..." autocomplete="off">
                <div id="paciente_resultados" class="paciente-resultados" hidden></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Paciente seleccionado *</label>
                <select name="paciente_codigo" id="paciente_codigo" class="form-select" required>
                    <option value="">Sin seleccionar</option>
                    <?php foreach ($pacientes as $p): ?>
                        <option value="<?= (int) $p['codigo'] ?>" <?= (string) $form['paciente_codigo'] === (string) $p['codigo'] ? 'selected' : '' ?>>
                            <?= escapar_html($p['apellido'] . ', ' . $p['nombre']) ?> - DNI <?= escapar_html($p['dni']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">¿No existe? <a href="paciente_formulario.php?volver=orden" target="_blank" rel="noopener">Crear paciente nuevo</a></small>
            </div>

            <div class="col-12">
                <div class="card bg-light border-0">
                    <div class="card-body py-2">
                        <p class="mb-2 small text-muted" id="link_editar_paciente" style="display:none;">
                            <a href="#" id="link_editar_paciente_anchor">✏️ Editar ficha del paciente (incluye obra social)</a>
                        </p>
                        <div class="row g-2">
                            <div class="col-md-3">
                                <small class="text-muted d-block">Obra social</small>
                                <strong id="obra_social_display">—</strong>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block">Credencial</small>
                                <strong id="nro_credencial_display">—</strong>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block">Cobertura</small>
                                <strong id="cobertura_display">—</strong>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block">Porcentaje</small>
                                <strong id="porcentaje_display">—</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label">Fecha de orden *</label>
                <input type="date" name="fecha_orden" class="form-control" required value="<?= escapar_html($form['fecha_orden']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Médico solicitante *</label>
                <input type="text" name="medico" class="form-control" required value="<?= escapar_html($form['medico']) ?>" placeholder="Nombre del profesional">
            </div>
            <div class="col-md-4">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-select">
                    <option value="Pendiente" <?= $form['estado'] === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="Validado" <?= $form['estado'] === 'Validado' ? 'selected' : '' ?>>Validado</option>
                    <option value="Finalizado" <?= $form['estado'] === 'Finalizado' ? 'selected' : '' ?>>Finalizado</option>
                </select>
            </div>

            <div class="col-12">
                <label class="form-label">Buscar estudio por código o nombre</label>
                <input type="text" id="estudio_busqueda" class="form-control" placeholder="Escribí código o nombre y presioná Enter..." autocomplete="off">
                <small class="text-muted">Si hay una sola coincidencia, presioná <strong>Enter</strong> para marcarla.</small>
            </div>

            <div class="col-12">
                <label class="form-label">Estudios solicitados *</label>
                <div class="study-picker" id="lista_estudios">
                    <?php foreach ($estudios as $e): ?>
                        <label class="study-option" data-codigo="<?= escapar_html($e['codigo']) ?>" data-precio="<?= escapar_html((string) ($e['precio'] ?? 0)) ?>" data-texto="<?= escapar_html(strtolower($e['codigo'] . ' ' . $e['estudio'])) ?>">
                            <input type="checkbox" name="estudios_ids[]" value="<?= escapar_html($e['codigo']) ?>" <?= in_array($e['codigo'], $estudiosSeleccionados, true) ? 'checked' : '' ?>>
                            <span>
                                <strong><?= escapar_html($e['codigo']) ?></strong> —
                                <?= escapar_html($e['estudio']) ?>
                                <?php if (!empty($e['parametros'])): ?><small class="text-muted">(<?= escapar_html($e['parametros']) ?>)</small><?php endif; ?>
                                — $ <?= number_format((float) ($e['precio'] ?? 0), 2, ',', '.') ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-12">
                <div class="card bg-light border-0">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between py-1">
                            <span>Total estudios:</span>
                            <strong id="resumen_total">$ 0,00</strong>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span>Cobertura obra (<span id="resumen_porcentaje">0</span>%):</span>
                            <strong id="resumen_cubre">$ 0,00</strong>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-top mt-1 pt-2">
                            <span class="fw-bold">Paciente paga:</span>
                            <strong id="resumen_paciente" class="text-primary fs-5">$ 0,00</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script>
    (function () {
        'use strict';

        var datosPacientes = <?= json_encode($datosPacientes, JSON_UNESCAPED_UNICODE) ?>;

        var inputPaciente = document.getElementById('paciente_busqueda');
        var selectPaciente = document.getElementById('paciente_codigo');
        var resultadosPaciente = document.getElementById('paciente_resultados');

        var inputObra = document.getElementById('obra_social_display');
        var inputCred = document.getElementById('nro_credencial_display');
        var inputCobertura = document.getElementById('cobertura_display');
        var inputPorcentaje = document.getElementById('porcentaje_display');

        var linkEditar = document.getElementById('link_editar_paciente');
        var linkEditarAnchor = document.getElementById('link_editar_paciente_anchor');

        function actualizarDatosPaciente() {
            var pacCodigo = selectPaciente.value;

            if (!pacCodigo || !datosPacientes[pacCodigo]) {
                inputObra.textContent = '—';
                inputCred.textContent = '—';
                inputCobertura.textContent = '—';
                inputPorcentaje.textContent = '—';
                linkEditar.style.display = 'none';
                calcularCobro();
                return;
            }

            var p = datosPacientes[pacCodigo];
            inputObra.textContent = p.obra_social_nombre || 'Particular';
            inputCred.textContent = p.nro_credencial || '—';
            inputCobertura.textContent = p.cobertura || '—';
            inputPorcentaje.textContent = p.porcentaje_cobertura ? p.porcentaje_cobertura + '%' : '—';

            linkEditarAnchor.href = 'paciente_formulario.php?id=' + pacCodigo;
            linkEditar.style.display = '';

            calcularCobro();
        }

        selectPaciente.addEventListener('change', actualizarDatosPaciente);

        function calcularCobro() {
            var seleccionados = document.querySelectorAll('input[name="estudios_ids[]"]:checked');
            var total = 0;
            seleccionados.forEach(function (cb) {
                var opcion = cb.closest('.study-option');
                total += Number(opcion.dataset.precio || 0);
            });

            var pacCodigo = selectPaciente.value;
            var porcentaje = 0;
            if (pacCodigo && datosPacientes[pacCodigo]) {
                porcentaje = Number(datosPacientes[pacCodigo].porcentaje_cobertura) || 0;
            }
            if (porcentaje < 0) porcentaje = 0;
            if (porcentaje > 100) porcentaje = 100;

            var cubre = total * (porcentaje / 100);
            var paciente = total - cubre;

            var formato = function (v) {
                return '$ ' + Number(v).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            };

            document.getElementById('resumen_total').textContent = formato(total);
            document.getElementById('resumen_porcentaje').textContent = porcentaje.toFixed(2).replace(/\.00$/, '');
            document.getElementById('resumen_cubre').textContent = formato(cubre);
            document.getElementById('resumen_paciente').textContent = formato(paciente);
        }

        document.querySelectorAll('input[name="estudios_ids[]"]').forEach(function (cb) {
            cb.addEventListener('change', calcularCobro);
        });

        function buscarPacientes() {
            var term = inputPaciente.value.toLowerCase().trim();
            if (term === '') {
                resultadosPaciente.hidden = true;
                resultadosPaciente.innerHTML = '';
                return;
            }
            var opciones = Array.from(selectPaciente.options).filter(function (o) { return o.value !== ''; });
            var matches = opciones.filter(function (o) { return o.text.toLowerCase().includes(term); });

            if (matches.length === 0) {
                resultadosPaciente.innerHTML = '<div class="paciente-no-encontrado p-2 bg-warning bg-opacity-10">No se encontró paciente con "<strong>' + inputPaciente.value + '</strong>".</div>';
                resultadosPaciente.hidden = false;
                return;
            }

            resultadosPaciente.innerHTML = '';
            matches.slice(0, 10).forEach(function (op) {
                var div = document.createElement('div');
                div.className = 'paciente-resultado p-2 border-bottom';
                div.style.cursor = 'pointer';
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
            if (inputPaciente && !inputPaciente.contains(e.target) && !resultadosPaciente.contains(e.target)) {
                resultadosPaciente.hidden = true;
            }
        });

        var inputEstudio = document.getElementById('estudio_busqueda');
        var listaEstudios = document.getElementById('lista_estudios');

        function filtrarEstudios(term) {
            listaEstudios.querySelectorAll('.study-option').forEach(function (opt) {
                var texto = opt.dataset.texto || '';
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

                var term = this.value.toLowerCase().trim();
                if (term === '') return;

                var visibles = Array.from(listaEstudios.querySelectorAll('.study-option')).filter(function (o) { return !o.hidden; });
                if (visibles.length === 0) return;

                var elegido = visibles.find(function (o) { return (o.dataset.codigo || '').toLowerCase() === term; });
                if (!elegido && visibles.length === 1) {
                    elegido = visibles[0];
                }

                if (elegido) {
                    var checkbox = elegido.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        checkbox.checked = true;
                        elegido.style.background = '#dcfce7';
                        setTimeout(function () { elegido.style.background = ''; }, 600);
                        this.value = '';
                        filtrarEstudios('');
                        calcularCobro();
                    }
                }
            });
        }

        actualizarDatosPaciente();
        calcularCobro();
    })();
    </script>
    <?php
    exit;
}

$tituloPagina = ($esEdicion ? 'Editar' : 'Nueva') . ' orden | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="main-content">
    <h1><?= $esEdicion ? 'Editar orden' : 'Nueva orden' ?></h1>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>