<?php
class ConektaConfig {
    const API_BASE_URL = 'https://api.conekta.io';
    const API_ACCEPT   = 'application/vnd.conekta-v2.2.0+json';

    public static function apiKey(): string {
        // Cargar .env si aún no se cargó
        static $loaded = false;
        if (!$loaded) {
            $envFile = __DIR__ . '/../../.env';
            if (file_exists($envFile)) {
                foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    if (str_starts_with(trim($line), '#')) continue;
                    [$key, $value] = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value);
                }
            }
            $loaded = true;
        }

        $key = $_ENV['CONEKTA_API_KEY'] ?? '';
        if (empty($key)) {
            throw new Exception('CONEKTA_API_KEY no está definida en el .env');
        }
        return $key;
    }
}
