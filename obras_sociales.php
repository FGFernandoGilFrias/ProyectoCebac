<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('obras_sociales.gestionar');

$search = trim((string) ($_GET['q'] ?? ''));
$consultaSql = 'SELECT s.*, COUNT(p.id) AS patient_count FROM obras_sociales s LEFT JOIN pacientes p ON p.obra_social_id = s.id';
$params = [];
if ($search !== '') {
    $consultaSql .= ' WHERE s.nombre LIKE :q OR s.identificador_fiscal LIKE :q';
    $params['q'] = '%' . $search . '%';
}
$consultaSql .= ' GROUP BY s.id ORDER BY s.activo DESC, s.nombre LIMIT 100';
$consulta = conexion_bd()->prepare($consultaSql);
$consulta->execute($params);
$obraSocials = $consulta->fetchAll();
$mensaje = mensaje_flash();
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Obras sociales | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main class="container"><div class="page-heading"><div><h1>Obras sociales y convenios</h1><p class="muted">Alta, consulta, modificación y baja lógica de convenios.</p></div><a class="button-link" href="obra_social_formulario.php">Nuevo convenio</a></div><?php if ($mensaje): ?><div class="alert success-alert"><?= escapar_html($mensaje) ?></div><?php endif; ?><form class="search-form" method="get"><input name="q" value="<?= escapar_html($search) ?>" placeholder="Buscar por nombre o CUIT"><button type="submit">Buscar</button></form><div class="table-wrap"><table><thead><tr><th>Obra social</th><th>CUIT</th><th>Pacientes asociados</th><th>Estado</th><th></th></tr></thead><tbody><?php foreach ($obraSocials as $obraSocial): ?><tr><td><?= escapar_html($obraSocial['nombre']) ?></td><td><?= escapar_html($obraSocial['identificador_fiscal']) ?></td><td><?= (int) $obraSocial['patient_count'] ?></td><td><span class="badge <?= $obraSocial['activo'] ? 'success' : '' ?>"><?= $obraSocial['activo'] ? 'Activa' : 'Inactiva' ?></span></td><td><a href="obra_social_formulario.php?id=<?= (int) $obraSocial['id'] ?>">Editar</a><?php if ($obraSocial['activo']): ?> · <a href="obra_social_estado.php?id=<?= (int) $obraSocial['id'] ?>&activo=0">Dar de baja</a><?php else: ?> · <a href="obra_social_estado.php?id=<?= (int) $obraSocial['id'] ?>&activo=1">Reactivar</a><?php endif; ?></td></tr><?php endforeach; ?><?php if (!$obraSocials): ?><tr><td colspan="5" class="muted">No hay obras sociales registradas.</td></tr><?php endif; ?></tbody></table></div></main></body></html>








