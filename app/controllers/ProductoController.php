<?php
require_once __DIR__ . '/../Models/Producto.php';
require_once __DIR__ . '/../Config/Database.php';

class ProductoController {
    private $db;
    private $producto;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->producto = new Producto($this->db);
    }

    // GET /productos
    public function index() {
        $stmt = $this->producto->getAll();
        $productos = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $productos[] = [
                'id' => (int)$row['id'],
                'nombre' => $row['nombre'],
                'precio' => (float)$row['precio'],
                'descripcion' => $row['descripcion'],
                'vendedor_id' => (int)$row['vendedor_id'],
                'vendedor_nombre' => $row['vendedor_nombre'],
                'stock' => (int)$row['stock']
            ];
        }

        http_response_code(200);
        echo json_encode($productos);
    }

    // GET /productos/{id}
    public function show($id) {
        $stmt = $this->producto->getById($id);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $producto = [
                'id' => (int)$row['id'],
                'nombre' => $row['nombre'],
                'precio' => (float)$row['precio'],
                'descripcion' => $row['descripcion'],
                'vendedor_id' => (int)$row['vendedor_id'],
                'vendedor_nombre' => $row['vendedor_nombre'],
                'stock' => (int)$row['stock']
            ];

            http_response_code(200);
            echo json_encode($producto);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Producto no encontrado']);
        }
    }

    // POST /productos
    public function store() {
        $data = json_decode(file_get_contents("php://input"), true);

        // Validaciones básicas
        if (empty($data['nombre']) || empty($data['precio']) || empty($data['vendedor_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos incompletos']);
            return;
        }

        $this->producto->nombre = $data['nombre'];
        $this->producto->precio = $data['precio'];
        $this->producto->descripcion = $data['descripcion'] ?? null;
        $this->producto->vendedor_id = $data['vendedor_id'];

        try {
            $this->db->beginTransaction();
            
            $producto_id = $this->producto->create();
            
            if ($producto_id) {
                // Crear inventario inicial
                $stock_inicial = $data['stock_inicial'] ?? 0;
                $this->producto->createInventario($producto_id, $stock_inicial);
                
                $this->db->commit();
                
                http_response_code(201);
                echo json_encode([
                    'mensaje' => 'Producto creado exitosamente',
                    'id' => $producto_id
                ]);
            } else {
                $this->db->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'No se pudo crear el producto']);
            }
        } catch (Exception $e) {
            $this->db->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // PUT /productos/{id}
    public function update($id) {
        $data = json_decode(file_get_contents("php://input"), true);

        $this->producto->id = $id;
        $this->producto->nombre = $data['nombre'] ?? null;
        $this->producto->precio = $data['precio'] ?? null;
        $this->producto->descripcion = $data['descripcion'] ?? null;

        if ($this->producto->update()) {
            http_response_code(200);
            echo json_encode(['mensaje' => 'Producto actualizado']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo actualizar']);
        }
    }

    // DELETE /productos/{id}
    public function destroy($id) {
        $this->producto->id = $id;

        if ($this->producto->delete()) {
            http_response_code(200);
            echo json_encode(['mensaje' => 'Producto eliminado']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo eliminar']);
        }
    }
}