<?php
require_once __DIR__ . '/includes/autenticacion.php';
exigir_privilegio('obras_sociales.manage');

$id = (int) ($_GET['id'] ?? 0);
$esEdicion = $id > 0;
$obraSocial = ['nombre' => '', 'identificacion_fiscal' => '', 'condiciones_convenio' => ''];
if ($id > 0) {
    $stmt = db()->prepare('SELECT * FROM obras_sociales WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $obraSocial = $stmt->fetch() ?: $obraSocial;
}
$estudios = db()->query('SELECT id, codigo, nombre FROM estudios WHERE activo = 1 ORDER BY nombre')->fetchAll();
$autorizadosEstudios = [];
if ($id > 0) {
    $consultaAutorizados = db()->prepare('SELECT estudio_id FROM obras_sociales_estudios WHERE obra_social_id = :id');
    $consultaAutorizados->execute(['id' => $id]);
    $autorizadosEstudios = array_map('intval', $consultaAutorizados->fetchAll(PDO::FETCH_COLUMN));
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $obraSocial['nombre'] = trim((string) ($_POST['nombre'] ?? ''));
    $obraSocial['identificacion_fiscal'] = trim((string) ($_POST['identificacion_fiscal'] ?? ''));
    $obraSocial['condiciones_convenio'] = trim((string) ($_POST['condiciones_convenio'] ?? ''));
    $autorizadosEstudios = array_values(array_unique(array_map('intval', $_POST['estudios_ids'] ?? [])));
    if ($obraSocial['nombre'] === '' || $obraSocial['identificacion_fiscal'] === '') {
        $error = 'Nombre y CUIT son obligatorios.';
    } else {
        try {
            $pdo = db();
            $pdo->beginTransaction();
            if ($id > 0) {
                $stmt = db()->prepare('UPDATE obras_sociales SET nombre=:nombre, identificacion_fiscal=:identificacion_fiscal, condiciones_convenio=:condiciones_convenio WHERE id=:id');
                $stmt->execute([
                    'nombre' => $obraSocial['nombre'],
                    'identificacion_fiscal' => $obraSocial['identificacion_fiscal'],
                    'condiciones_convenio' => $obraSocial['condiciones_convenio'],
                    'id' => $id,
                ]);
            } else {
                $stmt = db()->prepare('INSERT INTO obras_sociales (nombre, identificacion_fiscal, condiciones_convenio) VALUES (:nombre, :identificacion_fiscal, :condiciones_convenio)');
                $stmt->execute([
                    'nombre' => $obraSocial['nombre'],
                    'identificacion_fiscal' => $obraSocial['identificacion_fiscal'],
                    'condiciones_convenio' => $obraSocial['condiciones_convenio'],
                ]);
                $id = (int) $pdo->lastInsertId();
            }
            $deleteAuthorization = $pdo->prepare('DELETE FROM obras_sociales_estudios WHERE obra_social_id = :id');
            $deleteAuthorization->execute(['id' => $id]);
            $autorizacion = $pdo->prepare('INSERT INTO obras_sociales_estudios (obra_social_id, estudio_id) VALUES (:obra_social_id, :estudio_id)');
            foreach ($autorizadosEstudios as $estudioId) {
                $autorizacion->execute(['obra_social_id' => $id, 'estudio_id' => $estudioId]);
            }
            $pdo->commit();
            flash($esEdicion ? 'Convenio actualizado correctamente.' : 'Obra social registrada correctamente.');
            header('Location: obras_sociales.php');
            exit;
        } catch (PDOException $excepcion) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $excepcion->getCode() === '23000' ? 'Ya existe una obra social con ese CUIT.' : 'No se pudo guardar el convenio.';
        }
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $id ? 'Editar' : 'Nueva' ?> obra social | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/barra_lateral.php'; ?><main class="container narrow"><p><a href="obras_sociales.php">← Volver a obras sociales</a></p><h1><?= $id ? 'Editar convenio' : 'Nueva obra social' ?></h1><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><form method="post" class="form-grid"><div class="full"><label>Nombre *</label><input name="nombre" required value="<?= e($obraSocial['nombre']) ?>"></div><div><label>CUIT *</label><input name="identificacion_fiscal" required value="<?= e($obraSocial['identificacion_fiscal']) ?>"></div><div class="full"><label>Condiciones del convenio</label><textarea name="condiciones_convenio" rows="5"><?= e($obraSocial['condiciones_convenio']) ?></textarea></div><div class="full"><label>Estudios autorizados</label><div class="estudio-picker"><?php foreach ($estudios as $estudio): ?><label class="estudio-option"><input type="checkbox" name="estudios_ids[]" value="<?= (int) $estudio['id'] ?>" <?= in_array((int) $estudio['id'], $autorizadosEstudios, true) ? 'checked' : '' ?>><span><?= e($estudio['nombre'] . ' (' . $estudio['codigo'] . ')') ?></span></label><?php endforeach; ?><?php if (!$estudios): ?><span class="muted">Primero debes registrar estudios.</span><?php endif; ?></div></div><div class="full"><button type="submit">Guardar convenio</button></div></form></main></body></html>



