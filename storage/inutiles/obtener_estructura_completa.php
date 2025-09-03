<?php
// Script para obtener toda la estructura de la base de datos
try {
    $pdo = new PDO("mysql:host=localhost;dbname=sac_db;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Obtener todas las tablas
    $tablas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    echo "=== ESTRUCTURA COMPLETA DE LA BASE DE DATOS SAC ===\n\n";

    foreach ($tablas as $tabla) {
        echo "TABLA: $tabla\n";
        echo str_repeat("-", 50) . "\n";

        $columnas = $pdo->query("DESCRIBE $tabla")->fetchAll();

        foreach ($columnas as $columna) {
            echo sprintf(
                "%-30s %-20s %-10s %-10s %-15s\n",
                $columna['Field'],
                $columna['Type'],
                $columna['Null'],
                $columna['Key'],
                $columna['Default']
            );
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
