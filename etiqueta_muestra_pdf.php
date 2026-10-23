<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pdf_helper.php';
exigir_privilegio('reportes.gestionar');

$ordenId = (int) ($_GET['orden_id'] ?? 0);
$conexion = conexion_bd();

$stmt = $conexion->prepare(
    "SELECT o.codigo AS orden_codigo, o.fecha_orden, o.medico, o.estado,
            p.apellido, p.nombre, p.dni, p.fecha_nacimiento
     FROM ordenes o
     JOIN pacientes p ON p.codigo = o.paciente_codigo
     WHERE o.codigo = :codigo"
);
$stmt->execute(['codigo' => $ordenId]);
$orden = $stmt->fetch();

if (!$orden) {
    http_response_code(404);
    exit('Orden no encontrada.');
}

// Estudios de la orden
$stmt = $conexion->prepare(
    "SELECT e.codigo, e.estudio, e.parametros
     FROM estudios_orden eo
     JOIN estudios e ON e.codigo = eo.estudio_codigo
     WHERE eo.orden_codigo = :codigo
     ORDER BY e.estudio"
);
$stmt->execute(['codigo' => $ordenId]);
$estudios = $stmt->fetchAll();

$config = require __DIR__ . '/includes/config_lab.php';

$nombrePaciente = mb_strtoupper($orden['apellido'] . ' ' . $orden['nombre'], 'UTF-8');
$codigoOrden = str_pad($orden['orden_codigo'], 4, '0', STR_PAD_LEFT);

ob_start();
?>
<style>
    @page {
        margin: 5mm;
    }
    body { font-family: Arial, sans-serif; color: #1f2937; font-size: 10px; }

    .etiqueta {
        width: 65mm;
        padding: 3mm 4mm;
        margin-bottom: 6mm;
        page-break-inside: avoid;
        text-align: center;
    }

    .paciente {
        font-size: 11px;
        font-weight: bold;
        letter-spacing: 0.5px;
        margin-bottom: 2mm;
        white-space: nowrap;
        overflow: hidden;
    }

    .barcode-box {
        margin-bottom: 1.5mm;
    }

    .codigo-orden {
        font-size: 10px;
        font-weight: bold;
        text-align: left;
        margin-bottom: 0.5mm;
    }

    .fecha {
        font-size: 10px;
        text-align: left;
        margin-bottom: 1.5mm;
    }

    .titulo-orden {
        font-size: 16px;
        font-weight: bold;
        letter-spacing: 3px;
        margin-bottom: 2mm;
    }

    .estudio {
        font-size: 11px;
        font-weight: bold;
        text-align: left;
    }

    .sin-datos { text-align: center; color: #94a3b8; font-style: italic; padding: 30px; }
</style>

<?php if ($estudios): ?>
    <?php foreach ($estudios as $e): ?>
        <?php
        // El código de barras es el código del estudio (muestra)
        $codigoEstudio = $e['codigo'];
        $codigoEstudioEscapado = htmlspecialchars($codigoEstudio, ENT_QUOTES, 'UTF-8');
        $codigoConCeros = htmlspecialchars($codigoOrden, ENT_QUOTES, 'UTF-8');
        ?>
        <div class="etiqueta">

            <!-- Nombre del paciente -->
            <div class="paciente"><?= htmlspecialchars($nombrePaciente, ENT_QUOTES, 'UTF-8') ?></div>

            <!-- Código de barras (código de muestra / estudio) -->
            <div class="barcode-box">
                <barcode code="<?= $codigoEstudioEscapado ?>" type="C128B" size="0.9" height="1.2" />
            </div>

            <!-- Código de orden (solo, sin el código de estudio) -->
            <div class="codigo-orden"><?= $codigoConCeros ?></div>

            <!-- Fecha de la orden -->
            <div class="fecha"><?= htmlspecialchars(fecha_ddmmyyyy($orden['fecha_orden']), ENT_QUOTES, 'UTF-8') ?></div>

            <!-- ORDEN -->
            <div class="titulo-orden">ORDEN</div>

            <!-- Código de estudio + nombre del estudio -->
            <div class="estudio"><?= $codigoEstudioEscapado ?> - <?= htmlspecialchars($e['estudio'], ENT_QUOTES, 'UTF-8') ?></div>

        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="sin-datos">Esta orden no tiene estudios para generar etiquetas.</div>
<?php endif; ?>

<?php
$html = ob_get_clean();
$mpdf = crear_pdf('Etiquetas - Orden ' . $codigoOrden);
$mpdf->WriteHTML($html);
$mpdf->Output('Etiquetas_' . $codigoOrden . '_' . $orden['dni'] . '.pdf', \Mpdf\Output\Destination::INLINE);
exit;