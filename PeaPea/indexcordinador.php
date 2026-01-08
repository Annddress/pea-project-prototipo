<?php
session_start();

// Verificar sesión y rol
if (!isset($_SESSION['id_docente'])) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['primer_acceso'] == 1) {
    header('Location: cambiar_password.php');
    exit();
}

// Obtener datos del coordinador
require_once 'php/conexion.php';

$id_docente = $_SESSION['id_docente'];
$id_carrera = $_SESSION['id_carrera_coordina'];

// Obtener información de la carrera que coordina
$sql_carrera = "SELECT nombre, codigo FROM carrera WHERE id_carrera = ?";
$stmt = mysqli_prepare($cnx, $sql_carrera);
mysqli_stmt_bind_param($stmt, "i", $id_carrera);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$carrera = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Obtener periodo activo
$sql_periodo = "SELECT id_periodo, nombre FROM periodo_academico WHERE activo = 1 LIMIT 1";
$result_periodo = mysqli_query($cnx, $sql_periodo);
$periodo_activo = mysqli_fetch_assoc($result_periodo);

// Contar asignaturas de la carrera
$sql_asig = "SELECT COUNT(*) as total FROM asignatura WHERE id_carrera = ? AND activa = 1";
$stmt = mysqli_prepare($cnx, $sql_asig);
mysqli_stmt_bind_param($stmt, "i", $id_carrera);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$total_asignaturas = mysqli_fetch_assoc($result)['total'];
mysqli_stmt_close($stmt);

// Contar asignaciones del periodo activo
$sql_asignaciones = "SELECT COUNT(*) as total FROM asignacion a 
                     INNER JOIN asignatura asig ON a.id_asignatura = asig.id_asignatura 
                     WHERE asig.id_carrera = ? AND a.id_periodo = ?";
$stmt = mysqli_prepare($cnx, $sql_asignaciones);
mysqli_stmt_bind_param($stmt, "ii", $id_carrera, $periodo_activo['id_periodo']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$total_asignaciones = mysqli_fetch_assoc($result)['total'];
mysqli_stmt_close($stmt);

// Contar docentes con asignaciones en esta carrera
$sql_docentes = "SELECT COUNT(DISTINCT a.id_docente) as total FROM asignacion a 
                 INNER JOIN asignatura asig ON a.id_asignatura = asig.id_asignatura 
                 WHERE asig.id_carrera = ? AND a.id_periodo = ?";
$stmt = mysqli_prepare($cnx, $sql_docentes);
mysqli_stmt_bind_param($stmt, "ii", $id_carrera, $periodo_activo['id_periodo']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$total_docentes = mysqli_fetch_assoc($result)['total'];
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Coordinador - Sistema PEA</title>
    <link rel="stylesheet" href="css/coordinador.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
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
                    <li><a href="indexcordinador.php" class="active">🏠 Inicio</a></li>
                    <li><a href="mis_peas.php">📝 Mis PEAs</a></li>
                    <li><a href="asignar_materias.php">📋 Asignar Materias</a></li>
                    <li><a href="ver_asignaciones.php">👥 Ver Asignaciones</a></li>
                    <li><a href="malla_curricular.php">📖 Malla Curricular</a></li>
                    <li><a href="estado_peas.php">📊 Estado de PEAs</a></li>
                    <li><a href="perfilcordinador.php">👤 Mi Perfil</a></li>
                    <li>
                        <form action="php/configuracion/cerrarsecion.php" method="POST" style="margin:0;">
                            <button type="submit" class="btn-salir">➜] Cerrar Sesión</button>
                        </form>
                    </li>
                </ul>
            </nav>
        </div>
        
        <div class="contenido-principal">
            <?php if (isset($_GET['bienvenido'])): ?>
            <div class="alert alert-success">
                ¡Bienvenido/a! Has iniciado sesión correctamente.
            </div>
            <?php endif; ?>
            
            <div class="page-header">
                <h1>Panel de Coordinador</h1>
                <p>Gestiona las asignaciones de materias para tu carrera</p>
            </div>
            
            <div class="periodo-actual">
                <span class="label">Periodo Académico Activo:</span>
                <span class="valor"><?php echo htmlspecialchars($periodo_activo['nombre'] ?? 'No definido'); ?></span>
            </div>
            
            <div class="dashboard-cards">
                <div class="card card-primary">
                    <div class="card-icon">📚</div>
                    <div class="card-info">
                        <h3><?php echo $total_asignaturas; ?></h3>
                        <p>Asignaturas en Malla</p>
                    </div>
                </div>
                
                <div class="card card-success">
                    <div class="card-icon">✅</div>
                    <div class="card-info">
                        <h3><?php echo $total_asignaciones; ?></h3>
                        <p>Asignaciones Realizadas</p>
                    </div>
                </div>
                
                <div class="card card-info">
                    <div class="card-icon">👨‍🏫</div>
                    <div class="card-info">
                        <h3><?php echo $total_docentes; ?></h3>
                        <p>Docentes Asignados</p>
                    </div>
                </div>
                
                <div class="card card-warning">
                    <div class="card-icon">📝</div>
                    <div class="card-info">
                        <h3>0</h3>
                        <p>PEAs Pendientes</p>
                    </div>
                </div>
            </div>
            
            <div class="acciones-rapidas">
                <h2>Acciones Rápidas</h2>
                <div class="acciones-grid">
                    <a href="asignar_materias.php" class="accion-card">
                        <div class="accion-icon">🗒</div>
                        <h3>Asignar Materias</h3>
                        <p>Asigna asignaturas a los docentes para el periodo actual</p>
                    </a>
                    
                    <a href="ver_asignaciones.php" class="accion-card">
                        <div class="accion-icon">𖨆𖨆</div>
                        <h3>Ver Asignaciones</h3>
                        <p>Revisa las asignaciones realizadas por docente</p>
                    </a>
                    
                    <a href="malla_curricular.php" class="accion-card">
                        <div class="accion-icon">🕮</div>
                        <h3>Malla Curricular</h3>
                        <p>Consulta las asignaturas de la carrera</p>
                    </a>
                    
                    <a href="estado_peas.php" class="accion-card">
                        <div class="accion-icon">✔</div>
                        <h3>Estado de PEAs</h3>
                        <p>Monitorea el progreso de elaboración de PEAs</p>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
