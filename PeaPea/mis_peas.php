<?php
session_start();

if (!isset($_SESSION['id_docente'])) {
    header('Location: login.php');
    exit();
}

require_once 'php/conexion.php';

$id_docente = $_SESSION['id_docente'];
$es_coordinador = ($_SESSION['rol'] === 'coordinador');

// Obtener asignaciones del docente con estado del PEA
$sql = "SELECT a.id_asignacion, a.paralelo, a.jornada,
               asig.nombre as asignatura, asig.codigo, asig.nivel, asig.total_horas,
               c.nombre as carrera,
               per.nombre as periodo, per.activo as periodo_activo,
               p.id_pea, p.estado as estado_pea, p.fecha_creacion, p.fecha_envio
        FROM asignacion a
        INNER JOIN asignatura asig ON a.id_asignatura = asig.id_asignatura
        INNER JOIN carrera c ON asig.id_carrera = c.id_carrera
        INNER JOIN periodo_academico per ON a.id_periodo = per.id_periodo
        LEFT JOIN pea p ON a.id_asignacion = p.id_asignacion
        WHERE a.id_docente = ?
        ORDER BY per.activo DESC, c.nombre, asig.nivel, asig.nombre";

$stmt = mysqli_prepare($cnx, $sql);
mysqli_stmt_bind_param($stmt, "i", $id_docente);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$asignaciones = [];
while ($row = mysqli_fetch_assoc($result)) {
    $asignaciones[] = $row;
}
mysqli_stmt_close($stmt);

$sql_periodo = "SELECT id_periodo, nombre FROM periodo_academico WHERE activo = 1 LIMIT 1";
$result_periodo = mysqli_query($cnx, $sql_periodo);
$periodo_activo = mysqli_fetch_assoc($result_periodo);

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

function getEstadoTexto($estado) {
    switch ($estado) {
        case 'borrador': return 'Borrador';
        case 'enviado': return 'Enviado';
        case 'aprobado': return 'Aprobado';
        case 'rechazado': return 'Rechazado';
        default: return 'Sin PEA';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis PEAs - Sistema PEA</title>
    <link rel="stylesheet" href="css/coordinador.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>

        * { font-family: 'Inter', sans-serif; }
        /* Mensajes */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        /* Estadísticas */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-box {
    flex: 1;
    min-width: 150px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 13px;
    text-align: center;
}
        .stat-box h3 {
            font-size: 2rem;
            color: #1e3a5f;
            margin-bottom: 5px;
        }
        .stat-box p {
            color: #666;
            font-size: 0.74rem;
            margin: 0;
        }
        .stat-box.warning h3 { color: #d97706; }
        .stat-box.success h3 { color: #28a745; }
        .stat-box.info h3 { color: #17a2b8; }
        .stat-box.danger h3 { color: #dc3545; }
        
        /* Filtros */
        .filtros-section {
            background: rgba(242, 245, 249, 0.85);
            padding: 15px 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            
        }
        .filtro-group {
    display: flex;
    align-items: center;
    gap: 8px;
}
        .filtros-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filtro-group label {
            font-size: 0.8rem;
            color: #6b7280;
        }
        .filtro-select {
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.8rem;
            max-width: 120px;
        }
        .filtro-select:focus {
            outline: none;
            border-color: #0081c9;
        }
        
        /* Tarjetas de asignatura */
        .asignatura-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 11px;
    margin-bottom: 15px;
    overflow: hidden;
    transition: box-shadow 0.2s;
}
        .asignatura-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .asignatura-card.periodo-inactivo {
            opacity: 0.7;
        }
        
        .asignatura-card-header {
    background: #f9fafb;
    padding: 12px 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}
        .asignatura-info h3 {
    margin: 0 0 4px 0;
    font-size: 0.99rem;
    font-weight: 600;
    color: #1f2937;
}
        .asignatura-info p {
    margin: 0;
    font-size: 0.8rem;
    color: #6b7280;
        padding-top: 1px;

}
        
        .asignatura-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .badge-primary {
    background: #1e3a5f;
    color: white;
}
        .badge-secondary {
    background: #e5e7eb;
    color: #4b5563;
}
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        
        
        .asignatura-card-body {
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}
        
        .asignatura-meta {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
        }
        .meta-item {
            font-size: 0.9rem;
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
            font-size: 14px;
            padding-bottom: 0px;
        }
        
        .asignatura-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        /* Botones */
        .btn {
            padding: 8px 16px;
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
        .btn-primary {
    background: #1e3a5f;
    color: white;
}
        .btn-primary:hover { background: #006ba1; }
        .btn-secondary { background: #e9ecef; color: #495057; }
        .btn-secondary:hover { background: #dee2e6; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .btn-sm { padding: 8px 14px; font-size: 0.8rem; }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .empty-state .icon {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        .empty-state h3 {
            color: #333;
            margin-bottom: 10px;
        }
        .empty-state p {
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Contador */
        .resultados-count {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 15px;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            display: inline-block;
        }
        
        @media (max-width: 768px) {
            .filtros-row { grid-template-columns: 1fr; }
            .asignatura-card-header { flex-direction: column; align-items: flex-start; }
            .asignatura-card-body { flex-direction: column; align-items: flex-start; }
            .asignatura-actions { width: 100%; }
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
                <h1>Mis PEAs</h1>
                <p>Gestione los Planes de Estudio de sus asignaturas</p>
            </div>
            <div class="periodo-actual">
                <span class="label">Periodo Académico Activo:</span>
                <span class="valor"><?php echo htmlspecialchars($periodo_activo['nombre'] ?? 'No definido'); ?></span>
            </div>
            
            <?php if (isset($_SESSION['mensaje_exito'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['mensaje_exito']; unset($_SESSION['mensaje_exito']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['mensaje_error'])): ?>
            <div class="alert alert-error">
                <?php echo $_SESSION['mensaje_error']; unset($_SESSION['mensaje_error']); ?>
            </div>
            <?php endif; ?>
            
            <?php
            // Calcular estadísticas
            $total = count($asignaciones);
            $sin_pea = 0;
            $borradores = 0;
            $enviados = 0;
            $aprobados = 0;
            $rechazados = 0;
            
            foreach ($asignaciones as $a) {
                switch ($a['estado_pea']) {
                    case 'borrador': $borradores++; break;
                    case 'enviado': $enviados++; break;
                    case 'aprobado': $aprobados++; break;
                    case 'rechazado': $rechazados++; break;
                    default: $sin_pea++;
                }
            }
            ?>
            
            <!-- Estadísticas -->
            <div class="stats-row">
                <div class="stat-box">
                    <h3><?php echo $total; ?></h3>
                    <p>Total Asignaturas</p>
                </div>
                <div class="stat-box warning">
                    <h3><?php echo $sin_pea + $borradores; ?></h3>
                    <p>Pendientes</p>
                </div>
                <div class="stat-box info">
                    <h3><?php echo $enviados; ?></h3>
                    <p>En Revisión</p>
                </div>
                <div class="stat-box success">
                    <h3><?php echo $aprobados; ?></h3>
                    <p>Aprobados</p>
                </div>
                <?php if ($rechazados > 0): ?>
                <div class="stat-box danger">
                    <h3><?php echo $rechazados; ?></h3>
                    <p>Rechazados</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Filtros -->
            <?php if (!empty($asignaciones)): ?>
            <div class="filtros-section">
                <div class="filtros-row">
                    <div class="filtro-group">
                        <label>☰ Estado del PEA:</label>
                        <select id="filtroEstado" class="filtro-select" onchange="filtrarAsignaturas()">
                            <option value="">Todos</option>
                            <option value="sin_pea">Sin PEA</option>
                            <option value="borrador">Borrador</option>
                            <option value="enviado">Enviado</option>
                            <option value="aprobado">Aprobado</option>
                            <option value="rechazado">Rechazado</option>
                        </select>
                    </div>
                    <div class="filtro-group">
                        <label>Periodo ▼:</label>
                        <select id="filtroPeriodo" class="filtro-select" onchange="filtrarAsignaturas()">
                            <option value="">Todos los periodos</option>
                            <option value="activo" selected>Solo Activos</option>
                            <option value="inactivo">Solo Inactivos</option>
                        </select>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Contador -->
            <div class="resultados-count" id="contadorResultados">
                Mostrando <?php echo $total; ?> asignaturas
            </div>
            
            <!-- Lista de asignaturas -->
            <?php if (empty($asignaciones)): ?>
            <div class="empty-state">
                <div class="icon">📭</div>
                <h3>No tienes asignaturas asignadas</h3>
                <p>Contacta al coordinador de tu carrera para que te asigne materias.</p>
            </div>
            <?php else: ?>
            <div id="listaAsignaturas">
                <?php foreach ($asignaciones as $a): ?>
                <div class="asignatura-card <?php echo !$a['periodo_activo'] ? 'periodo-inactivo' : ''; ?>" 
                     data-estado="<?php echo $a['estado_pea'] ?? 'sin_pea'; ?>"
                     data-periodo="<?php echo $a['periodo_activo'] ? 'activo' : 'inactivo'; ?>">
                    <div class="asignatura-card-header">
                        <div class="asignatura-info">
                            <h3><?php echo htmlspecialchars($a['asignatura']); ?></h3>
                            <p><?php echo htmlspecialchars($a['carrera']); ?> • <?php echo htmlspecialchars($a['periodo']); ?></p>
                        </div>
                        <div class="asignatura-badges">
                            <span class="badge badge-primary">Nivel <?php echo $a['nivel']; ?></span>
                            <span class="badge badge-secondary">Paralelo <?php echo $a['paralelo']; ?></span>
                            <span class="badge <?php echo getEstadoBadge($a['estado_pea']); ?>">
                                <?php echo getEstadoTexto($a['estado_pea']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="asignatura-card-body">
                        <div class="asignatura-meta">
                            <div class="meta-item">
                                <label>Código</label>
                                <span><?php echo htmlspecialchars($a['codigo']); ?></span>
                            </div>
                            <div class="meta-item">
                                <label>Jornada</label>
                                <span><?php echo ucfirst($a['jornada']); ?></span>
                            </div>
                            <div class="meta-item">
                                <label>Total Horas</label>
                                <span><?php echo $a['total_horas']; ?></span>
                            </div>
                            <?php if ($a['fecha_creacion']): ?>
                            <div class="meta-item">
                                <label>Creado</label>
                                <span><?php echo date('d/m/Y', strtotime($a['fecha_creacion'])); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="asignatura-actions">
                            <?php if (!$a['id_pea']): ?>
                                <!-- Sin PEA - Crear -->
                                <a href="crear_pea.php?id_asignacion=<?php echo $a['id_asignacion']; ?>" class="btn btn-primary">
                                    Crear PEA +
                                </a>
                            <?php elseif ($a['estado_pea'] === 'borrador' || $a['estado_pea'] === 'rechazado'): ?>
                                <!-- Borrador o Rechazado - Editar -->
                                <a href="distribucion_semanas.php?id_pea=<?php echo $a['id_pea']; ?>" class="btn btn-warning">
                                    Editar PEA ✎
                                </a>
                                <a href="ver_pea.php?id_pea=<?php echo $a['id_pea']; ?>" class="btn btn-secondary btn-sm">
                                    👁️ Ver
                                </a>
                            <?php elseif ($a['estado_pea'] === 'enviado'): ?>
                                <!-- Enviado - Solo ver -->
                                 
                                <span class="btn btn-secondary btn-sm" style="cursor: default;">⏳ En revisión</span>
                                <a href="ver_pea.php?id_pea=<?php echo $a['id_pea']; ?>" class="btn btn-secondary btn-sm">
                                    👁️ Ver
                                </a>
                            <?php elseif ($a['estado_pea'] === 'aprobado'): ?>
                                <!-- Aprobado - Ver y Descargar -->
                                <a href="ver_pea.php?id_pea=<?php echo $a['id_pea']; ?>" class="btn btn-secondary btn-sm">
                                    👁️ Ver
                                </a>
                                <a href="php/pea/generar_word.php?id_pea=<?php echo $a['id_pea']; ?>" class="btn btn-success btn-sm">
                                    📄 Descargar Word
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function filtrarAsignaturas() {
            const estado = document.getElementById('filtroEstado').value;
            const periodo = document.getElementById('filtroPeriodo').value;
            const cards = document.querySelectorAll('.asignatura-card');
            let visibles = 0;
            
            cards.forEach(card => {
                const cardEstado = card.dataset.estado;
                const cardPeriodo = card.dataset.periodo;
                
                let mostrar = true;
                
                if (estado && cardEstado !== estado) {
                    mostrar = false;
                }
                
                if (periodo && cardPeriodo !== periodo) {
                    mostrar = false;
                }
                
                card.style.display = mostrar ? 'block' : 'none';
                if (mostrar) visibles++;
            });
            
            document.getElementById('contadorResultados').textContent = 
                `Mostrando ${visibles} asignatura${visibles !== 1 ? 's' : ''}`;
        }
        
        // Aplicar filtro inicial
        document.addEventListener('DOMContentLoaded', filtrarAsignaturas);
    </script>
</body>
</html>
