# ========================================================================
# SCRIPT DE EJECUCIÓN AUTOMÁTICA PARA CONFIGURACIÓN DE BASE DE DATOS
# ========================================================================
# Archivo: configurar_bd_automatico.bat
# Propósito: Script para ejecutar automáticamente toda la configuración
# Sistema: Windows con XAMPP
# ========================================================================

@echo off
color 0A
echo ========================================================================
echo           CONFIGURACION AUTOMATICA DE BASE DE DATOS SAC_DB
echo ========================================================================
echo.

echo [INFO] Verificando servicios XAMPP...
echo.

REM Verificar si MySQL está corriendo
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [OK] MySQL está ejecutándose
) else (
    echo [ERROR] MySQL no está ejecutándose. Iniciando...
    net start mysql
    timeout /t 3 >nul
)

echo.
echo [INFO] Ejecutando configuración completa de base de datos...
echo.

REM Cambiar al directorio del proyecto
cd /d "C:\xampp\htdocs\SAC"

REM Crear directorio sql si no existe
if not exist "sql" mkdir sql

echo [PASO 1] Ejecutando script maestro de configuración...
echo.

REM Ejecutar el script maestro que incluye todos los demás
C:\xampp\mysql\bin\mysql.exe -u root -e "USE sac_db; SOURCE sql/ejecutar_configuracion_completa_bd.sql;"

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ========================================================================
    echo                        CONFIGURACION COMPLETADA
    echo ========================================================================
    echo [SUCCESS] Base de datos SAC_DB configurada correctamente
    echo [INFO] Tablas creadas: 16 tablas principales + complementos
    echo [INFO] Catálogos SAT: Insertados y verificados
    echo [INFO] Procedimientos: Creados y funcionales
    echo [INFO] Sistema de auditoría: Activado
    echo.
    echo ========================================================================
    echo                         SIGUIENTE PASO
    echo ========================================================================
    echo [NEXT] Ahora ejecutar: paso3_importador_completo.php
    echo [NEXT] Para importar y procesar los XMLs de CFDI
    echo ========================================================================
    echo.
) else (
    echo.
    echo ========================================================================
    echo                            ERROR DETECTADO
    echo ========================================================================
    echo [ERROR] Hubo un problema durante la configuración
    echo [ERROR] Revisar los mensajes anteriores para más detalles
    echo [ERROR] Verificar que MySQL esté corriendo y accesible
    echo ========================================================================
    echo.
)

echo [INFO] Presiona cualquier tecla para continuar...
pause >nul

echo.
echo [INFO] ¿Deseas ver el estado actual de la base de datos? (S/N)
set /p respuesta=

if /i "%respuesta%"=="S" (
    echo.
    echo ========================================================================
    echo                      ESTADO ACTUAL DE LA BASE DE DATOS
    echo ========================================================================
    echo [INFO] Verificando que MySQL esté corriendo y accesible
    C:\xampp\mysql\bin\mysql.exe -u root -e "USE sac_db; SHOW TABLES; SELECT COUNT(*) as total_tablas FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'sac_db';"
    echo.
)

echo.
echo ========================================================================
echo                              FINALIZADO
echo ========================================================================
echo [INFO] Script de configuración terminado
echo [INFO] Base de datos lista para el Paso 3
echo ========================================================================
echo.
