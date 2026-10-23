<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('usuarios.gestionar');

$roles = conexion_bd()->query(
    "SELECT r.codigo, r.nombre, r.descripcion, r.activo,
            COUNT(DISTINCT ur.usuario_codigo) AS cantidad_usuarios,
            COUNT(DISTINCT rp.privilegio_codigo) AS cantidad_privilegios
     FROM roles r
     LEFT JOIN usuarios_roles ur ON ur.rol_codigo = r.codigo
     LEFT JOIN roles_privilegios rp ON rp.rol_codigo = r.codigo
     GROUP BY r.codigo ORDER BY r.nombre"
)->fetchAll();
$mensaje = mensaje_flash();
$tituloPagina = 'Roles | CEBAC';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Roles y privilegios</h1>
        <p class="text-muted mb-0">Define a qué partes del sistema puede acceder cada rol.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="abrirModalRol(0)">
        <i class="bi bi-plus-lg me-1"></i>Nuevo rol
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
                    <th>Rol</th>
                    <th>Descripción</th>
                    <th class="text-center">Usuarios</th>
                    <th class="text-center">Privilegios</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roles as $rol): ?>
                    <tr>
                        <td><strong><?= escapar_html($rol['nombre']) ?></strong></td>
                        <td><?= escapar_html($rol['descripcion']) ?></td>
                        <td class="text-center"><span class="badge bg-secondary"><?= (int) $rol['cantidad_usuarios'] ?></span></td>
                        <td class="text-center"><span class="badge bg-info text-dark"><?= (int) $rol['cantidad_privilegios'] ?></span></td>
                        <td>
                            <span class="badge <?= $rol['activo'] ? 'bg-success' : 'bg-danger' ?>">
                                <?= $rol['activo'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirModalRol(<?= (int) $rol['codigo'] ?>)">
                                <i class="bi bi-gear"></i> Administrar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$roles): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        No hay roles registrados.
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL ROL -->
<div class="modal fade modal-ventana" id="modalRol" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-titulo">
                    <i class="bi bi-key text-primary"></i>
                    <span id="modalRolTitulo">Nuevo rol</span>
                </h5>
                <div class="modal-botones">
                    <button type="button" class="modal-accion minimizar" title="Minimizar"><i class="bi bi-dash-lg"></i></button>
                    <button type="button" class="modal-accion maximizar" title="Maximizar"><i class="bi bi-fullscreen"></i></button>
                    <button type="button" class="modal-accion cerrar" title="Cerrar"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="modal-body" id="modalRolBody">
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
                <button type="button" class="btn btn-primary" id="btnGuardarRol">
                    <i class="bi bi-check-lg me-1"></i>Guardar rol
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function abrirModalRol(id) {
    const modal = document.getElementById('modalRol');
    const body = document.getElementById('modalRolBody');
    const titulo = document.getElementById('modalRolTitulo');
    const btnGuardar = document.getElementById('btnGuardarRol');

    titulo.textContent = id > 0 ? 'Editar rol' : 'Nuevo rol';
    body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';

    fetch('rol_formulario.php?_modal=1' + (id > 0 ? '&id=' + id : ''))
        .then(r => r.text())
        .then(html => {
            body.innerHTML = html;

            const form = document.getElementById('rol-modal-form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const formData = new FormData(form);
                    formData.append('_modal', '1');

                    btnGuardar.disabled = true;
                    btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

                    fetch('rol_formulario.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        btnGuardar.disabled = false;
                        btnGuardar.innerHTML = '<i class="bi bi-check-lg me-1"></i>Guardar rol';

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
                        btnGuardar.innerHTML = '<i class="bi bi-check-lg me-1"></i>Guardar rol';
                    });
                });
            }
        });

    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

document.getElementById('btnGuardarRol').addEventListener('click', function () {
    const form = document.getElementById('rol-modal-form');
    if (form) {
        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>