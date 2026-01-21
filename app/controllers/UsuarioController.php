<?php
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../config/Database.php';

class UsuarioController {
    private $db;
    private $usuario;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->usuario = new Usuario($this->db);
    }

    public function index() {
        $stmt = $this->usuario->getAll();
        $usuarios = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $usuarios[] = [
                'id' => (int)$row['id'],
                'nombre' => $row['nombre'],
                'email' => $row['email'],
                'rol' => $row['rol'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }

        http_response_code(200);
        echo json_encode($usuarios);
    }

    public function show($id) {
        $stmt = $this->usuario->getById($id);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $usuario = [
                'id' => (int)$row['id'],
                'nombre' => $row['nombre'],
                'email' => $row['email'],
                'rol' => $row['rol'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];

            http_response_code(200);
            echo json_encode($usuario);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Usuario no encontrado']);
        }
    }

    public function store() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['nombre']) || empty($data['email']) || empty($data['password']) || empty($data['rol'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos incompletos']);
            return;
        }

        if (!in_array($data['rol'], ['comprador', 'admin'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Rol no válido']);
            return;
        }

        $this->usuario->email = $data['email'];
        if ($this->usuario->emailExists()) {
            http_response_code(400);
            echo json_encode(['error' => 'El email ya está registrado']);
            return;
        }

        $this->usuario->nombre = $data['nombre'];
        $this->usuario->password = password_hash($data['password'], PASSWORD_BCRYPT);
        $this->usuario->rol = $data['rol'];

        $usuario_id = $this->usuario->create();

        if ($usuario_id) {
            http_response_code(201);
            echo json_encode([
                'mensaje' => 'Usuario creado exitosamente',
                'id' => $usuario_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo crear el usuario']);
        }
    }

    public function update($id) {
        $data = json_decode(file_get_contents("php://input"), true);

        $stmt = $this->usuario->getById($id);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(404);
            echo json_encode(['error' => 'Usuario no encontrado']);
            return;
        }

        $this->usuario->id = $id;
        $this->usuario->nombre = $data['nombre'] ?? null;
        $this->usuario->email = $data['email'] ?? null;
        $this->usuario->rol = $data['rol'] ?? null;

        if ($this->usuario->update()) {
            http_response_code(200);
            echo json_encode(['mensaje' => 'Usuario actualizado']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo actualizar']);
        }
    }

    public function destroy($id) {
        $this->usuario->id = $id;

        if ($this->usuario->delete()) {
            http_response_code(200);
            echo json_encode(['mensaje' => 'Usuario eliminado']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo eliminar']);
        }
    }

    public function login() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['email']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email y contraseña requeridos']);
            return;
        }

        $stmt = $this->usuario->getByEmail($data['email']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && password_verify($data['password'], $row['password'])) {
            http_response_code(200);
            echo json_encode([
                'mensaje' => 'Login exitoso',
                'usuario' => [
                    'id' => (int)$row['id'],
                    'nombre' => $row['nombre'],
                    'email' => $row['email'],
                    'rol' => $row['rol']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Credenciales inválidas']);
        }
    }
}
