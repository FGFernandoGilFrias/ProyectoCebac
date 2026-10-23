<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('resultados.gestionar');

$conexion = conexion_bd();

$consultaSql = "SELECT o.codigo AS orden_codigo, o.fecha_orden, o.medico, o.estado,
                       p.codigo AS paciente_codigo, p.apellido, p.nombre, p.dni,
                       os.nombre AS obra_social,
                       pos.nro_credencial, pos.cobertura, pos.porcentaje_cobertura,
                       COUNT(DISTINCT eo.estudio_codigo) AS cantidad_estudios,
                       COALESCE(SUM(pp.precio), 0) AS total_orden,
                       r.codigo AS resultado_codigo, r.resultado, r.fecha_resultado
                FROM ordenes o
                JOIN pacientes p ON p.codigo = o.paciente_codigo
                LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
                LEFT JOIN obras_sociales os ON os.codigo = pos.obra_social_codigo
                LEFT JOIN estudios_orden eo ON eo.orden_codigo = o.codigo
                LEFT JOIN estudios e ON e.codigo = eo.estudio_codigo
                LEFT JOIN precio_practica pp ON pp.codigo = e.practica_codigo
                LEFT JOIN resultados r ON r.orden_codigo = o.codigo
                GROUP BY o.codigo
                ORDER BY o.fecha_orden DESC, o.codigo DESC
                LIMIT 100";
$consulta = $conexion->query($consultaSql);
$ordenes = $consulta->fetchAll();

$pagosPorPaciente = [];
$stmt = $conexion->query('SELECT paciente_codigo, SUM(monto) AS total_pagado FROM caja GROUP BY paciente_codigo');
foreach ($stmt->fetchAll() as $row) {
    $pagosPorPaciente[(int) $row['paciente_codigo']] = (float) $row['total_pagado'];
}

$mensaje = mensaje_flash();
$tituloPagina = 'Resultados | CEBAC';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Resultados</h1>
        <p class="text-muted mb-0">Carga y gestión de resultados por orden médica.</p>
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
                    <th>Cobertura</th>
                    <th class="text-center">Estudios</th>
                    <th class="text-end">A cargo pac.</th>
                    <th class="text-end">Pagado</th>
                    <th>Estado</th>
                    <th>Resultado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ordenes as $o): ?>
                    <?php
                    $totalOrden = (float) $o['total_orden'];
                    $porcentaje = (float) ($o['porcentaje_cobertura'] ?? 0);
                    $aCargo = $totalOrden * (1 - $porcentaje / 100);
                    $pagado = $pagosPorPaciente[(int) $o['paciente_codigo']] ?? 0;
                    $tieneResultado = !empty($o['resultado_codigo']);
                    ?>
                    <tr>
                        <td><strong>#<?= formatear_codigo_orden($o['orden_codigo']) ?></strong></td>
                        <td>
                            <strong><?= escapar_html($o['apellido'] . ', ' . $o['nombre']) ?></strong>
                            <small class="text-muted d-block"><?= escapar_html($o['dni']) ?></small>
                        </td>
                        <td><?= escapar_html($o['fecha_orden']) ?></td>
                        <td>
                            <?php if ($o['cobertura']): ?>
                                <span class="badge bg-info text-dark"><?= escapar_html($o['cobertura']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">Particular</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= (int) $o['cantidad_estudios'] ?></td>
                        <td class="text-end">$ <?= number_format($aCargo, 2, ',', '.') ?></td>
                        <td class="text-end">$ <?= number_format($pagado, 2, ',', '.') ?></td>
                        <td>
                            <?php
                            $badgeEstado = 'bg-secondary';
                            if ($o['estado'] === 'Validado') $badgeEstado = 'bg-info text-dark';
                            elseif ($o['estado'] === 'Finalizado') $badgeEstado = 'bg-success';
                            ?>
                            <span class="badge <?= $badgeEstado ?>"><?= escapar_html($o['estado']) ?></span>
                        </td>
                        <td>
                            <?php if ($tieneResultado): ?>
                                <span class="badge bg-success">Cargado</span>
                            <?php else: ?>
                                <span class="text-muted">Sin cargar</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirModalResultado(<?= (int) $o['orden_codigo'] ?>)">
                                <i class="bi bi-pencil"></i> <?= $tieneResultado ? 'Ver / Editar' : 'Cargar' ?>
                            </button>
                            <?php if ($tieneResultado): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="resultado_pdf.php?orden_id=<?= (int) $o['orden_codigo'] ?>" target="_blank">
                                    <i class="bi bi-file-pdf"></i> PDF
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$ordenes): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        No hay órdenes registradas.
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL RESULTADO -->
<div class="modal fade modal-ventana" id="modalResultado" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-titulo">
                    <i class="bi bi-file-earmark-text text-primary"></i>
                    <span id="modalResultadoTitulo">Resultado</span>
                </h5>
                <div class="modal-botones">
                    <button type="button" class="modal-accion minimizar" title="Minimizar"><i class="bi bi-dash-lg"></i></button>
                    <button type="button" class="modal-accion maximizar" title="Maximizar"><i class="bi bi-fullscreen"></i></button>
                    <button type="button" class="modal-accion cerrar" title="Cerrar"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="modal-body" id="modalResultadoBody">
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
                <button type="button" class="btn btn-primary" id="btnGuardarResultado">
                    <i class="bi bi-save me-1"></i>Guardar resultado
                </button>
            </div>
        </div>
    </div>
</div>

<script>


function abrirModalResultado(ordenId) {
    const modal = document.getElementById('modalResultado');
    const body = document.getElementById('modalResultadoBody');
    const btnGuardar = document.getElementById('btnGuardarResultado');

    body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';

    fetch('resultado_formulario.php?_modal=1&orden_id=' + ordenId)
        .then(r => r.text())
        .then(html => {
            body.innerHTML = html;

            // ✅ EJECUTAR LOS SCRIPTS DEL HTML CARGADO
            window.ejecutarScripts(body);

            const form = document.getElementById('resultado-modal-form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const formData = new FormData(form);
                    formData.append('_modal', '1');

                    btnGuardar.disabled = true;
                    btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

                    fetch('resultado_formulario.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        btnGuardar.disabled = false;
                        btnGuardar.innerHTML = '<i class="bi bi-save me-1"></i>Guardar resultado';

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
                        btnGuardar.innerHTML = '<i class="bi bi-save me-1"></i>Guardar resultado';
                    });
                });
            }
        });

    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

document.getElementById('btnGuardarResultado').addEventListener('click', function () {
    const form = document.getElementById('resultado-modal-form');
    if (form) {
        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>