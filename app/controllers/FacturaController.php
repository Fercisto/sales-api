<?php
require_once __DIR__ . '/../models/Factura.php';
require_once __DIR__ . '/../models/Pedido.php';
require_once __DIR__ . '/../config/Database.php';

class FacturaController {
    private $db;
    private $factura;
    private $pedido;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->factura = new Factura($this->db);
        $this->pedido = new Pedido($this->db);
    }

    public function index() {
        $stmt = $this->factura->getAll();
        $facturas = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $facturas[] = [
                'id' => (int)$row['id'],
                'facturama_id' => $row['facturama_id'],
                'pedido_id' => (int)$row['pedido_id'],
                'cliente_id' => (int)$row['cliente_id'],
                'cliente_nombre' => $row['cliente_nombre'],
                'folio' => $row['folio'],
                'serie' => $row['serie'],
                'uuid' => $row['uuid'],
                'subtotal' => (float)$row['subtotal'],
                'impuestos' => (float)$row['impuestos'],
                'total' => (float)$row['total'],
                'metodo_pago' => $row['metodo_pago'],
                'forma_pago' => $row['forma_pago'],
                'uso_cfdi' => $row['uso_cfdi'],
                'fecha_emision' => $row['fecha_emision'],
                'estado' => $row['estado'],
                'created_at' => $row['created_at']
            ];
        }

        http_response_code(200);
        echo json_encode($facturas);
    }

    public function show($id) {
        $stmt = $this->factura->getById($id);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $factura = [
                'id' => (int)$row['id'],
                'facturama_id' => $row['facturama_id'],
                'pedido_id' => (int)$row['pedido_id'],
                'cliente_id' => (int)$row['cliente_id'],
                'cliente_nombre' => $row['cliente_nombre'],
                'cliente_email' => $row['cliente_email'],
                'folio' => $row['folio'],
                'serie' => $row['serie'],
                'uuid' => $row['uuid'],
                'subtotal' => (float)$row['subtotal'],
                'impuestos' => (float)$row['impuestos'],
                'total' => (float)$row['total'],
                'metodo_pago' => $row['metodo_pago'],
                'forma_pago' => $row['forma_pago'],
                'uso_cfdi' => $row['uso_cfdi'],
                'fecha_emision' => $row['fecha_emision'],
                'estado' => $row['estado'],
                'notas' => $row['notas'],
                'xml_path' => $row['xml_path'],
                'pdf_path' => $row['pdf_path'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];

            http_response_code(200);
            echo json_encode($factura);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Factura no encontrada']);
        }
    }

    public function store() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['pedido_id']) || empty($data['cliente_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos incompletos']);
            return;
        }

        $stmtPedido = $this->pedido->getById($data['pedido_id']);
        $pedidoData = $stmtPedido->fetch(PDO::FETCH_ASSOC);

        if (!$pedidoData) {
            http_response_code(404);
            echo json_encode(['error' => 'Pedido no encontrado']);
            return;
        }

        $stmtFacturaExistente = $this->factura->getByPedido($data['pedido_id']);
        if ($stmtFacturaExistente->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(400);
            echo json_encode(['error' => 'Ya existe una factura para este pedido']);
            return;
        }

        try {
            $this->db->beginTransaction();

            $iva = 0.16;
            $subtotal = $pedidoData['total'] / (1 + $iva);
            $impuestos = $pedidoData['total'] - $subtotal;

            $this->factura->facturama_id = $data['facturama_id'] ?? null;
            $this->factura->pedido_id = $data['pedido_id'];
            $this->factura->cliente_id = $data['cliente_id'];
            $this->factura->folio = $data['folio'] ?? null;
            $this->factura->serie = $data['serie'] ?? null;
            $this->factura->uuid = $data['uuid'] ?? null;
            $this->factura->subtotal = $subtotal;
            $this->factura->impuestos = $impuestos;
            $this->factura->total = $pedidoData['total'];
            $this->factura->metodo_pago = $data['metodo_pago'] ?? null;
            $this->factura->forma_pago = $data['forma_pago'] ?? null;
            $this->factura->uso_cfdi = $data['uso_cfdi'] ?? null;
            $this->factura->fecha_emision = $data['fecha_emision'] ?? date('Y-m-d H:i:s');
            $this->factura->estado = 'generada';
            $this->factura->notas = $data['notas'] ?? null;
            $this->factura->xml_path = $data['xml_path'] ?? null;
            $this->factura->pdf_path = $data['pdf_path'] ?? null;

            $factura_id = $this->factura->create();

            if (!$factura_id) {
                throw new Exception("No se pudo crear la factura");
            }

            $this->db->commit();

            http_response_code(201);
            echo json_encode([
                'mensaje' => 'Factura creada exitosamente',
                'id' => $factura_id,
                'subtotal' => $subtotal,
                'impuestos' => $impuestos,
                'total' => $pedidoData['total']
            ]);

        } catch (Exception $e) {
            $this->db->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function update($id) {
        $data = json_decode(file_get_contents("php://input"), true);

        $stmt = $this->factura->getById($id);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(404);
            echo json_encode(['error' => 'Factura no encontrada']);
            return;
        }

        $this->factura->id = $id;
        $this->factura->estado = $data['estado'] ?? null;
        $this->factura->notas = $data['notas'] ?? null;
        $this->factura->xml_path = $data['xml_path'] ?? null;
        $this->factura->pdf_path = $data['pdf_path'] ?? null;

        if ($this->factura->update()) {
            http_response_code(200);
            echo json_encode(['mensaje' => 'Factura actualizada']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo actualizar']);
        }
    }

    public function cancelar($id) {
        $stmt = $this->factura->getById($id);
        $facturaData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$facturaData) {
            http_response_code(404);
            echo json_encode(['error' => 'Factura no encontrada']);
            return;
        }

        if ($facturaData['estado'] === 'cancelada') {
            http_response_code(400);
            echo json_encode(['error' => 'La factura ya está cancelada']);
            return;
        }

        $this->factura->id = $id;

        if ($this->factura->cancelar()) {
            http_response_code(200);
            echo json_encode(['mensaje' => 'Factura cancelada exitosamente']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo cancelar la factura']);
        }
    }

    public function destroy($id) {
        $this->factura->id = $id;

        if ($this->factura->delete()) {
            http_response_code(200);
            echo json_encode(['mensaje' => 'Factura eliminada']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo eliminar']);
        }
    }

    public function getByPedido($pedido_id) {
        $stmt = $this->factura->getByPedido($pedido_id);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $factura = [
                'id' => (int)$row['id'],
                'facturama_id' => $row['facturama_id'],
                'pedido_id' => (int)$row['pedido_id'],
                'cliente_id' => (int)$row['cliente_id'],
                'cliente_nombre' => $row['cliente_nombre'],
                'folio' => $row['folio'],
                'serie' => $row['serie'],
                'uuid' => $row['uuid'],
                'subtotal' => (float)$row['subtotal'],
                'impuestos' => (float)$row['impuestos'],
                'total' => (float)$row['total'],
                'estado' => $row['estado'],
                'fecha_emision' => $row['fecha_emision']
            ];

            http_response_code(200);
            echo json_encode($factura);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'No se encontró factura para este pedido']);
        }
    }

    public function getByCliente($cliente_id) {
        $stmt = $this->factura->getByCliente($cliente_id);
        $facturas = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $facturas[] = [
                'id' => (int)$row['id'],
                'pedido_id' => (int)$row['pedido_id'],
                'folio' => $row['folio'],
                'serie' => $row['serie'],
                'uuid' => $row['uuid'],
                'total' => (float)$row['total'],
                'estado' => $row['estado'],
                'fecha_emision' => $row['fecha_emision']
            ];
        }

        http_response_code(200);
        echo json_encode($facturas);
    }
}
