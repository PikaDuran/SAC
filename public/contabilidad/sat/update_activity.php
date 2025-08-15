<?php
session_start();

if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
    echo 'OK';
} else {
    http_response_code(401);
    echo 'Session expired';
}
