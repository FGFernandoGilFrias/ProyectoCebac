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
                mensaje_flash('Estudio actualizado correctamente.');
            } else {
                $consulta = conexion_bd()->prepare('INSERT INTO estudios (codigo, estudio, parametros, practica_codigo) VALUES (:codigo, :estudio, :parametros, :practica_codigo)');
                $consulta->execute([
                    'codigo' => $estudio['codigo'],
                    'estudio' => $estudio['estudio'],
                    'parametros' => $estudio['parametros'] !== '' ? $estudio['parametros'] : null,
                    'practica_codigo' => (int) $estudio['practica_codigo'],
                ]);
                mensaje_flash('Estudio registrado correctamente.');
            }
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

$tituloPagina = ($esEdicion ? 'Editar' : 'Nuevo') . ' estudio | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="container narrow">
<p><a href="estudios.php">← Volver a estudios</a></p>
<h1><?= $esEdicion ? 'Editar estudio' : 'Nuevo estudio' ?></h1>

<?php if ($error): ?><div class="alert error"><?= escapar_html($error) ?></div><?php endif; ?>

<form method="post" class="form-grid">
    <div class="full">
        <label>Código del estudio *</label>
        <input name="codigo" required value="<?= escapar_html($estudio['codigo']) ?>" placeholder="Ej: 668020 o LAB-001" maxlength="30">
        <small class="muted">Acepta letras, números y caracteres especiales. Debe coincidir con el código de la obra social.</small>
    </div>
    <div class="full"><label>Nombre del estudio *</label><input name="estudio" required value="<?= escapar_html($estudio['estudio']) ?>" placeholder="Ej: HEMATOCRITO" maxlength="80"></div>
    <div class="full"><label>Parámetros</label><input name="parametros" value="<?= escapar_html($estudio['parametros'] ?? '') ?>" placeholder="Ej: 36 - 46" maxlength="50"></div>
    <div class="full">
        <label>Precio de la práctica *</label>
        <select name="practica_codigo" required>
            <option value="">Seleccionar precio</option>
            <?php foreach ($precios as $p): ?>
                <option value="<?= (int) $p['codigo'] ?>" <?= (string) $estudio['practica_codigo'] === (string) $p['codigo'] ? 'selected' : '' ?>>
                    Código <?= (int) $p['codigo'] ?> — $ <?= number_format((float) $p['precio'], 2, ',', '.') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="full"><button type="submit">Guardar estudio</button></div>
</form>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>