<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../phpmailer/Exception.php';
require __DIR__ . '/../phpmailer/PHPMailer.php';
require __DIR__ . '/../phpmailer/SMTP.php';

// ========== FUNCIÓN 1: Correos de verificación ==========
function enviarCorreoVerificacion($email, $nombre, $token) {
    $verification_link = "https://ciclodevida.mx/verify.php?token=" . urlencode($token);
    
    $html = generarTemplateEmail(
        titulo: '🌱 Verifica tu cuenta',
        colorHeader: '#1b4332',
        nombreUsuario: $nombre,
        contenidoPrincipal: "
            <p>Gracias por registrarte en el sistema de <strong>MexILCA</strong>.</p>
            <p>Para completar tu registro y acceder a todas las funcionalidades, por favor <strong>verifica tu dirección de correo electrónico</strong> haciendo clic en el siguiente botón:</p>
            <center>
                <a href='{$verification_link}' class='button'>✅ Verificar mi cuenta</a>
            </center>
            <p>Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
            <div class='code-box'>{$verification_link}</div>
            <div class='warning'>
                <strong>⏰ Importante:</strong> Este enlace expirará en <strong>24 horas</strong>.
            </div>
            <p>Si no creaste esta cuenta, puedes ignorar este mensaje de forma segura.</p>
        ",
        textoFooter: 'Este es un correo automático, por favor no respondas.'
    );
    
    return enviarCorreo(
        email: $email,
        nombre: $nombre,
        asunto: 'Verifica tu cuenta - MexILCA',
        contenido: $html,
        remitente: 'avisos@ciclodevida.mx',
        remitenteNombre: 'Ciclo de Vida UNAM',
        replyTo: 'avisos@ciclodevida.mx',
        password: 'Matematicas#123'
    );
}

// ========== FUNCIÓN 2: Notificaciones administrativas ==========
function enviarNotificacionAdmin($email, $nombre, $accion, $comentario = '') {
    if ($accion === 'aprobado') {
        $asunto = 'Solicitud de Administrador Aprobada';
        $color = '#16a34a';
        $icono = '✅';
        $titulo = '¡Solicitud Aprobada!';
        
        $contenido = "
            <p>Tu solicitud para ser <strong>administrador</strong> ha sido <strong>aprobada exitosamente</strong>.</p>
            <div class='info-box success'>
                <strong>¡Felicidades!</strong> Ahora eres un administrador de MexILCA.
            </div>
            <p><strong>Próximos pasos:</strong></p>
            <ol>
                <li>Cierra sesión en el sistema</li>
                <li>Vuelve a iniciar sesión</li>
                <li>Verás el nuevo menú de Administración disponible</li>
            </ol>";
        
        if (!empty($comentario)) {
            $contenido .= "
            <div class='comment-box'>
                <strong>📝 Comentario del revisor:</strong><br>
                " . nl2br(htmlspecialchars($comentario)) . "
            </div>";
        }
        
        $contenido .= "
            <p>Si tienes alguna pregunta, <strong>puedes responder a este correo</strong>.</p>";
        
    } else {
        $asunto = 'Solicitud de Administrador Rechazada';
        $color = '#dc2626';
        $icono = '❌';
        $titulo = 'Solicitud Rechazada';
        
        $contenido = "
            <p>Lamentamos informarte que tu solicitud para ser <strong>administrador</strong> ha sido <strong>rechazada</strong>.</p>";
        
        if (!empty($comentario)) {
            $contenido .= "
            <div class='info-box error'>
                <strong>📋 Motivo del rechazo:</strong><br>
                " . nl2br(htmlspecialchars($comentario)) . "
            </div>";
        }
        
        $contenido .= "
            <p>Si deseas más información o crees que se trata de un error, <strong>puedes responder a este correo</strong> y el equipo de administración te atenderá.</p>
            <p>Puedes continuar usando el sistema con tu cuenta actual.</p>";
    }
    
    $html = generarTemplateEmail(
        titulo: "$icono $titulo",
        colorHeader: $color,
        nombreUsuario: $nombre,
        contenidoPrincipal: $contenido,
        textoFooter: 'Puedes responder a este correo si tienes dudas.'
    );
    
    return enviarCorreo(
        email: $email,
        nombre: $nombre,
        asunto: $asunto,
        contenido: $html,
        remitente: 'admin@ciclodevida.mx',
        remitenteNombre: 'Admin - MexILCA',
        replyTo: 'admin@ciclodevida.mx',
        password: 'Matematicas#123'
    );
}

// ========== FUNCIÓN 3: Notificaciones de datasets ==========
function enviarNotificacionDataset($email, $nombre, $nombreDataset, $accion, $motivo = '') {
    if ($accion === 'aprobado') {
        $asunto = 'Dataset Aprobado - ' . $nombreDataset;
        $color = '#16a34a';
        $icono = '✅';
        $titulo = '¡Dataset Aprobado!';
        
        $contenido = "
            <p>¡Excelentes noticias! Tu dataset <strong>{$nombreDataset}</strong> ha sido <strong>aprobado</strong> y ya está disponible en MexILCA.</p>
            <div class='info-box success'>
                <strong>🎉 ¡Felicidades!</strong> Tu contribución ahora está disponible para toda la comunidad.
            </div>
            <p><strong>¿Qué significa esto?</strong></p>
            <ul>
                <li>✅ Tu dataset es visible en la base de datos</li>
                <li>✅ Otros usuarios pueden consultarlo y utilizarlo</li>
                <li>✅ Aparece en las búsquedas del sistema</li>
            </ul>
            <p>Puedes verlo en: <a href='https://ciclodevida.mx/conjuntos.php' style='color: #16a34a; text-decoration: underline;'>https://ciclodevida.mx/conjuntos.php</a></p>
            <p><strong>Gracias por tu contribución</strong> al proyecto MexILCA.</p>";
        
    } else {
        $asunto = 'Dataset Rechazado - ' . $nombreDataset;
        $color = '#dc2626';
        $icono = '❌';
        $titulo = 'Dataset Rechazado';
        
        $contenido = "
            <p>Tu dataset <strong>{$nombreDataset}</strong> ha sido <strong>rechazado</strong> y no estara disponible en MexILCA.</p>";
        
        if (!empty($motivo)) {
            $contenido .= "
            <div class='info-box error'>
                <strong>📋 Motivo del rechazo:</strong><br>
                " . nl2br(htmlspecialchars($motivo)) . "
            </div>";
        }
        
        $contenido .= "
            <p><strong>¿Qué puedes hacer ahora?</strong></p>
            <ol>
                <li>Revisa cuidadosamente las observaciones anteriores</li>
                <li>Realiza todas las correcciones necesarias</li>
                <li>Crea un nuevo dataset con los cambios aplicados</li>
            </ol>
            <p>Si tienes dudas sobre las correcciones solicitadas, <strong>puedes responder a este correo</strong> y el equipo de revisión te ayudará.</p>";
    }
    
    $html = generarTemplateEmail(
        titulo: "$icono $titulo",
        colorHeader: $color,
        nombreUsuario: $nombre,
        contenidoPrincipal: $contenido,
        textoFooter: 'Puedes responder a este correo si tienes preguntas.'
    );
    
    return enviarCorreo(
        email: $email,
        nombre: $nombre,
        asunto: $asunto,
        contenido: $html,
        remitente: 'admin@ciclodevida.mx',
        remitenteNombre: 'Datasets - Ciclo de Vida UNAM',
        replyTo: 'admin@ciclodevida.mx',
        password: 'Matematicas#123'
    );
}

// ========== FUNCIÓN GENÉRICA: Template HTML profesional ==========
function generarTemplateEmail($titulo, $colorHeader, $nombreUsuario, $contenidoPrincipal, $textoFooter) {
    return "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif; 
                background-color: #f4f4f4; 
                padding: 20px;
                line-height: 1.6;
            }
            .email-container { 
                max-width: 600px; 
                margin: 20px auto; 
                background-color: #ffffff; 
                border-radius: 12px; 
                overflow: hidden; 
                box-shadow: 0 4px 12px rgba(0,0,0,0.15); 
            }
            .header { 
                background: linear-gradient(135deg, {$colorHeader} 0%, " . ajustarColor($colorHeader, -20) . " 100%);
                color: white; 
                padding: 40px 30px; 
                text-align: center; 
            }
            .header h1 { 
                margin: 0; 
                font-size: 28px; 
                font-weight: 700;
                text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }
            .content { 
                padding: 40px 30px; 
                color: #333333; 
            }
            .content h2 { 
                color: {$colorHeader}; 
                margin-top: 0;
                margin-bottom: 15px;
                font-size: 22px;
            }
            .content p { 
                margin-bottom: 15px;
            }
            .content ul, .content ol {
                margin-left: 20px;
                margin-bottom: 15px;
            }
            .content li {
                margin-bottom: 8px;
            }
            .button { 
                display: inline-block; 
                background: linear-gradient(135deg, #2d6a4f 0%, #1b4332 100%);
                color: white !important; 
                padding: 16px 40px; 
                text-decoration: none; 
                border-radius: 8px; 
                font-weight: 700; 
                font-size: 16px;
                margin: 20px 0;
                box-shadow: 0 4px 8px rgba(45, 106, 79, 0.3);
                transition: all 0.3s ease;
            }
            .button:hover { 
                box-shadow: 0 6px 12px rgba(45, 106, 79, 0.4);
                transform: translateY(-2px);
            }
            .code-box { 
                background-color: #f8f9fa; 
                padding: 15px; 
                border-radius: 6px; 
                word-break: break-all; 
                font-family: 'Courier New', monospace; 
                font-size: 13px; 
                border-left: 4px solid {$colorHeader};
                margin: 15px 0;
                color: #495057;
            }
            .warning { 
                background-color: #fff3cd; 
                border-left: 4px solid #ffc107; 
                padding: 15px; 
                margin: 20px 0;
                border-radius: 6px;
            }
            .info-box {
                padding: 15px;
                border-radius: 6px;
                margin: 20px 0;
                border-left: 4px solid;
            }
            .info-box.success {
                background-color: #d1f2eb;
                border-color: #16a34a;
                color: #0f5132;
            }
            .info-box.error {
                background-color: #f8d7da;
                border-color: #dc2626;
                color: #721c24;
            }
            .comment-box {
                background-color: #e7f3ff;
                border-left: 4px solid #0066cc;
                padding: 15px;
                margin: 20px 0;
                border-radius: 6px;
                color: #004085;
            }
            .footer { 
                background-color: #f8f9fa; 
                text-align: center; 
                padding: 30px 20px; 
                color: #6c757d; 
                font-size: 13px; 
                border-top: 1px solid #dee2e6; 
            }
            .footer strong {
                color: #495057;
                display: block;
                margin-bottom: 5px;
            }
            .footer a {
                color: {$colorHeader};
                text-decoration: none;
            }
            @media only screen and (max-width: 600px) {
                .content { padding: 25px 20px; }
                .header { padding: 30px 20px; }
                .header h1 { font-size: 24px; }
                .button { padding: 14px 30px; font-size: 15px; }
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <h1>{$titulo}</h1>
            </div>
            <div class='content'>
                <h2>¡Hola, {$nombreUsuario}!</h2>
                {$contenidoPrincipal}
            </div>
            <div class='footer'>
                <strong>MexILCA - UNAM</strong>
                <p>© " . date('Y') . " Universidad Nacional Autónoma de México</p>
                <p>{$textoFooter}</p>
            </div>
        </div>
    </body>
    </html>";
}

// ========== FUNCIÓN AUXILIAR: Ajustar color para gradientes ==========
function ajustarColor($hex, $porcentaje) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    $r = max(0, min(255, $r + $porcentaje));
    $g = max(0, min(255, $g + $porcentaje));
    $b = max(0, min(255, $b + $porcentaje));
    
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

// ========== FUNCIÓN GENÉRICA DE ENVÍO (Interna) ==========
function enviarCorreo($email, $nombre, $asunto, $contenido, $remitente, $remitenteNombre, $replyTo, $password) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = $remitente;
        $mail->Password = $password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        
        $mail->setFrom($remitente, $remitenteNombre);
        $mail->addAddress($email, $nombre);
        $mail->addReplyTo($replyTo, $remitenteNombre);
        
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body = $contenido;
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Error PHPMailer ({$remitente}): {$mail->ErrorInfo}");
        return false;
    }
}
?>
