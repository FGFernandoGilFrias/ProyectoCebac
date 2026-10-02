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
}

$tituloPagina = ($esEdicion ? 'Editar' : 'Nuevo') . ' rol | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container narrow">
<p><a href="roles.php">← Volver a roles</a></p>
<h1><?= $esEdicion ? 'Editar rol' : 'Nuevo rol' ?></h1>

<?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?>

<form method="post" class="form-grid">
    <div class="full"><label>Nombre del rol *</label><input name="nombre" required value="<?= escapar_html($rol['nombre']) ?>"></div>
    <div class="full"><label>Descripción</label><textarea name="descripcion" rows="3"><?= escapar_html($rol['descripcion']) ?></textarea></div>
    <div class="full">
        <label>Privilegios *</label>
        <div class="study-picker">
            <?php foreach ($privilegios as $privilegio): ?>
                <label class="study-option">
                    <input type="checkbox" name="privilegios_ids[]" value="<?= (int) $privilegio['codigo'] ?>" <?= in_array((int) $privilegio['codigo'], $privilegiosSeleccionados, true) ? 'checked' : '' ?>>
                    <span><strong><?= escapar_html($privilegio['nombre']) ?></strong> — <?= escapar_html($privilegio['descripcion']) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="full"><button type="submit">Guardar rol</button></div>
</form>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>