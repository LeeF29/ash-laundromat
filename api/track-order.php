<?php
// Public Track Order lookup: order number in, status out.
// Read-only. It never returns the customer's name or contact details.
header("Content-Type: application/json");
header("Cache-Control: no-store");
require __DIR__ . "/../admin/db.php";
require __DIR__ . "/../admin/client-ip.php";

$rawOrderNo = $_GET["order_no"] ?? "";
$orderNo = is_string($rawOrderNo) ? strtoupper(trim($rawOrderNo)) : "";

// real order numbers look like ASH-1001; anything else can't exist
if (!preg_match('/^ASH-[0-9]{1,12}$/', $orderNo)) {
    echo json_encode(["found" => false]);
    exit;
}

// ---- Lookup limit: stops someone from trying order numbers one after another ----
// Needs the track_lookups table (add-track-lookups-table.sql). If that table doesn't exist yet,
// the limit is skipped so Track Order keeps working for customers, and a note goes to the PHP error log.
$LIMIT_PER_5_MIN = 15;   // lookups one visitor can make in 5 minutes
$LIMIT_PER_HOUR  = 60;   // ...and in one hour

// Only a one-way hash of the visitor's address is kept, just to count their recent lookups
$ipHash = hash("sha256", client_ip() . "|ash-track-lookup");

try {

    // Now and then, remove log rows older than a day so the table stays small
    if (random_int(1, 50) === 1) {
        $pdo->exec("DELETE FROM track_lookups WHERE TIMESTAMPDIFF(SECOND, created_at, NOW()) > 86400");
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS hour_count,
                COALESCE(SUM(CASE WHEN TIMESTAMPDIFF(SECOND, created_at, NOW()) < 300 THEN 1 ELSE 0 END), 0) AS recent_count
         FROM track_lookups
         WHERE ip_hash = ? AND TIMESTAMPDIFF(SECOND, created_at, NOW()) < 3600"
    );
    $stmt->execute([$ipHash]);
    $usage = $stmt->fetch();

    if ((int) $usage["recent_count"] >= $LIMIT_PER_5_MIN || (int) $usage["hour_count"] >= $LIMIT_PER_HOUR) {
        http_response_code(429);
        header("Retry-After: 300");
        echo json_encode([
            "found"   => false,
            "limited" => true,
            "message" => "Too many lookups in a short time. Please wait a few minutes and try again.",
        ]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO track_lookups (ip_hash) VALUES (?)");
    $stmt->execute([$ipHash]);

} catch (PDOException $e) {
    error_log("track-order.php (lookup limit skipped): " . $e->getMessage());
}

$stmt = $pdo->prepare(
    "SELECT o.order_no, o.weight_kg, o.status, o.payment_status, o.pickup_delivery, o.press_ironing, s.name AS service_name
     FROM orders o
     LEFT JOIN services s ON s.id = o.service_id
     WHERE o.order_no = ?
     LIMIT 1"
);
$stmt->execute([$orderNo]);
$order = $stmt->fetch();

if (!$order) {
    echo json_encode(["found" => false]);
    exit;
}

echo json_encode([
    "found"          => true,
    "order_no"       => $order["order_no"],
    "service"        => $order["service_name"] ?: "",
    "weight"         => $order["weight_kg"] !== null ? (float) $order["weight_kg"] : null,
    "status"         => $order["status"],
    "payment_status" => $order["payment_status"],
    "pickup"         => (int) $order["pickup_delivery"] === 1,
    "press"          => (int) $order["press_ironing"] === 1,
]);