<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('pacientes.gestionar');

$search = trim((string) ($_GET['q'] ?? ''));
$consultaSql = 'SELECT p.codigo, p.apellido, p.nombre, p.dni, p.fecha_nacimiento, p.telefono,
                       os.nombre AS obra_social, c.nro_credencial, c.cobertura, c.porcentaje_cobertura
                FROM pacientes p
                LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
                LEFT JOIN obras_sociales_credenciales c ON c.codigo = pos.credencial_codigo
                LEFT JOIN obras_sociales os ON os.codigo = c.obra_social_codigo';
$params = [];
if ($search !== '') {
    $consultaSql .= ' WHERE p.dni LIKE :q OR p.apellido LIKE :q OR p.nombre LIKE :q';
    $params['q'] = '%' . $search . '%';
}
$consultaSql .= ' ORDER BY p.apellido, p.nombre LIMIT 100';
$consulta = conexion_bd()->prepare($consultaSql);
$consulta->execute($params);
$pacientes = $consulta->fetchAll();
$mensaje = mensaje_flash();
$tituloPagina = 'Pacientes | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container">
<div class="page-heading">
    <div>
        <h1>Pacientes</h1>
        <p class="muted">Registro y consulta de pacientes.</p>
    </div>
    <a class="button-link" href="paciente_formulario.php">Nuevo paciente</a>
</div>
<?php if ($mensaje): ?><div class="alert success-alert"><?= escapar_html($mensaje) ?></div><?php endif; ?>
<form class="search-form" method="get">
    <input name="q" value="<?= escapar_html($search) ?>" placeholder="Buscar por DNI, apellido o nombre">
    <button type="submit">Buscar</button>
</form>
<div class="table-wrap">
    <table>
        <thead>
            <tr><th>Código</th><th>Paciente</th><th>DNI</th><th>Fecha nac.</th><th>Teléfono</th><th>Obra social</th><th>Credencial</th><th>Cobertura</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($pacientes as $paciente): ?>
                <tr>
                    <td><?= (int) $paciente['codigo'] ?></td>
                    <td><strong><?= escapar_html($paciente['apellido'] . ', ' . $paciente['nombre']) ?></strong></td>
                    <td><?= escapar_html($paciente['dni']) ?></td>
                    <td><?= escapar_html($paciente['fecha_nacimiento'] ?? '—') ?></td>
                    <td><?= escapar_html($paciente['telefono'] ?? '—') ?></td>
                    <td><?= escapar_html($paciente['obra_social'] ?? 'Particular') ?></td>
                    <td><?= escapar_html($paciente['nro_credencial'] ?? '—') ?></td>
                    <td>
                        <?php if ($paciente['cobertura']): ?>
                            <span class="badge"><?= escapar_html($paciente['cobertura']) ?></span>
                            <br><small class="muted"><?= number_format((float) $paciente['porcentaje_cobertura'], 0, ',', '.') ?>%</small>
                        <?php else: ?>
                            <small class="muted">—</small>
                        <?php endif; ?>
                    </td>
                    <td><a href="paciente_formulario.php?id=<?= (int) $paciente['codigo'] ?>">Editar</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pacientes): ?>
                <tr><td colspan="9" class="muted">No hay pacientes registrados.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>