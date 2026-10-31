<?php
header("Content-Type: application/json");
require __DIR__ . "/../admin/session-start.php";

unset($_SESSION["customer_id"], $_SESSION["customer_name"]);
session_regenerate_id(true);

echo json_encode(["success" => true]);