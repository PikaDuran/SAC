<?php
require_once __DIR__ . '/../helpers/db.php';
class User
{
    private $db;
    public function __construct()
    {
        $this->db = DB::getInstance()->getConnection();
    }
    public function getByUsername($usuario)
    {
        $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE usuario = ? LIMIT 1');
        $stmt->bind_param('s', $usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}
