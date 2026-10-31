<?php
// Admin: messages sent from the Contact Us form on the website. New messages arrive as "new";
// mark them "read" once seen and "replied" once answered. Messages are never deleted from here.
require __DIR__ . "/auth-check.php";
require __DIR__ . "/db.php";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Keep these in step with the dropdown in index.html and the list in api/contact.php.
$CONCERN_LABELS = [
    "general"    => "General Question",
    "order"      => "Order or Tracking",
    "pricing"    => "Pricing & Services",
    "special"    => "Special Request",
    "commercial" => "Commercial / Partnership",
    "complaint"  => "Complaint or Feedback",
    "other"      => "Other",
];

$MESSAGE_LABELS = ["new" => "New", "read" => "Read", "replied" => "Replied"];
$tabs = ["all" => "All", "new" => "New", "read" => "Read", "replied" => "Replied"];

$filter = $_GET["status"] ?? "all";
if (!isset($tabs[$filter])) {
    $filter = "all";
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
        $error = "Your session expired. Please try again.";
    } else {

        $id = (int) ($_POST["id"] ?? 0);
        $action = $_POST["action"] ?? "";

        if ($id > 0 && $action === "mark_read") {

            // Only a New message can become Read
            $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ? AND status = 'new'");
            $stmt->execute([$id]);

        } elseif ($id > 0 && $action === "mark_replied") {

            // A New or Read message can become Replied
            $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'replied' WHERE id = ? AND status <> 'replied'");
            $stmt->execute([$id]);

        }

        header("Location: messages.php" . ($filter !== "all" ? "?status=" . rawurlencode($filter) : ""));
        exit;

    }

}

$counts = ["all" => 0, "new" => 0, "read" => 0, "replied" => 0];
foreach ($pdo->query("SELECT status, COUNT(*) AS n FROM contact_messages GROUP BY status")->fetchAll() as $row) {
    if (isset($counts[$row["status"]])) {
        $counts[$row["status"]] = (int) $row["n"];
    }
}
$counts["all"] = $counts["new"] + $counts["read"] + $counts["replied"];

$sql = "SELECT id, full_name, email, concern, message, status, created_at FROM contact_messages";
$params = [];

if ($filter !== "all") {
    $sql .= " WHERE status = ?";
    $params[] = $filter;
}

$sql .= " ORDER BY created_at DESC, id DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll();

$activePage = "messages";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Messages | Ash Laundromat Admin</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="admin-panel.css">
</head>
<body class="ap-body">

  <?php require __DIR__ . "/sidebar.php"; ?>

  <div class="ap-main">
    <header class="ap-header">
      <h1>Messages</h1>
      <p>ASH Laundry &mdash; Admin<?= $counts["new"] > 0 ? " &middot; " . (int) $counts["new"] . " new" : "" ?></p>
    </header>

    <main class="ap-content">

      <?php if ($error): ?>
        <p class="ap-alert"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <nav class="ap-tabs" aria-label="Filter messages">
        <?php foreach ($tabs as $key => $label): ?>
          <a href="messages.php<?= $key === "all" ? "" : "?status=" . $key ?>" class="ap-tab<?= $filter === $key ? " is-active" : "" ?>">
            <?= htmlspecialchars($label) ?> <span class="ap-tab-count"><?= (int) $counts[$key] ?></span>
          </a>
        <?php endforeach; ?>
      </nav>

      <section class="ap-card ap-panel-flush">
        <table class="ap-table ap-table-tight">
          <thead>
            <tr>
              <th>Date</th>
              <th>From</th>
              <th>Concern</th>
              <th>Message</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($messages as $m): ?>
              <?php
                $concernLabel = $CONCERN_LABELS[$m["concern"]] ?? $m["concern"];
                $mailSubject  = "Re: " . $concernLabel . " - Ash Laundromat";
                $sent         = strtotime($m["created_at"]);
              ?>
              <tr class="<?= $m["status"] === "new" ? "ap-row-new" : "" ?>">
                <td class="ap-nowrap">
                  <div class="ap-strong"><?= htmlspecialchars(date("Y-m-d", $sent)) ?></div>
                  <div class="ap-sub"><?= htmlspecialchars(date("g:i A", $sent)) ?></div>
                </td>
                <td class="ap-msg-from">
                  <div class="ap-strong"><?= htmlspecialchars($m["full_name"]) ?></div>
                  <a class="ap-link" href="mailto:<?= htmlspecialchars($m["email"]) ?>"><?= htmlspecialchars($m["email"]) ?></a>
                </td>
                <td><span class="ap-badge ap-badge-concern"><?= htmlspecialchars($concernLabel) ?></span></td>
                <td class="ap-review-text ap-msg-text"><?= htmlspecialchars($m["message"]) ?></td>
                <td><span class="ap-badge ap-badge-<?= htmlspecialchars($m["status"]) ?>"><?= htmlspecialchars($MESSAGE_LABELS[$m["status"]] ?? $m["status"]) ?></span></td>
                <td>
                  <div class="ap-actions ap-msg-actions">

                    <a class="ap-btn ap-btn-next" href="mailto:<?= htmlspecialchars($m["email"]) ?>?subject=<?= rawurlencode($mailSubject) ?>" title="Open your email app to reply">
                      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
                      Reply
                    </a>

                    <?php if ($m["status"] === "new"): ?>
                      <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="id" value="<?= (int) $m["id"] ?>">
                        <input type="hidden" name="action" value="mark_read">
                        <button type="submit" class="ap-btn ap-btn-hide" title="Mark this message as read">Mark read</button>
                      </form>
                    <?php endif; ?>

                    <?php if ($m["status"] !== "replied"): ?>
                      <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="id" value="<?= (int) $m["id"] ?>">
                        <input type="hidden" name="action" value="mark_replied">
                        <button type="submit" class="ap-btn ap-btn-approve" title="You have replied to this message">Mark replied</button>
                      </form>
                    <?php endif; ?>

                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$messages): ?>
              <tr><td colspan="6" class="ap-empty"><?= $filter === "all" ? "No messages yet. Messages sent from the Contact Us form on the website show up here." : "No " . htmlspecialchars($filter) . " messages." ?></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

    </main>
  </div>

</body>
</html>