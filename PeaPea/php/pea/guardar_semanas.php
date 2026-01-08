<?php
session_start();
header('Content-Type: application/json');

require_once '../conexion.php';

if (!isset($_SESSION['id_docente'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$id_docente = $_SESSION['id_docente'];
$id_pea = intval($_POST['id_pea'] ?? 0);
$enviar_revision = isset($_POST['enviar_revision']) && $_POST['enviar_revision'] === '1';

if (!$id_pea) {
    echo json_encode(['success' => false, 'message' => 'ID de PEA no válido']);
    exit();
}

// Verificar permisos
$sql_check = "SELECT p.id_pea, p.estado, per.num_semanas 
              FROM pea p
              INNER JOIN asignacion a ON p.id_asignacion = a.id_asignacion
              INNER JOIN periodo_academico per ON a.id_periodo = per.id_periodo
              WHERE p.id_pea = ? AND a.id_docente = ?";
$stmt = mysqli_prepare($cnx, $sql_check);
mysqli_stmt_bind_param($stmt, "ii", $id_pea, $id_docente);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pea = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$pea) {
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para editar este PEA']);
    exit();
}

if (!in_array($pea['estado'], ['borrador', 'rechazado'])) {
    echo json_encode(['success' => false, 'message' => 'Este PEA no puede editarse en su estado actual']);
    exit();
}

$num_semanas = $pea['num_semanas'] ?? 16;

// Iniciar transacción
mysqli_begin_transaction($cnx);

try {
    // Procesar cada semana
    for ($i = 1; $i <= $num_semanas; $i++) {
        $id_semana = intval($_POST["semana_{$i}_id"] ?? 0);
        
        if (!$id_semana) continue;
        
        // Datos de la semana
        $fecha_inicio = !empty($_POST["semana_{$i}_fecha_inicio"]) ? $_POST["semana_{$i}_fecha_inicio"] : null;
        $fecha_fin = !empty($_POST["semana_{$i}_fecha_fin"]) ? $_POST["semana_{$i}_fecha_fin"] : null;
        $horario_matutina = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_horario_matutina"] ?? '');
        $horario_nocturna = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_horario_nocturna"] ?? '');
        $temas = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_temas"] ?? '');
        $objetivo = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_objetivo"] ?? '');
        $resultado_aprendizaje = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_resultado_aprendizaje"] ?? '');
        $eje_transversal = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_eje_transversal"] ?? '');
        $medioambiente = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_medioambiente"] ?? '');
        $horas_docencia = intval($_POST["semana_{$i}_horas_docencia"] ?? 0);
        $horas_practicas_docente = intval($_POST["semana_{$i}_horas_practicas_docente"] ?? 0);
        $horas_practicas_autonomo = intval($_POST["semana_{$i}_horas_practicas_autonomo"] ?? 0);
        $horas_autonomo = intval($_POST["semana_{$i}_horas_autonomo"] ?? 0);
        
        // Actualizar semana (14 parámetros: 9 strings + 4 ints + 1 int final)
        $sql_update = "UPDATE semanas_pea SET 
                        fecha_inicio = ?, 
                        fecha_fin = ?,
                        horario_matutina = ?,
                        horario_nocturna = ?,
                        temas = ?,
                        objetivo = ?,
                        resultado_aprendizaje = ?,
                        eje_transversal = ?,
                        medioambiente = ?,
                        horas_docencia = ?,
                        horas_practicas_con_docente = ?,
                        horas_practicas_autonomo = ?,
                        horas_trabajo_autonomo = ?
                       WHERE id_semana = ?";
        
        $stmt = mysqli_prepare($cnx, $sql_update);
        mysqli_stmt_bind_param($stmt, "sssssssssiiiii",
            $fecha_inicio, $fecha_fin, $horario_matutina, $horario_nocturna,
            $temas, $objetivo, $resultado_aprendizaje, $eje_transversal, $medioambiente,
            $horas_docencia, $horas_practicas_docente, $horas_practicas_autonomo, $horas_autonomo,
            $id_semana
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error al actualizar semana {$i}: " . mysqli_error($cnx));
        }
        mysqli_stmt_close($stmt);
        
        // Procesar actividades - primero eliminar las que ya no existen
        $ids_practicas = [];
        $ids_autonomas = [];
        
        // Buscar actividades prácticas en el POST
        $j = 1;
        while (isset($_POST["semana_{$i}_practica_{$j}_tema"]) || isset($_POST["semana_{$i}_practica_{$j}_id"])) {
            if (!empty($_POST["semana_{$i}_practica_{$j}_id"])) {
                $ids_practicas[] = intval($_POST["semana_{$i}_practica_{$j}_id"]);
            }
            $j++;
        }
        
        // Buscar actividades autónomas en el POST
        $j = 1;
        while (isset($_POST["semana_{$i}_autonoma_{$j}_tema"]) || isset($_POST["semana_{$i}_autonoma_{$j}_id"])) {
            if (!empty($_POST["semana_{$i}_autonoma_{$j}_id"])) {
                $ids_autonomas[] = intval($_POST["semana_{$i}_autonoma_{$j}_id"]);
            }
            $j++;
        }
        
        // Eliminar actividades que ya no están en el formulario
        $ids_mantener = array_merge($ids_practicas, $ids_autonomas);
        if (!empty($ids_mantener)) {
            $ids_str = implode(',', array_map('intval', $ids_mantener));
            $sql_delete = "DELETE FROM actividades_pea WHERE id_semana = ? AND id_actividad NOT IN ({$ids_str})";
        } else {
            $sql_delete = "DELETE FROM actividades_pea WHERE id_semana = ?";
        }
        $stmt = mysqli_prepare($cnx, $sql_delete);
        mysqli_stmt_bind_param($stmt, "i", $id_semana);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        // Procesar actividades prácticas
        $j = 1;
        while (isset($_POST["semana_{$i}_practica_{$j}_tema"]) || isset($_POST["semana_{$i}_practica_{$j}_horas"])) {
            $id_actividad = intval($_POST["semana_{$i}_practica_{$j}_id"] ?? 0);
            $horas = intval($_POST["semana_{$i}_practica_{$j}_horas"] ?? 0);
            $horas_docente_act = intval($_POST["semana_{$i}_practica_{$j}_horas_docente"] ?? 0);
            $tema = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_practica_{$j}_tema"] ?? '');
            $descripcion = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_practica_{$j}_descripcion"] ?? '');
            $ra = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_practica_{$j}_ra"] ?? '');
            $metodologia = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_practica_{$j}_metodologia"] ?? '');
            $recursos = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_practica_{$j}_recursos"] ?? '');
            $bibliografia = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_practica_{$j}_bibliografia"] ?? '');
            $tecnologicos = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_practica_{$j}_tecnologicos"] ?? '');
            $producto = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_practica_{$j}_producto"] ?? '');
            $calificacion = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_practica_{$j}_calificacion"] ?? '');
            $fecha_entrega = !empty($_POST["semana_{$i}_practica_{$j}_entrega"]) ? $_POST["semana_{$i}_practica_{$j}_entrega"] : null;
            
            if ($id_actividad > 0) {
                // Actualizar existente (12 campos SET + 1 WHERE = 13 parámetros)
                $sql_act = "UPDATE actividades_pea SET 
                            horas = ?, horas_con_docente = ?, tema = ?, descripcion = ?,
                            resultado_aprendizaje = ?, metodologia = ?, recursos = ?,
                            recurso_bibliografico = ?, recursos_tecnologicos = ?,
                            producto_final = ?, calificacion = ?, fecha_entrega = ?
                            WHERE id_actividad = ?";
                $stmt = mysqli_prepare($cnx, $sql_act);
                // 2 ints + 10 strings + 1 int = iissssssssssi
                mysqli_stmt_bind_param($stmt, "iissssssssssi",
                    $horas, $horas_docente_act, $tema, $descripcion, $ra, $metodologia,
                    $recursos, $bibliografia, $tecnologicos, $producto, $calificacion, $fecha_entrega,
                    $id_actividad
                );
            } else {
                // Crear nueva (14 valores)
                $sql_act = "INSERT INTO actividades_pea 
                            (id_semana, tipo, numero, horas, horas_con_docente, tema, descripcion,
                             resultado_aprendizaje, metodologia, recursos, recurso_bibliografico,
                             recursos_tecnologicos, producto_final, calificacion, fecha_entrega)
                            VALUES (?, 'practica', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($cnx, $sql_act);
                // 4 ints + 10 strings = iiiissssssssss
                mysqli_stmt_bind_param($stmt, "iiiissssssssss",
                    $id_semana, $j, $horas, $horas_docente_act, $tema, $descripcion, $ra, $metodologia,
                    $recursos, $bibliografia, $tecnologicos, $producto, $calificacion, $fecha_entrega
                );
            }
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error al guardar actividad práctica {$j} de semana {$i}: " . mysqli_error($cnx));
            }
            mysqli_stmt_close($stmt);
            
            $j++;
        }
        
        // Procesar actividades autónomas
        $j = 1;
        while (isset($_POST["semana_{$i}_autonoma_{$j}_tema"]) || isset($_POST["semana_{$i}_autonoma_{$j}_horas"])) {
            $id_actividad = intval($_POST["semana_{$i}_autonoma_{$j}_id"] ?? 0);
            $horas = intval($_POST["semana_{$i}_autonoma_{$j}_horas"] ?? 0);
            $tema = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_autonoma_{$j}_tema"] ?? '');
            $descripcion = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_autonoma_{$j}_descripcion"] ?? '');
            $ra = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_autonoma_{$j}_ra"] ?? '');
            $metodologia = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_autonoma_{$j}_metodologia"] ?? '');
            $recursos = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_autonoma_{$j}_recursos"] ?? '');
            $bibliografia = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_autonoma_{$j}_bibliografia"] ?? '');
            $tecnologicos = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_autonoma_{$j}_tecnologicos"] ?? '');
            $producto = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_autonoma_{$j}_producto"] ?? '');
            $calificacion = mysqli_real_escape_string($cnx, $_POST["semana_{$i}_autonoma_{$j}_calificacion"] ?? '');
            $fecha_entrega = !empty($_POST["semana_{$i}_autonoma_{$j}_entrega"]) ? $_POST["semana_{$i}_autonoma_{$j}_entrega"] : null;
            
            if ($id_actividad > 0) {
                // Actualizar existente (11 campos SET + 1 WHERE = 12 parámetros)
                $sql_act = "UPDATE actividades_pea SET 
                            horas = ?, tema = ?, descripcion = ?,
                            resultado_aprendizaje = ?, metodologia = ?, recursos = ?,
                            recurso_bibliografico = ?, recursos_tecnologicos = ?,
                            producto_final = ?, calificacion = ?, fecha_entrega = ?
                            WHERE id_actividad = ?";
                $stmt = mysqli_prepare($cnx, $sql_act);
                // 1 int + 10 strings + 1 int = issssssssssi
                mysqli_stmt_bind_param($stmt, "issssssssssi",
                    $horas, $tema, $descripcion, $ra, $metodologia,
                    $recursos, $bibliografia, $tecnologicos, $producto, $calificacion, $fecha_entrega,
                    $id_actividad
                );
            } else {
                // Crear nueva (13 valores)
                $sql_act = "INSERT INTO actividades_pea 
                            (id_semana, tipo, numero, horas, tema, descripcion,
                             resultado_aprendizaje, metodologia, recursos, recurso_bibliografico,
                             recursos_tecnologicos, producto_final, calificacion, fecha_entrega)
                            VALUES (?, 'autonoma', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($cnx, $sql_act);
                // 3 ints + 10 strings = iiisssssssssss
                mysqli_stmt_bind_param($stmt, "iiisssssssssss",
                    $id_semana, $j, $horas, $tema, $descripcion, $ra, $metodologia,
                    $recursos, $bibliografia, $tecnologicos, $producto, $calificacion, $fecha_entrega
                );
            }
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error al guardar actividad autónoma {$j} de semana {$i}: " . mysqli_error($cnx));
            }
            mysqli_stmt_close($stmt);
            
            $j++;
        }
    }
    
    // Si se solicita enviar para revisión
    if ($enviar_revision) {
        $sql_estado = "UPDATE pea SET estado = 'enviado', fecha_envio = NOW() WHERE id_pea = ?";
        $stmt = mysqli_prepare($cnx, $sql_estado);
        mysqli_stmt_bind_param($stmt, "i", $id_pea);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error al cambiar estado: " . mysqli_error($cnx));
        }
        mysqli_stmt_close($stmt);
    }
    
    // Confirmar transacción
    mysqli_commit($cnx);
    
    echo json_encode([
        'success' => true, 
        'message' => $enviar_revision ? 'PEA enviado para revisión' : 'Cambios guardados correctamente'
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($cnx);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

mysqli_close($cnx);
