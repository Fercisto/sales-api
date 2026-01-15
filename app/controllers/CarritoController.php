<?php
require_once __DIR__ . '/../Models/Carrito.php';
require_once __DIR__ . '/../Config/Database.php';

class CarritoController {
    private $db;
    private $carrito;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->carrito = new Carrito($this->db);
    }

    // GET /carrito/{usuario_id} - Ver carrito completo con total
    public function show($usuario_id) {
        $stmt = $this->carrito->getByUsuario($usuario_id);
        $items = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = [
                'id' => (int)$row['id'],
                'producto_id' => (int)$row['producto_id'],
                'producto_nombre' => $row['producto_nombre'],
                'precio' => (float)$row['producto_precio'],
                'descripcion' => $row['producto_descripcion'],
                'vendedor_nombre' => $row['vendedor_nombre'],
                'cantidad' => (int)$row['cantidad'],
                'subtotal' => (float)$row['subtotal'],
                'stock_disponible' => (int)$row['stock_disponible']
            ];
        }

        $total_items = $this->carrito->countItems($usuario_id);
        $total = $this->carrito->calculateTotal($usuario_id);

        http_response_code(200);
        echo json_encode([
            'items' => $items,
            'total' => $total,
            'total_items' => $total_items
        ]);
    }

    // POST /carrito - Agregar producto al carrito
    public function store() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['usuario_id']) || empty($data['producto_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'usuario_id y producto_id son requeridos']);
            return;
        }

        $usuario_id = $data['usuario_id'];
        $producto_id = $data['producto_id'];
        $cantidad = $data['cantidad'] ?? 1;

        if ($cantidad < 1) {
            http_response_code(400);
            echo json_encode(['error' => 'La cantidad debe ser mayor a 0']);
            return;
        }

        // Verificar si hay stock disponible
        if (!$this->carrito->verificarStock($producto_id, $cantidad)) {
            http_response_code(400);
            echo json_encode(['error' => 'No hay suficiente stock disponible']);
            return;
        }

        try {
            $this->db->beginTransaction();

            // Verificar si el producto ya está en el carrito
            $existe = $this->carrito->existeEnCarrito($usuario_id, $producto_id);

            if ($existe) {
                // Si existe, incrementar la cantidad
                $nueva_cantidad = $existe['cantidad'] + $cantidad;

                // Verificar stock con la nueva cantidad
                if (!$this->carrito->verificarStock($producto_id, $nueva_cantidad)) {
                    $this->db->rollBack();
                    http_response_code(400);
                    echo json_encode(['error' => 'No hay suficiente stock disponible']);
                    return;
                }

                $this->carrito->incrementarCantidad($existe['id'], $cantidad);
                $item_id = $existe['id'];
                $mensaje = 'Cantidad actualizada en el carrito';
            } else {
                // Si no existe, crear nuevo item
                $this->carrito->usuario_id = $usuario_id;
                $this->carrito->producto_id = $producto_id;
                $this->carrito->cantidad = $cantidad;

                $item_id = $this->carrito->create();
                $mensaje = 'Producto agregado al carrito';
            }

            if ($item_id) {
                $this->db->commit();

                // Obtener los totales actualizados
                $total_items = $this->carrito->countItems($usuario_id);
                $total = $this->carrito->calculateTotal($usuario_id);

                http_response_code(201);
                echo json_encode([
                    'mensaje' => $mensaje,
                    'id' => (int)$item_id,
                    'total_items' => $total_items,
                    'total' => $total
                ]);
            } else {
                $this->db->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'No se pudo agregar al carrito']);
            }
        } catch (Exception $e) {
            $this->db->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // PUT /carrito/{id} - Actualizar cantidad de un item
    public function update($id) {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['cantidad'])) {
            http_response_code(400);
            echo json_encode(['error' => 'La cantidad es requerida']);
            return;
        }

        $cantidad = (int)$data['cantidad'];

        if ($cantidad < 1) {
            http_response_code(400);
            echo json_encode(['error' => 'La cantidad debe ser mayor a 0']);
            return;
        }

        // Obtener información del item para verificar stock
        $query = "SELECT producto_id, usuario_id FROM carrito WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            http_response_code(404);
            echo json_encode(['error' => 'Item no encontrado en el carrito']);
            return;
        }

        // Verificar stock
        if (!$this->carrito->verificarStock($item['producto_id'], $cantidad)) {
            http_response_code(400);
            echo json_encode(['error' => 'No hay suficiente stock disponible']);
            return;
        }

        if ($this->carrito->updateCantidad($id, $cantidad)) {
            // Obtener los totales actualizados
            $total_items = $this->carrito->countItems($item['usuario_id']);
            $total = $this->carrito->calculateTotal($item['usuario_id']);

            http_response_code(200);
            echo json_encode([
                'mensaje' => 'Cantidad actualizada',
                'total_items' => $total_items,
                'total' => $total
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo actualizar la cantidad']);
        }
    }

    // DELETE /carrito/{id} - Eliminar un item del carrito
    public function destroy($id) {
        // Obtener usuario_id antes de eliminar para calcular totales
        $query = "SELECT usuario_id FROM carrito WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            http_response_code(404);
            echo json_encode(['error' => 'Item no encontrado en el carrito']);
            return;
        }

        if ($this->carrito->delete($id)) {
            // Obtener los totales actualizados
            $total_items = $this->carrito->countItems($item['usuario_id']);
            $total = $this->carrito->calculateTotal($item['usuario_id']);

            http_response_code(200);
            echo json_encode([
                'mensaje' => 'Item eliminado del carrito',
                'total_items' => $total_items,
                'total' => $total
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo eliminar el item']);
        }
    }

    // DELETE /carrito/{usuario_id}/clear - Vaciar todo el carrito
    public function clear($usuario_id) {
        if ($this->carrito->clearCarrito($usuario_id)) {
            http_response_code(200);
            echo json_encode([
                'mensaje' => 'Carrito vaciado',
                'total_items' => 0,
                'total' => 0
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo vaciar el carrito']);
        }
    }

    // GET /carrito/{usuario_id}/count - Obtener número de items
    public function count($usuario_id) {
        $total_items = $this->carrito->countItems($usuario_id);

        http_response_code(200);
        echo json_encode([
            'total_items' => $total_items
        ]);
    }

    // GET /carrito/{usuario_id}/total - Obtener el total del carrito
    public function total($usuario_id) {
        $total = $this->carrito->calculateTotal($usuario_id);

        http_response_code(200);
        echo json_encode([
            'total' => $total
        ]);
    }
}
