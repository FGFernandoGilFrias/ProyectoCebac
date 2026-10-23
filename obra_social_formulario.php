<?php
require_once __DIR__ . '/includes/auth.php';
exigir_privilegio('obras_sociales.gestionar');

$id = (int) ($_GET['id'] ?? 0);
$esEdicion = $id > 0;

$obraSocial = [
    'nombre' => '',
    'cuit' => '',
    'condiciones_convenio' => '',
    'fecha_alta' => date('Y-m-d'),
    'fecha_baja' => null,
];

if ($esEdicion) {
    $consulta = conexion_bd()->prepare('SELECT * FROM obras_sociales WHERE codigo = :codigo');
    $consulta->execute(['codigo' => $id]);
    $fila = $consulta->fetch();
    if ($fila) {
        $obraSocial = $fila;
    }
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $obraSocial['nombre'] = trim((string) ($_POST['nombre'] ?? ''));
    $obraSocial['cuit'] = trim((string) ($_POST['cuit'] ?? ''));
    $obraSocial['condiciones_convenio'] = trim((string) ($_POST['condiciones_convenio'] ?? ''));
    $obraSocial['fecha_alta'] = trim((string) ($_POST['fecha_alta'] ?? ''));
    $fechaBaja = trim((string) ($_POST['fecha_baja'] ?? ''));
    $obraSocial['fecha_baja'] = $fechaBaja !== '' ? $fechaBaja : null;

    if ($obraSocial['nombre'] === '' || $obraSocial['cuit'] === '') {
        $error = 'Nombre y CUIT son obligatorios.';
    } else {
        // Verificar CUIT duplicado
        $stmtCheck = conexion_bd()->prepare('SELECT codigo FROM obras_sociales WHERE cuit = :cuit AND codigo <> :codigo LIMIT 1');
        $stmtCheck->execute(['cuit' => $obraSocial['cuit'], 'codigo' => $id]);

        if ($stmtCheck->fetch()) {
            $error = 'Ya existe una obra social con el CUIT ' . escapar_html($obraSocial['cuit']) . '.';
        } else {
            try {
                $conexion = conexion_bd();

                if ($esEdicion) {
                    $consulta = $conexion->prepare(
                        'UPDATE obras_sociales
                         SET nombre = :nombre, cuit = :cuit,
                             condiciones_convenio = :condiciones,
                             fecha_alta = :fecha_alta, fecha_baja = :fecha_baja
                         WHERE codigo = :codigo'
                    );
                    $consulta->execute([
                        'nombre' => $obraSocial['nombre'],
                        'cuit' => $obraSocial['cuit'],
                        'condiciones' => $obraSocial['condiciones_convenio'] !== '' ? $obraSocial['condiciones_convenio'] : null,
                        'fecha_alta' => $obraSocial['fecha_alta'] !== '' ? $obraSocial['fecha_alta'] : null,
                        'fecha_baja' => $obraSocial['fecha_baja'],
                        'codigo' => $id,
                    ]);
                } else {
                    $consulta = $conexion->prepare(
                        'INSERT INTO obras_sociales (nombre, cuit, condiciones_convenio, fecha_alta)
                         VALUES (:nombre, :cuit, :condiciones, :fecha_alta)'
                    );
                    $consulta->execute([
                        'nombre' => $obraSocial['nombre'],
                        'cuit' => $obraSocial['cuit'],
                        'condiciones' => $obraSocial['condiciones_convenio'] !== '' ? $obraSocial['condiciones_convenio'] : null,
                        'fecha_alta' => $obraSocial['fecha_alta'] !== '' ? $obraSocial['fecha_alta'] : date('Y-m-d'),
                    ]);
                    $id = (int) $conexion->lastInsertId();
                }

                // Respuesta JSON si es modal
                if (isset($_POST['_modal']) && $_POST['_modal'] === '1') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'ok' => true,
                        'mensaje' => $esEdicion ? 'Obra social actualizada correctamente.' : 'Obra social registrada correctamente.',
                        'id' => $id,
                    ]);
                    exit;
                }

                mensaje_flash($esEdicion ? 'Obra social actualizada correctamente.' : 'Obra social registrada correctamente.');
                header('Location: obras_sociales.php');
                exit;
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $error = 'Ya existe una obra social con ese CUIT.';
                } else {
                    $error = 'No se pudo guardar la obra social.';
                }
            }
        }
    }

    // Respuesta JSON si es modal y hay error
    if (isset($_POST['_modal']) && $_POST['_modal'] === '1') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $error]);
        exit;
    }
}

// Si es modal, solo devolver el formulario
$esModal = isset($_GET['_modal']) && $_GET['_modal'] === '1';

if ($esModal) {
    ?>
    <form method="post" id="obra_social-modal-form">
        <input type="hidden" name="_modal" value="1">

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= escapar_html($error) ?></div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Nombre *</label>
                <input type="text" name="nombre" class="form-control" required value="<?= escapar_html($obraSocial['nombre']) ?>" placeholder="Ej: CONSALUD">
            </div>
            <div class="col-md-4">
                <label class="form-label">CUIT *</label>
                <input type="text" name="cuit" class="form-control" required value="<?= escapar_html($obraSocial['cuit']) ?>" placeholder="Ej: 33-707043569-9">
            </div>
            <div class="col-12">
                <label class="form-label">Condiciones del convenio</label>
                <textarea name="condiciones_convenio" class="form-control" rows="3" placeholder="Ej: Convenio general con cobertura según plan del paciente"><?= escapar_html($obraSocial['condiciones_convenio'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Fecha de alta</label>
                <input type="date" name="fecha_alta" class="form-control" value="<?= escapar_html($obraSocial['fecha_alta'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Fecha de baja</label>
                <input type="date" name="fecha_baja" class="form-control" value="<?= escapar_html($obraSocial['fecha_baja'] ?? '') ?>">
                <small class="text-muted">Dejar vacío si está activa.</small>
            </div>
        </div>
    </form>
    <?php
    exit;
}

// Si no es modal, comportamiento de página completa (fallback)
$tituloPagina = ($esEdicion ? 'Editar' : 'Nueva') . ' obra social | CEBAC';
include __DIR__ . '/includes/header.php';
?>
<main class="main-content">
    <h1><?= $esEdicion ? 'Editar obra social' : 'Nueva obra social' ?></h1>
    <!-- ... el formulario completo, igual al de arriba ... -->
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>