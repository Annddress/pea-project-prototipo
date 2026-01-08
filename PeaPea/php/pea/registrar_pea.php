<?php
session_start();
include("../conexion.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $carrera = $_POST['carrera'];
    $docente = $_POST['docente'];
    $asignatura = $_POST['asignatura'];
    $modalidad = $_POST['modalidad'];
    $jornada = $_POST['jornada'];
    $periodo = $_POST['periodo'];
    $ciclo = $_POST['ciclo'];
    $campo_formacion = $_POST['campo_formacion'];
    $unidad_curricular = $_POST['unidad_curricular'];
    $codigo_asignatura = $_POST['codigo_asignatura'];
    $horas_total = $_POST['horas_total'];
    $titulo_docente = $_POST['titulo_docente'];
    $telefono = $_POST['telefono'] ?? null;
    $tutoria_grupal = $_POST['tutoria_grupal'];
    $tutoria_individual = $_POST['tutoria_individual'];
    $h_docencia = $_POST['h_docencia'];
    $h_contacto = $_POST['h_contacto'];
    $h_autonomo = $_POST['h_autonomo'];
    $h_actividad_autonoma = $_POST['h_actividad_autonoma'] ?? 0;

    // Usar consulta preparada para mayor seguridad
    $sql = "INSERT INTO pea (
        carrera, docente, asignatura, modalidad, jornada, periodo, ciclo,
        campo_formacion, unidad_curricular, codigo_asignatura,
        horas_total, titulo_docente, telefono, tutoria_grupal, tutoria_individual,
        h_docencia, h_contacto, h_autonomo, h_actividad_autonoma
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $cnx->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("ssssssssssisssiiiii", 
            $carrera, $docente, $asignatura, $modalidad, $jornada, $periodo, $ciclo,
            $campo_formacion, $unidad_curricular, $codigo_asignatura,
            $horas_total, $titulo_docente, $telefono, $tutoria_grupal, $tutoria_individual,
            $h_docencia, $h_contacto, $h_autonomo, $h_actividad_autonoma
        );
        
        if ($stmt->execute()) {
            $id_pea = $cnx->insert_id;
            $_SESSION['id_pea'] = $id_pea;
            header("Location: ../../completar_pea.php");
            exit;
        } else {
            echo "Error al guardar: " . $stmt->error;
        }
        
        $stmt->close();
    } else {
        echo "Error al preparar la consulta: " . $cnx->error;
    }
}
?>