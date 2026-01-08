<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_docente']) || $_SESSION['rol'] !== 'coordinador') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once '../conexion.php';

$id_asignacion = intval($_POST['id_asignacion'] ?? 0);

if ($id_asignacion <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
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

// Eliminar
$sql_delete = "DELETE FROM asignacion WHERE id_asignacion = ?";
$stmt = mysqli_prepare($cnx, $sql_delete);
mysqli_stmt_bind_param($stmt, "i", $id_asignacion);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
}
mysqli_stmt_close($stmt);