<?php
// =====================================================
// FLOTRANS — auth.php
// Colocar en: C:\xampp\htdocs\flotrans\api\auth.php
// Maneja: login, logout, verificar sesión
// =====================================================

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
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
    echo json_encode(["error" => "Error de conexión: " . $e->getMessage()]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents("php://input"), true);
$accion = $_GET['accion'] ?? 'login';

// =====================================================
// POST /auth.php  → Login
// Body: { correo, contrasena, rol }
// =====================================================
if ($method === 'POST') {

    $correo    = trim($input['correo']     ?? '');
    $contrasena = trim($input['contrasena'] ?? '');
    $rolSolicitado = trim($input['rol']    ?? '');

    if (!$correo || !$contrasena) {
        http_response_code(400);
        echo json_encode(["error" => "Correo y contraseña son obligatorios"]);
        exit();
    }

    // Buscar usuario por correo
    $stmt = $pdo->prepare("
        SELECT id, nombre, correo, contrasena_hash, rol, activo
        FROM usuarios
        WHERE correo = ?
        LIMIT 1
    ");
    $stmt->execute([$correo]);
    $usuario = $stmt->fetch();

    // Usuario no existe
    if (!$usuario) {
        http_response_code(401);
        echo json_encode(["error" => "Credenciales incorrectas"]);
        exit();
    }

    // Usuario inactivo
    if (!(int)$usuario['activo']) {
        http_response_code(403);
        echo json_encode(["error" => "Tu cuenta está desactivada. Contacta al administrador."]);
        exit();
    }

    // Verificar contraseña con password_verify (bcrypt)
    if (!password_verify($contrasena, $usuario['contrasena_hash'])) {
        http_response_code(401);
        echo json_encode(["error" => "Credenciales incorrectas"]);
        exit();
    }

    // Verificar que el rol seleccionado coincide con el rol real del usuario
    if ($rolSolicitado && $usuario['rol'] !== $rolSolicitado) {
        http_response_code(403);
        echo json_encode([
            "error" => "Tu cuenta no tiene el rol de " . ucfirst($rolSolicitado) . "."
        ]);
        exit();
    }

    // Registrar último acceso
    $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?")
        ->execute([$usuario['id']]);

    // Registrar en auditoría
    $pdo->prepare("
        INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, ip_usuario)
        VALUES (?, 'INSERT', 'sesion', ?, ?)
    ")->execute([$usuario['id'], $usuario['id'], $_SERVER['REMOTE_ADDR'] ?? '']);

    // Devolver datos del usuario (sin contraseña)
    echo json_encode([
        "ok"      => true,
        "usuario" => [
            "id"     => (int) $usuario['id'],
            "nombre" => $usuario['nombre'],
            "correo" => $usuario['correo'],
            "rol"    => $usuario['rol'],
        ]
    ]);
    exit();
}

// =====================================================
// GET /auth.php?accion=crear_admin
// Crea el usuario administrador por defecto UNA SOLA VEZ.
// Llama esto una sola vez desde el navegador para generar
// el primer admin: http://localhost/flotrans/api/auth.php?accion=crear_admin
// Después de usarlo, ELIMINA O COMENTA este bloque.
// =====================================================
if ($method === 'GET' && $accion === 'crear_admin') {

    // Verificar que no exista ya un administrador
    $check = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'administrador'");
    if ((int)$check->fetchColumn() > 0) {
        echo json_encode(["error" => "Ya existe un administrador. Usa la interfaz para gestionar usuarios."]);
        exit();
    }

    $nombre    = "Administrador";
    $correo    = "admin@flotrans.com";
    $contrasena = "Admin2026*";          // Cambia esto antes de producción
    $hash      = password_hash($contrasena, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("
        INSERT INTO usuarios (nombre, correo, contrasena_hash, rol, activo)
        VALUES (?, ?, ?, 'administrador', 1)
    ");
    $stmt->execute([$nombre, $correo, $hash]);

    echo json_encode([
        "ok"      => true,
        "mensaje" => "Administrador creado exitosamente.",
        "correo"  => $correo,
        "contrasena_temporal" => $contrasena,
        "aviso"   => "ELIMINA este endpoint después de usarlo."
    ]);
    exit();
}

http_response_code(405);
echo json_encode(["error" => "Método o acción no permitida"]);