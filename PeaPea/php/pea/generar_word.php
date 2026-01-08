<?php
// generar_word_solucion_robusta.php - SOLUCIÓN ROBUSTA PARA MARCADORES
require_once '../../vendor/autoload.php';
include('../conexion.php');

// Verificar si se pasó el ID del PEA
$id_pea = isset($_GET['id_pea']) ? intval($_GET['id_pea']) : null;

if (!$id_pea) {
    die("Error: ID de PEA no especificado.");
}

// Consultar datos del PEA
$query_pea = "SELECT * FROM pea WHERE id_pea = ?";
$stmt_pea = $cnx->prepare($query_pea);
$stmt_pea->bind_param("i", $id_pea);
$stmt_pea->execute();
$result_pea = $stmt_pea->get_result();
$pea_data = $result_pea->fetch_assoc();

if (!$pea_data) {
    die("Error: PEA no encontrado.");
}

// Consultar datos de semanas
$query_semanas = "SELECT * FROM semanas WHERE id_pea = ? ORDER BY numero_semana";
$stmt_semanas = $cnx->prepare($query_semanas);
$stmt_semanas->bind_param("i", $id_pea);
$stmt_semanas->execute();
$result_semanas = $stmt_semanas->get_result();
$semanas_data = $result_semanas->fetch_all(MYSQLI_ASSOC);

// Organizar semanas por unidades y calcular totales
$unidades = [
    1 => ['nombre' => 'Unidad 1', 'semanas' => []],
    2 => ['nombre' => 'Unidad 2', 'semanas' => []],
    3 => ['nombre' => 'Unidad 3', 'semanas' => []]
];

foreach ($semanas_data as $semana) {
    $num_semana = $semana['numero_semana'];
    if ($num_semana >= 1 && $num_semana <= 8) {
        $unidades[1]['semanas'][] = $semana;
        if ($semana['nombre_unidad']) $unidades[1]['nombre'] = $semana['nombre_unidad'];
    } elseif ($num_semana >= 9 && $num_semana <= 13) {
        $unidades[2]['semanas'][] = $semana;
        if ($semana['nombre_unidad']) $unidades[2]['nombre'] = $semana['nombre_unidad'];
    } elseif ($num_semana >= 14 && $num_semana <= 16) {
        $unidades[3]['semanas'][] = $semana;
        if ($semana['nombre_unidad']) $unidades[3]['nombre'] = $semana['nombre_unidad'];
    }
}

// Calcular totales por unidad
$totales_por_unidad = [];
foreach ($unidades as $num_unidad => $unidad) {
    $totales_por_unidad[$num_unidad] = [
        'docencia' => 0,
        'con_docente' => 0,
        'trabajo_autonomo' => 0,
        'actividad' => 0,
        'total_unidad' => 0
    ];
    
    foreach ($unidad['semanas'] as $semana) {
        $totales_por_unidad[$num_unidad]['docencia'] += $semana['docencia'];
        $totales_por_unidad[$num_unidad]['con_docente'] += $semana['con_docente'];
        $totales_por_unidad[$num_unidad]['trabajo_autonomo'] += $semana['trabajo_autonomo'];
        $totales_por_unidad[$num_unidad]['actividad'] += $semana['actividad'];
    }
    
    $totales_por_unidad[$num_unidad]['total_unidad'] = 
        $totales_por_unidad[$num_unidad]['docencia'] + 
        $totales_por_unidad[$num_unidad]['con_docente'] + 
        $totales_por_unidad[$num_unidad]['trabajo_autonomo'] + 
        $totales_por_unidad[$num_unidad]['actividad'];
}

// Calcular totales generales
$total_docencia = array_sum(array_column($totales_por_unidad, 'docencia'));
$total_con_docente = array_sum(array_column($totales_por_unidad, 'con_docente'));
$total_trabajo_autonomo = array_sum(array_column($totales_por_unidad, 'trabajo_autonomo'));
$total_actividad = array_sum(array_column($totales_por_unidad, 'actividad'));
$gran_total = $total_docencia + $total_con_docente + $total_trabajo_autonomo + $total_actividad;

try {
    $ruta_plantilla = 'plantillas/pea_plantilla_BACKUP.docx';
    
    // Verifico que la plantilla existe
    if (!file_exists($ruta_plantilla)) {
        throw new Exception("La plantilla no se encuentra en: " . $ruta_plantilla);
    }
    
    // Aqui abro la plantilla como ZIP
    $zip = new ZipArchive();
    if ($zip->open($ruta_plantilla) === TRUE) {
        
        // Leer el contenido del documento principal
        $documentXML = $zip->getFromName('word/document.xml');
        
        if ($documentXML === false) {
            throw new Exception("No se pudo leer el contenido del documento Word");
        }
        
        // Crear array de reemplazos con valores seguros
        $reemplazos = [
            '{CARRERA}' => htmlspecialchars($pea_data['carrera'] ?: 'No especificada', ENT_XML1, 'UTF-8'),
            '{ASIGNATURA}' => htmlspecialchars($pea_data['asignatura'] ?: 'No especificada', ENT_XML1, 'UTF-8'),
            '{MODALIDAD}' => htmlspecialchars($pea_data['modalidad'] ?: 'No especificada', ENT_XML1, 'UTF-8'),
            '{CAMPO_FORMACION}' => htmlspecialchars($pea_data['campo_formacion'] ?: 'No especificado', ENT_XML1, 'UTF-8'),
            '{JORNADA}' => htmlspecialchars($pea_data['jornada'] ?: 'No especificada', ENT_XML1, 'UTF-8'),
            '{UNIDAD_CURRICULAR}' => htmlspecialchars($pea_data['unidad_curricular'] ?: 'No especificada', ENT_XML1, 'UTF-8'),
            '{PERIODO}' => htmlspecialchars($pea_data['periodo'] ?: 'No especificado', ENT_XML1, 'UTF-8'),
            '{CODIGO_ASIGNATURA}' => htmlspecialchars($pea_data['codigo_asignatura'] ?: 'No especificado', ENT_XML1, 'UTF-8'),
            '{HORAS_TOTAL}' => strval($pea_data['horas_total'] ?: '0'),
            
            // Distribución de horas
            '{H_DOCENCIA}' => strval($pea_data['h_docencia'] ?: '0'),
            '{H_CONTACTO}' => strval($pea_data['h_contacto'] ?: '0'),
            '{H_AUTONOMO}' => strval($pea_data['h_autonomo'] ?: '0'),
            '{H_ACTIVIDAD_AUTONOMA}' => strval($pea_data['h_actividad_autonoma'] ?: '0'),
            
            // Datos del docente
            '{DOCENTE}' => htmlspecialchars($pea_data['docente'] ?: 'No especificado', ENT_XML1, 'UTF-8'),
            '{TELEFONO}' => htmlspecialchars($pea_data['telefono'] ?: 'No especificado', ENT_XML1, 'UTF-8'),
            '{TITULO_DOCENTE}' => htmlspecialchars($pea_data['titulo_docente'] ?: 'No especificado', ENT_XML1, 'UTF-8'),
            '{CORREO}' => htmlspecialchars($pea_data['correo'] ?: 'No especificado', ENT_XML1, 'UTF-8'),
            '{TUTORIA_GRUPAL}' => htmlspecialchars($pea_data['tutoria_grupal'] ?: 'No especificado', ENT_XML1, 'UTF-8'),
            '{TUTORIA_INDIVIDUAL}' => htmlspecialchars($pea_data['tutoria_individual'] ?: 'No especificado', ENT_XML1, 'UTF-8'),
            
            // Contenidos académicos
            '{DESCRIPCION_ASIGNATURA}' => htmlspecialchars($pea_data['descripcion_asignatura'] ?: 'No especificada', ENT_XML1, 'UTF-8'),
            '{OBJETIVO_GENERAL}' => htmlspecialchars($pea_data['objetivo_general'] ?: 'No especificado', ENT_XML1, 'UTF-8'),
            '{EJE_TRANSVERSAL}' => htmlspecialchars($pea_data['eje_transversal'] ?: 'No especificado', ENT_XML1, 'UTF-8'),
            '{TEMATICA_1}' => htmlspecialchars($pea_data['tematica_1'] ?: 'No especificada', ENT_XML1, 'UTF-8'),
            '{DESCRIPCION_1}' => htmlspecialchars($pea_data['descripcion_1'] ?: 'No especificada', ENT_XML1, 'UTF-8'),
            '{TEMATICA_2}' => htmlspecialchars($pea_data['tematica_2'] ?: 'No especificada', ENT_XML1, 'UTF-8'),
            '{DESCRIPCION_2}' => htmlspecialchars($pea_data['descripcion_2'] ?: 'No especificada', ENT_XML1, 'UTF-8'),
            
            // Resultados de aprendizaje
            '{RA1}' => htmlspecialchars($pea_data['ra1'] ?: 'No especificado', ENT_XML1, 'UTF-8'),
            '{RA2}' => htmlspecialchars($pea_data['ra2'] ?: 'No especificado', ENT_XML1, 'UTF-8'),
            '{RA3}' => htmlspecialchars($pea_data['ra3'] ?: 'No especificado', ENT_XML1, 'UTF-8'),
            '{PE1}' => htmlspecialchars($pea_data['pe1'] ?: 'No especificado', ENT_XML1, 'UTF-8'),
            '{PE2}' => htmlspecialchars($pea_data['pe2'] ?: 'No especificado', ENT_XML1, 'UTF-8'),
            '{PE3}' => htmlspecialchars($pea_data['pe3'] ?: 'No especificado', ENT_XML1, 'UTF-8'),
            '{CONTRIBUCION_1}' => htmlspecialchars($pea_data['contribucion_1'] ?: 'No especificada', ENT_XML1, 'UTF-8'),
            '{CONTRIBUCION_2}' => htmlspecialchars($pea_data['contribucion_2'] ?: 'No especificada', ENT_XML1, 'UTF-8'),
            '{CONTRIBUCION_3}' => htmlspecialchars($pea_data['contribucion_3'] ?: 'No especificada', ENT_XML1, 'UTF-8'),
            
            // Datos de las unidades
            '{UNIDAD_1_NOMBRE}' => htmlspecialchars($unidades[1]['nombre'], ENT_XML1, 'UTF-8'),
            '{UNIDAD_1_DOCENCIA}' => strval($totales_por_unidad[1]['docencia']),
            '{UNIDAD_1_CONTACTO}' => strval($totales_por_unidad[1]['con_docente']),
            '{UNIDAD_1_TRABAJO_AUTONOMO}' => strval($totales_por_unidad[1]['trabajo_autonomo']),
            '{UNIDAD_1_ACTIVIDAD}' => strval($totales_por_unidad[1]['actividad']),
            '{UNIDAD_1_TOTAL}' => strval($totales_por_unidad[1]['total_unidad']),
            
            '{UNIDAD_2_NOMBRE}' => htmlspecialchars($unidades[2]['nombre'], ENT_XML1, 'UTF-8'),
            '{UNIDAD_2_DOCENCIA}' => strval($totales_por_unidad[2]['docencia']),
            '{UNIDAD_2_CONTACTO}' => strval($totales_por_unidad[2]['con_docente']),
            '{UNIDAD_2_TRABAJO_AUTONOMO}' => strval($totales_por_unidad[2]['trabajo_autonomo']),
            '{UNIDAD_2_ACTIVIDAD}' => strval($totales_por_unidad[2]['actividad']),
            '{UNIDAD_2_TOTAL}' => strval($totales_por_unidad[2]['total_unidad']),
            
            '{UNIDAD_3_NOMBRE}' => htmlspecialchars($unidades[3]['nombre'], ENT_XML1, 'UTF-8'),
            '{UNIDAD_3_DOCENCIA}' => strval($totales_por_unidad[3]['docencia']),
            '{UNIDAD_3_CONTACTO}' => strval($totales_por_unidad[3]['con_docente']),
            '{UNIDAD_3_TRABAJO_AUTONOMO}' => strval($totales_por_unidad[3]['trabajo_autonomo']),
            '{UNIDAD_3_ACTIVIDAD}' => strval($totales_por_unidad[3]['actividad']),
            '{UNIDAD_3_TOTAL}' => strval($totales_por_unidad[3]['total_unidad']),
            
            // Totales generales
            '{TOTAL_DOCENCIA}' => strval($total_docencia),
            '{TOTAL_CONTACTO}' => strval($total_con_docente),
            '{TOTAL_TRABAJO_AUTONOMO}' => strval($total_trabajo_autonomo),
            '{TOTAL_ACTIVIDAD}' => strval($total_actividad),
            '{GRAN_TOTAL}' => strval($gran_total)
        ];
        
        // MÉTODO ROBUSTO: Buscar marcadores con caracteres invisibles/formato
        $marcadores_problemáticos = [
            '/\{[\s\p{Z}\p{C}]*PE1[\s\p{Z}\p{C}]*\}/u',
            '/\{[\s\p{Z}\p{C}]*UNIDAD[\s\p{Z}\p{C}]*_[\s\p{Z}\p{C}]*2[\s\p{Z}\p{C}]*_[\s\p{Z}\p{C}]*DOCENCIA[\s\p{Z}\p{C}]*\}/u',
            '/\{[\s\p{Z}\p{C}]*UNIDAD[\s\p{Z}\p{C}]*_[\s\p{Z}\p{C}]*3[\s\p{Z}\p{C}]*_[\s\p{Z}\p{C}]*DOCENCIA[\s\p{Z}\p{C}]*\}/u'
        ];
        
        $valores_problemáticos = [
            htmlspecialchars($pea_data['pe1'] ?: 'No especificado', ENT_XML1, 'UTF-8'),
            strval($totales_por_unidad[2]['docencia']),
            strval($totales_por_unidad[3]['docencia'])
        ];
        
        // Aplicar reemplazos con expresiones regulares para marcadores problemáticos
        for ($i = 0; $i < count($marcadores_problemáticos); $i++) {
            $documentXML = preg_replace($marcadores_problemáticos[$i], $valores_problemáticos[$i], $documentXML);
        }
        
        // Realizar los reemplazos normales
        foreach ($reemplazos as $marcador => $valor) {
            // Método normal
            $documentXML = str_replace($marcador, $valor, $documentXML);
            
            // Método robusto con expresiones regulares para manejar espacios y formato
            $patron_robusto = '/\{[\s\p{Z}\p{C}]*' . preg_quote(trim($marcador, '{}'), '/') . '[\s\p{Z}\p{C}]*\}/u';
            $documentXML = preg_replace($patron_robusto, $valor, $documentXML);
        }
        
        $zip->close();
        
        // Crear nuevo archivo con los datos reemplazados
        $nuevo_zip = new ZipArchive();
        $nombre_archivo = 'PEA_' . 
                         str_replace(' ', '_', $pea_data['asignatura']) . '_' .
                         str_replace(' ', '_', $pea_data['docente']) . '_' .
                         date('Y-m-d') . '.docx';
        
        $nombre_archivo = preg_replace('/[^A-Za-z0-9_\-\.]/', '', $nombre_archivo);
        $archivo_temporal = tempnam(sys_get_temp_dir(), 'pea_') . '.docx';
        
        // Copiar plantilla y actualizar contenido
        copy($ruta_plantilla, $archivo_temporal);
        
        if ($nuevo_zip->open($archivo_temporal) === TRUE) {
            $nuevo_zip->addFromString('word/document.xml', $documentXML);
            $nuevo_zip->close();
            
            // Headers para descarga
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment;filename="' . $nombre_archivo . '"');
            header('Cache-Control: max-age=0');
            header('Cache-Control: max-age=1');
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Cache-Control: cache, must-revalidate');
            header('Pragma: public');
            
            // Enviar archivo
            readfile($archivo_temporal);
            unlink($archivo_temporal);
            
        } else {
            throw new Exception("No se pudo crear el archivo Word modificado");
        }
        
    } else {
        throw new Exception("No se pudo abrir la plantilla como archivo ZIP");
    }
    
} catch (Exception $e) {
    echo "<div style='max-width: 800px; margin: 50px auto; padding: 30px; font-family: Arial, sans-serif; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 10px;'>";
    echo "<h2 style='color: #721c24;'>❌ Error al generar el documento</h2>";
    echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
    echo "<div style='margin-top: 30px;'>";
    echo "<a href='resumen_pea.php?id_pea=$id_pea' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Volver al resumen</a>";
    echo "</div>";
    echo "</div>";
}
?>