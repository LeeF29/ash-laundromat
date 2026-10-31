<?php
header("Content-Type: application/json");
require __DIR__ . "/../admin/session-start.php";
require __DIR__ . "/../admin/db.php";

function fail($message) {
    http_response_code(400);
    echo json_encode(["error" => $message]);
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

$identifier = trim($raw["identifier"] ?? "");
$password   = $raw["password"] ?? "";

if ($identifier === "" || $password === "") {
    fail("Please enter your phone/email and password.");
}

$MAX_ATTEMPTS    = 5;
$LOCKOUT_MINUTES = 15;

$stmt = $pdo->prepare(
    "SELECT * FROM customers WHERE password_hash IS NOT NULL AND (email = ? OR contact_number = ?) LIMIT 1"
);
$stmt->execute([$identifier, $identifier]);
$customer = $stmt->fetch();

if ($customer && $customer["locked_until"] && strtotime($customer["locked_until"]) > time()) {
    $minutesLeft = (int) ceil((strtotime($customer["locked_until"]) - time()) / 60);
    fail("Too many failed attempts. Try again in {$minutesLeft} minute" . ($minutesLeft === 1 ? "" : "s") . ".");
}

if (!$customer || !password_verify($password, $customer["password_hash"])) {

    if ($customer) {
        $attempts = $customer["failed_attempts"] + 1;

        if ($attempts >= $MAX_ATTEMPTS) {
            $lockUntil = date("Y-m-d H:i:s", time() + $LOCKOUT_MINUTES * 60);
            $upd = $pdo->prepare("UPDATE customers SET failed_attempts = ?, locked_until = ? WHERE id = ?");
            $upd->execute([$attempts, $lockUntil, $customer["id"]]);
            fail("Too many failed attempts. Your account is locked for {$LOCKOUT_MINUTES} minutes.");
        } else {
            $upd = $pdo->prepare("UPDATE customers SET failed_attempts = ? WHERE id = ?");
            $upd->execute([$attempts, $customer["id"]]);
        }
    }

    fail("Incorrect phone/email or password.");

}

$reset = $pdo->prepare("UPDATE customers SET failed_attempts = 0, locked_until = NULL WHERE id = ?");
$reset->execute([$customer["id"]]);

session_regenerate_id(true);

$_SESSION["customer_id"]   = (int) $customer["id"];
$_SESSION["customer_name"] = $customer["full_name"];

echo json_encode([
    "user" => [
        "id"             => (int) $customer["id"],
        "name"           => $customer["full_name"],
        "phone"          => $customer["contact_number"],
        "email"          => $customer["email"],
        "address"        => $customer["address"],
        "payment_method" => $customer["payment_method"],
        "pickup_day"     => $customer["pickup_day"],
        "pickup_time"    => $customer["pickup_time"],
    ],
]);