-- Script para agregar campo is_read a activity_logs si no existe
ALTER TABLE activity_logs
ADD COLUMN is_read TINYINT (1) NOT NULL DEFAULT 0;