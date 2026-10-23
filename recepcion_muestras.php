<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('muestras.gestionar');

$conexion = conexion_bd();

$stmt = $conexion->query(
    "SELECT o.codigo AS orden_codigo, o.fecha_orden, o.medico, o.estado,
            p.codigo AS paciente_codigo, p.apellido, p.nombre, p.dni,
            COUNT(DISTINCT eo.estudio_codigo) AS cantidad_estudios,
            GROUP_CONCAT(DISTINCT e.estudio SEPARATOR ', ') AS estudios
     FROM ordenes o
     JOIN pacientes p ON p.codigo = o.paciente_codigo
     LEFT JOIN estudios_orden eo ON eo.orden_codigo = o.codigo
     LEFT JOIN estudios e ON e.codigo = eo.estudio_codigo
     WHERE
         NOT EXISTS (
             SELECT 1 FROM resultados r_x WHERE r_x.orden_codigo = o.codigo
         )
         OR EXISTS (
             SELECT 1 FROM resultados r_x
             JOIN historial_errores he_x ON he_x.resultado_codigo = r_x.codigo
             WHERE r_x.orden_codigo = o.codigo
               AND (he_x.error_muestra LIKE '%PENDIENTE%' OR he_x.error_muestra LIKE '%RECHAZADO%')
         )
     GROUP BY o.codigo
     ORDER BY o.fecha_orden ASC, o.codigo ASC"
);
$ordenesPendientes = $stmt->fetchAll();

$pendientesPorOrden = [];
$rechazadosPorOrden = [];
if (!empty($ordenesPendientes)) {
    $stmtPend = $conexion->query(
        "SELECT r.orden_codigo, he.error_muestra
         FROM historial_errores he
         JOIN resultados r ON r.codigo = he.resultado_codigo
         WHERE he.error_muestra LIKE '%PENDIENTE%' OR he.error_muestra LIKE '%RECHAZADO%'"
    );
    foreach ($stmtPend->fetchAll() as $row) {
        $oid = (int) $row['orden_codigo'];
        $texto = $row['error_muestra'];
        if (stripos($texto, 'PENDIENTE') !== false) {
            $pendientesPorOrden[$oid] = ($pendientesPorOrden[$oid] ?? 0) + 1;
        } elseif (stripos($texto, 'RECHAZADO') !== false) {
            $rechazadosPorOrden[$oid] = ($rechazadosPorOrden[$oid] ?? 0) + 1;
        }
    }
}

$mensaje = mensaje_flash();
$tituloPagina = 'Check-in de Muestras | CEBAC';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Check-in de Muestras</h1>
        <p class="text-muted mb-0">Órdenes pendientes de validación. Una vez validadas todas, salen de la lista.</p>
    </div>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success"><?= escapar_html($mensaje) ?></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Orden</th>
                    <th>Paciente</th>
                    <th>Fecha</th>
                    <th>Médico</th>
                    <th>Estudios</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ordenesPendientes as $o): ?>
                    <?php
                    $oid = (int) $o['orden_codigo'];
                    $cantidadEstudios = (int) $o['cantidad_estudios'];
                    $cantidadPendientes = $pendientesPorOrden[$oid] ?? 0;
                    $cantidadRechazados = $rechazadosPorOrden[$oid] ?? 0;
                    ?>
                    <tr>
                        <td><strong>#<?= formatear_codigo_orden($o['orden_codigo']) ?></strong></td>
                        <td>
                            <strong><?= escapar_html($o['apellido'] . ', ' . $o['nombre']) ?></strong>
                            <small class="text-muted d-block">DNI <?= escapar_html($o['dni']) ?></small>
                        </td>
                        <td><?= escapar_html($o['fecha_orden']) ?></td>
                        <td><?= escapar_html($o['medico']) ?></td>
                        <td>
                            <?= $cantidadEstudios ?> estudio(s)<br>
                            <small class="text-muted"><?= escapar_html($o['estudios'] ?? '—') ?></small>
                        </td>
                        <td>
                            <?php if ($cantidadRechazados > 0): ?>
                                <span class="badge bg-danger">Rechazado</span>
                                <small class="text-muted d-block"><?= $cantidadRechazados ?> para 2° análisis</small>
                            <?php elseif ($cantidadPendientes > 0): ?>
                                <span class="badge bg-warning text-dark">Pendiente</span>
                                <small class="text-muted d-block"><?= $cantidadPendientes ?> sin validar</small>
                            <?php else: ?>
                                <span class="badge bg-secondary">Sin check-in</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-primary" onclick="abrirModalCheckin(<?= $oid ?>)">
                                <i class="bi bi-clipboard-check"></i> Check-in
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$ordenesPendientes): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">
                        <i class="bi bi-check-circle fs-3 d-block mb-2 text-success"></i>
                        No hay órdenes pendientes de check-in.
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL CHECKIN -->
<div class="modal fade modal-ventana" id="modalCheckin" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-titulo">
                    <i class="bi bi-clipboard-check text-primary"></i>
                    <span>Check-in de muestra</span>
                </h5>
                <div class="modal-botones">
                    <button type="button" class="modal-accion minimizar" title="Minimizar"><i class="bi bi-dash-lg"></i></button>
                    <button type="button" class="modal-accion maximizar" title="Maximizar"><i class="bi bi-fullscreen"></i></button>
                    <button type="button" class="modal-accion cerrar" title="Cerrar"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="modal-body" id="modalCheckinBody">
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
                <button type="button" class="btn btn-success" id="btnValidarTodo">
                    <i class="bi bi-check-all me-1"></i>Validar todas
                </button>
                <button type="button" class="btn btn-primary" id="btnGuardarCheckin">
                    <i class="bi bi-save me-1"></i>Guardar check-in
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function abrirModalCheckin(ordenId) {
    const modal = document.getElementById('modalCheckin');
    const body = document.getElementById('modalCheckinBody');
    const btnGuardar = document.getElementById('btnGuardarCheckin');
    const btnValidarTodo = document.getElementById('btnValidarTodo');

    body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';

    fetch('checkin_muestra_formulario.php?_modal=1&orden_id=' + ordenId)
        .then(r => r.text())
        .then(html => {
            body.innerHTML = html;

            const form = document.getElementById('checkin-modal-form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const formData = new FormData(form);
                    formData.append('_modal', '1');

                    btnGuardar.disabled = true;
                    btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

                    fetch('checkin_muestra_formulario.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        btnGuardar.disabled = false;
                        btnGuardar.innerHTML = '<i class="bi bi-save me-1"></i>Guardar check-in';

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
                        btnGuardar.innerHTML = '<i class="bi bi-save me-1"></i>Guardar check-in';
                    });
                });
            }
        });

    btnValidarTodo.onclick = function () {
        document.querySelectorAll('#modalCheckinBody .checkin-estudio').forEach(function (box) {
            const radioValidar = box.querySelector('input[type="radio"][value="validar"]');
            if (radioValidar) radioValidar.checked = true;
        });
    };

    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

document.getElementById('btnGuardarCheckin').addEventListener('click', function () {
    const form = document.getElementById('checkin-modal-form');
    if (form) {
        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>