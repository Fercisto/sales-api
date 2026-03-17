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
    public $conekta_order_id;
    public $conekta_customer_id;
    public $currency = 'MXN';
    public $payment_method_type;
    public $last_webhook_event;
    public $raw_response;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $query = "SELECT pg.*, p.comprador_id, p.total as total_pedido
                  FROM " . $this->table_name . " pg
                  INNER JOIN pedidos p ON pg.pedido_id = p.id
                  ORDER BY pg.id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function getById($id) {
        $query = "SELECT pg.*, p.comprador_id, p.total as total_pedido
                  FROM " . $this->table_name . " pg
                  INNER JOIN pedidos p ON pg.pedido_id = p.id
                  WHERE pg.id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt;
    }

    public function getByConektaOrderId($conekta_order_id) {
        $query = "SELECT pg.*, p.comprador_id, p.total as total_pedido
                  FROM " . $this->table_name . " pg
                  INNER JOIN pedidos p ON pg.pedido_id = p.id
                  WHERE pg.conekta_order_id = :conekta_order_id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':conekta_order_id', $conekta_order_id);
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
                  (pedido_id, metodo_pago, monto, estatus, conekta_order_id,
                   conekta_customer_id, currency, payment_method_type, raw_response)
                  VALUES (:pedido_id, :metodo_pago, :monto, :estatus, :conekta_order_id,
                          :conekta_customer_id, :currency, :payment_method_type, :raw_response)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':pedido_id', $this->pedido_id);
        $stmt->bindParam(':metodo_pago', $this->metodo_pago);
        $stmt->bindParam(':monto', $this->monto);
        $stmt->bindParam(':estatus', $this->estatus);
        $stmt->bindParam(':conekta_order_id', $this->conekta_order_id);
        $stmt->bindParam(':conekta_customer_id', $this->conekta_customer_id);
        $stmt->bindParam(':currency', $this->currency);
        $stmt->bindParam(':payment_method_type', $this->payment_method_type);
        $stmt->bindParam(':raw_response', $this->raw_response);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    public function updateEstatus() {
        $query = "UPDATE " . $this->table_name . "
                  SET estatus = :estatus,
                      last_webhook_event = :last_webhook_event
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':estatus', $this->estatus);
        $stmt->bindParam(':last_webhook_event', $this->last_webhook_event);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
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
