<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('obras_sociales.gestionar');

$search = trim((string) ($_GET['q'] ?? ''));
$consultaSql = 'SELECT s.codigo, s.nombre, s.cuit, s.condiciones_convenio,
                       s.fecha_alta, s.fecha_baja,
                       COUNT(DISTINCT pos.paciente_codigo) AS cantidad_pacientes
                FROM obras_sociales s
                LEFT JOIN paciente_obra_social pos ON pos.obra_social_codigo = s.codigo';
$params = [];
if ($search !== '') {
    $consultaSql .= ' WHERE s.nombre LIKE :q1 OR s.cuit LIKE :q2';
    $params['q1'] = '%' . $search . '%';
    $params['q2'] = '%' . $search . '%';
}
$consultaSql .= ' GROUP BY s.codigo ORDER BY s.fecha_baja ASC, s.nombre LIMIT 100';
$consulta = conexion_bd()->prepare($consultaSql);
$consulta->execute($params);
$obrasSociales = $consulta->fetchAll();
$mensaje = mensaje_flash();
$tituloPagina = 'Obras sociales | CEBAC';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Obras sociales</h1>
        <p class="text-muted mb-0">Convenios con obras sociales y prepagas.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="abrirModalObraSocial(0)">
        <i class="bi bi-plus-lg me-1"></i>Nueva obra social
    </button>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success"><?= escapar_html($mensaje) ?></div>
<?php endif; ?>

<form method="get" class="row g-2 mb-4">
    <div class="col-md-8 col-lg-6">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" name="q" value="<?= escapar_html($search) ?>" placeholder="Buscar por nombre o CUIT">
        </div>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary">Buscar</button>
        <?php if ($search !== ''): ?>
            <a href="obras_sociales.php" class="btn btn-outline-secondary">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>CUIT</th>
                    <th class="text-center">Pacientes</th>
                    <th>Fecha alta</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($obrasSociales as $os): ?>
                    <tr>
                        <td><span class="text-muted">#<?= (int) $os['codigo'] ?></span></td>
                        <td><strong><?= escapar_html($os['nombre']) ?></strong></td>
                        <td><?= escapar_html($os['cuit']) ?></td>
                        <td class="text-center"><span class="badge bg-secondary"><?= (int) $os['cantidad_pacientes'] ?></span></td>
                        <td><?= escapar_html($os['fecha_alta'] ?? '—') ?></td>
                        <td>
                            <?php if ($os['fecha_baja']): ?>
                                <span class="badge bg-danger">Dada de baja</span>
                                <small class="text-muted d-block"><?= escapar_html($os['fecha_baja']) ?></small>
                            <?php else: ?>
                                <span class="badge bg-success">Activa</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirModalObraSocial(<?= (int) $os['codigo'] ?>)">
                                <i class="bi bi-pencil"></i> Editar
                            </button>
                            <form method="post" action="obra_social_baja.php" class="d-inline">
                                <input type="hidden" name="id" value="<?= (int) $os['codigo'] ?>">
                                <input type="hidden" name="accion" value="<?= $os['fecha_baja'] ? 'reactivar' : 'baja' ?>">
                                <button type="submit" class="btn btn-sm btn-outline-<?= $os['fecha_baja'] ? 'success' : 'danger' ?>" data-confirm="¿<?= $os['fecha_baja'] ? 'Reactivar' : 'Dar de baja' ?> esta obra social?">
                                    <i class="bi bi-<?= $os['fecha_baja'] ? 'arrow-counterclockwise' : 'x-circle' ?>"></i>
                                    <?= $os['fecha_baja'] ? 'Reactivar' : 'Baja' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$obrasSociales): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        No hay obras sociales registradas.
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL OBRA SOCIAL -->
<div class="modal fade modal-ventana" id="modalObraSocial" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-titulo">
                    <i class="bi bi-shield-check text-primary"></i>
                    <span id="modalObraSocialTitulo">Nueva obra social</span>
                </h5>
                <div class="modal-botones">
                    <button type="button" class="modal-accion minimizar" title="Minimizar"><i class="bi bi-dash-lg"></i></button>
                    <button type="button" class="modal-accion maximizar" title="Maximizar"><i class="bi bi-fullscreen"></i></button>
                    <button type="button" class="modal-accion cerrar" title="Cerrar"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="modal-body" id="modalObraSocialBody">
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
                <button type="button" class="btn btn-primary" id="btnGuardarObraSocial">
                    <i class="bi bi-check-lg me-1"></i>Guardar obra social
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function abrirModalObraSocial(id) {
    const modal = document.getElementById('modalObraSocial');
    const body = document.getElementById('modalObraSocialBody');
    const titulo = document.getElementById('modalObraSocialTitulo');
    const btnGuardar = document.getElementById('btnGuardarObraSocial');

    titulo.textContent = id > 0 ? 'Editar obra social' : 'Nueva obra social';
    body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';

    fetch('obra_social_formulario.php?_modal=1' + (id > 0 ? '&id=' + id : ''))
        .then(r => r.text())
        .then(html => {
            body.innerHTML = html;

            const form = document.getElementById('obra_social-modal-form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const formData = new FormData(form);
                    formData.append('_modal', '1');

                    btnGuardar.disabled = true;
                    btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

                    fetch('obra_social_formulario.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        btnGuardar.disabled = false;
                        btnGuardar.innerHTML = '<i class="bi bi-check-lg me-1"></i>Guardar obra social';

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
                        btnGuardar.innerHTML = '<i class="bi bi-check-lg me-1"></i>Guardar obra social';
                    });
                });
            }
        });

    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

document.getElementById('btnGuardarObraSocial').addEventListener('click', function () {
    const form = document.getElementById('obra_social-modal-form');
    if (form) {
        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>