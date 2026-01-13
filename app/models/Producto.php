<?php
class Producto {
    private $conn;
    private $table_name = "productos";

    public $id;
    public $nombre;
    public $precio;
    public $descripcion;
    public $vendedor_id;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Listar todos
    public function getAll() {
        $query = "SELECT p.*, i.cantidad_disponible as stock 
                  FROM " . $this->table_name . " p
                  LEFT JOIN inventario i ON p.id = i.producto_id
                  ORDER BY p.id DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt;
    }

    // Obtener uno
    public function getById($id) {
        $query = "SELECT p.*, i.cantidad_disponible as stock 
                  FROM " . $this->table_name . " p
                  LEFT JOIN inventario i ON p.id = i.producto_id
                  WHERE p.id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt;
    }

    // Crear
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  (nombre, precio, descripcion, vendedor_id)
                  VALUES (:nombre, :precio, :descripcion, :vendedor_id)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':nombre', $this->nombre);
        $stmt->bindParam(':precio', $this->precio);
        $stmt->bindParam(':descripcion', $this->descripcion);
        $stmt->bindParam(':vendedor_id', $this->vendedor_id);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        return false;
    }

    // Actualizar
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET nombre = :nombre,
                      precio = :precio,
                      descripcion = :descripcion
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':nombre', $this->nombre);
        $stmt->bindParam(':precio', $this->precio);
        $stmt->bindParam(':descripcion', $this->descripcion);
        $stmt->bindParam(':id', $this->id);
        
        return $stmt->execute();
    }

    // Eliminar
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        
        return $stmt->execute();
    }

    // Crear inventario inicial
    public function createInventario($producto_id, $cantidad = 0) {
        $query = "INSERT INTO inventario (producto_id, cantidad_disponible)
                  VALUES (:producto_id, :cantidad)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':producto_id', $producto_id);
        $stmt->bindParam(':cantidad', $cantidad);
        
        return $stmt->execute();
    }
}