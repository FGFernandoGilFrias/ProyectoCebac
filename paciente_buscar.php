<?php
/**
 * paciente_buscar.php
 * Endpoint que devuelve los datos de un paciente por DNI (JSON).
 * Usado por orden_formulario.php para autocompletar.
 */
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('pacientes.gestionar');

header('Content-Type: application/json; charset=utf-8');

$dni = trim((string) ($_GET['dni'] ?? ''));

if ($dni === '') {
    echo json_encode(['ok' => false, 'motivo' => 'sin_dni']);
    exit;
}

$consulta = conexion_bd()->prepare(
    "SELECT p.codigo, p.dni, p.apellido, p.nombre, p.fecha_nacimiento,
            p.telefono, p.correo, p.direccion,
            pos.obra_social_codigo, pos.nro_credencial, pos.cobertura, pos.porcentaje_cobertura,
            os.nombre AS obra_social_nombre
     FROM pacientes p
     LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
     LEFT JOIN obras_sociales os ON os.codigo = pos.obra_social_codigo
     WHERE p.dni = :dni
     LIMIT 1"
);
$consulta->execute(['dni' => $dni]);
$paciente = $consulta->fetch(PDO::FETCH_ASSOC);

if (!$paciente) {
    echo json_encode(['ok' => false, 'motivo' => 'no_existe', 'dni' => $dni]);
    exit;
}

if ($paciente['obra_social_nombre'] === null) $paciente['obra_social_nombre'] = '';
if ($paciente['nro_credencial'] === null)    $paciente['nro_credencial'] = '';
if ($paciente['cobertura'] === null)         $paciente['cobertura'] = '';
if ($paciente['porcentaje_cobertura'] === null) $paciente['porcentaje_cobertura'] = 0;

echo json_encode(['ok' => true, 'paciente' => $paciente], JSON_UNESCAPED_UNICODE);