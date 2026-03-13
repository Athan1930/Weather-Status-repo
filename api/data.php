<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-Key");

// Handle preflight OPTIONS request (for CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── API KEY PROTECTION ─────────────────────────────────────
// Change this to any secret string. Must match your Arduino code.
define('API_KEY', 'ucv-wsn-secret-2025');

// ── DB CONFIG ──────────────────────────────────────────────
// Reads from Railway environment variables automatically.
// Falls back to localhost for local testing.
$host = getenv('MYSQLHOST')     ?: 'localhost';
$user = getenv('MYSQLUSER')     ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$db   = getenv('MYSQLDATABASE') ?: 'wsn_weather';
$port = intval(getenv('MYSQLPORT') ?: 3306);

$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "DB connection failed: " . $conn->connect_error]);
    exit;
}
$conn->set_charset("utf8mb4");

// ── AUTO-CREATE TABLE IF NOT EXISTS ───────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS sensor_data (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        node_id      VARCHAR(20)  NOT NULL DEFAULT 'NODE-01',
        location     VARCHAR(100) DEFAULT NULL,
        temperature  FLOAT        NOT NULL DEFAULT 0,
        humidity     FLOAT        NOT NULL DEFAULT 0,
        wind_speed   FLOAT        NOT NULL DEFAULT 0,
        rain         FLOAT        NOT NULL DEFAULT 0,
        total_rain   FLOAT        NOT NULL DEFAULT 0,
        recorded_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_recorded_at (recorded_at),
        INDEX idx_node_id (node_id)
    )
");

// ── HELPER: Send JSON error and exit ──────────────────────
function jsonError(int $code, string $message): void {
    http_response_code($code);
    echo json_encode(["error" => $message]);
    exit;
}

// ══════════════════════════════════════════════════════════
//  POST — Arduino sends sensor data
// ══════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── API Key check ──────────────────────────────────────
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($apiKey !== API_KEY) {
        jsonError(401, "Unauthorized — invalid or missing API key");
    }

    // ── Parse JSON body ────────────────────────────────────
    $body = json_decode(file_get_contents("php://input"), true);
    if (!$body || !is_array($body)) {
        jsonError(400, "Invalid or empty JSON body");
    }

    // ── Sanitize & validate inputs ─────────────────────────
    $node_id  = substr($conn->real_escape_string($body['node_id']  ?? 'NODE-01'), 0, 20);
    $location = substr($conn->real_escape_string($body['location'] ?? ''),        0, 100);
    $temp     = floatval($body['temperature'] ?? 0);
    $hum      = floatval($body['humidity']    ?? 0);
    $wind     = floatval($body['wind_speed']  ?? 0);
    $rain     = floatval($body['rain']        ?? 0);
    $total    = floatval($body['total_rain']  ?? 0);

    // Sanity-check sensor ranges (reject physically impossible values)
    if ($temp  < -40  || $temp  > 85)  $temp  = 0.0;
    if ($hum   < 0    || $hum   > 100) $hum   = 0.0;
    if ($wind  < 0    || $wind  > 400) $wind  = 0.0;
    if ($rain  < 0    || $rain  > 500) $rain  = 0.0;
    if ($total < 0)                    $total = 0.0;

    // ── Insert into DB ─────────────────────────────────────
    $stmt = $conn->prepare("
        INSERT INTO sensor_data (node_id, location, temperature, humidity, wind_speed, rain, total_rain)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssddddd", $node_id, $location, $temp, $hum, $wind, $rain, $total);

    if ($stmt->execute()) {
        echo json_encode([
            "status"      => "ok",
            "id"          => $stmt->insert_id,
            "node_id"     => $node_id,
            "temperature" => $temp,
            "humidity"    => $hum,
            "wind_speed"  => $wind,
            "rain"        => $rain,
            "total_rain"  => $total
        ]);
    } else {
        jsonError(500, "Insert failed: " . $stmt->error);
    }

    $stmt->close();

// ══════════════════════════════════════════════════════════
//  GET — Dashboard fetches data
// ══════════════════════════════════════════════════════════
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // ── Sanitize rows param (1–1000) ───────────────────────
    $rows = max(1, min(1000, intval($_GET['rows'] ?? 60)));

    // ── Whitelist range param ──────────────────────────────
    $rangeMap = [
        'live'  => 'AND recorded_at >= NOW() - INTERVAL 2 HOUR',
        'today' => 'AND DATE(recorded_at) = CURDATE()',
        '3days' => 'AND recorded_at >= NOW() - INTERVAL 3 DAY',
        'week'  => 'AND recorded_at >= NOW() - INTERVAL 7 DAY',
        'month' => 'AND recorded_at >= NOW() - INTERVAL 30 DAY',
    ];
    $range       = $_GET['range'] ?? 'live';
    $whereClause = $rangeMap[$range] ?? $rangeMap['live'];

    // ── rows=1: return only the latest single reading ──────
    if ($rows === 1) {
        $result = $conn->query("
            SELECT * FROM sensor_data
            ORDER BY recorded_at DESC
            LIMIT 1
        ");
        if (!$result) {
            jsonError(500, "Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        echo json_encode($row ?: new stdClass());

    // ── rows>1: return historical data for charts/table ────
    } else {
        $stmt = $conn->prepare("
            SELECT * FROM sensor_data
            WHERE 1=1 $whereClause
            ORDER BY recorded_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("i", $rows);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$result) {
            jsonError(500, "Query failed: " . $conn->error);
        }

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        echo json_encode(array_reverse($data));
        $stmt->close();
    }

} else {
    jsonError(405, "Method not allowed");
}

$conn->close();
?>
