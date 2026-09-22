<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('pacientes.gestionar');

$id = (int) ($_GET['id'] ?? 0);
$paciente = ['dni'=>'','apellido'=>'','nombre'=>'','fecha_nacimiento'=>'','direccion'=>'','correo'=>'','telefono'=>'','obra_social_id'=>'','numero_afiliado'=>''];
if ($id > 0) {
    $consulta = conexion_bd()->prepare('SELECT * FROM pacientes WHERE id = :id');
    $consulta->execute(['id' => $id]);
    $paciente = $consulta->fetch() ?: $paciente;
}
$obraSocials = conexion_bd()->query('SELECT id, nombre FROM obras_sociales WHERE activo = 1 ORDER BY nombre')->fetchAll();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($paciente as $key => $value) {
        if (array_key_exists($key, $_POST)) $paciente[$key] = trim((string) $_POST[$key]);
    }
    if ($paciente['dni'] === '' || $paciente['apellido'] === '' || $paciente['nombre'] === '') {
        $error = 'DNI, apellido y nombre son obligatorios.';
    } else {
        try {
            $paciente['fecha_nacimiento'] = $paciente['fecha_nacimiento'] !== '' ? $paciente['fecha_nacimiento'] : null;
            $paciente['obra_social_id'] = $paciente['obra_social_id'] !== '' ? (int) $paciente['obra_social_id'] : null;
            if ($id > 0) {
                $consulta = conexion_bd()->prepare('UPDATE pacientes SET dni=:dni,apellido=:apellido,nombre=:nombre,fecha_nacimiento=:fecha_nacimiento,direccion=:direccion,correo=:correo,telefono=:telefono,obra_social_id=:obra_social_id,numero_afiliado=:numero_afiliado WHERE id=:id');
                $consulta->execute([
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
                mensaje_flash('Paciente actualizado correctamente.');
            } else {
                $siguienteCodigo = (int) conexion_bd()->query("SELECT COALESCE(MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)), 0) + 1 FROM pacientes WHERE codigo REGEXP '^PAC-[0-9]+$'")->fetchColumn();
                $codigo = 'PAC-' . str_pad((string) $siguienteCodigo, 3, '0', STR_PAD_LEFT);
                $consulta = conexion_bd()->prepare('INSERT INTO pacientes (codigo,dni,apellido,nombre,fecha_nacimiento,direccion,correo,telefono,obra_social_id,numero_afiliado) VALUES (:codigo,:dni,:apellido,:nombre,:fecha_nacimiento,:direccion,:correo,:telefono,:obra_social_id,:numero_afiliado)');
                $consulta->execute($paciente + ['codigo' => $codigo]);
                mensaje_flash('Paciente registrado correctamente.');
            }
            header('Location: pacientes.php'); exit;
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $error = str_contains($exception->getMessage(), 'dni') ? 'Ya existe un paciente con ese DNI.' : 'Los datos ingresados ya existen o no son válidos.';
            } else {
                $error = 'No se pudo guardar el paciente.';
            }
        }
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $id ? 'Editar' : 'Nuevo' ?> paciente | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main class="container narrow"><p><a href="pacientes.php">← Volver a pacientes</a></p><h1><?= $id ? 'Editar paciente' : 'Nuevo paciente' ?></h1><?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?><form method="post" class="form-grid"><div><label>DNI *</label><input name="dni" required value="<?= escapar_html($paciente['dni']) ?>"></div><div><label>Apellido *</label><input name="apellido" required value="<?= escapar_html($paciente['apellido']) ?>"></div><div><label>Nombre *</label><input name="nombre" required value="<?= escapar_html($paciente['nombre']) ?>"></div><div><label>Fecha de nacimiento</label><input type="date" name="fecha_nacimiento" value="<?= escapar_html($paciente['fecha_nacimiento']) ?>"></div><div class="full"><label>Domicilio</label><input name="direccion" value="<?= escapar_html($paciente['direccion']) ?>"></div><div><label>Email</label><input type="correo" name="correo" value="<?= escapar_html($paciente['correo']) ?>"></div><div><label>Teléfono</label><input name="telefono" value="<?= escapar_html($paciente['telefono']) ?>"></div><div><label>Obra social</label><select name="obra_social_id"><option value="">Particular</option><?php foreach ($obraSocials as $obraSocial): ?><option value="<?= (int) $obraSocial['id'] ?>" <?= (string) $paciente['obra_social_id'] === (string) $obraSocial['id'] ? 'selected' : '' ?>><?= escapar_html($obraSocial['nombre']) ?></option><?php endforeach; ?></select></div><div><label>Nro. credencial</label><input name="numero_afiliado" value="<?= escapar_html($paciente['numero_afiliado']) ?>"></div><div class="full actions"><button type="submit">Guardar paciente</button></div></form></main></body></html>








