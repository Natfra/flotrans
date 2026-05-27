<?php
// =====================================================
// FLOTRANS — registro.php
// Colocar en: C:\xampp\htdocs\flotrans\api\registro.php
// Maneja: creación de nuevos usuarios desde el formulario
// =====================================================

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"]);
    exit();
}

// ---- Conexión ----
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
    echo json_encode(["error" => "Error de conexión con la base de datos"]);
    exit();
}

// ---- Leer body ----
$input = json_decode(file_get_contents("php://input"), true);

$nombre     = trim($input['nombre']     ?? '');
$correo     = trim($input['correo']     ?? '');
$contrasena = trim($input['contrasena'] ?? '');
$rol        = trim($input['rol']        ?? '');

// ---- Validaciones ----
$rolesPermitidos = ['administrador', 'operador', 'conductor'];

if (!$nombre) {
    http_response_code(400);
    echo json_encode(["error" => "El nombre es obligatorio"]);
    exit();
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["error" => "El correo electrónico no es válido"]);
    exit();
}

if (strlen($contrasena) < 8) {
    http_response_code(400);
    echo json_encode(["error" => "La contraseña debe tener al menos 8 caracteres"]);
    exit();
}

if (!in_array($rol, $rolesPermitidos)) {
    http_response_code(400);
    echo json_encode(["error" => "El rol seleccionado no es válido"]);
    exit();
}

// ---- Verificar correo duplicado ----
$check = $pdo->prepare("SELECT id FROM usuarios WHERE correo = ?");
$check->execute([$correo]);
if ($check->fetch()) {
    http_response_code(409);
    echo json_encode(["error" => "Ya existe una cuenta registrada con ese correo"]);
    exit();
}

// ---- Crear usuario ----
$hash = password_hash($contrasena, PASSWORD_BCRYPT);

try {
    $stmt = $pdo->prepare("
        INSERT INTO usuarios (nombre, correo, contrasena_hash, rol, activo, created_at)
        VALUES (?, ?, ?, ?, 1, NOW())
    ");
    $stmt->execute([$nombre, $correo, $hash, $rol]);
    $nuevoId = (int) $pdo->lastInsertId();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "No se pudo crear la cuenta. Intenta de nuevo."]);
    exit();
}

// ---- Registrar en auditoría (si la tabla existe) ----
try {
    $pdo->prepare("
        INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, ip_usuario)
        VALUES (?, 'INSERT', 'usuarios', ?, ?)
    ")->execute([$nuevoId, $nuevoId, $_SERVER['REMOTE_ADDR'] ?? '']);
} catch (PDOException $e) {
    // La auditoría es opcional; no interrumpir el flujo
}

// ---- Respuesta exitosa ----
echo json_encode([
    "ok"      => true,
    "mensaje" => "Cuenta creada correctamente",
    "usuario" => [
        "id"     => $nuevoId,
        "nombre" => $nombre,
        "correo" => $correo,
        "rol"    => $rol,
    ]
]);