<?php
ini_set("session.use_strict_mode", "1");

// Online the site is served over HTTPS (Render tells PHP this through the
// X-Forwarded-Proto header). On XAMPP it is plain http, so the flag stays off there.
$isHttps = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
    || (($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https");

session_set_cookie_params([
    "lifetime" => 0,
    "path"     => "/",
    "secure"   => $isHttps,
    "httponly" => true,
    "samesite" => "Strict",
]);

session_start();