<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('caja.gestionar');

$conexion = conexion_bd();

$stmtPacientes = $conexion->query(
    "SELECT p.codigo AS paciente_codigo, p.apellido, p.nombre, p.dni,
            os.nombre AS obra_social,
            pos.nro_credencial, pos.cobertura, pos.porcentaje_cobertura
     FROM pacientes p
     LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
     LEFT JOIN obras_sociales os ON os.codigo = pos.obra_social_codigo
     ORDER BY p.apellido, p.nombre"
);
$pacientes = $stmtPacientes->fetchAll();

$stmtOrdenesCompleta = $conexion->query(
    "SELECT o.codigo, o.paciente_codigo, o.fecha_orden,
            COALESCE(SUM(pp.precio), 0) AS total_orden
     FROM ordenes o
     LEFT JOIN estudios_orden eo ON eo.orden_codigo = o.codigo
     LEFT JOIN estudios e ON e.codigo = eo.estudio_codigo
     LEFT JOIN precio_practica pp ON pp.codigo = e.practica_codigo
     GROUP BY o.codigo
     ORDER BY o.paciente_codigo, o.fecha_orden ASC, o.codigo ASC"
);

$acargoPorPaciente = [];
foreach ($stmtOrdenesCompleta->fetchAll() as $o) {
    $pc = (int) $o['paciente_codigo'];
    $porcentaje = 0;
    foreach ($pacientes as $p) {
        if ((int) $p['paciente_codigo'] === $pc) {
            $porcentaje = (float) ($p['porcentaje_cobertura'] ?? 0);
            break;
        }
    }
    $montoPaciente = (float) $o['total_orden'] * (1 - $porcentaje / 100);
    $acargoPorPaciente[$pc] = ($acargoPorPaciente[$pc] ?? 0) + $montoPaciente;
}

$pagosPorPaciente = [];
$stmtPagos = $conexion->query('SELECT paciente_codigo, SUM(monto) AS total_pagado FROM caja GROUP BY paciente_codigo');
foreach ($stmtPagos->fetchAll() as $row) {
    $pagosPorPaciente[(int) $row['paciente_codigo']] = (float) $row['total_pagado'];
}

$pacientesConSaldo = [];
foreach ($pacientes as $p) {
    $pc = (int) $p['paciente_codigo'];
    $aCargo = $acargoPorPaciente[$pc] ?? 0;
    $pagado = $pagosPorPaciente[$pc] ?? 0;
    $saldo = max(0, $aCargo - $pagado);
    if ($saldo > 0) {
        $pacientesConSaldo[] = array_merge($p, [
            'a_cargo' => $aCargo,
            'pagado' => $pagado,
            'saldo' => $saldo,
        ]);
    }
}

$stmtHistorial = $conexion->query(
    "SELECT c.codigo, c.monto, c.metodo_pago, c.fecha_pago,
            p.apellido, p.nombre, p.dni
     FROM caja c
     JOIN pacientes p ON p.codigo = c.paciente_codigo
     ORDER BY c.fecha_pago DESC, c.codigo DESC
     LIMIT 20"
);
$historial = $stmtHistorial->fetchAll();

$mensaje = mensaje_flash();
$tituloPagina = 'Caja | CEBAC';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Caja</h1>
        <p class="text-muted mb-0">Saldos pendientes y registro de pagos.</p>
    </div>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success"><?= escapar_html($mensaje) ?></div>
<?php endif; ?>

<h2 class="h5 fw-bold mb-3">Pacientes con saldo pendiente</h2>
<div class="card shadow-sm mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Paciente</th>
                    <th>DNI</th>
                    <th>Obra social</th>
                    <th>Cobertura</th>
                    <th class="text-end">A cargo</th>
                    <th class="text-end">Pagado</th>
                    <th class="text-end">Saldo</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pacientesConSaldo as $p): ?>
                    <tr>
                        <td><strong><?= escapar_html($p['apellido'] . ', ' . $p['nombre']) ?></strong></td>
                        <td><?= escapar_html($p['dni']) ?></td>
                        <td><?= escapar_html($p['obra_social'] ?? 'Particular') ?></td>
                        <td>
                            <?php if ($p['cobertura']): ?>
                                <span class="badge bg-info text-dark"><?= escapar_html($p['cobertura']) ?></span>
                                <small class="text-muted d-block"><?= number_format((float) $p['porcentaje_cobertura'], 0, ',', '.') ?>%</small>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">$ <?= number_format((float) $p['a_cargo'], 2, ',', '.') ?></td>
                        <td class="text-end">$ <?= number_format((float) $p['pagado'], 2, ',', '.') ?></td>
                        <td class="text-end">
                            <span class="badge bg-danger fs-6">$ <?= number_format((float) $p['saldo'], 2, ',', '.') ?></span>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-primary" onclick="abrirModalPago(<?= (int) $p['paciente_codigo'] ?>)">
                                <i class="bi bi-cash-coin"></i> Registrar pago
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$pacientesConSaldo): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">
                        <i class="bi bi-check-circle fs-3 d-block mb-2 text-success"></i>
                        No hay pacientes con saldo pendiente.
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<h2 class="h5 fw-bold mb-3">Últimos pagos registrados</h2>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nº</th>
                    <th>Fecha</th>
                    <th>Paciente</th>
                    <th>DNI</th>
                    <th class="text-end">Monto</th>
                    <th>Método</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historial as $h): ?>
                    <tr>
                        <td><span class="text-muted">#<?= (int) $h['codigo'] ?></span></td>
                        <td><?= escapar_html($h['fecha_pago']) ?></td>
                        <td><?= escapar_html($h['apellido'] . ', ' . $h['nombre']) ?></td>
                        <td><?= escapar_html($h['dni']) ?></td>
                        <td class="text-end"><strong>$ <?= number_format((float) $h['monto'], 2, ',', '.') ?></strong></td>
                        <td><span class="badge bg-secondary"><?= escapar_html($h['metodo_pago']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$historial): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        No hay pagos registrados todavía.
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL CAJA -->
<div class="modal fade modal-ventana" id="modalPago" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-titulo">
                    <i class="bi bi-cash-coin text-primary"></i>
                    <span id="modalPagoTitulo">Registrar pago</span>
                </h5>
                <div class="modal-botones">
                    <button type="button" class="modal-accion minimizar" title="Minimizar"><i class="bi bi-dash-lg"></i></button>
                    <button type="button" class="modal-accion maximizar" title="Maximizar"><i class="bi bi-fullscreen"></i></button>
                    <button type="button" class="modal-accion cerrar" title="Cerrar"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="modal-body" id="modalPagoBody">
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
                <button type="button" class="btn btn-success" id="btnGuardarPago">
                    <i class="bi bi-check-lg me-1"></i>Registrar pago
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function abrirModalPago(pacienteCodigo) {
    const modal = document.getElementById('modalPago');
    const body = document.getElementById('modalPagoBody');
    const btnGuardar = document.getElementById('btnGuardarPago');

    body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';

    fetch('caja_formulario.php?_modal=1&paciente_codigo=' + pacienteCodigo)
        .then(r => r.text())
        .then(html => {
            body.innerHTML = html;

            const form = document.getElementById('caja-modal-form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const formData = new FormData(form);
                    formData.append('_modal', '1');

                    btnGuardar.disabled = true;
                    btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Registrando...';

                    fetch('caja_formulario.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        btnGuardar.disabled = false;
                        btnGuardar.innerHTML = '<i class="bi bi-check-lg me-1"></i>Registrar pago';

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
                        btnGuardar.innerHTML = '<i class="bi bi-check-lg me-1"></i>Registrar pago';
                    });
                });
            }
        });

    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

document.getElementById('btnGuardarPago').addEventListener('click', function () {
    const form = document.getElementById('caja-modal-form');
    if (form) {
        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>