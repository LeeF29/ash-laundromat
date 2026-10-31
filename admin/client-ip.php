<?php
// Finds the visitor's IP address, used only to count recent messages / lookups.
//
// On XAMPP it is simply REMOTE_ADDR. Online, PHP sits behind Render's proxy, so
// REMOTE_ADDR would be the proxy's address for every visitor. When the TRUST_PROXY
// environment variable is "1" (set in Render), the address from the proxy's
// True-Client-IP header is used instead.
function client_ip(): string
{
    if (getenv("TRUST_PROXY") === "1") {
        foreach (["HTTP_TRUE_CLIENT_IP", "HTTP_CF_CONNECTING_IP"] as $header) {
            $value = trim($_SERVER[$header] ?? "");
            if ($value !== "" && filter_var($value, FILTER_VALIDATE_IP)) {
                return $value;
            }
        }
    }

    return $_SERVER["REMOTE_ADDR"] ?? "unknown";
}