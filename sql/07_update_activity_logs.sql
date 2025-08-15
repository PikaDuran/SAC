-- Script para actualizar la tabla activity_logs con nuevos campos para auditoría completa
-- Ejecutar este script si ya tienes la tabla activity_logs creada
USE sac_db;

-- Agregar campos module y record_id si no existen
ALTER TABLE activity_logs
ADD COLUMN IF NOT EXISTS module VARCHAR(50) DEFAULT NULL AFTER description,
ADD COLUMN IF NOT EXISTS record_id INT DEFAULT NULL AFTER module;

-- Crear índices para optimizar consultas
CREATE INDEX IF NOT EXISTS idx_module ON activity_logs (module);

CREATE INDEX IF NOT EXISTS idx_module_record ON activity_logs (module, record_id);

-- Mostrar la estructura actualizada
DESCRIBE activity_logs;