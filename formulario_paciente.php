<?php
require_once __DIR__ . '/includes/autenticacion.php';
exigir_privilegio('pacientes.manage');

$id = (int) ($_GET['id'] ?? 0);
$paciente = ['dni'=>'','apellido'=>'','nombre'=>'','fecha_nacimiento'=>'','direccion'=>'','correo'=>'','telefono'=>'','obra_social_id'=>'','numero_afiliado'=>''];
if ($id > 0) {
    $stmt = db()->prepare('SELECT * FROM pacientes WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $paciente = $stmt->fetch() ?: $paciente;
}
$obrasSociales = db()->query('SELECT id, nombre FROM obras_sociales WHERE activo = 1 ORDER BY nombre')->fetchAll();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($paciente as $key => $valor) {
        if (array_key_exists($key, $_POST)) $paciente[$key] = trim((string) $_POST[$key]);
    }
    if ($paciente['dni'] === '' || $paciente['apellido'] === '' || $paciente['nombre'] === '') {
        $error = 'DNI, apellido y nombre son obligatorios.';
    } else {
        try {
            $paciente['fecha_nacimiento'] = $paciente['fecha_nacimiento'] !== '' ? $paciente['fecha_nacimiento'] : null;
            $paciente['obra_social_id'] = $paciente['obra_social_id'] !== '' ? (int) $paciente['obra_social_id'] : null;
            if ($id > 0) {
                $stmt = db()->prepare('UPDATE pacientes SET dni=:dni,apellido=:apellido,nombre=:nombre,fecha_nacimiento=:fecha_nacimiento,direccion=:direccion,correo=:correo,telefono=:telefono,obra_social_id=:obra_social_id,numero_afiliado=:numero_afiliado WHERE id=:id');
                $stmt->execute([
                    'dni' => $paciente['dni'],
                    'apellido' => $paciente['apellido'],
                    'nombre' => $paciente['nombre'],
                    'fecha_nacimiento' => $paciente['fecha_nacimiento'],
                    'direccion' => $paciente['direccion'],
                    'correo' => $paciente['correo'],
                    'telefono' => $paciente['telefono'],
                    'obra_social_id' => $paciente['obra_social_id'],
                    'numero_afiliado' => $paciente['numero_afiliado'],
                    'id' => $id,
                ]);
                flash('Paciente actualizado correctamente.');
            } else {
                $siguienteCodigo = (int) db()->query("SELECT COALESCE(MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)), 0) + 1 FROM pacientes WHERE codigo REGEXP '^PAC-[0-9]+$'")->fetchColumn();
                $codigo = 'PAC-' . str_pad((string) $siguienteCodigo, 3, '0', STR_PAD_LEFT);
                $stmt = db()->prepare('INSERT INTO pacientes (codigo,dni,apellido,nombre,fecha_nacimiento,direccion,correo,telefono,obra_social_id,numero_afiliado) VALUES (:codigo,:dni,:apellido,:nombre,:fecha_nacimiento,:direccion,:correo,:telefono,:obra_social_id,:numero_afiliado)');
                $stmt->execute($paciente + ['codigo' => $codigo]);
                flash('Paciente registrado correctamente.');
            }
            header('Location: pacientes.php'); exit;
        } catch (PDOException $excepcion) {
            if ($excepcion->getCode() === '23000') {
                $error = str_contains($excepcion->getMessage(), 'dni') ? 'Ya existe un paciente con ese DNI.' : 'Los datos ingresados ya existen o no son válidos.';
            } else {
                $error = 'No se pudo guardar el paciente.';
            }
        }
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $id ? 'Editar' : 'Nuevo' ?> paciente | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/barra_lateral.php'; ?><main class="container narrow"><p><a href="pacientes.php">← Volver a pacientes</a></p><h1><?= $id ? 'Editar paciente' : 'Nuevo paciente' ?></h1><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><form method="post" class="form-grid"><div><label>DNI *</label><input name="dni" required value="<?= e($paciente['dni']) ?>"></div><div><label>Apellido *</label><input name="apellido" required value="<?= e($paciente['apellido']) ?>"></div><div><label>Nombre *</label><input name="nombre" required value="<?= e($paciente['nombre']) ?>"></div><div><label>Fecha de nacimiento</label><input type="date" name="fecha_nacimiento" value="<?= e($paciente['fecha_nacimiento']) ?>"></div><div class="full"><label>Domicilio</label><input name="direccion" value="<?= e($paciente['direccion']) ?>"></div><div><label>Email</label><input type="email" name="correo" value="<?= e($paciente['correo']) ?>"></div><div><label>Teléfono</label><input name="telefono" value="<?= e($paciente['telefono']) ?>"></div><div><label>Obra social</label><select name="obra_social_id"><option value="">Particular</option><?php foreach ($obrasSociales as $obraSocial): ?><option value="<?= (int) $obraSocial['id'] ?>" <?= (string) $paciente['obra_social_id'] === (string) $obraSocial['id'] ? 'selected' : '' ?>><?= e($obraSocial['nombre']) ?></option><?php endforeach; ?></select></div><div><label>Nro. credencial</label><input name="numero_afiliado" value="<?= e($paciente['numero_afiliado']) ?>"></div><div class="full actions"><button type="submit">Guardar paciente</button></div></form></main></body></html>



