<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('pacientes.gestionar');

$search = trim((string) ($_GET['q'] ?? ''));
$consultaSql = 'SELECT p.codigo, p.apellido, p.nombre, p.dni, p.fecha_nacimiento, p.telefono,
                       os.nombre AS obra_social,
                       pos.nro_credencial, pos.cobertura, pos.porcentaje_cobertura
                FROM pacientes p
                LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
                LEFT JOIN obras_sociales os ON os.codigo = pos.obra_social_codigo';
$params = [];
if ($search !== '') {
    $consultaSql .= ' WHERE p.dni LIKE :q1 OR p.apellido LIKE :q2 OR p.nombre LIKE :q3';
    $params['q1'] = '%' . $search . '%';
    $params['q2'] = '%' . $search . '%';
    $params['q3'] = '%' . $search . '%';
}
$consultaSql .= ' ORDER BY p.apellido, p.nombre LIMIT 100';
$consulta = conexion_bd()->prepare($consultaSql);
$consulta->execute($params);
$pacientes = $consulta->fetchAll();
$mensaje = mensaje_flash();
$tituloPagina = 'Pacientes | CEBAC';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Pacientes</h1>
        <p class="text-muted mb-0">Registro y consulta de pacientes.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-primary" href="reporte_pacientes_listado.php?<?= http_build_query(['q' => $search]) ?>" target="_blank">
            <i class="bi bi-printer me-1"></i>Imprimir listado
        </a>
        <button type="button" class="btn btn-primary" onclick="abrirModalPaciente(0)">
            <i class="bi bi-plus-lg me-1"></i>Nuevo paciente
        </button>
    </div>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success"><?= escapar_html($mensaje) ?></div>
<?php endif; ?>

<form method="get" class="row g-2 mb-4">
    <div class="col-md-8 col-lg-6">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" name="q" value="<?= escapar_html($search) ?>" placeholder="Buscar por DNI, apellido o nombre">
        </div>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary">Buscar</button>
        <?php if ($search !== ''): ?>
            <a href="pacientes.php" class="btn btn-outline-secondary">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Paciente</th>
                    <th>DNI</th>
                    <th>Fecha nac.</th>
                    <th>Teléfono</th>
                    <th>Obra social</th>
                    <th>Credencial</th>
                    <th>Cobertura</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pacientes as $paciente): ?>
                    <tr>
                        <td><span class="text-muted">#<?= (int) $paciente['codigo'] ?></span></td>
                        <td><strong><?= escapar_html($paciente['apellido'] . ', ' . $paciente['nombre']) ?></strong></td>
                        <td><?= escapar_html($paciente['dni']) ?></td>
                        <td><?= escapar_html($paciente['fecha_nacimiento'] ?? '—') ?></td>
                        <td><?= escapar_html($paciente['telefono'] ?? '—') ?></td>
                        <td><?= escapar_html($paciente['obra_social'] ?? 'Particular') ?></td>
                        <td><?= escapar_html($paciente['nro_credencial'] ?? '—') ?></td>
                        <td>
                            <?php if ($paciente['cobertura']): ?>
                                <span class="badge bg-info text-dark"><?= escapar_html($paciente['cobertura']) ?></span>
                                <small class="text-muted d-block"><?= number_format((float) $paciente['porcentaje_cobertura'], 0, ',', '.') ?>%</small>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirModalPaciente(<?= (int) $paciente['codigo'] ?>)">
                                <i class="bi bi-pencil"></i> Editar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$pacientes): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            No hay pacientes registrados.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL PACIENTE -->
<div class="modal fade modal-ventana" id="modalPaciente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-titulo">
                    <i class="bi bi-person-fill text-primary"></i>
                    <span id="modalPacienteTitulo">Nuevo paciente</span>
                </h5>
                <div class="modal-botones">
                    <button type="button" class="modal-accion minimizar" title="Minimizar"><i class="bi bi-dash-lg"></i></button>
                    <button type="button" class="modal-accion maximizar" title="Maximizar"><i class="bi bi-fullscreen"></i></button>
                    <button type="button" class="modal-accion cerrar" title="Cerrar"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="modal-body" id="modalPacienteBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancelar
                </button>
                <button type="button" class="btn btn-primary" id="btnGuardarPaciente">
                    <i class="bi bi-check-lg me-1"></i>Guardar paciente
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function abrirModalPaciente(id) {
    const modal = document.getElementById('modalPaciente');
    const body = document.getElementById('modalPacienteBody');
    const titulo = document.getElementById('modalPacienteTitulo');
    const btnGuardar = document.getElementById('btnGuardarPaciente');

    titulo.textContent = id > 0 ? 'Editar paciente' : 'Nuevo paciente';
    body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';

    fetch('paciente_formulario.php?_modal=1' + (id > 0 ? '&id=' + id : ''))
        .then(r => r.text())
        .then(html => {
            body.innerHTML = html;

            if (typeof window.initModalVentana === 'function') {
                window.initModalVentana();
            }

            const form = document.getElementById('paciente-modal-form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const formData = new FormData(form);
                    formData.append('_modal', '1');

                    btnGuardar.disabled = true;
                    btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

                    fetch('paciente_formulario.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        btnGuardar.disabled = false;
                        btnGuardar.innerHTML = '<i class="bi bi-check-lg me-1"></i>Guardar paciente';

                        if (data.ok) {
                            bootstrap.Modal.getInstance(modal).hide();
                            setTimeout(() => window.location.reload(), 300);
                        } else {
                            let alerta = body.querySelector('.alert-danger');
                            if (!alerta) {
                                alerta = document.createElement('div');
                                alerta.className = 'alert alert-danger';
                                body.insertBefore(alerta, body.firstChild);
                            }
                            alerta.textContent = data.error;
                            body.scrollTop = 0;
                        }
                    })
                    .catch(() => {
                        btnGuardar.disabled = false;
                        btnGuardar.innerHTML = '<i class="bi bi-check-lg me-1"></i>Guardar paciente';
                    });
                });
            }
        });

    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

document.getElementById('btnGuardarPaciente').addEventListener('click', function () {
    const form = document.getElementById('paciente-modal-form');
    if (form) {
        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>