<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/base_datos.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

function sesion_iniciada(): bool
{
    return isset($_SESSION['usuario']);
}

function exigir_inicio_sesion(): void
{
    if (!sesion_iniciada()) {
        header('Location: login.php');
        exit;
    }
}

function tiene_privilegio(string $privilegio): bool
{
    if (!sesion_iniciada()) {
        return false;
    }
    static $cache = [];
    if (array_key_exists($privilegio, $cache)) {
        return $cache[$privilegio];
    }
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM usuarios_roles ur
         JOIN roles_privilegios rp ON rp.rol_id = ur.rol_id
         JOIN privilegios p ON p.id = rp.privilegio_id
         JOIN roles r ON r.id = ur.rol_id
         WHERE ur.usuario_id = :usuario_id AND p.codigo = :codigo AND r.activo = 1'
    );
    $stmt->execute(['usuario_id' => (int) $_SESSION['usuario']['id'], 'codigo' => $privilegio]);
    return $cache[$privilegio] = (int) $stmt->fetchColumn() > 0;
}

function exigir_privilegio(string $privilegio): void
{
    exigir_inicio_sesion();
    if (!tiene_privilegio($privilegio)) {
        http_response_code(403);
        exit('No tienes permisos para acceder a este módulo.');
    }
}

function usuario_actual(): array
{
    return $_SESSION['usuario'] ?? [];
}

function flash(?string $mensaje = null): ?string
{
    if ($mensaje !== null) {
        $_SESSION['flash'] = $mensaje;
        return null;
    }
    $valor = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $valor;
}

function actualizar_finalizacion_orden(int $ordenId): void
{
    $pdo = db();
    $stmt = $pdo->prepare(
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
    $stmt->execute(['id' => $ordenId]);
    $progreso = $stmt->fetch();
    if (!$progreso) {
        return;
    }
    $completo = (float) $progreso['monto_pagado'] >= (float) $progreso['total_adeudado']
        && (int) $progreso['cantidad_estudios'] > 0
        && (int) $progreso['muestras_finalizadas'] === (int) $progreso['cantidad_estudios']
        && (int) $progreso['resultados_entregados'] === (int) $progreso['cantidad_estudios'];
    if ($completo) {
        $update = $pdo->prepare("UPDATE ordenes SET estado = 'Finalizada' WHERE id = :id");
        $update->execute(['id' => $ordenId]);
    }
}



