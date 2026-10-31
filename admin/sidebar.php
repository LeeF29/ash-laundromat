<?php
// Admin panel sidebar. Set $activePage before including: dashboard, create-order, order-history, customers, reviews, messages, pricing.
$activePage = $activePage ?? "";

$navItems = [
    ["key" => "dashboard",     "href" => "dashboard.php",     "label" => "Dashboard",     "icon" => "Boxdash-icon.svg"],
    ["key" => "create-order",  "href" => "create-order.php",  "label" => "Create Order",  "icon" => "Add-Icon.svg"],
    ["key" => "order-history", "href" => "order-history.php", "label" => "Order History", "icon" => "Box-Icon.svg"],
    ["key" => "customers",     "href" => "customers.php",     "label" => "Customers",     "icon" => "Customer-Icon.svg"],
    ["key" => "reviews",       "href" => "reviews.php",       "label" => "Reviews",       "icon" => "Star-Icon.svg"],
    ["key" => "messages",      "href" => "messages.php",      "label" => "Messages",      "icon" => "Message Icon.svg"],
    ["key" => "pricing",       "href" => "pricing.php",       "label" => "Pricing",       "icon" => "pricetag2-svgrepo-com.svg"],
];

// Number of unread (New) contact messages, shown as a small badge next to "Messages".
// If the contact_messages table doesn't exist yet this quietly shows no badge instead of breaking the page.
$unreadMessages = 0;
if (isset($pdo)) {
    try {
        $unreadMessages = (int) $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn();
    } catch (Throwable $e) {
        $unreadMessages = 0;
    }
}
?>
<aside class="ap-sidebar">
  <div class="ap-brand">
    <img src="../Images/transparent%20tagline.png" alt="">
    <span>ADMIN PANEL</span>
  </div>

  <nav class="ap-nav">
    <?php foreach ($navItems as $item): ?>
      <a href="<?= $item["href"] ?>" class="<?= $activePage === $item["key"] ? "is-active" : "" ?>">
        <span class="ap-ico" style="--ico:url('../Assets/<?= $item["icon"] ?>')"></span>
        <?= $item["label"] ?>
        <?php if ($item["key"] === "messages" && $unreadMessages > 0): ?>
          <span class="ap-nav-badge"><?= $unreadMessages > 99 ? "99+" : $unreadMessages ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="ap-nav-foot">
    <nav class="ap-nav">
      <a href="logout.php">
        <span class="ap-ico" style="--ico:url('../Assets/Logout-Icon.svg')"></span>
        Logout
      </a>
    </nav>
  </div>
</aside>

<script src="admin-motion.js" defer></script>