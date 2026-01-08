<?php
session_start();

if (!isset($_SESSION['id_docente']) || $_SESSION['rol'] !== 'coordinador') {
    header('Location: login.php');
    exit();
}

require_once 'php/conexion.php';

$id_carrera = $_SESSION['id_carrera_coordina'];
$mensaje = '';
$tipo_mensaje = '';

// Obtener carrera
$sql_carrera = "SELECT nombre FROM carrera WHERE id_carrera = ?";
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

// Obtener todos los docentes activos
$sql_docentes = "SELECT id_docente, cedula, nombres, apellidos FROM docente WHERE activo = 1 ORDER BY apellidos, nombres";
$result_docentes = mysqli_query($cnx, $sql_docentes);
$docentes = [];
while ($row = mysqli_fetch_assoc($result_docentes)) {
    $docentes[] = $row;
}

// Obtener asignaturas de la carrera
$sql_asignaturas = "SELECT id_asignatura, nombre, nivel, total_horas, creditos 
                    FROM asignatura 
                    WHERE id_carrera = ? AND activa = 1 
                    ORDER BY nivel, nombre";
$stmt = mysqli_prepare($cnx, $sql_asignaturas);
mysqli_stmt_bind_param($stmt, "i", $id_carrera);
mysqli_stmt_execute($stmt);
$result_asig = mysqli_stmt_get_result($stmt);
$asignaturas = [];
while ($row = mysqli_fetch_assoc($result_asig)) {
    $asignaturas[] = $row;
}
mysqli_stmt_close($stmt);

// Procesar asignación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['asignar'])) {
   
    
    
    
    $id_docente_asig = intval($_POST['id_docente']);
    $id_asignatura = intval($_POST['id_asignatura']);
    $paralelo = $_POST['paralelo'];
    $jornada = $_POST['jornada'];
    
    // Verificar si ya existe la asignación
    $sql_check = "SELECT id_asignacion FROM asignacion 
                  WHERE id_docente = ? AND id_asignatura = ? AND id_periodo = ? AND paralelo = ?";
    $stmt_check = mysqli_prepare($cnx, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "iiis", $id_docente_asig, $id_asignatura, $periodo_activo['id_periodo'], $paralelo);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    
    if (mysqli_num_rows($result_check) > 0) {
        $mensaje = 'Esta asignación ya existe para este docente y paralelo';
        $tipo_mensaje = 'error';
    } else {
        $sql_insert = "INSERT INTO asignacion (id_docente, id_asignatura, id_periodo, paralelo, jornada, asignado_por) 
                       VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_insert = mysqli_prepare($cnx, $sql_insert);
        mysqli_stmt_bind_param($stmt_insert, "iiissi", $id_docente_asig, $id_asignatura, $periodo_activo['id_periodo'], $paralelo, $jornada, $_SESSION['id_docente']);
        
        if (mysqli_stmt_execute($stmt_insert)) {
            $mensaje = 'Asignación creada correctamente';
            $tipo_mensaje = 'success';
        } else {
            $mensaje = 'Error al crear la asignación';
            $tipo_mensaje = 'error';
        }
        mysqli_stmt_close($stmt_insert);
    }
    mysqli_stmt_close($stmt_check);
}

// Obtener asignaciones AGRUPADAS POR DOCENTE
$sql_asignaciones = "SELECT d.id_docente, d.nombres, d.apellidos, d.cedula,
                            a.id_asignacion, a.paralelo, a.jornada,
                            asig.nombre as asignatura, asig.nivel, asig.total_horas
                     FROM asignacion a
                     INNER JOIN docente d ON a.id_docente = d.id_docente
                     INNER JOIN asignatura asig ON a.id_asignatura = asig.id_asignatura
                     WHERE asig.id_carrera = ? AND a.id_periodo = ?
                     ORDER BY d.apellidos, d.nombres, asig.nivel, asig.nombre";
$stmt = mysqli_prepare($cnx, $sql_asignaciones);
mysqli_stmt_bind_param($stmt, "ii", $id_carrera, $periodo_activo['id_periodo']);
mysqli_stmt_execute($stmt);
$result_asignaciones = mysqli_stmt_get_result($stmt);

// Agrupar por docente
$docentes_asignados = [];
while ($row = mysqli_fetch_assoc($result_asignaciones)) {
    $id_doc = $row['id_docente'];
    if (!isset($docentes_asignados[$id_doc])) {
        $docentes_asignados[$id_doc] = [
            'id_docente' => $id_doc,
            'nombres' => $row['nombres'],
            'apellidos' => $row['apellidos'],
            'cedula' => $row['cedula'],
            'materias' => []
        ];
    }
    $docentes_asignados[$id_doc]['materias'][] = [
        'id_asignacion' => $row['id_asignacion'],
        'asignatura' => $row['asignatura'],
        'nivel' => $row['nivel'],
        'paralelo' => $row['paralelo'],
        'jornada' => $row['jornada'],
        'total_horas' => $row['total_horas']
    ];
}
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignar Materias - Sistema PEA</title>
    <link rel="stylesheet" href="css/coordinador.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
  .form-asignacion {
            width: 100%;
            margin: 0 auto 30px;
            background: rgba(242, 245, 249, 0.85);
            
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            padding-bottom: 10px;
            overflow: hidden;
        }

        .form-asignacion-header{
            background: linear-gradient(135deg, #012e7aef  0%, #012d7acb 50%, #235bbdef 100%);
            border-bottom: 1px solid #e5e7eb;
            padding: 15px 20px;
            border-radius: 15px 15px 0 0; 
            padding-bottom: 12px;
            font-size: 1.0rem;
            
        }
        .form-asignacion-header p{
            color: #d8d8d8ff;
            font-size: 0.862rem;
            margin-top: -10px;
        }
      
        .form-asignacion h3 {
            margin-bottom: 20px;
            color: #e4e3e3ff;
        }
        
        .form-asignacion-body {
    padding: 20px;
}
       /*form row no usado*/
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-bottom: 20px;
        }

        /* Buscador */
        .filtro-buscar {
            display: flex;
            
        }
        .buscador-section {
            display: flex; 
            background: rgba(242, 245, 249, 0.85);
            padding: 15px 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .buscador-input {
            max-width: 199px;
            padding: 3px 8px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 0.82rem;
            transition: border-color 0.3s;
        }
        .buscador-input:focus {
            outline: none;
            border-color: #0081c9;
        }
        
        /* Tarjetas de docentes */
        .docente-card {
              background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 15px;
    overflow: hidden;
    transition: box-shadow 0.2s;
        }
        .docente-card.oculto {
            display: none;
        }
        .docente-header {
            background: #f9fafb;
            padding: 13px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
        }
        .docente-info h3 {
            color: #333;
            font-size: 0.95rem;
            margin-bottom: 5px;
        }
        .docente-info p {
            color: #666;
            font-size: 0.85rem;
        }
        .docente-stats {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .stat-badge {
            background: #1e3a5f;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .btn-editar {
            background: #ffc107;
            color: #000;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: background 0.3s;
        }
        .btn-editar:hover {
            background: #e0a800;
        }
        .btn-editar.activo {
            background: #ffc107;
            color: black;
            padding: 8px 15px;  
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 400;
            transition: background 0.3s;
        }
        .btn-editar.activo:hover {
            background: #e0a800;
        }
        
        /* Lista de materias */
       
        .materia-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        .materia-item:last-child {
            margin-bottom: 0;
        }
        .materia-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }
        .nivel-badge {
            background: #e3f2fd;
            color: #0081c9;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .materia-nombre {
            font-size: 0.95rem;
            color: #333;
        }
        .materia-detalles {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .paralelo-badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .paralelo-a { background: #d4edda; color: #155724; }
        .paralelo-b { background: #fff3cd; color: #856404; }
        .paralelo-ab { background: #d1ecf1; color: #0c5460; }
        .horas-badge {
            background: #e9ecef;
            color: #495057;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
        }
        
        /* Botón eliminar */
.btn-eliminar-materia {
    background: #dc3545;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 0.3s;
    min-width: 32px;
}
.btn-eliminar-materia:hover {
    background: #c82333;
    transform: scale(1.05);
}
.btn-eliminar-materia.confirmar {
    background: #ffc107;
    color: #000;
    animation: pulse-btn 0.5s ease;
}
.btn-eliminar-materia.confirmar:hover {
    background: #e0a800;
}
@keyframes pulse-btn {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.15); }
}
.materia-item.eliminando {
    opacity: 0;
    transform: translateX(50px);
    max-height: 0;
    padding: 0;
    margin: 0;
    overflow: hidden;
}
.docente-card.eliminando {
    opacity: 0;
    transform: scale(0.95);
    max-height: 0;
    overflow: hidden;
}
        
        /* Modo edición */
        .modo-edicion .materia-item {
            background: #fff5f5;
            border: 1px inset #dc3545;
        }
        .btn-eliminar-materia {
    display: none;
}
.btn-eliminar-materia {
    display: none;
}
.modo-edicion .btn-eliminar-materia {
    display: inline-block;
}
.modo-edicion .paralelo-badge {
    cursor: pointer;
    position: relative;
}
.modo-edicion .paralelo-badge:hover {
    opacity: 0.8;
    transform: scale(1.05);
}
.modo-edicion .paralelo-badge::after {
    content: '↔';
    margin-left: 5px;
    font-size: 0.7rem;
}
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .empty-state .icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        /* Total horas */
        .total-horas {
            padding: 4px 11px;
            background: #e3f2fd;
            border-top: 1px solid #bee5eb;
            display: flex;
            justify-content: flex-end;
            font-weight: 450;
            font-size: 0.8rem;
            color: #0081c9;
        }
        /* Toast Notifications */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.toast {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    border-radius: 12px;
    background: white;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    min-width: 300px;
    max-width: 450px;
    animation: slideIn 0.3s ease;
    border-left: 4px solid;
}
.toast.success { border-color: #10b981; }
.toast.error { border-color: #ef4444; }
.toast.warning { border-color: #f59e0b; }
.toast.info { border-color: #3b82f6; }
.toast-icon {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
}
.toast.success .toast-icon { background: #d1fae5; color: #10b981; }
.toast.error .toast-icon { background: #fee2e2; color: #ef4444; }
.toast.warning .toast-icon { background: #fef3c7; color: #f59e0b; }
.toast.info .toast-icon { background: #dbeafe; color: #3b82f6; }
.toast-content { flex: 1; }
.toast-title { font-weight: 600; font-size: 0.9rem; color: #1f2937; margin-bottom: 2px; }
.toast-message { font-size: 0.8rem; color: #6b7280; }
.toast-close {
    background: none;
    border: none;
    color: #9ca3af;
    cursor: pointer;
    padding: 4px;
    font-size: 1.2rem;
    line-height: 1;
}
.toast-close:hover { color: #6b7280; }
@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
@keyframes slideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}
.toast.hiding { animation: slideOut 0.3s ease forwards; }
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
                    <li><a href="asignar_materias.php" class="active">📋 Asignar Materias</a></li>
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
            <div class="page-header">
                <h1>Asignar Materias</h1>
                <p>Asigna las asignaturas de la malla curricular a los docentes</p>
            </div>
            
            <div class="periodo-actual">
                <span class="label">Periodo Académico Activo:</span>
                <span class="valor"><?php echo htmlspecialchars($periodo_activo['nombre'] ?? 'No definido'); ?></span>
            </div>
            
            <?php if ($mensaje): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    showToast('<?php echo $tipo_mensaje; ?>', 
              '<?php echo $tipo_mensaje === "success" ? "¡Éxito!" : "Error"; ?>', 
              '<?php echo addslashes($mensaje); ?>');
});
</script>
<?php endif; ?>
            
            <!-- FORMULARIO NUEVA ASIGNACIÓN -->
            <div id="nueva-asignacion">
                <div class="form-asignacion">
                <div class = "form-asignacion-header">
                    <h3>Nueva Asignación</h3>
                    <p> Asignar asignaturas correspondientes a docentes.
                </div>
          <div class="form-asignacion-body">
                <form method="POST">
                    <div class="form-row">
                        
                        <div class="form-group">
                            
                            <label for="id_asignatura">Asignatura</label>
                            <select name="id_asignatura" id="id_asignatura" class="form-control" required>
                                <option value="">Seleccione una asignatura</option>
                                <?php foreach ($asignaturas as $asig): ?>
                                <option value="<?php echo $asig['id_asignatura']; ?>">
                                    [Nivel <?php echo $asig['nivel']; ?>] <?php echo htmlspecialchars($asig['nombre']); ?> (<?php echo $asig['total_horas']; ?>h)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="id_docente">Docente</label>
                            <select name="id_docente" id="id_docente" class="form-control" required>
                                <option value="">Seleccione un docente</option>
                                <?php foreach ($docentes as $doc): ?>
                                <option value="<?php echo $doc['id_docente']; ?>">
                                    <?php echo htmlspecialchars($doc['apellidos'] . ' ' . $doc['nombres']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="paralelo">Paralelo</label>
                            <select name="paralelo" id="paralelo" class="form-control" required>
                                <option value="A">A - Matutino</option>
                                <option value="B">B - Nocturno</option>
                                 <option value="AB">A - B Matutino Y Nocturno</option>
                            </select>
                        </div>
                        
                      <div class="form-group">
    <label for="jornada">Jornada</label>
    <select name="jornada" id="jornada" class="form-control" required>
        <option value="matutina">Matutina</option>
        <option value="nocturna">Nocturna</option>
        <option value="ambas">Ambas (Matutina y Nocturna)</option>
    </select>
</div>
                        
                        
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end;">
                            <button type="submit" name="asignar" class="btn btn-success">
                                Asignar ✔
                            </button>
                        </div>
                </form>
                                </div>
            </div>
        </div>
            <!-- BUSCADOR DE DOCENTES -->
            <!-- FILTROS -->
<?php if (count($docentes_asignados) > 0): ?>
<div class="buscador-section">
    <div style="display: flex; grid-template-columns: 2fr 1fr; gap: 15px;">
        <div>
            <label style="font-size: 0.8rem; color: #666; display: block; margin-bottom: 5px;">🔎︎ Buscar docente</label>
            <input type="text" 
                   id="buscadorDocente" 
                   class="buscador-input" 
                   placeholder="Nombre, apellido o cédula..."
                   autocomplete="off">
        </div>
        <div>
            <label style="font-size: 0.8rem; color: #666; display: block; margin-bottom: 5px;">☰ Filtrar por nivel</label>
            <select id="filtroNivel" class="buscador-input" style="cursor: pointer;">
                <option value="">Todos los niveles</option>
                <?php 
                $niveles_unicos = array_unique(array_column($asignaturas, 'nivel'));
                sort($niveles_unicos);
                foreach ($niveles_unicos as $niv): ?>
                <option value="<?php echo $niv; ?>">Nivel <?php echo $niv; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>
<?php endif; ?>        
            <!-- LISTA DE DOCENTES CON SUS MATERIAS -->
            <div id="listaDocentes">
                <?php if (count($docentes_asignados) > 0): ?>
                    <?php foreach ($docentes_asignados as $doc): ?>
                    <?php 
                    $total_horas_doc = array_sum(array_column($doc['materias'], 'total_horas'));
                    ?>
                    <div class="docente-card" 
                         data-nombre="<?php echo strtolower($doc['nombres'] . ' ' . $doc['apellidos']); ?>"
                         data-cedula="<?php echo $doc['cedula']; ?>">
                        <div class="docente-header">
                            <div class="docente-info">
                                <h3>👤 <?php echo htmlspecialchars($doc['apellidos'] . ' ' . $doc['nombres']); ?></h3>
                                <p>Cédula: <?php echo htmlspecialchars($doc['cedula']); ?></p>
                            </div>
                            <div class="docente-stats">
                                <span class="stat-badge"><?php echo count($doc['materias']); ?> materia<?php echo count($doc['materias']) != 1 ? 's' : ''; ?></span>
                                <button type="button" class="btn-editar" onclick="toggleEdicion(this)">
                                   Editar ✎
                                </button>
                            </div>
                        </div>
                        <div class="materias-body">
                            <?php foreach ($doc['materias'] as $mat): ?>
                            <div class="materia-item" data-nivel="<?php echo $mat['nivel']; ?>">
                                <div class="materia-info">
                                    <span class="nivel-badge">Nivel <?php echo $mat['nivel']; ?></span>
                                    <span class="materia-nombre"><?php echo htmlspecialchars($mat['asignatura']); ?></span>
                                </div>
                                <div class="materia-detalles">
                                    <span class="horas-badge"><?php echo $mat['total_horas']; ?>h</span>
                                    <span class="paralelo-badge paralelo-<?php echo strtolower($mat['paralelo']); ?>" 
      data-id="<?php echo $mat['id_asignacion']; ?>"
      data-paralelo="<?php echo $mat['paralelo']; ?>"
      onclick="cambiarParalelo(this)">
    Paralelo <?php echo $mat['paralelo'] === 'AB' ? 'A y B' : $mat['paralelo']; ?>
</span>
                                   
                                    <button type="button" class="btn-eliminar-materia" 
        data-id="<?php echo $mat['id_asignacion']; ?>"
        onclick="prepararEliminar(this)">X</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="total-horas">
                            Total: <?php echo $total_horas_doc; ?> horas
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="empty-state">
                    <div class="icon">📭</div>
                    <h3>Sin asignaciones</h3>
                    <p>Aún no hay asignaturas asignadas para este periodo. Usa el formulario de arriba para comenzar.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
<script>
    // Toast Notifications
function showToast(type, title, message, duration = 5000) {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
    };
    
    toast.innerHTML = `
        <div class="toast-icon">${icons[type]}</div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="closeToast(this)">×</button>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        closeToast(toast.querySelector('.toast-close'));
    }, duration);
}

function closeToast(btn) {
    const toast = btn.closest ? btn.closest('.toast') : btn.parentElement;
    toast.classList.add('hiding');
    setTimeout(() => toast.remove(), 300);
}
    // Auto-seleccionar jornada según paralelo
document.getElementById('paralelo').addEventListener('change', function() {
    const jornada = document.getElementById('jornada');
    if (this.value === 'A') {
        jornada.value = 'matutina';
    } else if (this.value === 'B') {
        jornada.value = 'nocturna';
    } else if (this.value === 'AB') {
        jornada.value = 'ambas';
    }
});
    const buscadorDocente = document.getElementById('buscadorDocente');
    const filtroNivel = document.getElementById('filtroNivel');

    function aplicarFiltros() {
        const termino = buscadorDocente.value.toLowerCase().trim();
        const nivelSel = filtroNivel.value;
        
        const tarjetas = document.querySelectorAll('.docente-card');
        
        tarjetas.forEach(function(tarjeta) {
            const nombre = tarjeta.dataset.nombre;
            const cedula = tarjeta.dataset.cedula;
            const materias = tarjeta.querySelectorAll('.materia-item');
            
            const coincideDocente = nombre.includes(termino) || cedula.includes(termino);
            
            if (!coincideDocente) {
                tarjeta.classList.add('oculto');
                return;
            }
            
            let tieneMateriasVisibles = false;
            
            materias.forEach(function(materia) {
                const nivel = materia.dataset.nivel;
                const coincideNivel = !nivelSel || nivel === nivelSel;
                
                if (coincideNivel) {
                    materia.style.display = 'flex';
                    tieneMateriasVisibles = true;
                } else {
                    materia.style.display = 'none';
                }
            });
            
            if (tieneMateriasVisibles) {
                tarjeta.classList.remove('oculto');
            } else {
                tarjeta.classList.add('oculto');
            }
        });
    }

    buscadorDocente.addEventListener('input', aplicarFiltros);
    filtroNivel.addEventListener('change', aplicarFiltros);

    // Toggle modo edición
    function toggleEdicion(btn) {
        const card = btn.closest('.docente-card');
        const materiasBody = card.querySelector('.materias-body');
        
        if (materiasBody.classList.contains('modo-edicion')) {
            materiasBody.classList.remove('modo-edicion');
            btn.classList.remove('activo');
            btn.innerHTML = '✏️ Editar';
            
            // Resetear botones de eliminar
            card.querySelectorAll('.btn-eliminar-materia').forEach(function(b) {
                b.classList.remove('confirmar');
                b.textContent = 'X';
            });
        } else {
            materiasBody.classList.add('modo-edicion');
            btn.classList.add('activo');
            btn.innerHTML = '🗂️ Guardar';
        }
    }

    // Sistema de eliminación en dos pasos
    function prepararEliminar(btn) {
        if (btn.classList.contains('confirmar')) {
            // Segundo clic: eliminar
            eliminarAsignacion(btn);
        } else {
            // Primer clic: cambiar a modo confirmación
            // Resetear otros botones en modo confirmar
            document.querySelectorAll('.btn-eliminar-materia.confirmar').forEach(function(b) {
                b.classList.remove('confirmar');
                b.textContent = 'X';
            });
            
            btn.classList.add('confirmar');
            btn.textContent = '✔';
            
            // Auto-resetear después de 3 segundos si no confirma
            setTimeout(function() {
                if (btn.classList.contains('confirmar')) {
                    btn.classList.remove('confirmar');
                    btn.textContent = 'X';
                }
            }, 3000);
        }
    }

    function eliminarAsignacion(btn) {
        const idAsignacion = btn.dataset.id;
        const materiaItem = btn.closest('.materia-item');
        const docenteCard = btn.closest('.docente-card');
        const materiasBody = docenteCard.querySelector('.materias-body');
        
        // Animación de eliminación
        materiaItem.classList.add('eliminando');
        
        // Enviar petición AJAX
        fetch('php/coordinador/eliminar_asignacion_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id_asignacion=' + idAsignacion
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Esperar animación y eliminar del DOM
                setTimeout(function() {
                    materiaItem.remove();
                    
                    // Verificar si quedan materias
                    const materiasRestantes = materiasBody.querySelectorAll('.materia-item');
                    if (materiasRestantes.length === 0) {
                        docenteCard.classList.add('eliminando');
                        setTimeout(function() {
                            docenteCard.remove();
                            
                            // Verificar si quedan docentes
                            const docentesRestantes = document.querySelectorAll('.docente-card');
if (docentesRestantes.length === 0) {
    document.querySelector('.buscador-section').style.display = 'none';
    document.getElementById('listaDocentes').innerHTML = `
        <div class="empty-state">
            <div class="icon">📭</div>
            <h3>Sin asignaciones</h3>
            <p>Aún no hay asignaturas asignadas para este periodo.</p>
        </div>
    `;
}
                        }, 0);
                    } else {
                        // Actualizar contador de materias y horas
                        actualizarContadores(docenteCard);
                    }
                }, 300);
            } else {
                // Error: restaurar
                materiaItem.classList.remove('eliminando');
                btn.classList.remove('confirmar');
                btn.textContent = 'X';
                showToast('error', 'Error', 'No se pudo eliminar: ' + data.message);
            }
        })
        .catch(error => {
            materiaItem.classList.remove('eliminando');
            btn.classList.remove('confirmar');
            btn.textContent = 'X';
            showToast('error', 'Error de conexión', 'No se pudo conectar con el servidor');
        });
    }

    function actualizarContadores(docenteCard) {
        const materias = docenteCard.querySelectorAll('.materia-item');
        const totalMaterias = materias.length;
        
        let totalHoras = 0;
        materias.forEach(function(m) {
            const horasText = m.querySelector('.horas-badge').textContent;
            totalHoras += parseInt(horasText) || 0;
        });
        
        // Actualizar badge de materias
        const statBadge = docenteCard.querySelector('.stat-badge');
        statBadge.textContent = totalMaterias + ' materia' + (totalMaterias !== 1 ? 's' : '');
        
        // Actualizar total de horas
        const totalHorasDiv = docenteCard.querySelector('.total-horas');
        totalHorasDiv.textContent = 'Total: ' + totalHoras + ' horas';
    }
    function cambiarParalelo(badge) {
    const materiasBody = badge.closest('.materias-body');
    if (!materiasBody.classList.contains('modo-edicion')) return;
    
    const idAsignacion = badge.dataset.id;
    const paraleloActual = badge.dataset.paralelo;
    
    // Ciclo: A -> B -> AB -> A
    let nuevoParalelo;
    if (paraleloActual === 'A') {
        nuevoParalelo = 'B';
    } else if (paraleloActual === 'B') {
        nuevoParalelo = 'AB';
    } else {
        nuevoParalelo = 'A';
    }
    
    fetch('php/coordinador/cambiar_paralelo_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id_asignacion=' + idAsignacion + '&paralelo=' + nuevoParalelo
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            badge.dataset.paralelo = nuevoParalelo;
            badge.textContent = 'Paralelo ' + (nuevoParalelo === 'AB' ? 'A y B' : nuevoParalelo);
            badge.classList.remove('paralelo-a', 'paralelo-b', 'paralelo-ab');
            badge.classList.add('paralelo-' + nuevoParalelo.toLowerCase());
        } else {
            showToast('error', 'Error', data.message);
        }
    })
    .catch(error => {
        alert('Error de conexión');
    });
}
</script>
<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>
</body>
</html>
