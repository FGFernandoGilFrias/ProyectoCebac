<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('panel.ver');
$usuario = usuario_actual();
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Panel | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main class="container"><h1>Panel principal</h1><p class="muted">Bienvenido al sistema de gestión del Laboratorio CEBAC S.R.L.</p><section class="grid"><article class="module"><h2>Pacientes</h2><p>Alta, consulta y actualización de datos de pacientes.</p><a class="button-link" href="pacientes.php">Abrir módulo</a></article><article class="module"><h2>Obras sociales</h2><p>Gestión de convenios y datos de cobertura.</p><a class="button-link" href="obras_sociales.php">Abrir módulo</a></article><article class="module"><h2>Estudios</h2><p>Catálogo de estudios y parámetros.</p><a class="button-link" href="estudios.php">Abrir módulo</a></article><article class="module"><h2>Precios de prácticas</h2><p>Entidad independiente relacionada con los estudios.</p><a class="button-link" href="precios_practicas.php">Abrir módulo</a></article><article class="module"><h2>Órdenes médicas</h2><p>Órdenes de pacientes y estudios solicitados.</p><a class="button-link" href="ordenes.php">Abrir módulo</a></article><article class="module"><h2>Caja</h2><p>Pagos de estudios particulares y pago al retirar.</p><a class="button-link" href="caja.php">Abrir módulo</a></article><article class="module"><h2>Check-in de muestras</h2><p>Recepción y estados individuales de las muestras.</p><a class="button-link" href="recepcion_muestras.php">Abrir módulo</a></article><article class="module"><h2>Usuarios</h2><p>Acceso protegido por credenciales y roles.</p><span class="badge success">Activo</span></article></section></main></body></html>








