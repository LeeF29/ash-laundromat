<?php
// Shared helpers for the admin panel pages (dashboard, order history, customers).

function peso($amount) {
    $amount = (float) $amount;
    $decimals = floor($amount) == $amount ? 0 : 2;
    return "&#8369;" . number_format($amount, $decimals);
}

$STATUS_LABELS = [
    "waiting"    => "Waiting",
    "for_pickup" => "For Pickup",
    "received"   => "Received",
    "washing"    => "Washing",
    "drying"     => "Drying",
    "pressing"   => "Pressing & Ironing",
    "ready"      => "Ready",
    "completed"  => "Completed",
];

// The next status for an order, or null when it is already completed.
//   Walk-in:            Waiting > Received > Washing > Drying > Ready > Completed
//   Pickup & Delivery:  Waiting > For Pickup > Received > ...
//   Press & Ironing:    ... Drying > Pressing & Ironing > Ready
function next_status($status, $pickup, $press) {
    switch ($status) {
        case "waiting":    return $pickup ? "for_pickup" : "received";
        case "for_pickup": return "received";
        case "received":   return "washing";
        case "washing":    return "drying";
        case "drying":     return $press ? "pressing" : "ready";
        case "pressing":   return "ready";
        case "ready":      return "completed";
    }
    return null;
}

$PAYMENT_LABELS = [
    "paid"   => "Paid",
    "unpaid" => "Unpaid",
];

function weight_label($weight) {
    if ($weight === null) {
        return "—";
    }
    return rtrim(rtrim(number_format((float) $weight, 2), "0"), ".") . "kg";
}