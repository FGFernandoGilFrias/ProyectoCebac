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
    $conexion = conexion_bd();
    $usuarioId = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuarioId <= 0 && isset($_SESSION['usuario']['codigo'])) {
        $consultaUsuario = $conexion->prepare('SELECT id FROM usuarios WHERE codigo = :codigo LIMIT 1');
        $consultaUsuario->execute(['codigo' => (int) $_SESSION['usuario']['codigo']]);
        $usuarioId = (int) $consultaUsuario->fetchColumn();
    }
    if ($usuarioId <= 0) {
        return $cache[$privilegio] = false;
    }
    $codigoPrivilegioColumna = 'p.codigo';
    $columnaCodigoPrivilegio = $conexion->query("SHOW COLUMNS FROM privilegios LIKE 'codigo_privilegio'")->fetch();
    $definicionCodigo = $conexion->query("SHOW COLUMNS FROM privilegios LIKE 'codigo'")->fetch();
    if (
        $columnaCodigoPrivilegio
        && $definicionCodigo
        && preg_match('/\b(int|tinyint|smallint|mediumint|bigint)\b/i', (string) $definicionCodigo['Type'])
    ) {
        $codigoPrivilegioColumna = 'p.codigo_privilegio';
    }
    $consulta = $conexion->prepare(
        "SELECT COUNT(*) FROM usuarios_roles ur
         JOIN roles_privilegios rp ON rp.rol_id = ur.rol_id
         JOIN privilegios p ON p.id = rp.privilegio_id
         JOIN roles r ON r.id = ur.rol_id
         WHERE ur.usuario_id = :usuario_id AND $codigoPrivilegioColumna = :codigo AND r.activo = 1"
    );
    $consulta->execute(['usuario_id' => $usuarioId, 'codigo' => $privilegio]);
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

function actualizar_finalizacion_orden(int $ordenId): void
{
    $conexion = conexion_bd();
    $consulta = $conexion->prepare(
        "SELECT o.total_adeudado, o.monto_pagado,
                COUNT(os.id) AS cantidad_estudios,
                SUM(CASE WHEN m.estado = 'Finalizada' THEN 1 ELSE 0 END) AS muestras_finalizadas,
                SUM(CASE WHEN r.estado = 'Entregado' THEN 1 ELSE 0 END) AS resultados_entregados
         FROM ordenes o
         JOIN ordenes_estudios os ON os.orden_id = o.id
         LEFT JOIN muestras m ON m.orden_estudio_id = os.id
         LEFT JOIN resultados r ON r.orden_estudio_id = os.id
         WHERE o.id = :id
         GROUP BY o.id"
    );
    $consulta->execute(['id' => $ordenId]);
    $progreso = $consulta->fetch();
    if (!$progreso) {
        return;
    }
    $completo = (float) $progreso['monto_pagado'] >= (float) $progreso['total_adeudado']
        && (int) $progreso['cantidad_estudios'] > 0
        && (int) $progreso['muestras_finalizadas'] === (int) $progreso['cantidad_estudios']
        && (int) $progreso['resultados_entregados'] === (int) $progreso['cantidad_estudios'];
    if ($completo) {
        $actualizacion = $conexion->prepare("UPDATE ordenes SET estado = 'Finalizada' WHERE id = :id");
        $actualizacion->execute(['id' => $ordenId]);
    }
}







