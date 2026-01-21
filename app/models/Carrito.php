<?php
class Carrito {
    private $conn;
    private $table_name = "carrito";

    public $id;
    public $usuario_id;
    public $producto_id;
    public $cantidad;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Obtener carrito completo con detalles de productos
    public function getByUsuario($usuario_id) {
        $query = "SELECT
                    c.id,
                    c.usuario_id,
                    c.producto_id,
                    c.cantidad,
                    p.nombre as producto_nombre,
                    p.precio as producto_precio,
                    p.descripcion as producto_descripcion,
                    (c.cantidad * p.precio) as subtotal,
                    i.cantidad_disponible as stock_disponible
                  FROM " . $this->table_name . " c
                  INNER JOIN productos p ON c.producto_id = p.id
                  LEFT JOIN inventario i ON p.id = i.producto_id
                  WHERE c.usuario_id = :usuario_id
                  ORDER BY c.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->execute();

        return $stmt;
    }

    // Verificar si un producto ya está en el carrito
    public function existeEnCarrito($usuario_id, $producto_id) {
        $query = "SELECT id, cantidad FROM " . $this->table_name . "
                  WHERE usuario_id = :usuario_id AND producto_id = :producto_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->bindParam(':producto_id', $producto_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Agregar producto al carrito
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  (usuario_id, producto_id, cantidad)
                  VALUES (:usuario_id, :producto_id, :cantidad)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':usuario_id', $this->usuario_id);
        $stmt->bindParam(':producto_id', $this->producto_id);
        $stmt->bindParam(':cantidad', $this->cantidad);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    // Actualizar cantidad
    public function updateCantidad($id, $cantidad) {
        $query = "UPDATE " . $this->table_name . "
                  SET cantidad = :cantidad
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':cantidad', $cantidad);
        $stmt->bindParam(':id', $id);

        return $stmt->execute();
    }

    // Incrementar cantidad de un producto existente
    public function incrementarCantidad($id, $cantidad) {
        $query = "UPDATE " . $this->table_name . "
                  SET cantidad = cantidad + :cantidad
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':cantidad', $cantidad);
        $stmt->bindParam(':id', $id);

        return $stmt->execute();
    }

    // Eliminar un item del carrito
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);

        return $stmt->execute();
    }

    // Vaciar carrito
    public function clearCarrito($usuario_id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE usuario_id = :usuario_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':usuario_id', $usuario_id);

        return $stmt->execute();
    }

    // Contar items en el carrito
    public function countItems($usuario_id) {
        $query = "SELECT SUM(cantidad) as total_items
                  FROM " . $this->table_name . "
                  WHERE usuario_id = :usuario_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['total_items'] ?? 0);
    }

    // Calcular total del carrito
    public function calculateTotal($usuario_id) {
        $query = "SELECT SUM(c.cantidad * p.precio) as total
                  FROM " . $this->table_name . " c
                  INNER JOIN productos p ON c.producto_id = p.id
                  WHERE c.usuario_id = :usuario_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float)($row['total'] ?? 0);
    }

    // Verificar disponibilidad de stock
    public function verificarStock($producto_id, $cantidad) {
        $query = "SELECT i.cantidad_disponible
                  FROM inventario i
                  WHERE i.producto_id = :producto_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':producto_id', $producto_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stock_disponible = (int)($row['cantidad_disponible'] ?? 0);

        return $stock_disponible >= $cantidad;
    }
}
