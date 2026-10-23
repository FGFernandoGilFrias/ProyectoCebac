<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('reportes.gestionar');

$tituloPagina = 'Reportes | CEBAC';
include __DIR__ . '/includes/header.php';

$reportes = [
    [
        'titulo' => 'Prácticas realizadas',
        'descripcion' => 'Órdenes con estudios realizados en el período, con filtros por obra social y médico.',
        'href' => 'reporte_practicas_realizadas.php',
        'icono' => 'clipboard-check',
        'color' => 'primary',
    ],
    [
        'titulo' => 'Resultados por paciente',
        'descripcion' => 'Historial completo de resultados de un paciente en el período.',
        'href' => 'reporte_resultados_paciente.php',
        'icono' => 'file-earmark-person',
        'color' => 'info',
    ],
    [
        'titulo' => 'Estudios realizados',
        'descripcion' => 'Cantidad de veces que se realizó cada estudio en el período, con facturación.',
        'href' => 'reporte_estudios_realizados.php',
        'icono' => 'bar-chart-line',
        'color' => 'primary',
    ],
    [
        'titulo' => 'Muestras rechazadas',
        'descripcion' => 'Rechazos registrados en el período, con motivo y responsable. Incluye el historial de errores.',
        'href' => 'reporte_muestras_rechazadas.php',
        'icono' => 'exclamation-triangle',
        'color' => 'danger',
    ],
    [
        'titulo' => 'Liquidación mensual',
        'descripcion' => 'Planilla de órdenes médicas para enviar a la obra social a fin de mes.',
        'href' => 'reporte_liquidacion_mensual.php',
        'icono' => 'clipboard-data',
        'color' => 'success',
    ],
    [
        'titulo' => 'Comprobante de pago',
        'descripcion' => 'Imprime el comprobante de un pago registrado en caja.',
        'href' => 'comprobante_pago_listado.php',
        'icono' => 'receipt',
        'color' => 'primary',
    ],
    [
        'titulo' => 'Etiquetas de muestra',
        'descripcion' => 'Imprime las etiquetas para pegar en los tubos de muestra.',
        'href' => 'etiqueta_muestra_listado.php',
        'icono' => 'tags',
        'color' => 'warning',
    ],
];
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Reportes del sistema</h1>
        <p class="text-muted mb-0">Seleccioná un reporte para verlo con filtros de fecha.</p>
    </div>
</div>

<div class="row g-4">
    <?php foreach ($reportes as $rep): ?>
        <div class="col-md-6 col-lg-4">
            <a href="<?= escapar_html($rep['href']) ?>" class="card h-100 text-decoration-none shadow-sm border-0 border-start border-4 border-<?= escapar_html($rep['color']) ?>">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-<?= escapar_html($rep['color']) ?> bg-opacity-10 text-<?= escapar_html($rep['color']) ?> rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                            <i class="bi bi-<?= escapar_html($rep['icono']) ?> fs-4"></i>
                        </div>
                        <h2 class="h5 fw-bold mb-0 text-dark"><?= escapar_html($rep['titulo']) ?></h2>
                    </div>
                    <p class="text-muted small mb-0"><?= escapar_html($rep['descripcion']) ?></p>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>