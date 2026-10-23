<?php
/**
 * includes/resultado_notificar.php
 * Lógica que decide cuándo notificar al paciente por email + adjunta el PDF del resultado.
 *
 * ✅ ADAPTADO AL SISTEMA A (CEBAC)
 * - Usa conexion_bd() en lugar de db()
 * - Usa la estructura real: ordenes (codigo), pacientes (codigo, correo), estudios_orden, resultados (orden_codigo), historial_errores
 */

require_once __DIR__ . '/email.php';
require_once __DIR__ . '/pdf_helper.php';

/**
 * Calcula el estado global de la orden según sus estudios.
 * Devuelve: 'listo' | 'rechazado' | 'entregado' | 'pendiente'
 */
function estado_global_orden(int $ordenCodigo): string
{
    $conexion = conexion_bd();

    // Traer todos los estudios de la orden
    $stmt = $conexion->prepare(
        "SELECT e.codigo AS estudio_codigo, e.estudio AS nombre_estudio
         FROM estudios_orden eo
         JOIN estudios e ON e.codigo = eo.estudio_codigo
         WHERE eo.orden_codigo = :oid"
    );
    $stmt->execute(['oid' => $ordenCodigo]);
    $estudios = $stmt->fetchAll();

    if (!$estudios) return 'pendiente';

    // Traer resultado + errores de la orden
    $stmt = $conexion->prepare('SELECT codigo, resultado FROM resultados WHERE orden_codigo = :oid LIMIT 1');
    $stmt->execute(['oid' => $ordenCodigo]);
    $resultado = $stmt->fetch();
    $resultadoCodigo = (int) ($resultado['codigo'] ?? 0);

    $errores = [];
    if ($resultadoCodigo > 0) {
        $stmtErr = $conexion->prepare('SELECT error_muestra FROM historial_errores WHERE resultado_codigo = :rid');
        $stmtErr->execute(['rid' => $resultadoCodigo]);
        $errores = $stmtErr->fetchAll(PDO::FETCH_COLUMN);
    }

    $total = count($estudios);
    $conResultado = 0;
    $conRechazo = 0;
    $conPendiente = 0;

    // Parsear el resultado para ver qué estudios tienen valor
    $valores = [];
    if (!empty($resultado['resultado'])) {
        foreach (preg_split('/\R/', $resultado['resultado']) as $linea) {
            $partes = explode(':', $linea, 2);
            if (count($partes) === 2) {
                $valores[trim($partes[0])] = trim($partes[1]);
            }
        }
    }

    foreach ($estudios as $est) {
        $nombreEst = $est['nombre_estudio'];

        $tieneRechazo = false;
        $tienePendiente = false;
        foreach ($errores as $err) {
            if (stripos($err, $nombreEst) === false) continue;
            if (stripos($err, 'RECHAZADO') !== false) $tieneRechazo = true;
            elseif (stripos($err, 'PENDIENTE') !== false) $tienePendiente = true;
        }

        if ($tieneRechazo) {
            $conRechazo++;
        } elseif ($tienePendiente) {
            $conPendiente++;
        } elseif (isset($valores[$nombreEst]) && $valores[$nombreEst] !== '' && stripos($valores[$nombreEst], 'RECHAZADO') === false && stripos($valores[$nombreEst], 'PENDIENTE') === false) {
            $conResultado++;
        }
    }

    if ($conRechazo > 0 && ($conRechazo + $conResultado) === $total) return 'rechazado';
    if ($conResultado === $total) return 'listo';
    if ($conPendiente > 0) return 'pendiente';

    return 'pendiente';
}

/**
 * Verifica si ya se envió un email de cierto tipo para la orden.
 */
function email_ya_enviado(int $ordenCodigo, string $tipo): bool
{
    $stmt = conexion_bd()->prepare(
        "SELECT COUNT(*) FROM envios_email
         WHERE orden_codigo = :orden_codigo AND tipo = :tipo AND estado = 'enviado'"
    );
    $stmt->execute(['orden_codigo' => $ordenCodigo, 'tipo' => $tipo]);
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Registra el envío en la tabla envios_email.
 */
function registrar_envio(int $ordenCodigo, string $tipo, string $destinatario, string $asunto, string $cuerpo, bool $ok, ?string $error = null): void
{
    $stmt = conexion_bd()->prepare(
        "INSERT INTO envios_email (orden_codigo, tipo, destinatario, asunto, cuerpo, estado, error_mensaje)
         VALUES (:orden_codigo, :tipo, :destinatario, :asunto, :cuerpo, :estado, :error)"
    );
    $stmt->execute([
        'orden_codigo' => $ordenCodigo,
        'tipo' => $tipo,
        'destinatario' => $destinatario,
        'asunto' => $asunto,
        'cuerpo' => $cuerpo,
        'estado' => $ok ? 'enviado' : 'fallido',
        'error' => $error,
    ]);
}

/**
 * Cuerpo del email de "resultados listos".
 */
function cuerpo_resultados_listos(array $orden, array $estudios): string
{
    $config = require __DIR__ . '/config_lab.php';
    $lineas = [];
    $lineas[] = "Hola " . $orden['nombre'] . ",";
    $lineas[] = "";
    $lineas[] = "Te informamos que los resultados de tu orden #" . $orden['codigo_orden'] . " ya están listos.";
    $lineas[] = "Adjuntamos el informe en formato PDF.";
    $lineas[] = "";
    $lineas[] = "Estudios:";
    foreach ($estudios as $e) {
        $lineas[] = "  • " . $e['nombre_estudio'] . " (" . $e['estudio_codigo'] . ")";
    }
    $lineas[] = "";
    $lineas[] = "Horario de atención:";
    $lineas[] = $config['horario'];
    $lineas[] = "";
    $lineas[] = "¡Gracias por confiar en nosotros!";
    $lineas[] = "";
    $lineas[] = $config['nombre'];
    $lineas[] = $config['direccion'];
    $lineas[] = "Tel: " . $config['telefono'];
    return implode("\n", $lineas);
}

/**
 * Cuerpo del email de "rechazo".
 */
function cuerpo_rechazo(array $orden, array $rechazados): string
{
    $config = require __DIR__ . '/config_lab.php';
    $lineas = [];
    $lineas[] = "Hola " . $orden['nombre'] . ",";
    $lineas[] = "";
    $lineas[] = "Te escribimos del " . $config['nombre'] . ". Alguno de los estudios de tu orden #" . $orden['codigo_orden'] . " no pudo procesarse y necesitamos que vuelvas para una nueva extracción.";
    $lineas[] = "";
    $lineas[] = "Estudios a repetir:";
    foreach ($rechazados as $r) {
        $lineas[] = "  • " . $r['nombre_estudio'] . " (motivo: " . ($r['motivo_rechazo'] ?: 'sin especificar') . ")";
    }
    $lineas[] = "";
    $lineas[] = "No hace falta abonar nada adicional. Podés acercarte dentro del horario de atención.";
    $lineas[] = "";
    $lineas[] = "Disculpá las molestias.";
    $lineas[] = "";
    $lineas[] = $config['nombre'];
    $lineas[] = $config['direccion'];
    $lineas[] = "Tel: " . $config['telefono'];
    return implode("\n", $lineas);
}

/**
 * Genera el PDF del resultado de la orden y devuelve su contenido binario.
 */
function generar_pdf_orden_binario(int $ordenCodigo): ?string
{
    $conexion = conexion_bd();

    // 1. Datos de la orden + paciente
    $stmt = $conexion->prepare(
        "SELECT o.codigo AS orden_codigo, o.fecha_orden, o.estado, o.medico,
                p.apellido, p.nombre, p.dni, p.fecha_nacimiento, p.telefono,
                os.nombre AS obra_social
         FROM ordenes o
         JOIN pacientes p ON p.codigo = o.paciente_codigo
         LEFT JOIN paciente_obra_social pos ON pos.paciente_codigo = p.codigo
         LEFT JOIN obras_sociales os ON os.codigo = pos.obra_social_codigo
         WHERE o.codigo = :codigo"
    );
    $stmt->execute(['codigo' => $ordenCodigo]);
    $orden = $stmt->fetch();

    if (!$orden) return null;

    // 2. Estudios de la orden
    $stmtEst = $conexion->prepare(
        "SELECT e.codigo AS estudio_codigo, e.estudio AS nombre_estudio, e.parametros
         FROM estudios_orden eo
         JOIN estudios e ON e.codigo = eo.estudio_codigo
         WHERE eo.orden_codigo = :codigo
         ORDER BY e.estudio"
    );
    $stmtEst->execute(['codigo' => $ordenCodigo]);
    $estudios = $stmtEst->fetchAll();

    // 3. Resultado + errores
    $stmtRes = $conexion->prepare('SELECT codigo, resultado, fecha_resultado FROM resultados WHERE orden_codigo = :codigo LIMIT 1');
    $stmtRes->execute(['codigo' => $ordenCodigo]);
    $resultado = $stmtRes->fetch();
    $resultadoCodigo = (int) ($resultado['codigo'] ?? 0);

    $errores = [];
    if ($resultadoCodigo > 0) {
        $stmtErr = $conexion->prepare('SELECT error_muestra FROM historial_errores WHERE resultado_codigo = :rid');
        $stmtErr->execute(['rid' => $resultadoCodigo]);
        $errores = $stmtErr->fetchAll(PDO::FETCH_COLUMN);
    }

    // Parsear valores
    $valores = [];
    if (!empty($resultado['resultado'])) {
        foreach (preg_split('/\R/', $resultado['resultado']) as $linea) {
            $partes = explode(':', $linea, 2);
            if (count($partes) === 2) {
                $valores[trim($partes[0])] = trim($partes[1]);
            }
        }
    }

    $edadTexto = '—';
    if (!empty($orden['fecha_nacimiento'])) {
        try { $edadTexto = (new DateTime())->diff(new DateTime($orden['fecha_nacimiento']))->y . ' años'; }
        catch (Throwable $e) {}
    }

    ob_start();
    ?>
    <style>
        body { font-family: Arial, sans-serif; color: #1f2937; font-size: 11px; }
        .titulo { text-align: center; color: #0b234a; font-size: 16px; font-weight: bold; margin: 0 0 4px; letter-spacing: 2px; }
        .subtitulo { text-align: center; color: #64748b; font-size: 10px; margin: 0 0 14px; }
        .paciente-box { width: 100%; border-collapse: collapse; margin-bottom: 14px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .paciente-box td { padding: 6px 10px; font-size: 10px; }
        .paciente-box td strong { color: #0b234a; }
        .estudio-header { background: #0b234a; color: #fff; padding: 8px 12px; margin-top: 12px; font-size: 12px; font-weight: bold; }
        .params-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .params-table th { background: #f1f5f9; color: #475569; font-size: 9px; padding: 6px 8px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .params-table td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; font-size: 10px; }
        .valor { font-weight: bold; color: #0b234a; }
        .rechazado { color: #991b1b; font-style: italic; }
        .texto-libre { background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; font-size: 10px; }
    </style>

    <div class="titulo">INFORME DE RESULTADOS</div>
    <div class="subtitulo">Orden #<?= htmlspecialchars(formatear_codigo_orden($orden['orden_codigo']), ENT_QUOTES, 'UTF-8') ?></div>

    <table class="paciente-box">
        <tr>
            <td style="width: 50%;"><strong>Paciente:</strong> <?= htmlspecialchars($orden['apellido'] . ', ' . $orden['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
            <td style="width: 25%;"><strong>DNI:</strong> <?= htmlspecialchars($orden['dni'], ENT_QUOTES, 'UTF-8') ?></td>
            <td style="width: 25%;"><strong>Edad:</strong> <?= htmlspecialchars($edadTexto, ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <td><strong>Obra social:</strong> <?= htmlspecialchars($orden['obra_social'] ?: 'Particular', ENT_QUOTES, 'UTF-8') ?></td>
            <td><strong>Fecha:</strong> <?= htmlspecialchars($orden['fecha_orden'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><strong>Médico:</strong> <?= htmlspecialchars($orden['medico'], ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
    </table>

    <?php foreach ($estudios as $est): ?>
        <?php
        $nombreEst = $est['nombre_estudio'];
        $valor = $valores[$nombreEst] ?? '—';
        $esRechazo = stripos($valor, 'RECHAZADO') !== false;
        $esPendiente = stripos($valor, 'PENDIENTE') !== false;
        ?>
        <div class="estudio-header">
            <?= htmlspecialchars($nombreEst, ENT_QUOTES, 'UTF-8') ?>
            <small>(<?= htmlspecialchars($est['estudio_codigo'], ENT_QUOTES, 'UTF-8') ?>)</small>
        </div>

        <?php if ($esRechazo): ?>
            <div class="texto-libre rechazado" style="margin-top: 6px;">
                Muestra rechazada. <?= htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php elseif ($esPendiente): ?>
            <div class="texto-libre" style="margin-top: 6px;">
                Muestra pendiente de validación.
            </div>
        <?php else: ?>
            <div class="texto-libre" style="margin-top: 6px;">
                <strong>Resultado:</strong> <?= htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') ?>
                <?php if (!empty($est['parametros'])): ?>
                    <br><small><strong>Referencia:</strong> <?= htmlspecialchars($est['parametros'], ENT_QUOTES, 'UTF-8') ?></small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php
    $html = ob_get_clean();

    try {
        $mpdf = crear_pdf('Informe - Orden ' . formatear_codigo_orden($orden['orden_codigo']));
        $mpdf->WriteHTML($html);
        return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Notifica la orden. Adjunta el PDF si corresponde a resultados listos.
 *
 * @param int  $ordenCodigo Código de la orden (NO id).
 * @param bool $forzar      Si true, envía aunque ya se haya enviado antes.
 * @param bool $modoEntrega Si true, fuerza el envío de "resultados listos".
 */
function notificar_orden(int $ordenCodigo, bool $forzar = false, bool $modoEntrega = false): array
{
    $conexion = conexion_bd();

    $stmt = $conexion->prepare(
        "SELECT o.codigo AS codigo_orden, p.nombre, p.apellido, p.correo
         FROM ordenes o
         JOIN pacientes p ON p.codigo = o.paciente_codigo
         WHERE o.codigo = :codigo"
    );
    $stmt->execute(['codigo' => $ordenCodigo]);
    $orden = $stmt->fetch();

    if (!$orden) {
        return ['enviados' => [], 'errores' => ['Orden no encontrada'], 'info' => []];
    }

    $destinatario = trim((string) $orden['correo']);
    if ($destinatario === '') {
        return ['enviados' => [], 'errores' => [], 'info' => ['El paciente no tiene email cargado, no se notificó.']];
    }

    // Traer estudios con su estado
    $stmtEst = $conexion->prepare(
        "SELECT e.codigo AS estudio_codigo, e.estudio AS nombre_estudio
         FROM estudios_orden eo
         JOIN estudios e ON e.codigo = eo.estudio_codigo
         WHERE eo.orden_codigo = :codigo
         ORDER BY e.estudio"
    );
    $stmtEst->execute(['codigo' => $ordenCodigo]);
    $estudios = $stmtEst->fetchAll();

    // Errores
    $stmtRes = $conexion->prepare('SELECT codigo FROM resultados WHERE orden_codigo = :codigo LIMIT 1');
    $stmtRes->execute(['codigo' => $ordenCodigo]);
    $resultadoCodigo = (int) ($stmtRes->fetchColumn() ?: 0);

    $errores = [];
    if ($resultadoCodigo > 0) {
        $stmtErr = $conexion->prepare('SELECT error_muestra FROM historial_errores WHERE resultado_codigo = :rid');
        $stmtErr->execute(['rid' => $resultadoCodigo]);
        $errores = $stmtErr->fetchAll(PDO::FETCH_COLUMN);
    }

    $estado = estado_global_orden($ordenCodigo);
    $enviados = [];
    $erroresArr = [];
    $info = [];
    $adjuntos = [];

    // Caso A: entrega forzada
    if ($modoEntrega) {
        if (!$forzar && email_ya_enviado($ordenCodigo, 'resultados_listos')) {
            $info[] = 'Ya se había enviado el aviso de "resultados listos" antes.';
        } else {
            $pdfBin = generar_pdf_orden_binario($ordenCodigo);
            if ($pdfBin) $adjuntos[] = ['contenido' => $pdfBin, 'nombre' => 'Resultado_' . formatear_codigo_orden($orden['codigo_orden']) . '.pdf'];

            $asunto = 'Tus resultados están listos - Laboratorio CEBAC';
            $cuerpo = cuerpo_resultados_listos($orden, $estudios);
            [$ok, $err] = enviar_email($destinatario, $asunto, $cuerpo, $adjuntos);
            registrar_envio($ordenCodigo, 'resultados_listos', $destinatario, $asunto, $cuerpo, $ok, $err);
            if ($ok) $enviados[] = 'resultados_listos';
            else $erroresArr[] = 'No se pudo enviar el email: ' . $err;
        }
        return ['enviados' => $enviados, 'errores' => $erroresArr, 'info' => $info];
    }

    // Caso B: rechazo
    $rechazados = [];
    foreach ($estudios as $est) {
        $nombreEst = $est['nombre_estudio'];
        foreach ($errores as $err) {
            if (stripos($err, $nombreEst) === false) continue;
            if (stripos($err, 'RECHAZADO') !== false) {
                $rechazados[] = [
                    'nombre_estudio' => $nombreEst,
                    'estudio_codigo' => $est['estudio_codigo'],
                    'motivo_rechazo' => preg_replace('/.*RECHAZADO[:\s-]*/i', '', $err),
                ];
            }
        }
    }

    if (!empty($rechazados)) {
        if (!$forzar && email_ya_enviado($ordenCodigo, 'rechazo')) {
            $info[] = 'Ya se había enviado el aviso de "rechazo" antes.';
        } else {
            $asunto = 'Necesitamos que vuelvas al laboratorio - CEBAC';
            $cuerpo = cuerpo_rechazo($orden, $rechazados);
            [$ok, $err] = enviar_email($destinatario, $asunto, $cuerpo);
            registrar_envio($ordenCodigo, 'rechazo', $destinatario, $asunto, $cuerpo, $ok, $err);
            if ($ok) $enviados[] = 'rechazo';
            else $erroresArr[] = 'No se pudo enviar el email de rechazo: ' . $err;
        }
    }

    // Caso C: resultados listos (todos finalizados)
    if ($estado === 'listo') {
        if (!$forzar && email_ya_enviado($ordenCodigo, 'resultados_listos')) {
            $info[] = 'Ya se había enviado el aviso de "resultados listos" antes.';
        } else {
            $pdfBin = generar_pdf_orden_binario($ordenCodigo);
            if ($pdfBin) $adjuntos[] = ['contenido' => $pdfBin, 'nombre' => 'Resultado_' . formatear_codigo_orden($orden['codigo_orden']) . '.pdf'];

            $asunto = 'Tus resultados están listos - Laboratorio CEBAC';
            $cuerpo = cuerpo_resultados_listos($orden, $estudios);
            [$ok, $err] = enviar_email($destinatario, $asunto, $cuerpo, $adjuntos);
            registrar_envio($ordenCodigo, 'resultados_listos', $destinatario, $asunto, $cuerpo, $ok, $err);
            if ($ok) $enviados[] = 'resultados_listos';
            else $erroresArr[] = 'No se pudo enviar el email: ' . $err;
        }
    }

    return ['enviados' => $enviados, 'errores' => $erroresArr, 'info' => $info];
}