<?php
require_once __DIR__ . '/includes/autenticacion.php';
exigir_privilegio('practices.manage');
$id = (int) ($_GET['id'] ?? 0);
$precio = ['codigo_practica' => '', 'descripcion' => '', 'precio' => '0.00'];
if ($id > 0) { $stmt = db()->prepare('SELECT * FROM precios_practicas WHERE id=:id'); $stmt->execute(['id' => $id]); $precio = $stmt->fetch() ?: $precio; }
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $precio['descripcion'] = trim((string) ($_POST['descripcion'] ?? ''));
    $precio['precio'] = trim((string) ($_POST['precio'] ?? ''));
    if ($precio['descripcion'] === '' || !is_numeric($precio['precio']) || (float) $precio['precio'] < 0) $error = 'Descripción y precio válido son obligatorios.';
    else try {
        $params = ['descripcion' => $precio['descripcion'], 'precio' => number_format((float) $precio['precio'], 2, '.', '')];
        if ($id > 0) { $stmt = db()->prepare('UPDATE precios_practicas SET descripcion=:descripcion,precio=:precio WHERE id=:id'); $stmt->execute($params + ['id' => $id]); flash('Práctica actualizada correctamente.'); }
        else {
            $siguienteCodigo = (int) db()->query("SELECT COALESCE(MAX(CAST(SUBSTRING(codigo_practica, 5) AS UNSIGNED)), 0) + 1 FROM precios_practicas WHERE codigo_practica REGEXP '^PRA-[0-9]+$'")->fetchColumn();
            $params['codigo_practica'] = 'PRA-' . str_pad((string) $siguienteCodigo, 3, '0', STR_PAD_LEFT);
            $stmt = db()->prepare('INSERT INTO precios_practicas (codigo_practica,descripcion,precio) VALUES (:codigo_practica,:descripcion,:precio)'); $stmt->execute($params); flash('Práctica registrada correctamente.');
        }
        header('Location: precios_practicas.php'); exit;
    } catch (PDOException $excepcion) { $error = $excepcion->getCode() === '23000' ? 'Ese código de práctica ya existe.' : 'No se pudo guardar el precio.'; }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Precio práctica | CEBAC</title><link rel="stylesheet" href="public/assets/app.css"></head><body><?php include __DIR__ . '/includes/barra_lateral.php'; ?><main class="container narrow"><p><a href="precios_practicas.php">← Volver</a></p><h1><?= $id ? 'Editar' : 'Nueva' ?> práctica</h1><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><form method="post" class="form-grid"><?php if ($id): ?><div><label>Código de práctica</label><input value="<?= e($precio['codigo_practica']) ?>" readonly></div><?php endif; ?><div class="<?= $id ? '' : 'full' ?>"><label>Descripción *</label><input name="descripcion" required value="<?= e($precio['descripcion']) ?>" placeholder="Ej.: Hemograma completo"></div><div><label>Monto *</label><input type="number" min="0" step="0.01" name="precio" required value="<?= e((string) $precio['precio']) ?>"></div><div class="full"><button type="submit">Guardar práctica</button></div></form></main></body></html>



