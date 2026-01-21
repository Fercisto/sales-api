<?php
class Inventario {
    private $conn;
    private $table_name = "inventario";

    public $id;
    public $producto_id;
    public $cantidad_disponible;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $query = "SELECT i.*, p.nombre, p.precio, p.vendedor_id
                  FROM " . $this->table_name . " i
                  INNER JOIN productos p ON i.producto_id = p.id
                  ORDER BY i.id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    public function getByProducto($producto_id) {
        $query = "SELECT i.*, p.nombre, p.precio
                  FROM " . $this->table_name . " i
                  INNER JOIN productos p ON i.producto_id = p.id
                  WHERE i.producto_id = :producto_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':producto_id', $producto_id);
        $stmt->execute();

        return $stmt;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  (producto_id, cantidad_disponible)
                  VALUES (:producto_id, :cantidad_disponible)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':producto_id', $this->producto_id);
        $stmt->bindParam(':cantidad_disponible', $this->cantidad_disponible);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET cantidad_disponible = :cantidad_disponible
                  WHERE producto_id = :producto_id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':cantidad_disponible', $this->cantidad_disponible);
        $stmt->bindParam(':producto_id', $this->producto_id);

        return $stmt->execute();
    }

    public function incrementar($producto_id, $cantidad) {
        $query = "UPDATE " . $this->table_name . "
                  SET cantidad_disponible = cantidad_disponible + :cantidad
                  WHERE producto_id = :producto_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':cantidad', $cantidad);
        $stmt->bindParam(':producto_id', $producto_id);

        return $stmt->execute();
    }

    public function decrementar($producto_id, $cantidad) {
        $query = "UPDATE " . $this->table_name . "
                  SET cantidad_disponible = cantidad_disponible - :cantidad
                  WHERE producto_id = :producto_id
                  AND cantidad_disponible >= :cantidad";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':cantidad', $cantidad);
        $stmt->bindParam(':producto_id', $producto_id);

        return $stmt->execute();
    }

    public function verificarDisponibilidad($producto_id, $cantidad) {
        $query = "SELECT cantidad_disponible FROM " . $this->table_name . "
                  WHERE producto_id = :producto_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':producto_id', $producto_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return $row['cantidad_disponible'] >= $cantidad;
        }

        return false;
    }

    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE producto_id = :producto_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':producto_id', $this->producto_id);

        return $stmt->execute();
    }
}
