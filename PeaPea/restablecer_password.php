<?php
session_start();

$error = '';
$exito = '';
$token_valido = false;
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (empty($token)) {
    header('Location: login.php');
    exit();
}

require_once 'php/conexion.php';

// Verificar token
$sql = "SELECT tr.id, tr.id_docente, tr.expira, d.nombres, d.apellidos 
        FROM tokens_recuperacion tr 
        JOIN docente d ON tr.id_docente = d.id_docente 
        WHERE tr.token = ? AND tr.usado = 0 AND tr.expira > NOW()";
$stmt = mysqli_prepare($cnx, $sql);
mysqli_stmt_bind_param($stmt, "s", $token);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

if ($datos = mysqli_fetch_assoc($resultado)) {
    $token_valido = true;
    $nombre = $datos['nombres'] . ' ' . $datos['apellidos'];
    $id_docente = $datos['id_docente'];
    $id_token = $datos['id'];
} else {
    $error = 'El enlace ha expirado o ya fue utilizado. Solicite uno nuevo.';
}
mysqli_stmt_close($stmt);

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valido) {
    $password_nueva = $_POST['password_nueva'] ?? '';
    $password_confirmar = $_POST['password_confirmar'] ?? '';
    
    if (strlen($password_nueva) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif ($password_nueva !== $password_confirmar) {
        $error = 'Las contraseñas no coinciden';
    } else {
        // Actualizar contraseña
        $nuevo_hash = password_hash($password_nueva, PASSWORD_BCRYPT);
        $update = "UPDATE docente SET password = ?, primer_acceso = 0 WHERE id_docente = ?";
        $stmt = mysqli_prepare($cnx, $update);
        mysqli_stmt_bind_param($stmt, "si", $nuevo_hash, $id_docente);
        
        if (mysqli_stmt_execute($stmt)) {
            // Marcar token como usado
            $marcar = "UPDATE tokens_recuperacion SET usado = 1 WHERE id = ?";
            $stmt_marcar = mysqli_prepare($cnx, $marcar);
            mysqli_stmt_bind_param($stmt_marcar, "i", $id_token);
            mysqli_stmt_execute($stmt_marcar);
            mysqli_stmt_close($stmt_marcar);
            
            header('Location: login.php?exito=1');
            exit();
        } else {
            $error = 'Error al actualizar la contraseña';
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña - Sistema PEA</title>
    <link rel="stylesheet" href="css/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .container { max-width: 500px; }
        .left-panel { display: none; }
        .right-panel { flex: 1; border-radius: 20px; }
        .user-info {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            background: #e0f2fe;
            border-radius: 10px;
        }
        .user-info p { margin: 0; color: #0369a1; }
        .user-info strong { font-size: 1.1rem; }
        .password-requirements {
            background: #f3f4f6;
            border-radius: 8px;
            padding: 12px;
            margin-top: 15px;
            font-size: 0.85rem;
            color: #6b7280;
        }
        .password-requirements ul { margin: 8px 0 0 20px; }
        .password-requirements li { margin: 4px 0; }
        .expired-box {
            text-align: center;
            padding: 30px;
        }
        .expired-box .icon { font-size: 4rem; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="right-panel">
            <div class="login-box" style="max-width: 400px;">
                
                <?php if (!$token_valido): ?>
                    <div class="expired-box">
                        <div class="icon">⏰</div>
                        <h2>Enlace Expirado</h2>
                        <p style="color: #6b7280; margin: 20px 0;"><?php echo htmlspecialchars($error); ?></p>
                        <a href="recuperar_password.php" class="btn-login" style="display: inline-block; text-decoration: none;">
                            Solicitar Nuevo Enlace
                        </a>
                    </div>
                <?php else: ?>
                
                <h2>🔐 Nueva Contraseña</h2>
                <p class="subtitle">Ingrese su nueva contraseña</p>
                
                <div class="user-info">
                    <p>Restableciendo acceso para</p>
                    <p><strong><?php echo htmlspecialchars($nombre); ?></strong></p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <span>⚠️</span> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="input-group">
                        <label for="password_nueva">Nueva Contraseña</label>
                        <input 
                            type="password" 
                            id="password_nueva" 
                            name="password_nueva" 
                            placeholder="Mínimo 6 caracteres"
                            minlength="6"
                            required
                        >
                    </div>
                    
                    <div class="input-group">
                        <label for="password_confirmar">Confirmar Contraseña</label>
                        <input 
                            type="password" 
                            id="password_confirmar" 
                            name="password_confirmar" 
                            placeholder="Repita la contraseña"
                            minlength="6"
                            required
                        >
                    </div>
                    
                    <div class="password-requirements">
                        <strong>Requisitos:</strong>
                        <ul>
                            <li>Mínimo 6 caracteres</li>
                            <li>Diferente a su cédula</li>
                        </ul>
                    </div>
                    
                    <button type="submit" class="btn-login" style="margin-top: 25px;">
                        Guardar Nueva Contraseña
                    </button>
                </form>
                
                <?php endif; ?>
                
            </div>
            
            <footer>
                <p>© 2025 Instituto Superior Tecnológico Tena</p>
            </footer>
        </div>
    </div>
</body>
</html>
