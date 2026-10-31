<?php
require __DIR__ . "/auth-check.php";
require __DIR__ . "/db.php";
require __DIR__ . "/panel-helpers.php";

$id = (int) ($_GET["id"] ?? 0);

$stmt = $pdo->prepare(
    "SELECT o.id, o.order_no, o.weight_kg, o.press_ironing, o.pickup_delivery, o.pickup_date,
            o.total_amount, o.payment_status, o.status, o.created_at,
            c.full_name, c.contact_number, c.address, s.name AS service_name
     FROM orders o
     LEFT JOIN customers c ON c.id = o.customer_id
     LEFT JOIN services s ON s.id = o.service_id
     WHERE o.id = ?"
);
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    echo "Order not found.";
    exit;
}

$stmt = $pdo->prepare("SELECT item_name, detail, price FROM order_addon_items WHERE order_id = ?");
$stmt->execute([$id]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Receipt <?= htmlspecialchars($order["order_no"]) ?> | Ash Laundromat</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="admin-panel.css">
</head>
<body class="ap-body ap-receipt-page">

  <div class="ap-receipt">

    <div class="ap-receipt-head">
      <img src="../Images/transparent%20tagline.png" alt="Ash Laundromat Logo">
      <h1>Receipt</h1>
      <p class="ap-sub"><?= htmlspecialchars($order["order_no"]) ?> &middot; <?= htmlspecialchars(date("M j, Y g:i A", strtotime($order["created_at"]))) ?></p>
    </div>

    <dl class="ap-receipt-rows">
      <div><dt>Customer</dt><dd><?= htmlspecialchars($order["full_name"] ?? "—") ?></dd></div>
      <div><dt>Contact</dt><dd><?= htmlspecialchars($order["contact_number"] ?? "—") ?></dd></div>
      <div><dt>Service</dt><dd><?= htmlspecialchars($order["service_name"] ?? "—") ?></dd></div>
      <div><dt>Weight</dt><dd><?= htmlspecialchars(weight_label($order["weight_kg"])) ?></dd></div>
      <?php if ($order["press_ironing"]): ?>
        <div><dt>Press &amp; Ironing</dt><dd>Yes</dd></div>
      <?php endif; ?>
      <?php if ($order["pickup_delivery"]): ?>
        <div><dt>Pickup &amp; Delivery</dt><dd><?= $order["pickup_date"] ? htmlspecialchars(date("M j, Y", strtotime($order["pickup_date"]))) : "Yes" ?></dd></div>
        <?php if (trim((string) $order["address"]) !== ""): ?>
          <div><dt>Address</dt><dd><?= htmlspecialchars($order["address"]) ?></dd></div>
        <?php endif; ?>
      <?php endif; ?>
      <?php foreach ($items as $item): ?>
        <div>
          <dt><?= htmlspecialchars($item["item_name"]) ?><?= $item["detail"] ? " (" . htmlspecialchars($item["detail"]) . ")" : "" ?></dt>
          <dd><?= peso($item["price"]) ?></dd>
        </div>
      <?php endforeach; ?>
      <div><dt>Status</dt><dd><?= htmlspecialchars($STATUS_LABELS[$order["status"]] ?? $order["status"]) ?></dd></div>
      <div><dt>Payment</dt><dd><?= htmlspecialchars($PAYMENT_LABELS[$order["payment_status"]] ?? $order["payment_status"]) ?></dd></div>
    </dl>

    <div class="ap-receipt-total">
      <span>Total</span>
      <strong><?= peso($order["total_amount"]) ?></strong>
    </div>

    <p class="ap-receipt-thanks">Thank you for choosing Ash Laundromat!</p>

    <div class="ap-receipt-actions ap-no-print">
      <button type="button" class="ap-btn ap-btn-next" onclick="window.print()">Print</button>
      <a class="ap-btn ap-btn-receipt" href="order-history.php">Back to Order History</a>
    </div>

  </div>

</body>
</html>