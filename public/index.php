<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

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
            require_once __DIR__ . '/../app/controllers/ProductoController.php';
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

        case 'usuarios':
            require_once __DIR__ . '/../app/controllers/UsuarioController.php';
            $controller = new UsuarioController();

            $action = $parts[1] ?? null;

            if ($action === 'login' && $method === 'POST') {
                $controller->login();
            } else {
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
            }
            break;

        case 'pedidos':
            require_once __DIR__ . '/../app/controllers/PedidoController.php';
            $controller = new PedidoController();

            $action = $parts[2] ?? null;

            if ($id && $action === 'estatus' && $method === 'PATCH') {
                $controller->updateEstatus($id);
            } elseif ($id && $action === 'comprador' && $method === 'GET') {
                $controller->getByComprador($id);
            } else {
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
            }
            break;

        case 'pagos':
            require_once __DIR__ . '/../app/controllers/PagoController.php';
            $controller = new PagoController();

            $action = $parts[2] ?? null;

            if ($id && $action === 'estatus' && $method === 'PATCH') {
                $controller->updateEstatus($id);
            } elseif ($id && $action === 'pedido' && $method === 'GET') {
                $controller->getByPedido($id);
            } else {
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
            }
            break;

        case 'facturas':
            require_once __DIR__ . '/../app/controllers/FacturaController.php';
            $controller = new FacturaController();

            $action = $parts[2] ?? null;

            if ($id && $action === 'cancelar' && $method === 'PATCH') {
                $controller->cancelar($id);
            } elseif ($id && $action === 'pedido' && $method === 'GET') {
                $controller->getByPedido($id);
            } elseif ($id && $action === 'cliente' && $method === 'GET') {
                $controller->getByCliente($id);
            } else {
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
            }
            break;

        case 'carrito':
            require_once __DIR__ . '/../app/controllers/CarritoController.php';
            $controller = new CarritoController();

            $action = $parts[2] ?? null;

            // GET /carrito/{usuario_id}/count
            if ($id && $action === 'count' && $method === 'GET') {
                $controller->count($id);
            }
            // GET /carrito/{usuario_id}/total
            elseif ($id && $action === 'total' && $method === 'GET') {
                $controller->total($id);
            }
            // DELETE /carrito/{usuario_id}/clear
            elseif ($id && $action === 'clear' && $method === 'DELETE') {
                $controller->clear($id);
            }
            // Operaciones normales CRUD
            else {
                switch ($method) {
                    case 'GET':
                        if ($id) {
                            // GET /carrito/{usuario_id}
                            $controller->show($id);
                        } else {
                            http_response_code(400);
                            echo json_encode(['error' => 'Se requiere usuario_id']);
                        }
                        break;

                    case 'POST':
                        // POST /carrito
                        $controller->store();
                        break;

                    case 'PUT':
                        // PUT /carrito/{id}
                        if ($id) {
                            $controller->update($id);
                        } else {
                            http_response_code(400);
                            echo json_encode(['error' => 'Se requiere id del item']);
                        }
                        break;

                    case 'DELETE':
                        // DELETE /carrito/{id}
                        if ($id) {
                            $controller->destroy($id);
                        } else {
                            http_response_code(400);
                            echo json_encode(['error' => 'Se requiere id del item']);
                        }
                        break;

                    default:
                        http_response_code(405);
                        echo json_encode(['error' => 'Método no permitido']);
                }
            }
            break;

        case '':
            http_response_code(200);
            echo json_encode([
                'mensaje' => 'API Sistema de Ventas',
                'version' => '1.0',
                'endpoints' => [
                    'usuarios' => '/usuarios',
                    'productos' => '/productos',
                    'pedidos' => '/pedidos',
                    'pagos' => '/pagos',
                    'facturas' => '/facturas',
                    'carrito' => '/carrito'
                ]
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