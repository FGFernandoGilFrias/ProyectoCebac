<?php
/**
 * reporte_pacientes_listado.php
 * Genera un PDF con el listado de pacientes (con filtro opcional).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pdf_helper.php';
exigir_privilegio('pacientes.gestionar');

$conexion = conexion_bd();

$search = trim((string) ($_GET['q'] ?? ''));

$sql = 'SELECT p.codigo, p.apellido, p.nombre, p.dni, p.fecha_nacimiento, p.direccion,
               p.correo AS email, p.telefono, p.fecha_alta,
               os.nombre AS obra_social,
               pos.nro_credencial, pos.cobertura, pos.porcentaje_cobertura
        FROM pacientes p
        LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
        LEFT JOIN obras_sociales os ON os.codigo = pos.obra_social_codigo';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE p.dni LIKE :q1 OR p.apellido LIKE :q2 OR p.nombre LIKE :q3';
    $params['q1'] = '%' . $search . '%';
    $params['q2'] = '%' . $search . '%';
    $params['q3'] = '%' . $search . '%';
}
$sql .= ' ORDER BY p.apellido, p.nombre';

$stmt = $conexion->prepare($sql);
$stmt->execute($params);
$pacientes = $stmt->fetchAll();

$config = require __DIR__ . '/includes/config_lab.php';

ob_start();
?>
<style>
    body { font-family: Arial, sans-serif; color: #1f2937; font-size: 10px; }
    .titulo { text-align: center; color: #0b234a; font-size: 15px; font-weight: bold; letter-spacing: 2px; margin: 0 0 4px; }
    .subtitulo { text-align: center; color: #64748b; font-size: 9px; margin: 0 0 14px; }
    .filtros-box { width: 100%; border-collapse: collapse; margin-bottom: 14px; background: #f8fafc; border: 1px solid #e2e8f0; }
    .filtros-box td { padding: 6px 9px; font-size: 9px; }
    .filtros-box td strong { color: #0b234a; }
    .resumen { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .resumen td { padding: 10px; text-align: center; border: 1px solid #e2e8f0; background: #f0f9ff; }
    .resumen .numero { font-size: 20px; font-weight: bold; color: #1769aa; display: block; }
    .resumen .label { font-size: 8px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; }
    .tabla { width: 100%; border-collapse: collapse; }
    .tabla th { background: #0b234a; color: #fff; font-size: 8px; padding: 6px 7px; text-align: left; text-transform: uppercase; }
    .tabla td { padding: 5px 7px; font-size: 9px; border-bottom: 1px solid #e2e8f0; }
    .tabla tr:nth-child(even) td { background: #f8fafc; }
    .tabla .codigo { font-family: monospace; color: #075985; font-weight: bold; }
    .tabla .center { text-align: center; }
    .sin-datos { text-align: center; color: #94a3b8; font-style: italic; padding: 30px; }
</style>

<div class="titulo">LISTADO DE PACIENTES</div>
<div class="subtitulo">
    <?= $search !== ''
        ? 'Filtro: "' . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . '"'
        : 'Listado completo de pacientes' ?>
    · <?= htmlspecialchars($config['nombre'], ENT_QUOTES, 'UTF-8') ?>
</div>

<table class="filtros-box">
    <tr>
        <td style="width: 33%;"><strong>Fecha:</strong> <?= date('d/m/Y H:i') ?></td>
        <td style="width: 33%;"><strong>Filtro:</strong> <?= $search !== '' ? htmlspecialchars($search, ENT_QUOTES, 'UTF-8') : 'Ninguno' ?></td>
        <td style="width: 34%;"><strong>Total:</strong> <?= count($pacientes) ?> paciente(s)</td>
    </tr>
</table>

<?php if ($pacientes): ?>
    <table class="tabla">
        <thead>
            <tr>
                <th style="width: 6%;">Cód.</th>
                <th style="width: 22%;">Apellido y Nombre</th>
                <th style="width: 10%;">DNI</th>
                <th style="width: 10%;">F. Nac.</th>
                <th style="width: 12%;">Teléfono</th>
                <th style="width: 18%;">Obra social</th>
                <th style="width: 10%;">Credencial</th>
                <th style="width: 12%;">Cobertura</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pacientes as $p): ?>
                <tr>
                    <td class="codigo">#<?= (int) $p['codigo'] ?></td>
                    <td><strong><?= htmlspecialchars($p['apellido'] . ', ' . $p['nombre'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td><?= htmlspecialchars($p['dni'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(fecha_ddmmyyyy($p['fecha_nacimiento']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($p['telefono'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($p['obra_social'] ?? 'Particular', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($p['nro_credencial'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if ($p['cobertura']): ?>
                            <?= htmlspecialchars($p['cobertura'], ENT_QUOTES, 'UTF-8') ?>
                            (<?= number_format((float) $p['porcentaje_cobertura'], 0, ',', '.') ?>%)
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <div class="sin-datos">No hay pacientes registrados<?= $search !== '' ? ' con ese filtro' : '' ?>.</div>
<?php endif; ?>

<?php
$html = ob_get_clean();

$mpdf = crear_pdf('Listado de pacientes');
$mpdf->WriteHTML($html);
$mpdf->Output('Listado_Pacientes_' . date('Y-m-d') . '.pdf', \Mpdf\Output\Destination::INLINE);
exit;