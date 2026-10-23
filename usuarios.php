<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('usuarios.gestionar');

$conexion = conexion_bd();
$usuarios = $conexion->query(
    "SELECT u.codigo, u.nombre_usuario, u.nombre_completo, u.email, u.telefono, u.cargo, u.matricula,
            u.activo, u.ultimo_acceso,
            COALESCE(GROUP_CONCAT(DISTINCT r.nombre ORDER BY r.nombre SEPARATOR ', '), 'Sin rol') AS roles
     FROM usuarios u
     LEFT JOIN usuarios_roles ur ON ur.usuario_codigo = u.codigo
     LEFT JOIN roles r ON r.codigo = ur.rol_codigo
     GROUP BY u.codigo ORDER BY u.nombre_completo"
)->fetchAll();

$mensaje = mensaje_flash();

function iniciales_listado(string $nombre): string
{
    $partes = preg_split('/\s+/', trim($nombre));
    $a = mb_substr($partes[0] ?? '', 0, 1, 'UTF-8');
    $b = isset($partes[1]) ? mb_substr($partes[1], 0, 1, 'UTF-8') : '';
    return mb_strtoupper($a . $b, 'UTF-8');
}

$tituloPagina = 'Usuarios | CEBAC';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Usuarios y accesos</h1>
        <p class="text-muted mb-0">Administrá usuarios y los roles que determinan sus privilegios.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="abrirModalUsuario(0)">
        <i class="bi bi-person-plus me-1"></i>Nuevo usuario
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
                    <th></th>
                    <th>Usuario</th>
                    <th>Contacto</th>
                    <th>Roles</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td style="width: 60px;">
                            <div class="user-mini-avatar"><?= escapar_html(iniciales_listado($usuario['nombre_completo'])) ?></div>
                        </td>
                        <td>
                            <strong><?= escapar_html($usuario['nombre_completo']) ?></strong>
                            <small class="text-muted d-block">@<?= escapar_html($usuario['nombre_usuario']) ?></small>
                            <?php if (!empty($usuario['cargo'])): ?>
                                <small class="text-muted d-block"><?= escapar_html($usuario['cargo']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($usuario['email'])): ?>
                                <small><i class="bi bi-envelope me-1"></i><?= escapar_html($usuario['email']) ?></small><br>
                            <?php endif; ?>
                            <?php if (!empty($usuario['telefono'])): ?>
                                <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= escapar_html($usuario['telefono']) ?></small>
                            <?php endif; ?>
                            <?php if (empty($usuario['email']) && empty($usuario['telefono'])): ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-info text-dark"><?= escapar_html($usuario['roles']) ?></span></td>
                        <td>
                            <span class="badge <?= $usuario['activo'] ? 'bg-success' : 'bg-danger' ?>">
                                <?= $usuario['activo'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                            <?php if (!empty($usuario['ultimo_acceso'])): ?>
                                <small class="text-muted d-block"><?= escapar_html($usuario['ultimo_acceso']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirModalUsuario(<?= (int) $usuario['codigo'] ?>)">
                                <i class="bi bi-pencil"></i> Editar
                            </button>
                            <?php if ((int) $usuario['codigo'] !== (int) usuario_actual()['codigo']): ?>
                                <form method="post" action="usuario_estado.php" class="d-inline">
                                    <input type="hidden" name="id" value="<?= (int) $usuario['codigo'] ?>">
                                    <input type="hidden" name="activo" value="<?= $usuario['activo'] ? 0 : 1 ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-<?= $usuario['activo'] ? 'danger' : 'success' ?>" data-confirm="¿<?= $usuario['activo'] ? 'Desactivar' : 'Activar' ?> este usuario?">
                                        <i class="bi bi-<?= $usuario['activo'] ? 'person-x' : 'person-check' ?>"></i>
                                        <?= $usuario['activo'] ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$usuarios): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        No hay usuarios registrados.
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL USUARIO -->
<div class="modal fade modal-ventana" id="modalUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-titulo">
                    <i class="bi bi-person-badge text-primary"></i>
                    <span id="modalUsuarioTitulo">Nuevo usuario</span>
                </h5>
                <div class="modal-botones">
                    <button type="button" class="modal-accion minimizar" title="Minimizar"><i class="bi bi-dash-lg"></i></button>
                    <button type="button" class="modal-accion maximizar" title="Maximizar"><i class="bi bi-fullscreen"></i></button>
                    <button type="button" class="modal-accion cerrar" title="Cerrar"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="modal-body" id="modalUsuarioBody">
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
                <button type="button" class="btn btn-primary" id="btnGuardarUsuario">
                    <i class="bi bi-check-lg me-1"></i>Guardar usuario
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function abrirModalUsuario(id) {
    const modal = document.getElementById('modalUsuario');
    const body = document.getElementById('modalUsuarioBody');
    const titulo = document.getElementById('modalUsuarioTitulo');
    const btnGuardar = document.getElementById('btnGuardarUsuario');

    titulo.textContent = id > 0 ? 'Editar usuario' : 'Nuevo usuario';
    body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';

    fetch('usuario_formulario.php?_modal=1' + (id > 0 ? '&id=' + id : ''))
        .then(r => r.text())
        .then(html => {
            body.innerHTML = html;

            const form = document.getElementById('usuario-modal-form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const formData = new FormData(form);
                    formData.append('_modal', '1');

                    btnGuardar.disabled = true;
                    btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

                    fetch('usuario_formulario.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        btnGuardar.disabled = false;
                        btnGuardar.innerHTML = '<i class="bi bi-check-lg me-1"></i>Guardar usuario';

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
                        btnGuardar.innerHTML = '<i class="bi bi-check-lg me-1"></i>Guardar usuario';
                    });
                });
            }
        });

    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

document.getElementById('btnGuardarUsuario').addEventListener('click', function () {
    const form = document.getElementById('usuario-modal-form');
    if (form) {
        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>