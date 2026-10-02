<?php
/**
 * etiqueta_muestra_pdf.php
 * Genera UNA etiqueta por cada estudio de la orden.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/pdf_helper.php';

$pdo = conexion_bd();

// ---------------------------------------------------------------
// 1. Validar parámetro
// ---------------------------------------------------------------
$ordenId = isset($_GET['orden_id']) ? (int)$_GET['orden_id'] : 0;

if ($ordenId <= 0) {
    die('Falta el parámetro orden_id.');
}

// ---------------------------------------------------------------
// 2. Traer la orden + paciente
// ---------------------------------------------------------------
$sql = "SELECT o.codigo, o.fecha_orden, o.medico, o.estado,
               p.apellido, p.nombre
        FROM ordenes o
        INNER JOIN pacientes p ON p.codigo = o.paciente_codigo
        WHERE o.codigo = :id
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $ordenId]);
$orden = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orden) {
    die('Orden no encontrada.');
}

// ---------------------------------------------------------------
// 3. Traer los estudios de la orden
// ---------------------------------------------------------------
$sqlEst = "SELECT estudio_codigo
           FROM estudios_orden
           WHERE orden_codigo = :id
           ORDER BY estudio_codigo";
$stmtEst = $pdo->prepare($sqlEst);
$stmtEst->execute([':id' => $ordenId]);
$estudios = $stmtEst->fetchAll(PDO::FETCH_ASSOC);

if (!$estudios) {
    die('La orden no tiene estudios.');
}

// ---------------------------------------------------------------
// 4. Datos generales
// ---------------------------------------------------------------
$codigoOrden = str_pad($orden['codigo'], 4, '0', STR_PAD_LEFT);

$nombrePaciente = mb_strtoupper(
    trim($orden['apellido'] . ' ' . $orden['nombre']),
    'UTF-8'
);

$fecha = date('d/m/Y', strtotime($orden['fecha_orden']));

// ---------------------------------------------------------------
// 5. Crear el PDF (etiqueta un poco más alta)
// ---------------------------------------------------------------
$mpdf = new \Mpdf\Mpdf([
    'mode'              => 'utf-8',
    'format'            => [60, 50], // 60mm ancho x 50mm alto
    'margin_left'       => 2,
    'margin_right'      => 2,
    'margin_top'        => 2,
    'margin_bottom'     => 2,
    'margin_header'     => 0,
    'margin_footer'     => 0,
    'default_font_size' => 9,
]);

// ---------------------------------------------------------------
// 6. Una etiqueta por cada estudio
// ---------------------------------------------------------------
foreach ($estudios as $e) {

    $codigoEstudio = $e['estudio_codigo'];
    $codigoEstudioEscapado = htmlspecialchars($codigoEstudio, ENT_QUOTES, 'UTF-8');

    $html = '
    <div style="font-family: Arial, sans-serif; text-align: center; padding: 0;">

        <!-- Nombre del paciente -->
        <div style="
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.5px;
            margin-bottom: 1.5mm;
            white-space: nowrap;
            overflow: hidden;
        ">
            ' . htmlspecialchars($nombrePaciente, ENT_QUOTES, 'UTF-8') . '
        </div>

        <!-- Código de barras -->
        <div style="margin-bottom: 1.5mm;">
            <barcode code="' . $codigoEstudioEscapado . '" type="C128B" size="0.9" height="1.1" />
        </div>

        <!-- Código orden - código estudio -->
        <div style="
            font-size: 9px;
            font-weight: bold;
            text-align: left;
            margin-bottom: 0.5mm;
        ">
            ' . htmlspecialchars($codigoOrden, ENT_QUOTES, 'UTF-8') . ' - ' . $codigoEstudioEscapado . '
        </div>

        <!-- Fecha -->
        <div style="font-size: 9px; text-align: left; margin-bottom: 1mm;">
            ' . htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8') . '
        </div>

        <!-- ORDEN -->
        <div style="
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 2px;
            margin-bottom: 1.5mm;
        ">
            ORDEN
        </div>

        <!-- COD: código de estudio -->
        <div style="font-size: 10px; font-weight: bold; text-align: left;">
            COD: ' . $codigoEstudioEscapado . '
        </div>

    </div>
    ';

    $mpdf->AddPage();
    $mpdf->WriteHTML($html);
}

$mpdf->Output('etiquetas_orden_' . $codigoOrden . '.pdf', \Mpdf\Output\Destination::INLINE);