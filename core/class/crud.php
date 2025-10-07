<?php
require_once 'db.php';
class crud {

    // Conexión
    private $conn;
    public $id_param;
    public $id_key;
    public $db_table;
    public $data;
    public $query;


    public function __construct($db) {
        $this->conn = $db;
    }

    // ======================
    // Crear
    // ======================
   public function create() {
        if (!empty($this->data)) {
            $data = $this->data;
            $sqlQuery = "INSERT INTO " . $this->db_table . " SET ";
            $maxFields = count((array) $data);
            $i = 0;
            foreach ($data as $key => $value) {
                $sqlQuery .= $key . " = :" . $key;
                $i++;
                if ($i < $maxFields) {
                    $sqlQuery .= ", ";
                }
            }
            $this->query = $sqlQuery;
            $stmt = $this->conn->prepare($sqlQuery);

            foreach ($data as $key => &$val) {

                $stmt->bindParam($key, $val);
            }
            try {
                $stmt->execute();
                return true;
            } catch (Exception $ex) {
                echo $ex;
                return false;
            }
        }
        return false;
    }

    // ======================
    // Leer
    // ======================
     public function read() {
        $sqlQuery = "SELECT * FROM " . $this->db_table . "";

        if (!empty($this->id_param && !empty($this->id_key))) {
            $sqlQuery .= " WHERE " . $this->id_key . " = :key ";
        }
        $stmt = $this->conn->prepare($sqlQuery);

        if (!empty($this->id_param && !empty($this->id_key))) {
            $stmt->bindParam(':key', $this->id_param);
        }
        try {
            $stmt->execute();
            $record = [];
            $reccount = $stmt->rowCount();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $record[] = $row;
            }

            $this->data = $record;
        } catch (Exception $ex) {
            $reccount = 0;
        }
        return $reccount;
    }

    // ======================
    // Actualizar
    // ======================
    public function update() {
        if (!empty($this->data)) {
            // Evitar actualizar la clave primaria
            if (isset($this->data[$this->id_key])) {
                unset($this->data[$this->id_key]);
            }

            $data = $this->data;
            $sqlQuery = "UPDATE {$this->db_table} SET ";
            $fields = [];
            foreach ($data as $key => $value) {
                $fields[] = "{$key} = :{$key}";
            }
            $sqlQuery .= implode(", ", $fields);
            $sqlQuery .= " WHERE {$this->id_key} = :idparam";

            $stmt = $this->conn->prepare($sqlQuery);
            $stmt->bindValue(":idparam", $this->id_param);
            foreach ($data as $key => $val) {
                $stmt->bindValue(':' . $key, $val);
            }

            try {
                return $stmt->execute();
            } catch (Exception $ex) {
                error_log($ex->getMessage());
                return false;
            }
        }
        return false;
    }

    // ======================
    // Eliminar
    // ======================
    public function delete() {
        $sqlQuery = "DELETE FROM {$this->db_table} WHERE {$this->id_key} = :idparam";
        $stmt = $this->conn->prepare($sqlQuery);
        $stmt->bindValue(':idparam', $this->id_param);
        try {
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (Exception $ex) {
            error_log($ex->getMessage());
            return false;
        }
    }

    // ======================
    // Consulta personalizada
    // ======================
    public function customQuery($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $idx => $val) {
            // Los parámetros son numerados desde 1 en PDO si usas ?
            $stmt->bindValue($idx + 1, $val);
        }
        try {
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $ex) {
            error_log($ex->getMessage());
            return [];
        }
    }
}
?>