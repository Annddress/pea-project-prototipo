<?php
/**
 * DASHBOARD DOCENTE
 * Sistema PEA - Instituto Superior Tecnológico Tena
 * 
 * Panel principal para docentes con:
 * - Resumen de PEAs por estado
 * - Asignaturas pendientes de crear PEA
 * - PEAs que requieren atención
 * - Accesos rápidos
 */

session_start();

if (!isset($_SESSION['id_docente'])) {
    header('Location: login.php');
    exit();
}

// Si es coordinador, redirigir a su panel
if ($_SESSION['rol'] === 'coordinador') {
    header('Location: indexcordinador.php');
    exit();
}

require_once 'php/conexion.php';

$id_docente = $_SESSION['id_docente'];
$nombre_completo = $_SESSION['nombre_completo'];

// =====================================================
// OBTENER ESTADÍSTICAS DE PEAs
// =====================================================

// Contar PEAs por estado
$sql_stats = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN p.estado = 'borrador' THEN 1 ELSE 0 END) as borradores,
    SUM(CASE WHEN p.estado = 'enviado' THEN 1 ELSE 0 END) as enviados,
    SUM(CASE WHEN p.estado = 'aprobado' THEN 1 ELSE 0 END) as aprobados,
    SUM(CASE WHEN p.estado = 'rechazado' THEN 1 ELSE 0 END) as rechazados
FROM pea p
INNER JOIN asignacion a ON p.id_asignacion = a.id_asignacion
WHERE a.id_docente = ?";

$stmt = mysqli_prepare($cnx, $sql_stats);
mysqli_stmt_bind_param($stmt, "i", $id_docente);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

$total_peas = $stats['total'] ?? 0;
$peas_borrador = $stats['borradores'] ?? 0;
$peas_enviados = $stats['enviados'] ?? 0;
$peas_aprobados = $stats['aprobados'] ?? 0;
$peas_rechazados = $stats['rechazados'] ?? 0;

// =====================================================
// ASIGNATURAS SIN PEA (Pendientes de crear)
// =====================================================

$sql_pendientes = "SELECT a.id_asignacion, a.paralelo, a.jornada,
                          asig.nombre as asignatura, asig.codigo, asig.nivel,
                          c.nombre as carrera,
                          per.nombre as periodo
                   FROM asignacion a
                   INNER JOIN asignatura asig ON a.id_asignatura = asig.id_asignatura
                   INNER JOIN carrera c ON asig.id_carrera = c.id_carrera
                   INNER JOIN periodo_academico per ON a.id_periodo = per.id_periodo
                   LEFT JOIN pea p ON a.id_asignacion = p.id_asignacion
                   WHERE a.id_docente = ? AND a.estado = 'activa' AND p.id_pea IS NULL
                   ORDER BY asig.nivel, asig.nombre";

$stmt = mysqli_prepare($cnx, $sql_pendientes);
mysqli_stmt_bind_param($stmt, "i", $id_docente);
mysqli_stmt_execute($stmt);
$result_pendientes = mysqli_stmt_get_result($stmt);
$asignaturas_pendientes = [];
while ($row = mysqli_fetch_assoc($result_pendientes)) {
    $asignaturas_pendientes[] = $row;
}
mysqli_stmt_close($stmt);

// =====================================================
// PEAs QUE REQUIEREN ATENCIÓN (Rechazados o Borradores)
// =====================================================

$sql_atencion = "SELECT p.id_pea, p.estado, p.observaciones_rechazo, p.fecha_modificacion,
                        asig.nombre as asignatura, asig.codigo, asig.nivel,
                        a.paralelo, a.jornada,
                        c.nombre as carrera
                 FROM pea p
                 INNER JOIN asignacion a ON p.id_asignacion = a.id_asignacion
                 INNER JOIN asignatura asig ON a.id_asignatura = asig.id_asignatura
                 INNER JOIN carrera c ON asig.id_carrera = c.id_carrera
                 WHERE a.id_docente = ? AND p.estado IN ('borrador', 'rechazado')
                 ORDER BY p.estado DESC, p.fecha_modificacion DESC
                 LIMIT 5";

$stmt = mysqli_prepare($cnx, $sql_atencion);
mysqli_stmt_bind_param($stmt, "i", $id_docente);
mysqli_stmt_execute($stmt);
$result_atencion = mysqli_stmt_get_result($stmt);
$peas_atencion = [];
while ($row = mysqli_fetch_assoc($result_atencion)) {
    $peas_atencion[] = $row;
}
mysqli_stmt_close($stmt);

// =====================================================
// PEAs RECIENTES (últimos modificados)
// =====================================================

$sql_recientes = "SELECT p.id_pea, p.estado, p.fecha_modificacion,
                         asig.nombre as asignatura, asig.codigo,
                         a.paralelo
                  FROM pea p
                  INNER JOIN asignacion a ON p.id_asignacion = a.id_asignacion
                  INNER JOIN asignatura asig ON a.id_asignatura = asig.id_asignatura
                  WHERE a.id_docente = ?
                  ORDER BY p.fecha_modificacion DESC
                  LIMIT 5";

$stmt = mysqli_prepare($cnx, $sql_recientes);
mysqli_stmt_bind_param($stmt, "i", $id_docente);
mysqli_stmt_execute($stmt);
$result_recientes = mysqli_stmt_get_result($stmt);
$peas_recientes = [];
while ($row = mysqli_fetch_assoc($result_recientes)) {
    $peas_recientes[] = $row;
}
mysqli_stmt_close($stmt);

// =====================================================
// TOTAL DE ASIGNACIONES
// =====================================================

$sql_total_asig = "SELECT COUNT(*) as total FROM asignacion WHERE id_docente = ? AND estado = 'activa'";
$stmt = mysqli_prepare($cnx, $sql_total_asig);
mysqli_stmt_bind_param($stmt, "i", $id_docente);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$total_asignaciones = mysqli_fetch_assoc($result)['total'] ?? 0;
mysqli_stmt_close($stmt);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Docente - Sistema PEA</title>
    <link rel="stylesheet" href="css/coordinador.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        /* Bienvenida */
        .welcome-section {
    background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
    border-radius: 13px;
    padding: 23px;
    color: white;
    margin-bottom: 25px;
    position: relative;
    overflow: hidden;
        }
        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        .welcome-section h1 {
            margin: 0 0 8px 0;
            font-size: 1.50rem;
            font-weight: 505;
        }
        .welcome-section p {
            margin: 0;
            opacity: 0.8;
            font-size: 0.9148rem;
        }
        .welcome-date {
            margin-top: 15px;
            font-size: 0.82rem;
            opacity: 0.8;
            font-weight: 90;
        }
        
        /* Tarjetas de estadísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .stat-icon.blue { background: #e0f2fe; color: #0284c7; }
        .stat-icon.yellow { background: #fef3c7; color: #d97706; }
        .stat-icon.green { background: #dcfce7; color: #16a34a; }
        .stat-icon.red { background: #fee2e2; color: #dc2626; }
        .stat-icon.purple { background: #f3e8ff; color: #9333ea; }
        .stat-info h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
        }
        .stat-info p {
            margin: 2px 0 0 0;
            font-size: 0.8rem;
            color: #6b7280;
        }
        
        /* Secciones */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .section-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .section-header {
            padding: 18px 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .section-header h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-header .badge {
            background: #fee2e2;
            color: #dc2626;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .section-header .badge.warning {
            background: #fef3c7;
            color: #d97706;
        }
        .section-header a {
            font-size: 0.8rem;
            color: #1e3a5f;
            text-decoration: none;
        }
        .section-header a:hover {
            text-decoration: underline;
        }
        .section-body {
            padding: 15px 20px;
        }
        
        /* Lista de items */
        .item-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .item-list li {
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .item-list li:last-child {
            border-bottom: none;
        }
        .item-info {
            flex: 1;
        }
        .item-info h4 {
            margin: 0 0 3px 0;
            font-size: 0.9rem;
            font-weight: 500;
            color: #1f2937;
        }
        .item-info p {
            margin: 0;
            font-size: 0.75rem;
            color: #6b7280;
        }
        .item-action .btn {
            padding: 6px 12px;
            font-size: 0.75rem;
        }
        
        /* Estado badges */
        .estado-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        .estado-borrador { background: #e5e7eb; color: #374151; }
        .estado-enviado { background: #dbeafe; color: #1d4ed8; }
        .estado-aprobado { background: #dcfce7; color: #166534; }
        .estado-rechazado { background: #fee2e2; color: #dc2626; }
        
        /* Botones */
        .btn {
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #1e3a5f;
            color: white;
        }
        .btn-primary:hover {
            background: #152a45;
        }
        .btn-success {
            background: #059669;
            color: white;
        }
        .btn-success:hover {
            background: #047857;
        }
        .btn-warning {
            background: #d97706;
            color: white;
        }
        .btn-warning:hover {
            background: #b45309;
        }
        .btn-outline {
            background: transparent;
            border: 1px solid #d1d5db;
            color: #374151;
        }
        .btn-outline:hover {
            background: #f3f4f6;
        }
        
        /* Acciones rápidas */
        .quick-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .quick-action-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s;
            flex: 1;
            min-width: 200px;
        }
        .quick-action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }
        .quick-action-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }
        .quick-action-icon.primary { background: #1e3a5f; color: white; }
        .quick-action-icon.success { background: #059669; color: white; }
        .quick-action-icon.info { background: #1e3a5f; color: white; }
        .quick-action-text h4 {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 600;
            color: #1f2937;
        }
        .quick-action-text p {
            margin: 2px 0 0 0;
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: #6b7280;
        }
        .empty-state .icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        .empty-state p {
            margin: 0;
            font-size: 0.85rem;
        }
        .icon {
            color: #380b31ff;
            font-weight: 600;
        }
        
        /* Alerta de rechazados */
        .alert-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-box.warning {
            background: #fffbeb;
            border-color: #fde68a;
        }
        .alert-box .alert-icon {
            font-size: 1.5rem;
        }
        .alert-box .alert-content h4 {
            margin: 0 0 3px 0;
            font-size: 0.9rem;
            font-weight: 600;
            color: #dc2626;
        }
        .alert-box.warning .alert-content h4 {
            color: #d97706;
        }
        .alert-box .alert-content p {
            margin: 0;
            font-size: 0.8rem;
            color: #7f1d1d;
        }
        .alert-box.warning .alert-content p {
            color: #92400e;
        }
        .alert-box .btn {
            margin-left: auto;
        }
        
        /* Full width section */
        .full-width {
            grid-column: 1 / -1;
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
                <span class="badge-docente">Docente</span>
                <span class="user-name"><?php echo htmlspecialchars($nombre_completo); ?></span>
            </div>
        </div>
    </header>
    
    <div class="contenedor">
        <div class="menulateral">
            <nav>
                <ul>
                    <li><a href="indexusuario.php" class="active">🏠 Inicio</a></li>
                    <li><a href="mis_peas.php">📝 Mis PEAs</a></li>
                    <li><a href="perfilusuario.php">👤 Mi Perfil</a></li>
                    <li>
                        <form action="php/configuracion/cerrarsecion.php" method="POST" style="margin:0;">
                            <button type="submit" class="btn-salir">➜] Cerrar Sesión</button>
                        </form>
                    </li>
                </ul>
            </nav>
        </div>
        
        <div class="contenido-principal">
            <!-- Sección de Bienvenida -->
            <div class="welcome-section">
                <h1>¡Bienvenido, <?php echo htmlspecialchars(explode(' ', $_SESSION['nombres'])[0]); ?>!</h1>
                <p>Panel de gestión de Planes de Estudio de Asignatura</p>
                <div class="welcome-date">
                    🗒 <?php echo strftime('%A, %d de %B de %Y', strtotime('today')); ?>
                </div>
            </div>
            
            <?php if ($peas_rechazados > 0): ?>
            <!-- Alerta de PEAs Rechazados -->
            <div class="alert-box">
                <span class="alert-icon">⚠️</span>
                <div class="alert-content">
                    <h4>Tienes <?php echo $peas_rechazados; ?> PEA<?php echo $peas_rechazados > 1 ? 's' : ''; ?> rechazado<?php echo $peas_rechazados > 1 ? 's' : ''; ?></h4>
                    <p>Revisa las observaciones del coordinador y realiza las correcciones necesarias.</p>
                </div>
                <a href="mis_peas.php?filtro=rechazado" class="btn btn-warning">Ver PEAs Rechazados</a>
            </div>
            <?php endif; ?>
            
            <?php if (count($asignaturas_pendientes) > 0): ?>
            <!-- Alerta de Asignaturas sin PEA -->
            <div class="alert-box warning">
                <span class="alert-icon">📋</span>
                <div class="alert-content">
                    <h4>Tienes <?php echo count($asignaturas_pendientes); ?> asignatura<?php echo count($asignaturas_pendientes) > 1 ? 's' : ''; ?> sin PEA</h4>
                    <p>El coordinador te ha asignado materias que requieren la creación de su Plan de Estudio.</p>
                </div>
                <a href="#pendientes" class="btn btn-outline">Ver Pendientes ↓</a>
            </div>
            <?php endif; ?>
            
            <!-- Tarjetas de Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon purple">📚</div>
                    <div class="stat-info">
                        <h3><?php echo $total_asignaciones; ?></h3>
                        <p>Asignaturas Asignadas</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon blue">📝</div>
                    <div class="stat-info">
                        <h3><?php echo $peas_borrador; ?></h3>
                        <p>PEAs en Borrador</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon yellow">📤</div>
                    <div class="stat-info">
                        <h3><?php echo $peas_enviados; ?></h3>
                        <p>PEAs Enviados</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">✅</div>
                    <div class="stat-info">
                        <h3><?php echo $peas_aprobados; ?></h3>
                        <p>PEAs Aprobados</p>
                    </div>
                </div>
            </div>
            
            <!-- Acciones Rápidas -->
            <div class="quick-actions">
                <a href="mis_peas.php" class="quick-action-card">
                    <div class="quick-action-icon primary">🕮</div>
                    <div class="quick-action-text">
                        <h4>Mis PEAs</h4>
                        <p>Ver todos mis planes de estudio</p>
                    </div>
                </a>
                <?php if (count($asignaturas_pendientes) > 0): ?>
                <a href="crear_pea.php?id_asignacion=<?php echo $asignaturas_pendientes[0]['id_asignacion']; ?>" class="quick-action-card">
                    <div class="quick-action-icon success">➕</div>
                    <div class="quick-action-text">
                        <h4>Crear Nuevo PEA</h4>
                        <p>Iniciar un plan de estudio</p>
                    </div>
                </a>
                <?php endif; ?>
                <a href="perfilusuario.php" class="quick-action-card">
                    <div class="quick-action-icon info">𖨆</div>
                    <div class="quick-action-text">
                        <h4>Mi Perfil</h4>
                        <p>Actualizar mis datos</p>
                    </div>
                </a>
            </div>
            
            <!-- Grid Principal -->
            <div class="dashboard-grid">
                <!-- Asignaturas Pendientes de Crear PEA -->
                <div class="section-card" id="pendientes">
                    <div class="section-header">
                        <h2>
                            🗒 Asignaturas sin PEA
                            <?php if (count($asignaturas_pendientes) > 0): ?>
                            <span class="badge warning"><?php echo count($asignaturas_pendientes); ?></span>
                            <?php endif; ?>
                        </h2>
                    </div>
                    <div class="section-body">
                        <?php if (count($asignaturas_pendientes) > 0): ?>
                        <ul class="item-list">
                            <?php foreach ($asignaturas_pendientes as $asig): ?>
                            <li>
                                <div class="item-info">
                                    <h4><?php echo htmlspecialchars($asig['asignatura']); ?></h4>
                                    <p>
                                        <?php echo htmlspecialchars($asig['codigo']); ?> • 
                                        Nivel <?php echo $asig['nivel']; ?> • 
                                        Paralelo <?php echo $asig['paralelo']; ?> •
                                        <?php echo ucfirst($asig['jornada']); ?>
                                    </p>
                                </div>
                                <div class="item-action">
                                    <a href="crear_pea.php?id_asignacion=<?php echo $asig['id_asignacion']; ?>" class="btn btn-success">
                                        + Crear PEA
                                    </a>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <div class="empty-state">
                            <div class="icon">✓</div>
                            <p>¡Excelente! No tienes PEAs pendientes.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- PEAs que Requieren Atención -->
                <div class="section-card">
                    <div class="section-header">
                        <h2>
                            ！Requieren Atención
                            <?php if (count($peas_atencion) > 0): ?>
                            <span class="badge"><?php echo count($peas_atencion); ?></span>
                            <?php endif; ?>
                        </h2>
                        <a href="mis_peas.php">Ver todos →</a>
                    </div>
                    <div class="section-body">
                        <?php if (count($peas_atencion) > 0): ?>
                        <ul class="item-list">
                            <?php foreach ($peas_atencion as $pea): ?>
                            <li>
                                <div class="item-info">
                                    <h4><?php echo htmlspecialchars($pea['asignatura']); ?></h4>
                                    <p>
                                        <?php echo htmlspecialchars($pea['codigo']); ?> • 
                                        Paralelo <?php echo $pea['paralelo']; ?>
                                        <?php if ($pea['estado'] === 'rechazado' && !empty($pea['observaciones_rechazo'])): ?>
                                        <br><span style="color: #dc2626;">📌 <?php echo htmlspecialchars(substr($pea['observaciones_rechazo'], 0, 50)); ?>...</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="item-action">
                                    <span class="estado-badge estado-<?php echo $pea['estado']; ?>">
                                        <?php echo ucfirst($pea['estado']); ?>
                                    </span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <div class="empty-state">
                            <div class="icon">🕭</div>
                            <p>No tienes PEAs pendientes de atención.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- PEAs Recientes -->
                <div class="section-card full-width">
                    <div class="section-header">
                        <h2>⏲ PEAs Recientes</h2>
                        <a href="mis_peas.php">Ver todos →</a>
                    </div>
                    <div class="section-body">
                        <?php if (count($peas_recientes) > 0): ?>
                        <ul class="item-list">
                            <?php foreach ($peas_recientes as $pea): ?>
                            <li>
                                <div class="item-info">
                                    <h4><?php echo htmlspecialchars($pea['asignatura']); ?></h4>
                                    <p>
                                        <?php echo htmlspecialchars($pea['codigo']); ?> • 
                                        Paralelo <?php echo $pea['paralelo']; ?> •
                                        Modificado: <?php echo date('d/m/Y H:i', strtotime($pea['fecha_modificacion'])); ?>
                                    </p>
                                </div>
                                <div class="item-action" style="display: flex; gap: 10px; align-items: center;">
                                    <span class="estado-badge estado-<?php echo $pea['estado']; ?>">
                                        <?php echo ucfirst($pea['estado']); ?>
                                    </span>
                                    <?php if ($pea['estado'] === 'borrador' || $pea['estado'] === 'rechazado'): ?>
                                    <a href="distribucion_semanas.php?id_pea=<?php echo $pea['id_pea']; ?>" class="btn btn-outline">
                                        ✏️ Editar
                                    </a>
                                    <?php else: ?>
                                    <a href="ver_pea.php?id=<?php echo $pea['id_pea']; ?>" class="btn btn-outline">
                                        👁️ Ver
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <div class="empty-state">
                            <div class="icon">📄</div>
                            <p>Aún no has creado ningún PEA. ¡Comienza creando uno!</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Información de Ayuda -->
            <div class="section-card" style="margin-top: 25px;">
                <div class="section-header">
                    <h2>💡 ¿Cómo funciona?</h2>
                </div>
                <div class="section-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <div style="text-align: center; padding: 15px;">
                            <div style="font-size: 2rem; margin-bottom: 10px; color: #234b95ff;">❶</div>
                            <h4 style="margin: 0 0 5px 0; font-size: 0.9rem;">Crear PEA</h4>
                            <p style="margin: 0; font-size: 0.8rem; color: #6b7280;">Completa los datos generales de tu asignatura</p>
                        </div>
                        <div style="text-align: center; padding: 15px;">
                            <div style="font-size: 2rem; margin-bottom: 10px; color: #234b95ff;">❷</div>
                            <h4 style="margin: 0 0 5px 0; font-size: 0.9rem;">Distribución Semanal</h4>
                            <p style="margin: 0; font-size: 0.8rem; color: #6b7280;">Detalla las 16 semanas del período</p>
                        </div>
                        <div style="text-align: center; padding: 15px;">
                            <div style="font-size: 2rem; margin-bottom: 10px; color: #234b95ff;">❸</div>
                            <h4 style="margin: 0 0 5px 0; font-size: 0.9rem;">Enviar a Revisión</h4>
                            <p style="margin: 0; font-size: 0.8rem; color: #6b7280;">El coordinador revisará tu PEA</p>
                        </div>
                        <div style="text-align: center; padding: 15px;">
                            <div style="font-size: 2rem; margin-bottom: 10px; color: #234b95ff;">❹</div>
                            <h4 style="margin: 0 0 5px 0; font-size: 0.9rem;">Aprobación</h4>
                            <p style="margin: 0; font-size: 0.8rem; color: #6b7280;">Descarga tu PEA en formato Word</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Configurar locale para fechas en español
        <?php setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'Spanish_Spain'); ?>
    </script>
</body>
</html>
