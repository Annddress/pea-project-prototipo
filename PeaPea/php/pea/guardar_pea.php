<?php
/**
 * GUARDAR PEA - Crear o Actualizar
 * Sistema PEA - Instituto Superior Tecnológico Tena
 */

session_start();
require_once '../conexion.php';

// Verificar sesión
if (!isset($_SESSION['id_docente'])) {
    header('Location: ../../login.php');
    exit();
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['mensaje_error'] = "Método no permitido.";
    header('Location: ../../mis_peas.php');
    exit();
}

$id_docente = $_SESSION['id_docente'];
$id_asignacion = isset($_POST['id_asignacion']) ? intval($_POST['id_asignacion']) : 0;
$id_pea = isset($_POST['id_pea']) ? intval($_POST['id_pea']) : 0;
$modo_edicion = isset($_POST['modo_edicion']) && $_POST['modo_edicion'] === '1';

// =====================================================
// VALIDACIONES
// =====================================================

if (!$id_asignacion) {
    $_SESSION['mensaje_error'] = "ID de asignación no especificado.";
    header('Location: ../../mis_peas.php');
    exit();
}

// Verificar que la asignación pertenece al docente
$sql_verificar = "SELECT a.id_asignacion, a.id_periodo 
                  FROM asignacion a 
                  WHERE a.id_asignacion = ? AND a.id_docente = ?";
$stmt = mysqli_prepare($cnx, $sql_verificar);
mysqli_stmt_bind_param($stmt, "ii", $id_asignacion, $id_docente);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    $_SESSION['mensaje_error'] = "No tienes permiso para esta asignación.";
    header('Location: ../../mis_peas.php');
    exit();
}
mysqli_stmt_close($stmt);

// Si es modo edición, verificar que el PEA existe y pertenece al docente
if ($modo_edicion && $id_pea > 0) {
    $sql_pea = "SELECT p.id_pea, p.estado 
                FROM pea p 
                INNER JOIN asignacion a ON p.id_asignacion = a.id_asignacion 
                WHERE p.id_pea = ? AND a.id_docente = ?";
    $stmt = mysqli_prepare($cnx, $sql_pea);
    mysqli_stmt_bind_param($stmt, "ii", $id_pea, $id_docente);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $pea_actual = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$pea_actual) {
        $_SESSION['mensaje_error'] = "No tienes permiso para editar este PEA.";
        header('Location: ../../mis_peas.php');
        exit();
    }
    
    if (!in_array($pea_actual['estado'], ['borrador', 'rechazado'])) {
        $_SESSION['mensaje_error'] = "Este PEA no puede editarse en su estado actual.";
        header('Location: ../../mis_peas.php');
        exit();
    }
} else {
    $sql_existe = "SELECT id_pea FROM pea WHERE id_asignacion = ?";
    $stmt = mysqli_prepare($cnx, $sql_existe);
    mysqli_stmt_bind_param($stmt, "i", $id_asignacion);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) > 0) {
        $_SESSION['mensaje_error'] = "Ya existe un PEA para esta asignación.";
        header('Location: ../../mis_peas.php');
        exit();
    }
    mysqli_stmt_close($stmt);
}

// =====================================================
// OBTENER DATOS DEL FORMULARIO
// =====================================================

$modalidad = mysqli_real_escape_string($cnx, $_POST['modalidad'] ?? 'Presencial');
$campo_formacion = mysqli_real_escape_string($cnx, $_POST['campo_formacion'] ?? '');
$unidad_curricular = mysqli_real_escape_string($cnx, $_POST['unidad_curricular'] ?? '');

$horas_docencia = intval($_POST['horas_docencia'] ?? 0);
$horas_practicas = intval($_POST['horas_contacto'] ?? 0);
$horas_practico_autonomo = intval($_POST['horas_practico_autonomo'] ?? 0);
$horas_autonomas = intval($_POST['horas_autonomo'] ?? 0);
$horas_actividad_autonoma = intval($_POST['horas_actividad_autonoma'] ?? 0);

$titulo_docente = mysqli_real_escape_string($cnx, $_POST['titulo_docente'] ?? '');
$correo_docente = mysqli_real_escape_string($cnx, $_POST['correo_docente'] ?? '');
$telefono_docente = mysqli_real_escape_string($cnx, $_POST['telefono_docente'] ?? '');
$tutoria_grupal = mysqli_real_escape_string($cnx, $_POST['tutoria_grupal'] ?? '');
$tutoria_individual = mysqli_real_escape_string($cnx, $_POST['tutoria_individual'] ?? '');

$descripcion_asignatura = mysqli_real_escape_string($cnx, $_POST['descripcion_asignatura'] ?? '');
$objetivo_general = mysqli_real_escape_string($cnx, $_POST['objetivo_general'] ?? '');

$eje_transversal = mysqli_real_escape_string($cnx, $_POST['eje_transversal'] ?? '');
$tematica_1 = mysqli_real_escape_string($cnx, $_POST['tematica_1'] ?? '');
$descripcion_tematica_1 = mysqli_real_escape_string($cnx, $_POST['descripcion_tematica_1'] ?? '');
$tematica_2 = mysqli_real_escape_string($cnx, $_POST['tematica_2'] ?? '');
$descripcion_tematica_2 = mysqli_real_escape_string($cnx, $_POST['descripcion_tematica_2'] ?? '');

$ra1 = mysqli_real_escape_string($cnx, $_POST['ra1'] ?? '');
$ra1_evidencia = mysqli_real_escape_string($cnx, $_POST['pe1'] ?? '');
$ra1_contribucion = mysqli_real_escape_string($cnx, $_POST['contribucion_1'] ?? '');
$ra2 = mysqli_real_escape_string($cnx, $_POST['ra2'] ?? '');
$ra2_evidencia = mysqli_real_escape_string($cnx, $_POST['pe2'] ?? '');
$ra2_contribucion = mysqli_real_escape_string($cnx, $_POST['contribucion_2'] ?? '');
$ra3 = mysqli_real_escape_string($cnx, $_POST['ra3'] ?? '');
$ra3_evidencia = mysqli_real_escape_string($cnx, $_POST['pe3'] ?? '');
$ra3_contribucion = mysqli_real_escape_string($cnx, $_POST['contribucion_3'] ?? '');

$bibliografia_basica = mysqli_real_escape_string($cnx, $_POST['bibliografia_basica'] ?? '');
$bibliografia_complementaria = mysqli_real_escape_string($cnx, $_POST['bibliografia_complementaria'] ?? '');

// =====================================================
// INICIAR TRANSACCIÓN
// =====================================================
mysqli_begin_transaction($cnx);

try {
    if ($modo_edicion && $id_pea > 0) {
        // =====================================================
        // MODO EDICIÓN: UPDATE
        // =====================================================
        
        $sql_update = "UPDATE pea SET 
            modalidad = ?,
            campo_formacion = ?,
            unidad_curricular = ?,
            horas_docencia = ?,
            horas_practicas = ?,
            horas_practico_autonomo = ?,
            horas_autonomas = ?,
            horas_actividad_autonoma = ?,
            titulo_docente = ?,
            correo_docente = ?,
            telefono_docente = ?,
            tutoria_grupal = ?,
            tutoria_individual = ?,
            descripcion_asignatura = ?,
            objetivo_general = ?,
            eje_transversal = ?,
            tematica_1 = ?,
            descripcion_tematica_1 = ?,
            tematica_2 = ?,
            descripcion_tematica_2 = ?,
            ra1 = ?,
            ra1_evidencia = ?,
            ra1_contribucion = ?,
            ra2 = ?,
            ra2_evidencia = ?,
            ra2_contribucion = ?,
            ra3 = ?,
            ra3_evidencia = ?,
            ra3_contribucion = ?,
            bibliografia_basica = ?,
            bibliografia_complementaria = ?,
            fecha_modificacion = NOW()
            WHERE id_pea = ?";
        
        $stmt = mysqli_prepare($cnx, $sql_update);
        
        if (!$stmt) {
            throw new Exception("Error UPDATE: " . mysqli_error($cnx));
        }
        
        // Construir type string: 3s + 5i + 23s + 1i = 32
        $type_update = str_repeat('s', 3) . str_repeat('i', 5) . str_repeat('s', 23) . 'i';
        
        mysqli_stmt_bind_param($stmt, $type_update,
            $modalidad,
            $campo_formacion,
            $unidad_curricular,
            $horas_docencia,
            $horas_practicas,
            $horas_practico_autonomo,
            $horas_autonomas,
            $horas_actividad_autonoma,
            $titulo_docente,
            $correo_docente,
            $telefono_docente,
            $tutoria_grupal,
            $tutoria_individual,
            $descripcion_asignatura,
            $objetivo_general,
            $eje_transversal,
            $tematica_1,
            $descripcion_tematica_1,
            $tematica_2,
            $descripcion_tematica_2,
            $ra1,
            $ra1_evidencia,
            $ra1_contribucion,
            $ra2,
            $ra2_evidencia,
            $ra2_contribucion,
            $ra3,
            $ra3_evidencia,
            $ra3_contribucion,
            $bibliografia_basica,
            $bibliografia_complementaria,
            $id_pea
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error ejecutar UPDATE: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
        
        // Si estaba rechazado, volver a borrador
        $sql_estado = "UPDATE pea SET estado = 'borrador', observaciones_rechazo = NULL WHERE id_pea = ? AND estado = 'rechazado'";
        $stmt = mysqli_prepare($cnx, $sql_estado);
        mysqli_stmt_bind_param($stmt, "i", $id_pea);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
    } else {
        // =====================================================
        // MODO CREACIÓN: INSERT
        // =====================================================
        
        $sql_insert = "INSERT INTO pea (
            id_asignacion,
            estado,
            modalidad,
            campo_formacion,
            unidad_curricular,
            horas_docencia,
            horas_practicas,
            horas_practico_autonomo,
            horas_autonomas,
            horas_actividad_autonoma,
            titulo_docente,
            correo_docente,
            telefono_docente,
            tutoria_grupal,
            tutoria_individual,
            descripcion_asignatura,
            objetivo_general,
            eje_transversal,
            tematica_1,
            descripcion_tematica_1,
            tematica_2,
            descripcion_tematica_2,
            ra1,
            ra1_evidencia,
            ra1_contribucion,
            ra2,
            ra2_evidencia,
            ra2_contribucion,
            ra3,
            ra3_evidencia,
            ra3_contribucion,
            bibliografia_basica,
            bibliografia_complementaria,
            fecha_creacion
        ) VALUES (?, 'borrador', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = mysqli_prepare($cnx, $sql_insert);
        
        if (!$stmt) {
            throw new Exception("Error INSERT: " . mysqli_error($cnx));
        }
        
        // Construir type string: 1i + 3s + 5i + 23s = 32
        $type_insert = 'i' . str_repeat('s', 3) . str_repeat('i', 5) . str_repeat('s', 23);
        
        mysqli_stmt_bind_param($stmt, $type_insert,
            $id_asignacion,
            $modalidad,
            $campo_formacion,
            $unidad_curricular,
            $horas_docencia,
            $horas_practicas,
            $horas_practico_autonomo,
            $horas_autonomas,
            $horas_actividad_autonoma,
            $titulo_docente,
            $correo_docente,
            $telefono_docente,
            $tutoria_grupal,
            $tutoria_individual,
            $descripcion_asignatura,
            $objetivo_general,
            $eje_transversal,
            $tematica_1,
            $descripcion_tematica_1,
            $tematica_2,
            $descripcion_tematica_2,
            $ra1,
            $ra1_evidencia,
            $ra1_contribucion,
            $ra2,
            $ra2_evidencia,
            $ra2_contribucion,
            $ra3,
            $ra3_evidencia,
            $ra3_contribucion,
            $bibliografia_basica,
            $bibliografia_complementaria
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error ejecutar INSERT: " . mysqli_stmt_error($stmt));
        }
        
        $id_pea = mysqli_insert_id($cnx);
        mysqli_stmt_close($stmt);
    }
    
    // =====================================================
    // GUARDAR UNIDADES
    // =====================================================
    
    $sql_delete_unidades = "DELETE FROM unidades_pea WHERE id_pea = ?";
    $stmt = mysqli_prepare($cnx, $sql_delete_unidades);
    mysqli_stmt_bind_param($stmt, "i", $id_pea);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    $num_semanas = intval($_POST['num_semanas'] ?? 16);
    $unidad_num = 1;
    
    while (isset($_POST["unidad_{$unidad_num}_nombre"])) {
        $unidad_nombre = mysqli_real_escape_string($cnx, $_POST["unidad_{$unidad_num}_nombre"]);
        $unidad_descripcion = mysqli_real_escape_string($cnx, $_POST["unidad_{$unidad_num}_descripcion"] ?? '');
        $semana_inicio = intval($_POST["unidad_{$unidad_num}_semana_inicio"] ?? 0);
        $semana_fin = intval($_POST["unidad_{$unidad_num}_semana_fin"] ?? 0);
        
        if (!empty($unidad_nombre)) {
            $sql_unidad = "INSERT INTO unidades_pea (id_pea, numero, nombre, descripcion, semana_inicio, semana_fin) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($cnx, $sql_unidad);
            mysqli_stmt_bind_param($stmt, "iissii", $id_pea, $unidad_num, $unidad_nombre, $unidad_descripcion, $semana_inicio, $semana_fin);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error unidad {$unidad_num}: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        }
        
        $unidad_num++;
        if ($unidad_num > 10) break;
    }
    
    // =====================================================
    // CREAR/ACTUALIZAR SEMANAS
    // =====================================================
    
    $sql_get_unidades = "SELECT id_unidad, semana_inicio, semana_fin FROM unidades_pea WHERE id_pea = ? ORDER BY numero";
    $stmt = mysqli_prepare($cnx, $sql_get_unidades);
    mysqli_stmt_bind_param($stmt, "i", $id_pea);
    mysqli_stmt_execute($stmt);
    $result_unidades = mysqli_stmt_get_result($stmt);
    $unidades_creadas = [];
    while ($u = mysqli_fetch_assoc($result_unidades)) {
        $unidades_creadas[] = $u;
    }
    mysqli_stmt_close($stmt);
    
    if (!$modo_edicion) {
        for ($semana = 1; $semana <= $num_semanas; $semana++) {
            $id_unidad_semana = null;
            foreach ($unidades_creadas as $u) {
                if ($semana >= $u['semana_inicio'] && $semana <= $u['semana_fin']) {
                    $id_unidad_semana = $u['id_unidad'];
                    break;
                }
            }
            
            $sql_semana = "INSERT INTO semanas_pea (id_pea, id_unidad, numero_semana) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($cnx, $sql_semana);
            mysqli_stmt_bind_param($stmt, "iii", $id_pea, $id_unidad_semana, $semana);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error semana {$semana}: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        for ($semana = 1; $semana <= $num_semanas; $semana++) {
            $id_unidad_semana = null;
            foreach ($unidades_creadas as $u) {
                if ($semana >= $u['semana_inicio'] && $semana <= $u['semana_fin']) {
                    $id_unidad_semana = $u['id_unidad'];
                    break;
                }
            }
            
            $sql_update_semana = "UPDATE semanas_pea SET id_unidad = ? WHERE id_pea = ? AND numero_semana = ?";
            $stmt = mysqli_prepare($cnx, $sql_update_semana);
            mysqli_stmt_bind_param($stmt, "iii", $id_unidad_semana, $id_pea, $semana);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    
    // =====================================================
    // CONFIRMAR TRANSACCIÓN
    // =====================================================
    mysqli_commit($cnx);
    
    $_SESSION['mensaje_exito'] = $modo_edicion ? "PEA actualizado correctamente." : "PEA creado correctamente.";
    header('Location: ../../distribucion_semanas.php?id_pea=' . $id_pea);
    exit();
    
} catch (Exception $e) {
    mysqli_rollback($cnx);
    
    $_SESSION['mensaje_error'] = "Error: " . $e->getMessage();
    
    if ($modo_edicion) {
        header('Location: ../../crear_pea.php?id_pea=' . $id_pea);
    } else {
        header('Location: ../../crear_pea.php?id_asignacion=' . $id_asignacion);
    }
    exit();
}
?>
