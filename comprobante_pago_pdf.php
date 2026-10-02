<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pdf_helper.php';
exigir_privilegio('reportes.gestionar');

$pagoId = (int) ($_GET['pago_id'] ?? 0);
$conexion = conexion_bd();

$stmt = $conexion->prepare(
    "SELECT c.codigo AS pago_codigo, c.monto, c.metodo_pago, c.fecha_pago,
            p.codigo AS paciente_codigo, p.apellido, p.nombre, p.dni,
            p.telefono, p.email, p.direccion
     FROM caja c
     JOIN pacientes p ON p.codigo = c.paciente_codigo
     WHERE c.codigo = :codigo"
);
$stmt->execute(['codigo' => $pagoId]);
$pago = $stmt->fetch();

if (!$pago) {
    http_response_code(404);
    exit('Pago no encontrado.');
}

// Calcular total a cargo del paciente (con cobertura)
$stmt = $conexion->prepare(
    "SELECT COALESCE(SUM(pp.precio), 0) AS total_bruto
     FROM ordenes o
     JOIN estudios_orden eo ON eo.orden_codigo = o.codigo
     JOIN estudios e ON e.codigo = eo.estudio_codigo
     JOIN precio_practica pp ON pp.codigo = e.practica_codigo
     WHERE o.paciente_codigo = :pc"
);
$stmt->execute(['pc' => (int) $pago['paciente_codigo']]);
$totalBruto = (float) $stmt->fetchColumn();

// Obtener cobertura
$stmt = $conexion->prepare('SELECT porcentaje_cobertura FROM paciente_obra_social pos JOIN obras_sociales_credenciales c ON c.codigo = pos.credencial_codigo WHERE pos.paciente_codigo = :pc');
$stmt->execute(['pc' => (int) $pago['paciente_codigo']]);
$porcentaje = (float) ($stmt->fetchColumn() ?: 0);

$totalACargo = $totalBruto * (1 - $porcentaje / 100);

// Total pagado acumulado
$stmt = $conexion->prepare('SELECT COALESCE(SUM(monto), 0) FROM caja WHERE paciente_codigo = :pc');
$stmt->execute(['pc' => (int) $pago['paciente_codigo']]);
$totalPagado = (float) $stmt->fetchColumn();

$saldo = max(0, $totalACargo - $totalPagado);

$config = require __DIR__ . '/includes/config_lab.php';

function fecha_ddmmyyyy(?string $fecha): string
{
    if (!$fecha) return '—';
    $partes = explode('-', substr($fecha, 0, 10));
    return count($partes) === 3 ? $partes[2] . '/' . $partes[1] . '/' . $partes[0] : $fecha;
}

function numero_a_letras(float $numero): string
{
    // Conversión simple
    $entero = (int) $numero;
    $centavos = (int) round(($numero - $entero) * 100);

    $unidades = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
                 'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete',
                 'dieciocho', 'diecinueve', 'veinte'];
    $decenas = ['', '', 'veinti', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
    $centenas = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

    if ($entero === 0) return 'cero pesos con ' . $centavos . '/100';

    $texto = '';

    if ($entero >= 1000) {
        $miles = (int) ($entero / 1000);
        $resto = $entero % 1000;
        $texto .= ($miles === 1 ? 'mil' : $unidades[$miles] . ' mil') . ' ';
        $entero = $resto;
    }

    if ($entero >= 100) {
        $c = (int) ($entero / 100);
        $texto .= $centenas[$c] . ' ';
        $entero = $entero % 100;
    }

    if ($entero <= 20) {
        $texto .= $unidades[$entero];
    } elseif ($entero < 30) {
        $texto .= 'veinti' . $unidades[$entero - 20];
    } else {
        $d = (int) ($entero / 10);
        $u = $entero % 10;
        $texto .= $decenas[$d] . ($u > 0 ? ' y ' . $unidades[$u] : '');
    }

    return trim($texto) . ' pesos con ' . str_pad((string) $centavos, 2, '0', STR_PAD_LEFT) . '/100';
}

ob_start();
?>
<style>
    body { font-family: Arial, sans-serif; color: #1f2937; font-size: 11px; }
    .titulo { text-align: center; color: #0b234a; font-size: 16px; font-weight: bold; letter-spacing: 2px; margin: 0 0 4px; }
    .subtitulo { text-align: center; color: #64748b; font-size: 10px; margin: 0 0 18px; }

    .recuadro { border: 2px solid #0b234a; padding: 20px; margin-top: 10px; }

    .fila-info { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .fila-info td { padding: 5px 0; font-size: 11px; vertical-align: top; }
    .fila-info td:first-child { width: 30%; color: #475569; }
    .fila-info td:last-child { font-weight: bold; color: #0b234a; }

    .monto-box { background: #f0f9ff; border: 1px solid #bae6fd; padding: 16px 20px; margin: 18px 0; text-align: center; }
    .monto-box .label { font-size: 10px; color: #075985; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
    .monto-box .monto { font-size: 26px; font-weight: bold; color: #0b234a; }
    .monto-box .letras { font-size: 10px; color: #64748b; margin-top: 6px; font-style: italic; }

    .pie-firmas { width: 100%; border-collapse: collapse; margin-top: 50px; }
    .pie-firmas td { padding: 0 20px; text-align: center; vertical-align: bottom; }
    .pie-firmas .linea { border-top: 1px solid #0b234a; padding-top: 6px; font-size: 9px; color: #475569; }

    .info-contacto { margin-top: 30px; padding: 12px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; font-size: 9px; color: #64748b; }
</style>

<div class="titulo">COMPROBANTE DE PAGO</div>
<div class="subtitulo">
    Nº <?= str_pad((string) $pago['pago_codigo'], 4, '0', STR_PAD_LEFT) ?> ·
    Fecha: <?= htmlspecialchars(fecha_ddmmyyyy($pago['fecha_pago']), ENT_QUOTES, 'UTF-8') ?>
</div>

<div class="recuadro">
    <table class="fila-info">
        <tr>
            <td>Recibimos de:</td>
            <td><?= htmlspecialchars($pago['apellido'] . ', ' . $pago['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <td>DNI:</td>
            <td><?= htmlspecialchars($pago['dni'], ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php if (!empty($pago['telefono'])): ?>
        <tr>
            <td>Teléfono:</td>
            <td><?= htmlspecialchars($pago['telefono'], ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td>En concepto de:</td>
            <td>Pago de prestación de laboratorio</td>
        </tr>
        <tr>
            <td>Medio de pago:</td>
            <td><?= htmlspecialchars($pago['metodo_pago'], ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
    </table>

    <div class="monto-box">
        <div class="label">Importe abonado</div>
        <div class="monto">$ <?= number_format((float) $pago['monto'], 2, ',', '.') ?></div>
        <div class="letras"><?= htmlspecialchars(numero_a_letras((float) $pago['monto']), ENT_QUOTES, 'UTF-8') ?></div>
    </div>

    <table class="fila-info">
        <tr>
            <td>Total a cargo del paciente:</td>
            <td>$ <?= number_format($totalACargo, 2, ',', '.') ?></td>
        </tr>
        <tr>
            <td>Total abonado hasta la fecha:</td>
            <td>$ <?= number_format($totalPagado, 2, ',', '.') ?></td>
        </tr>
        <tr>
            <td>Saldo pendiente:</td>
            <td style="color: <?= $saldo > 0 ? '#991b1b' : '#166534' ?>;">
                $ <?= number_format($saldo, 2, ',', '.') ?>
            </td>
        </tr>
    </table>

    <table class="pie-firmas">
        <tr>
            <td style="width: 50%;">
                <div class="linea">Firma del paciente</div>
            </td>
            <td style="width: 50%;">
                <div class="linea">
                    <?= htmlspecialchars($config['nombre'], ENT_QUOTES, 'UTF-8') ?><br>
                    <?= htmlspecialchars($config['cuit'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            </td>
        </tr>
    </table>
</div>

<div class="info-contacto">
    <?= htmlspecialchars($config['direccion'], ENT_QUOTES, 'UTF-8') ?> ·
    Tel: <?= htmlspecialchars($config['telefono'], ENT_QUOTES, 'UTF-8') ?> ·
    <?= htmlspecialchars($config['email'], ENT_QUOTES, 'UTF-8') ?><br>
    <span style="color: #94a3b8;">Comprobante generado electrónicamente el <?= date('d/m/Y H:i') ?></span>
</div>

<?php
$html = ob_get_clean();

$mpdf = crear_pdf('Comprobante de pago Nº ' . str_pad((string) $pago['pago_codigo'], 4, '0', STR_PAD_LEFT));
$mpdf->WriteHTML($html);
$mpdf->Output('Comprobante_Pago_' . str_pad((string) $pago['pago_codigo'], 4, '0', STR_PAD_LEFT) . '_' . $pago['dni'] . '.pdf', \Mpdf\Output\Destination::INLINE);
exit;