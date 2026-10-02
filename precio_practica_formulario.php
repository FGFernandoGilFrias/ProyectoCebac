<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('practicas.gestionar');

$id = (int) ($_GET['id'] ?? 0);
$precio = ['codigo' => '', 'precio' => '0.00'];

if ($id > 0) {
    $consulta = conexion_bd()->prepare('SELECT * FROM precio_practica WHERE codigo = :codigo');
    $consulta->execute(['codigo' => $id]);
    $precio = $consulta->fetch() ?: $precio;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $precio['precio'] = trim((string) ($_POST['precio'] ?? ''));

    if (!is_numeric($precio['precio']) || (float) $precio['precio'] < 0) {
        $error = 'El precio debe ser un número válido.';
    } else {
        try {
            if ($id > 0) {
                $consulta = conexion_bd()->prepare('UPDATE precio_practica SET precio=:precio WHERE codigo=:codigo');
                $consulta->execute(['precio' => (float) $precio['precio'], 'codigo' => $id]);
                mensaje_flash('Precio actualizado correctamente.');
            } else {
                $consulta = conexion_bd()->prepare('INSERT INTO precio_practica (precio) VALUES (:precio)');
                $consulta->execute(['precio' => (float) $precio['precio']]);
                mensaje_flash('Precio registrado correctamente.');
            }
            header('Location: precios_practicas.php');
            exit;
        } catch (PDOException $exception) {
            $error = 'No se pudo guardar el precio.';
        }
    }
}

$tituloPagina = ($id ? 'Editar' : 'Nuevo') . ' precio | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container narrow">
<p><a href="precios_practicas.php">← Volver a precios</a></p>
<h1><?= $id ? 'Editar precio' : 'Nuevo precio' ?></h1>

<?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?>

<form method="post" class="form-grid">
    <?php if ($id): ?>
        <div><label>Código</label><input value="<?= (int) $precio['codigo'] ?>" readonly></div>
    <?php endif; ?>
    <div class="<?= $id ? '' : 'full' ?>"><label>Precio *</label><input type="number" min="0" step="0.01" name="precio" required value="<?= escapar_html((string) $precio['precio']) ?>"></div>
    <div class="full"><button type="submit">Guardar precio</button></div>
</form>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>