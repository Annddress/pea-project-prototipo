<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_docente'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit();
}

require_once '../conexion.php';

$id_docente = $_SESSION['id_docente'];
$pass_actual = $_POST['pass_actual'] ?? '';
$pass_nueva = $_POST['pass_nueva'] ?? '';

if (strlen($pass_nueva) < 6) {
    echo json_encode(['success' => false, 'message' => 'Mínimo 6 caracteres']);
    exit();
}

$sql = "SELECT password FROM docente WHERE id_docente = ?";
$stmt = mysqli_prepare($cnx, $sql);
mysqli_stmt_bind_param($stmt, "i", $id_docente);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$docente = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!password_verify($pass_actual, $docente['password'])) {
    echo json_encode(['success' => false, 'message' => 'Contraseña actual incorrecta']);
    exit();
}

$nuevo_hash = password_hash($pass_nueva, PASSWORD_BCRYPT);
$update = "UPDATE docente SET password = ? WHERE id_docente = ?";
$stmt = mysqli_prepare($cnx, $update);
mysqli_stmt_bind_param($stmt, "si", $nuevo_hash, $id_docente);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'message' => 'Contraseña actualizada']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar']);
}
mysqli_stmt_close($stmt);