<?php
session_start();

// Verificar que esté logueado
if (!isset($_SESSION['id_docente'])) {
    header('Location: login.php');
    exit();
}

// Si no es primer acceso, redirigir al inicio
if ($_SESSION['primer_acceso'] != 1) {
    $rol = $_SESSION['rol'] ?? 'docente';
    switch ($rol) {
        case 'administrador':
            header('Location: index.php');
            break;
        case 'coordinador':
            header('Location: indexcordinador.php');
            break;
        default:
            header('Location: indexusuario.php');
    }
    exit();
}

$error = '';
$nombre = $_SESSION['nombre_completo'] ?? 'Usuario';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'php/conexion.php';
    
    $password_actual = $_POST['password_actual'] ?? '';
    $password_nueva = $_POST['password_nueva'] ?? '';
    $password_confirmar = $_POST['password_confirmar'] ?? '';
    
    // Validaciones
    if (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
        $error = 'Todos los campos son obligatorios';
    } elseif (strlen($password_nueva) < 6) {
        $error = 'La nueva contraseña debe tener al menos 6 caracteres';
    } elseif ($password_nueva !== $password_confirmar) {
        $error = 'Las contraseñas nuevas no coinciden';
    } elseif ($password_actual === $password_nueva) {
        $error = 'La nueva contraseña debe ser diferente a la actual';
    } else {
        // Verificar contraseña actual
        $sql = "SELECT password FROM docente WHERE id_docente = ?";
        $stmt = mysqli_prepare($cnx, $sql);
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['id_docente']);
        mysqli_stmt_execute($stmt);
        $resultado = mysqli_stmt_get_result($stmt);
        $docente = mysqli_fetch_assoc($resultado);
        mysqli_stmt_close($stmt);
        
        $password_valido = password_verify($password_actual, $docente['password']);
        
        // También verificar si es la cédula (caso de migración)
        if (!$password_valido && $password_actual === $_SESSION['cedula']) {
            $password_valido = true;
        }
        
        if (!$password_valido) {
            $error = 'La contraseña actual es incorrecta';
        } else {
            // Actualizar contraseña
            $nuevo_hash = password_hash($password_nueva, PASSWORD_BCRYPT);
            $update = "UPDATE docente SET password = ?, primer_acceso = 0 WHERE id_docente = ?";
            $stmt = mysqli_prepare($cnx, $update);
            mysqli_stmt_bind_param($stmt, "si", $nuevo_hash, $_SESSION['id_docente']);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['primer_acceso'] = 0;
                mysqli_stmt_close($stmt);
                
                // Redirigir al inicio según rol
                $rol = $_SESSION['rol'] ?? 'docente';
                switch ($rol) {
                    case 'administrador':
                        header('Location: index.php?bienvenido=1');
                        break;
                    case 'coordinador':
                        header('Location: indexcordinador.php?bienvenido=1');
                        break;
                    default:
                        header('Location: indexusuario.php?bienvenido=1');
                }
                exit();
            } else {
                $error = 'Error al actualizar la contraseña';
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña - Sistema PEA</title>
    <link rel="stylesheet" href="css/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .container {
            max-width: 500px;
        }
        .left-panel {
            display: none;
        }
        .right-panel {
            flex: 1;
            border-radius: 15px;
        }
        .warning-box {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
        }
        .warning-box h3 {
            color: #92400e;
            font-size: 1rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .warning-box p {
            color: #a16207;
            font-size: 0.8rem;
            margin: 0;
        }
        .password-requirements {
            background: #f3f4f6;
            border-radius: 8px;
            padding: 12px;
            margin-top: 15px;
            font-size: 0.85rem;
            color: #6b7280;
        }
        .password-requirements ul {
            margin: 8px 0 0 20px;
        }
        .password-requirements li {
            margin: 4px 0;
        }
        .user-info {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            background: #e0f2fe;
            border-radius: 10px;
        }
        .user-info p {
            margin: 0;
            color: #0369a1;
        }
        .user-info strong {
            font-size: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="right-panel">
            <div class="login-box" style="max-width: 400px;">
                <h2>Cambiar Contraseña</h2>
                
                <div class="warning-box">
                    <h3>⚠️ Acción Requerida</h3>
                    <p>Por seguridad, debe cambiar su contraseña antes de continuar.</p>
                </div>
                
                <div class="user-info">
                    <p>Bienvenido/a</p>
                    <p><strong><?php echo htmlspecialchars($nombre); ?></strong></p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <span>⚠️</span> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="cambiarPasswordForm">
                    <div class="input-group">
                        <label for="password_actual">Contraseña Actual</label>
                        <div class="password-wrapper">
                            <input 
                                type="password" 
                                id="password_actual" 
                                name="password_actual" 
                                placeholder="Su contraseña actual (cédula)"
                                required
                            >
                            <button type="button" class="toggle-password" onclick="togglePassword('password_actual')">👁️</button>
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <label for="password_nueva">Nueva Contraseña</label>
                        <div class="password-wrapper">
                            <input 
                                type="password" 
                                id="password_nueva" 
                                name="password_nueva" 
                                placeholder="Ingrese nueva contraseña"
                                minlength="6"
                                required
                            >
                            <button type="button" class="toggle-password" onclick="togglePassword('password_nueva')">👁️</button>
                        </div>
                        
                    </div>
                    
                    <div class="input-group">
                        <label for="password_confirmar">Confirmar Nueva Contraseña</label>
                        <div class="password-wrapper">
                            <input 
                                type="password" 
                                id="password_confirmar" 
                                name="password_confirmar" 
                                placeholder="Repita la nueva contraseña"
                                minlength="6"
                                required
                            >
                            <button type="button" class="toggle-password" onclick="togglePassword('password_confirmar')">👁️</button>
                        </div>
                    </div>
                    
                    <div class="password-requirements">
                        <strong>Requisitos de la contraseña:</strong>
                        <ul>
                            <li>Mínimo 6 caracteres</li>
                            <li>Diferente a su cédula</li>
                            <li>No compartir con nadie</li>
                        </ul>
                    </div>
                    
                    <button type="submit" class="btn-login" style="margin-top: 25px;">
                        Guardar Nueva Contraseña
                    </button>
                </form>
                
                <div class="links" style="margin-top: 20px;">
                    <a href="php/configuracion/cerrarsecion.php">Cerrar sesión</a>
                </div>
            </div>
            
            <footer>
                <p>© 2025 Instituto Superior Tecnológico Tena</p>
            </footer>
        </div>
    </div>
    
    <script>
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const btn = input.nextElementSibling;
        if (input.type === 'password') {
            input.type = 'text';
            btn.textContent = '🔒';
        } else {
            input.type = 'password';
            btn.textContent = '👁️';
        }
    }

    document.getElementById('cambiarPasswordForm').addEventListener('submit', function(e) {
        const nueva = document.getElementById('password_nueva').value;
        const confirmar = document.getElementById('password_confirmar').value;
        
        if (nueva !== confirmar) {
            e.preventDefault();
            alert('Las contraseñas nuevas no coinciden');
        }
    });
</script>
</body>
</html>
