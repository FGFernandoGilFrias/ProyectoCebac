<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('ordenes.gestionar');

$id = (int) ($_GET['id'] ?? 0);
$conexion = conexion_bd();

$stmt = $conexion->prepare(
    "SELECT o.*, p.apellido, p.nombre, p.dni, p.fecha_nacimiento, p.telefono, p.email,
            s.nombre AS obra_social
     FROM ordenes o
     JOIN pacientes p ON p.codigo = o.paciente_codigo
     LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
     LEFT JOIN obras_sociales_credenciales c ON c.codigo = pos.credencial_codigo
     LEFT JOIN obras_sociales s ON s.codigo = c.obra_social_codigo
     WHERE o.codigo = :codigo"
);
$stmt->execute(['codigo' => $id]);
$orden = $stmt->fetch();

if (!$orden) {
    http_response_code(404);
    exit('Orden no encontrada.');
}

$stmt = $conexion->prepare(
    "SELECT e.codigo, e.estudio, e.parametros, pp.precio,
            r.codigo AS resultado_codigo, r.resultado, r.fecha_resultado
     FROM estudios_orden eo
     JOIN estudios e ON e.codigo = eo.estudio_codigo
     LEFT JOIN precio_practica pp ON pp.codigo = e.practica_codigo
     LEFT JOIN resultados r ON r.orden_codigo = eo.orden_codigo
     WHERE eo.orden_codigo = :codigo
     ORDER BY e.estudio"
);
$stmt->execute(['codigo' => $id]);
$estudios = $stmt->fetchAll();

$totalOrden = 0;
foreach ($estudios as $e) {
    $totalOrden += (float) ($e['precio'] ?? 0);
}

// Cobertura del paciente
$stmt = $conexion->prepare(
    'SELECT porcentaje_cobertura
     FROM paciente_obra_social pos
     JOIN obras_sociales_credenciales c ON c.codigo = pos.credencial_codigo
     WHERE pos.paciente_codigo = :pc'
);
$stmt->execute(['pc' => (int) $orden['paciente_codigo']]);
$porcentaje = (float) ($stmt->fetchColumn() ?: 0);

$montoCubre = $totalOrden * ($porcentaje / 100);
$montoPaciente = $totalOrden - $montoCubre;

// Pago
$stmt = $conexion->prepare(
    'SELECT COALESCE(SUM(monto), 0)
     FROM caja
     WHERE paciente_codigo = :pc'
);
$stmt->execute(['pc' => (int) $orden['paciente_codigo']]);
$pagadoPaciente = (float) $stmt->fetchColumn();

$saldoOrden = max(0, $montoPaciente - $pagadoPaciente);

$codigoFormateado = formatear_codigo_orden($orden['codigo']);

$tituloPagina = 'Orden ' . $codigoFormateado . ' | CEBAC';
include __DIR__ . '/includes/header.php';
?>

<main class="container">
    <p>
        <a href="ordenes.php">← Volver a órdenes</a>
    </p>

    <div class="page-heading">
        <div>
            <h1>Orden <?= escapar_html($codigoFormateado) ?></h1>
            <p class="muted">
                Fecha <?= escapar_html($orden['fecha_orden']) ?>
                · Estado
                <span class="badge"><?= escapar_html($orden['estado']) ?></span>
            </p>
        </div>

        <div>
            <a
                class="button-link"
                href="orden_formulario.php?id=<?= (int) $orden['codigo'] ?>"
            >
                Editar orden
            </a>

            <a
                class="button-secondary"
                href="orden_pdf.php?orden_id=<?= (int) $orden['codigo'] ?>"
                target="_blank"
            >
                📄 Imprimir orden
            </a>

            <a
                class="button-secondary"
                href="etiqueta_muestra_pdf.php?orden_id=<?= (int) $orden['codigo'] ?>"
                target="_blank"
            >
                🏷️ Imprimir etiquetas
            </a>

            <a
                class="button-secondary"
                href="instrucciones_pdf.php?orden_id=<?= (int) $orden['codigo'] ?>"
                target="_blank"
            >
                📋 Instrucciones
            </a>
        </div>
    </div>

    <section class="detail-grid">
        <div class="module">
            <h2>Paciente</h2>

            <p>
                <strong>
                    <?= escapar_html($orden['apellido'] . ', ' . $orden['nombre']) ?>
                </strong>
            </p>

            <p>DNI: <?= escapar_html($orden['dni']) ?></p>
            <p>Fecha nac.: <?= escapar_html($orden['fecha_nacimiento'] ?? '—') ?></p>
            <p>Teléfono: <?= escapar_html($orden['telefono'] ?? '—') ?></p>
            <p>Email: <?= escapar_html($orden['email'] ?? '—') ?></p>
        </div>

        <div class="module">
            <h2>Orden <?= escapar_html($codigoFormateado) ?></h2>

            <p>Médico: <?= escapar_html($orden['medico']) ?></p>
            <p>
                Obra social:
                <?= escapar_html($orden['obra_social'] ?? 'Particular') ?>
            </p>

            <?php if ($porcentaje > 0): ?>
                <p>
                    Cobertura:
                    <?= number_format($porcentaje, 0, ',', '.') ?>%
                </p>
            <?php endif; ?>

            <hr>

            <p>
                Total estudios:
                $ <?= number_format($totalOrden, 2, ',', '.') ?>
            </p>

            <?php if ($porcentaje > 0): ?>
                <p>
                    Obra cubre:
                    $ <?= number_format($montoCubre, 2, ',', '.') ?>
                </p>
            <?php endif; ?>

            <p>
                <strong style="color:#b45309;">
                    Paciente debe:
                    $ <?= number_format($montoPaciente, 2, ',', '.') ?>
                </strong>
            </p>

            <p>
                Pagado del paciente:
                $ <?= number_format($pagadoPaciente, 2, ',', '.') ?>
            </p>

            <p>
                <strong>
                    Saldo de esta orden:
                    $ <?= number_format($saldoOrden, 2, ',', '.') ?>
                </strong>
            </p>
        </div>
    </section>

    <h2>Estudios solicitados</h2>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Estudio</th>
                    <th>Parámetros</th>
                    <th>Precio</th>
                    <th>Resultado</th>
                    <th>Fecha resultado</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($estudios as $e): ?>
                    <tr>
                        <td><?= escapar_html($e['codigo']) ?></td>

                        <td>
                            <strong><?= escapar_html($e['estudio']) ?></strong>
                        </td>

                        <td>
                            <?= escapar_html($e['parametros'] ?? '—') ?>
                        </td>

                        <td>
                            $ <?= number_format((float) ($e['precio'] ?? 0), 2, ',', '.') ?>
                        </td>

                        <td>
                            <?= escapar_html($e['resultado'] ?? 'Pendiente') ?>
                        </td>

                        <td>
                            <?= escapar_html($e['fecha_resultado'] ?? '—') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$estudios): ?>
                    <tr>
                        <td colspan="6" class="muted">
                            La orden no tiene estudios asociados.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>