<?php
/**
 * CONFIGURACIÓN DE BASE DE DATOS
 * Sistema PEA - Instituto Superior Tecnológico Tena
 */
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pea');
// Configuración de la aplicación
define('APP_NAME', 'Sistema PEA');
define('APP_VERSION', '2.0');
define('INSTITUCION', 'Instituto Superior Tecnológico Tena');
// Zona horaria
date_default_timezone_set('America/Guayaquil');
// Conexión a la base de datos
$cnx = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verificar conexión
if (!$cnx) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());}
// Establecer charset
mysqli_set_charset($cnx, "utf8mb4");
// Función helper para consultas preparadas
function ejecutarConsulta($cnx, $sql, $params = [], $types = "") {
    $stmt = mysqli_prepare($cnx, $sql);
    
    if ($params && $types) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return $result;
}
// Función para escapar datos
function limpiar($cnx, $dato) {
    return mysqli_real_escape_string($cnx, trim($dato));
}
?>
