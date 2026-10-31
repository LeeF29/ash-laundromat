<?php
// Admin: moderate customer reviews. New reviews arrive as "pending" and only appear on the website once approved.
require __DIR__ . "/auth-check.php";
require __DIR__ . "/db.php";
require __DIR__ . "/panel-helpers.php";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$allowedFilters = ["all", "pending", "approved", "hidden"];
$filter = $_GET["status"] ?? "all";
if (!in_array($filter, $allowedFilters, true)) {
    $filter = "all";
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
        $error = "Your session expired. Please try again.";
    } else {

        $id = (int) ($_POST["id"] ?? 0);
        $action = $_POST["action"] ?? "";
        $newStatus = $action === "approve" ? "approved" : ($action === "hide" ? "hidden" : null);

        if ($id > 0 && $newStatus !== null) {
            $stmt = $pdo->prepare("UPDATE reviews SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $id]);
        }

        header("Location: reviews.php" . ($filter !== "all" ? "?status=" . rawurlencode($filter) : ""));
        exit;

    }

}

$counts = ["all" => 0, "pending" => 0, "approved" => 0, "hidden" => 0];
foreach ($pdo->query("SELECT status, COUNT(*) AS n FROM reviews GROUP BY status")->fetchAll() as $row) {
    if (isset($counts[$row["status"]])) {
        $counts[$row["status"]] = (int) $row["n"];
    }
}
$counts["all"] = $counts["pending"] + $counts["approved"] + $counts["hidden"];

$sql = "SELECT r.id, r.rating, r.comment, r.status, r.created_at,
               c.full_name, c.contact_number, o.order_no
        FROM reviews r
        LEFT JOIN customers c ON c.id = r.customer_id
        LEFT JOIN orders o ON o.id = r.order_id";
$params = [];

if ($filter !== "all") {
    $sql .= " WHERE r.status = ?";
    $params[] = $filter;
}

$sql .= " ORDER BY r.created_at DESC, r.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

$REVIEW_LABELS = ["pending" => "Pending", "approved" => "Approved", "hidden" => "Hidden"];
$tabs = ["all" => "All", "pending" => "Pending", "approved" => "Approved", "hidden" => "Hidden"];

$activePage = "reviews";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reviews | Ash Laundromat Admin</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="admin-panel.css">
</head>
<body class="ap-body">

  <?php require __DIR__ . "/sidebar.php"; ?>

  <div class="ap-main">
    <header class="ap-header">
      <h1>Customer Reviews</h1>
      <p>ASH Laundry &mdash; Admin<?= $counts["pending"] > 0 ? " &middot; " . (int) $counts["pending"] . " waiting for approval" : "" ?></p>
    </header>

    <main class="ap-content">

      <?php if ($error): ?>
        <p class="ap-alert"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <nav class="ap-tabs" aria-label="Filter reviews">
        <?php foreach ($tabs as $key => $label): ?>
          <a href="reviews.php<?= $key === "all" ? "" : "?status=" . $key ?>" class="ap-tab<?= $filter === $key ? " is-active" : "" ?>">
            <?= htmlspecialchars($label) ?> <span class="ap-tab-count"><?= (int) $counts[$key] ?></span>
          </a>
        <?php endforeach; ?>
      </nav>

      <section class="ap-card ap-panel-flush">
        <table class="ap-table ap-table-tight">
          <thead>
            <tr>
              <th>Date</th>
              <th>Customer</th>
              <th>Order</th>
              <th>Rating</th>
              <th>Review</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($reviews as $r): ?>
              <tr>
                <td class="ap-sub ap-nowrap"><?= htmlspecialchars(date("Y-m-d", strtotime($r["created_at"]))) ?></td>
                <td>
                  <div class="ap-strong"><?= htmlspecialchars($r["full_name"] ?? "—") ?></div>
                  <div class="ap-sub"><?= htmlspecialchars($r["contact_number"] ?? "") ?></div>
                </td>
                <td class="ap-strong ap-nowrap"><?= htmlspecialchars($r["order_no"] ?? "—") ?></td>
                <td class="ap-nowrap">
                  <span class="ap-stars" aria-label="<?= (int) $r["rating"] ?> out of 5"><?= str_repeat("★", (int) $r["rating"]) . str_repeat("☆", 5 - (int) $r["rating"]) ?></span>
                </td>
                <td class="ap-review-text"><?= htmlspecialchars($r["comment"]) ?></td>
                <td><span class="ap-badge ap-badge-<?= htmlspecialchars($r["status"]) ?>"><?= htmlspecialchars($REVIEW_LABELS[$r["status"]] ?? $r["status"]) ?></span></td>
                <td>
                  <div class="ap-actions">
                    <?php if ($r["status"] !== "approved"): ?>
                      <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="id" value="<?= (int) $r["id"] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="ap-btn ap-btn-approve">Approve</button>
                      </form>
                    <?php endif; ?>
                    <?php if ($r["status"] !== "hidden"): ?>
                      <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="id" value="<?= (int) $r["id"] ?>">
                        <input type="hidden" name="action" value="hide">
                        <button type="submit" class="ap-btn ap-btn-hide">Hide</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$reviews): ?>
              <tr><td colspan="7" class="ap-empty"><?= $filter === "all" ? "No reviews yet." : "No " . htmlspecialchars($filter) . " reviews." ?></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

    </main>
  </div>

</body>
</html>