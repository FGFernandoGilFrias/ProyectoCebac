<?php
/**
 * includes/pdf_helper.php
 * Helper para generar PDFs con mPDF.
 */

require_once __DIR__ . '/../lib/mPDF/vendor/autoload.php';

/**
 * Devuelve una instancia de mPDF configurada con los datos del laboratorio.
 */
function crear_pdf(string $titulo = 'Resultado'): \Mpdf\Mpdf
{
    $config = require __DIR__ . '/config_lab.php';

    $mpdf = new \Mpdf\Mpdf([
        'mode'              => 'utf-8',
        'format'            => 'A4',
        'margin_left'       => 15,
        'margin_right'      => 15,
        'margin_top'        => 45,
        'margin_bottom'     => 30,
        'margin_header'     => 10,
        'margin_footer'     => 10,
        'default_font_size' => 11,
    ]);

    $mpdf->SetTitle($titulo);
    $mpdf->SetAuthor($config['nombre']);
    $mpdf->SetCreator('Sistema CEBAC');

    // Header (encabezado con logo)
    $mpdf->SetHTMLHeader(html_header_pdf($config));

    // Footer (pie con firma y datos)
    $mpdf->SetHTMLFooter(html_footer_pdf($config));

    return $mpdf;
}

/**
 * HTML del encabezado (logo + datos del lab).
 */
function html_header_pdf(array $config): string
{
    $logoPath = __DIR__ . '/../' . $config['logo'];
    $logoHtml = '';
    if (file_exists($logoPath)) {
        $logoHtml = '<img src="' . $logoPath . '" style="height: 55px;">';
    }

    return '
    <table style="width:100%; border-bottom: 1px solid #1769aa; padding-bottom: 8px;">
        <tr>
            <td style="width: 130px; vertical-align: middle;">' . $logoHtml . '</td>
            <td style="vertical-align: middle; text-align: right; font-family: Arial, sans-serif;">
                <div style="font-size: 15px; font-weight: bold; color: #0b234a;">' . htmlspecialchars($config['nombre'], ENT_QUOTES, 'UTF-8') . '</div>
                <div style="font-size: 9px; color: #475569;">' . htmlspecialchars($config['direccion'], ENT_QUOTES, 'UTF-8') . '</div>
                <div style="font-size: 9px; color: #475569;">Tel: ' . htmlspecialchars($config['telefono'], ENT_QUOTES, 'UTF-8') . ' · ' . htmlspecialchars($config['email'], ENT_QUOTES, 'UTF-8') . '</div>
                <div style="font-size: 9px; color: #475569;">CUIT: ' . htmlspecialchars($config['cuit'], ENT_QUOTES, 'UTF-8') . '</div>
            </td>
        </tr>
    </table>';
}

/**
 * HTML del pie (firma + datos).
 */
function html_footer_pdf(array $config): string
{
    return '
    <table style="width:100%; border-top: 1px solid #cbd5e1; padding-top: 6px; font-family: Arial, sans-serif;">
        <tr>
            <td style="font-size: 8px; color: #64748b; width: 60%;">
                ' . htmlspecialchars($config['horario'], ENT_QUOTES, 'UTF-8') . '
            </td>
            <td style="font-size: 9px; color: #0b234a; text-align: right; width: 40%;">
                <div style="border-top: 1px solid #0b234a; padding-top: 3px; margin-bottom: 2px;"></div>
                <strong>' . htmlspecialchars($config['firma']['nombre'], ENT_QUOTES, 'UTF-8') . '</strong><br>
                ' . htmlspecialchars($config['firma']['cargo'], ENT_QUOTES, 'UTF-8') . '
            </td>
        </tr>
    </table>';
}