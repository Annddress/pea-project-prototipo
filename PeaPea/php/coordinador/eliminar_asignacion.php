<?php
session_start();

if (!isset($_SESSION['id_docente']) || $_SESSION['rol'] !== 'coordinador') {
    header('Location: ../../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_asignacion'])) {
    require_once '../conexion.php';
    
    $id_asignacion = intval($_POST['id_asignacion']);
    
    // Verificar que la asignación pertenece a la carrera del coordinador
    $sql_check = "SELECT a.id_asignacion 
                  FROM asignacion a
                  INNER JOIN asignatura asig ON a.id_asignatura = asig.id_asignatura
                  WHERE a.id_asignacion = ? AND asig.id_carrera = ?";
    $stmt = mysqli_prepare($cnx, $sql_check);
    mysqli_stmt_bind_param($stmt, "ii", $id_asignacion, $_SESSION['id_carrera_coordina']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        // Eliminar asignación
        $sql_delete = "DELETE FROM asignacion WHERE id_asignacion = ?";
        $stmt_delete = mysqli_prepare($cnx, $sql_delete);
        mysqli_stmt_bind_param($stmt_delete, "i", $id_asignacion);
        mysqli_stmt_execute($stmt_delete);
        mysqli_stmt_close($stmt_delete);
    }
    mysqli_stmt_close($stmt);
}

header('Location: ../../asignar_materias.php');
exit();
?>
