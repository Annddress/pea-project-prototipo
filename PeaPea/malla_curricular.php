<?php
session_start();

if (!isset($_SESSION['id_docente']) || $_SESSION['rol'] !== 'coordinador') {
    header('Location: login.php');
    exit();
}

require_once 'php/conexion.php';

$id_carrera = $_SESSION['id_carrera_coordina'];

// Obtener carrera
$sql_carrera = "SELECT nombre, codigo, total_periodos FROM carrera WHERE id_carrera = ?";
$stmt = mysqli_prepare($cnx, $sql_carrera);
mysqli_stmt_bind_param($stmt, "i", $id_carrera);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$carrera = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Obtener asignaturas agrupadas por nivel
$sql_asignaturas = "SELECT * FROM asignatura WHERE id_carrera = ? AND activa = 1 ORDER BY nivel, nombre";
$stmt = mysqli_prepare($cnx, $sql_asignaturas);
mysqli_stmt_bind_param($stmt, "i", $id_carrera);
mysqli_stmt_execute($stmt);
$result_asig = mysqli_stmt_get_result($stmt);

$asignaturas_por_nivel = [];
while ($row = mysqli_fetch_assoc($result_asig)) {
    $nivel = $row['nivel'];
    if (!isset($asignaturas_por_nivel[$nivel])) {
        $asignaturas_por_nivel[$nivel] = [];
    }
    $asignaturas_por_nivel[$nivel][] = $row;
}
mysqli_stmt_close($stmt);

// Calcular totales
$total_asignaturas = 0;
$total_horas = 0;
$total_creditos = 0;
foreach ($asignaturas_por_nivel as $nivel => $asigs) {
    foreach ($asigs as $asig) {
        $total_asignaturas++;
        $total_horas += $asig['total_horas'];
        $total_creditos += $asig['creditos'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Malla Curricular - Sistema PEA</title>
    <link rel="stylesheet" href="css/coordinador.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
    
        .malla-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .nivel-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .nivel-header {
            background: linear-gradient(135deg, #0081c9 0%, #005a8c 100%);
            color: white;
            padding: 15px 20px;
            font-weight: 600;
        }
        .nivel-body {
            padding: 15px;
        }
        .asignatura-item {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .asignatura-item:last-child {
            border-bottom: none;
        }
        .asignatura-nombre {
            font-size: 0.9rem;
            color: #333;
            flex: 1;
        }
        .asignatura-info {
            display: flex;
            gap: 10px;
            font-size: 0.75rem;
        }
        .asignatura-horas {
            background: #e3f2fd;
            color: #0081c9;
            padding: 3px 8px;
            border-radius: 10px;
        }
        .asignatura-creditos {
            background: #d4edda;
            color: #155724;
            padding: 3px 8px;
            border-radius: 10px;
        }
        .resumen-malla {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .resumen-item {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .resumen-item h3 {
            font-size: 1.8rem;
            #1e3a5f;
            margin-bottom: 5px;
        }
        .resumen-item p {
            color: #666;
            font-size: 0.85rem;
        }
        .unidad-badge {
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 8px;
            margin-left: 5px;
        }
        .unidad-basica { background: #fff3cd; color: #856404; }
        .unidad-profesional { background: #cce5ff; color: #004085; }
        .unidad-integracion { background: #d4edda; color: #155724; }
        .unidad-practicas { background: #f8d7da; color: #721c24; }
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
                    <li><a href="malla_curricular.php" class="active">📖 Malla Curricular</a></li>
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
            <div class="page-header">
                <h1>Malla Curricular</h1>
                <p><?php echo htmlspecialchars($carrera['nombre']); ?></p>
            </div>
            
            <div class="resumen-malla">
                <div class="resumen-item">
                    <h3><?php echo $carrera['total_periodos']; ?></h3>
                    <p>Periodos</p>
                </div>
                <div class="resumen-item">
                    <h3><?php echo $total_asignaturas; ?></h3>
                    <p>Asignaturas</p>
                </div>
                <div class="resumen-item">
                    <h3><?php echo number_format($total_horas); ?></h3>
                    <p>Horas Totales</p>
                </div>
                <div class="resumen-item">
                    <h3><?php echo $total_creditos; ?></h3>
                    <p>Créditos</p>
                </div>
            </div>
            
            <div class="malla-grid">
                <?php for ($nivel = 1; $nivel <= $carrera['total_periodos']; $nivel++): ?>
                <div class="nivel-card">
                    <div class="nivel-header">
                        📘 Periodo <?php echo $nivel; ?>
                    </div>
                    <div class="nivel-body">
                        <?php if (isset($asignaturas_por_nivel[$nivel])): ?>
                            <?php foreach ($asignaturas_por_nivel[$nivel] as $asig): ?>
                            <div class="asignatura-item">
                                <div class="asignatura-nombre">
                                    <?php echo htmlspecialchars($asig['nombre']); ?>
                                    
                                </div>
                                <div class="asignatura-info">
                                    <?php 
                                    $unidad = $asig['unidad_organizacion'];
                                    $unidad_class = '';
                                    $unidad_text = '';
                                    switch($unidad) {
                                        case 'basica': $unidad_class = 'unidad-basica'; $unidad_text = 'Básica'; break;
                                        case 'profesional': $unidad_class = 'unidad-profesional'; $unidad_text = 'Prof.'; break;
                                        case 'integracion': $unidad_class = 'unidad-integracion'; $unidad_text = 'Integración'; break;
                                        case 'practicas': $unidad_class = 'unidad-practicas'; $unidad_text = 'Prácticas'; break;
                                    }
                                    if ($unidad_text): ?>
                                    <span class="unidad-badge <?php echo $unidad_class; ?>"><?php echo $unidad_text; ?></span>
                                    <?php endif; ?>
                                    <span class="asignatura-horas"><?php echo $asig['total_horas']; ?>h</span>
                                    <span class="asignatura-creditos"><?php echo $asig['creditos']; ?>c</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align:center; color:#666; padding:20px;">Sin asignaturas</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</body>
</html>
