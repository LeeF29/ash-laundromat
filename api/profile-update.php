<?php
header("Content-Type: application/json");
require __DIR__ . "/../admin/session-start.php";
require __DIR__ . "/../admin/db.php";

function fail($message) {
    http_response_code(400);
    echo json_encode(["error" => $message]);
    exit;
}

if (empty($_SESSION["customer_id"])) {
    fail("Please log in.");
}

$raw = json_decode(file_get_contents("php://input"), true);
if (!is_array($raw)) {
    fail("Invalid request body.");
}

if (empty($_SESSION["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $raw["csrf_token"] ?? "")) {
    fail("Your session expired. Please reload the page and try again.");
}

$fullName      = trim($raw["full_name"] ?? "");
$phone         = trim($raw["phone"] ?? "");
$address       = trim($raw["address"] ?? "");
$paymentMethod = trim($raw["payment_method"] ?? "");

if (mb_strlen($fullName) < 6 || mb_strlen($fullName) > 100) {
    fail("Please enter your full name (at least 6 characters).");
}

if (!preg_match('/^(09|\+639)[0-9]{9}$/', $phone)) {
    fail("Please enter a valid phone number (e.g. 09xxxxxxxxx).");
}

if (mb_strlen($address) < 10 || mb_strlen($address) > 300) {
    fail("Please enter your full address.");
}

if (!in_array($paymentMethod, ["Cash", "GCash", "Maya", "Card"], true)) {
    fail("Please choose a payment method.");
}

$stmt = $pdo->prepare(
    "SELECT id FROM customers WHERE password_hash IS NOT NULL AND contact_number = ? AND id != ?"
);
$stmt->execute([$phone, $_SESSION["customer_id"]]);
if ($stmt->fetch()) {
    fail("That phone number is already used by another account.");
}

$stmt = $pdo->prepare(
    "UPDATE customers SET
        pending_full_name = ?,
        pending_contact_number = ?,
        pending_address = ?,
        pending_payment_method = ?,
        pending_requested_at = NOW(),
        pending_apply_at = NOW() + INTERVAL 48 HOUR
     WHERE id = ?"
);
$stmt->execute([$fullName, $phone, $address, $paymentMethod, $_SESSION["customer_id"]]);

echo json_encode(["success" => true]);