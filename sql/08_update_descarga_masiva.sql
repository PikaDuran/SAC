-- Script para actualizar tabla sat_download_history con campos específicos para Descarga Masiva SAT
-- Basado en documentación SW: https://developers.sw.com.mx/knowledge-base/descarga-masiva-sat-solicitud/
-- Fecha: 2025-08-06
USE sac_db;

-- Agregar campos faltantes según especificaciones del usuario y documentación SAT
ALTER TABLE sat_download_history
ADD COLUMN IF NOT EXISTS estatus_solicitud VARCHAR(50) DEFAULT NULL AFTER status,
ADD COLUMN IF NOT EXISTS ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER estatus_solicitud,
ADD COLUMN IF NOT EXISTS fecha_inicial DATE DEFAULT NULL AFTER ultima_actualizacion,
ADD COLUMN IF NOT EXISTS fecha_final DATE DEFAULT NULL AFTER fecha_inicial,
ADD COLUMN IF NOT EXISTS tipo_documento ENUM ('Emitidas', 'Recibidas') DEFAULT 'Emitidas' AFTER fecha_final,
ADD COLUMN IF NOT EXISTS mensaje_verificacion TEXT DEFAULT NULL AFTER tipo_documento,
ADD COLUMN IF NOT EXISTS paquetes JSON DEFAULT NULL AFTER mensaje_verificacion,
ADD COLUMN IF NOT EXISTS codigo_estado_verificacion VARCHAR(10) DEFAULT NULL AFTER paquetes,
ADD COLUMN IF NOT EXISTS codigo_estado_solicitud VARCHAR(10) DEFAULT NULL AFTER codigo_estado_verificacion,
ADD COLUMN IF NOT EXISTS fecha_solicitud TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER codigo_estado_solicitud;

-- Actualizar índices para optimizar consultas
CREATE INDEX IF NOT EXISTS idx_estatus_solicitud ON sat_download_history (estatus_solicitud);

CREATE INDEX IF NOT EXISTS idx_tipo_documento ON sat_download_history (tipo_documento);

CREATE INDEX IF NOT EXISTS idx_fecha_solicitud ON sat_download_history (fecha_solicitud);

CREATE INDEX IF NOT EXISTS idx_ultima_actualizacion ON sat_download_history (ultima_actualizacion);

-- Crear índice compuesto para consultas de filtrado
CREATE INDEX IF NOT EXISTS idx_rfc_fechas ON sat_download_history (rfc_emisor, fecha_inicial, fecha_final);

-- Registrar actualización en logs
INSERT INTO
    activity_logs (
        user_id,
        action,
        description,
        ip_address,
        created_at
    )
VALUES
    (
        1,
        'SYSTEM_UPDATE',
        'Actualización tabla sat_download_history con campos descarga masiva SAT',
        '127.0.0.1',
        NOW ()
    );

-- Mostrar estructura actualizada
DESCRIBE sat_download_history;