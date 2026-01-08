<?php
session_start();

if (!isset($_SESSION['id_docente']) || $_SESSION['rol'] !== 'coordinador') {
    header('Location: login.php');
    exit();
}

require_once 'php/conexion.php';

$id_carrera = $_SESSION['id_carrera_coordina'];

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

// Estadísticas de PEAs
$sql_stats = "SELECT 
    COUNT(DISTINCT a.id_asignacion) as total_asignaciones,
    SUM(CASE WHEN p.id_pea IS NULL THEN 1 ELSE 0 END) as sin_pea,
    SUM(CASE WHEN p.estado = 'borrador' THEN 1 ELSE 0 END) as borradores,
    SUM(CASE WHEN p.estado = 'enviado' THEN 1 ELSE 0 END) as enviados,
    SUM(CASE WHEN p.estado = 'aprobado' THEN 1 ELSE 0 END) as aprobados,
    SUM(CASE WHEN p.estado = 'rechazado' THEN 1 ELSE 0 END) as rechazados
FROM asignacion a
INNER JOIN asignatura asig ON a.id_asignatura = asig.id_asignatura
LEFT JOIN pea p ON a.id_asignacion = p.id_asignacion
WHERE asig.id_carrera = ? AND a.id_periodo = ?";

$stmt = mysqli_prepare($cnx, $sql_stats);
mysqli_stmt_bind_param($stmt, "ii", $id_carrera, $periodo_activo['id_periodo']);
mysqli_stmt_execute($stmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Obtener todos los PEAs de la carrera
$sql_peas = "SELECT p.*, 
                    a.paralelo, a.jornada,
                    asig.nombre as asignatura, asig.codigo, asig.nivel,
                    d.nombres as docente_nombres, d.apellidos as docente_apellidos, d.email as docente_email
             FROM pea p
             INNER JOIN asignacion a ON p.id_asignacion = a.id_asignacion
             INNER JOIN asignatura asig ON a.id_asignatura = asig.id_asignatura
             INNER JOIN docente d ON a.id_docente = d.id_docente
             WHERE asig.id_carrera = ? AND a.id_periodo = ?
             ORDER BY 
                CASE p.estado 
                    WHEN 'enviado' THEN 1 
                    WHEN 'rechazado' THEN 2 
                    WHEN 'borrador' THEN 3 
                    WHEN 'aprobado' THEN 4 
                END,
                p.fecha_envio DESC, asig.nivel, asig.nombre";

$stmt = mysqli_prepare($cnx, $sql_peas);
mysqli_stmt_bind_param($stmt, "ii", $id_carrera, $periodo_activo['id_periodo']);
mysqli_stmt_execute($stmt);
$result_peas = mysqli_stmt_get_result($stmt);
$peas = [];
while ($row = mysqli_fetch_assoc($result_peas)) {
    $peas[] = $row;
}
mysqli_stmt_close($stmt);

// También obtener asignaciones sin PEA
$sql_sin_pea = "SELECT a.id_asignacion, a.paralelo, a.jornada,
                       asig.nombre as asignatura, asig.codigo, asig.nivel,
                       d.nombres as docente_nombres, d.apellidos as docente_apellidos
                FROM asignacion a
                INNER JOIN asignatura asig ON a.id_asignatura = asig.id_asignatura
                INNER JOIN docente d ON a.id_docente = d.id_docente
                LEFT JOIN pea p ON a.id_asignacion = p.id_asignacion
                WHERE asig.id_carrera = ? AND a.id_periodo = ? AND p.id_pea IS NULL
                ORDER BY asig.nivel, asig.nombre";

$stmt = mysqli_prepare($cnx, $sql_sin_pea);
mysqli_stmt_bind_param($stmt, "ii", $id_carrera, $periodo_activo['id_periodo']);
mysqli_stmt_execute($stmt);
$result_sin_pea = mysqli_stmt_get_result($stmt);
$asignaciones_sin_pea = [];
while ($row = mysqli_fetch_assoc($result_sin_pea)) {
    $asignaciones_sin_pea[] = $row;
}
mysqli_stmt_close($stmt);

function getEstadoBadge($estado) {
    switch ($estado) {
        case 'borrador': return 'badge-warning';
        case 'enviado': return 'badge-info';
        case 'aprobado': return 'badge-success';
        case 'rechazado': return 'badge-danger';
        default: return 'badge-secondary';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de PEAs - Sistema PEA</title>
    <link rel="stylesheet" href="css/coordinador.css">
     <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
    
        /* Estadísticas */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-box {
            background: white;
            padding: 12px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        .stat-box:hover {
            transform: translateY(-3px);
        }
        .stat-box h3 {
            font-size: 2rem;
            margin-bottom: 5px;
        }
        .stat-box p {
            color: #666;
            font-size: 0.85rem;
            margin: 0;
        }
        .stat-box.default h3 { color: #1e3a5f; }
        .stat-box.warning h3 { color: #d97706; }
        .stat-box.info h3 { color: #0891b2; }
        .stat-box.success h3 { color: #059669; }
        .stat-box.danger h3 { color: #dc2626; }
        
        /* Filtros */
        .filtros-section {
            background: rgba(242, 245, 249, 0.85);
            padding: 13px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .filtros-row {
            display: flex;
            gap: 15px;
            align-items: end;
        }
        .filtro-group label {
            display: block;
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .filtro-input, .filtro-select {
            width: 175px;
            padding: 3px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 0.83rem;
            transition: border-color 0.3s;
        }
        .filtro-input:focus, .filtro-select:focus {
            outline: none;
            border-color: #0081c9;
        }
        
        /* Tabs */
        .tabs-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .tabs-header {
            display: flex;
            border-bottom: 1px solid #e0e0e0;
            background: #f8f9fa;
        }
        .tab-btn {
            flex: 1;
            padding: 15px 20px;
            border: none;
            background: none;
            font-size: 0.9rem;
            font-weight: 500;
            color: #666;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .tab-btn:hover {
            background: #e9ecef;
        }
        .tab-btn.active {
            background: white;
            color: #1e3a5f;;
            border-bottom: 3px solid #1e3a5f;
        }
        .tab-btn .count {
            background: #1e3a5f;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            color: white;
        }
        .tab-btn.active .count {
            background: #1e3a5f;
            color: white;
        }
        .tabs-body {
            padding: 20px;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        
        /* Tarjetas de PEA */
        .pea-card {
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 15px;
            overflow: hidden;
            transition: box-shadow 0.3s;
        }
        .pea-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .pea-card-header {
            background: #f4f4f4ff;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        .pea-info h4 {
            margin: 0 0 5px 0;
            font-size: 1rem;
            color: #333;
        }
        .pea-info p {
            margin: 0;
            font-size: 0.85rem;
            color: #666;
        }
        .pea-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .badge-secondary { background: #e9ecef; color: #495057; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-primary { background: #1e3a5f; color: white; }
        
        .pea-card-body {
            background: #fbfbfbff;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .pea-meta {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
        }
        .meta-item {
            font-size: 0.85rem;
        }
        .meta-item label {
            color: #666;
            display: block;
            font-size: 0.75rem;
            margin-bottom: 2px;
        }
        .meta-item span {
            color: #333;
            font-weight: 500;
        }
        .pea-actions {
            display: flex;
            gap: 10px;
        }
        
        /* Botones */
        .btn {
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }
        .btn-primary { background: #0081c9; color: white; }
        .btn-primary:hover { background: #006ba1; }
        .btn-secondary { background: #e9ecef; color: #495057; }
        .btn-secondary:hover { background: #dee2e6; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-sm { padding: 8px 14px; font-size: 0.8rem; }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }
        .empty-state .icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .empty-state h4 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        /* Urgente badge */
        .pea-card.urgente {
            border-left: 4px solid #dc3545;
        }
        .pea-card.pendiente-revision {
            border-left: 4px solid #0891b2;
        }
        
        /* Contador */
        .resultados-count {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .filtros-row { grid-template-columns: 1fr; }
            .tabs-header { flex-wrap: wrap; }
            .tab-btn { flex: 1 1 45%; }
            .pea-card-header, .pea-card-body { flex-direction: column; align-items: flex-start; }
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
                    <li><a href="estado_peas.php" class="active">📊 Estado de PEAs</a></li>
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
                <h1>Estado de PEAs</h1>
                <p>Revisión y aprobación de Planes de Estudio de la carrera</p>
            </div>
            
            <div class="periodo-actual">
                <span class="label">Periodo Académico:</span>
                <span class="valor"><?php echo htmlspecialchars($periodo_activo['nombre'] ?? 'No definido'); ?></span>
            </div>
            
            <!-- Estadísticas -->
            <div class="stats-row">
                <div class="stat-box default">
                    <h3><?php echo $stats['total_asignaciones'] ?? 0; ?></h3>
                    <p>Total Asignaciones</p>
                </div>
                <div class="stat-box warning">
                    <h3><?php echo ($stats['sin_pea'] ?? 0) + ($stats['borradores'] ?? 0); ?></h3>
                    <p>Pendientes</p>
                </div>
                <div class="stat-box info">
                    <h3><?php echo $stats['enviados'] ?? 0; ?></h3>
                    <p>Por Revisar</p>
                </div>
                <div class="stat-box success">
                    <h3><?php echo $stats['aprobados'] ?? 0; ?></h3>
                    <p>Aprobados</p>
                </div>
                <div class="stat-box danger">
                    <h3><?php echo $stats['rechazados'] ?? 0; ?></h3>
                    <p>Rechazados</p>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="filtros-section">
                <div class="filtros-row">
                    <div class="filtro-group">
                        <label>🔎︎ Buscar</label>
                        <input type="text" id="filtroBuscar" class="filtro-input" placeholder="Asignatura, docente...">
                    </div>
                    <div class="filtro-group">
                        <label>▼ Nivel</label>
                        <select id="filtroNivel" class="filtro-select">
                            <option value="">Todos</option>
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                            <option value="<?php echo $i; ?>">Nivel <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="filtro-group">
                        <label>☰ Estado</label>
                        <select id="filtroEstado" class="filtro-select">
                            <option value="">Todos</option>
                            <option value="enviado">Por Revisar</option>
                            <option value="borrador">Borrador</option>
                            <option value="aprobado">Aprobado</option>
                            <option value="rechazado">Rechazado</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="tabs-container">
                <div class="tabs-header">
                    <button class="tab-btn active" data-tab="revision">
                        🗒 Por Revisar <span class="count"><?php echo $stats['enviados'] ?? 0; ?></span>
                    </button>
                    <button class="tab-btn" data-tab="pendientes">
                        ⏱ Pendientes <span class="count"><?php echo ($stats['sin_pea'] ?? 0) + ($stats['borradores'] ?? 0); ?></span>
                    </button>
                    <button class="tab-btn" data-tab="aprobados">
                        ✓ Aprobados <span class="count"><?php echo $stats['aprobados'] ?? 0; ?></span>
                    </button>
                    <button class="tab-btn" data-tab="rechazados">
                        ✘ Rechazados <span class="count"><?php echo $stats['rechazados'] ?? 0; ?></span>
                    </button>
                </div>
                
                <div class="tabs-body">
                    <!-- Tab: Por Revisar -->
                    <div class="tab-content active" id="tab-revision">
                        <?php 
                        $enviados = array_filter($peas, fn($p) => $p['estado'] === 'enviado');
                        if (empty($enviados)): 
                        ?>
                        <div class="empty-state">
                            <div class="icon">✔</div>
                            <h4>Sin PEAs pendientes de revisión</h4>
                            <p>Todos los PEAs enviados han sido revisados</p>
                        </div>
                        <?php else: ?>
                        <div class="resultados-count">
                            <?php echo count($enviados); ?> PEA(s) pendiente(s) de revisión
                        </div>
                        <?php foreach ($enviados as $p): ?>
                        <div class="pea-card pendiente-revision" 
                             data-buscar="<?php echo strtolower($p['asignatura'] . ' ' . $p['docente_nombres'] . ' ' . $p['docente_apellidos']); ?>"
                             data-nivel="<?php echo $p['nivel']; ?>"
                             data-estado="enviado">
                            <div class="pea-card-header">
                                <div class="pea-info">
                                    <h4><?php echo htmlspecialchars($p['asignatura']); ?></h4>
                                    <p>👤 <?php echo htmlspecialchars($p['docente_apellidos'] . ' ' . $p['docente_nombres']); ?></p>
                                </div>
                                <div class="pea-badges">
                                    <span class="badge badge-primary">Nivel <?php echo $p['nivel']; ?></span>
                                    <span class="badge badge-secondary">Paralelo <?php echo $p['paralelo']; ?></span>
                                    <span class="badge badge-info">Enviado</span>
                                </div>
                            </div>
                            <div class="pea-card-body">
                                <div class="pea-meta">
                                    <div class="meta-item">
                                        <label>Código</label>
                                        <span><?php echo htmlspecialchars($p['codigo']); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <label>Jornada</label>
                                        <span><?php echo ucfirst($p['jornada']); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <label>Fecha Envío</label>
                                        <span><?php echo $p['fecha_envio'] ? date('d/m/Y H:i', strtotime($p['fecha_envio'])) : '-'; ?></span>
                                    </div>
                                </div>
                                <div class="pea-actions">
                                    <a href="ver_pea.php?id_pea=<?php echo $p['id_pea']; ?>" class="btn btn-primary">
                                        Revisar PEA
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tab: Pendientes -->
                    <div class="tab-content" id="tab-pendientes">
                        <?php 
                        $pendientes = array_filter($peas, fn($p) => $p['estado'] === 'borrador');
                        $total_pendientes = count($pendientes) + count($asignaciones_sin_pea);
                        if ($total_pendientes === 0): 
                        ?>
                        <div class="empty-state">
                            <div class="icon">🎉</div>
                            <h4>Todos los docentes han iniciado sus PEAs</h4>
                            <p>No hay asignaturas pendientes</p>
                        </div>
                        <?php else: ?>
                        <div class="resultados-count">
                            <?php echo $total_pendientes; ?> asignatura(s) sin PEA completo
                        </div>
                        
                        <?php foreach ($asignaciones_sin_pea as $a): ?>
                        <div class="pea-card"
                             data-buscar="<?php echo strtolower($a['asignatura'] . ' ' . $a['docente_nombres'] . ' ' . $a['docente_apellidos']); ?>"
                             data-nivel="<?php echo $a['nivel']; ?>"
                             data-estado="sin_pea">
                            <div class="pea-card-header">
                                <div class="pea-info">
                                    <h4><?php echo htmlspecialchars($a['asignatura']); ?></h4>
                                    <p>👤 <?php echo htmlspecialchars($a['docente_apellidos'] . ' ' . $a['docente_nombres']); ?></p>
                                </div>
                                <div class="pea-badges">
                                    <span class="badge badge-primary">Nivel <?php echo $a['nivel']; ?></span>
                                    <span class="badge badge-secondary">Paralelo <?php echo $a['paralelo']; ?></span>
                                    <span class="badge badge-warning">Sin PEA</span>
                                </div>
                            </div>
                            <div class="pea-card-body">
                                <div class="pea-meta">
                                    <div class="meta-item">
                                        <label>Código</label>
                                        <span><?php echo htmlspecialchars($a['codigo']); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <label>Jornada</label>
                                        <span><?php echo ucfirst($a['jornada']); ?></span>
                                    </div>
                                </div>
                                <div class="pea-actions">
                                    <span class="btn btn-secondary btn-sm" style="cursor: default;">⏳ Esperando inicio</span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php foreach ($pendientes as $p): ?>
                        <div class="pea-card"
                             data-buscar="<?php echo strtolower($p['asignatura'] . ' ' . $p['docente_nombres'] . ' ' . $p['docente_apellidos']); ?>"
                             data-nivel="<?php echo $p['nivel']; ?>"
                             data-estado="borrador">
                            <div class="pea-card-header">
                                <div class="pea-info">
                                    <h4><?php echo htmlspecialchars($p['asignatura']); ?></h4>
                                    <p>👤 <?php echo htmlspecialchars($p['docente_apellidos'] . ' ' . $p['docente_nombres']); ?></p>
                                </div>
                                <div class="pea-badges">
                                    <span class="badge badge-primary">Nivel <?php echo $p['nivel']; ?></span>
                                    <span class="badge badge-secondary">Paralelo <?php echo $p['paralelo']; ?></span>
                                    <span class="badge badge-warning">Borrador</span>
                                </div>
                            </div>
                            <div class="pea-card-body">
                                <div class="pea-meta">
                                    <div class="meta-item">
                                        <label>Código</label>
                                        <span><?php echo htmlspecialchars($p['codigo']); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <label>Creado</label>
                                        <span><?php echo $p['fecha_creacion'] ? date('d/m/Y', strtotime($p['fecha_creacion'])) : '-'; ?></span>
                                    </div>
                                </div>
                                <div class="pea-actions">
                                    <a href="ver_pea.php?id_pea=<?php echo $p['id_pea']; ?>" class="btn btn-secondary btn-sm">
                                        👁️ Ver Avance
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tab: Aprobados -->
                    <div class="tab-content" id="tab-aprobados">
                        <?php 
                        $aprobados = array_filter($peas, fn($p) => $p['estado'] === 'aprobado');
                        if (empty($aprobados)): 
                        ?>
                        <div class="empty-state">
                            <div class="icon">📋</div>
                            <h4>Sin PEAs aprobados</h4>
                            <p>Aún no se han aprobado PEAs en este periodo</p>
                        </div>
                        <?php else: ?>
                        <div class="resultados-count">
                            <?php echo count($aprobados); ?> PEA(s) aprobado(s)
                        </div>
                        <?php foreach ($aprobados as $p): ?>
                        <div class="pea-card"
                             data-buscar="<?php echo strtolower($p['asignatura'] . ' ' . $p['docente_nombres'] . ' ' . $p['docente_apellidos']); ?>"
                             data-nivel="<?php echo $p['nivel']; ?>"
                             data-estado="aprobado">
                            <div class="pea-card-header">
                                <div class="pea-info">
                                    <h4><?php echo htmlspecialchars($p['asignatura']); ?></h4>
                                    <p>👤 <?php echo htmlspecialchars($p['docente_apellidos'] . ' ' . $p['docente_nombres']); ?></p>
                                </div>
                                <div class="pea-badges">
                                    <span class="badge badge-primary">Nivel <?php echo $p['nivel']; ?></span>
                                    <span class="badge badge-secondary">Paralelo <?php echo $p['paralelo']; ?></span>
                                    <span class="badge badge-success">Aprobado</span>
                                </div>
                            </div>
                            <div class="pea-card-body">
                                <div class="pea-meta">
                                    <div class="meta-item">
                                        <label>Código</label>
                                        <span><?php echo htmlspecialchars($p['codigo']); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <label>Aprobado</label>
                                        <span><?php echo $p['fecha_aprobacion'] ? date('d/m/Y', strtotime($p['fecha_aprobacion'])) : '-'; ?></span>
                                    </div>
                                </div>
                                <div class="pea-actions">
                                    <a href="ver_pea.php?id_pea=<?php echo $p['id_pea']; ?>" class="btn btn-secondary btn-sm">
                                        👁️ Ver
                                    </a>
                                    <a href="php/pea/generar_word.php?id_pea=<?php echo $p['id_pea']; ?>" class="btn btn-success btn-sm">
                                        📄 Descargar
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tab: Rechazados -->
                    <div class="tab-content" id="tab-rechazados">
                        <?php 
                        $rechazados = array_filter($peas, fn($p) => $p['estado'] === 'rechazado');
                        if (empty($rechazados)): 
                        ?>
                        <div class="empty-state">
                            <div class="icon">✔</div>
                            <h4>Sin PEAs rechazados</h4>
                            <p>No hay PEAs rechazados en este periodo</p>
                        </div>
                        <?php else: ?>
                        <div class="resultados-count">
                            <?php echo count($rechazados); ?> PEA(s) rechazado(s) - esperando correcciones
                        </div>
                        <?php foreach ($rechazados as $p): ?>
                        <div class="pea-card urgente"
                             data-buscar="<?php echo strtolower($p['asignatura'] . ' ' . $p['docente_nombres'] . ' ' . $p['docente_apellidos']); ?>"
                             data-nivel="<?php echo $p['nivel']; ?>"
                             data-estado="rechazado">
                            <div class="pea-card-header">
                                <div class="pea-info">
                                    <h4><?php echo htmlspecialchars($p['asignatura']); ?></h4>
                                    <p>👤 <?php echo htmlspecialchars($p['docente_apellidos'] . ' ' . $p['docente_nombres']); ?></p>
                                </div>
                                <div class="pea-badges">
                                    <span class="badge badge-primary">Nivel <?php echo $p['nivel']; ?></span>
                                    <span class="badge badge-secondary">Paralelo <?php echo $p['paralelo']; ?></span>
                                    <span class="badge badge-danger">Rechazado</span>
                                </div>
                            </div>
                            <div class="pea-card-body">
                                <div class="pea-meta">
                                    <div class="meta-item">
                                        <label>Rechazado</label>
                                        <span><?php echo $p['fecha_rechazo'] ? date('d/m/Y', strtotime($p['fecha_rechazo'])) : '-'; ?></span>
                                    </div>
                                    <?php if ($p['observaciones_rechazo']): ?>
                                    <div class="meta-item" style="flex: 2;">
                                        <label>Observaciones</label>
                                        <span style="color: #dc3545;"><?php echo htmlspecialchars(substr($p['observaciones_rechazo'], 0, 100)); ?><?php echo strlen($p['observaciones_rechazo']) > 100 ? '...' : ''; ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="pea-actions">
                                    <a href="ver_pea.php?id_pea=<?php echo $p['id_pea']; ?>" class="btn btn-secondary btn-sm">
                                        👁️ Ver Detalles
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Tabs
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tabId = this.dataset.tab;
                
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                this.classList.add('active');
                document.getElementById('tab-' + tabId).classList.add('active');
            });
        });
        
        // Filtros
        const filtroBuscar = document.getElementById('filtroBuscar');
        const filtroNivel = document.getElementById('filtroNivel');
        const filtroEstado = document.getElementById('filtroEstado');
        
        function aplicarFiltros() {
            const termino = filtroBuscar.value.toLowerCase().trim();
            const nivel = filtroNivel.value;
            const estado = filtroEstado.value;
            
            document.querySelectorAll('.pea-card').forEach(card => {
                const cardBuscar = card.dataset.buscar || '';
                const cardNivel = card.dataset.nivel;
                const cardEstado = card.dataset.estado;
                
                let mostrar = true;
                
                if (termino && !cardBuscar.includes(termino)) {
                    mostrar = false;
                }
                if (nivel && cardNivel !== nivel) {
                    mostrar = false;
                }
                if (estado && cardEstado !== estado) {
                    mostrar = false;
                }
                
                card.style.display = mostrar ? 'block' : 'none';
            });
        }
        
        filtroBuscar.addEventListener('input', aplicarFiltros);
        filtroNivel.addEventListener('change', aplicarFiltros);
        filtroEstado.addEventListener('change', aplicarFiltros);
    </script>
</body>
</html>
