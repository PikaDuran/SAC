<?php
session_start();

// Si ya hay sesión activa, ir al dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: public/dashboard/dashboard.php');
    exit;
}

// Si no hay sesión, ir al login
header('Location: public/login/login.html');
exit;
