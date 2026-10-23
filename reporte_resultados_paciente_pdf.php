<?php
/**
 * reporte_resultados_paciente_pdf.php
 * PDF del historial de resultados por paciente.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pdf_helper.php';
exigir_privilegio('reportes.gestionar');

$conexion = conexion_bd();

$pacienteId = (int) ($_GET['paciente_id'] ?? 0);
$desde = trim((string) ($_GET['desde'] ?? date('Y-m-01')));
$hasta = trim((string) ($_GET['hasta'] ?? date('Y-m-d')));

if ($pacienteId <= 0) {
    exit('Falta paciente_id.');
}

$stmt = $conexion->prepare('SELECT codigo, apellido, nombre, dni, fecha_nacimiento FROM pacientes WHERE codigo = :codigo');
$stmt->execute(['codigo' => $pacienteId]);
$paciente = $stmt->fetch();

if (!$paciente) {
    exit('Paciente no encontrado.');
}

$sql = "SELECT o.codigo AS orden_codigo, o.fecha_orden, o.medico, o.estado,
               r.codigo AS resultado_codigo, r.resultado, r.fecha_resultado,
               os.nombre AS obra_social
        FROM ordenes o
        LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = o.paciente_codigo
        LEFT JOIN obras_sociales os ON os.codigo = pos.obra_social_codigo
        LEFT JOIN resultados r ON r.orden_codigo = o.codigo
        WHERE o.paciente_codigo = :paciente
          AND o.fecha_orden BETWEEN :desde AND :hasta
        ORDER BY o.fecha_orden DESC, o.codigo DESC";

$stmt = $conexion->prepare($sql);
$stmt->execute([
    'paciente' => $pacienteId,
    'desde' => $desde,
    'hasta' => $hasta,
]);
$ordenes = $stmt->fetchAll();

$config = require __DIR__ . '/includes/config_lab.php';

ob_start();
?>
<style>
    body { font-family: Arial, sans-serif; color: #1f2937; font-size: 10px; }
    .titulo { text-align: center; color: #0b234a; font-size: 15px; font-weight: bold; letter-spacing: 2px; margin: 0 0 4px; }
    .subtitulo { text-align: center; color: #64748b; font-size: 9px; margin: 0 0 14px; }
    .paciente-box { width: 100%; border-collapse: collapse; margin-bottom: 14px; background: #f8fafc; border: 1px solid #e2e8f0; }
    .paciente-box td { padding: 7px 10px; font-size: 10px; }
    .paciente-box td strong { color: #0b234a; }
    .tabla { width: 100%; border-collapse: collapse; }
    .tabla th { background: #0b234a; color: #fff; font-size: 8px; padding: 6px 7px; text-align: left; text-transform: uppercase; }
    .tabla td { padding: 6px 7px; font-size: 9px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    .tabla tr:nth-child(even) td { background: #f8fafc; }
    .tabla .codigo { font-family: monospace; color: #075985; font-weight: bold; }
    .resultado-texto { font-size: 8px; color: #334155; white-space: pre-line; }
    .sin-datos { text-align: center; color: #94a3b8; font-style: italic; padding: 25px; }
</style>

<div class="titulo">HISTORIAL DE RESULTADOS</div>
<div class="subtitulo">Período: <?= htmlspecialchars(fecha_ddmmyyyy($desde), ENT_QUOTES, 'UTF-8') ?> al <?= htmlspecialchars(fecha_ddmmyyyy($hasta), ENT_QUOTES, 'UTF-8') ?></div>

<table class="paciente-box">
    <tr>
        <td style="width: 50%;"><strong>Paciente:</strong> <?= htmlspecialchars($paciente['apellido'] . ', ' . $paciente['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
        <td style="width: 25%;"><strong>DNI:</strong> <?= htmlspecialchars($paciente['dni'], ENT_QUOTES, 'UTF-8') ?></td>
        <td style="width: 25%;"><strong>F. Nac.:</strong> <?= htmlspecialchars(fecha_ddmmyyyy($paciente['fecha_nacimiento']), ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <tr>
        <td colspan="2"><strong>Generado:</strong> <?= date('d/m/Y H:i') ?></td>
        <td><strong>Total órdenes:</strong> <?= count($ordenes) ?></td>
    </tr>
</table>

<?php if ($ordenes): ?>
    <table class="tabla">
        <thead>
            <tr>
                <th style="width: 10%;">F. Resultado</th>
                <th style="width: 8%;">Orden</th>
                <th style="width: 9%;">F. Orden</th>
                <th style="width: 12%;">Médico</th>
                <th style="width: 12%;">Obra social</th>
                <th style="width: 41%;">Resultado</th>
                <th style="width: 8%;">Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ordenes as $o): ?>
                <tr>
                    <td><?= htmlspecialchars(fecha_ddmmyyyy($o['fecha_resultado']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="codigo">#<?= htmlspecialchars(formatear_codigo_orden($o['orden_codigo']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(fecha_ddmmyyyy($o['fecha_orden']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($o['medico'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($o['obra_social'] ?? 'Particular', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="resultado-texto"><?= htmlspecialchars($o['resultado'] ?? 'Sin cargar', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($o['estado'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <div class="sin-datos">No hay resultados para ese paciente en el período.</div>
<?php endif; ?>

<?php
$html = ob_get_clean();
$mpdf = crear_pdf('Resultados del paciente');
$mpdf->WriteHTML($html);
$mpdf->Output('Resultados_' . preg_replace('/\s+/', '_', $paciente['apellido']) . '_' . $desde . '_' . $hasta . '.pdf', \Mpdf\Output\Destination::INLINE);
exit;