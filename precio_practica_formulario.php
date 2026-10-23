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
            } else {
                $consulta = conexion_bd()->prepare('INSERT INTO precio_practica (precio) VALUES (:precio)');
                $consulta->execute(['precio' => (float) $precio['precio']]);
                $id = (int) conexion_bd()->lastInsertId();
            }

            if (isset($_POST['_modal']) && $_POST['_modal'] === '1') {
                header('Content-Type: application/json');
                echo json_encode([
                    'ok' => true,
                    'mensaje' => $id > 0 ? 'Precio actualizado correctamente.' : 'Precio registrado correctamente.',
                    'id' => $id,
                ]);
                exit;
            }

            mensaje_flash('Precio guardado correctamente.');
            header('Location: precios_practicas.php');
            exit;
        } catch (PDOException $exception) {
            $error = 'No se pudo guardar el precio.';
        }
    }

    if (isset($_POST['_modal']) && $_POST['_modal'] === '1') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $error]);
        exit;
    }
}

$esModal = isset($_GET['_modal']) && $_GET['_modal'] === '1';

if ($esModal) {
    ?>
    <form method="post" id="precio-modal-form">
        <input type="hidden" name="_modal" value="1">

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= escapar_html($error) ?></div>
        <?php endif; ?>

        <div class="row g-3">
            <?php if ($id > 0): ?>
                <div class="col-md-4">
                    <label class="form-label">Código</label>
                    <input type="text" class="form-control" value="<?= (int) $precio['codigo'] ?>" readonly>
                </div>
            <?php endif; ?>
            <div class="col-md-<?= $id > 0 ? '8' : '12' ?>">
                <label class="form-label">Precio *</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" min="0" step="0.01" name="precio" class="form-control" required value="<?= escapar_html((string) $precio['precio']) ?>">
                </div>
            </div>
        </div>
    </form>
    <?php
    exit;
}

$tituloPagina = ($id ? 'Editar' : 'Nuevo') . ' precio | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="main-content">
    <h1><?= $id ? 'Editar precio' : 'Nuevo precio' ?></h1>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>