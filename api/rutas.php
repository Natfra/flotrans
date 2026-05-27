<?php
// =====================================================
// FLOTRANS - API REST RUTAS
// Archivo: rutas.php
// Ubicación:
// C:\xampp\htdocs\flotrans\api\rutas.php
// =====================================================

// HEADERS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// PREVENT OPTIONS ERROR
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// =====================================================
// CONEXIÓN A BASE DE DATOS
// =====================================================

$host     = "localhost";
$dbname   = "flotrans";
$user     = "root";
$password = "";

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "error" => "Error de conexión: " . $e->getMessage()
    ]);

    exit();
}

// =====================================================
// VARIABLES GENERALES
// =====================================================

$method = $_SERVER['REQUEST_METHOD'];

$input = json_decode(file_get_contents("php://input"), true);

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

// =====================================================
// GET - LISTAR RUTAS
// =====================================================

if ($method === 'GET') {

    $where  = [];
    $params = [];

    // BUSCADOR
    if (!empty($_GET['buscar'])) {

        $where[] = "r.nombre LIKE ?";

        $params[] = "%" . $_GET['buscar'] . "%";
    }

    // FILTRO TIPO
    if (!empty($_GET['tipo'])) {

        $where[] = "r.tipo = ?";

        $params[] = $_GET['tipo'];
    }

    // FILTRO ESTADO
    if (!empty($_GET['estado'])) {

        $where[] = "r.estado = ?";

        $params[] = $_GET['estado'];
    }

    $sql = "
        SELECT 
            r.*,
            c.codigo AS contrato_codigo
        FROM rutas r
        LEFT JOIN contratos c 
            ON r.contrato_id = c.id
    ";

    if ($where) {

        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY r.id DESC";

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $rutas = $stmt->fetchAll();

    echo json_encode($rutas);

    exit();
}

// =====================================================
// POST - CREAR RUTA
// =====================================================

if ($method === 'POST') {

    $required = [
        'contrato_id',
        'nombre',
        'franja_horaria',
        'hora_inicio',
        'hora_fin',
        'dias_semana',
        'tipo'
    ];

    foreach ($required as $field) {

        if (empty($input[$field])) {

            http_response_code(400);

            echo json_encode([
                "error" => "El campo '$field' es obligatorio"
            ]);

            exit();
        }
    }

    try {

        $stmt = $pdo->prepare("
            INSERT INTO rutas (
                contrato_id,
                nombre,
                descripcion,
                franja_horaria,
                hora_inicio,
                hora_fin,
                dias_semana,
                tipo,
                estado
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $input['contrato_id'],
            trim($input['nombre']),
            $input['descripcion'] ?? null,
            $input['franja_horaria'],
            $input['hora_inicio'],
            $input['hora_fin'],
            $input['dias_semana'],
            $input['tipo'],
            $input['estado'] ?? 'activa'
        ]);

        $nuevoId = $pdo->lastInsertId();

        $consulta = $pdo->prepare("
            SELECT 
                r.*,
                c.codigo AS contrato_codigo
            FROM rutas r
            LEFT JOIN contratos c
                ON r.contrato_id = c.id
            WHERE r.id = ?
        ");

        $consulta->execute([$nuevoId]);

        $ruta = $consulta->fetch();

        http_response_code(201);

        echo json_encode([
            "ok" => true,
            "mensaje" => "Ruta creada correctamente",
            "ruta" => $ruta
        ]);

    } catch (PDOException $e) {

        http_response_code(500);

        echo json_encode([
            "error" => $e->getMessage()
        ]);
    }

    exit();
}

// =====================================================
// PUT - EDITAR RUTA
// =====================================================

if ($method === 'PUT' && $id) {

    try {

        $stmt = $pdo->prepare("
            UPDATE rutas SET
                contrato_id = ?,
                nombre = ?,
                descripcion = ?,
                franja_horaria = ?,
                hora_inicio = ?,
                hora_fin = ?,
                dias_semana = ?,
                tipo = ?,
                estado = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $input['contrato_id'],
            trim($input['nombre']),
            $input['descripcion'] ?? null,
            $input['franja_horaria'],
            $input['hora_inicio'],
            $input['hora_fin'],
            $input['dias_semana'],
            $input['tipo'],
            $input['estado'],
            $id
        ]);

        echo json_encode([
            "ok" => true,
            "mensaje" => "Ruta actualizada correctamente"
        ]);

    } catch (PDOException $e) {

        http_response_code(500);

        echo json_encode([
            "error" => $e->getMessage()
        ]);
    }

    exit();
}

// =====================================================
// DELETE - ELIMINAR RUTA
// =====================================================

if ($method === 'DELETE' && $id) {

    try {

        $stmt = $pdo->prepare("
            DELETE FROM rutas
            WHERE id = ?
        ");

        $stmt->execute([$id]);

        echo json_encode([
            "ok" => true,
            "mensaje" => "Ruta eliminada correctamente"
        ]);

    } catch (PDOException $e) {

        http_response_code(500);

        echo json_encode([
            "error" => $e->getMessage()
        ]);
    }

    exit();
}

// =====================================================
// MÉTODO NO PERMITIDO
// =====================================================

http_response_code(405);

echo json_encode([
    "error" => "Método no permitido"
]);
?>