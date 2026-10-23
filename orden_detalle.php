<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('ordenes.gestionar');

$id = (int) ($_GET['id'] ?? 0);
$conexion = conexion_bd();

$stmt = $conexion->prepare(
    "SELECT o.*, p.apellido, p.nombre, p.dni, p.fecha_nacimiento, p.telefono, p.correo,
            os.nombre AS obra_social,
            pos.nro_credencial, pos.cobertura, pos.porcentaje_cobertura
     FROM ordenes o
     JOIN pacientes p ON p.codigo = o.paciente_codigo
     LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
     LEFT JOIN obras_sociales os ON os.codigo = pos.obra_social_codigo
     WHERE o.codigo = :codigo"
);
$stmt->execute(['codigo' => $id]);
$orden = $stmt->fetch();

if (!$orden) {
    http_response_code(404);
    exit('Orden no encontrada.');
}

// Estudios (sin JOIN a resultados por estudio)
$stmt = $conexion->prepare(
    "SELECT e.codigo, e.estudio, e.parametros, pp.precio
     FROM estudios_orden eo
     JOIN estudios e ON e.codigo = eo.estudio_codigo
     LEFT JOIN precio_practica pp ON pp.codigo = e.practica_codigo
     WHERE eo.orden_codigo = :codigo
     ORDER BY e.estudio"
);
$stmt->execute(['codigo' => $id]);
$estudios = $stmt->fetchAll();

// Resultado general de la orden (si existe)
$stmt = $conexion->prepare('SELECT codigo, resultado, fecha_resultado FROM resultados WHERE orden_codigo = :codigo LIMIT 1');
$stmt->execute(['codigo' => $id]);
$resultado = $stmt->fetch();

$totalOrden = 0;
foreach ($estudios as $e) {
    $totalOrden += (float) ($e['precio'] ?? 0);
}

$porcentaje = (float) ($orden['porcentaje_cobertura'] ?? 0);
$montoCubre = $totalOrden * ($porcentaje / 100);
$montoPaciente = $totalOrden - $montoCubre;

// Pago del paciente
$stmt = $conexion->prepare('SELECT COALESCE(SUM(monto), 0) FROM caja WHERE paciente_codigo = :pc');
$stmt->execute(['pc' => (int) $orden['paciente_codigo']]);
$pagadoPaciente = (float) $stmt->fetchColumn();

$saldoOrden = max(0, $montoPaciente - $pagadoPaciente);

$codigoFormateado = formatear_codigo_orden($orden['codigo']);

$tituloPagina = 'Orden ' . $codigoFormateado . ' | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container">
<p><a href="ordenes.php">← Volver a órdenes</a></p>

<div class="page-heading">
    <div>
        <h1>Orden <?= $codigoFormateado ?></h1>
        <p class="muted">Fecha <?= escapar_html($orden['fecha_orden']) ?> · Estado <span class="badge"><?= escapar_html($orden['estado']) ?></span></p>
    </div>
    <div>
        <a class="button-link" href="orden_formulario.php?id=<?= (int) $orden['codigo'] ?>">Editar orden</a>
        <a class="button-secondary" href="orden_pdf.php?orden_id=<?= (int) $orden['codigo'] ?>" target="_blank">📄 Imprimir orden</a>
        <a class="button-secondary" href="instrucciones_pdf.php?orden_id=<?= (int) $orden['codigo'] ?>" target="_blank">📋 Instrucciones</a>
        <a class="button-secondary" href="etiqueta_muestra_pdf.php?orden_id=<?= (int) $orden['codigo'] ?>" target="_blank">🖨️ Imprimir etiquetas</a>
    </div>
</div>

<section class="detail-grid">
    <div class="module">
        <h2>Paciente</h2>
        <p><strong><?= escapar_html($orden['apellido'] . ', ' . $orden['nombre']) ?></strong></p>
        <p>DNI: <?= escapar_html($orden['dni']) ?></p>
        <p>Fecha nac.: <?= escapar_html($orden['fecha_nacimiento'] ?? '—') ?></p>
        <p>Teléfono: <?= escapar_html($orden['telefono'] ?? '—') ?></p>
        <p>Email: <?= escapar_html($orden['correo'] ?? '—') ?></p>
    </div>
    <div class="module">
        <h2>Orden <?= $codigoFormateado ?></h2>
        <p>Médico: <?= escapar_html($orden['medico']) ?></p>
        <p>Obra social: <?= escapar_html($orden['obra_social'] ?? 'Particular') ?></p>
        <?php if (!empty($orden['nro_credencial'])): ?>
            <p>Credencial: <?= escapar_html($orden['nro_credencial']) ?></p>
        <?php endif; ?>
        <?php if ($porcentaje > 0): ?>
            <p>Cobertura: <?= number_format($porcentaje, 0, ',', '.') ?>%</p>
        <?php endif; ?>
        <hr>
        <p>Total estudios: $ <?= number_format($totalOrden, 2, ',', '.') ?></p>
        <?php if ($porcentaje > 0): ?>
            <p>Obra cubre: $ <?= number_format($montoCubre, 2, ',', '.') ?></p>
        <?php endif; ?>
        <p><strong style="color:#b45309;">Paciente debe: $ <?= number_format($montoPaciente, 2, ',', '.') ?></strong></p>
        <p>Pagado del paciente: $ <?= number_format($pagadoPaciente, 2, ',', '.') ?></p>
        <p><strong>Saldo de esta orden: $ <?= number_format($saldoOrden, 2, ',', '.') ?></strong></p>
    </div>
</section>

<h2>Estudios solicitados</h2>
<div class="table-wrap">
    <table>
        <thead>
            <tr><th>Código</th><th>Estudio</th><th>Parámetros</th><th>Precio</th></tr>
        </thead>
        <tbody>
            <?php foreach ($estudios as $e): ?>
                <tr>
                    <td><?= escapar_html($e['codigo']) ?></td>
                    <td><strong><?= escapar_html($e['estudio']) ?></strong></td>
                    <td><?= escapar_html($e['parametros'] ?? '—') ?></td>
                    <td>$ <?= number_format((float) ($e['precio'] ?? 0), 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$estudios): ?>
                <tr><td colspan="4" class="muted">La orden no tiene estudios asociados.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($resultado && !empty($resultado['resultado'])): ?>
    <h2>Resultado de la orden</h2>
    <div class="module">
        <p class="muted">Cargado el <?= escapar_html($resultado['fecha_resultado'] ?? '—') ?></p>
        <pre style="white-space: pre-wrap; font-family: inherit; font-size: 0.95rem;"><?= escapar_html($resultado['resultado']) ?></pre>
        <a class="button-secondary" href="resultado_pdf.php?orden_id=<?= (int) $orden['codigo'] ?>" target="_blank">📄 Ver PDF del resultado</a>
    </div>
<?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>