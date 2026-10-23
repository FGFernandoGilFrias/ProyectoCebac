<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('ordenes.gestionar');

$q = trim((string) ($_GET['q'] ?? ''));

$consultaSql = "SELECT o.codigo, o.fecha_orden, o.medico, o.estado,
                       p.codigo AS paciente_codigo, p.apellido, p.nombre, p.dni,
                       os.nombre AS obra_social,
                       pos.nro_credencial, pos.cobertura, pos.porcentaje_cobertura,
                       COUNT(eo.estudio_codigo) AS cantidad_estudios,
                       COALESCE(SUM(pp.precio), 0) AS total_orden
                FROM ordenes o
                JOIN pacientes p ON p.codigo = o.paciente_codigo
                LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
                LEFT JOIN obras_sociales os ON os.codigo = pos.obra_social_codigo
                LEFT JOIN estudios_orden eo ON eo.orden_codigo = o.codigo
                LEFT JOIN estudios e ON e.codigo = eo.estudio_codigo
                LEFT JOIN precio_practica pp ON pp.codigo = e.practica_codigo";

$params = [];
if ($q !== '') {
    $consultaSql .= ' WHERE CAST(o.codigo AS CHAR) LIKE :q1 OR p.dni LIKE :q2 OR p.apellido LIKE :q3 OR p.nombre LIKE :q4';
    $params['q1'] = '%' . $q . '%';
    $params['q2'] = '%' . $q . '%';
    $params['q3'] = '%' . $q . '%';
    $params['q4'] = '%' . $q . '%';
}

$consultaSql .= ' GROUP BY o.codigo ORDER BY o.fecha_orden ASC, o.codigo ASC';

$consulta = conexion_bd()->prepare($consultaSql);
$consulta->execute($params);
$ordenesRaw = $consulta->fetchAll();

$ordenesPorPaciente = [];
foreach ($ordenesRaw as $o) {
    $pc = (int) $o['paciente_codigo'];
    if (!isset($ordenesPorPaciente[$pc])) $ordenesPorPaciente[$pc] = [];
    $ordenesPorPaciente[$pc][] = $o;
}

$pagosPorPaciente = [];
$stmt = conexion_bd()->query('SELECT paciente_codigo, SUM(monto) AS total_pagado FROM caja GROUP BY paciente_codigo');
foreach ($stmt->fetchAll() as $row) {
    $pagosPorPaciente[(int) $row['paciente_codigo']] = (float) $row['total_pagado'];
}

$saldosPorOrden = [];
$estadoPagoPorOrden = [];

foreach ($ordenesPorPaciente as $pacienteCodigo => $ordenes) {
    $pagoDisponible = $pagosPorPaciente[$pacienteCodigo] ?? 0;

    foreach ($ordenes as $o) {
        $totalOrden = (float) $o['total_orden'];
        $porcentaje = (float) ($o['porcentaje_cobertura'] ?? 0);
        $montoPaciente = $totalOrden * (1 - $porcentaje / 100);

        if ($pagoDisponible <= 0) {
            $saldosPorOrden[(int) $o['codigo']] = $montoPaciente;
            $estadoPagoPorOrden[(int) $o['codigo']] = 'Pendiente';
            continue;
        }

        if ($pagoDisponible >= $montoPaciente) {
            $pagoDisponible -= $montoPaciente;
            $saldosPorOrden[(int) $o['codigo']] = 0;
            $estadoPagoPorOrden[(int) $o['codigo']] = 'Pagado';
        } else {
            $saldosPorOrden[(int) $o['codigo']] = $montoPaciente - $pagoDisponible;
            $pagoDisponible = 0;
            $estadoPagoPorOrden[(int) $o['codigo']] = 'Parcial';
        }
    }
}

$ordenes = array_reverse($ordenesRaw);

// ✅ NUEVO: Obtener el ID de la orden recién creada (para abrir el PDF de instrucciones)
$nuevaOrdenInstrucciones = $_SESSION['nueva_orden_instrucciones'] ?? null;
unset($_SESSION['nueva_orden_instrucciones']);

$mensaje = mensaje_flash();
$tituloPagina = 'Órdenes | CEBAC';
include __DIR__ . '/includes/header.php';
?>

<?php if ($nuevaOrdenInstrucciones): ?>
    <script>
        // ✅ Abrir el PDF de instrucciones automáticamente en una pestaña nueva
        setTimeout(function () {
            window.open('instrucciones_pdf.php?orden_id=<?= (int) $nuevaOrdenInstrucciones ?>', '_blank');
        }, 500);
    </script>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Órdenes médicas</h1>
        <p class="text-muted mb-0">Registro de órdenes y estudios solicitados.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="abrirModalOrden(0)">
        <i class="bi bi-plus-lg me-1"></i>Nueva orden
    </button>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success"><?= escapar_html($mensaje) ?></div>
<?php endif; ?>

<form method="get" class="row g-2 mb-4">
    <div class="col-md-8 col-lg-6">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" name="q" value="<?= escapar_html($q) ?>" placeholder="Buscar por orden, DNI o paciente">
        </div>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary">Buscar</button>
        <?php if ($q !== ''): ?>
            <a href="ordenes.php" class="btn btn-outline-secondary">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Orden</th>
                    <th>Paciente</th>
                    <th>Obra social</th>
                    <th>Cobertura</th>
                    <th>Fecha</th>
                    <th>Médico</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Paciente paga</th>
                    <th>Saldo</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ordenes as $o): ?>
                    <?php
                    $ordenCodigo = (int) $o['codigo'];
                    $totalOrden = (float) $o['total_orden'];
                    $porcentaje = (float) ($o['porcentaje_cobertura'] ?? 0);
                    $montoPaciente = $totalOrden * (1 - $porcentaje / 100);
                    $saldoOrden = $saldosPorOrden[$ordenCodigo] ?? $montoPaciente;
                    $estadoPago = $estadoPagoPorOrden[$ordenCodigo] ?? 'Pendiente';
                    ?>
                    <tr>
                        <td>
                            <a href="orden_detalle.php?id=<?= $ordenCodigo ?>" class="fw-bold text-decoration-none">
                                #<?= formatear_codigo_orden($o['codigo']) ?>
                            </a>
                        </td>
                        <td>
                            <strong><?= escapar_html($o['apellido'] . ', ' . $o['nombre']) ?></strong>
                            <small class="text-muted d-block">DNI <?= escapar_html($o['dni']) ?></small>
                        </td>
                        <td><?= escapar_html($o['obra_social'] ?? 'Particular') ?></td>
                        <td>
                            <?php if ($o['cobertura']): ?>
                                <span class="badge bg-info text-dark"><?= escapar_html($o['cobertura']) ?></span>
                                <small class="text-muted d-block"><?= number_format($porcentaje, 0, ',', '.') ?>%</small>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= escapar_html($o['fecha_orden']) ?></td>
                        <td><?= escapar_html($o['medico']) ?></td>
                        <td class="text-end">$ <?= number_format($totalOrden, 2, ',', '.') ?></td>
                        <td class="text-end">$ <?= number_format($montoPaciente, 2, ',', '.') ?></td>
                        <td>
                            <?php if ($estadoPago === 'Pagado'): ?>
                                <span class="badge bg-success">Pagado</span>
                            <?php elseif ($estadoPago === 'Parcial'): ?>
                                <span class="badge bg-warning text-dark">$ <?= number_format($saldoOrden, 2, ',', '.') ?></span>
                            <?php else: ?>
                                <span class="badge bg-danger">$ <?= number_format($saldoOrden, 2, ',', '.') ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $badgeEstado = 'bg-secondary';
                            if ($o['estado'] === 'Validado') $badgeEstado = 'bg-info text-dark';
                            elseif ($o['estado'] === 'Finalizado') $badgeEstado = 'bg-success';
                            ?>
                            <span class="badge <?= $badgeEstado ?>"><?= escapar_html($o['estado']) ?></span>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirModalOrden(<?= $ordenCodigo ?>)">
                                <i class="bi bi-pencil"></i> Editar
                            </button>
                            <a class="btn btn-sm btn-outline-secondary" href="orden_detalle.php?id=<?= $ordenCodigo ?>">
                                <i class="bi bi-eye"></i> Ver
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$ordenes): ?>
                    <tr><td colspan="11" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        No hay órdenes registradas.
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL ORDEN -->
<div class="modal fade modal-ventana" id="modalOrden" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-titulo">
                    <i class="bi bi-file-earmark-medical text-primary"></i>
                    <span id="modalOrdenTitulo">Nueva orden</span>
                </h5>
                <div class="modal-botones">
                    <button type="button" class="modal-accion minimizar" title="Minimizar"><i class="bi bi-dash-lg"></i></button>
                    <button type="button" class="modal-accion maximizar" title="Maximizar"><i class="bi bi-fullscreen"></i></button>
                    <button type="button" class="modal-accion cerrar" title="Cerrar"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="modal-body" id="modalOrdenBody">
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
                <button type="button" class="btn btn-primary" id="btnGuardarOrden">
                    <i class="bi bi-check-lg me-1"></i>Guardar orden
                </button>
            </div>
        </div>
    </div>
</div>

<script>

function abrirModalOrden(id) {
    const modal = document.getElementById('modalOrden');
    const body = document.getElementById('modalOrdenBody');
    const titulo = document.getElementById('modalOrdenTitulo');
    const btnGuardar = document.getElementById('btnGuardarOrden');

    titulo.textContent = id > 0 ? 'Editar orden' : 'Nueva orden';
    body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';

    fetch('orden_formulario.php?_modal=1' + (id > 0 ? '&id=' + id : ''))
        .then(r => r.text())
        .then(html => {
            body.innerHTML = html;

            // ✅ EJECUTAR LOS SCRIPTS DEL HTML CARGADO
            window.ejecutarScripts(body);

            const form = document.getElementById('orden-modal-form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const formData = new FormData(form);
                    formData.append('_modal', '1');

                    btnGuardar.disabled = true;
                    btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

                    fetch('orden_formulario.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        btnGuardar.disabled = false;
                        btnGuardar.innerHTML = '<i class="bi bi-check-lg me-1"></i>Guardar orden';

                        if (data.ok) {
                            bootstrap.Modal.getInstance(modal).hide();

                            // ✅ Si es orden nueva, abrir el PDF de instrucciones
                            if (id === 0 && data.id) {
                                window.open('instrucciones_pdf.php?orden_id=' + data.id, '_blank');
                            }

                            setTimeout(() => window.location.reload(), 800);
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
                        btnGuardar.innerHTML = '<i class="bi bi-check-lg me-1"></i>Guardar orden';
                    });
                });
            }
        });

    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

document.getElementById('btnGuardarOrden').addEventListener('click', function () {
    const form = document.getElementById('orden-modal-form');
    if (form) {
        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>