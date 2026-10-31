<?php
require __DIR__ . "/session-start.php";
$_SESSION = [];
session_destroy();
header("Location: login.php");
exit;