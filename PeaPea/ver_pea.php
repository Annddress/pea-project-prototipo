<?php
session_start();

if (!isset($_SESSION['id_docente'])) {
    header('Location: login.php');
    exit();
}

require_once 'php/conexion.php';

$id_docente = $_SESSION['id_docente'];
$id_pea = isset($_GET['id_pea']) ? intval($_GET['id_pea']) : 0;
$es_coordinador = ($_SESSION['rol'] === 'coordinador');

if (!$id_pea) {
    $_SESSION['mensaje_error'] = "ID de PEA no especificado.";
    header('Location: mis_peas.php');
    exit();
}

// Verificar permisos: el docente dueño o el coordinador de la carrera pueden ver
$sql_pea = "SELECT p.*, 
                   a.paralelo, a.jornada, a.id_docente as docente_asignado,
                   asig.nombre as asignatura, asig.codigo, asig.nivel, asig.total_horas,
                   asig.descripcion as desc_asignatura, asig.objetivo as obj_asignatura,
                   c.id_carrera, c.nombre as carrera,
                   per.nombre as periodo_nombre, per.num_semanas,
                   d.nombres as docente_nombres, d.apellidos as docente_apellidos,
                   d.email as docente_email, d.celular as docente_celular, d.titulo as docente_titulo
            FROM pea p
            INNER JOIN asignacion a ON p.id_asignacion = a.id_asignacion
            INNER JOIN asignatura asig ON a.id_asignatura = asig.id_asignatura
            INNER JOIN carrera c ON asig.id_carrera = c.id_carrera
            INNER JOIN periodo_academico per ON a.id_periodo = per.id_periodo
            INNER JOIN docente d ON a.id_docente = d.id_docente
            WHERE p.id_pea = ?";

$stmt = mysqli_prepare($cnx, $sql_pea);
mysqli_stmt_bind_param($stmt, "i", $id_pea);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pea = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$pea) {
    $_SESSION['mensaje_error'] = "PEA no encontrado.";
    header('Location: mis_peas.php');
    exit();
}

// Verificar permisos
$es_dueno = ($pea['docente_asignado'] == $id_docente);
$es_coord_carrera = ($es_coordinador && isset($_SESSION['id_carrera_coordina']) && $_SESSION['id_carrera_coordina'] == $pea['id_carrera']);

if (!$es_dueno && !$es_coord_carrera) {
    $_SESSION['mensaje_error'] = "No tienes permiso para ver este PEA.";
    header('Location: mis_peas.php');
    exit();
}

$num_semanas = $pea['num_semanas'] ?? 16;

// Obtener unidades
$sql_unidades = "SELECT * FROM unidades_pea WHERE id_pea = ? ORDER BY numero";
$stmt = mysqli_prepare($cnx, $sql_unidades);
mysqli_stmt_bind_param($stmt, "i", $id_pea);
mysqli_stmt_execute($stmt);
$result_unidades = mysqli_stmt_get_result($stmt);
$unidades = [];
while ($u = mysqli_fetch_assoc($result_unidades)) {
    $unidades[$u['id_unidad']] = $u;
}
mysqli_stmt_close($stmt);

// Obtener semanas
$sql_semanas = "SELECT * FROM semanas_pea WHERE id_pea = ? ORDER BY numero_semana";
$stmt = mysqli_prepare($cnx, $sql_semanas);
mysqli_stmt_bind_param($stmt, "i", $id_pea);
mysqli_stmt_execute($stmt);
$result_semanas = mysqli_stmt_get_result($stmt);
$semanas = [];
while ($s = mysqli_fetch_assoc($result_semanas)) {
    $semanas[$s['numero_semana']] = $s;
}
mysqli_stmt_close($stmt);

// Obtener actividades
$sql_actividades = "SELECT a.* FROM actividades_pea a
                    INNER JOIN semanas_pea s ON a.id_semana = s.id_semana
                    WHERE s.id_pea = ?
                    ORDER BY s.numero_semana, a.tipo, a.numero";
$stmt = mysqli_prepare($cnx, $sql_actividades);
mysqli_stmt_bind_param($stmt, "i", $id_pea);
mysqli_stmt_execute($stmt);
$result_act = mysqli_stmt_get_result($stmt);
$actividades = [];
while ($act = mysqli_fetch_assoc($result_act)) {
    $actividades[$act['id_semana']][] = $act;
}
mysqli_stmt_close($stmt);

// Carrera del coordinador
if ($es_coordinador) {
    $sql_carrera = "SELECT nombre FROM carrera WHERE id_carrera = ?";
    $stmt = mysqli_prepare($cnx, $sql_carrera);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['id_carrera_coordina']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $carrera_coord = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// Función para obtener clase de badge según estado
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
    <title>Ver PEA - <?php echo htmlspecialchars($pea['asignatura']); ?></title>
    <link rel="stylesheet" href="css/coordinador.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        /* Contenedor principal */
        .pea-view-container {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 11px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .pea-view-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2d4a6f 100%);
            color: white;
            padding: 25px;
        }
        .pea-view-header h2 {
            margin: 0 0 8px 0;
            font-size: 1.0rem;
        }
        .pea-view-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.85rem;
        }
        .pea-header-meta {
            display: flex;
            gap: 12px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .pea-header-meta span {
            background: rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.80rem;
        }
        
        /* Acciones del coordinador */
        .coordinator-actions {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 11px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .coordinator-actions h4 {
            margin: 0;
            color: #856404;
            font-size: 1rem;
        }
        .coordinator-actions p {
            margin: 5px 0 0 0;
            color: #856404;
            font-size: 0.85rem;
        }
        .coordinator-actions.aprobado {
            background: #dcfce7;
            border-color: #86efac;
        }
        .coordinator-actions.aprobado h4,
        .coordinator-actions.aprobado p {
            color: #166534;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        /* Secciones */
        .pea-section {
            padding: 25px;
            border-bottom: 1px solid #e5e7eb;
        }
        .pea-section:last-child {
            border-bottom: none;
        }
        .pea-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e3a5f;
            margin: 0 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #1e3a5f;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Grid de datos */
        .data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .data-item {
            background: #f9fafb;
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
        }
        .data-item label {
            display: block;
            font-size: 0.8rem;
            color: #6b7280;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .data-item span, .data-item p {
            color: #1f2937;
            font-size: 0.95rem;
            margin: 0;
        }
        .data-item.full-width {
            grid-column: 1 / -1;
        }
        
        /* Unidades */
        .unidades-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        .unidad-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 15px;
        }
        .unidad-box h4 {
            margin: 0 0 8px 0;
            color: #1e3a5f;
            font-size: 1rem;
        }
        .unidad-box p {
            margin: 0;
            font-size: 0.85rem;
            color: #1f2937;
        }
        .unidad-box .semanas {
            margin-top: 10px;
            font-size: 0.8rem;
            color: #6b7280;
            background: white;
            padding: 5px 10px;
            border-radius: 5px;
            display: inline-block;
        }
        
        /* RAs */
        .ra-box {
            background: #f9fafb;
            border-left: 4px solid #1e3a5f;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 0 10px 10px 0;
        }
        .ra-box h4 {
            margin: 0 0 10px 0;
            color: #1e3a5f;
            font-size: 0.95rem;
        }
        .ra-box p {
            margin: 5px 0;
            font-size: 0.9rem;
            color: #1f2937;
        }
        .ra-box .contribucion {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-top: 10px;
        }
        .contribucion-alta { background: #d4edda; color: #155724; }
        .contribucion-media { background: #fff3cd; color: #856404; }
        .contribucion-baja { background: #f8d7da; color: #721c24; }
        
        /* Semanas */
        .semanas-accordion {
            margin-top: 15px;
        }
        .semana-item {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            margin-bottom: 10px;
            overflow: hidden;
        }
        .semana-header {
            background: #f9fafb;
            padding: 15px 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s;
        }
        .semana-header:hover {
            background: #e5e7eb;
        }
        .semana-header h4 {
            margin: 0;
            font-size: 0.95rem;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .semana-header .unidad-tag {
            background: #1e3a5f;
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
        }
        .semana-header .toggle-icon {
            font-size: 1.2rem;
            color: #6b7280;
            transition: transform 0.3s;
        }
        .semana-header.open .toggle-icon {
            transform: rotate(180deg);
        }
        .semana-body {
            padding: 20px;
            display: none;
            border-top: 1px solid #e5e7eb;
        }
        .semana-body.open {
            display: block;
        }
        
        /* Sub-secciones dentro de semana */
        .subsection {
            background: #f9fafb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #e5e7eb;
        }
        .subsection h5 {
            margin: 0 0 12px 0;
            color: #1f2937;
            font-size: 0.9rem;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 8px;
        }
        .subsection-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }
        .subsection-item label {
            display: block;
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 3px;
        }
        .subsection-item span {
            font-size: 0.85rem;
            color: #1f2937;
        }
        
        /* Actividades */
        .actividad-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 12px;
        }
        .actividad-card.practica {
            border-left: 4px solid #28a745;
        }
        .actividad-card.autonoma {
            border-left: 4px solid #17a2b8;
        }
        .actividad-card h6 {
            margin: 0 0 10px 0;
            font-size: 0.9rem;
            color: #1f2937;
        }
        
        /* Botones */
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }
        .btn-primary { background: #1e3a5f; color: white; }
        .btn-primary:hover { background: #2d4a6f; }
        .btn-secondary { background: #e5e7eb; color: #4b5563; }
        .btn-secondary:hover { background: #d1d5db; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .btn-sm { padding: 8px 14px; font-size: 0.8rem; }
        
        /* Badge */
        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-secondary { background: #e5e7eb; color: #4b5563; }
        
        /* Observaciones rechazo */
        .rechazo-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 11px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .rechazo-box h4 {
            margin: 0 0 10px 0;
            color: #721c24;
        }
        .rechazo-box p {
            margin: 0;
            color: #721c24;
        }
        
        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.show {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            padding: 25px;
        }
        .modal-content h3 {
            margin: 0 0 20px 0;
            color: #1f2937;
        }
        .modal-content textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.9rem;
            min-height: 120px;
            resize: vertical;
            margin-bottom: 20px;
            box-sizing: border-box;
        }
        .modal-content textarea:focus {
            outline: none;
            border-color: #1e3a5f;
        }
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 212px;
        }
        
        /* Header de página */
        .page-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px; 
            color: #333;
            font-size: 0.80rem;
            margin-bottom: 10px;
            
        }
        
        .page-header-flex p{
            color: #666;
            padding-top: 6px;
            font-size: 0.93rem
        }
        .header-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
       /* Toast Notifications - Estilo minimalista */
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
    animation: toastIn 0.3s ease;
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
.toast.hiding { animation: toastOut 0.3s ease forwards; }
@keyframes toastIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
@keyframes toastOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}
        
        @media (max-width: 768px) {
            .data-grid { grid-template-columns: 1fr; }
            .unidades-grid { grid-template-columns: 1fr; }
            .coordinator-actions { flex-direction: column; text-align: center; }
            .action-buttons { justify-content: center; }
            .page-header-flex { flex-direction: column; align-items: flex-start; }
        }
        
        @media print {
            .menulateral, header, .coordinator-actions, .btn, .header-buttons { display: none !important; }
            .contenido-principal { margin: 0; padding: 0; }
            .pea-section { break-inside: avoid; }
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
                <span class="badge-<?php echo $es_coordinador ? 'coordinador' : 'docente'; ?>">
                    <?php echo $es_coordinador ? 'Coordinador' : 'Docente'; ?>
                </span>
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></span>
            </div>
        </div>
    </header>
    
    <div class="contenedor">
        <div class="menulateral">
            <?php if ($es_coordinador): ?>
            <div class="carrera-info">
                <small>Carrera</small>
                <strong><?php echo htmlspecialchars($carrera_coord['nombre'] ?? ''); ?></strong>
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
            <?php else: ?>
            <nav>
                <ul>
                    <li><a href="indexusuario.php">🏠 Inicio</a></li>
                    <li><a href="mis_peas.php" class="active">📝 Mis PEAs</a></li>
                    <li><a href="perfilusuario.php">👤 Mi Perfil</a></li>
                    <li>
                        <form action="php/configuracion/cerrarsecion.php" method="POST" style="margin:0;">
                            <button type="submit" class="btn-salir">➜] Cerrar Sesión</button>
                        </form>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
        
        <div class="contenido-principal">
            <div class="page-header-flex">
                <div>
                    <h1>Ver PEA</h1>
                    <p>Vista de solo lectura del Plan de Estudio</p>
                </div>
                <div class="header-buttons">
    <button onclick="window.print()" class="btn btn-secondary btn-sm">🖨️ Imprimir</button>
    
    <!-- Botón Descargar Word - Visible en todos los estados -->
    <button onclick="descargarWord()" class="btn btn-secondary btn-sm">📄 Descargar Word</button>
    
    <?php if ($es_dueno): ?>
        <?php if ($pea['estado'] === 'borrador' || $pea['estado'] === 'rechazado'): ?>
            <!-- Docente: Editar y Enviar cuando está en borrador o rechazado -->
            <a href="distribucion_semanas.php?id_pea=<?php echo $id_pea; ?>" class="btn btn-primary btn-sm">✏️ Editar</a>
            <button onclick="mostrarModalEnviar()" class="btn btn-success btn-sm">📤 Enviar a Revisión</button>
        <?php elseif ($pea['estado'] === 'enviado'): ?>
            <!-- Docente: Puede devolver a borrador si se arrepiente -->
            <button onclick="mostrarModalDevolverDocente()" class="btn btn-warning btn-sm">↩ Devolver a Borrador</button>
        <?php endif; ?>
    <?php endif; ?>
    
    <a href="<?php echo $es_coord_carrera && !$es_dueno ? 'estado_peas.php' : 'mis_peas.php'; ?>" class="btn btn-secondary btn-sm">← Volver</a>
</div>
            </div>
            
            <?php if ($pea['estado'] === 'rechazado' && $pea['observaciones_rechazo']): ?>
            <div class="rechazo-box">
                <h4>⚠️ PEA Rechazado</h4>
                <p><strong>Observaciones:</strong> <?php echo nl2br(htmlspecialchars($pea['observaciones_rechazo'])); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($es_coord_carrera && $pea['estado'] === 'enviado'): ?>
            <div class="coordinator-actions">
                <div>
                    <h4>📋 Revisión de PEA</h4>
                    <p>Este PEA está pendiente de revisión. Revise el contenido y apruebe o rechace.</p>
                </div>
                <div class="action-buttons">
                    <button class="btn btn-success" onclick="aprobarPEA()">✓ Aprobar PEA</button>
                    <button class="btn btn-danger" onclick="mostrarModalRechazo()">✗ Rechazar PEA</button>
                    <button class="btn btn-secondary" onclick="devolverBorrador()">↩ Devolver a Borrador</button>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($es_coord_carrera && $pea['estado'] === 'aprobado'): ?>
            <div class="coordinator-actions aprobado">
                <div>
                    <h4>✅ PEA Aprobado</h4>
                    <p>Este PEA ya fue aprobado. Si necesita que el docente realice cambios, puede devolverlo a borrador.</p>
                </div>
                <div class="action-buttons">
                    <button class="btn btn-warning" onclick="devolverBorrador()">↩ Devolver a Borrador</button>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="pea-view-container">
                <div class="pea-view-header">
                    <h2><?php echo htmlspecialchars($pea['asignatura']); ?></h2>
                    <p><?php echo htmlspecialchars($pea['carrera']); ?> • <?php echo htmlspecialchars($pea['periodo_nombre']); ?></p>
                    <div class="pea-header-meta">
                        <span>Nivel <?php echo $pea['nivel']; ?></span>
                        <span>Paralelo <?php echo $pea['paralelo'] === 'AB' ? 'A y B' : $pea['paralelo']; ?></span>
                        <span><?php echo ucfirst($pea['jornada']); ?></span>
                        <span><?php echo $pea['total_horas']; ?> horas</span>
                        <span class="badge <?php echo getEstadoBadge($pea['estado']); ?>"><?php echo ucfirst($pea['estado']); ?></span>
                    </div>
                </div>
                
                <!-- Información General -->
                <div class="pea-section">
                    <h3 class="pea-section-title">📋 Información General</h3>
                    <div class="data-grid">
                        <div class="data-item">
                            <label>Código de Asignatura</label>
                            <span><?php echo htmlspecialchars($pea['codigo']); ?></span>
                        </div>
                        <div class="data-item">
                            <label>Modalidad</label>
                            <span><?php echo htmlspecialchars($pea['modalidad'] ?? 'Presencial'); ?></span>
                        </div>
                        <div class="data-item">
                            <label>Horas Docencia</label>
                            <span><?php echo $pea['horas_docencia'] ?? 0; ?></span>
                        </div>
                        <div class="data-item">
                            <label>Horas Prácticas</label>
                            <span><?php echo $pea['horas_practicas'] ?? 0; ?></span>
                        </div>
                        <div class="data-item">
                            <label>Horas Autónomas</label>
                            <span><?php echo $pea['horas_autonomas'] ?? 0; ?></span>
                        </div>
                        <div class="data-item">
                            <label>Fecha de Creación</label>
                            <span><?php echo $pea['fecha_creacion'] ? date('d/m/Y H:i', strtotime($pea['fecha_creacion'])) : '-'; ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Docente -->
                <div class="pea-section">
                    <h3 class="pea-section-title">👤 Información del Docente</h3>
                    <div class="data-grid">
                        <div class="data-item">
                            <label>Nombre del Docente</label>
                            <span><?php echo htmlspecialchars($pea['docente_apellidos'] . ' ' . $pea['docente_nombres']); ?></span>
                        </div>
                        <div class="data-item">
                            <label>Título</label>
                            <span><?php echo htmlspecialchars($pea['titulo_docente'] ?? $pea['docente_titulo'] ?? '-'); ?></span>
                        </div>
                        <div class="data-item">
                            <label>Correo</label>
                            <span><?php echo htmlspecialchars($pea['correo_docente'] ?? $pea['docente_email'] ?? '-'); ?></span>
                        </div>
                        <div class="data-item">
                            <label>Teléfono</label>
                            <span><?php echo htmlspecialchars($pea['telefono_docente'] ?? $pea['docente_celular'] ?? '-'); ?></span>
                        </div>
                        <div class="data-item">
                            <label>Tutoría Grupal</label>
                            <span><?php echo htmlspecialchars($pea['tutoria_grupal'] ?? '-'); ?></span>
                        </div>
                        <div class="data-item">
                            <label>Tutoría Individual</label>
                            <span><?php echo htmlspecialchars($pea['tutoria_individual'] ?? '-'); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Contenido Académico -->
                <div class="pea-section">
                    <h3 class="pea-section-title">📚 Contenido Académico</h3>
                    <div class="data-grid">
                        <div class="data-item full-width">
                            <label>Descripción de la Asignatura</label>
                            <p><?php echo nl2br(htmlspecialchars($pea['descripcion_asignatura'] ?? '-')); ?></p>
                        </div>
                        <div class="data-item full-width">
                            <label>Objetivo General</label>
                            <p><?php echo nl2br(htmlspecialchars($pea['objetivo_general'] ?? '-')); ?></p>
                        </div>
                        <div class="data-item">
                            <label>Eje Transversal</label>
                            <span><?php echo htmlspecialchars($pea['eje_transversal'] ?? '-'); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Unidades -->
                <div class="pea-section">
                    <h3 class="pea-section-title">📖 Unidades de la Asignatura</h3>
                    <div class="unidades-grid">
                        <?php foreach ($unidades as $unidad): ?>
                        <div class="unidad-box">
                            <h4>Unidad <?php echo $unidad['numero']; ?>: <?php echo htmlspecialchars($unidad['nombre']); ?></h4>
                            <p><?php echo htmlspecialchars($unidad['descripcion'] ?? ''); ?></p>
                            <span class="semanas">Semanas <?php echo $unidad['semana_inicio']; ?> - <?php echo $unidad['semana_fin']; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Resultados de Aprendizaje -->
                <div class="pea-section">
                    <h3 class="pea-section-title">🎯 Resultados de Aprendizaje</h3>
                    
                    <?php if ($pea['ra1']): ?>
                    <div class="ra-box">
                        <h4>RA 1</h4>
                        <p><strong>Resultado:</strong> <?php echo nl2br(htmlspecialchars($pea['ra1'])); ?></p>
                        <?php if ($pea['ra1_evidencia']): ?>
                        <p><strong>Perfil de Egreso:</strong> <?php echo nl2br(htmlspecialchars($pea['ra1_evidencia'])); ?></p>
                        <?php endif; ?>
                        <?php if ($pea['ra1_contribucion']): ?>
                        <span class="contribucion contribucion-<?php echo strtolower($pea['ra1_contribucion']); ?>">
                            Contribución: <?php echo $pea['ra1_contribucion']; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($pea['ra2']): ?>
                    <div class="ra-box">
                        <h4>RA 2</h4>
                        <p><strong>Resultado:</strong> <?php echo nl2br(htmlspecialchars($pea['ra2'])); ?></p>
                        <?php if ($pea['ra2_evidencia']): ?>
                        <p><strong>Perfil de Egreso:</strong> <?php echo nl2br(htmlspecialchars($pea['ra2_evidencia'])); ?></p>
                        <?php endif; ?>
                        <?php if ($pea['ra2_contribucion']): ?>
                        <span class="contribucion contribucion-<?php echo strtolower($pea['ra2_contribucion']); ?>">
                            Contribución: <?php echo $pea['ra2_contribucion']; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($pea['ra3']): ?>
                    <div class="ra-box">
                        <h4>RA 3</h4>
                        <p><strong>Resultado:</strong> <?php echo nl2br(htmlspecialchars($pea['ra3'])); ?></p>
                        <?php if ($pea['ra3_evidencia']): ?>
                        <p><strong>Perfil de Egreso:</strong> <?php echo nl2br(htmlspecialchars($pea['ra3_evidencia'])); ?></p>
                        <?php endif; ?>
                        <?php if ($pea['ra3_contribucion']): ?>
                        <span class="contribucion contribucion-<?php echo strtolower($pea['ra3_contribucion']); ?>">
                            Contribución: <?php echo $pea['ra3_contribucion']; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Distribución Semanal -->
                <div class="pea-section">
                    <h3 class="pea-section-title">📅 Distribución Semanal</h3>
                    
                    <div class="semanas-accordion">
                        <?php for ($i = 1; $i <= $num_semanas; $i++): 
                            $semana = $semanas[$i] ?? [];
                            $id_semana = $semana['id_semana'] ?? 0;
                            $id_unidad = $semana['id_unidad'] ?? null;
                            $nombre_unidad = ($id_unidad && isset($unidades[$id_unidad])) ? $unidades[$id_unidad]['nombre'] : '';
                            $acts_semana = $id_semana ? ($actividades[$id_semana] ?? []) : [];
                        ?>
                        <div class="semana-item">
                            <div class="semana-header" onclick="toggleSemana(this)">
                                <h4>
                                    Semana <?php echo $i; ?>
                                    <?php if ($nombre_unidad): ?>
                                    <span class="unidad-tag"><?php echo htmlspecialchars($nombre_unidad); ?></span>
                                    <?php endif; ?>
                                </h4>
                                <span class="toggle-icon">▼</span>
                            </div>
                            <div class="semana-body">
                                <!-- Fechas y Horarios -->
                                <?php if (!empty($semana['fecha_inicio']) || !empty($semana['horario_matutina'])): ?>
                                <div class="subsection">
                                    <h5>📅 Fechas y Horarios</h5>
                                    <div class="subsection-grid">
                                        <?php if (!empty($semana['fecha_inicio'])): ?>
                                        <div class="subsection-item">
                                            <label>Fecha Inicio</label>
                                            <span><?php echo date('d/m/Y', strtotime($semana['fecha_inicio'])); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($semana['fecha_fin'])): ?>
                                        <div class="subsection-item">
                                            <label>Fecha Fin</label>
                                            <span><?php echo date('d/m/Y', strtotime($semana['fecha_fin'])); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($semana['horario_matutina'])): ?>
                                        <div class="subsection-item">
                                            <label>Horario Matutina</label>
                                            <span><?php echo htmlspecialchars($semana['horario_matutina']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($semana['horario_nocturna'])): ?>
                                        <div class="subsection-item">
                                            <label>Horario Nocturna</label>
                                            <span><?php echo htmlspecialchars($semana['horario_nocturna']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Temas -->
                                <?php if (!empty($semana['temas'])): ?>
                                <div class="subsection">
                                    <h5>📚 Contenido</h5>
                                    <div class="subsection-grid">
                                        <div class="subsection-item" style="grid-column: 1/-1;">
                                            <label>Temas / Subtemas</label>
                                            <span><?php echo nl2br(htmlspecialchars($semana['temas'])); ?></span>
                                        </div>
                                        <?php if (!empty($semana['objetivo'])): ?>
                                        <div class="subsection-item" style="grid-column: 1/-1;">
                                            <label>Objetivo</label>
                                            <span><?php echo nl2br(htmlspecialchars($semana['objetivo'])); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($semana['resultado_aprendizaje'])): ?>
                                        <div class="subsection-item" style="grid-column: 1/-1;">
                                            <label>Resultado de Aprendizaje</label>
                                            <span><?php echo nl2br(htmlspecialchars($semana['resultado_aprendizaje'])); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Actividades Prácticas -->
                                <?php 
                                $practicas = array_filter($acts_semana, fn($a) => $a['tipo'] === 'practica');
                                if (!empty($practicas)): 
                                ?>
                                <div class="subsection">
                                    <h5>🔬 Actividades Prácticas</h5>
                                    <?php foreach ($practicas as $act): ?>
                                    <div class="actividad-card practica">
                                        <h6><?php echo htmlspecialchars($act['tema'] ?? 'Actividad Práctica'); ?></h6>
                                        <div class="subsection-grid">
                                            <?php if (!empty($act['descripcion'])): ?>
                                            <div class="subsection-item" style="grid-column: 1/-1;">
                                                <label>Descripción</label>
                                                <span><?php echo nl2br(htmlspecialchars($act['descripcion'])); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <div class="subsection-item">
                                                <label>Horas con Docente</label>
                                                <span><?php echo $act['horas_con_docente'] ?? 0; ?></span>
                                            </div>
                                            <div class="subsection-item">
                                                <label>Horas Autónomo</label>
                                                <span><?php echo $act['horas'] ?? 0; ?></span>
                                            </div>
                                            <?php if (!empty($act['producto_final'])): ?>
                                            <div class="subsection-item">
                                                <label>Producto Final</label>
                                                <span><?php echo htmlspecialchars($act['producto_final']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <?php if (!empty($act['calificacion'])): ?>
                                            <div class="subsection-item">
                                                <label>Calificación</label>
                                                <span><?php echo htmlspecialchars($act['calificacion']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Actividades Autónomas -->
                                <?php 
                                $autonomas = array_filter($acts_semana, fn($a) => $a['tipo'] === 'autonoma');
                                if (!empty($autonomas)): 
                                ?>
                                <div class="subsection">
                                    <h5>📖 Trabajo Autónomo</h5>
                                    <?php foreach ($autonomas as $act): ?>
                                    <div class="actividad-card autonoma">
                                        <h6><?php echo htmlspecialchars($act['tema'] ?? 'Actividad Autónoma'); ?></h6>
                                        <div class="subsection-grid">
                                            <?php if (!empty($act['descripcion'])): ?>
                                            <div class="subsection-item" style="grid-column: 1/-1;">
                                                <label>Descripción</label>
                                                <span><?php echo nl2br(htmlspecialchars($act['descripcion'])); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <div class="subsection-item">
                                                <label>Horas</label>
                                                <span><?php echo $act['horas'] ?? 0; ?></span>
                                            </div>
                                            <?php if (!empty($act['producto_final'])): ?>
                                            <div class="subsection-item">
                                                <label>Producto Final</label>
                                                <span><?php echo htmlspecialchars($act['producto_final']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <?php if (!empty($act['calificacion'])): ?>
                                            <div class="subsection-item">
                                                <label>Calificación</label>
                                                <span><?php echo htmlspecialchars($act['calificacion']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Horas -->
                                <div class="subsection">
                                    <h5>⏱️ Horas de la Semana</h5>
                                    <div class="subsection-grid">
                                        <div class="subsection-item">
                                            <label>Docencia</label>
                                            <span><?php echo $semana['horas_docencia'] ?? 0; ?></span>
                                        </div>
                                        <div class="subsection-item">
                                            <label>Prácticas con Docente</label>
                                            <span><?php echo $semana['horas_practicas_con_docente'] ?? 0; ?></span>
                                        </div>
                                        <div class="subsection-item">
                                            <label>Prácticas Autónomo</label>
                                            <span><?php echo $semana['horas_practicas_autonomo'] ?? 0; ?></span>
                                        </div>
                                        <div class="subsection-item">
                                            <label>Trabajo Autónomo</label>
                                            <span><?php echo $semana['horas_trabajo_autonomo'] ?? 0; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <!-- Bibliografía -->
                <?php if ($pea['bibliografia_basica'] || $pea['bibliografia_complementaria']): ?>
                <div class="pea-section">
                    <h3 class="pea-section-title">📚 Bibliografía</h3>
                    <div class="data-grid">
                        <?php if ($pea['bibliografia_basica']): ?>
                        <div class="data-item full-width">
                            <label>Bibliografía Básica</label>
                            <p><?php echo nl2br(htmlspecialchars($pea['bibliografia_basica'])); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($pea['bibliografia_complementaria']): ?>
                        <div class="data-item full-width">
                            <label>Bibliografía Complementaria</label>
                            <p><?php echo nl2br(htmlspecialchars($pea['bibliografia_complementaria'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Contenedor de Notificaciones Toast -->
    <div class="toast-container" id="toastContainer"></div>
    <!-- Modal de Confirmación Aprobar -->
    <div class="modal-overlay" id="modalAprobar">
        <div class="modal-content">
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #28a745, #20c997); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 2rem; color: white;">✓</div>
                <h3 style="margin: 0; color: #1f2937;">¿Aprobar este PEA?</h3>
            </div>
            <p style="color: #6b7280; text-align: center; margin-bottom: 25px;">El PEA quedará marcado como aprobado y el docente podrá descargar el documento Word.</p>
            <div class="modal-actions" style="justify-content: center;">
                <button class="btn btn-secondary" onclick="cerrarModalAprobar()">Cancelar</button>
                <button class="btn btn-success" onclick="confirmarAprobar()">✓ Sí, Aprobar</button>
            </div>
        </div>
    </div>
    
    <!-- Modal de Confirmación Devolver -->
    <div class="modal-overlay" id="modalDevolver">
        <div class="modal-content">
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #ffc107, #fd7e14); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 2rem; color: #212529;">↩</div>
                <h3 style="margin: 0; color: #1f2937;">¿Devolver a Borrador?</h3>
            </div>
            <p style="color: #6b7280; text-align: center; margin-bottom: 25px;">El PEA volverá al estado de borrador y el docente podrá editarlo nuevamente.</p>
            <div class="modal-actions" style="justify-content: center;">
                <button class="btn btn-secondary" onclick="cerrarModalDevolver()">Cancelar</button>
                <button class="btn btn-warning" onclick="confirmarDevolver()">↩ Sí, Devolver</button>
            </div>
        </div>
    </div>

    <!-- Modal de Rechazo -->
    <div class="modal-overlay" id="modalRechazo">
        <div class="modal-content">
            <h3>Rechazar PEA</h3>
            <p style="color: #6b7280; margin-bottom: 15px;">Indique las observaciones o correcciones que debe realizar el docente:</p>
            <textarea id="observacionesRechazo" placeholder="Escriba las observaciones..."></textarea>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="cerrarModal()">Cancelar</button>
                <button class="btn btn-danger" onclick="rechazarPEA()">Rechazar PEA</button>
            </div>
        </div>
    </div>
    <!-- Modal de Confirmación Enviar a Revisión (Docente) -->
<div class="modal-overlay" id="modalEnviar">
    <div class="modal-content">
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #28a745, #20c997); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 2rem; color: white;">📤</div>
            <h3 style="margin: 0; color: #1f2937;">¿Enviar PEA para Revisión?</h3>
        </div>
        <div style="background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <p style="margin: 0; color: #92400e; font-size: 0.9rem;">
                <strong>⚠️ Importante:</strong><br>
                Una vez enviado, el PEA quedará pendiente de revisión por el coordinador. 
                Podrá devolverlo a borrador si detecta algún error antes de que sea revisado.
            </p>
        </div>
        <div class="modal-actions" style="justify-content: center; gap: 15px;">
            <button class="btn btn-secondary" onclick="cerrarModalEnviar()">Cancelar</button>
            <button class="btn btn-success" onclick="confirmarEnviar()">📤 Sí, Enviar</button>
        </div>
    </div>
</div>

<!-- Modal de Confirmación Devolver a Borrador (Docente) -->
<div class="modal-overlay" id="modalDevolverDocente">
    <div class="modal-content">
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #ffc107, #fd7e14); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 2rem; color: #212529;">↩</div>
            <h3 style="margin: 0; color: #1f2937;">¿Devolver a Borrador?</h3>
        </div>
        <p style="color: #6b7280; text-align: center; margin-bottom: 25px;">
            El PEA volverá al estado de borrador y podrá editarlo nuevamente antes de enviarlo a revisión.
        </p>
        <div class="modal-actions" style="justify-content: center; gap: 15px;">
            <button class="btn btn-secondary" onclick="cerrarModalDevolverDocente()">Cancelar</button>
            <button class="btn btn-warning" onclick="confirmarDevolverDocente()">↩ Sí, Devolver</button>
        </div>
    </div>
</div>
    
    <script>
        const ID_PEA = <?php echo $id_pea; ?>;
        
        // Sistema de Notificaciones Toast
        // Sistema de Notificaciones Toast - Estilo minimalista
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
    if (!toast || toast.classList.contains('hiding')) return;
    toast.classList.add('hiding');
    setTimeout(() => toast.remove(), 300);
}
        
        function toggleSemana(header) {
            header.classList.toggle('open');
            header.nextElementSibling.classList.toggle('open');
        }
        
        function aprobarPEA() {
    document.getElementById('modalAprobar').classList.add('show');
}

function cerrarModalAprobar() {
    document.getElementById('modalAprobar').classList.remove('show');
}

function confirmarAprobar() {
    cerrarModalAprobar();
    showToast('info', 'Procesando...', 'Aprobando el PEA...', 10000);
    
    fetch('php/pea/cambiar_estado_pea.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id_pea=${ID_PEA}&accion=aprobar`
    })
    .then(response => {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.text();
    })
    .then(text => {
        document.querySelectorAll('.toast').forEach(t => closeToast(t));
        try {
            const data = JSON.parse(text);
            if (data.success) {
                showToast('success', '¡PEA Aprobado!', 'El PEA ha sido aprobado correctamente.', 3000);
                setTimeout(() => location.reload(), 2000);
            } else {
                showToast('error', 'Error', data.message);
            }
        } catch (e) {
            showToast('error', 'Error del servidor', text.substring(0, 100));
        }
    })
    .catch(error => {
        document.querySelectorAll('.toast').forEach(t => closeToast(t));
        showToast('error', 'Error de conexión', error.message);
    });
}
        
        function mostrarModalRechazo() {
            document.getElementById('modalRechazo').classList.add('show');
        }
        
        function cerrarModal() {
            document.getElementById('modalRechazo').classList.remove('show');
        }
        
        function rechazarPEA() {
            const observaciones = document.getElementById('observacionesRechazo').value.trim();
            
            if (!observaciones) {
                showToast('warning', 'Campo requerido', 'Debe indicar las observaciones del rechazo.');
                return;
            }
            
            cerrarModal();
            showToast('info', 'Procesando...', 'Rechazando el PEA...', 10000);
            
            fetch('php/pea/cambiar_estado_pea.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id_pea=${ID_PEA}&accion=rechazar&observaciones=${encodeURIComponent(observaciones)}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(text => {
                console.log('Respuesta del servidor:', text);
                document.querySelectorAll('.toast').forEach(t => closeToast(t));
                
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        showToast('success', 'PEA Rechazado', 'El docente ha sido notificado de las observaciones.', 3000);
                        setTimeout(() => location.href = 'estado_peas.php', 2000);
                    } else {
                        showToast('error', 'Error', data.message);
                    }
                } catch (e) {
                    showToast('error', 'Error del servidor', text.substring(0, 100));
                }
            })
            .catch(error => {
                document.querySelectorAll('.toast').forEach(t => closeToast(t));
                console.error('Error:', error);
                showToast('error', 'Error de conexión', error.message);
            });
        }
        
        function devolverBorrador() {
    document.getElementById('modalDevolver').classList.add('show');
}

function cerrarModalDevolver() {
    document.getElementById('modalDevolver').classList.remove('show');
}

function confirmarDevolver() {
    cerrarModalDevolver();
    showToast('info', 'Procesando...', 'Devolviendo a borrador...', 10000);
    
    fetch('php/pea/cambiar_estado_pea.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id_pea=${ID_PEA}&accion=devolver`
    })
    .then(response => {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.text();
    })
    .then(text => {
        document.querySelectorAll('.toast').forEach(t => closeToast(t));
        try {
            const data = JSON.parse(text);
            if (data.success) {
                showToast('success', 'Devuelto a Borrador', 'El docente puede editar el PEA nuevamente.', 3000);
                setTimeout(() => location.href = 'estado_peas.php', 2000);
            } else {
                showToast('error', 'Error', data.message);
            }
        } catch (e) {
            showToast('error', 'Error del servidor', text.substring(0, 100));
        }
    })
    .catch(error => {
        document.querySelectorAll('.toast').forEach(t => closeToast(t));
        showToast('error', 'Error de conexión', error.message);
    });
}
        
        // Cerrar modal con Escape
      document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModal();
        cerrarModalAprobar();
        cerrarModalDevolver();
        cerrarModalEnviar();
        cerrarModalDevolverDocente();
    }
});
        
        // Cerrar modal al hacer clic fuera
        document.getElementById('modalRechazo').addEventListener('click', function(e) {
            if (e.target === this) cerrarModal();
        });
        // ========== FUNCIONES DEL DOCENTE ==========

// Descargar Word (placeholder - se implementará después)
function descargarWord() {
    showToast('info', 'Éxito', 'La descarga de Word ha comenzado.');
    // Cuando esté listo: window.location.href = 'php/pea/generar_word.php?id_pea=' + ID_PEA;
}

// Modal Enviar a Revisión
function mostrarModalEnviar() {
    document.getElementById('modalEnviar').classList.add('show');
}

function cerrarModalEnviar() {
    document.getElementById('modalEnviar').classList.remove('show');
}

function confirmarEnviar() {
    cerrarModalEnviar();
    showToast('info', 'Procesando...', 'Enviando PEA para revisión...', 10000);
    
    fetch('php/pea/cambiar_estado_pea.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id_pea=${ID_PEA}&accion=enviar`
    })
    .then(response => {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.text();
    })
    .then(text => {
        document.querySelectorAll('.toast').forEach(t => {
            const closeBtn = t.querySelector('.toast-close');
            if (closeBtn) closeToast(closeBtn);
        });
        try {
            const data = JSON.parse(text);
            if (data.success) {
                showToast('success', '¡PEA Enviado!', 'Su PEA ha sido enviado para revisión del coordinador.', 4000);
                setTimeout(() => location.reload(), 2500);
            } else {
                showToast('error', 'Error', data.message);
            }
        } catch (e) {
            showToast('error', 'Error del servidor', text.substring(0, 100));
        }
    })
    .catch(error => {
        document.querySelectorAll('.toast').forEach(t => {
            const closeBtn = t.querySelector('.toast-close');
            if (closeBtn) closeToast(closeBtn);
        });
        showToast('error', 'Error de conexión', error.message);
    });
}

// Modal Devolver a Borrador (Docente)
function mostrarModalDevolverDocente() {
    document.getElementById('modalDevolverDocente').classList.add('show');
}

function cerrarModalDevolverDocente() {
    document.getElementById('modalDevolverDocente').classList.remove('show');
}

function confirmarDevolverDocente() {
    cerrarModalDevolverDocente();
    showToast('info', 'Procesando...', 'Devolviendo a borrador...', 10000);
    
    fetch('php/pea/cambiar_estado_pea.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id_pea=${ID_PEA}&accion=devolver`
    })
    .then(response => {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.text();
    })
    .then(text => {
        document.querySelectorAll('.toast').forEach(t => {
            const closeBtn = t.querySelector('.toast-close');
            if (closeBtn) closeToast(closeBtn);
        });
        try {
            const data = JSON.parse(text);
            if (data.success) {
                showToast('success', 'Devuelto a Borrador', 'Puede editar su PEA nuevamente.', 3000);
                setTimeout(() => location.reload(), 2000);
            } else {
                showToast('error', 'Error', data.message);
            }
        } catch (e) {
            showToast('error', 'Error del servidor', text.substring(0, 100));
        }
    })
    .catch(error => {
        document.querySelectorAll('.toast').forEach(t => {
            const closeBtn = t.querySelector('.toast-close');
            if (closeBtn) closeToast(closeBtn);
        });
        showToast('error', 'Error de conexión', error.message);
    });
}
// Cerrar modales al hacer clic fuera
document.getElementById('modalEnviar').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalEnviar();
});

document.getElementById('modalDevolverDocente').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalDevolverDocente();
});

document.getElementById('modalAprobar').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalAprobar();
});

document.getElementById('modalDevolver').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalDevolver();
});
    </script>
</body>
</html>
