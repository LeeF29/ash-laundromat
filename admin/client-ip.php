<?php
// Finds the visitor's IP address, used only to count recent messages / lookups.
//
// On XAMPP it is simply REMOTE_ADDR. Online, PHP sits behind Vercel and Render, so
// REMOTE_ADDR would be a proxy's address for every visitor. When the TRUST_PROXY
// environment variable is "1" (set in Render), the visitor's address is read from the
// headers the proxies add, in this order: Vercel's, the standard one, then Render's.
function client_ip_details(): array
{
    if (getenv("TRUST_PROXY") === "1") {
        $candidates = [
            "x-vercel-forwarded-for" => "HTTP_X_VERCEL_FORWARDED_FOR",
            "x-forwarded-for"        => "HTTP_X_FORWARDED_FOR",
            "true-client-ip"         => "HTTP_TRUE_CLIENT_IP",
            "cf-connecting-ip"       => "HTTP_CF_CONNECTING_IP",
        ];

        foreach ($candidates as $name => $key) {
            // a header can hold a list "client, proxy, proxy": the first one is the visitor
            $value = trim(explode(",", $_SERVER[$key] ?? "")[0]);
            if ($value !== "" && filter_var($value, FILTER_VALIDATE_IP)) {
                return [$value, $name];
            }
        }
    }

    return [$_SERVER["REMOTE_ADDR"] ?? "unknown", "remote-addr"];
}

function client_ip(): string
{
    return client_ip_details()[0];
}