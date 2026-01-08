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

// Obtener niveles para filtro
$sql_niveles = "SELECT DISTINCT nivel FROM asignatura WHERE id_carrera = ? AND activa = 1 ORDER BY nivel";
$stmt = mysqli_prepare($cnx, $sql_niveles);
mysqli_stmt_bind_param($stmt, "i", $id_carrera);
mysqli_stmt_execute($stmt);
$result_niveles = mysqli_stmt_get_result($stmt);
$niveles = [];
while ($row = mysqli_fetch_assoc($result_niveles)) {
    $niveles[] = $row['nivel'];
}
mysqli_stmt_close($stmt);

// Estadísticas
$sql_stats = "SELECT 
    COUNT(*) as total_asignaciones,
    COUNT(DISTINCT a.id_docente) as total_docentes,
    SUM(asig.total_horas) as total_horas
FROM asignacion a
INNER JOIN asignatura asig ON a.id_asignatura = asig.id_asignatura
WHERE asig.id_carrera = ? AND a.id_periodo = ?";
$stmt = mysqli_prepare($cnx, $sql_stats);
mysqli_stmt_bind_param($stmt, "ii", $id_carrera, $periodo_activo['id_periodo']);
mysqli_stmt_execute($stmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Obtener todas las asignaciones agrupadas por docente
$sql_asignaciones = "SELECT d.id_docente, d.nombres, d.apellidos, d.cedula, d.email,
                            a.id_asignacion, a.paralelo, a.jornada,
                            asig.nombre as asignatura, asig.nivel, asig.total_horas, asig.codigo
                     FROM asignacion a
                     INNER JOIN docente d ON a.id_docente = d.id_docente
                     INNER JOIN asignatura asig ON a.id_asignatura = asig.id_asignatura
                     WHERE asig.id_carrera = ? AND a.id_periodo = ?
                     ORDER BY d.apellidos, d.nombres, asig.nivel, asig.nombre";
$stmt = mysqli_prepare($cnx, $sql_asignaciones);
mysqli_stmt_bind_param($stmt, "ii", $id_carrera, $periodo_activo['id_periodo']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Agrupar por docente
$docentes_asignados = [];
while ($row = mysqli_fetch_assoc($result)) {
    $id_doc = $row['id_docente'];
    if (!isset($docentes_asignados[$id_doc])) {
        $docentes_asignados[$id_doc] = [
            'id_docente' => $id_doc,
            'nombres' => $row['nombres'],
            'apellidos' => $row['apellidos'],
            'cedula' => $row['cedula'],
            'email' => $row['email'],
            'materias' => [],
            'total_horas' => 0
        ];
    }
    $docentes_asignados[$id_doc]['materias'][] = [
        'id_asignacion' => $row['id_asignacion'],
        'asignatura' => $row['asignatura'],
        'codigo' => $row['codigo'],
        'nivel' => $row['nivel'],
        'paralelo' => $row['paralelo'],
        'jornada' => $row['jornada'],
        'total_horas' => $row['total_horas']
    ];
    $docentes_asignados[$id_doc]['total_horas'] += $row['total_horas'];
}
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Asignaciones - Sistema PEA</title>
    <link rel="stylesheet" href="css/coordinador.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
    
        /* Estadísticas */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-box {
    flex: 1;
    min-width: 150px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 13px ;
    text-align: center;
}
        .stat-box h3 {
            font-size: 1.8rem;
            #1e3a5f;
            margin-bottom: 5px;
        }
        .stat-box p {
            color: #666;
            font-size: 0.85rem;
            margin: 0;
        }
        
        /* Filtros */
        .filtros-section {
            display:flex;
            background: rgba(242, 245, 249, 0.85);
            padding: 15px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .filtros-row {
            display: flex;
            gap: 14px;
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
            max-width: 170px;
            padding: 5px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 0.81rem;
            transition: border-color 0.3s;
        }
        .filtro-input:focus, .filtro-select:focus {
            outline: none;
            border-color: #0081c9;
        }
        
        /* Tarjetas de docentes */
        .docente-card {
            background: white;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .docente-card.oculto {
            display: none;
        }
        .docente-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #f2f2f2ff 100%);
            padding: 18px 20px;
            padding-bottom: 0px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
        }
        .docente-info h3 {
            color: #333;
            font-size: 1rem;
            margin-bottom: 0px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
       .docente-info p {
    color: #666;
    font-size: 0.8rem;
    margin: 12px 0;
    line-height: 1.2;
}
        .docente-badges {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .badge-materias {
            background: #1e3a5f;
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-horas {
            background: #205d83ff;
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        /* Lista de materias */
        .materias-body {
            padding: 15px 20px;
        }
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
        .materia-item:hover {
            background: #e3f2fd;
        }
        .materia-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }
        .nivel-badge {
            background: #e3f2fd;
            color: #1e3a5f;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .materia-nombre {
            font-size: 0.9rem;
            color: #333;
        }
        .materia-codigo {
            font-size: 0.75rem;
            color: #999;
            margin-left: 8px;
        }
        .materia-detalles {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .paralelo-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        .paralelo-a { background: #d4edda; color: #155724; }
        .paralelo-b { background: #fff3cd; color: #856404; }
        .paralelo-ab { background: #d1ecf1; color: #0c5460; }

        .jornada-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            background: #e9ecef;
            color: #495057;
        }
        .horas-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            background: #1e3a5f;
            color: #f7f7f7ff;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px;
            color: #666;
            background: white;
            border-radius: 15px;
        }
        .empty-state .icon {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        
        /* Contador de resultados */
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
            .filtros-row {
                grid-template-columns: 1fr;
            }
            .docente-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            .materia-item {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            .materia-detalles {
                flex-wrap: wrap;
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
                    <li><a href="ver_asignaciones.php" class="active">👥 Ver Asignaciones</a></li>
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
                <h1>Ver Asignaciones</h1>
                <p>Consulta todas las asignaciones de materias del periodo actual</p>
            </div>
            
            <div class="periodo-actual">
                <span class="label">Periodo Académico:</span>
                <span class="valor"><?php echo htmlspecialchars($periodo_activo['nombre'] ?? 'No definido'); ?></span>
            </div>
            
            <!-- ESTADÍSTICAS -->
            <div class="stats-row">
                <div class="stat-box">
                    <h3><?php echo $stats['total_docentes'] ?? 0; ?></h3>
                    <p>Docentes</p>
                </div>
                <div class="stat-box">
                    <h3><?php echo $stats['total_asignaciones'] ?? 0; ?></h3>
                    <p>Asignaciones</p>
                </div>
                <div class="stat-box">
                    <h3><?php echo $stats['total_horas'] ?? 0; ?></h3>
                    <p>Horas Totales</p>
                </div>
            </div>
            
            <!-- FILTROS -->
            <?php if (count($docentes_asignados) > 0): ?>
            <div class="filtros-section">
                <div class="filtros-row">
                    <div class="filtro-group">
                        <label>🔎︎ Buscar docente</label>
                        <input type="text" id="filtroDocente" class="filtro-input" placeholder="Nombre, apellido o cédula...">
                    </div>
                    <div class="filtro-group">
                        <label>☰ Nivel</label>
                        <select id="filtroNivel" class="filtro-select">
                            <option value="">Todos los niveles</option>
                            <?php foreach ($niveles as $nivel): ?>
                            <option value="<?php echo $nivel; ?>">Nivel <?php echo $nivel; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filtro-group">
                        <label>▼ Paralelo</label>
                        <select id="filtroParalelo" class="filtro-select">
                            <option value="">Todos</option>
                            <option value="A">Paralelo A</option>
                            <option value="B">Paralelo B</option>
                        </select>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            
            <!-- CONTADOR DE RESULTADOS -->
            <div class="resultados-count" id="contadorResultados">
                Mostrando <?php echo count($docentes_asignados); ?> docentes
            </div>
            
            <!-- LISTA DE DOCENTES -->
            <div id="listaDocentes">
                <?php if (count($docentes_asignados) > 0): ?>
                    <?php foreach ($docentes_asignados as $doc): ?>
                    <div class="docente-card" 
                         data-nombre="<?php echo strtolower($doc['nombres'] . ' ' . $doc['apellidos']); ?>"
                         data-cedula="<?php echo $doc['cedula']; ?>">
                        <div class="docente-header">
                            <div class="docente-info">
                                <h3>👤 <?php echo htmlspecialchars($doc['apellidos'] . ' ' . $doc['nombres']); ?></h3>
                                <p>⠀Cédula: <?php echo htmlspecialchars($doc['cedula']); ?></p>
                                <?php if ($doc['email']): ?>
                                <p>⠀Correo: <?php echo htmlspecialchars($doc['email']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="docente-badges">
                                <span class="badge-materias"><?php echo count($doc['materias']); ?> materia<?php echo count($doc['materias']) != 1 ? 's' : ''; ?></span>
                                <span class="badge-horas"><?php echo $doc['total_horas']; ?> horas</span>
                            </div>
                        </div>
                        <div class="materias-body">
                            <?php foreach ($doc['materias'] as $mat): ?>
                            <div class="materia-item" data-nivel="<?php echo $mat['nivel']; ?>" data-paralelo="<?php echo $mat['paralelo']; ?>">
                                <div class="materia-info">
                                    <span class="nivel-badge">Nivel <?php echo $mat['nivel']; ?></span>
                                    <span class="materia-nombre">
                                        <?php echo htmlspecialchars($mat['asignatura']); ?>
                                        <?php if ($mat['codigo']): ?>
                                        <span class="materia-codigo">(<?php echo htmlspecialchars($mat['codigo']); ?>)</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="materia-detalles">
                                    <span class="paralelo-badge paralelo-<?php echo strtolower($mat['paralelo']); ?>">
                                        Paralelo <?php echo $mat['paralelo'] === 'AB' ? 'A y B' : $mat['paralelo']; ?>
                                    </span>      
                                    <span class="jornada-badge">
                                        <?php echo ucfirst($mat['jornada']); ?>
                                    </span>
                                    <span class="horas-badge">
                                        <?php echo $mat['total_horas']; ?>h
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="empty-state">
                    <div class="icon">📭</div>
                    <h3>Sin asignaciones</h3>
                    <p>Aún no hay asignaturas asignadas para este periodo.</p>
                    <a href="asignar_materias.php" class="btn btn-primary" style="margin-top: 15px;">Asignar Materias</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        const filtroDocente = document.getElementById('filtroDocente');
        const filtroNivel = document.getElementById('filtroNivel');
        const filtroParalelo = document.getElementById('filtroParalelo');
        const contadorResultados = document.getElementById('contadorResultados');
        
        function aplicarFiltros() {
            const termino = filtroDocente.value.toLowerCase().trim();
            const nivelSel = filtroNivel.value;
            const paraleloSel = filtroParalelo.value;
            
            const tarjetas = document.querySelectorAll('.docente-card');
            let docentesVisibles = 0;
            let materiasVisibles = 0;
            
            tarjetas.forEach(function(tarjeta) {
                const nombre = tarjeta.dataset.nombre;
                const cedula = tarjeta.dataset.cedula;
                const materias = tarjeta.querySelectorAll('.materia-item');
                
                // Filtro por docente
                const coincideDocente = nombre.includes(termino) || cedula.includes(termino);
                
                if (!coincideDocente) {
                    tarjeta.classList.add('oculto');
                    return;
                }
                
                // Filtro por nivel y paralelo en materias
                let tieneMateriasVisibles = false;
                
                materias.forEach(function(materia) {
                    const nivel = materia.dataset.nivel;
                    const paralelo = materia.dataset.paralelo;
                    
                    const coincideNivel = !nivelSel || nivel === nivelSel;
                    const coincideParalelo = !paraleloSel || paralelo === paraleloSel;
                    
                    if (coincideNivel && coincideParalelo) {
                        materia.style.display = 'flex';
                        tieneMateriasVisibles = true;
                        materiasVisibles++;
                    } else {
                        materia.style.display = 'none';
                    }
                });
                
                if (tieneMateriasVisibles) {
                    tarjeta.classList.remove('oculto');
                    docentesVisibles++;
                } else {
                    tarjeta.classList.add('oculto');
                }
            });
            
            // Actualizar contador
            contadorResultados.textContent = `Mostrando ${docentesVisibles} docente${docentesVisibles !== 1 ? 's' : ''} con ${materiasVisibles} asignación${materiasVisibles !== 1 ? 'es' : ''}`;
        }
        
        filtroDocente.addEventListener('input', aplicarFiltros);
        filtroNivel.addEventListener('change', aplicarFiltros);
        filtroParalelo.addEventListener('change', aplicarFiltros);
    </script>
</body>
</html>
