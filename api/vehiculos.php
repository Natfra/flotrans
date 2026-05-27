<?php
// =====================================================
// ORBITRANS - API REST (vehiculos.php)
// Colocar en: C:\xampp\htdocs\orbitrans\api\vehiculos.php
// Base de datos: flotrans | Tabla: vehiculos
// =====================================================
 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");
 
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
 
// --- CONFIGURACIÓN ---
$host     = "localhost";
$dbname   = "flotrans";
$user     = "root";
$password = "";  // XAMPP por defecto vacío
 
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error de conexión: " . $e->getMessage()]);
    exit();
}
 
$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents("php://input"), true);
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
 
// =====================================================
// GET - Listar / filtrar
// Campos reales: id, placa, marca, modelo, anio,
//   capacidad_pasajeros, tipo, estado, kilometraje,
//   fecha_soat, fecha_tecnomecanica, created_at, updated_at
// =====================================================
if ($method === 'GET') {
    $where  = [];
    $params = [];
 
    if (!empty($_GET['buscar'])) {
        $where[]  = "(placa LIKE ? OR marca LIKE ? OR modelo LIKE ?)";
        $like     = "%" . $_GET['buscar'] . "%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if (!empty($_GET['tipo'])) {
        $where[]  = "tipo = ?";
        $params[] = $_GET['tipo'];
    }
    if (!empty($_GET['estado'])) {
        $where[]  = "estado = ?";
        $params[] = $_GET['estado'];
    }
 
    $sql = "SELECT
                id, placa, marca, modelo, anio,
                capacidad_pasajeros, tipo, estado, kilometraje,
                fecha_soat, fecha_tecnomecanica,
                created_at, updated_at
            FROM vehiculos";
    if ($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY placa ASC";
 
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
    exit();
}
 
// =====================================================
// POST - Crear vehículo
// =====================================================
if ($method === 'POST') {
    $required = ['placa', 'marca', 'tipo'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(["error" => "El campo '$field' es obligatorio"]);
            exit();
        }
    }
 
    // Placa única
    $check = $pdo->prepare("SELECT id FROM vehiculos WHERE placa = ?");
    $check->execute([strtoupper(trim($input['placa']))]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(["error" => "Ya existe un vehículo con esa placa"]);
        exit();
    }
 
    $stmt = $pdo->prepare("
        INSERT INTO vehiculos
            (placa, marca, modelo, anio, capacidad_pasajeros,
             tipo, estado, kilometraje, fecha_soat, fecha_tecnomecanica)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        strtoupper(trim($input['placa'])),
        trim($input['marca']),
        $input['modelo']               ?? null,
        $input['anio']                 ?? null,
        (int)($input['capacidad_pasajeros'] ?? 0),
        $input['tipo'],
        $input['estado']               ?? 'activo',
        (int)($input['kilometraje']    ?? 0),
        $input['fecha_soat']           ?? null,
        $input['fecha_tecnomecanica']  ?? null,
    ]);
 
    $nuevo = $pdo->prepare("SELECT * FROM vehiculos WHERE id = ?");
    $nuevo->execute([$pdo->lastInsertId()]);
    http_response_code(201);
    echo json_encode($nuevo->fetch());
    exit();
}
 
// =====================================================
// PUT - Actualizar vehículo
// =====================================================
if ($method === 'PUT' && $id) {
    $stmt = $pdo->prepare("
        UPDATE vehiculos SET
            placa                = ?,
            marca                = ?,
            modelo               = ?,
            anio                 = ?,
            capacidad_pasajeros  = ?,
            tipo                 = ?,
            estado               = ?,
            kilometraje          = ?,
            fecha_soat           = ?,
            fecha_tecnomecanica  = ?
        WHERE id = ?
    ");
    $stmt->execute([
        strtoupper(trim($input['placa'])),
        trim($input['marca']),
        $input['modelo']              ?? null,
        $input['anio']                ?? null,
        (int)($input['capacidad_pasajeros'] ?? 0),
        $input['tipo'],
        $input['estado'],
        (int)($input['kilometraje']   ?? 0),
        $input['fecha_soat']          ?? null,
        $input['fecha_tecnomecanica'] ?? null,
        $id
    ]);
    echo json_encode(["ok" => true]);
    exit();
}
 
// =====================================================
// DELETE - Eliminar vehículo
// =====================================================
if ($method === 'DELETE' && $id) {
    $stmt = $pdo->prepare("DELETE FROM vehiculos WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(["ok" => true]);
    exit();
}
 
http_response_code(405);
echo json_encode(["error" => "Método no permitido"]);
