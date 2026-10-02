<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('reportes.gestionar');

$conexion = conexion_bd();

$desde = trim((string) ($_GET['desde'] ?? date('Y-m-01')));
$hasta = trim((string) ($_GET['hasta'] ?? date('Y-m-d')));

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

$ordenes = [];

if (!$error) {
    // Solo órdenes con muestra ya validada (Validado o Finalizado)
    $sql = "SELECT o.codigo AS orden_codigo, o.fecha_orden, o.estado,
                   p.apellido, p.nombre, p.dni,
                   COUNT(DISTINCT eo.estudio_codigo) AS cantidad_estudios,
                   GROUP_CONCAT(DISTINCT e.estudio ORDER BY e.estudio SEPARATOR ', ') AS estudios
            FROM ordenes o
            JOIN pacientes p ON p.codigo = o.paciente_codigo
            LEFT JOIN estudios_orden eo ON eo.orden_codigo = o.codigo
            LEFT JOIN estudios e ON e.codigo = eo.estudio_codigo
            WHERE o.fecha_orden BETWEEN :desde AND :hasta
              AND o.estado IN ('Validado', 'Finalizado')
            GROUP BY o.codigo
            ORDER BY o.fecha_orden DESC, o.codigo DESC
            LIMIT 200";

    $stmt = $conexion->prepare($sql);
    $stmt->execute(['desde' => $desde, 'hasta' => $hasta]);
    $ordenes = $stmt->fetchAll();
}

$tituloPagina = 'Etiquetas de muestra | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container">
<p><a href="reportes.php">← Volver a reportes</a></p>

<div class="page-heading">
    <div>
        <span class="eyebrow">Reporte</span>
        <h1>Etiquetas de muestra</h1>
        <p class="muted">Imprimí las etiquetas para pegar en los tubos de muestra. Solo se listan órdenes con muestra validada.</p>
    </div>
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
        <div class="filter-actions">
            <button type="submit">Generar</button>
            <a href="etiqueta_muestra_listado.php" class="link-secondary">Limpiar</a>
        </div>
    </div>
</form>

<?php if (!$error): ?>
    <section class="report-summary">
        <div class="summary-card">
            <span class="eyebrow">Órdenes con muestra</span>
            <strong class="summary-number"><?= count($ordenes) ?></strong>
            <small class="muted">Entre <?= escapar_html(fecha_ddmmyyyy($desde)) ?> y <?= escapar_html(fecha_ddmmyyyy($hasta)) ?></small>
        </div>
        <div class="summary-card">
            <span class="eyebrow">Etiquetas a imprimir</span>
            <?php
            $totalEtiquetas = 0;
            foreach ($ordenes as $o) $totalEtiquetas += (int) $o['cantidad_estudios'];
            ?>
            <strong class="summary-number"><?= $totalEtiquetas ?></strong>
            <small class="muted">Una por estudio</small>
        </div>
    </section>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Orden</th>
                    <th>Paciente</th>
                    <th>DNI</th>
                    <th>Fecha</th>
                    <th>Estudios</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ordenes as $o): ?>
                    <tr>
                        <td><strong><?= formatear_codigo_orden($o['orden_codigo']) ?></strong></td>
                        <td><?= escapar_html($o['apellido'] . ', ' . $o['nombre']) ?></td>
                        <td><?= escapar_html($o['dni']) ?></td>
                        <td><?= escapar_html(fecha_ddmmyyyy($o['fecha_orden'])) ?></td>
                        <td>
                            <?= (int) $o['cantidad_estudios'] ?> estudio(s)<br>
                            <small class="muted"><?= escapar_html($o['estudios'] ?? '—') ?></small>
                        </td>
                        <td><span class="badge"><?= escapar_html($o['estado']) ?></span></td>
                        <td>
                            <a class="button-small" href="etiqueta_muestra_pdf.php?orden_id=<?= (int) $o['orden_codigo'] ?>" target="_blank">🖨️ Imprimir etiquetas</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$ordenes): ?>
                    <tr><td colspan="7" class="muted">No hay órdenes con muestra validada en el período.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>