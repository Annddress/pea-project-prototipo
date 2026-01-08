<?php
session_start();

// Función para recuperar datos guardados
function getFormValue($field_name, $default = '') {
    return isset($_SESSION['form_data'][$field_name]) ? 
           htmlspecialchars($_SESSION['form_data'][$field_name]) : $default;
}

// Función para selects
function getSelectSelected($field_name, $option_value) {
    return (isset($_SESSION['form_data'][$field_name]) && 
            $_SESSION['form_data'][$field_name] == $option_value) ? 'selected' : '';
}
?>

<!-- Contenedor principal del formulario -->
<div class="form-container-ajax">
    <div class="form-header">
        <h1>📚 Programa de Estudio de la Asignatura</h1>
        <?php if (!empty($_SESSION['form_data'])): ?>
            <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0;">
                ✅ Datos recuperados automáticamente
            </div>
        <?php endif; ?>
    </div>
    
    <div class="form-content">
        <form action="php/pea/guardar_pea_completo.php" method="POST" id="formPEA">
            <div class="form-section active" id="parte1">
                <h2>📚 Programa de Estudio de la Asignatura - Parte 1</h2>
                
                <!-- Información General de la Carrera -->
                <div class="section-group">
                    <h3>🎓 Información General</h3>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label>Carrera:</label>
                            <select name="carrera" required>
                                <option value="">Seleccionar carrera</option>
                                <option value="Tecnología en Desarrollo de Software" <?php echo getSelectSelected('carrera', 'Tecnología en Desarrollo de Software'); ?>>Tecnología en Desarrollo de Software</option>
                                <option value="Tecnología en Administración" <?php echo getSelectSelected('carrera', 'Tecnología en Administración'); ?>>Tecnología en Administración</option>
                                <option value="Tecnología en Desarrollo Infantil" <?php echo getSelectSelected('carrera', 'Tecnología en Desarrollo Infantil'); ?>>Tecnología en Desarrollo Infantil</option>
                                <option value="Tecnología en Turismo" <?php echo getSelectSelected('carrera', 'Tecnología en Turismo'); ?>>Tecnología en Turismo</option>
                            </select>
                        </div>
                        
                        <div class="form-col">
                            <label>Modalidad:</label>
                            <select name="modalidad" required>
                                <option value="">Seleccionar modalidad</option>
                                <option value="Presencial" <?php echo getSelectSelected('modalidad', 'Presencial'); ?>>Presencial</option>
                                <option value="Virtual" <?php echo getSelectSelected('modalidad', 'Virtual'); ?>>Virtual</option>
                                <option value="Híbrida" <?php echo getSelectSelected('modalidad', 'Híbrida'); ?>>Híbrida</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <label>Jornada:</label>
                            <select name="jornada" required>
                                <option value="">Seleccionar jornada</option>
                                <option value="Matutina" <?php echo getSelectSelected('jornada', 'Matutina'); ?>>Matutina</option>
                                <option value="Vespertina" <?php echo getSelectSelected('jornada', 'Vespertina'); ?>>Vespertina</option>
                                <option value="Nocturna" <?php echo getSelectSelected('jornada', 'Nocturna'); ?>>Nocturna</option>
                            </select>
                        </div>
                        
                        <div class="form-col">
                            <label>Periodo académico:</label>
                            <select name="periodo" required>
                                <option value="">Seleccionar periodo</option>
                                <option value="Primero" <?php echo getSelectSelected('periodo', 'Primero'); ?>>Primero</option>
                                <option value="Segundo" <?php echo getSelectSelected('periodo', 'Segundo'); ?>>Segundo</option>
                                <option value="Tercero" <?php echo getSelectSelected('periodo', 'Tercero'); ?>>Tercero</option>
                                <option value="Cuarto" <?php echo getSelectSelected('periodo', 'Cuarto'); ?>>Cuarto</option>
                                <option value="Quinto" <?php echo getSelectSelected('periodo', 'Quinto'); ?>>Quinto</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <label>Ciclo académico:</label>
                            <input type="text" name="ciclo" value="<?php echo getFormValue('ciclo', '2025 IS'); ?>" readonly>
                        </div>
                    </div>
                </div>

                <!-- Información de la Asignatura -->
                <div class="section-group">
                    <h3>📖 Información de la Asignatura</h3>
                    
                    <div class="form-row">
                        <div class="form-col full-width">
                            <label>Nombre de la asignatura:</label>
                            <input type="text" name="asignatura" required placeholder="Ingrese el nombre de la asignatura" value="<?php echo getFormValue('asignatura'); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <label>Campo de Formación:</label>
                            <input type="text" name="campo_formacion" required placeholder="Ej: Básico, Profesional, Especialización" value="<?php echo getFormValue('campo_formacion'); ?>">
                        </div>
                        
                        <div class="form-col">
                            <label>Unidad de Organización Curricular:</label>
                            <input type="text" name="unidad_curricular" required placeholder="Ej: Básica, Profesional, Titulación" value="<?php echo getFormValue('unidad_curricular'); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <label>Código de la asignatura:</label>
                            <input type="text" name="codigo_asignatura" required maxlength="15" placeholder="Ej: DS-101" value="<?php echo getFormValue('codigo_asignatura'); ?>">
                        </div>
                        
                        <div class="form-col">
                            <label>N° Total de horas de la asignatura:</label>
                            <input type="number" name="horas_total" required min="1" max="999" placeholder="Total de horas" value="<?php echo getFormValue('horas_total'); ?>">
                        </div>
                    </div>
                </div>

                <!-- Distribución de Horas -->
                <div class="section-group">
                    <h3>⏰ Distribución de Horas</h3>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label>N° de horas Docencia:</label>
                            <input type="number" name="h_docencia" required min="0" max="99" placeholder="Horas" value="<?php echo getFormValue('h_docencia'); ?>">
                        </div>
                        
                        <div class="form-col">
                            <label>Horas en contacto con docente:</label>
                            <input type="number" name="h_contacto" required min="0" max="99" placeholder="Horas" value="<?php echo getFormValue('h_contacto'); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <label>Horas Autónomo (experimentales):</label>
                            <input type="number" name="h_autonomo" required min="0" max="99" placeholder="Horas" value="<?php echo getFormValue('h_autonomo'); ?>">
                        </div>
                        
                        <div class="form-col">
                            <label>N° de horas Trabajo Autónomo:<span class="required"></span></label>
                            <input type="number" name="h_actividad_autonoma" required min="0" max="99" placeholder="Horas" value="<?php echo getFormValue('h_actividad_autonoma'); ?>">
                        </div>
                    </div>
                </div>

                <!-- Datos del Docente -->
                <div class="section-group">
                    <h3>👨‍🏫 Datos del Docente</h3>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label>Nombre del Docente:</label>
                            <input type="text" name="docente" required placeholder="Nombre completo" value="<?php echo getFormValue('docente'); ?>">
                        </div>
                        
                        <div class="form-col">
                            <label>Título del Docente:</label>
                            <input type="text" name="titulo_docente" required placeholder="Título académico" value="<?php echo getFormValue('titulo_docente'); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <label>Correo institucional:</label>
                            <input type="email" name="correo" required placeholder="correo@institucion.edu.ec" value="<?php echo getFormValue('correo'); ?>">
                        </div>
                        
                        <div class="form-col">
                            <label>Número de Teléfono:</label>
                            <input type="tel" name="telefono" required pattern="[0-9]{10}" maxlength="10" placeholder="0999999999" value="<?php echo getFormValue('telefono'); ?>">
                            <div class="help-text">Solo números, exactamente 10 dígitos</div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <label>Tutoría Grupal:</label>
                            <input type="text" name="tutoria_grupal" placeholder="Descripción" value="<?php echo getFormValue('tutoria_grupal'); ?>">
                        </div>
                        
                        <div class="form-col">
                            <label>Tutoría Individual:</label>
                            <input type="text" name="tutoria_individual" placeholder="Descripción" value="<?php echo getFormValue('tutoria_individual'); ?>">
                        </div>
                    </div>
                </div>

                <div class="button-container">
                    <button type="button" class="btn-next" onclick="siguientePaso()">Siguiente ➡️</button>
                </div>
            </div>

            <div class="form-section" id="parte2">
                <h2>📋 Programa de Estudio de la Asignatura - Parte 2</h2>

                <!-- Descripción de la Asignatura -->
                <div class="section-group">
                    <h3>📄 Descripción de la Asignatura</h3>
                    
                    <div class="form-row">
                        <div class="form-col full-width">
                            <label>Descripción de la asignatura:</label>
                            <textarea name="descripcion_asignatura" required placeholder="Describa el contenido, enfoque y propósito de la asignatura..."><?php echo getFormValue('descripcion_asignatura'); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col full-width">
                            <label>Objetivo general:</label>
                            <textarea name="objetivo_general" required placeholder="Describa el objetivo principal que los estudiantes deben alcanzar..."><?php echo getFormValue('objetivo_general'); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Temáticas -->
                <div class="section-group">
                    <h3>📚 Temáticas de la Asignatura</h3>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label>Temática 1:</label>
                            <input type="text" name="tematica_1" required placeholder="Primera unidad temática" value="<?php echo getFormValue('tematica_1'); ?>">
                        </div>
                        
                        <div class="form-col">
                            <label>Temática 2:</label>
                            <input type="text" name="tematica_2" required placeholder="Segunda unidad temática" value="<?php echo getFormValue('tematica_2'); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col full-width">
                            <label>Descripción Temática 1:</label>
                            <textarea name="descripcion_1" required placeholder="Describa los contenidos y alcance de la primera temática..."><?php echo getFormValue('descripcion_1'); ?></textarea>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col full-width">
                            <label>Descripción Temática 2:</label>
                            <textarea name="descripcion_2" required placeholder="Describa los contenidos y alcance de la segunda temática..."><?php echo getFormValue('descripcion_2'); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col full-width">
                            <label>Eje transversal:</label>
                            <input type="text" name="eje_transversal" required placeholder="Ej: Ética profesional, Responsabilidad social" value="<?php echo getFormValue('eje_transversal'); ?>">
                        </div>
                    </div>
                </div>

                <!-- Resultados de Aprendizaje -->
                <div class="section-group">
                    <h3>🎯 Resultados de Aprendizaje</h3>

                    <!-- RA 1 -->
                    <div class="ra-container">
                        <h4>📍 Resultado de Aprendizaje 1</h4>
                        <div class="form-row">
                            <div class="form-col full-width">
                                <label>RA 1:</label>
                                <textarea name="ra1" required placeholder="Describa el primer resultado de aprendizaje esperado..."><?php echo getFormValue('ra1'); ?></textarea>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-col full-width">
                                <label>PE 1 (Prueba de Evaluación):</label>
                                <textarea name="pe1" required placeholder="Describa cómo se evaluará este resultado de aprendizaje..."><?php echo getFormValue('pe1'); ?></textarea>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-col">
                                <label>Nivel de Contribución 1:</label>
                                <select name="contribucion_1" required>
                                    <option value="">Seleccione nivel</option>
                                    <option value="Alta" <?php echo getSelectSelected('contribucion_1', 'Alta'); ?>>Alta</option>
                                    <option value="Media" <?php echo getSelectSelected('contribucion_1', 'Media'); ?>>Media</option>
                                    <option value="Baja" <?php echo getSelectSelected('contribucion_1', 'Baja'); ?>>Baja</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- RA 2 -->
                    <div class="ra-container">
                        <h4>📍 Resultado de Aprendizaje 2</h4>
                        <div class="form-row">
                            <div class="form-col full-width">
                                <label>RA 2:</label>
                                <textarea name="ra2" required placeholder="Describa el segundo resultado de aprendizaje esperado..."><?php echo getFormValue('ra2'); ?></textarea>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-col full-width">
                                <label>PE 2 (Prueba de Evaluación):</label>
                                <textarea name="pe2" required placeholder="Describa cómo se evaluará este resultado de aprendizaje..."><?php echo getFormValue('pe2'); ?></textarea>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-col">
                                <label>Nivel de Contribución 2:</label>
                                <select name="contribucion_2" required>
                                    <option value="">Seleccione nivel</option>
                                    <option value="Alta" <?php echo getSelectSelected('contribucion_2', 'Alta'); ?>>Alta</option>
                                    <option value="Media" <?php echo getSelectSelected('contribucion_2', 'Media'); ?>>Media</option>
                                    <option value="Baja" <?php echo getSelectSelected('contribucion_2', 'Baja'); ?>>Baja</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- RA 3 -->
                    <div class="ra-container">
                        <h4>📍 Resultado de Aprendizaje 3</h4>
                        <div class="form-row">
                            <div class="form-col full-width">
                                <label>RA 3:</label>
                                <textarea name="ra3" required placeholder="Describa el tercer resultado de aprendizaje esperado..."><?php echo getFormValue('ra3'); ?></textarea>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-col full-width">
                                <label>PE 3 (Prueba de Evaluación):</label>
                                <textarea name="pe3" required placeholder="Describa cómo se evaluará este resultado de aprendizaje..."><?php echo getFormValue('pe3'); ?></textarea>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-col">
                                <label>Nivel de Contribución 3:</label>
                                <select name="contribucion_3" required>
                                    <option value="">Seleccione nivel</option>
                                    <option value="Alta" <?php echo getSelectSelected('contribucion_3', 'Alta'); ?>>Alta</option>
                                    <option value="Media" <?php echo getSelectSelected('contribucion_3', 'Media'); ?>>Media</option>
                                    <option value="Baja" <?php echo getSelectSelected('contribucion_3', 'Baja'); ?>>Baja</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="button-container">
                    <button type="button" class="btn-back" onclick="pasoAnterior()">⬅️ Anterior</button>
                    <button style="display: block; margin: 0 auto;" type="submit" class="btn-submit">💾 Finalizar y Guardar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function siguientePaso() {
        document.getElementById('parte1').classList.remove('active');
        document.getElementById('parte2').classList.add('active');
    }

    function pasoAnterior() {
        document.getElementById('parte2').classList.remove('active');
        document.getElementById('parte1').classList.add('active');
    }
</script>

<style>
    /* Contenedor principal para AJAX - CENTRADO */
    .form-container-ajax {
        max-width: 1000px;
        margin: 20px auto; /* CENTRAR EL FORMULARIO */
        background: white;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .form-header {
        background: linear-gradient(135deg, #2196f3, #21cbf3);
        color: white;
        padding: 30px;
        text-align: center;
    }

    .form-header h1 {
        margin: 0;
        font-size: 2rem;
    }

    .form-content {
        padding: 40px;
    }

    /* Estilos para mejorar la organización */
    .section-group {
        background: #f8f9fa;
        padding: 25px;
        margin: 20px 0;
        border-radius: 10px;
        border-left: 4px solid #2196f3;
    }
    
    .section-group h3 {
        color: #2196f3;
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 1.2rem;
    }
    
    .form-row {
        display: flex;
        gap: 15px;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }
    
    .form-col {
        flex: 1;
        min-width: 280px;
    }
    
    .form-col.full-width {
        flex: 100%;
    }
    
    .ra-container {
        background: white;
        padding: 20px;
        margin: 15px 0;
        border-radius: 8px;
        border: 1px solid #dee2e6;
    }
    
    .ra-container h4 {
        color: #495057;
        margin-top: 0;
        margin-bottom: 15px;
    }
    
    label {
        font-weight: 600;
        margin-bottom: 5px;
        display: block;
        color: #495057;
    }
    
    input, select, textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        font-size: 1rem;
        box-sizing: border-box;
    }
    
    input:focus, select:focus, textarea:focus {
        outline: none;
        border-color: #2196f3;
        box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.2);
    }
    
    textarea {
        min-height: 80px;
        resize: vertical;
    }
    
    .button-container {
        text-align: center;
        margin-top: 30px;
        display: flex;
        justify-content: space-between;
        gap: 15px;
    }
    
    .btn-next, .btn-back, .btn-submit {
        padding: 12px 25px;
        border: none;
        border-radius: 6px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-next {
        background: #2196f3;
        color: white;
    }
    
    .btn-next:hover {
        background: #1976d2;
        transform: translateY(-2px);
    }
    
    .btn-back {
        background: #6c757d;
        color: white;
    }
    
    .btn-back:hover {
        background: #5a6268;
    }
    
    .btn-submit {
        background: #28a745;
        color: white;
    }
    
    .btn-submit:hover {
        background: #218838;
        transform: translateY(-2px);
    }
    
    .form-section {
        display: none;
    }
    
    .form-section.active {
        display: block;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .form-container-ajax {
            margin: 10px;
        }
        
        .form-row {
            flex-direction: column;
        }
        
        .form-col {
            min-width: 100%;
        }
        
        .button-container {
            flex-direction: column;
        }
        
        .section-group {
            padding: 15px;
        }
    }
    
    /* Validación visual para campos inválidos */
    input:invalid {
        border-color: #dc3545;
    }
    
    input:valid {
        border-color: #28a745;
    }
    
    /* Mensajes de ayuda */
    .help-text {
        font-size: 0.8rem;
        color: #6c757d;
        margin-top: 3px;
    }
    
    /* Centrar títulos principales */
    h2 {
        text-align: center;
        color: #2196f3;
        margin-bottom: 30px;
        font-size: 1.5rem;
    }
</style>