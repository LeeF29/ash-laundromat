<?php
// Public reviews feed for the "What Our Customers Say" section.
// Only APPROVED reviews are returned, and customers are shown as "First L." only.
// If a customer is logged in, it also lists their Completed orders that have no review yet,
// which is what the "Leave a Review" form offers.
header("Content-Type: application/json");
header("Cache-Control: no-store");
require __DIR__ . "/../admin/session-start.php";
require __DIR__ . "/../admin/db.php";

function display_name($fullName) {
    $parts = preg_split('/\s+/', trim((string) $fullName), -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) {
        return "Customer";
    }
    if (count($parts) === 1) {
        return $parts[0];
    }
    return $parts[0] . " " . mb_strtoupper(mb_substr($parts[count($parts) - 1], 0, 1)) . ".";
}

$summary = $pdo->query("SELECT COUNT(*) AS n, AVG(rating) AS avg_rating FROM reviews WHERE status = 'approved'")->fetch();

$rows = $pdo->query(
    "SELECT r.rating, r.comment, c.full_name
     FROM reviews r
     JOIN customers c ON c.id = r.customer_id
     WHERE r.status = 'approved'
     ORDER BY r.created_at DESC, r.id DESC
     LIMIT 30"
)->fetchAll();

$reviews = array_map(function ($r) {
    $name = display_name($r["full_name"]);
    return [
        "rating"  => (int) $r["rating"],
        "comment" => $r["comment"],
        "name"    => $name,
        "initial" => mb_strtoupper(mb_substr($name, 0, 1)),
        "detail"  => "Verified customer",
    ];
}, $rows);

$loggedIn = !empty($_SESSION["customer_id"]);
$eligible = [];

if ($loggedIn) {
    $stmt = $pdo->prepare(
        "SELECT o.id, o.order_no, s.name AS service_name
         FROM orders o
         LEFT JOIN services s ON s.id = o.service_id
         LEFT JOIN reviews r ON r.order_id = o.id
         WHERE o.customer_id = ? AND o.status = 'completed' AND r.id IS NULL
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT 20"
    );
    $stmt->execute([$_SESSION["customer_id"]]);
    foreach ($stmt->fetchAll() as $o) {
        $eligible[] = [
            "id"       => (int) $o["id"],
            "order_no" => $o["order_no"],
            "service"  => $o["service_name"] ?: "",
        ];
    }
}

echo json_encode([
    "summary"         => [
        "count"   => (int) $summary["n"],
        "average" => $summary["n"] > 0 ? round((float) $summary["avg_rating"], 1) : 0,
    ],
    "reviews"         => $reviews,
    "logged_in"       => $loggedIn,
    "eligible_orders" => $eligible,
]);