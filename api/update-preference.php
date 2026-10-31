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

$pickupDay  = trim($raw["pickup_day"] ?? "");
$pickupTime = trim($raw["pickup_time"] ?? "");

$validDays  = ["", "Any day", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
$validTimes = ["", "Morning", "Afternoon", "Evening"];

if (!in_array($pickupDay, $validDays, true) || !in_array($pickupTime, $validTimes, true)) {
    fail("Invalid pickup preference.");
}

$stmt = $pdo->prepare("UPDATE customers SET pickup_day = ?, pickup_time = ? WHERE id = ?");
$stmt->execute([$pickupDay ?: null, $pickupTime ?: null, $_SESSION["customer_id"]]);

echo json_encode(["success" => true]);