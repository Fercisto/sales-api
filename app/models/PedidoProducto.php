<?php
class PedidoProducto {
    private $conn;
    private $table_name = "pedido_productos";

    public $id;
    public $pedido_id;
    public $producto_id;
    public $cantidad;
    public $precio_unitario;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getByPedido($pedido_id) {
        $query = "SELECT pp.*, p.nombre, p.descripcion
                  FROM " . $this->table_name . " pp
                  INNER JOIN productos p ON pp.producto_id = p.id
                  WHERE pp.pedido_id = :pedido_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':pedido_id', $pedido_id);
        $stmt->execute();

        return $stmt;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  (pedido_id, producto_id, cantidad, precio_unitario)
                  VALUES (:pedido_id, :producto_id, :cantidad, :precio_unitario)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':pedido_id', $this->pedido_id);
        $stmt->bindParam(':producto_id', $this->producto_id);
        $stmt->bindParam(':cantidad', $this->cantidad);
        $stmt->bindParam(':precio_unitario', $this->precio_unitario);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET cantidad = :cantidad,
                      precio_unitario = :precio_unitario
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':cantidad', $this->cantidad);
        $stmt->bindParam(':precio_unitario', $this->precio_unitario);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    public function deleteByPedido($pedido_id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE pedido_id = :pedido_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':pedido_id', $pedido_id);

        return $stmt->execute();
    }
}
