<?php
require_once dirname(__DIR__,2 ) . '/config.php';

class Database {

   /**
 * Parámetros del MySQL  
 */
    private $host = DB_HOST;
    private $database_name = DB_NAME;
    private $username= DB_USER;
    private $password = DB_PASSWORD;
    public $conn;
    public $tables;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->database_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $sqlQuery = "SHOW TABLES FROM  " . $this->database_name . "";
            $stmt = $this->conn->prepare($sqlQuery);
            $stmt->execute();
            $record = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $record[] = $row;
            }
            $this->tables = $record;
        } catch (PDOException $exception) {
            echo "Database could not be connected: " . $exception->getMessage();
        }
        return $this->conn;
    }
    
    public function chktable($tabla) {
        $result = false;
        if (!empty($this->tables)) {
            foreach ($this->tables as $llave => $valor) {
                foreach ($valor as $table => $field) {
                    if ($tabla == $field) {
                        $result = true;
                    }
                }
            }
        }
        return $result;
    }
}
?>