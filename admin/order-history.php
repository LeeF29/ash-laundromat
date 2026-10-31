<?php
require __DIR__ . "/auth-check.php";
require __DIR__ . "/db.php";
require __DIR__ . "/panel-helpers.php";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$q = trim($_GET["q"] ?? "");
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
        $error = "Your session expired. Please try again.";
    } else {

        $id = (int) ($_POST["id"] ?? 0);

        if (($_POST["action"] ?? "") === "mark_paid") {

            // Mark paid: only an Unpaid order can be changed, and only to Paid.
            $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ? AND payment_status = 'unpaid'");
            $stmt->execute([$id]);

        } else {

            $stmt = $pdo->prepare("SELECT status, pickup_delivery, press_ironing FROM orders WHERE id = ?");
            $stmt->execute([$id]);
            $current = $stmt->fetch();

            $next = $current ? next_status($current["status"], $current["pickup_delivery"], $current["press_ironing"]) : null;

            if ($next) {
                $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ? AND status = ?");
                $stmt->execute([$next, $id, $current["status"]]);
            }

        }

        header("Location: order-history.php" . ($q !== "" ? "?q=" . rawurlencode($q) : ""));
        exit;

    }

}

$sql = "SELECT o.id, o.order_no, o.weight_kg, o.total_amount, o.payment_status, o.status, o.pickup_delivery, o.press_ironing, o.created_at,
               c.full_name, c.contact_number, s.name AS service_name
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        LEFT JOIN services s ON s.id = o.service_id";
$params = [];

if ($q !== "") {
    $sql .= " WHERE o.order_no LIKE ? OR c.full_name LIKE ? OR c.contact_number LIKE ?";
    $like = "%" . $q . "%";
    $params = [$like, $like, $like];
}

$sql .= " ORDER BY o.created_at DESC, o.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$activePage = "order-history";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order History | Ash Laundromat Admin</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="admin-panel.css">
</head>
<body class="ap-body">

  <?php require __DIR__ . "/sidebar.php"; ?>

  <div class="ap-main">
    <header class="ap-header ap-header-split">
      <div>
        <h1>Order History</h1>
        <p>ASH Laundry &mdash; Admin</p>
      </div>
      <form method="get" class="ap-search">
        <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search order or customer...">
      </form>
    </header>

    <main class="ap-content">

      <?php if ($error): ?>
        <p class="ap-alert"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <section class="ap-card ap-panel-flush">
        <table class="ap-table ap-table-tight">
          <thead>
            <tr>
              <th>Order No.</th>
              <th>Customer</th>
              <th>Service</th>
              <th>Weight</th>
              <th>Total</th>
              <th>Status</th>
              <th>Payment</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $o): ?>
              <?php $next = next_status($o["status"], $o["pickup_delivery"], $o["press_ironing"]); ?>
              <tr>
                <td class="ap-strong ap-nowrap"><?= htmlspecialchars($o["order_no"]) ?></td>
                <td>
                  <div class="ap-strong"><?= htmlspecialchars($o["full_name"] ?? "—") ?></div>
                  <div class="ap-sub"><?= htmlspecialchars($o["contact_number"] ?? "") ?></div>
                </td>
                <td><?= htmlspecialchars($o["service_name"] ?? "—") ?></td>
                <td><?= htmlspecialchars(weight_label($o["weight_kg"])) ?></td>
                <td class="ap-strong"><?= peso($o["total_amount"]) ?></td>
                <td><span class="ap-badge ap-badge-<?= htmlspecialchars($o["status"]) ?>"><?= htmlspecialchars($STATUS_LABELS[$o["status"]] ?? $o["status"]) ?></span></td>
                <td><span class="ap-badge ap-badge-<?= htmlspecialchars($o["payment_status"]) ?>"><?= htmlspecialchars($PAYMENT_LABELS[$o["payment_status"]] ?? $o["payment_status"]) ?></span></td>
                <td class="ap-sub ap-nowrap"><?= htmlspecialchars(date("Y-m-d", strtotime($o["created_at"]))) ?></td>
                <td>
                  <div class="ap-actions">
                    <?php if ($next): ?>
                      <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="id" value="<?= (int) $o["id"] ?>">
                        <button type="submit" class="ap-btn ap-btn-next" title="Move to <?= htmlspecialchars($STATUS_LABELS[$next]) ?>">
                          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>
                          Next
                        </button>
                      </form>
                    <?php endif; ?>
                    <?php if ($o["payment_status"] === "unpaid"): ?>
                      <form method="post" onsubmit="return confirm('Mark order <?= htmlspecialchars($o["order_no"], ENT_QUOTES) ?> as paid?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="id" value="<?= (int) $o["id"] ?>">
                        <input type="hidden" name="action" value="mark_paid">
                        <button type="submit" class="ap-btn ap-btn-paid" title="Mark this order as paid">
                          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5L20 7"/></svg>
                          Mark paid
                        </button>
                      </form>
                    <?php endif; ?>
                    <a class="ap-btn ap-btn-receipt" href="receipt.php?id=<?= (int) $o["id"] ?>" target="_blank" rel="noopener">
                      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h9l4 4v14H6z"/><path d="M9 12h7"/><path d="M9 16h7"/></svg>
                      Receipt
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?>
              <tr><td colspan="9" class="ap-empty"><?= $q !== "" ? "No orders match your search." : "No orders yet." ?></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

    </main>
  </div>

</body>
</html>