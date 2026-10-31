<?php
header("Content-Type: application/json");
require __DIR__ . "/../admin/session-start.php";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

echo json_encode(["csrf_token" => $_SESSION["csrf_token"]]);