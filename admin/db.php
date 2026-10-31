<?php
// Database connection.
//
// Online (Render): the settings come from environment variables, so no password
// is ever stored in this file or on GitHub.
// On XAMPP: with no variables set, it falls back to the local settings below.

date_default_timezone_set("Asia/Manila");

$DB_HOST = getenv("DB_HOST") ?: "localhost";
$DB_PORT = getenv("DB_PORT") ?: "3306";
$DB_NAME = getenv("DB_NAME") ?: "ash_laundromat";
$DB_USER = getenv("DB_USER") ?: "root";
$DB_PASS = getenv("DB_PASS");
if ($DB_PASS === false) {
    $DB_PASS = "";
}

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Any database that is not on this computer (Aiven) must be reached over SSL.
$isLocal = in_array($DB_HOST, ["localhost", "127.0.0.1"], true);

if (!$isLocal) {
    $caFile = getenv("DB_SSL_CA") ?: __DIR__ . "/../aiven-ca.pem";

    if (!is_file($caFile)) {
        error_log("db.php: SSL certificate file not found: " . $caFile);
        http_response_code(500);
        die("Database connection failed.");
    }

    $options[PDO::MYSQL_ATTR_SSL_CA] = $caFile;
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
}

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        $options
    );

    // Keep the database clock in Philippine time, matching PHP above.
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    error_log("db.php: " . $e->getMessage());
    http_response_code(500);
    die("Database connection failed.");
}

// Don't leave the login details sitting in memory for the rest of the page.
unset($DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASS, $options, $isLocal, $caFile);