<?php
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'sac_db';

$sqlFile = __DIR__ . '/sql/12_catalogos_sat_inserts.sql';
if (!file_exists($sqlFile)) {
    die('No se encontró el archivo SQL: ' . $sqlFile);
}
$sql = file_get_contents($sqlFile);
if ($sql === false) {
    die('No se pudo leer el archivo SQL.');
}

$mysqli = new mysqli($host, $user, $password, $database);
if ($mysqli->connect_errno) {
    die('Error de conexión: ' . $mysqli->connect_error);
}

if ($mysqli->multi_query($sql)) {
    do {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
    } while ($mysqli->next_result());
    echo "Catálogos SAT insertados correctamente.";
} else {
    echo "Error al ejecutar los INSERTs: " . $mysqli->error;
}

$mysqli->close();
