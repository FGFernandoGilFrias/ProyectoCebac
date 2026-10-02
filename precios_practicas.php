<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('practicas.gestionar');

$precios = conexion_bd()->query('SELECT * FROM precio_practica ORDER BY codigo')->fetchAll();
$mensaje = mensaje_flash();
$tituloPagina = 'Precios de prácticas | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container">
<div class="page-heading">
    <div>
        <h1>Precios de prácticas</h1>
        <p class="muted">Catálogo de precios de las prácticas.</p>
    </div>
    <a class="button-link" href="precio_practica_formulario.php">Nuevo precio</a>
</div>
<?php if ($mensaje): ?><div class="alert success-alert"><?= escapar_html($mensaje) ?></div><?php endif; ?>
<div class="table-wrap">
    <table>
        <thead>
            <tr><th>Código</th><th>Precio</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($precios as $precio): ?>
                <tr>
                    <td><?= (int) $precio['codigo'] ?></td>
                    <td><strong>$ <?= number_format((float) $precio['precio'], 2, ',', '.') ?></strong></td>
                    <td><a href="precio_practica_formulario.php?id=<?= (int) $precio['codigo'] ?>">Editar</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$precios): ?>
                <tr><td colspan="3" class="muted">No hay precios registrados.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>