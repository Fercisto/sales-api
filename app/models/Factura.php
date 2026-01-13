<?php
class Factura {
    private $conn;
    private $table_name = "facturas";

    public $id;
    public $facturama_id;
    public $pedido_id;
    public $cliente_id;
    public $folio;
    public $serie;
    public $uuid;
    public $subtotal;
    public $impuestos;
    public $total;
    public $metodo_pago;
    public $forma_pago;
    public $uso_cfdi;
    public $fecha_emision;
    public $estado;
    public $notas;
    public $xml_path;
    public $pdf_path;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $query = "SELECT f.*, u.nombre as cliente_nombre, u.email as cliente_email
                  FROM " . $this->table_name . " f
                  LEFT JOIN usuarios u ON f.cliente_id = u.id
                  ORDER BY f.id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    public function getById($id) {
        $query = "SELECT f.*, u.nombre as cliente_nombre, u.email as cliente_email
                  FROM " . $this->table_name . " f
                  LEFT JOIN usuarios u ON f.cliente_id = u.id
                  WHERE f.id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt;
    }

    public function getByPedido($pedido_id) {
        $query = "SELECT f.*, u.nombre as cliente_nombre
                  FROM " . $this->table_name . " f
                  LEFT JOIN usuarios u ON f.cliente_id = u.id
                  WHERE f.pedido_id = :pedido_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':pedido_id', $pedido_id);
        $stmt->execute();

        return $stmt;
    }

    public function getByCliente($cliente_id) {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE cliente_id = :cliente_id
                  ORDER BY id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':cliente_id', $cliente_id);
        $stmt->execute();

        return $stmt;
    }

    public function getByUuid($uuid) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE uuid = :uuid";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':uuid', $uuid);
        $stmt->execute();

        return $stmt;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  (facturama_id, pedido_id, cliente_id, folio, serie, uuid,
                   subtotal, impuestos, total, metodo_pago, forma_pago, uso_cfdi,
                   fecha_emision, estado, notas, xml_path, pdf_path)
                  VALUES (:facturama_id, :pedido_id, :cliente_id, :folio, :serie, :uuid,
                          :subtotal, :impuestos, :total, :metodo_pago, :forma_pago, :uso_cfdi,
                          :fecha_emision, :estado, :notas, :xml_path, :pdf_path)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':facturama_id', $this->facturama_id);
        $stmt->bindParam(':pedido_id', $this->pedido_id);
        $stmt->bindParam(':cliente_id', $this->cliente_id);
        $stmt->bindParam(':folio', $this->folio);
        $stmt->bindParam(':serie', $this->serie);
        $stmt->bindParam(':uuid', $this->uuid);
        $stmt->bindParam(':subtotal', $this->subtotal);
        $stmt->bindParam(':impuestos', $this->impuestos);
        $stmt->bindParam(':total', $this->total);
        $stmt->bindParam(':metodo_pago', $this->metodo_pago);
        $stmt->bindParam(':forma_pago', $this->forma_pago);
        $stmt->bindParam(':uso_cfdi', $this->uso_cfdi);
        $stmt->bindParam(':fecha_emision', $this->fecha_emision);
        $stmt->bindParam(':estado', $this->estado);
        $stmt->bindParam(':notas', $this->notas);
        $stmt->bindParam(':xml_path', $this->xml_path);
        $stmt->bindParam(':pdf_path', $this->pdf_path);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET estado = :estado,
                      notas = :notas,
                      xml_path = :xml_path,
                      pdf_path = :pdf_path
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':estado', $this->estado);
        $stmt->bindParam(':notas', $this->notas);
        $stmt->bindParam(':xml_path', $this->xml_path);
        $stmt->bindParam(':pdf_path', $this->pdf_path);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    public function cancelar() {
        $query = "UPDATE " . $this->table_name . "
                  SET estado = 'cancelada'
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }
}
