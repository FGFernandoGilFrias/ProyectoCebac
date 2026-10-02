<?php
/**
 * paciente_buscar.php
 * Endpoint que devuelve los datos de un paciente por DNI (JSON).
 * Usado por orden_formulario.php para autocompletar.
 */
require_once __DIR__ . '/includes/auth.php';
exigir_acceso();

header('Content-Type: application/json; charset=utf-8');

$dni = trim((string) ($_GET['dni'] ?? ''));

if ($dni === '') {
    echo json_encode(['ok' => false, 'motivo' => 'sin_dni']);
    exit;
}

$consulta = conexion_bd()->prepare(
    "SELECT id, codigo, dni, apellido, nombre, fecha_nacimiento, telefono, correo, direccion,
            obra_social_id, numero_afiliado
     FROM pacientes
     WHERE dni = :dni
     LIMIT 1"
);
$consulta->execute(['dni' => $dni]);
$paciente = $consulta->fetch(PDO::FETCH_ASSOC);

if (!$paciente) {
    echo json_encode(['ok' => false, 'motivo' => 'no_existe', 'dni' => $dni]);
    exit;
}

// Traer nombre de obra social si tiene
$obraSocialNombre = '';
if (!empty($paciente['obra_social_id'])) {
    $sw = conexion_bd()->prepare('SELECT nombre FROM obras_sociales WHERE id = :id LIMIT 1');
    $sw->execute(['id' => (int) $paciente['obra_social_id']]);
    $obraSocialNombre = (string) ($sw->fetchColumn() ?: '');
}

$paciente['obra_social_nombre'] = $obraSocialNombre;
echo json_encode(['ok' => true, 'paciente' => $paciente], JSON_UNESCAPED_UNICODE);