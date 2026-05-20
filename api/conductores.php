<?php
// =====================================================
// FLOTRANS - API REST (conductores.php)
// Colocar en: C:\xampp\htdocs\flotrans\api\conductores.php
// =====================================================
 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");
 
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
 
// --- CONFIGURACIÓN DE BASE DE DATOS ---
$host     = "localhost";
$dbname   = "flotrans";          // ← nombre real de la BD
$user     = "root";
$password = "";   // En XAMPP por defecto está vacío
 
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
// GET - Listar todos o filtrar
// =====================================================
if ($method === 'GET') {
    $where  = [];
    $params = [];
 
    if (!empty($_GET['buscar'])) {
        // La tabla real no tiene 'apellido', solo 'nombre' y 'cedula'
        $where[]  = "(nombre LIKE ? OR cedula LIKE ?)";
        $like     = "%" . $_GET['buscar'] . "%";
        $params[] = $like;
        $params[] = $like;
    }
    if (!empty($_GET['tipo_licencia'])) {
        $where[]  = "tipo_licencia = ?";
        $params[] = $_GET['tipo_licencia'];
    }
    if (!empty($_GET['estado'])) {
        $where[]  = "estado = ?";
        $params[] = $_GET['estado'];
    }
 
    $sql = "SELECT * FROM conductores";
    if ($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY nombre ASC";
 
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
    exit();
}
 
// =====================================================
// POST - Crear nuevo conductor
// =====================================================
if ($method === 'POST') {
    // Campos obligatorios según la tabla real
    $required = ['nombre', 'cedula', 'tipo_licencia'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(["error" => "El campo '$field' es obligatorio"]);
            exit();
        }
    }
 
    // Verificar cédula duplicada
    $check = $pdo->prepare("SELECT id FROM conductores WHERE cedula = ?");
    $check->execute([$input['cedula']]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(["error" => "Ya existe un conductor con esa cédula"]);
        exit();
    }
 
    $stmt = $pdo->prepare("
        INSERT INTO conductores (nombre, cedula, tipo_licencia, jornada_max_horas, descanso_min_horas, estado, fecha_ingreso)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        trim($input['nombre']),
        trim($input['cedula']),
        $input['tipo_licencia'],
        $input['jornada_max_horas']  ?? 8,
        $input['descanso_min_horas'] ?? 1,
        $input['estado']             ?? 'Disponible',
        $input['fecha_ingreso']      ?? date('Y-m-d'),
    ]);
 
    $nuevo = $pdo->prepare("SELECT * FROM conductores WHERE id = ?");
    $nuevo->execute([$pdo->lastInsertId()]);
    http_response_code(201);
    echo json_encode($nuevo->fetch());
    exit();
}
 
// =====================================================
// PUT - Actualizar conductor
// =====================================================
if ($method === 'PUT' && $id) {
    $stmt = $pdo->prepare("
        UPDATE conductores SET
            nombre = ?, cedula = ?, tipo_licencia = ?,
            jornada_max_horas = ?, descanso_min_horas = ?,
            estado = ?, fecha_ingreso = ?
        WHERE id = ?
    ");
    $stmt->execute([
        trim($input['nombre']),
        trim($input['cedula']),
        $input['tipo_licencia'],
        $input['jornada_max_horas'],
        $input['descanso_min_horas'],
        $input['estado'],
        $input['fecha_ingreso'] ?? date('Y-m-d'),
        $id
    ]);
    echo json_encode(["ok" => true]);
    exit();
}
 
// =====================================================
// DELETE - Eliminar conductor
// =====================================================
if ($method === 'DELETE' && $id) {
    $stmt = $pdo->prepare("DELETE FROM conductores WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(["ok" => true]);
    exit();
}
 
http_response_code(405);
echo json_encode(["error" => "Método no permitido"]);