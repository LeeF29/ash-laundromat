<?php
header("Content-Type: application/json");
require __DIR__ . "/../admin/db.php";

$stmt = $pdo->query(
    "SELECT id, name, variant, slug, category, pricing_type, price
     FROM services
     WHERE is_active = 1
     ORDER BY FIELD(category, 'laundry', 'bedding', 'footwear', 'special', 'addon'), name"
);
$services = $stmt->fetchAll();

$grouped = [
    "laundry"  => [],
    "bedding"  => [],
    "footwear" => [],
    "special"  => [],
    "addon"    => [],
];

foreach ($services as $s) {
    $grouped[$s["category"]][] = [
        "id"           => (int) $s["id"],
        "name"         => $s["name"],
        "variant"      => $s["variant"],
        "slug"         => $s["slug"],
        "pricing_type" => $s["pricing_type"],
        "price"        => (float) $s["price"],
    ];
}

echo json_encode($grouped);