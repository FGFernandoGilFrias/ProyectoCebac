<?php
/**
 * includes/email.php
 * Envío de emails vía PHPMailer + Gmail SMTP (con soporte para adjuntos).
 */

require_once __DIR__ . '/pdf_helper.php';
require_once __DIR__ . '/../lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Envía un email.
 *
 * @param string $destinatario  Email del destinatario
 * @param string $asunto        Asunto
 * @param string $cuerpo        Cuerpo del mensaje (texto plano)
 * @param array  $adjuntos      Array de adjuntos: [['contenido' => 'binary', 'nombre' => 'archivo.pdf'], ...]
 * @return array [true, null] si OK, o [false, mensaje_error] si falla
 */
function enviar_email(string $destinatario, string $asunto, string $cuerpo, array $adjuntos = []): array
{
    $config = require __DIR__ . '/config_lab.php';
    $smtp = $config['smtp'];

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $smtp['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp['usuario'];
        $mail->Password   = $smtp['contrasena'];
        $mail->SMTPSecure = $smtp['seguridad'];
        $mail->Port       = $smtp['puerto'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($smtp['remitente'], $smtp['nombre_remitente']);
        $mail->addAddress($destinatario);

        $mail->isHTML(false);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpo;

        // Adjuntos
        foreach ($adjuntos as $adj) {
            if (!empty($adj['contenido']) && !empty($adj['nombre'])) {
                $mail->addStringAttachment($adj['contenido'], $adj['nombre']);
            }
        }

        $mail->send();
        return [true, null];
    } catch (Exception $e) {
        return [false, $mail->ErrorInfo ?: $e->getMessage()];
    }
}