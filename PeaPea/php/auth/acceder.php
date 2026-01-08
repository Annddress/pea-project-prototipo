<?php
/**
 * AUTENTICACIÓN DE USUARIOS
 * Sistema PEA - Instituto Superior Tecnológico Tena
 * 
 * Características:
 * - Login con cédula + contraseña
 * - Contraseñas hasheadas con bcrypt
 * - Detección de primer acceso
 * - Protección contra SQL Injection
 */

session_start();
require_once '../conexion.php';

// Verificar que se recibieron los datos
if (!isset($_POST['cedula']) || !isset($_POST['password'])) {
    header('Location: ../../login.php?error=2');
    exit();
}

// Obtener y limpiar datos
$cedula = trim($_POST['cedula']);
$password = $_POST['password'];

// Validar formato de cédula (10 dígitos)
if (!preg_match('/^[0-9]{10}$/', $cedula)) {
    header('Location: ../../login.php?error=1');
    exit();
}

// Buscar usuario con consulta preparada (previene SQL Injection)
$sql = "SELECT id_docente, cedula, nombres, apellidos, password, rol, 
               id_carrera_coordina, primer_acceso, activo 
        FROM docente 
        WHERE cedula = ?";

$stmt = mysqli_prepare($cnx, $sql);
mysqli_stmt_bind_param($stmt, "s", $cedula);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

if ($docente = mysqli_fetch_assoc($resultado)) {
    
    // Verificar si el usuario está activo
    if ($docente['activo'] != 1) {
        header('Location: ../../login.php?error=1');
        exit();
    }
    
    // Verificar contraseña
    // Primero intentamos con password_verify (contraseña hasheada)
    $password_valido = password_verify($password, $docente['password']);
    
    // Si falla, verificar si es primer acceso con cédula como contraseña
    // (para migración de contraseñas en texto plano)
    if (!$password_valido && $docente['primer_acceso'] == 1) {
        // Verificar si la contraseña es igual a la cédula (caso especial de primer acceso)
        if ($password === $cedula) {
            $password_valido = true;
            
            // Actualizar a contraseña hasheada
            $nuevo_hash = password_hash($cedula, PASSWORD_BCRYPT);
            $update_sql = "UPDATE docente SET password = ? WHERE id_docente = ?";
            $update_stmt = mysqli_prepare($cnx, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "si", $nuevo_hash, $docente['id_docente']);
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);
        }
    }
    
    if ($password_valido) {
        // Login exitoso - Crear sesión
        $_SESSION['id_docente'] = $docente['id_docente'];
        $_SESSION['cedula'] = $docente['cedula'];
        $_SESSION['nombres'] = $docente['nombres'];
        $_SESSION['apellidos'] = $docente['apellidos'];
        $_SESSION['nombre_completo'] = $docente['nombres'] . ' ' . $docente['apellidos'];
        $_SESSION['rol'] = $docente['rol'];
        $_SESSION['id_carrera_coordina'] = $docente['id_carrera_coordina'];
        $_SESSION['primer_acceso'] = $docente['primer_acceso'];
        
        // Actualizar último acceso
        $update_acceso = "UPDATE docente SET ultimo_acceso = NOW() WHERE id_docente = ?";
        $stmt_acceso = mysqli_prepare($cnx, $update_acceso);
        mysqli_stmt_bind_param($stmt_acceso, "i", $docente['id_docente']);
        mysqli_stmt_execute($stmt_acceso);
        mysqli_stmt_close($stmt_acceso);
        
        // Redirigir según primer_acceso o rol
        if ($docente['primer_acceso'] == 1) {
            // Debe cambiar contraseña
            header('Location: ../../cambiar_password.php');
        } else {
            // Redirigir según rol
            switch ($docente['rol']) {
                case 'administrador':
                    header('Location: ../../index.php');
                    break;
                case 'coordinador':
                    header('Location: ../../indexcordinador.php');
                    break;
                default:
                    header('Location: ../../indexusuario.php');
            }
        }
        exit();
    }
}

// Login fallido
mysqli_stmt_close($stmt);
header('Location: ../../login.php?error=1');
exit();
?>
