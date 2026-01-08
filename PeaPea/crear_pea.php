<?php
session_start();

if (!isset($_SESSION['id_docente'])) {
    header('Location: login.php');
    exit();
}

require_once 'php/conexion.php';

$id_docente = $_SESSION['id_docente'];
$id_asignacion = isset($_GET['id_asignacion']) ? intval($_GET['id_asignacion']) : 0;
$id_pea = isset($_GET['id_pea']) ? intval($_GET['id_pea']) : 0;

// Modo edición o creación
$modo_edicion = ($id_pea > 0);
$pea_existente = null;
$unidades_existentes = [];

if ($modo_edicion) {
    // MODO EDICIÓN: Cargar PEA existente
    $sql_pea = "SELECT p.*, 
                       a.id_asignacion, a.paralelo, a.jornada, a.id_periodo,
                       asig.nombre as asignatura, asig.codigo, asig.nivel, asig.total_horas,
                       asig.descripcion as asig_descripcion, asig.objetivo as asig_objetivo,
                       c.nombre as carrera,
                       per.nombre as periodo_nombre, per.num_semanas,
                       d.nombres, d.apellidos, d.email, d.celular, d.titulo
                FROM pea p
                INNER JOIN asignacion a ON p.id_asignacion = a.id_asignacion
                INNER JOIN asignatura asig ON a.id_asignatura = asig.id_asignatura
                INNER JOIN carrera c ON asig.id_carrera = c.id_carrera
                INNER JOIN periodo_academico per ON a.id_periodo = per.id_periodo
                INNER JOIN docente d ON a.id_docente = d.id_docente
                WHERE p.id_pea = ? AND a.id_docente = ?";
    
    $stmt = mysqli_prepare($cnx, $sql_pea);
    mysqli_stmt_bind_param($stmt, "ii", $id_pea, $id_docente);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $datos = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$datos) {
        $_SESSION['mensaje_error'] = "No tienes permiso para editar este PEA.";
        header('Location: mis_peas.php');
        exit();
    }
    
    // Verificar estado (solo borrador o rechazado)
    if (!in_array($datos['estado'], ['borrador', 'rechazado'])) {
        $_SESSION['mensaje_error'] = "Este PEA no puede editarse en su estado actual.";
        header('Location: mis_peas.php');
        exit();
    }
    
    $pea_existente = $datos;
    $id_asignacion = $datos['id_asignacion'];
    
    // Cargar unidades existentes
    $sql_unidades = "SELECT * FROM unidades_pea WHERE id_pea = ? ORDER BY numero";
    $stmt = mysqli_prepare($cnx, $sql_unidades);
    mysqli_stmt_bind_param($stmt, "i", $id_pea);
    mysqli_stmt_execute($stmt);
    $result_unidades = mysqli_stmt_get_result($stmt);
    while ($u = mysqli_fetch_assoc($result_unidades)) {
        $unidades_existentes[] = $u;
    }
    mysqli_stmt_close($stmt);
    
} else {
    // MODO CREACIÓN: Verificar asignación
    if (!$id_asignacion) {
        $_SESSION['mensaje_error'] = "ID de asignación no especificado.";
        header('Location: mis_peas.php');
        exit();
    }
    
    $sql_verificar = "SELECT a.*, 
                             asig.nombre as asignatura, asig.codigo, asig.nivel, asig.total_horas,
                             asig.descripcion as asig_descripcion, asig.objetivo as asig_objetivo,
                             c.nombre as carrera,
                             p.nombre as periodo_nombre, p.num_semanas,
                             d.nombres, d.apellidos, d.email, d.celular, d.titulo
                      FROM asignacion a
                      INNER JOIN asignatura asig ON a.id_asignatura = asig.id_asignatura
                      INNER JOIN carrera c ON asig.id_carrera = c.id_carrera
                      INNER JOIN periodo_academico p ON a.id_periodo = p.id_periodo
                      INNER JOIN docente d ON a.id_docente = d.id_docente
                      WHERE a.id_asignacion = ? AND a.id_docente = ?";
    
    $stmt = mysqli_prepare($cnx, $sql_verificar);
    mysqli_stmt_bind_param($stmt, "ii", $id_asignacion, $id_docente);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $datos = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$datos) {
        $_SESSION['mensaje_error'] = "No tienes permiso para crear un PEA en esta asignación.";
        header('Location: mis_peas.php');
        exit();
    }
    
    // Verificar que no exista ya un PEA para esta asignación
    $sql_existe = "SELECT id_pea FROM pea WHERE id_asignacion = ?";
    $stmt = mysqli_prepare($cnx, $sql_existe);
    mysqli_stmt_bind_param($stmt, "i", $id_asignacion);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) > 0) {
        $_SESSION['mensaje_error'] = "Ya existe un PEA para esta asignación.";
        header('Location: mis_peas.php');
        exit();
    }
    mysqli_stmt_close($stmt);
}

// Pre-llenar datos (común para ambos modos)
$carrera = $datos['carrera'];
$asignatura = $datos['asignatura'];
$codigo = $datos['codigo'];
$nivel = $datos['nivel'];
$total_horas = $datos['total_horas'];
$paralelo = $datos['paralelo'];
$jornada = ucfirst($datos['jornada']);
$periodo = $datos['periodo_nombre'];
$num_semanas = 16;
$docente_nombre = $datos['nombres'] . ' ' . $datos['apellidos'];
$docente_email = $datos['email'];
$docente_celular = $datos['celular'];
$docente_titulo = $datos['titulo'];
$descripcion_asig = $datos['asig_descripcion'] ?? '';
$objetivo_asig = $datos['asig_objetivo'] ?? '';

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
    <title><?php echo $modo_edicion ? 'Editar' : 'Crear'; ?> PEA - <?php echo htmlspecialchars($asignatura); ?></title>
    <link rel="stylesheet" href="css/coordinador.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        /* Container principal */
        .pea-container {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        /* Header del formulario */
        .pea-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2d4a6f 100%);
            color: white;
            padding: 20px 25px;
        }
        .pea-header h2 {
            margin: 0 0 5px 0;
            font-size: 1.25rem;
            font-weight: 600;
        }
        .pea-header p {
            margin: 0;
            opacity: 0.85;
            font-size: 0.875rem;
        }
        .pea-header-badges {
            display: flex;
            gap: 10px;
            margin-top: 12px;
        }
        .pea-header-badges span {
            background: rgba(255,255,255,0.15);
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        /* Cuerpo del formulario */
        .pea-body {
            padding: 25px;
        }
        
        /* Navegación de pasos */
        .steps-nav {
            display: flex;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 25px;
            overflow-x: auto;
        }
        .step-tab {
            padding: 12px 20px;
            font-size: 0.875rem;
            color: #6b7280;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
        }
        .step-tab:hover {
            color: #1e3a5f;
        }
        .step-tab.active {
            color: #1e3a5f;
            border-bottom-color: #1e3a5f;
            font-weight: 500;
        }
        .step-tab.completed {
            color: #059669;
        }
        .step-tab.completed::before {
            content: '✓ ';
        }
        
        /* Secciones */
        .form-section {
            display: none;
        }
        .form-section.active {
            display: block;
        }
        
        /* Grupos de campos */
        .field-group {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .field-group-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        /* Grid de campos */
        .fields-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .fields-grid.cols-3 {
            grid-template-columns: repeat(3, 1fr);
        }
        .fields-grid.cols-1 {
            grid-template-columns: 1fr;
        }
        
        /* Campos */
        .field {
            display: flex;
            flex-direction: column;
        }
        .field label {
            font-size: 0.8rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 5px;
        }
        .field label .required {
            color: #dc2626;
        }
        .field input,
        .field select,
        .field textarea {
            padding: 9px 12px;
            border: 1px solid #d1d5db;
            border-radius: 5px;
            font-size: 0.875rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            outline: none;
            border-color: #1e3a5f;
            box-shadow: 0 0 0 3px rgba(30,58,95,0.1);
        }
        .field input:disabled,
        .field input[readonly] {
            background: #f3f4f6;
            color: #6b7280;
        }
        .field textarea {
            min-height: 80px;
            resize: vertical;
        }
        .field .hint {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 4px;
        }
        
        /* Unidades dinámicas */
        .unidades-container {
            margin-top: 15px;
        }
        .unidad-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        .unidad-card-header {
            background: #f3f4f6;
            padding: 12px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e5e7eb;
        }
        .unidad-card-header h4 {
            margin: 0;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
        }
        .unidad-card-body {
            padding: 15px;
        }
        .btn-remove-unidad {
            background: none;
            border: none;
            color: #dc2626;
            cursor: pointer;
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .btn-remove-unidad:hover {
            background: #fef2f2;
        }
        
        .btn-add-unidad {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 16px;
            background: #f3f4f6;
            border: 1px dashed #9ca3af;
            border-radius: 6px;
            color: #4b5563;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-add-unidad:hover {
            background: #e5e7eb;
            border-color: #6b7280;
        }
        
        /* Selector de semanas */
        .semanas-selector {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .semanas-selector label {
            font-size: 0.8rem;
            margin-bottom: 0;
        }
        .semanas-selector select {
            padding: 6px 10px;
            font-size: 0.8rem;
        }
        
        /* RA Container */
        .ra-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .ra-card h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin: 0 0 12px 0;
        }
        
        /* Botones de navegación */
        .form-nav {
            display: flex;
            justify-content: space-between;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            margin-top: 25px;
            align-items: center;
            position: relative;
            
        }
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        .btn-secondary:hover {
            background: #e5e7eb;
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
            position: absolute;
            left: 88%;
        }
        .btn-success:hover {
            background: #047857;
        }
        
        /* Info box */
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
            min-width: 300px;
            max-width: 400px;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: toastIn 0.3s ease;
            font-size: 0.9rem;
        }
        .toast.hiding { animation: toastOut 0.3s ease forwards; }
        .toast-success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; }
        .toast-error { background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%); color: white; }
        .toast-warning { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: #212529; }
        .toast-info { background: linear-gradient(135deg, #17a2b8 0%, #3498db 100%); color: white; }
        .toast-icon { font-size: 1.4rem; flex-shrink: 0; }
        .toast-content { flex: 1; }
        .toast-title { font-weight: 600; margin-bottom: 2px; }
        .toast-message { opacity: 0.95; font-size: 0.85rem; }
        .toast-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: inherit;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        .toast-close:hover { background: rgba(255,255,255,0.3); }
        @keyframes toastIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes toastOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .fields-grid,
            .fields-grid.cols-3 {
                grid-template-columns: 1fr;
            }
            .steps-nav {
                flex-wrap: nowrap;
            }
            .step-tab {
                padding: 10px 15px;
                font-size: 0.8rem;
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
                <h1><?php echo $modo_edicion ? 'Editar' : 'Crear Nuevo'; ?> PEA</h1>
                <p><?php echo $modo_edicion ? 'Modifique los datos generales del Plan de Estudio' : 'Complete el Plan de Estudio de la Asignatura'; ?></p>
            </div>
            
            <?php if ($modo_edicion && $pea_existente['estado'] === 'rechazado' && !empty($pea_existente['observaciones_rechazo'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        showToast('warning', 'PEA Rechazado', '<?php echo addslashes($pea_existente['observaciones_rechazo']); ?>', 8000);
    });
</script>
<?php endif; ?>
            
            <div class="pea-container">
                <div class="pea-header">
                    <h2><?php echo htmlspecialchars($asignatura); ?></h2>
                    <p><?php echo htmlspecialchars($carrera); ?></p>
                    <div class="pea-header-badges">
                        <span>Nivel <?php echo $nivel; ?></span>
                        <span>Paralelo <?php echo $paralelo; ?></span>
                        <span><?php echo $total_horas; ?> horas</span>
                        <span><?php echo $num_semanas; ?> semanas</span>
                        <?php if ($modo_edicion): ?>
                        <span style="background: rgba(255,193,7,0.3);">Editando</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="pea-body">
                    <!-- Navegación de pasos -->
                    <div class="steps-nav">
                        <div class="step-tab active" data-step="1">1. Datos Generales</div>
                        <div class="step-tab" data-step="2">2. Información Docente</div>
                        <div class="step-tab" data-step="3">3. Contenido Académico</div>
                        <div class="step-tab" data-step="4">4. Unidades</div>
                        <div class="step-tab" data-step="5">5. Resultados de Aprendizaje</div>
                    </div>
                    
                    <form id="formPEA" action="php/pea/guardar_pea.php" method="POST">
                        <input type="hidden" name="id_asignacion" value="<?php echo $id_asignacion; ?>">
                        <input type="hidden" name="id_pea" value="<?php echo $id_pea; ?>">
                        <input type="hidden" name="modo_edicion" value="<?php echo $modo_edicion ? '1' : '0'; ?>">
                        <input type="hidden" name="num_semanas" value="<?php echo $num_semanas; ?>">
                        
                        <!-- SECCIÓN 1: Datos Generales -->
                        <div class="form-section active" data-section="1">
                            <div class="info-box">
                                Los campos sombreados se han pre-llenado automáticamente desde el sistema y no pueden editarse.
                            </div>
                            
                            <div class="field-group">
                                <h3 class="field-group-title">Información de la Carrera</h3>
                                <div class="fields-grid">
                                    <div class="field">
                                        <label>Carrera</label>
                                        <input type="text" value="<?php echo htmlspecialchars($carrera); ?>" readonly>
                                        <input type="hidden" name="carrera" value="<?php echo htmlspecialchars($carrera); ?>">
                                    </div>
                                    <div class="field">
                                        <label>Periodo Académico</label>
                                        <input type="text" value="<?php echo htmlspecialchars($periodo); ?>" readonly>
                                        <input type="hidden" name="periodo" value="<?php echo htmlspecialchars($periodo); ?>">
                                    </div>
                                    <div class="field">
                                        <label>Modalidad <span class="required">*</span></label>
                                        <select name="modalidad" required>
                                            <option value="Presencial" <?php echo ($pea_existente['modalidad'] ?? '') === 'Presencial' ? 'selected' : ''; ?>>Presencial</option>
                                            <option value="Virtual" <?php echo ($pea_existente['modalidad'] ?? '') === 'Virtual' ? 'selected' : ''; ?>>Virtual</option>
                                            <option value="Híbrida" <?php echo ($pea_existente['modalidad'] ?? '') === 'Híbrida' ? 'selected' : ''; ?>>Híbrida</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label>Jornada</label>
                                        <input type="text" value="<?php echo htmlspecialchars($jornada); ?>" readonly>
                                        <input type="hidden" name="jornada" value="<?php echo htmlspecialchars($jornada); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="field-group">
                                <h3 class="field-group-title">Información de la Asignatura</h3>
                                <div class="fields-grid">
                                    <div class="field">
                                        <label>Nombre de la Asignatura</label>
                                        <input type="text" value="<?php echo htmlspecialchars($asignatura); ?>" readonly>
                                        <input type="hidden" name="asignatura" value="<?php echo htmlspecialchars($asignatura); ?>">
                                    </div>
                                    <div class="field">
                                        <label>Código</label>
                                        <input type="text" value="<?php echo htmlspecialchars($codigo); ?>" readonly>
                                        <input type="hidden" name="codigo_asignatura" value="<?php echo htmlspecialchars($codigo); ?>">
                                    </div>
                                    <div class="field">
                                        <label>Nivel / Ciclo</label>
                                        <input type="text" value="Nivel <?php echo $nivel; ?>" readonly>
                                        <input type="hidden" name="ciclo" value="<?php echo $nivel; ?>">
                                    </div>
                                    <div class="field">
                                        <label>Total de Horas</label>
                                        <input type="text" value="<?php echo $total_horas; ?>" readonly>
                                        <input type="hidden" name="horas_total" value="<?php echo $total_horas; ?>">
                                    </div>
                                    <div class="field">
                                        <label>Campo de Formación <span class="required">*</span></label>
                                        <select name="campo_formacion" required>
                                            <option value="">Seleccione...</option>
                                            <option value="Fundamentos teóricos" <?php echo ($pea_existente['campo_formacion'] ?? '') === 'Fundamentos teóricos' ? 'selected' : ''; ?>>Fundamentos teóricos</option>
                                            <option value="Praxis profesional" <?php echo ($pea_existente['campo_formacion'] ?? '') === 'Praxis profesional' ? 'selected' : ''; ?>>Praxis profesional</option>
                                            <option value="Epistemología y metodología de la investigación" <?php echo ($pea_existente['campo_formacion'] ?? '') === 'Epistemología y metodología de la investigación' ? 'selected' : ''; ?>>Epistemología y metodología de la investigación</option>
                                            <option value="Integración de contextos, saberes y cultura" <?php echo ($pea_existente['campo_formacion'] ?? '') === 'Integración de contextos, saberes y cultura' ? 'selected' : ''; ?>>Integración de contextos, saberes y cultura</option>
                                            <option value="Comunicación y lenguajes" <?php echo ($pea_existente['campo_formacion'] ?? '') === 'Comunicación y lenguajes' ? 'selected' : ''; ?>>Comunicación y lenguajes</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label>Unidad de Organización Curricular <span class="required">*</span></label>
                                        <select name="unidad_curricular" required>
                                            <option value="">Seleccione...</option>
                                            <option value="Básica" <?php echo ($pea_existente['unidad_curricular'] ?? '') === 'Básica' ? 'selected' : ''; ?>>Básica</option>
                                            <option value="Profesional" <?php echo ($pea_existente['unidad_curricular'] ?? '') === 'Profesional' ? 'selected' : ''; ?>>Profesional</option>
                                            <option value="Titulación" <?php echo ($pea_existente['unidad_curricular'] ?? '') === 'Titulación' ? 'selected' : ''; ?>>Titulación</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="field-group">
                                <h3 class="field-group-title">Distribución de Horas</h3>
                                <div class="fields-grid cols-3">
                                    <div class="field">
                                        <label>Horas de Docencia <span class="required">*</span></label>
                                        <input type="number" name="horas_docencia" min="0" max="200" required placeholder="0" value="<?php echo $pea_existente['horas_docencia'] ?? ''; ?>">
                                        <span class="hint">Nº de horas Docencia</span>
                                    </div>
                                    <div class="field">
                                        <label>Horas con Docente <span class="required">*</span></label>
                                        <input type="number" name="horas_contacto" min="0" max="200" required placeholder="0" value="<?php echo $pea_existente['horas_practicas'] ?? ''; ?>">
                                        <span class="hint">En contacto con docente</span>
                                    </div>
                                    <div class="field">
                                        <label>Horas Práctico Autónomo <span class="required">*</span></label>
                                        <input type="number" name="horas_practico_autonomo" min="0" max="200" required placeholder="0" value="<?php echo $pea_existente['horas_practico_autonomo'] ?? ''; ?>">
                                        <span class="hint">Aprendizaje Práctico Exp.</span>
                                    </div>
                                    <div class="field">
                                        <label>Horas Autónomo <span class="required">*</span></label>
                                        <input type="number" name="horas_autonomo" min="0" max="200" required placeholder="0" value="<?php echo $pea_existente['horas_autonomas'] ?? ''; ?>">
                                        <span class="hint">Nº de horas Autónomo</span>
                                    </div>
                                    <div class="field">
                                        <label>Horas Actividad Autónoma</label>
                                        <input type="number" name="horas_actividad_autonoma" min="0" max="200" placeholder="0" value="<?php echo $pea_existente['horas_actividad_autonoma'] ?? ''; ?>">
                                        <span class="hint">H. actividad autónoma</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-nav">
                                <?php if ($modo_edicion): ?>
                                <a href="distribucion_semanas.php?id_pea=<?php echo $id_pea; ?>" class="btn btn-secondary">← Volver a Distribución</a>
                                <?php else: ?>
                                <a href="mis_peas.php" class="btn btn-secondary">Cancelar</a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-primary" onclick="nextStep(2)">Siguiente →</button>
                            </div>
                        </div>
                        
                        <!-- SECCIÓN 2: Información Docente -->
                        <div class="form-section" data-section="2">
                            <div class="field-group">
                                <h3 class="field-group-title">Datos del Docente</h3>
                                <div class="fields-grid">
                                    <div class="field">
                                        <label>Nombre del Docente</label>
                                        <input type="text" value="<?php echo htmlspecialchars($docente_nombre); ?>" readonly>
                                        <input type="hidden" name="docente" value="<?php echo htmlspecialchars($docente_nombre); ?>">
                                    </div>
                                    <div class="field">
                                        <label>Título Académico</label>
                                        <input type="text" name="titulo_docente" value="<?php echo htmlspecialchars($pea_existente['titulo_docente'] ?? $docente_titulo ?? ''); ?>" placeholder="Ej: Ing., Lic., Mgs.">
                                    </div>
                                    <div class="field">
                                        <label>Correo Electrónico</label>
                                        <input type="email" name="correo_docente" value="<?php echo htmlspecialchars($pea_existente['correo_docente'] ?? $docente_email ?? ''); ?>">
                                    </div>
                                    <div class="field">
                                        <label>Teléfono / Celular</label>
                                        <input type="tel" name="telefono_docente" value="<?php echo htmlspecialchars($pea_existente['telefono_docente'] ?? $docente_celular ?? ''); ?>" maxlength="10">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="field-group">
                                <h3 class="field-group-title">Horarios de Tutoría</h3>
                                <div class="fields-grid">
                                    <div class="field">
                                        <label>Tutoría Grupal</label>
                                        <input type="text" name="tutoria_grupal" value="<?php echo htmlspecialchars($pea_existente['tutoria_grupal'] ?? ''); ?>" placeholder="Ej: Lunes 14:00-15:00">
                                    </div>
                                    <div class="field">
                                        <label>Tutoría Individual</label>
                                        <input type="text" name="tutoria_individual" value="<?php echo htmlspecialchars($pea_existente['tutoria_individual'] ?? ''); ?>" placeholder="Ej: Previo acuerdo">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-nav">
                                <button type="button" class="btn btn-secondary" onclick="prevStep(1)">← Anterior</button>
                                <button type="button" class="btn btn-primary" onclick="nextStep(3)">Siguiente →</button>
                            </div>
                        </div>
                        
                        <!-- SECCIÓN 3: Contenido Académico -->
                        <div class="form-section" data-section="3">
                            <div class="field-group">
                                <h3 class="field-group-title">Descripción de la Asignatura</h3>
                                <div class="fields-grid cols-1">
                                    <div class="field">
                                        <label>Descripción de la Asignatura <span class="required">*</span></label>
                                        <textarea name="descripcion_asignatura" required placeholder="Describa los contenidos y alcance de la asignatura..."><?php echo htmlspecialchars($pea_existente['descripcion_asignatura'] ?? $descripcion_asig); ?></textarea>
                                    </div>
                                    <div class="field">
                                        <label>Objetivo General <span class="required">*</span></label>
                                        <textarea name="objetivo_general" required placeholder="Describa el objetivo general de la asignatura..."><?php echo htmlspecialchars($pea_existente['objetivo_general'] ?? $objetivo_asig); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="field-group">
                                <h3 class="field-group-title">Eje Transversal</h3>
                                <div class="fields-grid">
                                    <div class="field">
                                        <label>Eje Transversal</label>
                                        <input type="text" name="eje_transversal" value="<?php echo htmlspecialchars($pea_existente['eje_transversal'] ?? ''); ?>" placeholder="Ej: Formación ciudadana integral">
                                    </div>
                                    <div class="field">
                                        <label>Temática 1</label>
                                        <input type="text" name="tematica_1" value="<?php echo htmlspecialchars($pea_existente['tematica_1'] ?? ''); ?>" placeholder="Ej: habilidad blanda">
                                    </div>
                                </div>
                                <div class="fields-grid cols-1" style="margin-top: 15px;">
                                    <div class="field">
                                        <label>Descripción Temática 1</label>
                                        <textarea name="descripcion_tematica_1" placeholder="Descripción de la temática 1..."><?php echo htmlspecialchars($pea_existente['descripcion_tematica_1'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <div class="fields-grid" style="margin-top: 15px;">
                                    <div class="field">
                                        <label>Temática 2</label>
                                        <input type="text" name="tematica_2" value="<?php echo htmlspecialchars($pea_existente['tematica_2'] ?? ''); ?>" placeholder="Ej: medioambiente">
                                    </div>
                                </div>
                                <div class="fields-grid cols-1" style="margin-top: 15px;">
                                    <div class="field">
                                        <label>Descripción Temática 2</label>
                                        <textarea name="descripcion_tematica_2" placeholder="Descripción de la temática 2..."><?php echo htmlspecialchars($pea_existente['descripcion_tematica_2'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="field-group">
                                <h3 class="field-group-title">Bibliografía</h3>
                                <div class="fields-grid cols-1">
                                    <div class="field">
                                        <label>Bibliografía Básica</label>
                                        <textarea name="bibliografia_basica" placeholder="Liste la bibliografía básica recomendada..."><?php echo htmlspecialchars($pea_existente['bibliografia_basica'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="field">
                                        <label>Bibliografía Complementaria</label>
                                        <textarea name="bibliografia_complementaria" placeholder="Liste la bibliografía complementaria..."><?php echo htmlspecialchars($pea_existente['bibliografia_complementaria'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-nav">
                                <button type="button" class="btn btn-secondary" onclick="prevStep(2)">← Anterior</button>
                                <button type="button" class="btn btn-primary" onclick="nextStep(4)">Siguiente →</button>
                            </div>
                        </div>
                        
                        <!-- SECCIÓN 4: Unidades -->
                        <div class="form-section" data-section="4">
                            <div class="info-box">
                                Defina las unidades de su asignatura. Puede agregar de 2 a 6 unidades según la estructura de su materia.
                                Cada unidad debe tener asignadas las semanas correspondientes (el periodo tiene <strong><?php echo $num_semanas; ?> semanas</strong>).
                            </div>
                            
                            <div class="field-group">
                                <h3 class="field-group-title">Unidades de la Asignatura</h3>
                                
                                <div id="unidadesContainer">
                                    <!-- Unidades se agregan dinámicamente aquí -->
                                </div>
                                
                                <button type="button" class="btn-add-unidad" onclick="agregarUnidad()">
                                    + Agregar Unidad
                                </button>
                            </div>
                            
                            <div class="form-nav">
                                <button type="button" class="btn btn-secondary" onclick="prevStep(3)">← Anterior</button>
                                <button type="button" class="btn btn-primary" onclick="nextStep(5)">Siguiente →</button>
                            </div>
                        </div>
                        
                        <!-- SECCIÓN 5: Resultados de Aprendizaje -->
                        <div class="form-section" data-section="5">
                            <div class="field-group">
                                <h3 class="field-group-title">Resultados de Aprendizaje de la Asignatura</h3>
                                
                                <div class="ra-card">
                                    <h4>Resultado de Aprendizaje 1</h4>
                                    <div class="fields-grid cols-1">
                                        <div class="field">
                                            <label>RA 1 <span class="required">*</span></label>
                                            <textarea name="ra1" required placeholder="Describa el primer resultado de aprendizaje..."><?php echo htmlspecialchars($pea_existente['ra1'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="field">
                                            <label>Resultado de Aprendizaje del Perfil de Egreso 1 <span class="required">*</span></label>
                                            <textarea name="pe1" required placeholder="Describa el resultado del perfil de egreso..."><?php echo htmlspecialchars($pea_existente['ra1_evidencia'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="fields-grid" style="margin-top: 10px;">
                                        <div class="field">
                                            <label>Nivel de Contribución 1 <span class="required">*</span></label>
                                            <select name="contribucion_1" required>
                                                <option value="">Seleccione...</option>
                                                <option value="Alta" <?php echo ($pea_existente['ra1_contribucion'] ?? '') === 'Alta' ? 'selected' : ''; ?>>Alta</option>
                                                <option value="Media" <?php echo ($pea_existente['ra1_contribucion'] ?? '') === 'Media' ? 'selected' : ''; ?>>Media</option>
                                                <option value="Baja" <?php echo ($pea_existente['ra1_contribucion'] ?? '') === 'Baja' ? 'selected' : ''; ?>>Baja</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="ra-card">
                                    <h4>Resultado de Aprendizaje 2</h4>
                                    <div class="fields-grid cols-1">
                                        <div class="field">
                                            <label>RA 2 <span class="required">*</span></label>
                                            <textarea name="ra2" required placeholder="Describa el segundo resultado de aprendizaje..."><?php echo htmlspecialchars($pea_existente['ra2'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="field">
                                            <label>Resultado de Aprendizaje del Perfil de Egreso 2 <span class="required">*</span></label>
                                            <textarea name="pe2" required placeholder="Describa el resultado del perfil de egreso..."><?php echo htmlspecialchars($pea_existente['ra2_evidencia'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="fields-grid" style="margin-top: 10px;">
                                        <div class="field">
                                            <label>Nivel de Contribución 2 <span class="required">*</span></label>
                                            <select name="contribucion_2" required>
                                                <option value="">Seleccione...</option>
                                                <option value="Alta" <?php echo ($pea_existente['ra2_contribucion'] ?? '') === 'Alta' ? 'selected' : ''; ?>>Alta</option>
                                                <option value="Media" <?php echo ($pea_existente['ra2_contribucion'] ?? '') === 'Media' ? 'selected' : ''; ?>>Media</option>
                                                <option value="Baja" <?php echo ($pea_existente['ra2_contribucion'] ?? '') === 'Baja' ? 'selected' : ''; ?>>Baja</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="ra-card">
                                    <h4>Resultado de Aprendizaje 3</h4>
                                    <div class="fields-grid cols-1">
                                        <div class="field">
                                            <label>RA 3 <span class="required">*</span></label>
                                            <textarea name="ra3" required placeholder="Describa el tercer resultado de aprendizaje..."><?php echo htmlspecialchars($pea_existente['ra3'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="field">
                                            <label>Resultado de Aprendizaje del Perfil de Egreso 3 <span class="required">*</span></label>
                                            <textarea name="pe3" required placeholder="Describa el resultado del perfil de egreso..."><?php echo htmlspecialchars($pea_existente['ra3_evidencia'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="fields-grid" style="margin-top: 10px;">
                                        <div class="field">
                                            <label>Nivel de Contribución 3 <span class="required">*</span></label>
                                            <select name="contribucion_3" required>
                                                <option value="">Seleccione...</option>
                                                <option value="Alta" <?php echo ($pea_existente['ra3_contribucion'] ?? '') === 'Alta' ? 'selected' : ''; ?>>Alta</option>
                                                <option value="Media" <?php echo ($pea_existente['ra3_contribucion'] ?? '') === 'Media' ? 'selected' : ''; ?>>Media</option>
                                                <option value="Baja" <?php echo ($pea_existente['ra3_contribucion'] ?? '') === 'Baja' ? 'selected' : ''; ?>>Baja</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-nav">
                                <button type="button" class="btn btn-secondary" onclick="prevStep(4)">← Anterior</button>
                                <button type="submit" class="btn btn-success"><?php echo $modo_edicion ? '💾 Guardar Cambios' : 'Guardar y Continuar →'; ?></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <script>
        // Sistema Toast
        function showToast(type, title, message, duration = 4000) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };
            toast.innerHTML = `
                <span class="toast-icon">${icons[type] || 'ℹ'}</span>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="closeToast(this.parentElement)">×</button>
            `;
            container.appendChild(toast);
            setTimeout(() => closeToast(toast), duration);
        }
        
        function closeToast(toast) {
            if (!toast || toast.classList.contains('hiding')) return;
            toast.classList.add('hiding');
            setTimeout(() => { if (toast.parentElement) toast.remove(); }, 300);
        }
        
        const NUM_SEMANAS = <?php echo $num_semanas; ?>;
        const MODO_EDICION = <?php echo $modo_edicion ? 'true' : 'false'; ?>;
        const ID_PEA = <?php echo $id_pea ?: 0; ?>;
        let unidadCount = 0;
        let semanasUsadas = new Set();
        
        // Unidades existentes para modo edición
        const unidadesExistentes = <?php echo json_encode($unidades_existentes); ?>;
        
        // Navegación entre secciones
        function goToStep(step) {
            document.querySelectorAll('.form-section').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.step-tab').forEach(t => t.classList.remove('active'));
            
            document.querySelector(`.form-section[data-section="${step}"]`).classList.add('active');
            document.querySelector(`.step-tab[data-step="${step}"]`).classList.add('active');
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        function nextStep(step) {
            const currentSection = document.querySelector('.form-section.active');
            const currentStep = parseInt(currentSection.dataset.section);
            
            // Validar campos requeridos
            const requiredFields = currentSection.querySelectorAll('[required]');
            let valid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc2626';
                    valid = false;
                } else {
                    field.style.borderColor = '#d1d5db';
                }
            });
            
            // Validación especial para unidades
            if (currentStep === 4) {
                if (unidadCount < 1) {
                    showToast('warning', 'Unidades requeridas', 'Debe agregar al menos 1 unidad.');
                    return;
                }
                if (!validarSemanasCompletas()) {
                    showToast('warning', 'Semanas incompletas', 'Debe asignar todas las semanas a las unidades. Semanas sin asignar: ' + getSemanasLibres().join(', '));
                    return;
                }
            }
            
            if (!valid) {
                showToast('warning', 'Campos incompletos', 'Por favor, complete todos los campos requeridos.');
                return;
            }
            
            document.querySelector(`.step-tab[data-step="${currentStep}"]`).classList.add('completed');
            goToStep(step);
        }
        
        function prevStep(step) {
            goToStep(step);
        }
        
        // Click en tabs
        document.querySelectorAll('.step-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                goToStep(this.dataset.step);
            });
        });
        
        // GESTIÓN DE UNIDADES
        function agregarUnidad(datos = null) {
            if (unidadCount >= 6) {
                showToast('warning', 'Límite alcanzado', 'Máximo 6 unidades permitidas.');
                return;
            }
            
            unidadCount++;
            const container = document.getElementById('unidadesContainer');
            
            const nombre = datos ? datos.nombre : '';
            const descripcion = datos ? datos.descripcion : '';
            const semanaInicio = datos ? datos.semana_inicio : '';
            const semanaFin = datos ? datos.semana_fin : '';
            const idUnidad = datos ? datos.id_unidad : '';
            
            const unidadHTML = `
                <div class="unidad-card" id="unidad_${unidadCount}" data-unidad="${unidadCount}">
                    <div class="unidad-card-header">
                        <h4>Unidad ${unidadCount}</h4>
                        <button type="button" class="btn-remove-unidad" onclick="eliminarUnidad(${unidadCount})">Eliminar</button>
                    </div>
                    <div class="unidad-card-body">
                        <input type="hidden" name="unidad_${unidadCount}_id" value="${idUnidad}">
                        <div class="fields-grid">
                            <div class="field">
                                <label>Nombre de la Unidad <span class="required">*</span></label>
                                <input type="text" name="unidad_${unidadCount}_nombre" required placeholder="Ej: Fundamentos de Calidad" value="${nombre}">
                            </div>
                            <div class="field">
                                <label>Semanas</label>
                                <div class="semanas-selector">
                                    <label>Desde:</label>
                                    <select name="unidad_${unidadCount}_semana_inicio" onchange="actualizarSemanasUsadas()">
                                        ${generarOpcionesSemanas(semanaInicio)}
                                    </select>
                                    <label>Hasta:</label>
                                    <select name="unidad_${unidadCount}_semana_fin" onchange="actualizarSemanasUsadas()">
                                        ${generarOpcionesSemanas(semanaFin)}
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="fields-grid cols-1" style="margin-top: 15px;">
                            <div class="field">
                                <label>Descripción de la Unidad</label>
                                <textarea name="unidad_${unidadCount}_descripcion" placeholder="Descripción de los contenidos de la unidad...">${descripcion}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', unidadHTML);
            actualizarNumerosUnidades();
            actualizarSemanasUsadas();
        }
        
        function eliminarUnidad(num) {
            if (unidadCount <= 1) {
                showToast('warning', 'Mínimo requerido', 'Debe mantener al menos 1 unidad.');
                return;
            }
            
            document.getElementById(`unidad_${num}`).remove();
            unidadCount--;
            actualizarNumerosUnidades();
            actualizarSemanasUsadas();
        }
        
        function actualizarNumerosUnidades() {
            const unidades = document.querySelectorAll('.unidad-card');
            unidades.forEach((unidad, index) => {
                const numero = index + 1;
                unidad.querySelector('h4').textContent = `Unidad ${numero}`;
            });
        }
        
        function generarOpcionesSemanas(seleccionada = '') {
            let options = '<option value="">--</option>';
            for (let i = 1; i <= NUM_SEMANAS; i++) {
                const selected = (seleccionada == i) ? 'selected' : '';
                options += `<option value="${i}" ${selected}>${i}</option>`;
            }
            return options;
        }
        
        function actualizarSemanasUsadas() {
            semanasUsadas.clear();
            const unidades = document.querySelectorAll('.unidad-card');
            
            unidades.forEach(unidad => {
                const inicio = parseInt(unidad.querySelector('select[name$="_semana_inicio"]').value) || 0;
                const fin = parseInt(unidad.querySelector('select[name$="_semana_fin"]').value) || 0;
                
                if (inicio > 0 && fin > 0 && fin >= inicio) {
                    for (let i = inicio; i <= fin; i++) {
                        semanasUsadas.add(i);
                    }
                }
            });
        }
        
        function validarSemanasCompletas() {
            actualizarSemanasUsadas();
            for (let i = 1; i <= NUM_SEMANAS; i++) {
                if (!semanasUsadas.has(i)) return false;
            }
            return true;
        }
        
        function getSemanasLibres() {
            const libres = [];
            for (let i = 1; i <= NUM_SEMANAS; i++) {
                if (!semanasUsadas.has(i)) libres.push(i);
            }
            return libres;
        }
        
        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            if (MODO_EDICION && unidadesExistentes.length > 0) {
                // Cargar unidades existentes
                unidadesExistentes.forEach(u => {
                    agregarUnidad(u);
                });
            } else {
                // Agregar 1 unidad por defecto
                agregarUnidad();
            }
        });
        
        // Validación antes de enviar
        document.getElementById('formPEA').addEventListener('submit', function(e) {
            if (unidadCount < 1) {
                e.preventDefault();
                showToast('error', 'Error', 'Debe agregar al menos 1 unidad.');
                goToStep(4);
                return;
            }
            
            if (!validarSemanasCompletas()) {
                e.preventDefault();
                showToast('error', 'Error', 'Debe asignar todas las semanas a las unidades.');
                goToStep(4);
                return;
            }
        });
    </script>
</body>
</html>
