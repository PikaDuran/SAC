<?php
class DB
{
    private static $instance = null;
    private $conn;
    private $host;
    private $user;
    private $pass;
    private $dbname;

    private function __construct()
    {
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->user = getenv('DB_USER') ?: 'root';
        $this->pass = getenv('DB_PASS') ?: '';
        $this->dbname = getenv('DB_NAME') ?: 'sac_db';

        try {
            $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->dbname);
            if ($this->conn->connect_error) {
                throw new Exception('Error de conexión: ' . $this->conn->connect_error);
            }
            $this->conn->set_charset('utf8mb4');
        } catch (Exception $e) {
            // Si la base de datos no existe, intentar crearla
            $tempConn = new mysqli($this->host, $this->user, $this->pass);
            if (!$tempConn->connect_error) {
                $tempConn->query("CREATE DATABASE IF NOT EXISTS {$this->dbname} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $tempConn->close();
                $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->dbname);
                if ($this->conn->connect_error) {
                    die('Error de conexión: ' . $this->conn->connect_error);
                }
                $this->conn->set_charset('utf8mb4');
            } else {
                die('Error de conexión: ' . $e->getMessage());
            }
        }
    }
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new DB();
        }
        return self::$instance;
    }
    public function getConnection()
    {
        return $this->conn;
    }
}
