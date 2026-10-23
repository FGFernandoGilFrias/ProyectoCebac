<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('estudios.gestionar');

$search = trim((string) ($_GET['q'] ?? ''));
$consultaSql = 'SELECT e.*, p.precio
                FROM estudios e
                LEFT JOIN precio_practica p ON p.codigo = e.practica_codigo';
$params = [];
if ($search !== '') {
    $consultaSql .= ' WHERE e.codigo LIKE :q1 OR e.estudio LIKE :q2';
    $params['q1'] = '%' . $search . '%';
    $params['q2'] = '%' . $search . '%';
}
$consultaSql .= ' ORDER BY e.estudio LIMIT 100';
$consulta = conexion_bd()->prepare($consultaSql);
$consulta->execute($params);
$estudios = $consulta->fetchAll();
$mensaje = mensaje_flash();
$tituloPagina = 'Estudios | CEBAC';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Estudios</h1>
        <p class="text-muted mb-0">Catálogo de estudios del laboratorio.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="abrirModalEstudio('')">
        <i class="bi bi-plus-lg me-1"></i>Nuevo estudio
    </button>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success"><?= escapar_html($mensaje) ?></div>
<?php endif; ?>

<form method="get" class="row g-2 mb-4">
    <div class="col-md-8 col-lg-6">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" name="q" value="<?= escapar_html($search) ?>" placeholder="Buscar por código o nombre">
        </div>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary">Buscar</button>
        <?php if ($search !== ''): ?>
            <a href="estudios.php" class="btn btn-outline-secondary">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Estudio</th>
                    <th>Parámetros</th>
                    <th class="text-end">Precio</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($estudios as $estudio): ?>
                    <tr>
                        <td><span class="badge bg-secondary"><?= escapar_html($estudio['codigo']) ?></span></td>
                        <td><strong><?= escapar_html($estudio['estudio']) ?></strong></td>
                        <td><small><?= escapar_html($estudio['parametros'] ?? '—') ?></small></td>
                        <td class="text-end">$ <?= number_format((float) ($estudio['precio'] ?? 0), 2, ',', '.') ?></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirModalEstudio('<?= escapar_html($estudio['codigo']) ?>')">
                                <i class="bi bi-pencil"></i> Editar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$estudios): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        No hay estudios registrados.
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL ESTUDIO -->
<div class="modal fade modal-ventana" id="modalEstudio" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-titulo">
                    <i class="bi bi-journal-medical text-primary"></i>
                    <span id="modalEstudioTitulo">Nuevo estudio</span>
                </h5>
                <div class="modal-botones">
                    <button type="button" class="modal-accion minimizar" title="Minimizar"><i class="bi bi-dash-lg"></i></button>
                    <button type="button" class="modal-accion maximizar" title="Maximizar"><i class="bi bi-fullscreen"></i></button>
                    <button type="button" class="modal-accion cerrar" title="Cerrar"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="modal-body" id="modalEstudioBody">
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
                <button type="button" class="btn btn-primary" id="btnGuardarEstudio">
                    <i class="bi bi-check-lg me-1"></i>Guardar estudio
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function abrirModalEstudio(codigo) {
    const modal = document.getElementById('modalEstudio');
    const body = document.getElementById('modalEstudioBody');
    const titulo = document.getElementById('modalEstudioTitulo');
    const btnGuardar = document.getElementById('btnGuardarEstudio');

    titulo.textContent = codigo ? 'Editar estudio' : 'Nuevo estudio';
    body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';

    fetch('estudio_formulario.php?_modal=1' + (codigo ? '&id=' + encodeURIComponent(codigo) : ''))
        .then(r => r.text())
        .then(html => {
            body.innerHTML = html;

            const form = document.getElementById('estudio-modal-form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const formData = new FormData(form);
                    formData.append('_modal', '1');

                    btnGuardar.disabled = true;
                    btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

                    fetch('estudio_formulario.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        btnGuardar.disabled = false;
                        btnGuardar.innerHTML = '<i class="bi bi-check-lg me-1"></i>Guardar estudio';

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
                        btnGuardar.innerHTML = '<i class="bi bi-check-lg me-1"></i>Guardar estudio';
                    });
                });
            }
        });

    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

document.getElementById('btnGuardarEstudio').addEventListener('click', function () {
    const form = document.getElementById('estudio-modal-form');
    if (form) {
        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>