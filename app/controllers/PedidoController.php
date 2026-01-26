<?php
require_once __DIR__ . '/../models/Pedido.php';
require_once __DIR__ . '/../models/PedidoProducto.php';
require_once __DIR__ . '/../models/Inventario.php';
require_once __DIR__ . '/../models/Producto.php';
require_once __DIR__ . '/../config/Database.php';

class PedidoController {
    private $db;
    private $pedido;
    private $pedidoProducto;
    private $inventario;
    private $producto;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->pedido = new Pedido($this->db);
        $this->pedidoProducto = new PedidoProducto($this->db);
        $this->inventario = new Inventario($this->db);
        $this->producto = new Producto($this->db);
    }

    public function index() {
        $usuario_id = $_GET['usuario_id'] ?? null;

        $stmt = $this->pedido->getAll($usuario_id);
        $pedidos = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pedidos[] = [
                'id' => (int)$row['id'],
                'comprador_id' => (int)$row['comprador_id'],
                'comprador_nombre' => $row['comprador_nombre'],
                'fecha' => $row['fecha'],
                'total' => (float)$row['total'],
                'estatus' => $row['estatus'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }

        http_response_code(200);
        echo json_encode($pedidos);
    }

    public function show($id) {
        $stmt = $this->pedido->getById($id);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $productosStmt = $this->pedido->getProductos($id);
            $productos = [];

            while ($prod = $productosStmt->fetch(PDO::FETCH_ASSOC)) {
                $productos[] = [
                    'id' => (int)$prod['id'],
                    'producto_id' => (int)$prod['producto_id'],
                    'nombre' => $prod['nombre'],
                    'descripcion' => $prod['descripcion'],
                    'cantidad' => (int)$prod['cantidad'],
                    'precio_unitario' => (float)$prod['precio_unitario'],
                    'subtotal' => (float)$prod['cantidad'] * (float)$prod['precio_unitario']
                ];
            }

            $pedido = [
                'id' => (int)$row['id'],
                'comprador_id' => (int)$row['comprador_id'],
                'comprador_nombre' => $row['comprador_nombre'],
                'fecha' => $row['fecha'],
                'total' => (float)$row['total'],
                'estatus' => $row['estatus'],
                'productos' => $productos,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];

            http_response_code(200);
            echo json_encode($pedido);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Pedido no encontrado']);
        }
    }

    public function store() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['comprador_id']) || empty($data['productos'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos incompletos']);
            return;
        }

        try {
            $this->db->beginTransaction();

            $total = 0;
            foreach ($data['productos'] as $item) {
                if (!$this->inventario->verificarDisponibilidad($item['producto_id'], $item['cantidad'])) {
                    throw new Exception("Stock insuficiente para el producto ID: " . $item['producto_id']);
                }

                $stmtProd = $this->producto->getById($item['producto_id']);
                $prodData = $stmtProd->fetch(PDO::FETCH_ASSOC);

                if (!$prodData) {
                    throw new Exception("Producto no encontrado: " . $item['producto_id']);
                }

                $precio = $prodData['precio'];
                $total += $precio * $item['cantidad'];
            }

            $this->pedido->comprador_id = $data['comprador_id'];
            $this->pedido->total = $total;
            $this->pedido->estatus = $data['estatus'] ?? 'pendiente';

            $pedido_id = $this->pedido->create();

            if (!$pedido_id) {
                throw new Exception("No se pudo crear el pedido");
            }

            foreach ($data['productos'] as $item) {
                $stmtProd = $this->producto->getById($item['producto_id']);
                $prodData = $stmtProd->fetch(PDO::FETCH_ASSOC);
                $precio = $prodData['precio'];

                $this->pedidoProducto->pedido_id = $pedido_id;
                $this->pedidoProducto->producto_id = $item['producto_id'];
                $this->pedidoProducto->cantidad = $item['cantidad'];
                $this->pedidoProducto->precio_unitario = $precio;

                if (!$this->pedidoProducto->create()) {
                    throw new Exception("Error al agregar producto al pedido");
                }

                if (!$this->inventario->decrementar($item['producto_id'], $item['cantidad'])) {
                    throw new Exception("Error al actualizar inventario");
                }
            }

            $this->db->commit();

            http_response_code(201);
            echo json_encode([
                'mensaje' => 'Pedido creado exitosamente',
                'id' => $pedido_id,
                'total' => $total
            ]);

        } catch (Exception $e) {
            $this->db->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function update($id) {
        $data = json_decode(file_get_contents("php://input"), true);

        $stmt = $this->pedido->getById($id);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(404);
            echo json_encode(['error' => 'Pedido no encontrado']);
            return;
        }

        $this->pedido->id = $id;
        $this->pedido->estatus = $data['estatus'] ?? null;
        $this->pedido->total = $data['total'] ?? null;

        if ($this->pedido->update()) {
            http_response_code(200);
            echo json_encode(['mensaje' => 'Pedido actualizado']);
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

        $this->pedido->id = $id;
        $this->pedido->estatus = $data['estatus'];

        if ($this->pedido->updateEstatus()) {
            http_response_code(200);
            echo json_encode(['mensaje' => 'Estatus actualizado']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo actualizar el estatus']);
        }
    }

    public function destroy($id) {
        try {
            $this->db->beginTransaction();

            $stmt = $this->pedidoProducto->getByPedido($id);
            while ($prod = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->inventario->incrementar($prod['producto_id'], $prod['cantidad']);
            }

            $this->pedidoProducto->deleteByPedido($id);

            $this->pedido->id = $id;
            if (!$this->pedido->delete()) {
                throw new Exception("No se pudo eliminar el pedido");
            }

            $this->db->commit();

            http_response_code(200);
            echo json_encode(['mensaje' => 'Pedido eliminado']);

        } catch (Exception $e) {
            $this->db->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getByComprador($comprador_id) {
        $stmt = $this->pedido->getByComprador($comprador_id);
        $pedidos = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pedidos[] = [
                'id' => (int)$row['id'],
                'comprador_id' => (int)$row['comprador_id'],
                'comprador_nombre' => $row['comprador_nombre'],
                'fecha' => $row['fecha'],
                'total' => (float)$row['total'],
                'estatus' => $row['estatus']
            ];
        }

        http_response_code(200);
        echo json_encode($pedidos);
    }

    public function cancelar($id) {
        try {
            $this->pedido->cancelar($id);

            http_response_code(200);
            echo json_encode(['mensaje' => 'Pedido cancelado exitosamente']);

        } catch (PDOException $e) {
            // El SP lanza errores con SQLSTATE 45000
            $mensaje = $e->getMessage();

            // Extraer mensaje limpio del error del SP
            if (strpos($mensaje, 'El pedido no existe') !== false) {
                http_response_code(404);
                echo json_encode(['error' => 'El pedido no existe']);
            } elseif (strpos($mensaje, 'ya está cancelado') !== false) {
                http_response_code(400);
                echo json_encode(['error' => 'El pedido ya está cancelado']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error al cancelar el pedido', 'detalle' => $mensaje]);
            }
        }
    }
}
