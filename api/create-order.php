<?php
header("Content-Type: application/json");
require __DIR__ . "/../admin/session-start.php";
require __DIR__ . "/../admin/db.php";
require __DIR__ . "/../admin/order-number.php";

function fail($message) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => $message]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    fail("Invalid request.");
}

$raw = json_decode(file_get_contents("php://input"), true);
if (!is_array($raw)) {
    fail("Invalid request body.");
}

if (empty($_SESSION["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $raw["csrf_token"] ?? "")) {
    fail("Your session expired. Please reload the page and try again.");
}

if (empty($_SESSION["customer_id"])) {
    fail("Please log in to create an order.");
}

$customerId = (int) $_SESSION["customer_id"];

$stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ?");
$stmt->execute([$customerId]);
if (!$stmt->fetch()) {
    fail("Your session is invalid. Please log in again.");
}

$serviceId  = (int) ($raw["serviceId"] ?? 0);
$weight     = (float) ($raw["weight"] ?? 0);
$addPress   = !empty($raw["addPress"]);
$addPickup  = !empty($raw["addPickup"]);
$pickupDate = trim($raw["pickupDate"] ?? "");
$items      = is_array($raw["items"] ?? null) ? $raw["items"] : [];

$stmt = $pdo->prepare(
    "SELECT * FROM services WHERE id = ? AND category = 'laundry' AND is_active = 1"
);
$stmt->execute([$serviceId]);
$service = $stmt->fetch();

if (!$service) {
    fail("Please select a valid service.");
}

if ($weight < 0.5 || $weight > 100) {
    fail("Please enter a valid weight (0.5 to 100 kg).");
}

if ($addPickup) {
    if ($pickupDate === "" || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $pickupDate)) {
        fail("Please choose a pickup date.");
    }
}

$press = null;
$pickup = null;

if ($addPress || $addPickup) {
    $stmt = $pdo->query("SELECT * FROM services WHERE category = 'addon' AND is_active = 1");
    foreach ($stmt->fetchAll() as $row) {
        if ($row["slug"] === "press_ironing") $press = $row;
        if ($row["slug"] === "pickup_delivery") $pickup = $row;
    }
}

if ($addPress && !$press) {
    fail("Press & Ironing isn't available right now.");
}
if ($addPickup && !$pickup) {
    fail("Pickup & Delivery isn't available right now.");
}

$validatedItems = [];

foreach ($items as $item) {

    $category = $item["category"] ?? "";
    $itemId   = (int) ($item["id"] ?? 0);
    $qty      = (float) ($item["qty"] ?? 0);

    if (!in_array($category, ["bedding", "footwear", "special"], true)) {
        fail("Invalid item category.");
    }

    if ($qty <= 0 || $qty > 100) {
        fail("Invalid item quantity.");
    }

    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ? AND category = ? AND is_active = 1");
    $stmt->execute([$itemId, $category]);
    $row = $stmt->fetch();

    if (!$row) {
        fail("One of the added items is no longer available.");
    }

    $unitLabel = $row["pricing_type"] === "per_pair"
        ? ($qty == 1 ? " pair" : " pairs")
        : ($row["pricing_type"] === "per_piece" ? " pc" : "kg");

    $validatedItems[] = [
        "category" => $category,
        "name"     => $row["name"],
        "detail"   => $qty . $unitLabel,
        "price"    => round($row["price"] * $qty, 2),
    ];

}

$total = round($service["price"] * $weight, 2);

if ($addPress) {
    $total += round($press["price"] * $weight, 2);
}
if ($addPickup) {
    $total += round($pickup["price"], 2);
}
foreach ($validatedItems as $item) {
    $total += $item["price"];
}

try {

    $pdo->beginTransaction();

    $placeholder = "T" . bin2hex(random_bytes(6));

    $stmt = $pdo->prepare(
        "INSERT INTO orders
            (order_no, customer_id, service_id, weight_kg, press_ironing, pickup_delivery, pickup_date, total_amount, payment_status, status)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', 'received')"
    );
    $stmt->execute([
        $placeholder,
        $customerId,
        $service["id"],
        $weight,
        $addPress ? 1 : 0,
        $addPickup ? 1 : 0,
        $addPickup ? $pickupDate : null,
        $total,
    ]);

    $orderId = (int) $pdo->lastInsertId();
    $orderNo = generate_order_no($pdo);

    $stmt = $pdo->prepare("UPDATE orders SET order_no = ? WHERE id = ?");
    $stmt->execute([$orderNo, $orderId]);

    if ($validatedItems) {
        $stmt = $pdo->prepare(
            "INSERT INTO order_addon_items (order_id, category, item_name, detail, price) VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($validatedItems as $item) {
            $stmt->execute([$orderId, $item["category"], $item["name"], $item["detail"], $item["price"]]);
        }
    }

    $pdo->commit();

} catch (PDOException $e) {
    $pdo->rollBack();
    fail("Something went wrong saving your order. Please try again.");
}

echo json_encode([
    "success"  => true,
    "order_no" => $orderNo,
    "total"    => $total,
]);