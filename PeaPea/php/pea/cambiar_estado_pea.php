<?php
session_start();
header('Content-Type: application/json');

require_once '../conexion.php';

if (!isset($_SESSION['id_docente']) || $_SESSION['rol'] !== 'coordinador') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$id_pea = intval($_POST['id_pea'] ?? 0);
$accion = $_POST['accion'] ?? '';
$observaciones = $_POST['observaciones'] ?? '';
$id_carrera_coord = $_SESSION['id_carrera_coordina'];

// Acciones válidas: aprobar, rechazar, devolver
if (!$id_pea || !in_array($accion, ['aprobar', 'rechazar', 'devolver'])) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

// Verificar que el PEA pertenece a la carrera del coordinador
// Para aprobar/rechazar: debe estar en 'enviado'
// Para devolver: puede estar en 'enviado' o 'aprobado'
$estados_validos = ($accion === 'devolver') ? "'enviado', 'aprobado'" : "'enviado'";

$sql_check = "SELECT p.id_pea, p.estado, a.id_docente, d.email, d.nombres, d.apellidos,
                     asig.nombre as asignatura
              FROM pea p
              INNER JOIN asignacion a ON p.id_asignacion = a.id_asignacion
              INNER JOIN asignatura asig ON a.id_asignatura = asig.id_asignatura
              INNER JOIN docente d ON a.id_docente = d.id_docente
              WHERE p.id_pea = ? AND asig.id_carrera = ? AND p.estado IN ({$estados_validos})";

$stmt = mysqli_prepare($cnx, $sql_check);
mysqli_stmt_bind_param($stmt, "ii", $id_pea, $id_carrera_coord);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pea = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$pea) {
    echo json_encode(['success' => false, 'message' => 'PEA no encontrado o no está en un estado válido para esta acción']);
    exit();
}

// Procesar según la acción
switch ($accion) {
    case 'aprobar':
        $nuevo_estado = 'aprobado';
        $sql_update = "UPDATE pea SET estado = 'aprobado' WHERE id_pea = ?";
        $stmt = mysqli_prepare($cnx, $sql_update);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Error SQL: ' . mysqli_error($cnx)]);
            exit();
        }
        mysqli_stmt_bind_param($stmt, "i", $id_pea);
        $mensaje_exito = 'PEA aprobado correctamente';
        break;
        
    case 'rechazar':
        if (empty(trim($observaciones))) {
            echo json_encode(['success' => false, 'message' => 'Debe indicar las observaciones del rechazo']);
            exit();
        }
        
        $nuevo_estado = 'rechazado';
        $sql_update = "UPDATE pea SET estado = 'rechazado', observaciones_rechazo = ? WHERE id_pea = ?";
        $stmt = mysqli_prepare($cnx, $sql_update);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Error SQL: ' . mysqli_error($cnx)]);
            exit();
        }
        mysqli_stmt_bind_param($stmt, "si", $observaciones, $id_pea);
        $mensaje_exito = 'PEA rechazado. El docente podrá corregirlo.';
        break;
        
    case 'devolver':
        $nuevo_estado = 'borrador';
        $sql_update = "UPDATE pea SET estado = 'borrador', observaciones_rechazo = NULL WHERE id_pea = ?";
        $stmt = mysqli_prepare($cnx, $sql_update);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Error SQL: ' . mysqli_error($cnx)]);
            exit();
        }
        mysqli_stmt_bind_param($stmt, "i", $id_pea);
        $mensaje_exito = 'PEA devuelto a borrador. El docente podrá editarlo.';
        break;
        case 'enviar':
    // Verificar que el docente sea dueño del PEA
    $sql_verificar = "SELECT p.id_pea, a.id_docente 
                      FROM pea p 
                      INNER JOIN asignacion a ON p.id_asignacion = a.id_asignacion 
                      WHERE p.id_pea = ? AND a.id_docente = ? AND p.estado IN ('borrador', 'rechazado')";
    $stmt = mysqli_prepare($cnx, $sql_verificar);
    mysqli_stmt_bind_param($stmt, "ii", $id_pea, $_SESSION['id_docente']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        echo json_encode(['success' => false, 'message' => 'No tiene permiso para enviar este PEA o ya fue enviado.']);
        exit;
    }
    mysqli_stmt_close($stmt);
    
    $sql = "UPDATE pea SET estado = 'enviado', fecha_envio = NOW() WHERE id_pea = ?";
    $stmt = mysqli_prepare($cnx, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_pea);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'PEA enviado para revisión.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al enviar el PEA.']);
    }
    mysqli_stmt_close($stmt);
    break;
}

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    
    // Aquí podrías agregar envío de correo al docente
    // mail($pea['email'], 'PEA ' . $nuevo_estado, '...');
    
    echo json_encode([
        'success' => true, 
        'message' => $mensaje_exito,
        'estado' => $nuevo_estado
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . mysqli_error($cnx)]);
}

mysqli_close($cnx);
