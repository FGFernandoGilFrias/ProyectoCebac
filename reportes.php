<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('reportes.gestionar');

$tituloPagina = 'Reportes | CEBAC';
include __DIR__ . '/includes/header.php';

$reportes = [
    [
        'titulo' => 'Estudios realizados',
        'descripcion' => 'Cantidad de veces que se realizó cada estudio en el período, con facturación.',
        'href' => 'reporte_estudios_realizados.php',
        'icono' => '📊',
        'color' => 'azul',
    ],
    [
        'titulo' => 'Muestras rechazadas',
        'descripcion' => 'Rechazos registrados en el período, con motivo y responsable.',
        'href' => 'reporte_muestras_rechazadas.php',
        'icono' => '⚠️',
        'color' => 'rojo',
    ],
    [
        'titulo' => 'Liquidación mensual',
        'descripcion' => 'Planilla de órdenes médicas para enviar a la obra social a fin de mes.',
        'href' => 'reporte_liquidacion_mensual.php',
        'icono' => '📋',
        'color' => 'verde',
    ],
    [
        'titulo' => 'Comprobante de pago',
        'descripcion' => 'Imprime el comprobante de un pago registrado en caja.',
        'href' => 'comprobante_pago_listado.php',
        'icono' => '📄',
        'color' => 'azul',
    ],
    [
        'titulo' => 'Etiquetas de muestra',
        'descripcion' => 'Imprime las etiquetas para pegar en los tubos de muestra.',
        'href' => 'etiqueta_muestra_listado.php',
        'icono' => '🏷️',
        'color' => 'naranja',
    ],
    /*[
        'titulo' => 'Comprobante de muestra actualizada',
        'descripcion' => 'Comprobante para el paciente que vuelve a traer una muestra.',
        'href' => 'comprobante_muestra_listado.php',
        'icono' => '📝',
        'color' => 'naranja',
    ],
    [
        'titulo' => 'Instrucciones de análisis',
        'descripcion' => 'Imprime las instrucciones que se le dan al paciente al crear la orden.',
        'href' => 'instrucciones_listado.php',
        'icono' => '📖',
        'color' => 'verde',
    ],*/
];
?>
<main class="container">
<div class="page-heading">
    <div>
        <span class="eyebrow">Análisis</span>
        <h1>Reportes del sistema</h1>
        <p class="muted">Seleccioná un reporte para verlo con filtros de fecha.</p>
    </div>
</div>

<section class="reportes-grid">
    <?php foreach ($reportes as $rep): ?>
        <a class="reporte-card reporte-card-<?= escapar_html($rep['color']) ?>" href="<?= escapar_html($rep['href']) ?>">
            <div class="reporte-card-icon"><?= $rep['icono'] ?></div>
            <h2><?= escapar_html($rep['titulo']) ?></h2>
            <p><?= escapar_html($rep['descripcion']) ?></p>
            <span class="reporte-card-action">Ver reporte →</span>
        </a>
    <?php endforeach; ?>
</section>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>