<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('pacientes.gestionar');

$search = trim((string) ($_GET['q'] ?? ''));
$consultaSql = 'SELECT p.*, s.nombre AS nombre_obra_social FROM pacientes p LEFT JOIN obras_sociales s ON s.id = p.obra_social_id';
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
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Pacientes | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main class="container"><div class="page-heading"><div><h1>Pacientes</h1><p class="muted">Registro y consulta de pacientes.</p></div><a class="button-link" href="paciente_formulario.php">Nuevo paciente</a></div><?php if ($mensaje): ?><div class="alert success-alert"><?= escapar_html($mensaje) ?></div><?php endif; ?><form class="search-form" method="get"><input name="q" value="<?= escapar_html($search) ?>" placeholder="Buscar por DNI o nombre"><button type="submit">Buscar</button></form><div class="table-wrap"><table><thead><tr><th>Código</th><th>Paciente</th><th>DNI</th><th>Fecha nacimiento</th><th>Obra social</th><th></th></tr></thead><tbody><?php foreach ($pacientes as $paciente): ?><tr><td><?= escapar_html($paciente['codigo']) ?></td><td><?= escapar_html($paciente['apellido'] . ', ' . $paciente['nombre']) ?></td><td><?= escapar_html($paciente['dni']) ?></td><td><?= escapar_html($paciente['fecha_nacimiento']) ?></td><td><?= escapar_html($paciente['nombre_obra_social'] ?? 'Particular') ?></td><td><a href="paciente_formulario.php?id=<?= (int) $paciente['id'] ?>">Editar</a></td></tr><?php endforeach; ?><?php if (!$pacientes): ?><tr><td colspan="6" class="muted">No hay pacientes registrados.</td></tr><?php endif; ?></tbody></table></div></main></body></html>








