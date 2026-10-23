<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('usuarios.gestionar');

$id = (int) ($_GET['id'] ?? 0);
$esEdicion = $id > 0;

$usuario = [
    'nombre_usuario' => '',
    'nombre_completo' => '',
    'email' => '',
    'telefono' => '',
    'dni' => '',
    'cargo' => '',
    'matricula' => '',
    'notas' => '',
    'activo' => 1,
];

$rolesSeleccionados = [];
$conexion = conexion_bd();

if ($esEdicion) {
    $consulta = $conexion->prepare('SELECT * FROM usuarios WHERE codigo = :codigo');
    $consulta->execute(['codigo' => $id]);
    $fila = $consulta->fetch();
    if ($fila) {
        $usuario = array_merge($usuario, $fila);
    }
    $consultaRoles = $conexion->prepare('SELECT rol_codigo FROM usuarios_roles WHERE usuario_codigo = :codigo');
    $consultaRoles->execute(['codigo' => $id]);
    $rolesSeleccionados = array_map('intval', $consultaRoles->fetchAll(PDO::FETCH_COLUMN));
}

$roles = $conexion->query(
    "SELECT r.codigo, r.nombre, r.descripcion,
            GROUP_CONCAT(p.nombre ORDER BY p.nombre SEPARATOR ', ') AS privilegios
     FROM roles r
     LEFT JOIN roles_privilegios rp ON rp.rol_codigo = r.codigo
     LEFT JOIN privilegios p ON p.codigo = rp.privilegio_codigo
     WHERE r.activo = 1
     GROUP BY r.codigo ORDER BY r.nombre"
)->fetchAll();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario['nombre_usuario'] = trim((string) ($_POST['nombre_usuario'] ?? ''));
    $usuario['nombre_completo'] = trim((string) ($_POST['nombre_completo'] ?? ''));
    $usuario['email'] = trim((string) ($_POST['email'] ?? ''));
    $usuario['telefono'] = trim((string) ($_POST['telefono'] ?? ''));
    $usuario['dni'] = trim((string) ($_POST['dni'] ?? ''));
    $usuario['cargo'] = trim((string) ($_POST['cargo'] ?? ''));
    $usuario['matricula'] = trim((string) ($_POST['matricula'] ?? ''));
    $usuario['notas'] = trim((string) ($_POST['notas'] ?? ''));
    $usuario['activo'] = isset($_POST['activo']) ? 1 : 0;
    $contrasena = (string) ($_POST['contrasena'] ?? '');
    $contrasena2 = (string) ($_POST['contrasena2'] ?? '');
    $rolesSeleccionados = array_values(array_unique(array_map('intval', $_POST['roles_ids'] ?? [])));

    if ($usuario['nombre_usuario'] === '' || $usuario['nombre_completo'] === '') {
        $error = 'Usuario y nombre completo son obligatorios.';
    } elseif (!$esEdicion && $contrasena === '') {
        $error = 'La contraseña es obligatoria para usuarios nuevos.';
    } elseif ($contrasena !== '' && strlen($contrasena) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($contrasena !== '' && $contrasena !== $contrasena2) {
        $error = 'Las contraseñas no coinciden.';
    } elseif ($usuario['email'] !== '' && !filter_var($usuario['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'El email no es válido.';
    } elseif (empty($rolesSeleccionados)) {
        $error = 'Seleccioná al menos un rol.';
    } else {
        try {
            $conexion->beginTransaction();

            if ($esEdicion) {
                $sql = 'UPDATE usuarios SET nombre_usuario=:nombre_usuario, nombre_completo=:nombre_completo,
                        email=:email, telefono=:telefono, dni=:dni, cargo=:cargo, matricula=:matricula, notas=:notas,
                        activo=:activo';
                $params = [
                    'nombre_usuario' => $usuario['nombre_usuario'],
                    'nombre_completo' => $usuario['nombre_completo'],
                    'email' => $usuario['email'] !== '' ? $usuario['email'] : null,
                    'telefono' => $usuario['telefono'] !== '' ? $usuario['telefono'] : null,
                    'dni' => $usuario['dni'] !== '' ? $usuario['dni'] : null,
                    'cargo' => $usuario['cargo'] !== '' ? $usuario['cargo'] : null,
                    'matricula' => $usuario['matricula'] !== '' ? $usuario['matricula'] : null,
                    'notas' => $usuario['notas'] !== '' ? $usuario['notas'] : null,
                    'activo' => $usuario['activo'],
                ];
                if ($contrasena !== '') {
                    $sql .= ', hash_contrasena=:hash';
                    $params['hash'] = password_hash($contrasena, PASSWORD_DEFAULT);
                }
                $sql .= ' WHERE codigo=:codigo';
                $params['codigo'] = $id;
                $conexion->prepare($sql)->execute($params);
                $conexion->prepare('DELETE FROM usuarios_roles WHERE usuario_codigo = :codigo')->execute(['codigo' => $id]);
            } else {
                $consulta = $conexion->prepare('INSERT INTO usuarios (nombre_usuario, nombre_completo, email, telefono, dni, cargo, matricula, notas, hash_contrasena, activo) VALUES (:nombre_usuario, :nombre_completo, :email, :telefono, :dni, :cargo, :matricula, :notas, :hash_contrasena, :activo)');
                $consulta->execute([
                    'nombre_usuario' => $usuario['nombre_usuario'],
                    'nombre_completo' => $usuario['nombre_completo'],
                    'email' => $usuario['email'] !== '' ? $usuario['email'] : null,
                    'telefono' => $usuario['telefono'] !== '' ? $usuario['telefono'] : null,
                    'dni' => $usuario['dni'] !== '' ? $usuario['dni'] : null,
                    'cargo' => $usuario['cargo'] !== '' ? $usuario['cargo'] : null,
                    'matricula' => $usuario['matricula'] !== '' ? $usuario['matricula'] : null,
                    'notas' => $usuario['notas'] !== '' ? $usuario['notas'] : null,
                    'hash_contrasena' => password_hash($contrasena, PASSWORD_DEFAULT),
                    'activo' => $usuario['activo'],
                ]);
                $id = (int) $conexion->lastInsertId();
            }

            $roleInsert = $conexion->prepare('INSERT INTO usuarios_roles (usuario_codigo, rol_codigo) VALUES (:usuario_codigo, :rol_codigo)');
            foreach ($rolesSeleccionados as $rolCodigo) {
                $roleInsert->execute(['usuario_codigo' => $id, 'rol_codigo' => $rolCodigo]);
            }

            $conexion->commit();

            if (isset($_POST['_modal']) && $_POST['_modal'] === '1') {
                header('Content-Type: application/json');
                echo json_encode([
                    'ok' => true,
                    'mensaje' => $esEdicion ? 'Usuario actualizado correctamente.' : 'Usuario creado correctamente.',
                    'id' => $id,
                ]);
                exit;
            }

            mensaje_flash($esEdicion ? 'Usuario actualizado correctamente.' : 'Usuario creado correctamente.');
            header('Location: usuarios.php');
            exit;
        } catch (PDOException $exception) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            $error = $exception->getCode() === '23000'
                ? 'El nombre de usuario ya existe o hay un dato duplicado.'
                : 'No se pudo guardar el usuario.';
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
    <form method="post" id="usuario-modal-form">
        <input type="hidden" name="_modal" value="1">

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= escapar_html($error) ?></div>
        <?php endif; ?>

        <div class="row g-3">
            <!-- USUARIO -->
            <div class="col-md-6">
                <label class="form-label">Usuario *</label>
                <input type="text" name="nombre_usuario" class="form-control" required value="<?= escapar_html($usuario['nombre_usuario']) ?>" placeholder="Ej: jgonzalez" autocomplete="off">
                <small class="text-muted">Sin espacios. Se usa para iniciar sesión.</small>
            </div>
            <div class="col-md-6">
                <label class="form-label">Nombre completo *</label>
                <input type="text" name="nombre_completo" class="form-control" required value="<?= escapar_html($usuario['nombre_completo']) ?>" placeholder="Ej: Juan González">
            </div>

            <!-- DATOS PERSONALES -->
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= escapar_html($usuario['email'] ?? '') ?>" placeholder="usuario@cebac.com.ar">
            </div>
            <div class="col-md-6">
                <label class="form-label">Teléfono</label>
                <input type="text" name="telefono" class="form-control" value="<?= escapar_html($usuario['telefono'] ?? '') ?>" placeholder="3764 20 59 95">
            </div>
            <div class="col-md-6">
                <label class="form-label">DNI</label>
                <input type="text" name="dni" class="form-control" value="<?= escapar_html($usuario['dni'] ?? '') ?>" placeholder="Sin puntos">
            </div>
            <div class="col-md-6">
                <label class="form-label">Cargo / área</label>
                <input type="text" name="cargo" class="form-control" value="<?= escapar_html($usuario['cargo'] ?? '') ?>" placeholder="Ej: Bioquímico, Recepción">
            </div>
            <div class="col-md-6">
                <label class="form-label">Matrícula profesional</label>
                <input type="text" name="matricula" class="form-control" value="<?= escapar_html($usuario['matricula'] ?? '') ?>" placeholder="Ej: MP 327 (opcional)">
            </div>
            <div class="col-md-6 d-flex align-items-center">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="activo" id="activo_check" value="1" <?= $usuario['activo'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activo_check">
                        Usuario activo (puede iniciar sesión)
                    </label>
                </div>
            </div>

            <!-- CONTRASEÑA -->
            <div class="col-12"><hr></div>
            <div class="col-12">
                <h6 class="fw-bold mb-0"><i class="bi bi-key me-1 text-primary"></i>Contraseña</h6>
                <small class="text-muted"><?= $esEdicion ? 'Dejala vacía para mantener la contraseña actual.' : 'La contraseña debe tener al menos 8 caracteres.' ?></small>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= $esEdicion ? 'Nueva contraseña (opcional)' : 'Contraseña *' ?></label>
                <input type="password" name="contrasena" class="form-control" <?= $esEdicion ? '' : 'required' ?> minlength="8" autocomplete="new-password">
            </div>
            <div class="col-md-6">
                <label class="form-label">Confirmar contraseña</label>
                <input type="password" name="contrasena2" class="form-control" <?= $esEdicion ? '' : 'required' ?> minlength="8" autocomplete="new-password">
            </div>

            <!-- ROLES -->
            <div class="col-12"><hr></div>
            <div class="col-12">
                <h6 class="fw-bold mb-0"><i class="bi bi-shield-lock me-1 text-primary"></i>Roles y permisos</h6>
                <small class="text-muted">Los privilegios se acumulan según los roles asignados.</small>
            </div>
            <div class="col-12">
                <div class="row g-2">
                    <?php foreach ($roles as $rol): ?>
                        <?php $checked = in_array((int) $rol['codigo'], $rolesSeleccionados, true); ?>
                        <div class="col-md-6">
                            <label class="border rounded p-3 d-block" style="cursor: pointer;">
                                <div class="d-flex align-items-start">
                                    <input class="form-check-input me-2 mt-1" type="checkbox" name="roles_ids[]" value="<?= (int) $rol['codigo'] ?>" <?= $checked ? 'checked' : '' ?>>
                                    <div>
                                        <strong><?= escapar_html($rol['nombre']) ?></strong>
                                        <p class="text-muted small mb-0"><?= escapar_html($rol['descripcion']) ?></p>
                                        <?php if (!empty($rol['privilegios'])): ?>
                                            <small class="text-muted d-block mt-1">Puede: <?= escapar_html($rol['privilegios']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- NOTAS -->
            <div class="col-12"><hr></div>
            <div class="col-12">
                <label class="form-label">Notas internas</label>
                <textarea name="notas" class="form-control" rows="2" placeholder="Ej: Ingresó el 15/09/2026, cubre turno tarde..."><?= escapar_html($usuario['notas'] ?? '') ?></textarea>
            </div>
        </div>
    </form>
    <?php
    exit;
}

$tituloPagina = ($esEdicion ? 'Editar' : 'Nuevo') . ' usuario | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="main-content">
    <h1><?= $esEdicion ? 'Editar usuario' : 'Nuevo usuario' ?></h1>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>