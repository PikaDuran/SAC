<?php
session_start();
require_once '../../src/config/database.php';


session_destroy();
header('Location: ../login/login.html');
exit;
