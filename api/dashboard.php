bash

cat > /mnt/user-data/outputs/dashboard.php << 'ENDOFFILE'
<?php
// =====================================================
// FLOTRANS - API REST (dashboard.php)
// Colocar en: C:\xampp\htdocs\flotrans\api\dashboard.php
// Base de datos: flotrans
// =====================================================

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- CONEXIÓN ---
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
$hoy    = date('Y-m-d');

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"]);
    exit();
}

// =====================================================
// El frontend llama a este archivo con distintos params:
//   ?seccion=kpis         → KPI cards principales
//   ?seccion=ocupacion    → Gráfico de barras (franjas horarias)
//   ?seccion=contratos    → Donut de contratos por tipo
//   ?seccion=rutas_hoy    → Tabla de rutas del día
//   ?seccion=alertas      → Panel de alertas recientes
// =====================================================
$seccion = $_GET['seccion'] ?? 'kpis';

switch ($seccion) {

    // ==================================================
    // KPIs PRINCIPALES
    // Rutas activas hoy, conductores disponibles,
    // vehículos en servicio, conflictos detectados
    // ==================================================
    case 'kpis':

        // -- Rutas activas hoy --
        // Una ruta está "activa hoy" si tiene una asignación para hoy
        $stmtRutasActivas = $pdo->prepare("
            SELECT COUNT(DISTINCT ruta_id) AS total
            FROM asignaciones
            WHERE fecha = :hoy
              AND estado IN ('programada', 'en_curso')
        ");
        $stmtRutasActivas->execute([':hoy' => $hoy]);
        $rutasActivas = (int) $stmtRutasActivas->fetchColumn();

        // Total de rutas programadas (estado activo en tabla rutas)
        $stmtRutasTotal = $pdo->query("
            SELECT COUNT(*) FROM rutas WHERE estado = 'activo'
        ");
        $rutasTotal = (int) $stmtRutasTotal->fetchColumn();

        // -- Conductores disponibles --
        $stmtCondDisp = $pdo->query("
            SELECT COUNT(*) FROM conductores WHERE estado = 'Disponible'
        ");
        $conductoresDisponibles = (int) $stmtCondDisp->fetchColumn();

        $stmtCondTotal = $pdo->query("
            SELECT COUNT(*) FROM conductores
        ");
        $conductoresTotal = (int) $stmtCondTotal->fetchColumn();

        // -- Vehículos en servicio hoy --
        // Vehículos que tienen asignación activa hoy
        $stmtVehServicio = $pdo->prepare("
            SELECT COUNT(DISTINCT vehiculo_id) AS total
            FROM asignaciones
            WHERE fecha = :hoy
              AND estado IN ('programada', 'en_curso')
        ");
        $stmtVehServicio->execute([':hoy' => $hoy]);
        $vehiculosEnServicio = (int) $stmtVehServicio->fetchColumn();

        // Total de vehículos activos
        $stmtVehTotal = $pdo->query("
            SELECT COUNT(*) FROM vehiculos WHERE estado = 'activo'
        ");
        $vehiculosTotal = (int) $stmtVehTotal->fetchColumn();

        // -- Conflictos detectados (no resueltos) --
        // Los conflictos son del sistema de programación (tabla conflictos)
        $stmtConflictos = $pdo->query("
            SELECT COUNT(*) FROM conflictos WHERE evitado = 0
        ");
        $conflictos = (int) $stmtConflictos->fetchColumn();

        echo json_encode([
            'rutas_activas'           => $rutasActivas,
            'rutas_total'             => $rutasTotal,
            'conductores_disponibles' => $conductoresDisponibles,
            'conductores_total'       => $conductoresTotal,
            'vehiculos_en_servicio'   => $vehiculosEnServicio,
            'vehiculos_total'         => $vehiculosTotal,
            'conflictos'              => $conflictos,
        ]);
        break;


    // ==================================================
    // GRÁFICO DE OCUPACIÓN POR FRANJA HORARIA
    // Cuenta asignaciones agrupadas por franja_horaria
    // de la tabla rutas, para la semana en curso
    // ==================================================
    case 'ocupacion':

        $periodo = $_GET['periodo'] ?? 'semana'; // 'semana' | 'mes'

        if ($periodo === 'mes') {
            // Agrupar por semana del mes en curso
            $stmtOcup = $pdo->prepare("
                SELECT
                    CONCAT('S', CEIL(DAY(a.fecha) / 7)) AS label,
                    COUNT(DISTINCT a.vehiculo_id)        AS val
                FROM asignaciones a
                WHERE YEAR(a.fecha)  = YEAR(:hoy)
                  AND MONTH(a.fecha) = MONTH(:hoy)
                  AND a.estado IN ('programada', 'en_curso', 'completada')
                GROUP BY label
                ORDER BY MIN(a.fecha)
            ");
            $stmtOcup->execute([':hoy' => $hoy]);
        } else {
            // Agrupar por día de la semana en curso (lunes a domingo)
            $stmtOcup = $pdo->prepare("
                SELECT
                    ELT(DAYOFWEEK(a.fecha), 'Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb') AS label,
                    COUNT(DISTINCT a.vehiculo_id) AS val
                FROM asignaciones a
                WHERE a.fecha >= DATE_SUB(:hoy, INTERVAL WEEKDAY(:hoy2) DAY)
                  AND a.fecha <= DATE_ADD(:hoy3, INTERVAL (6 - WEEKDAY(:hoy4)) DAY)
                  AND a.estado IN ('programada', 'en_curso', 'completada')
                GROUP BY DAYOFWEEK(a.fecha), label
                ORDER BY DAYOFWEEK(a.fecha)
            ");
            $stmtOcup->execute([
                ':hoy'  => $hoy,
                ':hoy2' => $hoy,
                ':hoy3' => $hoy,
                ':hoy4' => $hoy,
            ]);
        }

        $filas = $stmtOcup->fetchAll();

        // Si no hay datos todavía, devolver estructura vacía con ceros
        if (empty($filas)) {
            if ($periodo === 'mes') {
                $filas = [
                    ['label' => 'S1', 'val' => 0],
                    ['label' => 'S2', 'val' => 0],
                    ['label' => 'S3', 'val' => 0],
                    ['label' => 'S4', 'val' => 0],
                ];
            } else {
                $filas = [
                    ['label' => 'Lun', 'val' => 0],
                    ['label' => 'Mar', 'val' => 0],
                    ['label' => 'Mié', 'val' => 0],
                    ['label' => 'Jue', 'val' => 0],
                    ['label' => 'Vie', 'val' => 0],
                    ['label' => 'Sáb', 'val' => 0],
                    ['label' => 'Dom', 'val' => 0],
                ];
            }
        }

        // Castear val a int
        $filas = array_map(function($f) {
            return ['label' => $f['label'], 'val' => (int)$f['val']];
        }, $filas);

        echo json_encode($filas);
        break;


    // ==================================================
    // DONUT — CONTRATOS POR TIPO
    // Agrupa contratos activos por campo 'prioridad'
    // (Alta → Educativo, Media → Industrial, Baja → Urbano)
    // Ajusta la lógica según tu categorización real
    // ==================================================
    case 'contratos':

        $stmtContratos = $pdo->query("
            SELECT
                prioridad      AS tipo_raw,
                COUNT(*)       AS cantidad
            FROM contratos
            WHERE estado = 'activo'
            GROUP BY prioridad
            ORDER BY cantidad DESC
        ");
        $filas = $stmtContratos->fetchAll();

        $stmtTotal = $pdo->query("
            SELECT COUNT(*) FROM contratos WHERE estado = 'activo'
        ");
        $total = (int) $stmtTotal->fetchColumn();

        // Mapear prioridad → etiqueta visual y color
        $colores = [
            'alta'   => ['label' => 'Alta prioridad',   'color' => '#1a5c2a'],
            'media'  => ['label' => 'Media prioridad',  'color' => '#4caf70'],
            'baja'   => ['label' => 'Baja prioridad',   'color' => '#a8d5b5'],
        ];

        $tipos = [];
        foreach ($filas as $fila) {
            $key    = strtolower(trim($fila['tipo_raw'] ?? 'baja'));
            $config = $colores[$key] ?? ['label' => ucfirst($key), 'color' => '#cccccc'];
            $pct    = $total > 0 ? round(($fila['cantidad'] / $total) * 100) : 0;

            $tipos[] = [
                'label'    => $config['label'],
                'color'    => $config['color'],
                'pct'      => $pct,
                'cantidad' => (int) $fila['cantidad'],
            ];
        }

        echo json_encode([
            'total' => $total,
            'tipos' => $tipos,
        ]);
        break;


    // ==================================================
    // RUTAS DEL DÍA
    // Trae las asignaciones de hoy con nombre de ruta,
    // conductor y placa del vehículo
    // ==================================================
    case 'rutas_hoy':

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 6;

        $stmtRutas = $pdo->prepare("
            SELECT
                r.nombre                              AS nombre,
                c.nombre                              AS conductor,
                v.placa                               AS placa,
                a.estado                              AS estado_asignacion,
                r.hora_inicio                         AS hora_inicio,
                r.hora_fin                            AS hora_fin
            FROM asignaciones a
            JOIN rutas      r ON a.ruta_id      = r.id
            LEFT JOIN conductores c ON a.conductor_id = c.id
            LEFT JOIN vehiculos   v ON a.vehiculo_id  = v.id
            WHERE a.fecha = :hoy
            ORDER BY r.hora_inicio ASC
            LIMIT :lim
        ");
        $stmtRutas->bindValue(':hoy', $hoy, PDO::PARAM_STR);
        $stmtRutas->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmtRutas->execute();

        echo json_encode($stmtRutas->fetchAll());
        break;


    // ==================================================
    // ALERTAS RECIENTES
    // Combina 3 fuentes:
    //   1. Conflictos no resueltos (tabla conflictos)
    //   2. Documentos por vencer o vencidos (SOAT/Tecno de vehículos)
    //   3. Novedades abiertas recientes (tabla novedades)
    // ==================================================
    case 'alertas':

        $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
        $alertas = [];

        // --- 1. Conflictos sin resolver ---
        $stmtConf = $pdo->query("
            SELECT
                'error'                          AS tipo,
                CONCAT(
                    CASE c.tipo
                        WHEN 'jornada'   THEN 'Exceso de jornada — '
                        WHEN 'soat'      THEN 'SOAT vencido — '
                        WHEN 'tecno'     THEN 'Tecnomecánica vencida — '
                        WHEN 'descanso'  THEN 'Descanso insuficiente — '
                        ELSE CONCAT(c.tipo, ' — ')
                    END,
                    COALESCE(co.nombre, ''),
                    CASE WHEN v.placa IS NOT NULL THEN CONCAT(' / Veh. ', v.placa) ELSE '' END
                )                                AS titulo,
                CONCAT(
                    'Hace ',
                    TIMESTAMPDIFF(MINUTE, c.id, NOW()),
                    ' min'
                )                               AS hace,
                c.id                            AS orden
            FROM conflictos c
            LEFT JOIN conductores co ON c.conductor_id = co.id
            LEFT JOIN vehiculos    v  ON c.vehiculo_id  = v.id
            WHERE c.evitado = 0
            ORDER BY c.id DESC
            LIMIT 3
        ");
        foreach ($stmtConf->fetchAll() as $row) {
            $alertas[] = $row;
        }

        // --- 2. Documentos de vehículos por vencer (próximos 30 días) o ya vencidos ---
        $stmtDocs = $pdo->query("
            SELECT
                CASE
                    WHEN fecha_soat < CURDATE()
                         OR fecha_tecnomecanica < CURDATE()          THEN 'error'
                    ELSE 'warn'
                END                                                  AS tipo,
                CONCAT(
                    CASE
                        WHEN fecha_soat < CURDATE()                  THEN 'SOAT vencido'
                        WHEN fecha_tecnomecanica < CURDATE()         THEN 'Tecnomecánica vencida'
                        WHEN fecha_soat <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN
                             CONCAT('SOAT por vencer (', DATEDIFF(fecha_soat, CURDATE()), ' días)')
                        ELSE CONCAT('Tecnomecánica por vencer (',
                             DATEDIFF(fecha_tecnomecanica, CURDATE()), ' días)')
                    END,
                    ' — Veh. ', placa
                )                                                    AS titulo,
                'Documento'                                          AS hace,
                id                                                   AS orden
            FROM vehiculos
            WHERE estado = 'activo'
              AND (
                fecha_soat          <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                OR fecha_tecnomecanica <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
              )
            ORDER BY LEAST(
                COALESCE(fecha_soat, '9999-12-31'),
                COALESCE(fecha_tecnomecanica, '9999-12-31')
            ) ASC
            LIMIT 3
        ");
        foreach ($stmtDocs->fetchAll() as $row) {
            $alertas[] = $row;
        }

        // --- 3. Novedades abiertas recientes ---
        $stmtNov = $pdo->query("
            SELECT
                'warn'                                              AS tipo,
                CONCAT(
                    CASE n.tipo
                        WHEN 'retraso'   THEN 'Retraso reportado — '
                        WHEN 'accidente' THEN 'Accidente reportado — '
                        WHEN 'falla'     THEN 'Falla mecánica — '
                        ELSE CONCAT(n.tipo, ' — ')
                    END,
                    r.nombre
                )                                                   AS titulo,
                CONCAT(
                    'Hace ',
                    TIMESTAMPDIFF(HOUR, a.fecha, NOW()),
                    'h'
                )                                                   AS hace,
                n.id                                                AS orden
            FROM novedades n
            JOIN asignaciones a ON n.asignacion_id = a.id
            JOIN rutas        r ON a.ruta_id       = r.id
            WHERE n.estado_resolucion = 'abierto'
            ORDER BY n.id DESC
            LIMIT 3
        ");
        foreach ($stmtNov->fetchAll() as $row) {
            $alertas[] = $row;
        }

        // Ordenar por id desc y limitar al total pedido
        usort($alertas, fn($a, $b) => $b['orden'] - $a['orden']);
        $alertas = array_slice($alertas, 0, $limit);

        // Limpiar campo interno 'orden' antes de enviar
        $alertas = array_map(function($a) {
            unset($a['orden']);
            return $a;
        }, $alertas);

        echo json_encode(array_values($alertas));
        break;


    default:
        http_response_code(400);
        echo json_encode(["error" => "Sección no válida. Usa: kpis, ocupacion, contratos, rutas_hoy, alertas"]);
        break;
}
ENDOFFILE
echo "dashboard.php created"