<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('reportes.gestionar');

$conexion = conexion_bd();

$desde = trim((string) ($_GET['desde'] ?? date('Y-m-01')));
$hasta = trim((string) ($_GET['hasta'] ?? date('Y-m-d')));
$obraSocialId = (int) ($_GET['obra_social_id'] ?? 0);
$estudioId = (int) ($_GET['estudio_id'] ?? 0);

$error = null;
if ($desde === '' || $hasta === '') {
    $error = 'Ingresá ambas fechas.';
}
if (!$error && $desde > $hasta) {
    $error = 'La fecha "desde" no puede ser mayor que "hasta".';
}

function fecha_ddmmyyyy(?string $fecha): string
{
    if (!$fecha) return '—';
    $partes = explode('-', substr($fecha, 0, 10));
    return count($partes) === 3 ? $partes[2] . '-' . $partes[1] . '-' . $partes[0] : $fecha;
}

$rechazos = [];
$totales = ['cantidad' => 0];
$topMotivos = [];
$topEstudios = [];

if (!$error) {
    $sql = "SELECT he.codigo AS historial_codigo, he.error_muestra,
                   r.codigo AS resultado_codigo, r.fecha_resultado,
                   o.codigo AS orden_codigo, o.codigo AS orden_numero, o.fecha_orden,
                   p.apellido, p.nombre, p.dni,
                   e.codigo AS estudio_codigo, e.estudio AS nombre_estudio,
                   os.nombre AS obra_social
            FROM historial_errores he
            JOIN resultados r ON r.codigo = he.resultado_codigo
            JOIN ordenes o ON o.codigo = r.orden_codigo
            JOIN pacientes p ON p.codigo = o.paciente_codigo
            JOIN estudios_orden eo ON eo.orden_codigo = o.codigo
            JOIN estudios e ON e.codigo = eo.estudio_codigo
            LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
            LEFT JOIN obras_sociales_credenciales c ON c.codigo = pos.credencial_codigo
            LEFT JOIN obras_sociales os ON os.codigo = c.obra_social_codigo
            WHERE he.error_muestra LIKE 'RECHAZADO%'
              AND r.fecha_resultado BETWEEN :desde AND :hasta";
    $params = [
        'desde' => $desde,
        'hasta' => $hasta,
    ];

    if ($estudioId > 0) {
        $sql .= ' AND e.codigo = :estudio_codigo';
        $params['estudio_codigo'] = $estudioId;
    }
    if ($obraSocialId > 0) {
        $sql .= ' AND c.obra_social_codigo = :obra_social';
        $params['obra_social'] = $obraSocialId;
    }

    $sql .= ' ORDER BY r.fecha_resultado DESC';

    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $rechazos = $stmt->fetchAll();

    $totales['cantidad'] = count($rechazos);

    // Top motivos
    $conteo = [];
    foreach ($rechazos as $r) {
        $motivo = trim((string) ($r['error_muestra'] ?? 'Sin motivo'));
        $motivo = preg_replace('/^RECHAZADO[:\s-]*/i', '', $motivo);
        $motivo = trim($motivo);
        if ($motivo === '') $motivo = 'Sin motivo';
        $conteo[$motivo] = ($conteo[$motivo] ?? 0) + 1;
    }
    arsort($conteo);
    $topMotivos = array_slice($conteo, 0, 3, true);

    // Top estudios
    $conteoEst = [];
    foreach ($rechazos as $r) {
        $k = $r['nombre_estudio'] . ' (' . $r['estudio_codigo'] . ')';
        $conteoEst[$k] = ($conteoEst[$k] ?? 0) + 1;
    }
    arsort($conteoEst);
    $topEstudios = array_slice($conteoEst, 0, 3, true);
}

$obrasSociales = $conexion->query('SELECT codigo, nombre FROM obras_sociales WHERE fecha_baja IS NULL ORDER BY nombre')->fetchAll();
$listaEstudios = $conexion->query('SELECT codigo, estudio FROM estudios ORDER BY estudio')->fetchAll();

$tituloPagina = 'Reporte: Muestras rechazadas | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container">
<p><a href="reportes.php">← Volver a reportes</a></p>

<div class="page-heading">
    <div>
        <span class="eyebrow">Reporte</span>
        <h1>Muestras rechazadas</h1>
        <p class="muted">Rechazos registrados en el período, con motivo y responsable.</p>
    </div>
    <?php if (!$error && $rechazos): ?>
        <a class="button-link" href="reporte_muestras_rechazadas_pdf.php?<?= http_build_query([
            'desde' => $desde,
            'hasta' => $hasta,
            'obra_social_id' => $obraSocialId,
            'estudio_id' => $estudioId,
        ]) ?>" target="_blank">📄 Ver PDF</a>
    <?php endif; ?>
</div>

<?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?>

<form method="get" class="report-filters module">
    <div class="filter-grid">
        <div>
            <label for="desde">Desde</label>
            <input type="date" id="desde" name="desde" value="<?= escapar_html($desde) ?>" required>
        </div>
        <div>
            <label for="hasta">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="<?= escapar_html($hasta) ?>" required>
        </div>
        <div>
            <label for="obra_social_id">Obra social</label>
            <select id="obra_social_id" name="obra_social_id">
                <option value="0">Todas</option>
                <?php foreach ($obrasSociales as $os): ?>
                    <option value="<?= (int) $os['codigo'] ?>" <?= $obraSocialId === (int) $os['codigo'] ? 'selected' : '' ?>>
                        <?= escapar_html($os['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="estudio_id">Estudio</label>
            <select id="estudio_id" name="estudio_id">
                <option value="0">Todos</option>
                <?php foreach ($listaEstudios as $e): ?>
                    <option value="<?= escapar_html($e['codigo']) ?>" <?= $estudioId === $e['codigo'] ? 'selected' : '' ?>>
                        <?= escapar_html($e['estudio']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit">Generar reporte</button>
            <a href="reporte_muestras_rechazadas.php" class="link-secondary">Limpiar</a>
        </div>
    </div>
</form>

<?php if (!$error): ?>
    <section class="report-summary">
        <div class="summary-card">
            <span class="eyebrow">Total de rechazos</span>
            <strong class="summary-number"><?= (int) $totales['cantidad'] ?></strong>
            <small class="muted">Entre <?= escapar_html(fecha_ddmmyyyy($desde)) ?> y <?= escapar_html(fecha_ddmmyyyy($hasta)) ?></small>
        </div>
        <div class="summary-card">
            <span class="eyebrow">Motivo más frecuente</span>
            <?php if ($topMotivos): ?>
                <?php $primerMotivo = array_key_first($topMotivos); ?>
                <strong><?= escapar_html($primerMotivo) ?></strong>
                <small class="muted"><?= (int) $topMotivos[$primerMotivo] ?> caso(s)</small>
            <?php else: ?>
                <strong class="muted">—</strong>
            <?php endif; ?>
        </div>
        <div class="summary-card">
            <span class="eyebrow">Estudio más rechazado</span>
            <?php if ($topEstudios): ?>
                <?php $primerEst = array_key_first($topEstudios); ?>
                <strong><?= escapar_html($primerEst) ?></strong>
                <small class="muted"><?= (int) $topEstudios[$primerEst] ?> caso(s)</small>
            <?php else: ?>
                <strong class="muted">—</strong>
            <?php endif; ?>
        </div>
    </section>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Orden</th>
                    <th>Paciente</th>
                    <th>DNI</th>
                    <th>Estudio</th>
                    <th>Obra social</th>
                    <th>Motivo del rechazo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rechazos as $r): ?>
                    <tr>
                        <td><?= escapar_html(fecha_ddmmyyyy($r['fecha_resultado'])) ?></td>
                        <td><strong><?= formatear_codigo_orden($r['orden_numero']) ?></strong></td>
                        <td><?= escapar_html($r['apellido'] . ', ' . $r['nombre']) ?></td>
                        <td><?= escapar_html($r['dni']) ?></td>
                        <td>
                            <?= escapar_html($r['nombre_estudio']) ?><br>
                            <small class="muted"><?= escapar_html($r['estudio_codigo']) ?></small>
                        </td>
                        <td><?= escapar_html($r['obra_social'] ?? 'Particular') ?></td>
                        <td><?= escapar_html(preg_replace('/^RECHAZADO[:\s-]*/i', '', $r['error_muestra'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rechazos): ?>
                    <tr><td colspan="7" class="muted">No se registraron rechazos en el período seleccionado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($rechazos): ?>
        <section class="report-details">
            <div class="detail-card">
                <h3>Top 3 motivos de rechazo</h3>
                <?php if ($topMotivos): ?>
                    <ol>
                        <?php foreach ($topMotivos as $motivo => $cant): ?>
                            <li><span><?= escapar_html($motivo) ?></span><strong><?= (int) $cant ?></strong></li>
                        <?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <p class="muted">—</p>
                <?php endif; ?>
            </div>
            <div class="detail-card">
                <h3>Top 3 estudios rechazados</h3>
                <?php if ($topEstudios): ?>
                    <ol>
                        <?php foreach ($topEstudios as $est => $cant): ?>
                            <li><span><?= escapar_html($est) ?></span><strong><?= (int) $cant ?></strong></li>
                        <?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <p class="muted">—</p>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>