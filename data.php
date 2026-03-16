<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-Key");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

define('API_KEY', 'ucv-wsn-secret-2025');

// PostgreSQL connection using Railway env vars
$dbUrl = getenv('DATABASE_URL');
if (!$dbUrl) {
    http_response_code(500);
    echo json_encode(["error" => "No DATABASE_URL set"]);
    exit;
}

try {
    $pdo = new PDO($dbUrl, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "DB connection failed: " . $e->getMessage()]);
    exit;
}

// Auto-create table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS sensor_data (
        id           SERIAL PRIMARY KEY,
        node_id      VARCHAR(20)  NOT NULL DEFAULT 'NODE-01',
        location     VARCHAR(100) DEFAULT NULL,
        temperature  FLOAT        NOT NULL DEFAULT 0,
        humidity     FLOAT        NOT NULL DEFAULT 0,
        wind_speed   FLOAT        NOT NULL DEFAULT 0,
        rain         FLOAT        NOT NULL DEFAULT 0,
        total_rain   FLOAT        NOT NULL DEFAULT 0,
        recorded_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    )
");

function jsonError(int $code, string $message): void {
    http_response_code($code);
    echo json_encode(["error" => $message]);
    exit;
}

// ── POST: Arduino sends data ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($apiKey !== API_KEY) {
        jsonError(401, "Unauthorized");
    }

    $body = json_decode(file_get_contents("php://input"), true);
    if (!$body || !is_array($body)) {
        jsonError(400, "Invalid JSON");
    }

    $node_id  = substr($body['node_id']  ?? 'NODE-01', 0, 20);
    $location = substr($body['location'] ?? '', 0, 100);
    $temp     = floatval($body['temperature'] ?? 0);
    $hum      = floatval($body['humidity']    ?? 0);
    $wind     = floatval($body['wind_speed']  ?? 0);
    $rain     = floatval($body['rain']        ?? 0);
    $total    = floatval($body['total_rain']  ?? 0);

    if ($temp  < -40  || $temp  > 85)  $temp  = 0.0;
    if ($hum   < 0    || $hum   > 100) $hum   = 0.0;
    if ($wind  < 0    || $wind  > 400) $wind  = 0.0;
    if ($rain  < 0    || $rain  > 500) $rain  = 0.0;
    if ($total < 0)                    $total = 0.0;

    $stmt = $pdo->prepare("
        INSERT INTO sensor_data (node_id, location, temperature, humidity, wind_speed, rain, total_rain)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$node_id, $location, $temp, $hum, $wind, $rain, $total]);

    echo json_encode([
        "status"      => "ok",
        "id"          => $pdo->lastInsertId(),
        "node_id"     => $node_id,
        "temperature" => $temp,
        "humidity"    => $hum,
        "wind_speed"  => $wind,
        "rain"        => $rain,
        "total_rain"  => $total
    ]);

// ── GET: Dashboard fetches data ───────────────
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $rows = max(1, min(1000, intval($_GET['rows'] ?? 60)));

    $rangeMap = [
        'live'  => "AND recorded_at >= NOW() - INTERVAL '2 hours'",
        'today' => "AND recorded_at::date = CURRENT_DATE",
        '3days' => "AND recorded_at >= NOW() - INTERVAL '3 days'",
        'week'  => "AND recorded_at >= NOW() - INTERVAL '7 days'",
        'month' => "AND recorded_at >= NOW() - INTERVAL '30 days'",
    ];
    $range       = $_GET['range'] ?? 'live';
    $whereClause = $rangeMap[$range] ?? $rangeMap['live'];

    if ($rows === 1) {
        $stmt = $pdo->query("SELECT * FROM sensor_data ORDER BY recorded_at DESC LIMIT 1");
        $row  = $stmt->fetch();
        echo json_encode($row ?: new stdClass());
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM sensor_data
            WHERE 1=1 $whereClause
            ORDER BY recorded_at DESC
            LIMIT ?
        ");
        $stmt->execute([$rows]);
        $data = $stmt->fetchAll();
        echo json_encode(array_reverse($data));
    }

} else {
    jsonError(405, "Method not allowed");
}
?>
