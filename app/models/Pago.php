<?php
class Pago {
    private $conn;
    private $table_name = "pagos";

    public $id;
    public $pedido_id;
    public $metodo_pago;
    public $monto;
    public $estatus;
    public $fecha;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $query = "SELECT pg.*, p.comprador_id, p.vendedor_id, p.total as total_pedido
                  FROM " . $this->table_name . " pg
                  INNER JOIN pedidos p ON pg.pedido_id = p.id
                  ORDER BY pg.id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    public function getById($id) {
        $query = "SELECT pg.*, p.comprador_id, p.vendedor_id, p.total as total_pedido
                  FROM " . $this->table_name . " pg
                  INNER JOIN pedidos p ON pg.pedido_id = p.id
                  WHERE pg.id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt;
    }

    public function getByPedido($pedido_id) {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE pedido_id = :pedido_id
                  ORDER BY id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':pedido_id', $pedido_id);
        $stmt->execute();

        return $stmt;
    }

    public function getByComprador($comprador_id) {
        $query = "SELECT pg.*, p.total as total_pedido, p.estatus as estatus_pedido
                  FROM " . $this->table_name . " pg
                  INNER JOIN pedidos p ON pg.pedido_id = p.id
                  WHERE p.comprador_id = :comprador_id
                  ORDER BY pg.id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':comprador_id', $comprador_id);
        $stmt->execute();

        return $stmt;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  (pedido_id, metodo_pago, monto, estatus)
                  VALUES (:pedido_id, :metodo_pago, :monto, :estatus)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':pedido_id', $this->pedido_id);
        $stmt->bindParam(':metodo_pago', $this->metodo_pago);
        $stmt->bindParam(':monto', $this->monto);
        $stmt->bindParam(':estatus', $this->estatus);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET metodo_pago = :metodo_pago,
                      monto = :monto,
                      estatus = :estatus
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':metodo_pago', $this->metodo_pago);
        $stmt->bindParam(':monto', $this->monto);
        $stmt->bindParam(':estatus', $this->estatus);
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

    public function getTotalPagadoPorPedido($pedido_id) {
        $query = "SELECT COALESCE(SUM(monto), 0) as total_pagado
                  FROM " . $this->table_name . "
                  WHERE pedido_id = :pedido_id
                  AND estatus = 'aprobado'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':pedido_id', $pedido_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total_pagado'];
    }
}
