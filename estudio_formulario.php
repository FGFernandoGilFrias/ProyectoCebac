<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('estudios.gestionar');

$codigoOriginal = (string) ($_GET['id'] ?? '');
$esEdicion = $codigoOriginal !== '';
$estudio = ['codigo' => '', 'estudio' => '', 'parametros' => '', 'practica_codigo' => ''];

if ($esEdicion) {
    $consulta = conexion_bd()->prepare('SELECT * FROM estudios WHERE codigo = :codigo');
    $consulta->execute(['codigo' => $codigoOriginal]);
    $fila = $consulta->fetch();
    if (!$fila) {
        http_response_code(404);
        exit('Estudio no encontrado.');
    }
    $estudio = $fila;
}

$error = null;
$precios = conexion_bd()->query('SELECT codigo, precio FROM precio_practica ORDER BY codigo')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $estudio['codigo'] = trim((string) ($_POST['codigo'] ?? ''));
    $estudio['estudio'] = trim((string) ($_POST['estudio'] ?? ''));
    $estudio['parametros'] = trim((string) ($_POST['parametros'] ?? ''));
    $estudio['practica_codigo'] = trim((string) ($_POST['practica_codigo'] ?? ''));

    if ($estudio['codigo'] === '' || $estudio['estudio'] === '' || $estudio['practica_codigo'] === '') {
        $error = 'Código, nombre y precio de práctica son obligatorios.';
    } else {
        // Verificar código duplicado
        $stmtCheck = conexion_bd()->prepare('SELECT codigo FROM estudios WHERE codigo = :codigo AND codigo <> :codigo_original LIMIT 1');
        $stmtCheck->execute(['codigo' => $estudio['codigo'], 'codigo_original' => $codigoOriginal]);

        if ($stmtCheck->fetch()) {
            $error = 'Ya existe un estudio con el código ' . escapar_html($estudio['codigo']) . '.';
        } else {
            try {
                if ($esEdicion) {
                    $consulta = conexion_bd()->prepare('UPDATE estudios SET codigo=:codigo, estudio=:estudio, parametros=:parametros, practica_codigo=:practica_codigo WHERE codigo=:codigo_original');
                    $consulta->execute([
                        'codigo' => $estudio['codigo'],
                        'estudio' => $estudio['estudio'],
                        'parametros' => $estudio['parametros'] !== '' ? $estudio['parametros'] : null,
                        'practica_codigo' => (int) $estudio['practica_codigo'],
                        'codigo_original' => $codigoOriginal,
                    ]);
                } else {
                    $consulta = conexion_bd()->prepare('INSERT INTO estudios (codigo, estudio, parametros, practica_codigo) VALUES (:codigo, :estudio, :parametros, :practica_codigo)');
                    $consulta->execute([
                        'codigo' => $estudio['codigo'],
                        'estudio' => $estudio['estudio'],
                        'parametros' => $estudio['parametros'] !== '' ? $estudio['parametros'] : null,
                        'practica_codigo' => (int) $estudio['practica_codigo'],
                    ]);
                }

                if (isset($_POST['_modal']) && $_POST['_modal'] === '1') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'ok' => true,
                        'mensaje' => $esEdicion ? 'Estudio actualizado correctamente.' : 'Estudio registrado correctamente.',
                    ]);
                    exit;
                }

                mensaje_flash($esEdicion ? 'Estudio actualizado correctamente.' : 'Estudio registrado correctamente.');
                header('Location: estudios.php');
                exit;
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $error = 'Ya existe un estudio con ese código.';
                } else {
                    $error = 'No se pudo guardar el estudio.';
                }
            }
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
    <form method="post" id="estudio-modal-form">
        <input type="hidden" name="_modal" value="1">

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= escapar_html($error) ?></div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Código del estudio *</label>
                <input type="text" name="codigo" class="form-control" required value="<?= escapar_html($estudio['codigo']) ?>" placeholder="Ej: 668020 o LAB-001" maxlength="30">
                <small class="text-muted">Acepta letras, números y caracteres especiales.</small>
            </div>
            <div class="col-md-8">
                <label class="form-label">Nombre del estudio *</label>
                <input type="text" name="estudio" class="form-control" required value="<?= escapar_html($estudio['estudio']) ?>" placeholder="Ej: HEMATOCRITO" maxlength="80">
            </div>
            <div class="col-12">
                <label class="form-label">Parámetros de referencia</label>
                <textarea name="parametros" class="form-control" rows="4" placeholder="Ej:&#10;Hematíes: 4.000.000 - 5.200.000&#10;Hemoglobina: 12 - 16&#10;Hematocrito: 36 - 46"><?= escapar_html($estudio['parametros'] ?? '') ?></textarea>
                <small class="text-muted">Un parámetro por línea. Se muestra en la orden, instrucciones y resultados.</small>
            </div>
            <div class="col-md-6">
                <label class="form-label">Precio de la práctica *</label>
                <select name="practica_codigo" class="form-select" required>
                    <option value="">Seleccionar precio</option>
                    <?php foreach ($precios as $p): ?>
                        <option value="<?= (int) $p['codigo'] ?>" <?= (string) $estudio['practica_codigo'] === (string) $p['codigo'] ? 'selected' : '' ?>>
                            Código <?= (int) $p['codigo'] ?> — $ <?= number_format((float) $p['precio'], 2, ',', '.') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>
    <?php
    exit;
}

$tituloPagina = ($esEdicion ? 'Editar' : 'Nuevo') . ' estudio | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="main-content">
    <h1><?= $esEdicion ? 'Editar estudio' : 'Nuevo estudio' ?></h1>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>