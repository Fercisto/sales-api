<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../app/Config/Database.php';

$method = $_SERVER['REQUEST_METHOD'];

// Obtener URI y limpiarla
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = dirname($_SERVER['SCRIPT_NAME']);

// Remover el path base del proyecto
$uri = str_replace($script_name, '', $request_uri);
$uri = trim($uri, '/');

// Remover query string si existe
$uri = parse_url($uri, PHP_URL_PATH);
$uri = trim($uri, '/');

// Separar en partes
$parts = explode('/', $uri);

// Remover "api" si está presente
if (isset($parts[0]) && $parts[0] === 'api') {
    array_shift($parts);
}

$resource = $parts[0] ?? '';
$id = $parts[1] ?? null;

try {
    switch ($resource) {
        case 'productos':
            require_once __DIR__ . '/../app/Controllers/ProductoController.php';
            $controller = new ProductoController();
            
            switch ($method) {
                case 'GET':
                    if ($id) {
                        $controller->show($id);
                    } else {
                        $controller->index();
                    }
                    break;
                    
                case 'POST':
                    $controller->store();
                    break;
                    
                case 'PUT':
                    $controller->update($id);
                    break;
                    
                case 'DELETE':
                    $controller->destroy($id);
                    break;
                    
                default:
                    http_response_code(405);
                    echo json_encode(['error' => 'Método no permitido']);
            }
            break;
            
        case '':
            http_response_code(200);
            echo json_encode([
                'mensaje' => 'API Sistema de Ventas',
                'version' => '1.0'
            ]);
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'error' => 'Recurso no encontrado',
                'recurso_buscado' => $resource
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error del servidor',
        'mensaje' => $e->getMessage()
    ]);
}