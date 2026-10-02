<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pdf_helper.php';
exigir_privilegio('reportes.gestionar');

$conexion = conexion_bd();

$desde = trim((string) ($_GET['desde'] ?? date('Y-m-01')));
$hasta = trim((string) ($_GET['hasta'] ?? date('Y-m-d')));
$obraSocialId = (int) ($_GET['obra_social_id'] ?? 0);
$estudioId = (int) ($_GET['estudio_id'] ?? 0);

function fecha_ddmmyyyy(?string $fecha): string
{
    if (!$fecha) return '—';
    $partes = explode('-', substr($fecha, 0, 10));
    return count($partes) === 3 ? $partes[2] . '/' . $partes[1] . '/' . $partes[0] : $fecha;
}

$sql = "SELECT he.codigo AS historial_codigo, he.error_muestra,
               r.fecha_resultado,
               o.codigo AS orden_numero, o.fecha_orden,
               p.apellido, p.nombre, p.dni,
               e.codigo AS estudio_codigo, e.estudio AS nombre_estudio,
               os.nombre AS obra_social
        FROM historial_errores he
        JOIN resultados r ON r.codigo = he.resultado_codigo
        JOIN ordenes o ON o.codigo = r.orden_codigo
        JOIN pacientes p ON p.codigo = o.paciente_codigo
        JOIN estudios_orden eo ON eo.orden_codigo = o.codigo
        JOIN estudios e ON e.codigo = eo.estudio_codigo
        LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
        LEFT JOIN obras_sociales_credenciales c ON c.codigo = pos.credencial_codigo
        LEFT JOIN obras_sociales os ON os.codigo = c.obra_social_codigo
        WHERE he.error_muestra LIKE 'RECHAZADO%'
          AND r.fecha_resultado BETWEEN :desde AND :hasta";
$params = ['desde' => $desde, 'hasta' => $hasta];

if ($estudioId > 0) {
    $sql .= ' AND e.codigo = :estudio_codigo';
    $params['estudio_codigo'] = $estudioId;
}
if ($obraSocialId > 0) {
    $sql .= ' AND c.obra_social_codigo = :obra_social';
    $params['obra_social'] = $obraSocialId;
}

$sql .= ' ORDER BY r.fecha_resultado DESC';

$stmt = $conexion->prepare($sql);
$stmt->execute($params);
$rechazos = $stmt->fetchAll();

$total = count($rechazos);

// Top motivos
$conteoMotivos = [];
foreach ($rechazos as $r) {
    $m = trim(preg_replace('/^RECHAZADO[:\s-]*/i', '', $r['error_muestra']));
    if ($m === '') $m = 'Sin motivo';
    $conteoMotivos[$m] = ($conteoMotivos[$m] ?? 0) + 1;
}
arsort($conteoMotivos);
$topMotivos = array_slice($conteoMotivos, 0, 5, true);

// Top estudios
$conteoEstudios = [];
foreach ($rechazos as $r) {
    $k = $r['nombre_estudio'] . ' (' . $r['estudio_codigo'] . ')';
    $conteoEstudios[$k] = ($conteoEstudios[$k] ?? 0) + 1;
}
arsort($conteoEstudios);
$topEstudios = array_slice($conteoEstudios, 0, 5, true);

$obraSocialNombre = 'Todas';
if ($obraSocialId > 0) {
    $s = $conexion->prepare('SELECT nombre FROM obras_sociales WHERE codigo = :c');
    $s->execute(['c' => $obraSocialId]);
    $obraSocialNombre = (string) ($s->fetchColumn() ?: '—');
}
$estudioNombre = 'Todos';
if ($estudioId > 0) {
    $s = $conexion->prepare('SELECT estudio FROM estudios WHERE codigo = :c');
    $s->execute(['c' => $estudioId]);
    $estudioNombre = (string) ($s->fetchColumn() ?: '—');
}

$config = require __DIR__ . '/includes/config_lab.php';

ob_start();
?>
<style>
    body { font-family: Arial, sans-serif; color: #1f2937; font-size: 10px; }
    .titulo { text-align: center; color: #0b234a; font-size: 15px; font-weight: bold; margin: 0 0 4px; letter-spacing: 2px; }
    .subtitulo { text-align: center; color: #64748b; font-size: 9px; margin: 0 0 14px; }
    .filtros-box { width: 100%; border-collapse: collapse; margin-bottom: 14px; background: #f8fafc; border: 1px solid #e2e8f0; }
    .filtros-box td { padding: 6px 9px; font-size: 9px; }
    .filtros-box td strong { color: #0b234a; }
    .resumen { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .resumen td { padding: 10px; text-align: center; border: 1px solid #e2e8f0; background: #fef2f2; }
    .resumen .numero { font-size: 22px; font-weight: bold; color: #991b1b; display: block; }
    .resumen .label { font-size: 8px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; }
    .tabla { width: 100%; border-collapse: collapse; }
    .tabla th { background: #0b234a; color: #fff; font-size: 8px; padding: 6px 7px; text-align: left; text-transform: uppercase; }
    .tabla td { padding: 5px 7px; font-size: 9px; border-bottom: 1px solid #e2e8f0; }
    .tabla tr:nth-child(even) td { background: #f8fafc; }
    .tabla .codigo { font-family: monospace; color: #075985; font-weight: bold; }
    .tabla .motivo { color: #991b1b; }
    .top-box { width: 100%; border-collapse: collapse; margin-top: 18px; }
    .top-box td { padding: 0; vertical-align: top; width: 50%; }
    .top-box .inner { padding: 12px 14px; background: #f8fafc; border: 1px solid #e2e8f0; margin-right: 6px; }
    .top-box .inner.right { margin-right: 0; margin-left: 6px; }
    .top-box h3 { margin: 0 0 8px; color: #0b234a; font-size: 11px; }
    .top-box ol { padding-left: 18px; margin: 0; }
    .top-box li { font-size: 10px; padding: 3px 0; }
    .top-box li strong { color: #991b1b; }
    .sin-datos { text-align: center; color: #94a3b8; font-style: italic; padding: 30px; }
</style>

<div class="titulo">REPORTE DE MUESTRAS RECHAZADAS</div>
<div class="subtitulo">Período: <?= htmlspecialchars(fecha_ddmmyyyy($desde), ENT_QUOTES, 'UTF-8') ?> al <?= htmlspecialchars(fecha_ddmmyyyy($hasta), ENT_QUOTES, 'UTF-8') ?></div>

<table class="filtros-box">
    <tr>
        <td style="width: 33%;"><strong>Desde:</strong> <?= htmlspecialchars(fecha_ddmmyyyy($desde), ENT_QUOTES, 'UTF-8') ?></td>
        <td style="width: 33%;"><strong>Hasta:</strong> <?= htmlspecialchars(fecha_ddmmyyyy($hasta), ENT_QUOTES, 'UTF-8') ?></td>
        <td style="width: 34%;"><strong>Generado:</strong> <?= date('d-m-Y H:i') ?></td>
    </tr>
    <tr>
        <td><strong>Obra social:</strong> <?= htmlspecialchars($obraSocialNombre, ENT_QUOTES, 'UTF-8') ?></td>
        <td colspan="2"><strong>Estudio:</strong> <?= htmlspecialchars($estudioNombre, ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
</table>

<table class="resumen">
    <tr>
        <td>
            <span class="numero"><?= $total ?></span>
            <span class="label">Total de rechazos</span>
        </td>
        <td>
            <span class="numero"><?= count($topMotivos) ?></span>
            <span class="label">Motivos distintos</span>
        </td>
        <td>
            <span class="numero"><?= count($topEstudios) ?></span>
            <span class="label">Estudios afectados</span>
        </td>
    </tr>
</table>

<?php if ($rechazos): ?>
    <table class="tabla">
        <thead>
            <tr>
                <th style="width: 10%;">Fecha</th>
                <th style="width: 10%;">Orden</th>
                <th style="width: 20%;">Paciente</th>
                <th style="width: 10%;">DNI</th>
                <th style="width: 20%;">Estudio</th>
                <th style="width: 15%;">Obra social</th>
                <th style="width: 15%;">Motivo</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rechazos as $r): ?>
                <tr>
                    <td><?= htmlspecialchars(fecha_ddmmyyyy($r['fecha_resultado']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="codigo"><?= htmlspecialchars(formatear_codigo_orden($r['orden_numero']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($r['apellido'] . ', ' . $r['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($r['dni'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($r['nombre_estudio'] . ' (' . $r['estudio_codigo'] . ')', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($r['obra_social'] ?? 'Particular', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="motivo"><?= htmlspecialchars(preg_replace('/^RECHAZADO[:\s-]*/i', '', $r['error_muestra']), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table class="top-box">
        <tr>
            <td>
                <div class="inner">
                    <h3>Top motivos de rechazo</h3>
                    <?php if ($topMotivos): ?>
                        <ol>
                            <?php foreach ($topMotivos as $motivo => $cant): ?>
                                <li><strong><?= (int) $cant ?></strong> — <?= htmlspecialchars($motivo, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else: ?>
                        <p>—</p>
                    <?php endif; ?>
                </div>
            </td>
            <td>
                <div class="inner right">
                    <h3>Top estudios rechazados</h3>
                    <?php if ($topEstudios): ?>
                        <ol>
                            <?php foreach ($topEstudios as $est => $cant): ?>
                                <li><strong><?= (int) $cant ?></strong> — <?= htmlspecialchars($est, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else: ?>
                        <p>—</p>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    </table>
<?php else: ?>
    <div class="sin-datos">No se registraron rechazos en el período seleccionado.</div>
<?php endif; ?>

<?php
$html = ob_get_clean();
$mpdf = crear_pdf('Reporte de muestras rechazadas');
$mpdf->WriteHTML($html);
$mpdf->Output('Reporte_Rechazos_' . $desde . '_' . $hasta . '.pdf', \Mpdf\Output\Destination::INLINE);
exit;