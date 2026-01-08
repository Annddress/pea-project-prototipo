<?php
session_start();
include('../conexion.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_pea = isset($_POST['id_pea']) ? intval($_POST['id_pea']) : null;

    if (!$id_pea) {
        $_SESSION['mensaje_error'] = "❌ Error: ID de PEA no encontrado.";
        header("Location: editar_distribucion_semanas.php");
        exit;
    }

    if ($cnx) {
        try {
            // Primero, eliminamos registros anteriores para este PEA
            $delete_stmt = $cnx->prepare("DELETE FROM semanas WHERE id_pea = ?");
            $delete_stmt->bind_param("i", $id_pea);
            $delete_stmt->execute();

            // Guardar nombres de unidades
            $nombres_unidades = [
                1 => $_POST['nombre_unidad_1'] ?? 'Unidad 1',
                2 => $_POST['nombre_unidad_2'] ?? 'Unidad 2',
                3 => $_POST['nombre_unidad_3'] ?? 'Unidad 3'
            ];

            // Verificar si existe la tabla unidades_pea
            $check_table = $cnx->query("SHOW TABLES LIKE 'unidades_pea'");
            
            if ($check_table->num_rows > 0) {
                // Si existe la tabla, actualizar nombres de unidades
                $delete_unidades = $cnx->prepare("DELETE FROM unidades_pea WHERE id_pea = ?");
                $delete_unidades->bind_param("i", $id_pea);
                $delete_unidades->execute();

                foreach ($nombres_unidades as $num_unidad => $nombre_unidad) {
                    $stmt_unidad = $cnx->prepare("INSERT INTO unidades_pea (id_pea, numero_unidad, nombre_unidad) VALUES (?, ?, ?)");
                    $stmt_unidad->bind_param("iis", $id_pea, $num_unidad, $nombre_unidad);
                    $stmt_unidad->execute();
                }
            }

            // Definir qué unidad corresponde a cada semana
            $unidades_semanas = [
                1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 1, 7 => 1, 8 => 1,    // Unidad 1: semanas 1-8
                9 => 2, 10 => 2, 11 => 2, 12 => 2, 13 => 2,                         // Unidad 2: semanas 9-13
                14 => 3, 15 => 3, 16 => 3                                            // Unidad 3: semanas 14-16
            ];

            // Insertar datos de semanas actualizados
            for ($i = 1; $i <= 16; $i++) {
                $docencia = $_POST["docencia_$i"] ?? 0;
                $con_docente = $_POST["con_docente_$i"] ?? 0;
                $trabajo_autonomo = $_POST["trabajo_autonomo_$i"] ?? 0;
                $actividad = $_POST["actividad_autonoma_$i"] ?? 0;
                
                // Determinar a qué unidad pertenece esta semana
                $numero_unidad = $unidades_semanas[$i];
                $nombre_unidad = $nombres_unidades[$numero_unidad];

                // Preparar la consulta incluyendo información de unidad
                $stmt = $cnx->prepare("INSERT INTO semanas (numero_semana, docencia, con_docente, trabajo_autonomo, actividad, id_pea, unidad, nombre_unidad) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

                if ($stmt === false) {
                    throw new Exception("Error al preparar la consulta: " . $cnx->error);
                }

                // Vincular parámetros
                $stmt->bind_param("iiiiiiss", $i, $docencia, $con_docente, $trabajo_autonomo, $actividad, $id_pea, $numero_unidad, $nombre_unidad);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error al ejecutar la consulta para semana $i: " . $stmt->error);
                }
            }

            // Mensaje de éxito
            $_SESSION['mensaje_exito'] = "✅ Distribución de horas actualizada correctamente.";
            
            // Redirigir al resumen final
            header("Location: resumen_pea.php?id_pea=" . $id_pea);
            exit;

        } catch (Exception $e) {
            $_SESSION['mensaje_error'] = "❌ Error al actualizar la distribución: " . $e->getMessage();
            header("Location: editar_distribucion_semanas.php?id_pea=" . $id_pea);
            exit;
        }

    } else {
        $_SESSION['mensaje_error'] = "❌ Error de conexión: " . mysqli_connect_error();
        header("Location: editar_distribucion_semanas.php?id_pea=" . $id_pea);
        exit;
    }
} else {
    $_SESSION['mensaje_error'] = "❌ Acceso no permitido.";
    header("Location: editar_distribucion_semanas.php");
    exit;
}
?>