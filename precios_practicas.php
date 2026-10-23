<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('practicas.gestionar');

$precios = conexion_bd()->query('SELECT * FROM precio_practica ORDER BY codigo')->fetchAll();
$mensaje = mensaje_flash();
$tituloPagina = 'Precios de prácticas | CEBAC';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Precios de prácticas</h1>
        <p class="text-muted mb-0">Catálogo de precios de las prácticas.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="abrirModalPrecio(0)">
        <i class="bi bi-plus-lg me-1"></i>Nuevo precio
    </button>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success"><?= escapar_html($mensaje) ?></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th class="text-end">Precio</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($precios as $precio): ?>
                    <tr>
                        <td><span class="badge bg-secondary"><?= (int) $precio['codigo'] ?></span></td>
                        <td class="text-end"><strong>$ <?= number_format((float) $precio['precio'], 2, ',', '.') ?></strong></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirModalPrecio(<?= (int) $precio['codigo'] ?>)">
                                <i class="bi bi-pencil"></i> Editar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$precios): ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        No hay precios registrados.
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL PRECIO -->
<div class="modal fade modal-ventana" id="modalPrecio" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-titulo">
                    <i class="bi bi-tag text-primary"></i>
                    <span id="modalPrecioTitulo">Nuevo precio</span>
                </h5>
                <div class="modal-botones">
                    <button type="button" class="modal-accion minimizar" title="Minimizar"><i class="bi bi-dash-lg"></i></button>
                    <button type="button" class="modal-accion maximizar" title="Maximizar"><i class="bi bi-fullscreen"></i></button>
                    <button type="button" class="modal-accion cerrar" title="Cerrar"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="modal-body" id="modalPrecioBody">
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
                <button type="button" class="btn btn-primary" id="btnGuardarPrecio">
                    <i class="bi bi-check-lg me-1"></i>Guardar precio
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function abrirModalPrecio(id) {
    const modal = document.getElementById('modalPrecio');
    const body = document.getElementById('modalPrecioBody');
    const titulo = document.getElementById('modalPrecioTitulo');
    const btnGuardar = document.getElementById('btnGuardarPrecio');

    titulo.textContent = id > 0 ? 'Editar precio' : 'Nuevo precio';
    body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';

    fetch('precio_practica_formulario.php?_modal=1' + (id > 0 ? '&id=' + id : ''))
        .then(r => r.text())
        .then(html => {
            body.innerHTML = html;

            const form = document.getElementById('precio-modal-form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const formData = new FormData(form);
                    formData.append('_modal', '1');

                    btnGuardar.disabled = true;
                    btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

                    fetch('precio_practica_formulario.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        btnGuardar.disabled = false;
                        btnGuardar.innerHTML = '<i class="bi bi-check-lg me-1"></i>Guardar precio';

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
                        btnGuardar.innerHTML = '<i class="bi bi-check-lg me-1"></i>Guardar precio';
                    });
                });
            }
        });

    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

document.getElementById('btnGuardarPrecio').addEventListener('click', function () {
    const form = document.getElementById('precio-modal-form');
    if (form) {
        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>