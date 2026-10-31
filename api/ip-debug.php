<?php
// TEMPORARY test file: delete it after the check.
header("Content-Type: application/json");
header("Cache-Control: no-store");
require __DIR__ . "/../admin/client-ip.php";

$d = client_ip_details();
echo json_encode(["ip" => $d[0], "source" => $d[1]]);