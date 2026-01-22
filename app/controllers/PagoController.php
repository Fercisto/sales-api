<?php
require_once __DIR__ . '/../models/Pago.php';
require_once __DIR__ . '/../models/Pedido.php';
require_once __DIR__ . '/../config/Database.php';

class PagoController {
    private $db;
    private $pago;
    private $pedido;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->pago = new Pago($this->db);
        $this->pedido = new Pedido($this->db);
    }

    public function index() {
        $stmt = $this->pago->getAll();
        $pagos = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pagos[] = [
                'id' => (int)$row['id'],
                'pedido_id' => (int)$row['pedido_id'],
                'metodo_pago' => $row['metodo_pago'],
                'monto' => (float)$row['monto'],
                'estatus' => $row['estatus'],
                'fecha' => $row['fecha'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }

        http_response_code(200);
        echo json_encode($pagos);
    }

    public function show($id) {
        $stmt = $this->pago->getById($id);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $pago = [
                'id' => (int)$row['id'],
                'pedido_id' => (int)$row['pedido_id'],
                'metodo_pago' => $row['metodo_pago'],
                'monto' => (float)$row['monto'],
                'estatus' => $row['estatus'],
                'fecha' => $row['fecha'],
                'total_pedido' => (float)$row['total_pedido'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];

            http_response_code(200);
            echo json_encode($pago);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Pago no encontrado']);
        }
    }

    public function store() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['pedido_id']) || empty($data['metodo_pago']) || empty($data['monto'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos incompletos']);
            return;
        }

        $stmtPedido = $this->pedido->getById($data['pedido_id']);
        if (!$stmtPedido->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(404);
            echo json_encode(['error' => 'Pedido no encontrado']);
            return;
        }

        try {
            $this->db->beginTransaction();

            $this->pago->pedido_id = $data['pedido_id'];
            $this->pago->metodo_pago = $data['metodo_pago'];
            $this->pago->monto = $data['monto'];
            $this->pago->estatus = $data['estatus'] ?? 'pendiente';

            $pago_id = $this->pago->create();

            if (!$pago_id) {
                throw new Exception("No se pudo crear el pago");
            }

            if ($this->pago->estatus === 'aprobado') {
                $total_pagado = $this->pago->getTotalPagadoPorPedido($data['pedido_id']);
                $stmtPedido = $this->pedido->getById($data['pedido_id']);
                $pedidoData = $stmtPedido->fetch(PDO::FETCH_ASSOC);

                if ($total_pagado >= $pedidoData['total']) {
                    $this->pedido->id = $data['pedido_id'];
                    $this->pedido->estatus = 'pagado';
                    $this->pedido->total = $pedidoData['total'];
                    $this->pedido->update();
                }
            }

            $this->db->commit();

            http_response_code(201);
            echo json_encode([
                'mensaje' => 'Pago registrado exitosamente',
                'id' => $pago_id
            ]);

        } catch (Exception $e) {
            $this->db->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function update($id) {
        $data = json_decode(file_get_contents("php://input"), true);

        $stmt = $this->pago->getById($id);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(404);
            echo json_encode(['error' => 'Pago no encontrado']);
            return;
        }

        $this->pago->id = $id;
        $this->pago->metodo_pago = $data['metodo_pago'] ?? null;
        $this->pago->monto = $data['monto'] ?? null;
        $this->pago->estatus = $data['estatus'] ?? null;

        if ($this->pago->update()) {
            http_response_code(200);
            echo json_encode(['mensaje' => 'Pago actualizado']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo actualizar']);
        }
    }

    public function updateEstatus($id) {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['estatus'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Estatus requerido']);
            return;
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->pago->getById($id);
            $pagoData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pagoData) {
                throw new Exception("Pago no encontrado");
            }

            $this->pago->id = $id;
            $this->pago->estatus = $data['estatus'];

            if (!$this->pago->updateEstatus()) {
                throw new Exception("No se pudo actualizar el estatus");
            }

            if ($data['estatus'] === 'aprobado') {
                $total_pagado = $this->pago->getTotalPagadoPorPedido($pagoData['pedido_id']);
                $stmtPedido = $this->pedido->getById($pagoData['pedido_id']);
                $pedidoData = $stmtPedido->fetch(PDO::FETCH_ASSOC);

                if ($total_pagado >= $pedidoData['total']) {
                    $this->pedido->id = $pagoData['pedido_id'];
                    $this->pedido->estatus = 'pagado';
                    $this->pedido->total = $pedidoData['total'];
                    $this->pedido->update();
                }
            }

            $this->db->commit();

            http_response_code(200);
            echo json_encode(['mensaje' => 'Estatus actualizado']);

        } catch (Exception $e) {
            $this->db->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function destroy($id) {
        $this->pago->id = $id;

        if ($this->pago->delete()) {
            http_response_code(200);
            echo json_encode(['mensaje' => 'Pago eliminado']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo eliminar']);
        }
    }

    public function getByPedido($pedido_id) {
        $stmt = $this->pago->getByPedido($pedido_id);
        $pagos = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pagos[] = [
                'id' => (int)$row['id'],
                'pedido_id' => (int)$row['pedido_id'],
                'metodo_pago' => $row['metodo_pago'],
                'monto' => (float)$row['monto'],
                'estatus' => $row['estatus'],
                'fecha' => $row['fecha']
            ];
        }

        $total_pagado = $this->pago->getTotalPagadoPorPedido($pedido_id);

        http_response_code(200);
        echo json_encode([
            'pagos' => $pagos,
            'total_pagado' => (float)$total_pagado
        ]);
    }

    public function getByComprador($comprador_id) {
        $stmt = $this->pago->getByComprador($comprador_id);
        $pagos = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pagos[] = [
                'id' => (int)$row['id'],
                'pedido_id' => (int)$row['pedido_id'],
                'metodo_pago' => $row['metodo_pago'],
                'monto' => (float)$row['monto'],
                'estatus' => $row['estatus'],
                'fecha' => $row['fecha'],
                'total_pedido' => (float)$row['total_pedido'],
                'estatus_pedido' => $row['estatus_pedido']
            ];
        }

        http_response_code(200);
        echo json_encode($pagos);
    }
}
