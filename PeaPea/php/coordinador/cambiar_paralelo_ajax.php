<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_docente']) || $_SESSION['rol'] !== 'coordinador') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once '../conexion.php';

$id_asignacion = intval($_POST['id_asignacion'] ?? 0);
$paralelo = $_POST['paralelo'] ?? '';

if ($id_asignacion <= 0 || !in_array($paralelo, ['A', 'B', 'AB'])) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

// Verificar que la asignación pertenece a la carrera del coordinador
$id_carrera = $_SESSION['id_carrera_coordina'];

$sql_check = "SELECT a.id_asignacion FROM asignacion a 
              INNER JOIN asignatura asig ON a.id_asignatura = asig.id_asignatura 
              WHERE a.id_asignacion = ? AND asig.id_carrera = ?";
$stmt = mysqli_prepare($cnx, $sql_check);
mysqli_stmt_bind_param($stmt, "ii", $id_asignacion, $id_carrera);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Asignación no encontrada']);
    exit();
}
mysqli_stmt_close($stmt);

// Determinar jornada según paralelo
if ($paralelo === 'A') {
    $jornada = 'matutina';
} elseif ($paralelo === 'B') {
    $jornada = 'nocturna';
} else {
    $jornada = 'ambas';
}

// Actualizar
$sql_update = "UPDATE asignacion SET paralelo = ?, jornada = ? WHERE id_asignacion = ?";
$stmt = mysqli_prepare($cnx, $sql_update);
mysqli_stmt_bind_param($stmt, "ssi", $paralelo, $jornada, $id_asignacion);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar']);
}
mysqli_stmt_close($stmt);