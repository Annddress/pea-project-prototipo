<?php
session_start();

// Si ya está logueado, redirigir
if (isset($_SESSION['id_docente'])) {
    $rol = $_SESSION['rol'] ?? 'docente';
    if ($_SESSION['primer_acceso'] == 1) {
        header('Location: cambiar_password.php');
    } else {
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
    }
    exit();
}

$error = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case '1':
            $error = 'Cédula o contraseña incorrectos';
            break;
        case '2':
            $error = 'Por favor ingrese sus credenciales';
            break;
        case '3':
            $error = 'Su sesión ha expirado';
            break;
    }
}

$exito = '';
if (isset($_GET['exito'])) {
    switch ($_GET['exito']) {
        case '1':
            $exito = 'Contraseña cambiada exitosamente. Inicie sesión.';
            break;
        case '2':
            $exito = 'Se ha enviado un enlace de recuperación a su correo.';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema PEA | ISTTena</title>
    <link rel="stylesheet" href="css/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <div class="logo-section">
                <img src="imagenes/itstena.png" alt="Logo ISTTena">
                <h1>INSTITUTO SUPERIOR<br>TECNOLÓGICO TENA</h1>
                <p class="lema">Tecnología, Innovación, Desarrollo</p>
            </div>
            <div class="info-section">
                <h2>Sistema PEA</h2>
                <p>Plataforma para la elaboración y gestión de Programas de Estudio de Asignatura</p>
                <ul>
                    <li>✓ Gestión de mallas curriculares</li>
                    <li>✓ Asignación de materias por periodo</li>
                    <li>✓ Generación automática de PEAs</li>
                    <li>✓ Exportación a formato Word</li>
                </ul>
            </div>
        </div>
        
        <div class="right-panel">
            <div class="login-box">
                <h2>Iniciar Sesión</h2>
                <p class="subtitle">Ingrese con su cédula de identidad</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <span>⚠️</span> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($exito): ?>
                    <div class="alert alert-success">
                        <span>✓</span> <?php echo htmlspecialchars($exito); ?>
                    </div>
                <?php endif; ?>
                
                <form action="php/auth/acceder.php" method="POST" id="loginForm">
                    <div class="input-group">
                        <label for="cedula">Cédula</label>
                        <input 
                            type="text" 
                            id="cedula" 
                            name="cedula" 
                            placeholder="Ingrese su cédula" 
                            maxlength="10" 
                            pattern="[0-9]{10}"
                            title="La cédula debe tener 10 dígitos"
                            required
                            autocomplete="username"
                        >
                    </div>
                    
                    <div class="input-group">
                        <label for="password">Contraseña</label>
                        <div class="password-wrapper">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                placeholder="Ingrese su contraseña" 
                                required
                                autocomplete="current-password"
                            >
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                👁️
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-login">
                        Acceder
                    </button>
                    
                    <div class="links">
                        <a href="recuperar_password.php">¿Olvidó su contraseña?</a>
                    </div>
                </form>
                
                <div class="first-time">
                    <p><strong>¿Primera vez?</strong></p>
                    <p>Use su cédula como usuario y contraseña</p>
                </div>
            </div>
            
            <footer>
                <p>© 2025 Instituto Superior Tecnológico Tena</p>
            </footer>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const btn = document.querySelector('.toggle-password');
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = '🔒';
            } else {
                input.type = 'password';
                btn.textContent = '👁️';
            }
        }
        
        // Validar cédula en tiempo real
        document.getElementById('cedula').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>
