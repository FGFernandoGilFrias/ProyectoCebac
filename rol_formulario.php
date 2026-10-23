<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('usuarios.gestionar');

$id = (int) ($_GET['id'] ?? 0);
$esEdicion = $id > 0;
$rol = ['nombre' => '', 'descripcion' => ''];
$conexion = conexion_bd();
$privilegiosSeleccionados = [];

if ($esEdicion) {
    $consulta = $conexion->prepare('SELECT codigo, nombre, descripcion FROM roles WHERE codigo = :codigo');
    $consulta->execute(['codigo' => $id]);
    $rol = $consulta->fetch() ?: $rol;

    $privilegioStmt = $conexion->prepare('SELECT privilegio_codigo FROM roles_privilegios WHERE rol_codigo = :codigo');
    $privilegioStmt->execute(['codigo' => $id]);
    $privilegiosSeleccionados = array_map('intval', $privilegioStmt->fetchAll(PDO::FETCH_COLUMN));
}

$privilegios = $conexion->query('SELECT codigo, codigo_privilegio, nombre, descripcion FROM privilegios ORDER BY codigo_privilegio')->fetchAll();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rol['nombre'] = trim((string) ($_POST['nombre'] ?? ''));
    $rol['descripcion'] = trim((string) ($_POST['descripcion'] ?? ''));
    $privilegiosSeleccionados = array_values(array_unique(array_map('intval', $_POST['privilegios_ids'] ?? [])));

    if ($rol['nombre'] === '') {
        $error = 'El nombre del rol es obligatorio.';
    } else {
        try {
            $conexion->beginTransaction();
            if ($esEdicion) {
                $conexion->prepare('UPDATE roles SET nombre=:nombre, descripcion=:descripcion WHERE codigo=:codigo')
                    ->execute(['nombre' => $rol['nombre'], 'descripcion' => $rol['descripcion'], 'codigo' => $id]);
                $conexion->prepare('DELETE FROM roles_privilegios WHERE rol_codigo = :codigo')->execute(['codigo' => $id]);
            } else {
                $consulta = $conexion->prepare('INSERT INTO roles (nombre, descripcion) VALUES (:nombre, :descripcion)');
                $consulta->execute(['nombre' => $rol['nombre'], 'descripcion' => $rol['descripcion']]);
                $id = (int) $conexion->lastInsertId();
            }

            $save = $conexion->prepare('INSERT INTO roles_privilegios (rol_codigo, privilegio_codigo) VALUES (:rol_codigo, :privilegio_codigo)');
            foreach ($privilegiosSeleccionados as $privCodigo) {
                $save->execute(['rol_codigo' => $id, 'privilegio_codigo' => $privCodigo]);
            }

            $conexion->commit();

            if (isset($_POST['_modal']) && $_POST['_modal'] === '1') {
                header('Content-Type: application/json');
                echo json_encode([
                    'ok' => true,
                    'mensaje' => $esEdicion ? 'Rol actualizado correctamente.' : 'Rol creado correctamente.',
                    'id' => $id,
                ]);
                exit;
            }

            mensaje_flash($esEdicion ? 'Rol actualizado correctamente.' : 'Rol creado correctamente.');
            header('Location: roles.php');
            exit;
        } catch (PDOException $exception) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            $error = $exception->getCode() === '23000' ? 'Ya existe un rol con ese nombre.' : 'No se pudo guardar el rol.';
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
    <form method="post" id="rol-modal-form">
        <input type="hidden" name="_modal" value="1">

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= escapar_html($error) ?></div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nombre del rol *</label>
                <input type="text" name="nombre" class="form-control" required value="<?= escapar_html($rol['nombre']) ?>" placeholder="Ej: Bioquímico">
            </div>
            <div class="col-md-6">
                <label class="form-label">Descripción</label>
                <input type="text" name="descripcion" class="form-control" value="<?= escapar_html($rol['descripcion']) ?>" placeholder="Ej: Acceso a resultados y estudios">
            </div>

            <div class="col-12">
                <label class="form-label">Privilegios *</label>
                <div class="border rounded p-2" style="max-height: 320px; overflow-y: auto;">
                    <?php foreach ($privilegios as $privilegio): ?>
                        <?php $checked = in_array((int) $privilegio['codigo'], $privilegiosSeleccionados, true); ?>
                        <div class="form-check py-1">
                            <input class="form-check-input" type="checkbox" name="privilegios_ids[]" value="<?= (int) $privilegio['codigo'] ?>" id="priv_<?= (int) $privilegio['codigo'] ?>" <?= $checked ? 'checked' : '' ?>>
                            <label class="form-check-label" for="priv_<?= (int) $privilegio['codigo'] ?>">
                                <strong><?= escapar_html($privilegio['nombre']) ?></strong>
                                <small class="text-muted d-block"><?= escapar_html($privilegio['descripcion']) ?></small>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <small class="text-muted">Marcá los permisos que tendrá este rol.</small>
            </div>
        </div>
    </form>
    <?php
    exit;
}

$tituloPagina = ($esEdicion ? 'Editar' : 'Nuevo') . ' rol | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="main-content">
    <h1><?= $esEdicion ? 'Editar rol' : 'Nuevo rol' ?></h1>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>