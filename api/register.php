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

$fullName      = trim($raw["full_name"] ?? "");
$email         = trim($raw["email"] ?? "");
$phone         = trim($raw["phone"] ?? "");
$address       = trim($raw["address"] ?? "");
$password      = $raw["password"] ?? "";
$paymentMethod = trim($raw["payment_method"] ?? "");
$pickupDay     = trim($raw["pickup_day"] ?? "");
$pickupTime    = trim($raw["pickup_time"] ?? "");

if (mb_strlen($fullName) < 6 || mb_strlen($fullName) > 100) {
    fail("Please enter your full name (at least 6 characters).");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail("Please enter a valid email address.");
}

if (!preg_match('/^(09|\+639)[0-9]{9}$/', $phone)) {
    fail("Please enter a valid phone number (e.g. 09xxxxxxxxx).");
}

if (mb_strlen($address) < 10 || mb_strlen($address) > 300) {
    fail("Please enter your full address.");
}

if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,64}$/', $password)) {
    fail("Password must be 8-75 characters and include an uppercase letter, a lowercase letter, a number, and a special character.");
}

if (!in_array($paymentMethod, ["Cash", "GCash", "Maya", "Card"], true)) {
    fail("Please choose a payment method.");
}

$stmt = $pdo->prepare(
    "SELECT id FROM customers WHERE password_hash IS NOT NULL AND (email = ? OR contact_number = ?)"
);
$stmt->execute([$email, $phone]);
if ($stmt->fetch()) {
    fail("An account with that email or phone number already exists.");
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// A walk-in customer (placed orders at the store, no password yet) who now registers should
// keep their order history, so their existing row becomes the account instead of a duplicate.
// 09xxxxxxxxx and +639xxxxxxxxx are the same number.
$phone09 = preg_match('/^\+639[0-9]{9}$/', $phone) ? "0" . substr($phone, 3) : $phone;

$stmt = $pdo->prepare(
    "SELECT id, full_name FROM customers WHERE password_hash IS NULL AND (email = ? OR contact_number IN (?, ?))"
);
$stmt->execute([$email, $phone09, "+63" . substr($phone09, 1)]);
$walkins = $stmt->fetchAll();

if (count($walkins) > 1) {
    // two different walk-in records match: never combine them automatically
    fail("We found more than one record with these details. Please contact the laundromat so we can sort it out.");
}

function same_name($a, $b) {
    $clean = function ($n) {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($n)));
    };
    return $clean($a) === $clean($b);
}

if ($walkins && !same_name($walkins[0]["full_name"], $fullName)) {
    fail("We already have a customer on file with that phone number or email under a different name. Please contact the laundromat so we can link your orders.");
}

try {

    if ($walkins) {

        $customerId = (int) $walkins[0]["id"];

        $stmt = $pdo->prepare(
            "UPDATE customers
             SET full_name = ?, contact_number = ?, email = ?, address = ?, password_hash = ?,
                 payment_method = ?, pickup_day = ?, pickup_time = ?
             WHERE id = ? AND password_hash IS NULL"
        );
        $stmt->execute([
            $fullName, $phone, $email, $address, $passwordHash,
            $paymentMethod, $pickupDay ?: null, $pickupTime ?: null,
            $customerId,
        ]);

        if ($stmt->rowCount() !== 1) {
            fail("That email or phone number is already registered.");
        }

    } else {

        $stmt = $pdo->prepare(
            "INSERT INTO customers
                (full_name, contact_number, email, address, password_hash, payment_method, pickup_day, pickup_time)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $fullName, $phone, $email, $address, $passwordHash,
            $paymentMethod, $pickupDay ?: null, $pickupTime ?: null,
        ]);

        $customerId = (int) $pdo->lastInsertId();

    }

} catch (PDOException $e) {
    fail("That email or phone number is already registered.");
}

session_regenerate_id(true);

$_SESSION["customer_id"]   = $customerId;
$_SESSION["customer_name"] = $fullName;

echo json_encode([
    "user" => [
        "id"             => $customerId,
        "name"           => $fullName,
        "phone"          => $phone,
        "email"          => $email,
        "address"        => $address,
        "payment_method" => $paymentMethod,
        "pickup_day"     => $pickupDay,
        "pickup_time"    => $pickupTime,
    ],
]);