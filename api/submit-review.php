<?php
// A logged-in customer reviews one of their own Completed orders.
// The review is saved as 'pending' and is not shown on the website until an admin approves it.
header("Content-Type: application/json");
require __DIR__ . "/../admin/session-start.php";
require __DIR__ . "/../admin/db.php";

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

if (empty($_SESSION["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], (string) ($raw["csrf_token"] ?? ""))) {
    fail("Your session expired. Please reload the page and try again.");
}

if (empty($_SESSION["customer_id"])) {
    fail("Please log in to leave a review.");
}

$customerId = (int) $_SESSION["customer_id"];
$orderId    = (int) ($raw["order_id"] ?? 0);
$rating     = is_numeric($raw["rating"] ?? null) ? (int) $raw["rating"] : 0;
$comment    = trim((string) ($raw["comment"] ?? ""));

if ($rating < 1 || $rating > 5) {
    fail("Please choose a star rating from 1 to 5.");
}
if (mb_strlen($comment) < 5 || mb_strlen($comment) > 500) {
    fail("Please write a review between 5 and 500 characters.");
}

// the order must be this customer's own, and Completed
$stmt = $pdo->prepare("SELECT id, status FROM orders WHERE id = ? AND customer_id = ?");
$stmt->execute([$orderId, $customerId]);
$order = $stmt->fetch();

if (!$order) {
    fail("We couldn't find that order on your account.");
}
if ($order["status"] !== "completed") {
    fail("You can review an order once it is Completed.");
}

$stmt = $pdo->prepare("SELECT id FROM reviews WHERE order_id = ?");
$stmt->execute([$orderId]);
if ($stmt->fetch()) {
    fail("You have already reviewed this order.");
}

try {
    $stmt = $pdo->prepare("INSERT INTO reviews (order_id, customer_id, rating, comment, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->execute([$orderId, $customerId, $rating, $comment]);
} catch (PDOException $e) {
    fail("We couldn't save your review. Please try again.");
}

echo json_encode([
    "success" => true,
    "message" => "Thank you! Your review was submitted and will appear on the website once we approve it.",
]);