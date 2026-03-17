<?php
require_once __DIR__ . '/../models/Pago.php';
require_once __DIR__ . '/../models/Pedido.php';
require_once __DIR__ . '/../config/Database.php';

class WebhookController {
    private $db;
    private $pago;
    private $pedido;

    // Mapa de eventos
    private const EVENT_MAP = [
        'charge.paid' => 'aprobado',
        'charge.failed' => 'rechazado',
        'charge.declined' => 'rechazado',
        'order.canceled' => 'cancelado',
        'order.refunded' => 'reembolsado',
    ];

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->pago = new Pago($this->db);
        $this->pedido = new Pedido($this->db);
    }

    /**
     * POST /webhooks/conekta
     * Conekta llama a este endpoint para notificar eventos de pago.
     * Siempre debe responder HTTP 200, de lo contrario Conekta reintenta.
     */
    public function handle() {
        $raw  = file_get_contents('php://input');
        $body = json_decode($raw, true);

        // Si el body no es válido, igual responde 200 para no generar reintentos
        if (!$body || !isset($body['type'])) {
            http_response_code(200);
            echo json_encode(['mensaje' => 'Evento ignorado']);
            return;
        }

        $eventType = $body['type'];

        // Para charge.paid/declined, el objeto es el charge y order_id es un campo aparte.
        // Para order.canceled/refunded, el objeto es la orden y su id es el order_id.
        $conektaOrderId = $body['data']['object']['order_id']
                       ?? $body['data']['object']['id']
                       ?? null;

        // Solo procesar eventos que nos interesan
        if (!isset(self::EVENT_MAP[$eventType]) || !$conektaOrderId) {
            http_response_code(200);
            echo json_encode(['mensaje' => 'Evento no manejado: ' . $eventType]);
            return;
        }

        $nuevoEstatus = self::EVENT_MAP[$eventType];

        // Buscar el pago local por conekta_order_id
        $stmt = $this->pago->getByConektaOrderId($conektaOrderId);
        $pagoData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pagoData) {
            // No existe en BD, respondemos 200 igual para que Conekta no reintente
            http_response_code(200);
            echo json_encode(['mensaje' => 'Orden no encontrada localmente']);
            return;
        }

        try {
            $this->db->beginTransaction();

            // Actualizar estatus del pago
            $this->pago->id = $pagoData['id'];
            $this->pago->estatus = $nuevoEstatus;
            $this->pago->last_webhook_event = $eventType;
            $this->pago->updateEstatus();

            // Si el pago fue aprobado, marcar el pedido como pagado
            if ($nuevoEstatus === 'aprobado') {
                $this->pedido->id = $pagoData['pedido_id'];
                $this->pedido->estatus = 'pagado';
                $this->pedido->total = $pagoData['total_pedido'];
                $this->pedido->update();
            }

            // Si fue cancelado, usar el SP de cancelación (restaura stock)
            if ($nuevoEstatus === 'cancelado') {
                $this->pedido->cancelar($pagoData['pedido_id']);
            }

            $this->db->commit();

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Webhook Conekta error: ' . $e->getMessage());
        }

        http_response_code(200);
        echo json_encode(['mensaje' => 'OK']);
    }
}
