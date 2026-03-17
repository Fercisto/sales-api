<?php
require_once __DIR__ . '/../config/Conekta.php';

class ConektaService {

    private function request(string $method, string $endpoint, array $body = null): array {
        $url = ConektaConfig::API_BASE_URL . $endpoint;

        $headers = [
            'Authorization: Bearer ' . ConektaConfig::apiKey(),
            'Accept: ' . ConektaConfig::API_ACCEPT,
            'Content-Type: application/json',
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body ? json_encode($body) : '{}');
        }

        $response   = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception('Error de conexión con Conekta: ' . $curlError);
        }

        $decoded = json_decode($response, true);

        if ($httpStatus >= 400) {
            $msg = $decoded['details'][0]['message'] ?? $decoded['message'] ?? 'Error en Conekta';
            throw new Exception('Conekta error ' . $httpStatus . ': ' . $msg);
        }

        return $decoded;
    }

    /**
     * Crea una orden en Conekta.
     *
     * $payload debe incluir:
     *   - currency       string  'MXN'
     *   - customer_info  array   [name, email, phone]
     *   - line_items     array   [[name, unit_price (centavos), quantity]]
     *   - charges        array   [[payment_method => [type=>'token', token_id]]]
     */
    public function createOrder(array $payload): array {
        return $this->request('POST', '/orders', $payload);
    }

    /**
     * Consulta una orden existente.
     */
    public function getOrder(string $conektaOrderId): array {
        return $this->request('GET', '/orders/' . $conektaOrderId);
    }

    /**
     * Cancela una orden.
     */
    public function cancelOrder(string $conektaOrderId): array {
        return $this->request('POST', '/orders/' . $conektaOrderId . '/cancel');
    }

    /**
     * Reembolsa una orden.
     * Si $amount es null se reembolsa el total.
     */
    public function refundOrder(string $conektaOrderId, string $chargeId, int $amount = null): array {
        $body = ['charge_id' => $chargeId, 'reason' => 'other'];
        if ($amount !== null) {
            $body['amount'] = $amount;
        }
        return $this->request('POST', '/orders/' . $conektaOrderId . '/refunds', $body);
    }
}
