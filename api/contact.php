<?php
// Contact Us form: saves a visitor's message so the admin can read it in admin/messages.php.
// Open to everyone (no login needed), so it is protected by: CSRF token, strict validation,
// prepared statements, a hidden spam-trap field, a short cooldown and an hourly limit per visitor.

header("Content-Type: application/json");
require __DIR__ . "/../admin/session-start.php";
require __DIR__ . "/../admin/db.php";
require __DIR__ . "/../admin/client-ip.php";

// Keep this list in step with the dropdown in index.html and the labels in admin/messages.php.
$CONCERNS = ["general", "order", "pricing", "special", "commercial", "complaint", "other"];

$MAX_PER_HOUR     = 5;   // messages one visitor can send per hour
$COOLDOWN_SECONDS = 15;  // minimum wait between two messages from the same browser

function fail($message, $code = 400) {
    http_response_code($code);
    echo json_encode(["success" => false, "error" => $message]);
    exit;
}

// Anything that isn't plain text (a list, a number...) counts as empty
function field($data, $key) {
    $value = $data[$key] ?? "";
    return is_string($value) ? $value : "";
}

function text_length($value) {
    return function_exists("mb_strlen") ? mb_strlen($value, "UTF-8") : strlen($value);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    fail("Invalid request.", 405);
}

$raw = json_decode(file_get_contents("php://input"), true);
if (!is_array($raw)) {
    fail("Invalid request body.");
}

if (empty($_SESSION["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], field($raw, "csrf_token"))) {
    fail("Your session expired. Please reload the page and try again.");
}

// Spam trap: real visitors never see or fill this field, bots usually do.
// Pretend it worked so the bot learns nothing, and save nothing.
if (trim(field($raw, "website")) !== "") {
    echo json_encode(["success" => true]);
    exit;
}

$name    = trim(field($raw, "name"));
$email   = trim(field($raw, "email"));
$concern = trim(field($raw, "concern"));
$message = trim(str_replace("\r\n", "\n", field($raw, "message")));

if (text_length($name) < 2 || text_length($name) > 100) {
    fail("Please enter your full name.");
}

if ($email === "" || text_length($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail("Please enter a valid email address.");
}

if (!in_array($concern, $CONCERNS, true)) {
    fail("Please choose a type of concern.");
}

if (text_length($message) < 10) {
    fail("Please write a little more so we can help (at least 10 characters).");
}

if (text_length($message) > 1000) {
    fail("Your message is too long (1000 characters maximum).");
}

// No hidden control characters in anything we store
if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $name . $email . $message)) {
    fail("Your message contains characters we can't accept.");
}

// Cooldown for this browser
$now = time();
if (!empty($_SESSION["contact_last_sent"]) && ($now - (int) $_SESSION["contact_last_sent"]) < $COOLDOWN_SECONDS) {
    fail("Please wait a few seconds before sending another message.", 429);
}

// The visitor's address is only kept as a one-way hash, just to count recent messages
$ipHash = hash("sha256", client_ip() . "|ash-contact-form");

try {

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM contact_messages
         WHERE ip_hash = ? AND TIMESTAMPDIFF(SECOND, created_at, NOW()) < 3600"
    );
    $stmt->execute([$ipHash]);

    if ((int) $stmt->fetchColumn() >= $MAX_PER_HOUR) {
        fail("You've sent several messages already. Please try again later, or message us on Facebook Messenger.", 429);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO contact_messages (full_name, email, concern, message, ip_hash) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$name, $email, $concern, $message, $ipHash]);

} catch (PDOException $e) {
    error_log("contact.php: " . $e->getMessage());
    fail("Sorry, we couldn't send your message right now. Please try again later.", 500);
}

$_SESSION["contact_last_sent"] = $now;

echo json_encode(["success" => true]);