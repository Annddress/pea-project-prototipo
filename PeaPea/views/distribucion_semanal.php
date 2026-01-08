<?php session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distribución de Horas por Unidades</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            margin: 0;
        }

        /* Contenedor principal */
        .form-container {
            max-width: 876px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .form-header {
            background: linear-gradient(135deg, #2196f3, #21cbf3);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .btn-back-home {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-back-home:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .form-header h1 {
            margin: 0;
            font-size: 2rem;
        }

        .form-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }

        .form-content {
            padding: 40px;
        }

        /* Títulos */
        h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #2196f3;
            font-size: 1.8rem;
            font-weight: 600;
        }

        /* Contenedor de unidades */
        .unidad-container {
            margin-bottom: 25px;
            border: 2px solid #2196f3;
            border-radius: 12px;
            background: #f8f9fa;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);         
        }

        .unidad-container:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .unidad-header {
            font-weight: bold;
            cursor: pointer;
            background: linear-gradient(135deg, #2196f3, #21cbf3);
            color: white;
            padding: 18px 25px;
            margin: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .unidad-header:hover {
            background: linear-gradient(135deg, #1976d2, #1e88e5);
        }

        .unidad-body {
            display: none;
            padding: 25px;
        }

        .unidad-container.active .unidad-body {
            display: block;
        }

        .unidad-nombre {
            margin-bottom: 25px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .unidad-nombre label {
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
            color: #495057;
        }

        .unidad-nombre input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 15px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .unidad-nombre input:focus {
            outline: none;
            border-color: #2196f3;
            box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.2);
        }

        /* Contenedor de semanas */
        .semana-container {
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: white;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .semana-container:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .semana-header {
            font-weight: 600;
            cursor: pointer;
            background: #e9ecef;
            padding: 12px 18px;
            margin: 0;
            transition: all 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .semana-header:hover {
            background: #dee2e6;
        }

        .semana-body {
            display: none;
            padding: 20px;
        }

        .semana-container.active .semana-body {
            display: block;
        }

        /* Grid de horas */
        .horas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .hora-field {
            display: flex;
            flex-direction: column;
        }

        .hora-field label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #495057;
            font-size: 14px;
        }

        input[type="number"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        input[type="number"]:focus {
            outline: none;
            border-color: #2196f3;
            box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.2);
        }

        /* Iconos de toggle */
        .toggle-icon {
            transition: transform 0.3s ease;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
        }

        .unidad-container.active .unidad-header .toggle-icon {
            transform: rotate(180deg);
        }

        .semana-container .toggle-icon {
            color: #6c757d;
        }

        .semana-container.active .semana-header .toggle-icon {
            transform: rotate(180deg);
        }

        /* Botones */
        .btn-container {
            text-align: center;
            margin-top: 40px;
            padding: 30px 0;
        }

        .btn-guardar {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 15px 35px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-guardar:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-container {
                margin: 0;
                border-radius: 0;
            }
            
            .form-content {
                padding: 20px;
            }
            
            .horas-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .unidad-header {
                padding: 15px 20px;
            }
            
            .unidad-body {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .form-header {
                padding: 20px;
            }
            
            .form-header h1 {
                font-size: 1.6rem;
            }
            
            .btn-back-home {
                position: static;
                margin-bottom: 15px;
                display: inline-block;
            }
        }

        /* Animaciones suaves */
        .unidad-body {
            animation: slideDown 0.3s ease-out;
        }

        .semana-body {
            animation: slideDown 0.2s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Indicadores visuales */
        .unidad-container.active {
            border-color: #28a745;
        }

        .semana-container.active .semana-header {
            background: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <a href="../nuevo_pea.php" class="btn-back-home">⬅️ Volver</a>
            <h1>📊 Distribución de Horas por Unidades</h1>
            <p>Configura la distribución semanal de horas académicas</p>
        </div>
        
        <div class="form-content">
            <h2>📚 Distribución de Horas por Unidades Académicas</h2>

            <form action="../php/pea/guardar_semanas.php" method="POST">
                <input type="hidden" name="id_pea" value="<?php echo isset($_GET['id_pea']) ? $_GET['id_pea'] : ''; ?>">
                
                <div id="contenedor-unidades"></div>
                
                <div class="btn-container">
                    <button type="submit" class="btn-guardar">💾 Guardar Distribución por Unidades</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const contenedor = document.getElementById('contenedor-unidades');
        
        // Configuración de unidades
        const unidades = [
            { numero: 1, nombre: "Unidad 1", semanas: [1, 2, 3, 4, 5, 6, 7, 8] },
            { numero: 2, nombre: "Unidad 2", semanas: [9, 10, 11, 12, 13] },
            { numero: 3, nombre: "Unidad 3", semanas: [14, 15, 16] }
        ];

        // Crear las unidades
        unidades.forEach(unidad => {
            const unidadDiv = document.createElement('div');
            unidadDiv.classList.add('unidad-container');
            
            unidadDiv.innerHTML = `
                <div class="unidad-header" onclick="toggleUnidad(this)">
                    <span>${unidad.nombre} (Semanas ${unidad.semanas[0]} - ${unidad.semanas[unidad.semanas.length - 1]})</span>
                    <span class="toggle-icon">▼</span>
                </div>
                <div class="unidad-body">
                    <div class="unidad-nombre">
                        <label for="nombre_unidad_${unidad.numero}">Nombre personalizado de la unidad:</label>
                        <input type="text" name="nombre_unidad_${unidad.numero}" 
                               placeholder="Ingrese el nombre de la ${unidad.nombre}" 
                               value="${unidad.nombre}">
                    </div>
                    <div id="semanas-unidad-${unidad.numero}"></div>
                </div>
            `;
            
            contenedor.appendChild(unidadDiv);
            
            // Crear las semanas dentro de cada unidad
            const semanasContainer = document.getElementById(`semanas-unidad-${unidad.numero}`);
            
            unidad.semanas.forEach(numSemana => {
                const semanaDiv = document.createElement('div');
                semanaDiv.classList.add('semana-container');
                
                semanaDiv.innerHTML = `
                    <div class="semana-header" onclick="toggleSemana(this)">
                        <span>Semana ${numSemana}</span>
                        <span class="toggle-icon">▼</span>
                    </div>
                    <div class="semana-body">
                        <input type="hidden" name="unidad_${numSemana}" value="${unidad.numero}">
                        <div class="horas-grid">
                            <div class="hora-field">
                                <label>Horas Docencia:</label>
                                <input type="number" name="docencia_${numSemana}" required min="0" placeholder="0">
                            </div>
                            <div class="hora-field">
                                <label>Horas con Docente:</label>
                                <input type="number" name="con_docente_${numSemana}" required min="0" placeholder="0">
                            </div>
                            <div class="hora-field">
                                <label>Trabajo Autónomo:</label>
                                <input type="number" name="trabajo_autonomo_${numSemana}" required min="0" placeholder="0">
                            </div>
                            <div class="hora-field">
                                <label>Actividad Autónoma:</label>
                                <input type="number" name="actividad_autonoma_${numSemana}" required min="0" placeholder="0">
                            </div>
                        </div>
                    </div>
                `;
                
                semanasContainer.appendChild(semanaDiv);
            });
        });

        function toggleUnidad(header) {
            const container = header.parentElement;
            container.classList.toggle('active');
            
            const icon = header.querySelector('.toggle-icon');
            if (container.classList.contains('active')) {
                icon.textContent = '▲';
            } else {
                icon.textContent = '▼';
            }
        }

        function toggleSemana(header) {
            const container = header.parentElement;
            container.classList.toggle('active');
            
            const icon = header.querySelector('.toggle-icon');
            if (container.classList.contains('active')) {
                icon.textContent = '▲';
            } else {
                icon.textContent = '▼';
            }
        }

        // Expandir la primera unidad por defecto
        document.addEventListener('DOMContentLoaded', function() {
            const primeraUnidad = document.querySelector('.unidad-container');
            if (primeraUnidad) {
                primeraUnidad.classList.add('active');
                const icon = primeraUnidad.querySelector('.toggle-icon');
                icon.textContent = '▲';
            }
        });
    </script>
</body>
</html>