<?php
/**
 * SCRIPT DE INICIALIZACIÓN DE CONTRASEÑAS
 * ========================================
 * Este script debe ejecutarse UNA SOLA VEZ después de importar el SQL
 * Hashea las contraseñas de todos los docentes usando su cédula como contraseña inicial
 * 
 * Ejecutar desde navegador: http://tudominio/php/config/inicializar_passwords.php
 * O desde terminal: php inicializar_passwords.php
 */

require_once __DIR__ . '/../conexion.php';

// Verificar conexión
if (!$cnx) {
    die("Error de conexión: " . mysqli_connect_error());
}

echo "<h2>Inicializando contraseñas del Sistema PEA</h2>";
echo "<pre>";

// Obtener todos los docentes
$query = "SELECT id_docente, cedula, apellidos, nombres FROM docente";
$resultado = mysqli_query($cnx, $query);

if (!$resultado) {
    die("Error en consulta: " . mysqli_error($cnx));
}

$actualizados = 0;
$errores = 0;

while ($docente = mysqli_fetch_assoc($resultado)) {
    $id = $docente['id_docente'];
    $cedula = $docente['cedula'];
    $nombre = $docente['apellidos'] . ' ' . $docente['nombres'];
    
    // Hashear la cédula como contraseña inicial
    $password_hash = password_hash($cedula, PASSWORD_BCRYPT);
    
    // Actualizar en la base de datos
    $update = "UPDATE docente SET password = ? WHERE id_docente = ?";
    $stmt = mysqli_prepare($cnx, $update);
    mysqli_stmt_bind_param($stmt, "si", $password_hash, $id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo "✓ $nombre (Cédula: $cedula) - Contraseña hasheada correctamente\n";
        $actualizados++;
    } else {
        echo "✗ ERROR: $nombre - " . mysqli_error($cnx) . "\n";
        $errores++;
    }
    
    mysqli_stmt_close($stmt);
}

echo "\n========================================\n";
echo "Proceso completado:\n";
echo "- Docentes actualizados: $actualizados\n";
echo "- Errores: $errores\n";
echo "========================================\n";

if ($errores == 0) {
    echo "\n¡ÉXITO! Todos los docentes pueden iniciar sesión con:\n";
    echo "- Usuario: Su número de cédula\n";
    echo "- Contraseña: Su número de cédula (deberán cambiarla en el primer acceso)\n";
}

echo "</pre>";

// Cerrar conexión
mysqli_close($cnx);
?>
