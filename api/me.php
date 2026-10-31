<?php
header("Content-Type: application/json");
require __DIR__ . "/../admin/session-start.php";
require __DIR__ . "/../admin/db.php";

if (empty($_SESSION["customer_id"])) {
    echo json_encode(["user" => null, "pending" => null]);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$_SESSION["customer_id"]]);
$customer = $stmt->fetch();

if (!$customer) {
    unset($_SESSION["customer_id"], $_SESSION["customer_name"]);
    echo json_encode(["user" => null, "pending" => null]);
    exit;
}

if ($customer["pending_apply_at"] !== null && strtotime($customer["pending_apply_at"]) <= time()) {

    $stmt = $pdo->prepare(
        "UPDATE customers SET
            full_name = COALESCE(pending_full_name, full_name),
            contact_number = COALESCE(pending_contact_number, contact_number),
            address = COALESCE(pending_address, address),
            payment_method = COALESCE(pending_payment_method, payment_method),
            pending_full_name = NULL,
            pending_contact_number = NULL,
            pending_address = NULL,
            pending_payment_method = NULL,
            pending_requested_at = NULL,
            pending_apply_at = NULL
         WHERE id = ?"
    );
    $stmt->execute([$customer["id"]]);

    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customer["id"]]);
    $customer = $stmt->fetch();

}

$user = [
    "id"             => (int) $customer["id"],
    "name"           => $customer["full_name"],
    "phone"          => $customer["contact_number"],
    "email"          => $customer["email"],
    "address"        => $customer["address"],
    "payment_method" => $customer["payment_method"],
    "pickup_day"     => $customer["pickup_day"],
    "pickup_time"    => $customer["pickup_time"],
];

$pending = null;
if ($customer["pending_apply_at"] !== null) {
    $pending = [
        "full_name"      => $customer["pending_full_name"],
        "phone"          => $customer["pending_contact_number"],
        "address"        => $customer["pending_address"],
        "payment_method" => $customer["pending_payment_method"],
        "requested_at"   => $customer["pending_requested_at"],
        "apply_at"       => $customer["pending_apply_at"],
    ];
}

echo json_encode(["user" => $user, "pending" => $pending]);