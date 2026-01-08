<?php
session_start();

if (!isset($_SESSION['id_docente'])) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['primer_acceso'] == 1) {
    header('Location: cambiar_password.php');
    exit();
}

require_once 'php/conexion.php';

$id_docente = $_SESSION['id_docente'];
$id_carrera = $_SESSION['id_carrera_coordina'];

// Obtener datos del docente
$sql = "SELECT d.*, c.nombre as carrera_nombre 
        FROM docente d 
        LEFT JOIN carrera c ON d.id_carrera_coordina = c.id_carrera 
        WHERE d.id_docente = ?";
$stmt = mysqli_prepare($cnx, $sql);
mysqli_stmt_bind_param($stmt, "i", $id_docente);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$docente = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Obtener carrera
$sql_carrera = "SELECT nombre FROM carrera WHERE id_carrera = ?";
$stmt = mysqli_prepare($cnx, $sql_carrera);
mysqli_stmt_bind_param($stmt, "i", $id_carrera);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$carrera = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

$mensaje = '';
$tipo_mensaje = '';

// Procesar subida de foto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
    $archivo = $_FILES['foto'];
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (in_array($extension, $extensiones_permitidas)) {
        if ($archivo['size'] <= 2 * 1024 * 1024) { // Max 2MB
            $nombre_archivo = 'docente_' . $id_docente . '.' . $extension;
            $ruta_destino = 'imagenes/fotos/' . $nombre_archivo;
            
            // Crear carpeta si no existe
            if (!is_dir('imagenes/fotos')) {
                mkdir('imagenes/fotos', 0755, true);
            }
            
            // Eliminar foto anterior si existe
            $fotos_anteriores = glob('imagenes/fotos/docente_' . $id_docente . '.*');
            foreach ($fotos_anteriores as $foto_ant) {
                unlink($foto_ant);
            }
            
            if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                $mensaje = 'Foto actualizada correctamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al subir la foto';
                $tipo_mensaje = 'error';
            }
        } else {
            $mensaje = 'La imagen no debe superar 2MB';
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje = 'Solo se permiten imágenes JPG, PNG o GIF';
        $tipo_mensaje = 'error';
    }
}

// Procesar actualización de datos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_datos'])) {
    $email = trim($_POST['email'] ?? '');
    $celular = trim($_POST['celular'] ?? '');
    $titulo = trim($_POST['titulo'] ?? '');
    
    $update = "UPDATE docente SET email = ?, celular = ?, titulo = ? WHERE id_docente = ?";
    $stmt = mysqli_prepare($cnx, $update);
    mysqli_stmt_bind_param($stmt, "sssi", $email, $celular, $titulo, $id_docente);
    
    if (mysqli_stmt_execute($stmt)) {
        $mensaje = 'Datos actualizados correctamente';
        $tipo_mensaje = 'success';
        
        // Actualizar variable $docente con los nuevos datos
        $docente['email'] = $email;
        $docente['celular'] = $celular;
        $docente['titulo'] = $titulo;
    } else {
        $mensaje = 'Error al actualizar los datos';
        $tipo_mensaje = 'error';
    }
    mysqli_stmt_close($stmt);
}

// Buscar foto del docente
$foto_docente = 'imagenes/fotos/default.png';
$extensiones = ['jpg', 'jpeg', 'png', 'gif'];
foreach ($extensiones as $ext) {
    $ruta_foto = 'imagenes/fotos/docente_' . $id_docente . '.' . $ext;
    if (file_exists($ruta_foto)) {
        $foto_docente = $ruta_foto . '?v=' . time(); // Cache busting
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Sistema PEA</title>
    <link rel="stylesheet" href="css/coordinador.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>

        * { font-family: 'Inter', sans-serif; }

        .perfil-container {
    max-width: 700px;
    margin: 0 auto;
    margin-top: 40px;
}
    .perfil-header {
    background: linear-gradient(135deg, #032184c3 0%, #023475e0 50%, #0c4ba8b6 100%);
    color: white;
    padding: 40px 30px;
    border-radius: 20px 20px 0 0;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.perfil-header::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: pulse 4s ease-in-out infinite;
}
@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.1); opacity: 0.8; }
}
.perfil-avatar-container {
    position: relative;
    width: 130px;
    height: 130px;
    margin: 0 auto 20px;
    z-index: 1;
}
.perfil-avatar {
    width: 130px;
    height: 130px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid rgba(255,255,255,0.9);
    box-shadow: 0 8px 32px rgba(0,0,0,0.3), 0 0 0 4px rgba(255,255,255,0.2);
    background: white;
    transition: transform 0.3s, box-shadow 0.3s;
}
.perfil-avatar:hover {
    transform: scale(1.05);
    box-shadow: 0 12px 40px rgba(0,0,0,0.4), 0 0 0 6px rgba(255,255,255,0.3);
}
.btn-cambiar-foto {
    position: absolute;
    bottom: 5px;
    right: 5px;
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #18a14fff 0%, #2bab5eff 100%);
    border: 3px solid #219d53ff;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;        
    font-size: 1.1rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    transition: transform 0.3s, box-shadow 0.3s;
}
.btn-cambiar-foto:hover {
    transform: scale(1.15) rotate(10deg);
    box-shadow: 0 6px 20px rgba(0,0,0,0.4);
}
.perfil-nombre {
    font-size: 1.6rem;
    font-weight: 600;
    margin-bottom: 10px;
    text-shadow: 0 2px 10px rgba(0,0,0,0.2);
    position: relative;
    z-index: 1;
}
.perfil-rol {
    background: rgba(255,255,255,0.25);
    backdrop-filter: blur(10px);
    color: white;
    padding: 8px 20px;
    border-radius: 25px;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-block;
    border: 1px solid rgba(255,255,255,0.3);
    position: relative;
    z-index: 1;
}
        .perfil-body {
            background: white;
            padding: 30px;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .info-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .info-item label {
            display: block;
            font-size: 0.75rem;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-weight: 600;
        }
        .info-item span {
            font-size: 1rem;
            color: #333;
            font-weight: 500;
        }
        .seccion-titulo {
            font-size: 1.1rem;
            color: #333;
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: #0081c9;
        }
        .botones-accion {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: #0081c9;
            color: white;
        }
        .btn-primary:hover {
            background: #006aa3;
        }
        .btn-warning {
            background: #ffc107;
            color: #000;
        }
        .btn-warning:hover {
            background: #e0a800;
        }
        
        /* Modal para foto */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.activo {
            display: flex;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 400px;
            width: 90%;
            text-align: center;
        }
        .modal-content h3 {
            margin-bottom: 20px;
            color: #333;
        }
        .preview-foto {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin: 20px auto;
            display: block;
            border: 3px solid #e0e0e0;
        }
        .input-file {
            display: none;
        }
        .btn-seleccionar {
            background: #e3f2fd;
            color: #0081c9;
            padding: 12px 25px;
            border: 2px dashed #0081c9;
            border-radius: 8px;
            cursor: pointer;
            display: inline-block;
            margin-bottom: 15px;
        }
        .btn-seleccionar:hover {
            background: #d0e8f7;
        }
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }
        .btn-cancelar {
            background: #6c757d;
            color: white;
        }
        .btn-volver {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px solid #e0e0e0;
    background: white;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.3s;
}
.btn-volver:hover {
    background: #f0f0f0;
    border-color: #0081c9;
    color: #0081c9;
}
.password-wrapper {
    position: relative;
}
.btn-ver-pass {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1rem;
    padding: 0;
}
.btn-cancelar {
    background: #6c757d;
    color: white;
}
.btn-cancelar:hover {
    background: #5a6268;
}
#msgPassword .alert {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 12px;
}
#msgPassword .alert-success {
    background: #d4edda;
    color: #155724;
}
#msgPassword .alert-error {
    background: #f8d7da;
    color: #721c24;
}
        
        
        @media (max-width: 576px) {
            .info-row {
                grid-template-columns: 1fr;
            }
            .botones-accion {
                flex-direction: column;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo">
            <img src="imagenes/itstena.png" alt="ISTTena">
            <div class="logo-separator"></div>
            <span>Sistema PEA</span>
        </div>
            <div class="user-info">
                <span class="badge-coordinador">Coordinador</span>
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></span>
            </div>
        </div>
    </header>
    
    <div class="contenedor">
        <div class="menulateral">
            <div class="carrera-info">
                <small>Carrera</small>
                <strong><?php echo htmlspecialchars($carrera['nombre']); ?></strong>
            </div>
            <nav>
                <ul>
                    <li><a href="indexcordinador.php">🏠 Inicio</a></li>
                    <li><a href="mis_peas.php">📝 Mis PEAs</a></li>
                    <li><a href="asignar_materias.php">📋 Asignar Materias</a></li>
                    <li><a href="ver_asignaciones.php">👥 Ver Asignaciones</a></li>
                    <li><a href="malla_curricular.php">📖 Malla Curricular</a></li>
                    <li><a href="estado_peas.php">📊 Estado de PEAs</a></li>
                    <li><a href="perfilcordinador.php" class="active">👤 Mi Perfil</a></li>
                    <li>
                        <form action="php/configuracion/cerrarsecion.php" method="POST" style="margin:0;">
                            <button type="submit" class="btn-salir">➜] Cerrar Sesión</button>
                        </form>
                    </li>
                </ul>
            </nav>
        </div>
        
        <div class="contenido-principal">
            <div class="page-header">
                <h1>Mi Perfil</h1>
                <p>Información de tu cuenta y datos personales</p>
            </div>
            
            <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
            <?php endif; ?>
            
            <div class="perfil-container">
                <div class="perfil-header">
                    <div class="perfil-avatar-container">
                        <img src="<?php echo $foto_docente; ?>" alt="Foto de perfil" class="perfil-avatar" id="avatarPrincipal" onerror="this.src='imagenes/fotos/default.png'">
                        <button type="button" class="btn-cambiar-foto" onclick="abrirModalFoto()">📷</button>
                    </div>
                    <div class="perfil-nombre"><?php echo htmlspecialchars($docente['nombres'] . ' ' . $docente['apellidos']); ?></div>
                    <span class="perfil-rol">Coordinador de Carrera</span>
                </div>
                
                <div class="perfil-body" id="vistaPerfil">
                    <h3 class="seccion-titulo">📋 Información Personal</h3>
                    
                    <div class="info-row">
    <div class="info-item">
        <label>Cédula</label>
        <span><?php echo htmlspecialchars($docente['cedula']); ?></span>
    </div>
    <div class="info-item">
        <label>Carrera que Coordina</label>
        <span><?php echo htmlspecialchars($docente['carrera_nombre'] ?? 'No asignada'); ?></span>
    </div>
</div>

<div class="info-row">
    <div class="info-item">
        <label>Nombres</label>
        <span><?php echo htmlspecialchars($docente['nombres']); ?></span>
    </div>
    <div class="info-item">
        <label>Apellidos</label>
        <span><?php echo htmlspecialchars($docente['apellidos']); ?></span>
    </div>
</div>

<div class="info-row">
    <div class="info-item">
        <label>Email</label>
        <span><?php echo htmlspecialchars($docente['email'] ?? 'No registrado'); ?></span>
    </div>
    <div class="info-item">
        <label>Celular</label>
        <span><?php echo htmlspecialchars($docente['celular'] ?? 'No registrado'); ?></span>
    </div>
</div>

<div class="info-row">
    <div class="info-item" style="grid-column: span 2;">
        <label>Título Profesional</label>
        <span><?php echo htmlspecialchars($docente['titulo'] ?? 'No registrado'); ?></span>
    </div>
</div>
                    
                    <h3 class="seccion-titulo">Datos Editables</h3>
                    
                    <form method="POST">
                        <div class="info-row">
                            <div class="form-group">
                                <label for="email">Correo Electrónico</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($docente['email'] ?? ''); ?>"
                                       placeholder="ejemplo@itstena.edu.ec">
                            </div>
                            <div class="form-group">
                                <label for="celular">Celular</label>
                                <input type="text" id="celular" name="celular" class="form-control" 
                                       value="<?php echo htmlspecialchars($docente['celular'] ?? ''); ?>"
                                       placeholder="0999999999" maxlength="10">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="titulo">Título Profesional</label>
                            <input type="text" id="titulo" name="titulo" class="form-control" 
                                   value="<?php echo htmlspecialchars($docente['titulo'] ?? ''); ?>"
                                   placeholder="Ej: Ingeniero en Sistemas">
                        </div>
                        
               <div class="botones-accion">
                            <button type="submit" name="guardar_datos" class="btn btn-primary">💾 Guardar Cambios</button>
                            <button type="button" class="btn btn-warning" onclick="mostrarCambioPassword()">🔑 Cambiar Contraseña</button>
                        </div>
                    </form>
                    
                    <h3 class="seccion-titulo">Información del Sistema</h3>
                    
                    <div class="info-row">
                        <div class="info-item">
                            <label>Fecha de Registro</label>
                            <span><?php echo date('d/m/Y', strtotime($docente['fecha_registro'])); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Último Acceso</label>
                            <span><?php echo $docente['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($docente['ultimo_acceso'])) : 'N/A'; ?></span>
                        </div>
                    </div>
                </div><!-- FIN vistaPerfil -->
                
                <!-- VISTA CAMBIO DE CONTRASEÑA -->
                <div class="perfil-body" id="vistaPassword" style="display: none;">
                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 25px;">
                        <button type="button" class="btn-volver" onclick="volverPerfil()">←</button>
                        <h3 style="margin: 0;">Cambiar Contraseña</h3>
                    </div>
                    
                    <div id="msgPassword"></div>
                    
                    <form id="formPassword">
                        <div class="form-group">
                            <label for="pass_actual">Contraseña Actual</label>
                            <div class="password-wrapper">
                                <input type="password" id="pass_actual" name="pass_actual" class="form-control" required>
                                <button type="button" class="btn-ver-pass" onclick="togglePass('pass_actual')">👁️</button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="pass_nueva">Nueva Contraseña</label>
                            <div class="password-wrapper">
                                <input type="password" id="pass_nueva" name="pass_nueva" class="form-control" minlength="6" required>
                                <button type="button" class="btn-ver-pass" onclick="togglePass('pass_nueva')">👁️</button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="pass_confirmar">Confirmar Nueva Contraseña</label>
                            <div class="password-wrapper">
                                <input type="password" id="pass_confirmar" name="pass_confirmar" class="form-control" minlength="6" required>
                                <button type="button" class="btn-ver-pass" onclick="togglePass('pass_confirmar')">👁️</button>
                            </div>
                        </div>
                        
                        <p style="font-size: 0.85rem; color: #666; background: #f8f9fa; padding: 12px; border-radius: 8px;">
                            ℹ️ La contraseña debe tener mínimo 6 caracteres
                        </p>
                        
                        <div class="botones-accion" style="margin-top: 25px;">
                            <button type="submit" class="btn btn-primary">💾 Actualizar Contraseña</button>
                            <button type="button" class="btn btn-cancelar" onclick="volverPerfil()">Cancelar</button>
                        </div>
                    </form>
                </div><!-- FIN vistaPassword -->
                
            </div><!-- FIN perfil-container -->
        </div>
    </div>
                    
                    
                    
      
    
    <!-- Modal para cambiar foto -->
    <div class="modal-overlay" id="modalFoto">
        <div class="modal-content">
            <h3>📷 Cambiar Foto de Perfil</h3>
            <form method="POST" enctype="multipart/form-data" id="formFoto">
                <img src="<?php echo $foto_docente; ?>" alt="Preview" class="preview-foto" id="previewFoto" onerror="this.src='imagenes/fotos/default.png'">
                
                <label for="inputFoto" class="btn-seleccionar">
                    📁 Seleccionar imagen
                </label>
                <input type="file" name="foto" id="inputFoto" class="input-file" accept="image/jpeg,image/png,image/gif">
                
                <p style="font-size: 0.8rem; color: #666;">Formatos: JPG, PNG, GIF. Máximo 2MB</p>
                
                <div class="modal-buttons">
                    <button type="button" class="btn btn-cancelar" onclick="cerrarModalFoto()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnSubirFoto" disabled>Subir Foto</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
// Foto de perfil
function abrirModalFoto() {
    document.getElementById('modalFoto').classList.add('activo');
}

function cerrarModalFoto() {
    document.getElementById('modalFoto').classList.remove('activo');
    document.getElementById('inputFoto').value = '';
    document.getElementById('previewFoto').src = document.getElementById('avatarPrincipal').src;
    document.getElementById('btnSubirFoto').disabled = true;
}

document.getElementById('inputFoto').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewFoto').src = e.target.result;
            document.getElementById('btnSubirFoto').disabled = false;
        }
        reader.readAsDataURL(file);
    }
});

document.getElementById('modalFoto').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalFoto();
});

document.getElementById('celular').addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
});

// Cambio de contraseña
function mostrarCambioPassword() {
    document.getElementById('vistaPerfil').style.display = 'none';
    document.getElementById('vistaPassword').style.display = 'block';
    document.getElementById('formPassword').reset();
    document.getElementById('msgPassword').innerHTML = '';
}

function volverPerfil() {
    document.getElementById('vistaPassword').style.display = 'none';
    document.getElementById('vistaPerfil').style.display = 'block';
}

function togglePass(id) {
    const input = document.getElementById(id);
    const btn = input.nextElementSibling;
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = '🔒';
    } else {
        input.type = 'password';
        btn.textContent = '👁️';
    }
}

document.getElementById('formPassword').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const actual = document.getElementById('pass_actual').value;
    const nueva = document.getElementById('pass_nueva').value;
    const confirmar = document.getElementById('pass_confirmar').value;
    
    if (nueva !== confirmar) {
        document.getElementById('msgPassword').innerHTML = '<div class="alert alert-error">⚠️ Las contraseñas no coinciden</div>';
        return;
    }
    
    const formData = new FormData();
    formData.append('pass_actual', actual);
    formData.append('pass_nueva', nueva);
    
    fetch('php/coordinador/cambiar_password_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('msgPassword').innerHTML = '<div class="alert alert-success">✓ ' + data.message + '</div>';
            document.getElementById('formPassword').reset();
            setTimeout(() => volverPerfil(), 2000);
        } else {
            document.getElementById('msgPassword').innerHTML = '<div class="alert alert-error">⚠️ ' + data.message + '</div>';
        }
    })
    .catch(error => {
        document.getElementById('msgPassword').innerHTML = '<div class="alert alert-error">⚠️ Error de conexión</div>';
    });
});
    </script>
    

</body>
</html>
