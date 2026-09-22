<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('obras_sociales.gestionar');

$id = (int) ($_GET['id'] ?? 0);
$esEdicion = $id > 0;
$obraSocial = ['nombre' => '', 'identificador_fiscal' => '', 'condiciones_convenio' => ''];
if ($id > 0) {
    $consulta = conexion_bd()->prepare('SELECT * FROM obras_sociales WHERE id = :id');
    $consulta->execute(['id' => $id]);
    $obraSocial = $consulta->fetch() ?: $obraSocial;
}
$estudios = conexion_bd()->query('SELECT id, codigo, nombre FROM estudios WHERE activo = 1 ORDER BY nombre')->fetchAll();
$authorizedStudies = [];
if ($id > 0) {
    $authorizedStmt = conexion_bd()->prepare('SELECT estudio_id FROM obras_sociales_estudios WHERE obra_social_id = :id');
    $authorizedStmt->execute(['id' => $id]);
    $authorizedStudies = array_map('intval', $authorizedStmt->fetchAll(PDO::FETCH_COLUMN));
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $obraSocial['nombre'] = trim((string) ($_POST['nombre'] ?? ''));
    $obraSocial['identificador_fiscal'] = trim((string) ($_POST['identificador_fiscal'] ?? ''));
    $obraSocial['condiciones_convenio'] = trim((string) ($_POST['condiciones_convenio'] ?? ''));
    $authorizedStudies = array_values(array_unique(array_map('intval', $_POST['estudios_ids'] ?? [])));
    if ($obraSocial['nombre'] === '' || $obraSocial['identificador_fiscal'] === '') {
        $error = 'Nombre y CUIT son obligatorios.';
    } else {
        try {
            $conexion = conexion_bd();
            $conexion->beginTransaction();
            if ($id > 0) {
                $consulta = conexion_bd()->prepare('UPDATE obras_sociales SET name=:nombre, identificador_fiscal=:identificador_fiscal, condiciones_convenio=:condiciones_convenio WHERE id=:id');
                $consulta->execute([
                    'nombre' => $obraSocial['nombre'],
                    'identificador_fiscal' => $obraSocial['identificador_fiscal'],
                    'condiciones_convenio' => $obraSocial['condiciones_convenio'],
                    'id' => $id,
                ]);
            } else {
                $consulta = conexion_bd()->prepare('INSERT INTO obras_sociales (nombre, identificador_fiscal, condiciones_convenio) VALUES (:nombre, :identificador_fiscal, :condiciones_convenio)');
                $consulta->execute([
                    'nombre' => $obraSocial['nombre'],
                    'identificador_fiscal' => $obraSocial['identificador_fiscal'],
                    'condiciones_convenio' => $obraSocial['condiciones_convenio'],
                ]);
                $id = (int) $conexion->lastInsertId();
            }
            $deleteAuthorization = $conexion->prepare('DELETE FROM obras_sociales_estudios WHERE obra_social_id = :id');
            $deleteAuthorization->execute(['id' => $id]);
            $authorization = $conexion->prepare('INSERT INTO obras_sociales_estudios (obra_social_id, estudio_id) VALUES (:obra_social_id, :estudio_id)');
            foreach ($authorizedStudies as $estudioId) {
                $authorization->execute(['obra_social_id' => $id, 'estudio_id' => $estudioId]);
            }
            $conexion->commit();
            mensaje_flash($esEdicion ? 'Convenio actualizado correctamente.' : 'Obra social registrada correctamente.');
            header('Location: obras_sociales.php');
            exit;
        } catch (PDOException $exception) {
            if (isset($conexion) && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            $error = $exception->getCode() === '23000' ? 'Ya existe una obra social con ese CUIT.' : 'No se pudo guardar el convenio.';
        }
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $id ? 'Editar' : 'Nueva' ?> obra social | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main class="container narrow"><p><a href="obras_sociales.php">← Volver a obras sociales</a></p><h1><?= $id ? 'Editar convenio' : 'Nueva obra social' ?></h1><?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?><form method="post" class="form-grid"><div class="full"><label>Nombre *</label><input name="nombre" required value="<?= escapar_html($obraSocial['nombre']) ?>"></div><div><label>CUIT *</label><input name="identificador_fiscal" required value="<?= escapar_html($obraSocial['identificador_fiscal']) ?>"></div><div class="full"><label>Condiciones del convenio</label><textarea name="condiciones_convenio" rows="5"><?= escapar_html($obraSocial['condiciones_convenio']) ?></textarea></div><div class="full"><label>Estudios autorizados</label><div class="study-picker"><?php foreach ($estudios as $estudio): ?><label class="study-option"><input type="checkbox" name="estudios_ids[]" value="<?= (int) $estudio['id'] ?>" <?= in_array((int) $estudio['id'], $authorizedStudies, true) ? 'checked' : '' ?>><span><?= escapar_html($estudio['nombre'] . ' (' . $estudio['codigo'] . ')') ?></span></label><?php endforeach; ?><?php if (!$estudios): ?><span class="muted">Primero debes registrar estudios.</span><?php endif; ?></div></div><div class="full"><button type="submit">Guardar convenio</button></div></form></main></body></html>








