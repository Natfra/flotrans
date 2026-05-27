<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$host = "localhost";
$db   = "flotrans";
$user = "root";
$pass = "";

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "error" => "Error conexión DB"
    ]);

    exit();
}

$limit = isset($_GET['limit'])
    ? (int)$_GET['limit']
    : 5;

$alertas = [];

// ============================================
// VEHÍCULOS INACTIVOS
// ============================================

try {

    $stmt = $pdo->prepare("
        SELECT placa
        FROM vehiculos
        WHERE estado != 'Activo'
        LIMIT $limit
    ");

    $stmt->execute();

    $vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($vehiculos as $v) {

        $alertas[] = [
            "tipo"   => "warn",
            "titulo" => "Vehículo fuera de servicio: " . $v['placa'],
            "hace"   => "Hace unos minutos"
        ];
    }

} catch (Exception $e) {}


// ============================================
// RUTAS SIN CONDUCTOR
// ============================================

try {

    $stmt = $pdo->prepare("
        SELECT nombre
        FROM rutas
        WHERE conductor_id IS NULL
        LIMIT $limit
    ");

    $stmt->execute();

    $rutas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rutas as $r) {

        $alertas[] = [
            "tipo"   => "info",
            "titulo" => "Ruta sin conductor asignado: " . $r['nombre'],
            "hace"   => "Pendiente de programación"
        ];
    }

} catch (Exception $e) {}


// ============================================
// SIN ALERTAS
// ============================================

if (count($alertas) === 0) {

    echo json_encode([]);

    exit();
}

// Limitar cantidad final
$alertas = array_slice($alertas, 0, $limit);

echo json_encode($alertas);