<?php
require __DIR__ . "/auth-check.php";
require __DIR__ . "/db.php";
require __DIR__ . "/panel-helpers.php";

$stats = $pdo->query(
    "SELECT COUNT(*) AS total_orders,
            COALESCE(SUM(status <> 'completed'), 0) AS active_orders,
            COALESCE(SUM(payment_status = 'unpaid'), 0) AS unpaid_orders,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) AS paid_revenue
     FROM orders"
)->fetch();

$pipeline = array_fill_keys(array_keys($STATUS_LABELS), 0);
foreach ($pdo->query("SELECT status, COUNT(*) AS n FROM orders GROUP BY status")->fetchAll() as $row) {
    $pipeline[$row["status"]] = (int) $row["n"];
}

$recent = $pdo->query(
    "SELECT o.order_no, o.total_amount, o.status, o.payment_status, c.full_name
     FROM orders o
     LEFT JOIN customers c ON c.id = o.customer_id
     ORDER BY o.created_at DESC, o.id DESC
     LIMIT 5"
)->fetchAll();

$activePage = "dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | Ash Laundromat</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="admin-panel.css">
</head>
<body class="ap-body">

  <?php require __DIR__ . "/sidebar.php"; ?>

  <div class="ap-main">
    <header class="ap-header">
      <h1>Dashboard</h1>
      <p>ASH Laundry &mdash; Admin</p>
    </header>

    <main class="ap-content">

      <section class="ap-stats">
        <div class="ap-card ap-stat">
          <span class="ap-stat-ico ap-tone-blue"><span class="ap-ico" style="--ico:url('../Assets/Box-Icon.svg')"></span></span>
          <div>
            <div class="ap-stat-num"><?= (int) $stats["total_orders"] ?></div>
            <div class="ap-stat-label">Total Orders</div>
          </div>
        </div>

        <div class="ap-card ap-stat">
          <span class="ap-stat-ico ap-tone-purple">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
          </span>
          <div>
            <div class="ap-stat-num"><?= (int) $stats["active_orders"] ?></div>
            <div class="ap-stat-label">Active Orders</div>
          </div>
        </div>

        <div class="ap-card ap-stat">
          <span class="ap-stat-ico ap-tone-red">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16.5v.01"/></svg>
          </span>
          <div>
            <div class="ap-stat-num"><?= (int) $stats["unpaid_orders"] ?></div>
            <div class="ap-stat-label">Unpaid</div>
          </div>
        </div>

        <div class="ap-card ap-stat">
          <span class="ap-stat-ico ap-tone-green">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/></svg>
          </span>
          <div>
            <div class="ap-stat-num"><?= peso($stats["paid_revenue"]) ?></div>
            <div class="ap-stat-label">Revenue (Paid)</div>
          </div>
        </div>
      </section>

      <section class="ap-card ap-panel">
        <h2 class="ap-section-title">Order Pipeline</h2>
        <div class="ap-pipeline">
          <?php foreach ($STATUS_LABELS as $key => $label): ?>
            <div class="ap-pipe">
              <div class="ap-pipe-num"><?= $pipeline[$key] ?></div>
              <span class="ap-badge ap-badge-<?= $key ?>"><?= $label ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="ap-card ap-panel ap-panel-flush">
        <div class="ap-panel-head">
          <h2 class="ap-section-title">Recent Orders</h2>
          <a class="ap-link" href="order-history.php">View all &rarr;</a>
        </div>

        <table class="ap-table">
          <thead>
            <tr>
              <th>Order</th>
              <th>Customer</th>
              <th>Total</th>
              <th>Status</th>
              <th>Payment</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $o): ?>
              <tr>
                <td class="ap-strong"><?= htmlspecialchars($o["order_no"]) ?></td>
                <td><?= htmlspecialchars($o["full_name"] ?? "—") ?></td>
                <td class="ap-strong"><?= peso($o["total_amount"]) ?></td>
                <td><span class="ap-badge ap-badge-<?= htmlspecialchars($o["status"]) ?>"><?= htmlspecialchars($STATUS_LABELS[$o["status"]] ?? $o["status"]) ?></span></td>
                <td><span class="ap-badge ap-badge-<?= htmlspecialchars($o["payment_status"]) ?>"><?= htmlspecialchars($PAYMENT_LABELS[$o["payment_status"]] ?? $o["payment_status"]) ?></span></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$recent): ?>
              <tr><td colspan="5" class="ap-empty">No orders yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

    </main>
  </div>

</body>
</html>