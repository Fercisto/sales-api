<?php
class Pedido {
    private $conn;
    private $table_name = "pedidos";

    public $id;
    public $comprador_id;
    public $fecha;
    public $total;
    public $estatus;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll($comprador_id = null) {
        $query = "SELECT p.*,
                         uc.nombre as comprador_nombre, uc.email as comprador_email
                  FROM " . $this->table_name . " p
                  LEFT JOIN usuarios uc ON p.comprador_id = uc.id";

        if ($comprador_id) {
            $query .= " WHERE p.comprador_id = :comprador_id";
        }

        $query .= " ORDER BY p.id DESC";

        $stmt = $this->conn->prepare($query);

        if ($comprador_id) {
            $stmt->bindParam(':comprador_id', $comprador_id);
        }

        $stmt->execute();

        return $stmt;
    }

    public function getById($id) {
        $query = "SELECT p.*,
                         uc.nombre as comprador_nombre, uc.email as comprador_email
                  FROM " . $this->table_name . " p
                  LEFT JOIN usuarios uc ON p.comprador_id = uc.id
                  WHERE p.id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt;
    }

    public function getByComprador($comprador_id) {
        $query = "SELECT p.*,
                         uc.nombre as comprador_nombre
                  FROM " . $this->table_name . " p
                  LEFT JOIN usuarios uc ON p.comprador_id = uc.id
                  WHERE p.comprador_id = :comprador_id
                  ORDER BY p.id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':comprador_id', $comprador_id);
        $stmt->execute();

        return $stmt;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  (comprador_id, total, estatus)
                  VALUES (:comprador_id, :total, :estatus)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':comprador_id', $this->comprador_id);
        $stmt->bindParam(':total', $this->total);
        $stmt->bindParam(':estatus', $this->estatus);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET estatus = :estatus,
                      total = :total
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':estatus', $this->estatus);
        $stmt->bindParam(':total', $this->total);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    public function updateEstatus() {
        $query = "UPDATE " . $this->table_name . "
                  SET estatus = :estatus
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':estatus', $this->estatus);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    public function getProductos($pedido_id) {
        $query = "SELECT pp.*, pr.nombre, pr.descripcion
                  FROM pedido_productos pp
                  INNER JOIN productos pr ON pp.producto_id = pr.id
                  WHERE pp.pedido_id = :pedido_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':pedido_id', $pedido_id);
        $stmt->execute();

        return $stmt;
    }

    public function cancelar($pedido_id) {
        $stmt = $this->conn->prepare("CALL sp_cancelar_pedido(:pedido_id)");
        $stmt->bindParam(':pedido_id', $pedido_id);
        return $stmt->execute();
    }
}
