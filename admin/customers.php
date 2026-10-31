<?php
require __DIR__ . "/auth-check.php";
require __DIR__ . "/db.php";
require __DIR__ . "/panel-helpers.php";

$q = trim($_GET["q"] ?? "");

$sql = "SELECT c.id, c.full_name, c.contact_number,
               COUNT(o.id) AS total_orders,
               MAX(o.created_at) AS last_order,
               COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END), 0) AS total_spent
        FROM customers c
        LEFT JOIN orders o ON o.customer_id = c.id";
$params = [];

if ($q !== "") {
    $sql .= " WHERE c.full_name LIKE ? OR c.contact_number LIKE ?";
    $like = "%" . $q . "%";
    $params = [$like, $like];
}

$sql .= " GROUP BY c.id, c.full_name, c.contact_number ORDER BY c.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$activePage = "customers";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customers | Ash Laundromat Admin</title>

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
        <h1>Customer Registry</h1>
        <p>ASH Laundry &mdash; Admin</p>
      </div>
      <form method="get" class="ap-search">
        <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search customer or phone...">
      </form>
    </header>

    <main class="ap-content">

      <section class="ap-card ap-panel-flush">
        <table class="ap-table">
          <thead>
            <tr>
              <th>Customer</th>
              <th>Phone</th>
              <th>Total Orders</th>
              <th>Last Order</th>
              <th>Total Spent</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($customers as $c): ?>
              <tr>
                <td>
                  <div class="ap-person">
                    <span class="ap-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr(trim($c["full_name"] ?? ""), 0, 1)) ?: "?") ?></span>
                    <span class="ap-strong"><?= htmlspecialchars($c["full_name"] ?? "—") ?></span>
                  </div>
                </td>
                <td><?= htmlspecialchars($c["contact_number"] ?? "") ?></td>
                <td class="ap-strong"><?= (int) $c["total_orders"] ?></td>
                <td class="ap-sub ap-nowrap"><?= $c["last_order"] ? htmlspecialchars(date("Y-m-d", strtotime($c["last_order"]))) : "—" ?></td>
                <td class="ap-money"><?= peso($c["total_spent"]) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$customers): ?>
              <tr><td colspan="5" class="ap-empty"><?= $q !== "" ? "No customers match your search." : "No customers yet." ?></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

    </main>
  </div>

</body>
</html>