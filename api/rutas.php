bash

cat > /mnt/user-data/outputs/rutas.php << 'EOF'
<?php
// =====================================================
// FLOTRANS - API REST (rutas.php)
// Colocar en: C:\xampp\htdocs\flotrans\api\rutas.php
// =====================================================

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host     = "localhost";
$dbname   = "flotrans";
$user     = "root";
$password = "";

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

// GET
if ($method === 'GET') {
    $where  = [];
    $params = [];

    if (!empty($_GET['buscar'])) {
        $where[]  = "r.nombre LIKE ?";
        $params[] = "%" . $_GET['buscar'] . "%";
    }
    if (!empty($_GET['tipo'])) {
        $where[]  = "r.tipo = ?";
        $params[] = $_GET['tipo'];
    }
    if (!empty($_GET['estado'])) {
        $where[]  = "r.estado = ?";
        $params[] = $_GET['estado'];
    }

    $sql = "SELECT r.*, c.codigo as contrato_codigo 
            FROM rutas r
            LEFT JOIN contratos c ON r.contrato_id = c.id";
    if ($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY r.nombre ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
    exit();
}

// POST
if ($method === 'POST') {
    $required = ['nombre', 'tipo'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(["error" => "El campo '$field' es obligatorio"]);
            exit();
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO rutas (contrato_id, nombre, franja_horaria, hora_inicio, hora_fin, tipo, estado)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $input['contrato_id']    ?? null,
        trim($input['nombre']),
        $input['franja_horaria'] ?? null,
        $input['hora_inicio']    ?? null,
        $input['hora_fin']       ?? null,
        $input['tipo'],
        $input['estado']         ?? 'Activa',
    ]);

    $nuevo = $pdo->prepare("SELECT r.*, c.codigo as contrato_codigo FROM rutas r LEFT JOIN contratos c ON r.contrato_id = c.id WHERE r.id = ?");
    $nuevo->execute([$pdo->lastInsertId()]);
    http_response_code(201);
    echo json_encode($nuevo->fetch());
    exit();
}

// PUT
if ($method === 'PUT' && $id) {
    $stmt = $pdo->prepare("
        UPDATE rutas SET
            contrato_id = ?, nombre = ?, franja_horaria = ?,
            hora_inicio = ?, hora_fin = ?, tipo = ?, estado = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $input['contrato_id']    ?? null,
        trim($input['nombre']),
        $input['franja_horaria'] ?? null,
        $input['hora_inicio']    ?? null,
        $input['hora_fin']       ?? null,
        $input['tipo'],
        $input['estado'],
        $id
    ]);
    echo json_encode(["ok" => true]);
    exit();
}

// DELETE
if ($method === 'DELETE' && $id) {
    $stmt = $pdo->prepare("DELETE FROM rutas WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(["ok" => true]);
    exit();
}

http_response_code(405);
echo json_encode(["error" => "Método no permitido"]);
EOF
echo "rutas.php creado"
