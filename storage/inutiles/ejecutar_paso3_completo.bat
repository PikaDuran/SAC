# ========================================================================
# SCRIPT DE EJECUCIÓN AUTOMÁTICA PARA PASO 3 - IMPORTACIÓN COMPLETA
# ========================================================================
# Archivo: ejecutar_paso3_completo.bat
# Propósito: Ejecutar automáticamente todo el Paso 3
# Sistema: Windows con XAMPP
# ========================================================================

@echo off
color 0B
echo ========================================================================
echo               EJECUCIÓN COMPLETA DEL PASO 3 - IMPORTACIÓN
echo ========================================================================
echo.

echo [INFO] Verificando requisitos del sistema...
echo.

REM Verificar que la base de datos esté configurada
mysql -u root -e "USE sac_db; SELECT COUNT(*) as tablas FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'sac_db';" > temp_check.txt 2>&1

if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] La base de datos sac_db no está configurada
    echo [ERROR] Ejecute primero: configurar_bd_automatico.bat
    echo.
    pause
    exit /b 1
)

REM Verificar directorio de XMLs
if not exist "storage\sat_downloads" (
    echo [WARNING] Directorio storage\sat_downloads no existe
    echo [INFO] Creando estructura de directorios...
    mkdir storage\sat_downloads
    echo [INFO] Coloque los archivos XML en storage\sat_downloads\
    echo.
)

REM Contar archivos XML disponibles
for /f %%i in ('dir /s /b storage\sat_downloads\*.xml 2^>nul ^| find /c /v ""') do set xml_count=%%i

echo [INFO] Archivos XML encontrados: %xml_count%
echo.

if %xml_count% EQU 0 (
    echo [WARNING] No se encontraron archivos XML para procesar
    echo [INFO] Coloque archivos XML en storage\sat_downloads\ y ejecute nuevamente
    echo.
    pause
    exit /b 0
)

echo ========================================================================
echo                            PASO 3A: IMPORTACIÓN
echo ========================================================================
echo [INFO] Iniciando importación completa de %xml_count% archivos XML...
echo [INFO] Base de datos: sac_db (16 tablas + complementos)
echo [INFO] Análisis base: 387 campos únicos identificados
echo.

REM Ejecutar importador completo
php paso3_importador_completo.php

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ========================================================================
    echo                      PASO 3A COMPLETADO EXITOSAMENTE
    echo ========================================================================
    echo [SUCCESS] Importación de CFDIs completada
    echo [INFO] Todos los XMLs han sido procesados e insertados en sac_db
    echo [INFO] Sistema listo para consultas y reportes
    echo.
    
    REM Mostrar estadísticas rápidas
    echo [INFO] Generando estadísticas de importación...
    mysql -u root -e "
        USE sac_db;
        SELECT 'CFDIs Importados' as descripcion, COUNT(*) as cantidad FROM cfdi
        UNION ALL
        SELECT 'Timbres Fiscales', COUNT(*) FROM cfdi_timbre_fiscal_digital
        UNION ALL
        SELECT 'Complementos de Pago', COUNT(*) FROM cfdi_complemento_pagos_v10
        UNION ALL
        SELECT 'Conceptos/Líneas', COUNT(*) FROM conceptos
        UNION ALL
        SELECT 'Impuestos Trasladados', COUNT(*) FROM impuestos_trasladados;
    "
    echo.
    
    echo ========================================================================
    echo                         PASO 3B: SISTEMA DE CONSULTAS
    echo ========================================================================
    echo [INFO] ¿Desea abrir el sistema de consultas interactivo? (S/N)
    set /p abrir_consultas=
    
    if /i "%abrir_consultas%"=="S" (
        echo [INFO] Abriendo sistema de consultas...
        php paso3_sistema_consultas.php
    )
    
    echo.
    echo ========================================================================
    echo                          PASO 3 COMPLETADO
    echo ========================================================================
    echo [SUCCESS] Importación y sistema de consultas listos
    echo [INFO] Archivos disponibles:
    echo [INFO]   • paso3_importador_completo.php - Importador principal
    echo [INFO]   • paso3_sistema_consultas.php - Sistema de consultas
    echo [INFO]   • reportes\ - Reportes generados
    echo [INFO]   • exportaciones\ - Exportaciones CSV
    echo.
    echo [NEXT] Sistema listo para el Paso 4
    echo ========================================================================
    
) else (
    echo.
    echo ========================================================================
    echo                            ERROR EN PASO 3A
    echo ========================================================================
    echo [ERROR] Hubo un problema durante la importación
    echo [ERROR] Revisar los mensajes anteriores para más detalles
    echo.
    echo [DEBUG] Posibles causas:
    echo [DEBUG]   • Archivos XML corruptos o mal formateados
    echo [DEBUG]   • Problemas de memoria (aumentar memory_limit)
    echo [DEBUG]   • Problemas de conexión a base de datos
    echo [DEBUG]   • Permisos de escritura en directorio de reportes
    echo.
    echo [SOLUTION] Revisar logs y corregir problemas antes de continuar
    echo ========================================================================
)

echo.
echo [INFO] Presiona cualquier tecla para continuar...
pause >nul

REM Limpiar archivos temporales
if exist temp_check.txt del temp_check.txt

echo.
echo ========================================================================
echo                         FINALIZADO PASO 3
echo ========================================================================
echo [INFO] Todas las operaciones del Paso 3 han terminado
echo [INFO] Sistema de importación y consultas operativo
echo [INFO] Base de datos sac_db completamente poblada
echo ========================================================================
echo.
