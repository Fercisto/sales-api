<?php
require_once __DIR__ . '/../models/Pago.php';
require_once __DIR__ . '/../models/Pedido.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../services/ConektaService.php';

class PagoController {
    private $db;
    private $pago;
    private $pedido;
    private $conekta;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->pago = new Pago($this->db);
        $this->pedido = new Pedido($this->db);
        $this->conekta = new ConektaService();
    }

    public function index() {
        $stmt  = $this->pago->getAll();
        $pagos = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pagos[] = $this->formatRow($row);
        }

        http_response_code(200);
        echo json_encode($pagos);
    }

    public function show($id) {
        $stmt = $this->pago->getById($id);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Pago no encontrado']);
            return;
        }

        http_response_code(200);
        echo json_encode($this->formatRow($row));
    }

    /**
     * POST /pagos
     * Crea la orden en Conekta y guarda el pago como 'pendiente'.
     *
     * Body esperado:
     * {
     *   "pedido_id": 1,
     *   "token": "tok_test_xxx",
     *   "customer_name": "Juan Pérez",
     *   "customer_email": "juan@example.com",
     *   "customer_phone": "5512345678"
     * }
     */
    public function store() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['pedido_id']) || empty($data['token']) ||
            empty($data['customer_name']) || empty($data['customer_email'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Faltan campos: pedido_id, token, customer_name, customer_email']);
            return;
        }

        $stmtPedido  = $this->pedido->getById($data['pedido_id']);
        $pedidoData  = $stmtPedido->fetch(PDO::FETCH_ASSOC);

        if (!$pedidoData) {
            http_response_code(404);
            echo json_encode(['error' => 'Pedido no encontrado']);
            return;
        }

        // Conekta maneja montos en centavos (entero)
        $montoMXN = (float)$pedidoData['total'];
        $montoCentavos = (int)round($montoMXN * 100);

        try {
            $this->db->beginTransaction();

            // Construir payload para Conekta
            $conektaPayload = [
                'currency' => 'MXN',
                'customer_info' => [
                    'name' => $data['customer_name'],
                    'email' => $data['customer_email'],
                    'phone' => $data['customer_phone'] ?? '5500000000',
                ],
                'line_items' => [
                    [
                        'name' => 'Pedido #' . $data['pedido_id'],
                        'unit_price' => $montoCentavos,
                        'quantity' => 1,
                    ]
                ],
                'charges' => [
                    [
                        'payment_method' => [
                            'type' => 'card',
                            'token_id' => $data['token'],
                        ]
                    ]
                ],
                'metadata' => ['pedido_id' => (string)$data['pedido_id']],
            ];

            $conektaOrder = $this->conekta->createOrder($conektaPayload);

            // Guardar pago en BD como pendiente
            $this->pago->pedido_id = $data['pedido_id'];
            $this->pago->metodo_pago= 'tarjeta';
            $this->pago->monto = $montoMXN;
            $this->pago->estatus = 'pendiente';
            $this->pago->conekta_order_id = $conektaOrder['id'];
            $this->pago->currency = 'MXN';
            $this->pago->raw_response = json_encode($conektaOrder);

            $pago_id = $this->pago->create();

            if (!$pago_id) {
                throw new Exception('No se pudo guardar el pago en la base de datos');
            }

            $this->db->commit();

            http_response_code(201);
            echo json_encode([
                'mensaje' => 'Pago creado, pendiente de confirmación',
                'id' => (int)$pago_id,
                'conekta_order_id' => $conektaOrder['id'],
                'estatus' => 'pendiente',
            ]);

        } catch (Exception $e) {
            $this->db->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /pagos/{id}/cancelar
     * Cancela la orden en Conekta y actualiza el estatus local.
     */
    public function cancelar($id) {
        $stmt     = $this->pago->getById($id);
        $pagoData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pagoData) {
            http_response_code(404);
            echo json_encode(['error' => 'Pago no encontrado']);
            return;
        }

        if (empty($pagoData['conekta_order_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Este pago no tiene orden en Conekta']);
            return;
        }

        if ($pagoData['estatus'] === 'cancelado') {
            http_response_code(400);
            echo json_encode(['error' => 'El pago ya está cancelado']);
            return;
        }

        try {
            $this->db->beginTransaction();

            $this->conekta->cancelOrder($pagoData['conekta_order_id']);

            $this->pago->id = $id;
            $this->pago->estatus = 'cancelado';
            $this->pago->last_webhook_event = 'manual_cancel';
            $this->pago->updateEstatus();

            // Cancelar el pedido también
            $this->pedido->cancelar($pagoData['pedido_id']);

            $this->db->commit();

            http_response_code(200);
            echo json_encode(['mensaje' => 'Pago cancelado correctamente']);

        } catch (Exception $e) {
            $this->db->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /pagos/{id}/reembolsar
     * Body opcional: { "amount": 5000 }  (en centavos; si se omite = reembolso total)
     */
    public function reembolsar($id) {
        $data     = json_decode(file_get_contents("php://input"), true) ?? [];
        $stmt     = $this->pago->getById($id);
        $pagoData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pagoData) {
            http_response_code(404);
            echo json_encode(['error' => 'Pago no encontrado']);
            return;
        }

        if ($pagoData['estatus'] !== 'aprobado') {
            http_response_code(400);
            echo json_encode(['error' => 'Solo se pueden reembolsar pagos aprobados']);
            return;
        }

        if (empty($pagoData['conekta_order_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Este pago no tiene orden en Conekta']);
            return;
        }

        try {
            // Intentar obtener charge_id del raw_response guardado en BD
            $chargeId = null;
            if (!empty($pagoData['raw_response'])) {
                $raw = json_decode($pagoData['raw_response'], true);
                $chargeId = $raw['charges']['data'][0]['id'] ?? null;
            }

            // Si no estaba en BD, consultar directamente a Conekta
            if (!$chargeId) {
                $orden    = $this->conekta->getOrder($pagoData['conekta_order_id']);
                $chargeId = $orden['charges']['data'][0]['id'] ?? null;
            }

            if (!$chargeId) {
                throw new Exception('No se encontró el charge_id para este pago');
            }

            $amount = isset($data['amount']) ? (int)$data['amount'] : null;

            $this->conekta->refundOrder($pagoData['conekta_order_id'], $chargeId, $amount);

            $this->pago->id = $id;
            $this->pago->estatus = 'reembolsado';
            $this->pago->last_webhook_event = 'manual_refund';
            $this->pago->updateEstatus();

            http_response_code(200);
            echo json_encode(['mensaje' => 'Reembolso aplicado correctamente']);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
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

            $stmt     = $this->pago->getById($id);
            $pagoData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pagoData) {
                throw new Exception('Pago no encontrado');
            }

            $this->pago->id     = $id;
            $this->pago->estatus = $data['estatus'];

            if (!$this->pago->updateEstatus()) {
                throw new Exception('No se pudo actualizar el estatus');
            }

            if ($data['estatus'] === 'aprobado') {
                $this->marcarPedidoPagado($pagoData['pedido_id'], $pagoData['monto']);
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
        $stmt  = $this->pago->getByPedido($pedido_id);
        $pagos = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pagos[] = $this->formatRow($row);
        }

        $total_pagado = $this->pago->getTotalPagadoPorPedido($pedido_id);

        http_response_code(200);
        echo json_encode(['pagos' => $pagos, 'total_pagado' => (float)$total_pagado]);
    }

    public function getByComprador($comprador_id) {
        $stmt  = $this->pago->getByComprador($comprador_id);
        $pagos = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pagos[] = $this->formatRow($row);
        }

        http_response_code(200);
        echo json_encode($pagos);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function marcarPedidoPagado(int $pedido_id, float $montoPagado): void {
        $total_pagado = $this->pago->getTotalPagadoPorPedido($pedido_id);
        $stmtPedido = $this->pedido->getById($pedido_id);
        $pedidoData = $stmtPedido->fetch(PDO::FETCH_ASSOC);

        if ($pedidoData && $total_pagado >= $pedidoData['total']) {
            $this->pedido->id = $pedido_id;
            $this->pedido->estatus = 'pagado';
            $this->pedido->total  = $pedidoData['total'];
            $this->pedido->update();
        }
    }

    private function formatRow(array $row): array {
        return [
            'id' => (int)$row['id'],
            'pedido_id' => (int)$row['pedido_id'],
            'metodo_pago' => $row['metodo_pago'],
            'monto' => (float)$row['monto'],
            'estatus' => $row['estatus'],
            'currency' => $row['currency'] ?? 'MXN',
            'conekta_order_id' => $row['conekta_order_id'] ?? null,
            'payment_method_type' => $row['payment_method_type'] ?? null,
            'last_webhook_event' => $row['last_webhook_event'] ?? null,
            'fecha' => $row['fecha'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}
