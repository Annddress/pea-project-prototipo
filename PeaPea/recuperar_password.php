<?php
session_start();

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'php/conexion.php';
    
    $cedula = trim($_POST['cedula'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (!preg_match('/^[0-9]{10}$/', $cedula)) {
        $error = 'La cédula debe tener 10 dígitos';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingrese un correo electrónico válido';
    } else {
        $sql = "SELECT id_docente, nombres, apellidos, email FROM docente WHERE cedula = ?";
        $stmt = mysqli_prepare($cnx, $sql);
        mysqli_stmt_bind_param($stmt, "s", $cedula);
        mysqli_stmt_execute($stmt);
        $resultado = mysqli_stmt_get_result($stmt);
        
        if ($docente = mysqli_fetch_assoc($resultado)) {
            // Actualizar email si no tiene
            if (empty($docente['email'])) {
                $update_email = "UPDATE docente SET email = ? WHERE id_docente = ?";
                $stmt_email = mysqli_prepare($cnx, $update_email);
                mysqli_stmt_bind_param($stmt_email, "si", $email, $docente['id_docente']);
                mysqli_stmt_execute($stmt_email);
                mysqli_stmt_close($stmt_email);
            }
            
            // Generar token
            $token = bin2hex(random_bytes(32));
            $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Invalidar tokens anteriores
            $invalidar = "UPDATE tokens_recuperacion SET usado = 1 WHERE id_docente = ?";
            $stmt_inv = mysqli_prepare($cnx, $invalidar);
            mysqli_stmt_bind_param($stmt_inv, "i", $docente['id_docente']);
            mysqli_stmt_execute($stmt_inv);
            mysqli_stmt_close($stmt_inv);
            
            // Guardar nuevo token
            $insert = "INSERT INTO tokens_recuperacion (id_docente, token, expira) VALUES (?, ?, ?)";
            $stmt_token = mysqli_prepare($cnx, $insert);
            mysqli_stmt_bind_param($stmt_token, "iss", $docente['id_docente'], $token, $expira);
            mysqli_stmt_execute($stmt_token);
            mysqli_stmt_close($stmt_token);
            
            // Enviar correo
            $enviado = enviarCorreoRecuperacion($email, $docente['nombres'], $token);
            
            if ($enviado) {
                $exito = 'Se ha enviado un enlace de recuperación a su correo. Revise su bandeja de entrada y spam.';
            } else {
                $exito = 'Token generado. Contacte al administrador si no recibe el correo. Token: ' . substr($token, 0, 8) . '...';
            }
        } else {
            // No revelar si el usuario existe o no (seguridad)
            $exito = 'Si la cédula está registrada, recibirá un correo con instrucciones.';
        }
        mysqli_stmt_close($stmt);
    }
}

function enviarCorreoRecuperacion($email, $nombre, $token) {
    // Por ahora retornamos false hasta configurar SMTP
    // En producción, usar PHPMailer o similar
    
    $url_base = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
    $url_base .= dirname($_SERVER['PHP_SELF']);
    $enlace = $url_base . "/restablecer_password.php?token=" . $token;
    
    $asunto = "Recuperación de contraseña - Sistema PEA";
    $mensaje = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h2>Recuperación de Contraseña</h2>
        <p>Hola <strong>$nombre</strong>,</p>
        <p>Has solicitado restablecer tu contraseña del Sistema PEA del Instituto Superior Tecnológico Tena.</p>
        <p>Haz clic en el siguiente enlace para crear una nueva contraseña:</p>
        <p><a href='$enlace' style='background: #0081c9; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Restablecer Contraseña</a></p>
        <p>Este enlace expirará en 1 hora.</p>
        <p>Si no solicitaste este cambio, ignora este correo.</p>
        <hr>
        <p style='color: #666; font-size: 12px;'>Sistema PEA - Instituto Superior Tecnológico Tena</p>
    </body>
    </html>
    ";
    
    // Intentar enviar con mail() nativo (puede no funcionar en todos los servidores)
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Sistema PEA <noreply@itstena.edu.ec>\r\n";
    
    // Descomentar cuando el servidor de correo esté configurado
    // return mail($email, $asunto, $mensaje, $headers);
    
    return false; // Por ahora retornamos false
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - Sistema PEA</title>
    <link rel="stylesheet" href="css/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .container { max-width: 500px; }
        .left-panel { display: none; }
        .right-panel { flex: 1; border-radius: 20px; }
        .back-link { 
            display: inline-flex; 
            align-items: center; 
            gap: 5px; 
            color: #6b7280; 
            text-decoration: none; 
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .back-link:hover { color: #0081c9; }
        .info-box {
            background: #e0f2fe;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
        }
        .info-box p {
            color: #0369a1;
            font-size: 0.9rem;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="right-panel">
            <div class="login-box" style="max-width: 400px;">
                <a href="login.php" class="back-link">← Volver al inicio de sesión</a>
                
                <h2>🔑 Recuperar Contraseña</h2>
                <p class="subtitle">Ingrese sus datos para recuperar el acceso</p>
                
                <div class="info-box">
                    <p>Ingrese su cédula y correo electrónico. Le enviaremos un enlace para restablecer su contraseña.</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <span>⚠️</span> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($exito): ?>
                    <div class="alert alert-success">
                        <span>✓</span> <?php echo htmlspecialchars($exito); ?>
                    </div>
                <?php else: ?>
                
                <form method="POST">
                    <div class="input-group">
                        <label for="cedula">Cédula</label>
                        <input 
                            type="text" 
                            id="cedula" 
                            name="cedula" 
                            placeholder="Ingrese su cédula"
                            maxlength="10"
                            pattern="[0-9]{10}"
                            required
                            value="<?php echo htmlspecialchars($_POST['cedula'] ?? ''); ?>"
                        >
                    </div>
                    
                    <div class="input-group">
                        <label for="email">Correo Electrónico</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            placeholder="ejemplo@gmail.com"
                            required
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        >
                    </div>
                    
                    <button type="submit" class="btn-login">
                        Enviar Enlace de Recuperación
                    </button>
                </form>
                
                <?php endif; ?>
                
            </div>
            
            <footer>
                <p>© 2025 Instituto Superior Tecnológico Tena</p>
            </footer>
        </div>
    </div>
    
    <script>
        document.getElementById('cedula')?.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>
