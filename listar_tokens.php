<?php
$pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');
foreach ($pdo->query('SELECT id,request_id FROM sat_download_history ORDER BY id ASC LIMIT 10') as $row) {
    echo 'ID:' . $row['id'] . ' TOKEN:' . $row['request_id'] . PHP_EOL;
}
