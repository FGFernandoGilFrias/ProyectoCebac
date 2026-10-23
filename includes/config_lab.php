<?php
/**
 * includes/config_lab.php
 * Configuración del laboratorio y credenciales de envío de email.
 */

return [
    'nombre'      => 'Laboratorio CEBAC S.R.L.',
    'direccion'   => 'Córdoba 1393 - Posadas - Misiones',
    'telefono'    => '3764 20 59 95',
    'horario'     => 'Lunes a Viernes de 06:30 a 20:00 hs',
    'email'       => 'mantenimientocebac@gmail.com',
    'cuit'        => '30-67237269-7',
    'logo'        => 'public/assets/logo.png',

    'directores' => [
        ['nombre' => 'Dr. Carlos Francisco Insaurralde', 'cargo' => 'Director General'],
        ['nombre' => 'Dra. Graciela Mabel Carballo', 'cargo' => 'Directora'],
    ],

    'firma' => [
        'nombre' => 'Carlos Insaurralde',
        'cargo'  => 'Bioquímico',
    ],

    'smtp' => [
        'host'       => 'smtp.gmail.com',
        'puerto'     => 587,
        'seguridad'  => 'tls',
        'usuario'    => 'mantenimientocebac@gmail.com',
        'contrasena' => 'lwsrxhwvbjqftgig',
        'remitente'  => 'mantenimientocebac@gmail.com',
        'nombre_remitente' => 'Laboratorio CEBAC',
    ],
];