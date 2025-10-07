<?php

include_once 'db.php';

/*
 * Class Users 
 * @Autor: Ing. Juan Becerril Avila
 */

class users {

// Connection
    private $conn;
    public $data;
    public $error;

// Db connection
    public function __construct($db) {
        $this->conn = $db;
        $this->error = '';
    }

    private function isUnique($email) {
        $sqlQuery = "select * from us_usuarios where email='$email'";
        $stmt = $this->conn->prepare($sqlQuery);
        try {
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $this->error = 'Correo electrónico ya se encuentra registrado';
                return false;
            } else {
                return true;
            }
        } catch (Exception $ex) {
            $this->error = 'Error al validr correo. ' . $ex;
            return false;
        }
    }

    public function loginuser() {
        $data = $this->data;
        $email = '';
        $password = '';
        $param = 0;
        foreach ($data as $key => $value) {
            if ($key == 'email') {
                $email = $value;
                $param++;
            }
            if ($key == 'password') {
                $password = $value;
                $param++;
            }
        }

        $sqlQuery = "select * from us_usuarios where email='$email' and password = '$password'";
        $stmt = $this->conn->prepare($sqlQuery);

        try {
            $stmt->execute();
            if ($stmt->rowCount() == 0) {
                $this->error = 'Usuario o Contraseña no válido';
                return false;
            } else {
                $this->data = $stmt->fetch(PDO::FETCH_ASSOC);
                return true;
            }
        } catch (Exception $ex) {
            $this->error = 'Error al inicar sesión. ' . $ex;
            return false;
        }
    }

    public function isValidEmail($email) {
        $matches = null;
        if (1 === preg_match('/^[A-z0-9\\._-]+@[A-z0-9][A-z0-9-]*(\\.[A-z0-9_-]+)*\\.([A-z]{2,6})$/', $email, $matches)) {
            return true;
        } else {
            $this->error = ' El formato del correo electrónico no es válido';
            return false;
        }
    }

    public function isValidPassword($password) {
        $matches = null;
        if (1 === preg_match('/^((?=.*[0-9])(?=.*[a-z])(?=.*[A-Z])(?=\S+$)(?=.*[;:\.,!¡\?¿@#\$%\^&\-_+=\(\)\[\]\{\}])).{8,20}$/', $password, $matches)) {
            return true;
        } else {
            $this->error = ' La contraseña es muy corta o no es segura.';

            return false;
        }
    }

    public function isValidToken($key, $email) {
        // Validar token y expiración usando us_usuarios
        $sqlQuery = "SELECT email, modificacion FROM us_usuarios WHERE token_recuperacion = :token";
        $querykey = $this->conn->prepare($sqlQuery);
        $querykey->bindParam(':token', $key);
        $querykey->execute();

        if ($querykey->rowCount() > 0) {
            $stmt = $querykey->fetch(PDO::FETCH_ASSOC);
            $fechaMod = strtotime($stmt['modificacion']);
            $ahora = time();
            $expira = 24 * 60 * 60; // 24 horas en segundos
            if (($ahora - $fechaMod) < $expira && strtoupper($stmt['email']) == strtoupper($email)) {
                return true;
            } else {
                $this->error = "El token no es válido o el tiempo de validación ha expirado.";
                // Limpiar token si expiró
                $query = $this->conn->prepare("UPDATE us_usuarios SET token_recuperacion = NULL WHERE token_recuperacion = :token");
                $query->bindParam(':token', $key);
                $query->execute();
                return false;
            }
        } else {
            $this->error = "El token no es válido o ya fue utilizado.";
            return false;
        }
    }

    public function resetpassword($email) {
        if ($this->isUnique($email)) {
            $this->error = ' El correo electrónico ' . $email . ' no se encuentra registrado';
            return false;
        } else {
            $this->error = '';
            $data = $this->data;
            // Guardar token y fecha en us_usuarios
            $query = $this->conn->prepare("UPDATE us_usuarios SET token_recuperacion = :token, modificacion = NOW() WHERE email = :email");
            $query->bindParam(':token', $data['token']);
            $query->bindParam(':email', $email);
            try {
                $query->execute();
                // Obtener el id_usuario
                $sqlId = "SELECT id_usuario FROM us_usuarios WHERE email = :email LIMIT 1";
                $queryId = $this->conn->prepare($sqlId);
                $queryId->bindParam(':email', $email);
                $queryId->execute();
                $id_usuario = null;
                if ($queryId->rowCount() > 0) {
                    $row = $queryId->fetch(PDO::FETCH_ASSOC);
                    $id_usuario = $row['id_usuario'];
                }
                // Insertar en us_bitacora
                $accion = 'Recupera contraseña';
                $notas = 'Solicitud de recuperación de contraseña para ' . $email;
                $sqlbit = "INSERT INTO us_bitacora (id_usuario, fecha, notas, accion) VALUES (:id_usuario, NOW(), :notas, :accion)";
                $querybit = $this->conn->prepare($sqlbit);
                $querybit->bindParam(':id_usuario', $id_usuario);
                $querybit->bindParam(':notas', $notas);
                $querybit->bindParam(':accion', $accion);
                $querybit->execute();
                return true;
            } catch (Exception $ex) {
                $this->error = 'Error al recuperar contraseña. ' . $ex;
                return false;
            }
        }
    }

    public function validate_key($key) {
        $sqlQuery = "UPDATE users SET status = 1 WHERE token = :token";
        $querykey = $this->conn->prepare($sqlQuery);
        $querykey->bindParam(':token', $key);
        $querykey->execute();

        if ($querykey->rowCount() > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function changepassword() {
        if (!empty($this->data)) {
            $data = $this->data;
            $email = '';
            $password = '';
            $param = 0;
            foreach ($data as $key => $value) {
                if ($key == 'email') {
                    $email = $value;
                    $param++;
                }
                if ($key == 'password') {
                    $password = $value;
                    $param++;
                }
            }

            if ($param == 2) {
                $sqlQuery = "UPDATE us_usuarios SET password = :password, token_recuperacion = NULL WHERE email = :email";
                $query = $this->conn->prepare($sqlQuery);
                $query->bindParam(':password', $password);
                $query->bindParam(':email', $email);

                try {
                    $query->execute();
                    if ($query->rowCount() > 0) {
                        $this->error = 'Se cambio la contraseña del usuario. ';
                        // Insertar registro en us_bitacora
                        // Buscar id_usuario
                        $sqlId = "SELECT id_usuario FROM us_usuarios WHERE email = :email LIMIT 1";
                        $queryId = $this->conn->prepare($sqlId);
                        $queryId->bindParam(':email', $email);
                        $queryId->execute();
                        $id_usuario = null;
                        if ($queryId->rowCount() > 0) {
                            $row = $queryId->fetch(PDO::FETCH_ASSOC);
                            $id_usuario = $row['id_usuario'];
                        }
                        $accion = 'Cambio de contraseña';
                        $notas = 'El usuario ' . $email . ' cambió su contraseña.';
                        $sqlbit = "INSERT INTO us_bitacora (id_usuario, fecha, notas, accion) VALUES (:id_usuario, NOW(), :notas, :accion)";
                        $querybit = $this->conn->prepare($sqlbit);
                        $querybit->bindParam(':id_usuario', $id_usuario);
                        $querybit->bindParam(':notas', $notas);
                        $querybit->bindParam(':accion', $accion);
                        $querybit->execute();
                        return true;
                    } else {
                        $this->error = 'No se realizo nungun cambio en la contraseña del usuario. ';
                        return false;
                    }
                } catch (Exception $ex) {
                    $this->error = 'Error al cambiar contraseña del usuario. ' . $ex;
                    return false;
                }
            } else {
                $this->error = 'Error al al cambiar contraseña, faltan parámeros requeridos. ';
                return false;
            }
        } else {
            $this->error = 'Error al registrar el usuario, faltan parámeros requeridos. ';
            return false;
        }
    }

    public function register() {
        if (!empty($this->data)) {
            $data = $this->data;
            $email = '';
            $sqlQuery = "INSERT INTO users SET ";
            $maxFields = count((array) $data);
            $i = 0;
            foreach ($data as $key => $value) {
                $sqlQuery .= $key . " = :" . $key;
                $i++;
                if ($key == 'email') {
                    $email = $value;
                }
                if ($i < $maxFields) {
                    $sqlQuery .= ", ";
                }
            }
            if ($this->isUnique($email) && !empty($email)) {
                $stmt = $this->conn->prepare($sqlQuery);
                foreach ($data as $key => &$val) {
                    $stmt->bindParam($key, $val);
                }
                try {
                    $stmt->execute();
                    $notitype = 'Crear una cuenta';
                    $reciver = 'Admin';
                    $sender = $email;

                    $sqlnoti = "insert into notification (notiuser,notireciver,notitype) values (:notiuser,:notireciver,:notitype)";
                    $querynoti = $this->conn->prepare($sqlnoti);
                    $querynoti->bindParam(':notiuser', $sender, PDO::PARAM_STR);
                    $querynoti->bindParam(':notireciver', $reciver, PDO::PARAM_STR);
                    $querynoti->bindParam(':notitype', $notitype, PDO::PARAM_STR);
                    $querynoti->execute();
                    return true;
                } catch (Exception $ex) {
                    //echo $ex;
                    $this->error = 'Error al registrar el usuario. ' . $ex;
                    return false;
                }
            } else {
                return false;
            }
        } else {
            $this->error = 'Error al registrar el usuario, faltan parámeros requeridos. ';
            return false;
        }
    }

}
