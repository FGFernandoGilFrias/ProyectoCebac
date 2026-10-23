<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function escapar_html(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function esta_autenticado(): bool
{
    return isset($_SESSION['usuario']);
}

function exigir_acceso(): void
{
    if (!esta_autenticado()) {
        header('Location: login.php');
        exit;
    }
}

function tiene_privilegio(string $privilegio): bool
{
    if (!esta_autenticado()) {
        return false;
    }
    static $cache = [];
    if (array_key_exists($privilegio, $cache)) {
        return $cache[$privilegio];
    }
    $consulta = conexion_bd()->prepare(
        'SELECT COUNT(*) FROM usuarios_roles ur
         JOIN roles_privilegios rp ON rp.rol_codigo = ur.rol_codigo
         JOIN privilegios p ON p.codigo = rp.privilegio_codigo
         JOIN roles r ON r.codigo = ur.rol_codigo
         WHERE ur.usuario_codigo = :usuario_codigo AND p.codigo_privilegio = :codigo_privilegio AND r.activo = 1'
    );
    $consulta->execute([
        'usuario_codigo' => (int) $_SESSION['usuario']['codigo'],
        'codigo_privilegio' => $privilegio,
    ]);
    return $cache[$privilegio] = (int) $consulta->fetchColumn() > 0;
}

function exigir_privilegio(string $privilegio): void
{
    exigir_acceso();
    if (!tiene_privilegio($privilegio)) {
        http_response_code(403);
        exit('No tienes privilegios para acceder a este módulo.');
    }
}

function usuario_actual(): array
{
    return $_SESSION['usuario'] ?? [];
}

function mensaje_flash(?string $mensaje = null): ?string
{
    if ($mensaje !== null) {
        $_SESSION['mensaje_flash'] = $mensaje;
        return null;
    }
    $value = $_SESSION['mensaje_flash'] ?? null;
    unset($_SESSION['mensaje_flash']);
    return $value;
}

/**
 * Formatea el código de una orden con padding de 4 dígitos.
 */
function formatear_codigo_orden($codigo): string
{
    return str_pad((string) $codigo, 4, '0', STR_PAD_LEFT);
}

/**
 * Actualiza el estado de la orden según el estado de sus estudios.
 *
 * Reglas:
 *   - Si tiene algún estudio RECHAZADO → Pendiente (requiere segundo análisis)
 *   - Si tiene algún estudio PENDIENTE → Pendiente (faltan muestras)
 *   - Si TODOS los estudios tienen resultado válido → Finalizado
 *   - Si TODOS los estudios están validados pero faltan resultados → Validado
 *   - Si no hay check-in → Pendiente
 */
function actualizar_estado_orden(int $ordenCodigo): void
{
    $conexion = conexion_bd();

    $stmt = $conexion->prepare('SELECT COUNT(*) FROM estudios_orden WHERE orden_codigo = :oid');
    $stmt->execute(['oid' => $ordenCodigo]);
    $totalEstudios = (int) $stmt->fetchColumn();

    if ($totalEstudios === 0) {
        return;
    }

    // Obtener resultado de la orden (si existe)
    $stmt = $conexion->prepare('SELECT codigo, resultado FROM resultados WHERE orden_codigo = :oid LIMIT 1');
    $stmt->execute(['oid' => $ordenCodigo]);
    $resultado = $stmt->fetch();
    $resultadoCodigo = (int) ($resultado['codigo'] ?? 0);

    // Sin registro de check-in
    if ($resultadoCodigo === 0) {
        $update = $conexion->prepare('UPDATE ordenes SET estado = :estado WHERE codigo = :oid');
        $update->execute(['estado' => 'Pendiente', 'oid' => $ordenCodigo]);
        return;
    }

    // Contar PENDIENTES y RECHAZADOS (buscando en cualquier parte del texto)
    $stmt = $conexion->prepare(
        "SELECT
            SUM(CASE WHEN error_muestra LIKE '%PENDIENTE%' THEN 1 ELSE 0 END) AS pendientes,
            SUM(CASE WHEN error_muestra LIKE '%RECHAZADO%' THEN 1 ELSE 0 END) AS rechazados
         FROM historial_errores
         WHERE resultado_codigo = :rid"
    );
    $stmt->execute(['rid' => $resultadoCodigo]);
    $errores = $stmt->fetch();

    $pendientes = (int) ($errores['pendientes'] ?? 0);
    $rechazados = (int) ($errores['rechazados'] ?? 0);

    // Contar estudios con resultado cargado (y válido)
    $estudiosConResultado = 0;
    if (!empty($resultado['resultado'])) {
        foreach (preg_split('/\R/', $resultado['resultado']) as $linea) {
            $partes = explode(':', $linea, 2);
            if (count($partes) === 2) {
                $valor = trim($partes[1]);
                if ($valor !== ''
                    && stripos($valor, 'RECHAZADO') === false
                    && stripos($valor, 'PENDIENTE') === false
                ) {
                    $estudiosConResultado++;
                }
            }
        }
    }

    // Determinar estado
    if ($rechazados > 0) {
        $nuevoEstado = 'Pendiente';
    } elseif ($pendientes > 0) {
        $nuevoEstado = 'Pendiente';
    } elseif ($estudiosConResultado >= $totalEstudios) {
        $nuevoEstado = 'Finalizado';
    } else {
        $nuevoEstado = 'Validado';
    }

    $update = $conexion->prepare('UPDATE ordenes SET estado = :estado WHERE codigo = :oid');
    $update->execute(['estado' => $nuevoEstado, 'oid' => $ordenCodigo]);
}

/* ============================================================
   Protección CSRF
   ============================================================ */

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . escapar_html(csrf_token()) . '">';
}

function verificar_csrf(): void
{
    $enviado = (string) ($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '');
    if ($enviado === '' || !hash_equals(csrf_token(), $enviado)) {
        http_response_code(403);
        exit('Token de seguridad inválido. Volvé a la página anterior e intentá de nuevo.');
    }
}
require_once __DIR__ . '/helpers.php';