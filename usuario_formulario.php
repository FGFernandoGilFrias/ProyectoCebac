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
    'creado_en' => null,
    'ultimo_acceso' => null,
    'actualizado_en' => null,
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
}

function iniciales(string $nombre): string
{
    $partes = preg_split('/\s+/', trim($nombre));
    $a = mb_substr($partes[0] ?? '', 0, 1, 'UTF-8');
    $b = isset($partes[1]) ? mb_substr($partes[1], 0, 1, 'UTF-8') : '';
    return mb_strtoupper($a . $b, 'UTF-8');
}

$tituloPagina = ($esEdicion ? 'Editar' : 'Nuevo') . ' usuario | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container">
<p><a href="usuarios.php">← Volver a usuarios</a></p>

<div class="user-header">
    <div class="user-avatar"><?= escapar_html(iniciales($usuario['nombre_completo'] ?: 'NN')) ?></div>
    <div class="user-header-info">
        <h1><?= $esEdicion ? 'Editar usuario' : 'Nuevo usuario' ?></h1>
        <p class="muted">
            <?= escapar_html($usuario['nombre_completo'] ?: 'Completá los datos') ?>
            <?php if ($esEdicion && !empty($usuario['cargo'])): ?>
                · <?= escapar_html($usuario['cargo']) ?>
            <?php endif; ?>
        </p>
        <?php if ($esEdicion): ?>
            <div class="user-header-badges">
                <span class="badge <?= $usuario['activo'] ? 'success' : 'danger' ?>">
                    <?= $usuario['activo'] ? 'Activo' : 'Inactivo' ?>
                </span>
                <?php if (!empty($usuario['ultimo_acceso'])): ?>
                    <small class="muted">Último acceso: <?= escapar_html($usuario['ultimo_acceso']) ?></small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?>

<form method="post" class="usuario-form">

    <section class="module form-section">
        <div class="form-section-heading">
            <div>
                <span class="eyebrow">Datos personales</span>
                <h2>Identificación</h2>
            </div>
            <span class="required-hint">* Campos obligatorios</span>
        </div>

        <div class="form-grid-2">
            <div>
                <label for="nombre_usuario">Usuario *</label>
                <input id="nombre_usuario" name="nombre_usuario" required value="<?= escapar_html($usuario['nombre_usuario']) ?>" placeholder="Ej: jgonzalez" autocomplete="off">
                <small class="muted">Sin espacios. Se usa para iniciar sesión.</small>
            </div>
            <div>
                <label for="nombre_completo">Nombre completo *</label>
                <input id="nombre_completo" name="nombre_completo" required value="<?= escapar_html($usuario['nombre_completo']) ?>" placeholder="Ej: Juan González">
            </div>
            <div>
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="<?= escapar_html($usuario['email'] ?? '') ?>" placeholder="usuario@cebac.com.ar">
            </div>
            <div>
                <label for="telefono">Teléfono</label>
                <input id="telefono" name="telefono" value="<?= escapar_html($usuario['telefono'] ?? '') ?>" placeholder="3764 20 59 95">
            </div>
            <div>
                <label for="dni">DNI</label>
                <input id="dni" name="dni" value="<?= escapar_html($usuario['dni'] ?? '') ?>" placeholder="Sin puntos">
            </div>
            <div>
                <label for="cargo">Cargo / área</label>
                <input id="cargo" name="cargo" value="<?= escapar_html($usuario['cargo'] ?? '') ?>" placeholder="Ej: Bioquímico, Recepción, Administración">
            </div>
            <div>
                <label for="matricula">Matrícula profesional</label>
                <input id="matricula" name="matricula" value="<?= escapar_html($usuario['matricula'] ?? '') ?>" placeholder="Ej: MP 327 (opcional)">
                <small class="muted">Solo para profesionales. Aparece en los informes.</small>
            </div>
            <div class="full">
                <label class="check-inline">
                    <input type="checkbox" name="activo" value="1" <?= $usuario['activo'] ? 'checked' : '' ?>>
                    <span>Usuario activo (puede iniciar sesión)</span>
                </label>
            </div>
        </div>
    </section>

    <section class="module form-section">
        <div class="form-section-heading">
            <div>
                <span class="eyebrow">Seguridad</span>
                <h2>Contraseña</h2>
                <p class="muted"><?= $esEdicion ? 'Dejala vacía para mantener la contraseña actual.' : 'La contraseña debe tener al menos 8 caracteres.' ?></p>
            </div>
        </div>

        <div class="form-grid-2">
            <div>
                <label for="contrasena"><?= $esEdicion ? 'Nueva contraseña (opcional)' : 'Contraseña *' ?></label>
                <div class="input-with-action">
                    <input id="contrasena" type="password" name="contrasena" <?= $esEdicion ? '' : 'required' ?> minlength="8" autocomplete="new-password">
                    <button type="button" class="icon-button toggle-password" data-target="contrasena" title="Mostrar/ocultar">
                        <svg class="eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg class="eye-closed" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </button>
                    <button type="button" class="button-small generate-password" data-target="contrasena">Generar</button>
                </div>
                <div class="password-strength" id="strength-meter" hidden>
                    <div class="strength-bar"><span></span></div>
                    <small class="strength-text"></small>
                </div>
            </div>
            <div>
                <label for="contrasena2">Confirmar contraseña</label>
                <div class="input-with-action">
                    <input id="contrasena2" type="password" name="contrasena2" <?= $esEdicion ? '' : 'required' ?> minlength="8" autocomplete="new-password">
                    <button type="button" class="icon-button toggle-password" data-target="contrasena2" title="Mostrar/ocultar">
                        <svg class="eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg class="eye-closed" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="module form-section">
        <div class="form-section-heading">
            <div>
                <span class="eyebrow">Accesos</span>
                <h2>Roles y permisos</h2>
                <p class="muted">Los privilegios se acumulan según los roles asignados.</p>
            </div>
        </div>

        <div class="roles-cards">
            <?php foreach ($roles as $rol): ?>
                <?php $checked = in_array((int) $rol['codigo'], $rolesSeleccionados, true); ?>
                <label class="role-card <?= $checked ? 'is-selected' : '' ?>">
                    <input type="checkbox" name="roles_ids[]" value="<?= (int) $rol['codigo'] ?>" <?= $checked ? 'checked' : '' ?>>
                    <div class="role-card-body">
                        <div class="role-card-head">
                            <strong><?= escapar_html($rol['nombre']) ?></strong>
                            <span class="role-check">✓</span>
                        </div>
                        <p class="muted"><?= escapar_html($rol['descripcion']) ?></p>
                        <?php if (!empty($rol['privilegios'])): ?>
                            <small class="role-privilegios">Puede: <?= escapar_html($rol['privilegios']) ?></small>
                        <?php endif; ?>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="module form-section">
        <div class="form-section-heading">
            <div>
                <span class="eyebrow">Interno</span>
                <h2>Notas internas</h2>
                <p class="muted">Solo visibles para administradores. No las ve el usuario.</p>
            </div>
        </div>
        <textarea name="notas" rows="3" placeholder="Ej: Ingresó el 15/09/2026, cubre turno tarde..."><?= escapar_html($usuario['notas'] ?? '') ?></textarea>
    </section>

    <?php if ($esEdicion): ?>
        <section class="module form-section audit-section">
            <div class="audit-grid">
                <div>
                    <span class="eyebrow">Creado</span>
                    <strong><?= escapar_html($usuario['creado_en'] ?? '—') ?></strong>
                </div>
                <div>
                    <span class="eyebrow">Último cambio</span>
                    <strong><?= escapar_html($usuario['actualizado_en'] ?? '—') ?></strong>
                </div>
                <div>
                    <span class="eyebrow">Último acceso</span>
                    <strong><?= escapar_html($usuario['ultimo_acceso'] ?? 'Nunca') ?></strong>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <div class="form-actions-bar">
        <a class="button-link secondary-button" href="usuarios.php">Cancelar</a>
        <button type="submit"><?= $esEdicion ? 'Guardar cambios' : 'Crear usuario' ?></button>
    </div>
</form>
</main>

<script>
(function () {
    'use strict';

    document.querySelectorAll('.toggle-password').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetId = this.dataset.target;
            const input = document.getElementById(targetId);
            if (!input) return;
            const abierto = input.type === 'text';
            input.type = abierto ? 'password' : 'text';
            this.querySelector('.eye-open').style.display = abierto ? '' : 'none';
            this.querySelector('.eye-closed').style.display = abierto ? 'none' : '';
        });
    });

    document.querySelectorAll('.generate-password').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetId = this.dataset.target;
            const input = document.getElementById(targetId);
            const input2 = document.getElementById(targetId + '2');
            if (!input) return;

            const mayus = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
            const minus = 'abcdefghijkmnpqrstuvwxyz';
            const nums = '23456789';
            const simbolos = '!@#$%&*';
            const todos = mayus + minus + nums + simbolos;

            let pass = '';
            pass += mayus[Math.floor(Math.random() * mayus.length)];
            pass += minus[Math.floor(Math.random() * minus.length)];
            pass += nums[Math.floor(Math.random() * nums.length)];
            pass += simbolos[Math.floor(Math.random() * simbolos.length)];
            for (let i = pass.length; i < 14; i++) {
                pass += todos[Math.floor(Math.random() * todos.length)];
            }
            pass = pass.split('').sort(() => Math.random() - 0.5).join('');

            input.value = pass;
            input.type = 'text';
            if (input2) {
                input2.value = pass;
                input2.type = 'text';
            }

            input.dispatchEvent(new Event('input'));
        });
    });

    const passInput = document.getElementById('contrasena');
    const meter = document.getElementById('strength-meter');
    if (passInput && meter) {
        const bar = meter.querySelector('.strength-bar span');
        const text = meter.querySelector('.strength-text');

        function calcularFuerza(valor) {
            let puntos = 0;
            if (valor.length >= 8) puntos++;
            if (valor.length >= 12) puntos++;
            if (/[A-Z]/.test(valor)) puntos++;
            if (/[a-z]/.test(valor)) puntos++;
            if (/[0-9]/.test(valor)) puntos++;
            if (/[^A-Za-z0-9]/.test(valor)) puntos++;
            return puntos;
        }

        passInput.addEventListener('input', function () {
            const val = this.value;
            if (val === '') {
                meter.hidden = true;
                return;
            }
            meter.hidden = false;
            const puntos = calcularFuerza(val);
            let ancho = 0, clase = '', texto = '';
            if (puntos <= 2) { ancho = 33; clase = 'debil'; texto = 'Débil'; }
            else if (puntos <= 4) { ancho = 66; clase = 'media'; texto = 'Media'; }
            else { ancho = 100; clase = 'fuerte'; texto = 'Fuerte'; }
            bar.style.width = ancho + '%';
            bar.className = clase;
            text.textContent = 'Fuerza: ' + texto;
            text.className = 'strength-text ' + clase;
        });
    }

    document.querySelectorAll('.role-card input[type="checkbox"]').forEach(function (input) {
        input.addEventListener('change', function () {
            this.closest('.role-card').classList.toggle('is-selected', this.checked);
        });
    });

    const nc = document.getElementById('nombre_completo');
    const av = document.querySelector('.user-avatar');
    if (nc && av) {
        nc.addEventListener('input', function () {
            const partes = this.value.trim().split(/\s+/);
            const a = (partes[0] || '').charAt(0);
            const b = (partes[1] || '').charAt(0);
            av.textContent = (a + b).toUpperCase() || 'NN';
        });
    }
})();
</script>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>