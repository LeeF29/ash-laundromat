<?php
header("Content-Type: application/json");
require __DIR__ . "/../admin/session-start.php";
require __DIR__ . "/../admin/db.php";

if (empty($_SESSION["customer_id"])) {
    echo json_encode(["orders" => []]);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT o.order_no, o.weight_kg, o.total_amount, o.status, o.created_at, s.name AS service_name
     FROM orders o
     LEFT JOIN services s ON s.id = o.service_id
     WHERE o.customer_id = ?
     ORDER BY o.created_at DESC
     LIMIT 20"
);
$stmt->execute([$_SESSION["customer_id"]]);
$rows = $stmt->fetchAll();

$statusLabels = [
    "waiting"    => "Waiting",
    "for_pickup" => "For Pickup",
    "received"   => "Received",
    "washing"    => "Washing",
    "drying"     => "Drying",
    "pressing"   => "Pressing & Ironing",
    "ready"      => "Ready",
    "completed"  => "Completed",
];

$orders = array_map(function ($r) use ($statusLabels) {
    return [
        "order_no" => $r["order_no"],
        "service"  => $r["service_name"] ?: "\u2014",
        "weight"   => $r["weight_kg"] !== null ? rtrim(rtrim(number_format((float) $r["weight_kg"], 2), "0"), ".") . " kg" : "\u2014",
        "total"    => (float) $r["total_amount"],
        "status"   => $statusLabels[$r["status"]] ?? ucfirst($r["status"]),
        "date"     => date("M j, Y", strtotime($r["created_at"])),
    ];
}, $rows);

echo json_encode(["orders" => $orders]);