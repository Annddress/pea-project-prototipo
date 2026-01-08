<?php
session_start();

if (!isset($_SESSION['id_docente'])) {
    header('Location: login.php');
    exit();
}

require_once 'php/conexion.php';

$id_docente = $_SESSION['id_docente'];
$id_pea = isset($_GET['id_pea']) ? intval($_GET['id_pea']) : 0;

if (!$id_pea) {
    $_SESSION['mensaje_error'] = "ID de PEA no especificado.";
    header('Location: mis_peas.php');
    exit();
}

// Verificar que el PEA pertenece al docente
$sql_pea = "SELECT p.*, 
                   a.paralelo, a.jornada,
                   asig.nombre as asignatura, asig.codigo, asig.nivel,
                   c.nombre as carrera,
                   per.nombre as periodo_nombre
            FROM pea p
            INNER JOIN asignacion a ON p.id_asignacion = a.id_asignacion
            INNER JOIN asignatura asig ON a.id_asignatura = asig.id_asignatura
            INNER JOIN carrera c ON asig.id_carrera = c.id_carrera
            INNER JOIN periodo_academico per ON a.id_periodo = per.id_periodo
            WHERE p.id_pea = ? AND a.id_docente = ?";

$stmt = mysqli_prepare($cnx, $sql_pea);
mysqli_stmt_bind_param($stmt, "ii", $id_pea, $id_docente);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pea = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$pea) {
    $_SESSION['mensaje_error'] = "No tienes permiso para editar este PEA.";
    header('Location: mis_peas.php');
    exit();
}

// Verificar estado (solo borrador o rechazado)
if (!in_array($pea['estado'], ['borrador', 'rechazado'])) {
    $_SESSION['mensaje_error'] = "Este PEA no puede editarse en su estado actual.";
    header('Location: mis_peas.php');
    exit();
}

// Semanas fijas en 16
$num_semanas = 16;

// Obtener unidades del PEA
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

// Obtener semanas existentes
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

// Obtener actividades por semana
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

$es_coordinador = ($_SESSION['rol'] === 'coordinador');

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distribución Semanal - <?php echo htmlspecialchars($pea['asignatura']); ?></title>
    <link rel="stylesheet" href="css/coordinador.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        .pea-container {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .pea-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2d4a6f 100%);
            color: white;
            padding: 20px 25px;
        }
        .pea-header h2 { margin: 0 0 5px 0; font-size: 1.25rem; font-weight: 600; }
        .pea-header p { margin: 0; opacity: 0.85; font-size: 0.875rem; }
        .pea-header-badges { display: flex; gap: 10px; margin-top: 12px; flex-wrap: wrap; }
        .pea-header-badges span {
            background: rgba(255,255,255,0.15);
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .pea-body { padding: 25px; }
        
        .unidades-resumen {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .unidad-badge {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            padding: 10px 15px;
            font-size: 0.8rem;
        }
        .unidad-badge strong { color: #0369a1; display: block; margin-bottom: 3px; }
        .unidad-badge span { color: #6b7280; }
        
        .semanas-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 25px;
            padding: 15px;
            background: #f9fafb;
            border-radius: 6px;
        }
        .semana-btn {
            width: 40px;
            height: 40px;
            border: 1px solid #d1d5db;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .semana-btn:hover { border-color: #1e3a5f; color: #1e3a5f; }
        .semana-btn.active { background: #1e3a5f; color: white; border-color: #1e3a5f; }
        .semana-btn.completed { background: #dcfce7; border-color: #86efac; color: #166534; }
        
        .semana-container { display: none; }
        .semana-container.active { display: block; }
        
        .semana-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .semana-card-header {
            background: #e5e7eb;
            padding: 15px 20px;
            border-bottom: 1px solid #d1d5db;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .semana-card-header h3 { margin: 0; font-size: 1rem; font-weight: 600; color: #374151; }
        .semana-card-header .unidad-tag {
            background: #1e3a5f;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        .semana-card-body { padding: 20px; }
        
        .section-block {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        .section-block-header {
            background: #f3f4f6;
            padding: 10px 15px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.85rem;
            font-weight: 600;
            color: #374151;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .section-block-header:hover { background: #e5e7eb; }
        .section-block-header .toggle { font-size: 0.75rem; color: #6b7280; }
        .section-block-body { padding: 15px; }
        .section-block-body.collapsed { display: none; }
        
        .fields-row { display: flex; gap: 15px; margin-bottom: 12px; flex-wrap: wrap; }
        .fields-row .field { flex: 1; min-width: 200px; }
        .field { display: flex; flex-direction: column; margin-bottom: 10px; }
        .field label { font-size: 0.75rem; font-weight: 500; color: #374151; margin-bottom: 4px; }
        .field input, .field select, .field textarea {
            padding: 8px 10px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        .field input:focus, .field select:focus, .field textarea:focus {
            outline: none;
            border-color: #1e3a5f;
            box-shadow: 0 0 0 2px rgba(30,58,95,0.1);
        }
        .field textarea { min-height: 60px; resize: vertical; }
        .field-small { max-width: 100px; }
        
        .actividades-container { margin-top: 10px; }
        .actividad-item {
            background: #fefefe;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 12px;
        }
        .actividad-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #e5e7eb;
        }
        .actividad-item-header h5 { margin: 0; font-size: 0.85rem; font-weight: 600; color: #1e3a5f; }
        .btn-remove-actividad {
            background: none;
            border: none;
            color: #dc2626;
            cursor: pointer;
            font-size: 0.75rem;
            padding: 4px 8px;
        }
        .btn-remove-actividad:hover { background: #fef2f2; border-radius: 4px; }
        
        .btn-add-actividad {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 12px;
            background: #f0f9ff;
            border: 1px dashed #0ea5e9;
            border-radius: 4px;
            color: #0369a1;
            font-size: 0.8rem;
            cursor: pointer;
            margin-top: 10px;
        }
        .btn-add-actividad:hover { background: #e0f2fe; }
        
        .semana-nav-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
        }
        .semana-nav-buttons .btn-nav-semana {
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-prev-semana {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db !important;
        }
        .btn-prev-semana:hover { background: #e5e7eb; }
        .btn-prev-semana:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-next-semana {
            background: #1e3a5f;
            color: white;
        }
        .btn-next-semana:hover { background: #152a45; }
        .btn-enviar-revision {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            transition: all 0.3s ease;
            
        }
        .btn-enviar-revision:hover {  transform: translateY(-2px);}
        .btn-enviar-revision:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            padding: 18px;
            padding-left: 46px;
            padding-right: 46px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .btn-secondary { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }
        .btn-secondary:hover { background: #e5e7eb; }
        .btn-primary { background: #1e3a5f; color: white; }
        .btn-primary:hover { background: #152a45; }
        .btn-success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; }
        .btn-success:hover { background: #047857; }
        
        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            padding: 12px 15px;
            margin-bottom: 20px;
            font-size: 0.8rem;
            color: #1e40af;
        }
        .info-box.warning {
            background: #fef3c7;
            border-color: #fcd34d;
            color: #92400e;
        }
        .info-box.success {
            background: #dcfce7;
            border-color: #86efac;
            color: #166534;
        }
        
        .save-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #059669;
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            font-size: 0.85rem;
            display: none;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .save-indicator.show { display: block; }
        .save-indicator.error { background: #dc2626; }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }
        .modal-overlay.show { display: flex; }
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 25px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }
        .modal-content h3 {
            margin: 0 0 15px 0;
            color: #1e3a5f;
            font-size: 1.2rem;
        }
        .modal-content p {
            color: #4b5563;
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .modal-content .warning-text {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 6px;
            padding: 9px;
            padding-bottom: 0px;
            color: #92400e;
            font-size: 0.85rem;
            margin-bottom: 20px;
        }
        .modal-buttons {
            display: flex;
            gap: 158px;
            
            
        }
        .modal-buttons .btn { padding: 10px 20px; }
        
        .progress-bar {
            background: #e5e7eb;
            border-radius: 10px;
            height: 8px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .progress-bar-fill {
            background: linear-gradient(90deg, #059669, #10b981);
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        .progress-text {
            font-size: 0.8rem;
            color: #6b7280;
            margin-bottom: 10px;
            text-align: center;
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
@keyframes toastIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
@keyframes toastOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}
.toast.hiding { animation: toastOut 0.3s ease forwards; }
#modalExito .modal-content {
    max-width: 550px;
}
#modalExito .info-box.success ul {
    list-style-type: none;
    padding: 0;
    margin: 10px 0 0 0;
}
#modalExito .info-box.success ul li {
    padding: 5px 0;
}
        @media (max-width: 768px) {
            .fields-row { flex-direction: column; }
            .fields-row .field { min-width: 100%; }
            .semana-nav-buttons { flex-direction: column; gap: 10px; }
            .semana-nav-buttons .btn-nav-semana { width: 100%; justify-content: center; }
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
                    <li><a href="mis_peas.php" class="active">📝 Mis PEAs</a></li>
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
            <div class="page-header">
                <h1>Distribución Semanal</h1>
                <p>Complete la información de cada semana del PEA</p>
            </div>
            
            <?php if (isset($_SESSION['mensaje_exito'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        showToast('success', '¡Éxito!', '<?php echo addslashes($_SESSION['mensaje_exito']); ?>');
    });
</script>
<?php unset($_SESSION['mensaje_exito']); endif; ?>
            
            <?php if ($pea['estado'] === 'rechazado' && !empty($pea['observaciones_rechazo'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        showToast('warning', 'PEA Rechazado', '<?php echo addslashes($pea['observaciones_rechazo']); ?>', 8000);
    });
</script>
<?php endif; ?>
            
            <div class="pea-container">
                <div class="pea-header">
                    <h2><?php echo htmlspecialchars($pea['asignatura']); ?></h2>
                    <p><?php echo htmlspecialchars($pea['carrera']); ?></p>
                    <div class="pea-header-badges">
                        <span>Nivel <?php echo $pea['nivel']; ?></span>
                        <span>Paralelo <?php echo $pea['paralelo']; ?></span>
                        <span><?php echo $num_semanas; ?> semanas</span>
                        <span>Estado: <?php echo ucfirst($pea['estado']); ?></span>
                    </div>
                </div>
                
                <div class="pea-body">
                    <div class="unidades-resumen">
                        <?php foreach ($unidades as $unidad): ?>
                        <div class="unidad-badge">
                            <strong>Unidad <?php echo $unidad['numero']; ?>: <?php echo htmlspecialchars($unidad['nombre']); ?></strong>
                            <span>Semanas <?php echo $unidad['semana_inicio']; ?> - <?php echo $unidad['semana_fin']; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="progress-text">Progreso: <span id="progressCount">0</span>/<?php echo $num_semanas; ?> semanas completadas</div>
                    <div class="progress-bar">
                        <div class="progress-bar-fill" id="progressBar" style="width: 0%"></div>
                    </div>
                    
                    <div class="info-box">
                        Complete la información de cada semana. Use los botones de navegación para avanzar entre semanas.
                        <strong>El botón "Enviar para Revisión" estará disponible en la semana 16</strong> cuando haya completado al menos 1 semana.
                    </div>
                    
                    <div class="semanas-nav">
                        <?php for ($i = 1; $i <= $num_semanas; $i++): ?>
                        <button type="button" class="semana-btn <?php echo $i === 1 ? 'active' : ''; ?>" 
                                data-semana="<?php echo $i; ?>" onclick="cambiarSemana(<?php echo $i; ?>)">
                            <?php echo $i; ?>
                        </button>
                        <?php endfor; ?>
                    </div>
                    
                    <form id="formDistribucion">
                        <input type="hidden" name="id_pea" value="<?php echo $id_pea; ?>">
                        
                        <?php for ($i = 1; $i <= $num_semanas; $i++): 
                            $semana = $semanas[$i] ?? [];
                            $id_semana = $semana['id_semana'] ?? 0;
                            $id_unidad = $semana['id_unidad'] ?? null;
                            $nombre_unidad = ($id_unidad && isset($unidades[$id_unidad])) ? $unidades[$id_unidad]['nombre'] : 'Sin asignar';
                            $acts_semana = $id_semana ? ($actividades[$id_semana] ?? []) : [];
                        ?>
                        <div class="semana-container <?php echo $i === 1 ? 'active' : ''; ?>" data-semana="<?php echo $i; ?>">
                            <div class="semana-card">
                                <div class="semana-card-header">
                                    <h3>Semana <?php echo $i; ?> de <?php echo $num_semanas; ?></h3>
                                    <span class="unidad-tag"><?php echo htmlspecialchars($nombre_unidad); ?></span>
                                </div>
                                <div class="semana-card-body">
                                    <input type="hidden" name="semana_<?php echo $i; ?>_id" value="<?php echo $id_semana; ?>">
                                    
                                    <div class="section-block">
                                        <div class="section-block-header" onclick="toggleSection(this)">
                                            <span>📅 Horario y Fechas</span>
                                            <span class="toggle">▼</span>
                                        </div>
                                        <div class="section-block-body">
                                            <div class="fields-row">
                                                <div class="field">
                                                    <label>Fecha Inicio</label>
                                                    <input type="date" name="semana_<?php echo $i; ?>_fecha_inicio" 
                                                           value="<?php echo $semana['fecha_inicio'] ?? ''; ?>">
                                                </div>
                                                <div class="field">
                                                    <label>Fecha Fin</label>
                                                    <input type="date" name="semana_<?php echo $i; ?>_fecha_fin" 
                                                           value="<?php echo $semana['fecha_fin'] ?? ''; ?>">
                                                </div>
                                            </div>
                                            <div class="fields-row">
    <?php if ($pea['jornada'] === 'matutina' || $pea['jornada'] === 'ambas'): ?>
    <div class="field">
        <label>Horario Matutina</label>
        <input type="text" name="semana_<?php echo $i; ?>_horario_matutina" 
               value="<?php echo htmlspecialchars($semana['horario_matutina'] ?? ''); ?>"
               placeholder="Ej: Lunes 07:00-09:00, Miércoles 07:00-09:00">
    </div>
    <?php endif; ?>
    <?php if ($pea['jornada'] === 'nocturna' || $pea['jornada'] === 'ambas'): ?>
    <div class="field">
        <label>Horario Nocturna</label>
        <input type="text" name="semana_<?php echo $i; ?>_horario_nocturna" 
               value="<?php echo htmlspecialchars($semana['horario_nocturna'] ?? ''); ?>"
               placeholder="Ej: Lunes 19:00-22:00">
    </div>
    <?php endif; ?>
</div>
                                        </div>
                                    </div>
                                    
                                    <div class="section-block">
                                        <div class="section-block-header" onclick="toggleSection(this)">
                                            <span>📚 Temas y Contenido</span>
                                            <span class="toggle">▼</span>
                                        </div>
                                        <div class="section-block-body">
                                            <div class="field">
                                                <label>Temas / Subtemas</label>
                                                <textarea name="semana_<?php echo $i; ?>_temas" 
                                                          placeholder="Liste los temas y subtemas a tratar..."><?php echo htmlspecialchars($semana['temas'] ?? ''); ?></textarea>
                                            </div>
                                            <div class="field">
                                                <label>Objetivo de la Semana</label>
                                                <textarea name="semana_<?php echo $i; ?>_objetivo" 
                                                          placeholder="Objetivo específico de la semana..."><?php echo htmlspecialchars($semana['objetivo'] ?? ''); ?></textarea>
                                            </div>
                                            <div class="field">
                                                <label>Resultado de Aprendizaje</label>
                                                <textarea name="semana_<?php echo $i; ?>_resultado_aprendizaje" 
                                                          placeholder="RA específico..."><?php echo htmlspecialchars($semana['resultado_aprendizaje'] ?? ''); ?></textarea>
                                            </div>
                                            <div class="fields-row">
                                                <div class="field">
                                                    <label>Eje Transversal</label>
                                                    <input type="text" name="semana_<?php echo $i; ?>_eje_transversal" 
                                                           value="<?php echo htmlspecialchars($semana['eje_transversal'] ?? ''); ?>"
                                                           placeholder="Ej: Formación ciudadana">
                                                </div>
                                                <div class="field">
                                                    <label>Medioambiente</label>
                                                    <input type="text" name="semana_<?php echo $i; ?>_medioambiente" 
                                                           value="<?php echo htmlspecialchars($semana['medioambiente'] ?? ''); ?>"
                                                           placeholder="Relación con medioambiente">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="section-block">
                                        <div class="section-block-header" onclick="toggleSection(this)">
                                            <span>🔬 Guía de Aprendizaje Práctico Experimental</span>
                                            <span class="toggle">▼</span>
                                        </div>
                                        <div class="section-block-body">
                                            <div class="actividades-container" id="practicas_<?php echo $i; ?>">
                                                <?php 
                                                $num_practica = 1;
                                                foreach ($acts_semana as $act):
                                                    if ($act['tipo'] === 'practica'):
                                                ?>
                                                <div class="actividad-item" data-tipo="practica" data-num="<?php echo $num_practica; ?>">
                                                    <div class="actividad-item-header">
                                                        <h5>Actividad Práctica <?php echo $num_practica; ?></h5>
                                                        <button type="button" class="btn-remove-actividad" onclick="eliminarActividad(this)">✕ Eliminar</button>
                                                    </div>
                                                    <input type="hidden" name="semana_<?php echo $i; ?>_practica_<?php echo $num_practica; ?>_id" value="<?php echo $act['id_actividad']; ?>">
                                                    <div class="fields-row">
                                                        <div class="field field-small">
                                                            <label>Horas con Docente</label>
                                                            <input type="number" name="semana_<?php echo $i; ?>_practica_<?php echo $num_practica; ?>_horas_docente" 
                                                                   value="<?php echo $act['horas_con_docente']; ?>" min="0" max="20">
                                                        </div>
                                                        <div class="field field-small">
                                                            <label>Horas Autónomo</label>
                                                            <input type="number" name="semana_<?php echo $i; ?>_practica_<?php echo $num_practica; ?>_horas" 
                                                                   value="<?php echo $act['horas']; ?>" min="0" max="20">
                                                        </div>
                                                        <div class="field">
                                                            <label>Tema</label>
                                                            <input type="text" name="semana_<?php echo $i; ?>_practica_<?php echo $num_practica; ?>_tema" 
                                                                   value="<?php echo htmlspecialchars($act['tema'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                    <div class="field">
                                                        <label>Descripción</label>
                                                        <textarea name="semana_<?php echo $i; ?>_practica_<?php echo $num_practica; ?>_descripcion"><?php echo htmlspecialchars($act['descripcion'] ?? ''); ?></textarea>
                                                    </div>
                                                    <div class="fields-row">
                                                        <div class="field">
                                                            <label>Resultado de Aprendizaje</label>
                                                            <input type="text" name="semana_<?php echo $i; ?>_practica_<?php echo $num_practica; ?>_ra" 
                                                                   value="<?php echo htmlspecialchars($act['resultado_aprendizaje'] ?? ''); ?>">
                                                        </div>
                                                        <div class="field">
                                                            <label>Metodología / Habilidades Blandas</label>
                                                            <input type="text" name="semana_<?php echo $i; ?>_practica_<?php echo $num_practica; ?>_metodologia" 
                                                                   value="<?php echo htmlspecialchars($act['metodologia'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                    <div class="fields-row">
                                                        <div class="field">
                                                            <label>Recursos</label>
                                                            <input type="text" name="semana_<?php echo $i; ?>_practica_<?php echo $num_practica; ?>_recursos" 
                                                                   value="<?php echo htmlspecialchars($act['recursos'] ?? ''); ?>">
                                                        </div>
                                                        <div class="field">
                                                            <label>Recurso Bibliográfico</label>
                                                            <input type="text" name="semana_<?php echo $i; ?>_practica_<?php echo $num_practica; ?>_bibliografia" 
                                                                   value="<?php echo htmlspecialchars($act['recurso_bibliografico'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                    <div class="fields-row">
                                                        <div class="field">
                                                            <label>Recursos Tecnológicos</label>
                                                            <input type="text" name="semana_<?php echo $i; ?>_practica_<?php echo $num_practica; ?>_tecnologicos" 
                                                                   value="<?php echo htmlspecialchars($act['recursos_tecnologicos'] ?? ''); ?>">
                                                        </div>
                                                        <div class="field">
                                                            <label>Producto Final</label>
                                                            <input type="text" name="semana_<?php echo $i; ?>_practica_<?php echo $num_practica; ?>_producto" 
                                                                   value="<?php echo htmlspecialchars($act['producto_final'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                    <div class="fields-row">
                                                        <div class="field field-small">
                                                            <label>Calificación</label>
                                                            <input type="text" name="semana_<?php echo $i; ?>_practica_<?php echo $num_practica; ?>_calificacion" 
                                                                   value="<?php echo htmlspecialchars($act['calificacion'] ?? ''); ?>" placeholder="X puntos">
                                                        </div>
                                                        <div class="field">
                                                            <label>Fecha Entrega</label>
                                                            <input type="date" name="semana_<?php echo $i; ?>_practica_<?php echo $num_practica; ?>_entrega" 
                                                                   value="<?php echo $act['fecha_entrega'] ?? ''; ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php 
                                                    $num_practica++;
                                                    endif;
                                                endforeach; 
                                                ?>
                                            </div>
                                            <button type="button" class="btn-add-actividad" onclick="agregarActividad(<?php echo $i; ?>, 'practica')">
                                                + Agregar Actividad Práctica
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="section-block">
                                        <div class="section-block-header" onclick="toggleSection(this)">
                                            <span>📖 Trabajo Autónomo</span>
                                            <span class="toggle">▼</span>
                                        </div>
                                        <div class="section-block-body">
                                            <div class="actividades-container" id="autonomas_<?php echo $i; ?>">
                                                <?php 
                                                $num_autonoma = 1;
                                                foreach ($acts_semana as $act):
                                                    if ($act['tipo'] === 'autonoma'):
                                                ?>
                                                <div class="actividad-item" data-tipo="autonoma" data-num="<?php echo $num_autonoma; ?>">
                                                    <div class="actividad-item-header">
                                                        <h5>Actividad Autónoma <?php echo $num_autonoma; ?></h5>
                                                        <button type="button" class="btn-remove-actividad" onclick="eliminarActividad(this)">✕ Eliminar</button>
                                                    </div>
                                                    <input type="hidden" name="semana_<?php echo $i; ?>_autonoma_<?php echo $num_autonoma; ?>_id" value="<?php echo $act['id_actividad']; ?>">
                                                    <div class="fields-row">
                                                        <div class="field field-small">
                                                            <label>Horas</label>
                                                            <input type="number" name="semana_<?php echo $i; ?>_autonoma_<?php echo $num_autonoma; ?>_horas" 
                                                                   value="<?php echo $act['horas']; ?>" min="0" max="20">
                                                        </div>
                                                        <div class="field">
                                                            <label>Tema</label>
                                                            <input type="text" name="semana_<?php echo $i; ?>_autonoma_<?php echo $num_autonoma; ?>_tema" 
                                                                   value="<?php echo htmlspecialchars($act['tema'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                    <div class="field">
                                                        <label>Descripción</label>
                                                        <textarea name="semana_<?php echo $i; ?>_autonoma_<?php echo $num_autonoma; ?>_descripcion"><?php echo htmlspecialchars($act['descripcion'] ?? ''); ?></textarea>
                                                    </div>
                                                    <div class="fields-row">
                                                        <div class="field">
                                                            <label>Resultado de Aprendizaje</label>
                                                            <input type="text" name="semana_<?php echo $i; ?>_autonoma_<?php echo $num_autonoma; ?>_ra" 
                                                                   value="<?php echo htmlspecialchars($act['resultado_aprendizaje'] ?? ''); ?>">
                                                        </div>
                                                        <div class="field">
                                                            <label>Metodología / Habilidades Blandas</label>
                                                            <input type="text" name="semana_<?php echo $i; ?>_autonoma_<?php echo $num_autonoma; ?>_metodologia" 
                                                                   value="<?php echo htmlspecialchars($act['metodologia'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                    <div class="fields-row">
                                                        <div class="field">
                                                            <label>Recursos</label>
                                                            <input type="text" name="semana_<?php echo $i; ?>_autonoma_<?php echo $num_autonoma; ?>_recursos" 
                                                                   value="<?php echo htmlspecialchars($act['recursos'] ?? ''); ?>">
                                                        </div>
                                                        <div class="field">
                                                            <label>Recurso Bibliográfico</label>
                                                            <input type="text" name="semana_<?php echo $i; ?>_autonoma_<?php echo $num_autonoma; ?>_bibliografia" 
                                                                   value="<?php echo htmlspecialchars($act['recurso_bibliografico'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                    <div class="fields-row">
                                                        <div class="field">
                                                            <label>Recursos Tecnológicos</label>
                                                            <input type="text" name="semana_<?php echo $i; ?>_autonoma_<?php echo $num_autonoma; ?>_tecnologicos" 
                                                                   value="<?php echo htmlspecialchars($act['recursos_tecnologicos'] ?? ''); ?>">
                                                        </div>
                                                        <div class="field">
                                                            <label>Producto Final</label>
                                                            <input type="text" name="semana_<?php echo $i; ?>_autonoma_<?php echo $num_autonoma; ?>_producto" 
                                                                   value="<?php echo htmlspecialchars($act['producto_final'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                    <div class="fields-row">
                                                        <div class="field field-small">
                                                            <label>Calificación</label>
                                                            <input type="text" name="semana_<?php echo $i; ?>_autonoma_<?php echo $num_autonoma; ?>_calificacion" 
                                                                   value="<?php echo htmlspecialchars($act['calificacion'] ?? ''); ?>" placeholder="X puntos">
                                                        </div>
                                                        <div class="field">
                                                            <label>Fecha Entrega</label>
                                                            <input type="date" name="semana_<?php echo $i; ?>_autonoma_<?php echo $num_autonoma; ?>_entrega" 
                                                                   value="<?php echo $act['fecha_entrega'] ?? ''; ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php 
                                                    $num_autonoma++;
                                                    endif;
                                                endforeach; 
                                                ?>
                                            </div>
                                            <button type="button" class="btn-add-actividad" onclick="agregarActividad(<?php echo $i; ?>, 'autonoma')">
                                                + Agregar Actividad Autónoma
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="section-block">
                                        <div class="section-block-header" onclick="toggleSection(this)">
                                            <span>⏱️ Resumen de Horas</span>
                                            <span class="toggle">▼</span>
                                        </div>
                                        <div class="section-block-body">
                                            <div class="fields-row">
                                                <div class="field field-small">
                                                    <label>Horas Docencia</label>
                                                    <input type="number" name="semana_<?php echo $i; ?>_horas_docencia" 
                                                           value="<?php echo $semana['horas_docencia'] ?? 0; ?>" min="0" max="20">
                                                </div>
                                                <div class="field field-small">
                                                    <label>H. Prácticas con Docente</label>
                                                    <input type="number" name="semana_<?php echo $i; ?>_horas_practicas_docente" 
                                                           value="<?php echo $semana['horas_practicas_con_docente'] ?? 0; ?>" min="0" max="20">
                                                </div>
                                                <div class="field field-small">
                                                    <label>H. Prácticas Autónomo</label>
                                                    <input type="number" name="semana_<?php echo $i; ?>_horas_practicas_autonomo" 
                                                           value="<?php echo $semana['horas_practicas_autonomo'] ?? 0; ?>" min="0" max="20">
                                                </div>
                                                <div class="field field-small">
                                                    <label>H. Trabajo Autónomo</label>
                                                    <input type="number" name="semana_<?php echo $i; ?>_horas_autonomo" 
                                                           value="<?php echo $semana['horas_trabajo_autonomo'] ?? 0; ?>" min="0" max="20">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="semana-nav-buttons">
                                        <button type="button" class="btn-nav-semana btn-prev-semana" 
                                                onclick="cambiarSemana(<?php echo $i - 1; ?>)" 
                                                <?php echo $i === 1 ? 'disabled' : ''; ?>>
                                            ← Semana Anterior
                                        </button>
                                        
                                        <?php if ($i < $num_semanas): ?>
                                        <button type="button" class="btn-nav-semana btn-next-semana" onclick="guardarYSiguiente(<?php echo $i + 1; ?>)">
                                            Siguiente Semana →
                                        </button>
                                        <?php else: ?>
<button type="button" class="btn-nav-semana btn-enviar-revision" onclick="mostrarModalFinalizar()" id="btnFinalizarPEA">
    ✓ Finalizar PEA
</button>
<?php endif; ?>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </form>
                </div>
                
                <div class="form-actions">
    <a href="mis_peas.php" class="btn btn-secondary">← Volver a Mis PEAs</a>
    <a href="crear_pea.php?id_pea=<?php echo $id_pea; ?>" class="btn btn-secondary">✏️ Editar Datos Generales</a>
    <button type="button" class="btn btn-primary" onclick="guardarTodo()">💾 Guardar</button>
</div>
            </div>
        </div>
    </div>
    
    <div class="save-indicator" id="saveIndicator">Guardando...</div>
    
 <!-- Modal de Confirmación para Finalizar -->
<div class="modal-overlay" id="modalFinalizar">
    <div class="modal-content">
        <h3>📋 Finalizar PEA</h3>
        <p>Está a punto de finalizar la elaboración de su PEA.</p>
        <div class="info-box" style="font-size: 0.85rem; margin-bottom: 20px;">
            <strong>Información:</strong>
            <br></br>
            Se guardarán todos los datos. Podrá revisar, descargar el documento Word y editarlo antes de enviarlo al coordinador en Mis PEAs
        </div>
        <p>Semanas completadas: <strong><span id="modalSemanasCount">0</span>/<?php echo $num_semanas; ?></strong></p>
        <div class="modal-buttons">
            <button type="button" class="btn btn-secondary" onclick="cerrarModalFinalizar()">Cancelar</button>
            <button type="button" class="btn btn-success" onclick="confirmarFinalizar()" id="btnConfirmarFinalizar">
                Finalizar y Guardar ✓
            </button>
        </div>
    </div>
</div>

<!-- Modal de Éxito -->
<div class="modal-overlay" id="modalExito">
    <div class="modal-content" style="text-align: center;">
        <div style="font-size: 4rem; margin-bottom: 15px;">😀</div>
        <h3 style="color: #059669; font-size: 1.5rem;">¡Éxito!</h3>
        <h2 style="color: #1e3a5f; margin: 15px 0;">Su PEA ha sido realizado con éxito</h2>
        <p style="font-size: 1.1rem; color: #374151; margin-bottom: 20px;">
            <strong>¡Es hora de afinar y enviar!</strong>
        </p>
        <div class="info-box success" style="text-align: left; margin-bottom: 25px;">
            <strong>Siguiente paso:</strong><br><br>
            En la sección <strong>"Mis PEAs"</strong> puede:
            <ul style="margin: 10px 0 0 20px; line-height: 1.8;">
                <li>📄 <strong>Descargar el documento Word</strong> para revisarlo</li>
                <li>✏️ <strong>Editar</strong> cualquier dato si es necesario</li>
                <li>📤 <strong>Enviar al Coordinador</strong> cuando esté listo</li>
            </ul>
        </div>
        <button type="button" class="btn btn-enviar-revision" onclick="irAMisPEAs()" style="padding: 14px 40px; font-size: 1rem;">
            Ir a Mis PEAs →
        </button>
    </div>
</div>
    
    <script>
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
    toast.classList.add('hiding');
    setTimeout(() => toast.remove(), 300);
}

        const NUM_SEMANAS = <?php echo $num_semanas; ?>;
        const ID_PEA = <?php echo $id_pea; ?>;
        let actividadCounters = {};
        
        for (let i = 1; i <= NUM_SEMANAS; i++) {
            actividadCounters[i] = {
                practica: document.querySelectorAll(`#practicas_${i} .actividad-item`).length,
                autonoma: document.querySelectorAll(`#autonomas_${i} .actividad-item`).length
            };
        }
        
        function cambiarSemana(num) {
            if (num < 1 || num > NUM_SEMANAS) return;
            
            document.querySelectorAll('.semana-container').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.semana-btn').forEach(b => b.classList.remove('active'));
            
            document.querySelector(`.semana-container[data-semana="${num}"]`).classList.add('active');
            document.querySelector(`.semana-btn[data-semana="${num}"]`).classList.add('active');
            
            window.scrollTo({ top: 200, behavior: 'smooth' });
        }
        
        function guardarYSiguiente(siguienteSemana) {
            
            
            const formData = new FormData(document.getElementById('formDistribucion'));
            
            fetch('php/pea/guardar_semanas.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
    
    showToast('success', 'Guardado', 'Semana guardada correctamente');
    marcarSemanasCompletadas();
    actualizarProgreso();
    setTimeout(() => {
        cambiarSemana(siguienteSemana);
    }, 500);
} else {
    
    showToast('error', 'Error al guardar', data.message);
}
            })
            .catch(error => {
    
    showToast('error', 'Error de conexión', 'No se pudo conectar con el servidor');
    console.error(error);
});
        }
        
        function toggleSection(header) {
            const body = header.nextElementSibling;
            const toggle = header.querySelector('.toggle');
            body.classList.toggle('collapsed');
            toggle.textContent = body.classList.contains('collapsed') ? '►' : '▼';
        }
        
        function agregarActividad(semana, tipo) {
            actividadCounters[semana][tipo]++;
            const num = actividadCounters[semana][tipo];
            const container = document.getElementById(`${tipo === 'practica' ? 'practicas' : 'autonomas'}_${semana}`);
            const tipoLabel = tipo === 'practica' ? 'Práctica' : 'Autónoma';
            
            let horasExtra = '';
            if (tipo === 'practica') {
                horasExtra = `
                    <div class="field field-small">
                        <label>Horas con Docente</label>
                        <input type="number" name="semana_${semana}_${tipo}_${num}_horas_docente" value="0" min="0" max="20">
                    </div>
                `;
            }
            
            const html = `
                <div class="actividad-item" data-tipo="${tipo}" data-num="${num}">
                    <div class="actividad-item-header">
                        <h5>Actividad ${tipoLabel} ${num}</h5>
                        <button type="button" class="btn-remove-actividad" onclick="eliminarActividad(this)">✕ Eliminar</button>
                    </div>
                    <input type="hidden" name="semana_${semana}_${tipo}_${num}_id" value="">
                    <div class="fields-row">
                        ${horasExtra}
                        <div class="field field-small">
                            <label>Horas${tipo === 'practica' ? ' Autónomo' : ''}</label>
                            <input type="number" name="semana_${semana}_${tipo}_${num}_horas" value="0" min="0" max="20">
                        </div>
                        <div class="field">
                            <label>Tema</label>
                            <input type="text" name="semana_${semana}_${tipo}_${num}_tema">
                        </div>
                    </div>
                    <div class="field">
                        <label>Descripción</label>
                        <textarea name="semana_${semana}_${tipo}_${num}_descripcion"></textarea>
                    </div>
                    <div class="fields-row">
                        <div class="field">
                            <label>Resultado de Aprendizaje</label>
                            <input type="text" name="semana_${semana}_${tipo}_${num}_ra">
                        </div>
                        <div class="field">
                            <label>Metodología / Habilidades Blandas</label>
                            <input type="text" name="semana_${semana}_${tipo}_${num}_metodologia">
                        </div>
                    </div>
                    <div class="fields-row">
                        <div class="field">
                            <label>Recursos</label>
                            <input type="text" name="semana_${semana}_${tipo}_${num}_recursos">
                        </div>
                        <div class="field">
                            <label>Recurso Bibliográfico</label>
                            <input type="text" name="semana_${semana}_${tipo}_${num}_bibliografia">
                        </div>
                    </div>
                    <div class="fields-row">
                        <div class="field">
                            <label>Recursos Tecnológicos</label>
                            <input type="text" name="semana_${semana}_${tipo}_${num}_tecnologicos">
                        </div>
                        <div class="field">
                            <label>Producto Final</label>
                            <input type="text" name="semana_${semana}_${tipo}_${num}_producto">
                        </div>
                    </div>
                    <div class="fields-row">
                        <div class="field field-small">
                            <label>Calificación</label>
                            <input type="text" name="semana_${semana}_${tipo}_${num}_calificacion" placeholder="X puntos">
                        </div>
                        <div class="field">
                            <label>Fecha Entrega</label>
                            <input type="date" name="semana_${semana}_${tipo}_${num}_entrega">
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', html);
    showToast('info', 'Actividad agregada', 'Complete los datos y guarde los cambios');
        }
        
       function eliminarActividad(btn) {
    if (confirm('¿Eliminar esta actividad?')) {
        btn.closest('.actividad-item').remove();
        showToast('info', 'Actividad eliminada', 'Recuerde guardar los cambios');
    }
}
        
      
        
        function guardarTodo() {
           
            
            const formData = new FormData(document.getElementById('formDistribucion'));
            
            fetch('php/pea/guardar_semanas.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
    
    showToast('success', 'Guardado', 'Todos los cambios han sido guardados');
    marcarSemanasCompletadas();
    actualizarProgreso();
} else {
    
    showToast('error', 'Error al guardar', data.message);
}
            })
            .catch(error => {
    
    showToast('error', 'Error de conexión', 'No se pudo conectar con el servidor');
    console.error(error);
});
        }
        
        function contarSemanasCompletadas() {
            let count = 0;
            for (let i = 1; i <= NUM_SEMANAS; i++) {
                const temasField = document.querySelector(`[name="semana_${i}_temas"]`);
                if (temasField && temasField.value.trim() !== '') {
                    count++;
                }
            }
            return count;
        }
        
        function marcarSemanasCompletadas() {
            for (let i = 1; i <= NUM_SEMANAS; i++) {
                const temasField = document.querySelector(`[name="semana_${i}_temas"]`);
                const btn = document.querySelector(`.semana-btn[data-semana="${i}"]`);
                
                if (temasField && temasField.value.trim() !== '') {
                    btn.classList.add('completed');
                } else {
                    btn.classList.remove('completed');
                }
            }
        }
        
        function actualizarProgreso() {
            const completadas = contarSemanasCompletadas();
            const porcentaje = (completadas / NUM_SEMANAS) * 100;
            
            document.getElementById('progressCount').textContent = completadas;
            document.getElementById('progressBar').style.width = porcentaje + '%';
            document.getElementById('modalSemanasCount').textContent = completadas;
            
            const btnFinalizar = document.getElementById('btnFinalizarPEA');
            const btnConfirmar = document.getElementById('btnConfirmarFinalizar');

            if (completadas >= 1) {
                if (btnFinalizar) btnFinalizar.disabled = false;
                if (btnConfirmar) btnConfirmar.disabled = false;
            } else {
                if (btnFinalizar) btnFinalizar.disabled = true;
                if (btnConfirmar) btnConfirmar.disabled = true;
            }
        }
        
   
        // Mostrar modal de finalización
function mostrarModalFinalizar() {
    const completadas = contarSemanasCompletadas();
    
    if (completadas < 1) {
        showToast('warning', 'Semanas incompletas', 'Debe completar al menos 1 semana (llenar el campo "Temas") antes de finalizar.');
        return;
    }
    
    document.getElementById('modalSemanasCount').textContent = completadas;
    document.getElementById('modalFinalizar').classList.add('show');
}

// Cerrar modal de finalización
function cerrarModalFinalizar() {
    document.getElementById('modalFinalizar').classList.remove('show');
}

// Confirmar finalización (solo guarda, NO envía al coordinador)
function confirmarFinalizar() {
    cerrarModalFinalizar();
    
    const formData = new FormData(document.getElementById('formDistribucion'));
    // NO agregamos 'enviar_revision', solo guardamos
    
    fetch('php/pea/guardar_semanas.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', '¡Guardado!', 'PEA guardado correctamente');
            marcarSemanasCompletadas();
            actualizarProgreso();
            // Mostrar modal de éxito
            setTimeout(() => {
                document.getElementById('modalExito').classList.add('show');
            }, 500);
        } else {
            showToast('error', 'Error al guardar', data.message);
        }
    })
    .catch(error => {
        showToast('error', 'Error de conexión', 'No se pudo conectar con el servidor');
        console.error(error);
    });
}

// Ir a Mis PEAs
function irAMisPEAs() {
    window.location.href = 'mis_peas.php';
}

// Cerrar modales al hacer clic fuera
document.getElementById('modalFinalizar').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalFinalizar();
    }
});

document.getElementById('modalExito').addEventListener('click', function(e) {
    // No cerrar el modal de éxito al hacer clic fuera, forzar ir a Mis PEAs
});
        
        
      
        
        document.addEventListener('DOMContentLoaded', function() {
            marcarSemanasCompletadas();
            actualizarProgreso();
        });
    </script>
    <!-- Contenedor de Notificaciones Toast -->
<div class="toast-container" id="toastContainer"></div>
</body>
</html>
