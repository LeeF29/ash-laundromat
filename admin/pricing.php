<?php
require __DIR__ . "/auth-check.php";
require __DIR__ . "/db.php";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
        $error = "Your session expired. Please try again.";
    } else {

        $action = $_POST["action"] ?? "";

        if ($action === "delete") {

            $id = (int) ($_POST["id"] ?? 0);
            $stmt = $pdo->prepare("UPDATE services SET is_active = 0 WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Item removed from the active price list.";

        } else {

            $id           = (int) ($_POST["id"] ?? 0);
            $name         = trim($_POST["name"] ?? "");
            $category     = $_POST["category"] ?? "laundry";
            $pricing_type = $_POST["pricing_type"] ?? "per_kg";
            $price        = $_POST["price"] ?? "";
            $slug         = trim($_POST["slug"] ?? "");
            $variant      = trim($_POST["variant"] ?? "");

            if ($name === "") {
                $error = "Please enter a name.";
            } elseif (!in_array($category, ["laundry", "bedding", "footwear", "special", "addon"], true)) {
                $error = "Invalid category.";
            } elseif (!in_array($pricing_type, ["per_kg", "per_pair", "per_piece", "flat"], true)) {
                $error = "Invalid pricing type.";
            } elseif (!is_numeric($price) || $price < 0) {
                $error = "Please enter a valid price (0 or more).";
            } elseif ($category === "addon" && !in_array($slug, ["press_ironing", "pickup_delivery"], true)) {
                $error = "Please choose which checkbox this add-on matches (Press & Ironing or Pickup & Delivery).";
            } else {

                $price = round((float) $price, 2);
                $slug  = $category === "addon" ? $slug : null;

                try {

                    if ($id > 0) {
                        $stmt = $pdo->prepare(
                            "UPDATE services SET name = ?, variant = ?, slug = ?, category = ?, pricing_type = ?, price = ?, is_active = 1 WHERE id = ?"
                        );
                        $stmt->execute([$name, $variant ?: null, $slug, $category, $pricing_type, $price, $id]);
                        $success = "\"{$name}\" updated.";
                    } else {
                        $stmt = $pdo->prepare(
                            "INSERT INTO services (name, variant, slug, category, pricing_type, price) VALUES (?, ?, ?, ?, ?, ?)"
                        );
                        $stmt->execute([$name, $variant ?: null, $slug, $category, $pricing_type, $price]);
                        $success = "\"{$name}\" added.";
                    }

                } catch (PDOException $e) {
                    $error = "That add-on key is already used by another item. Each add-on needs a unique key.";
                }
            }
        }
    }
}

$editing = null;
if (isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->execute([$editId]);
    $editing = $stmt->fetch();
}

$categoryLabels = [
    "laundry"  => "Laundry Service",
    "bedding"  => "Bedding/Linen",
    "footwear" => "Footwear",
    "special"  => "Special Item",
    "addon"    => "Add-on",
];
$pricingLabels = [
    "per_kg"    => "Per kg",
    "per_pair"  => "Per pair",
    "per_piece" => "Per piece",
    "flat"      => "Flat rate",
];

$services = $pdo->query(
    "SELECT * FROM services WHERE is_active = 1
     ORDER BY FIELD(category, 'laundry', 'bedding', 'footwear', 'special', 'addon'), name"
)->fetchAll();


$activePage = "pricing";

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pricing | Ash Laundromat Admin</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="admin-panel.css">
</head>
<body class="ap-body">

  <?php require __DIR__ . "/sidebar.php"; ?>

  <div class="ap-main">
    <header class="ap-header">
      <h1>Pricing</h1>
      <p>ASH Laundry &mdash; Admin</p>
    </header>

    <main class="ap-content">

      <?php if ($error): ?>
        <p class="ap-alert ap-alert-error"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>
      <?php if ($success): ?>
        <p class="ap-alert ap-alert-ok"><?= htmlspecialchars($success) ?></p>
      <?php endif; ?>

      <section class="ap-card ap-panel">
        <h2 class="ap-section-title"><?= $editing ? "Edit Service" : "Add New Service" ?></h2>

        <form method="post" class="ap-form-grid">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= $editing ? (int)$editing["id"] : 0 ?>">

          <label class="ap-field">
            <span>Name</span>
            <input type="text" name="name" required value="<?= htmlspecialchars($editing["name"] ?? "") ?>">
          </label>

          <label class="ap-field">
            <span>Variant/Size <em>(for Special Items, e.g. Small, Large &mdash; leave blank otherwise)</em></span>
            <input type="text" name="variant" maxlength="50" value="<?= htmlspecialchars($editing["variant"] ?? "") ?>">
          </label>

          <label class="ap-field">
            <span>Category</span>
            <select name="category">

                <option value="laundry" <?= ($editing["category"] ?? "") === "laundry" ? "selected" : "" ?>>Laundry Service</option>
                <option value="bedding" <?= ($editing["category"] ?? "") === "bedding" ? "selected" : "" ?>>Bedding/Linen</option>
                <option value="footwear" <?= ($editing["category"] ?? "") === "footwear" ? "selected" : "" ?>>Footwear</option>
                <option value="special" <?= ($editing["category"] ?? "") === "special" ? "selected" : "" ?>>Special Item</option>
                <option value="addon" <?= ($editing["category"] ?? "") === "addon" ? "selected" : "" ?>>Add-on (Press & Ironing, Pickup & Delivery)</option>

            </select>
          </label>

          <label class="ap-field">
            <span>Pricing Type</span>
            <select name="pricing_type">

                <option value="per_kg" <?= ($editing["pricing_type"] ?? "") === "per_kg" ? "selected" : "" ?>>Per kg</option>
                <option value="per_pair" <?= ($editing["pricing_type"] ?? "") === "per_pair" ? "selected" : "" ?>>Per pair</option>
                <option value="per_piece" <?= ($editing["pricing_type"] ?? "") === "per_piece" ? "selected" : "" ?>>Per piece</option>
                <option value="flat" <?= ($editing["pricing_type"] ?? "") === "flat" ? "selected" : "" ?>>Flat rate</option>

            </select>
          </label>

          <label class="ap-field">
            <span>Price (PHP)</span>
            <input type="number" name="price" step="0.01" min="0" required value="<?= htmlspecialchars($editing["price"] ?? "") ?>">
          </label>

          <label class="ap-field">
            <span>Add-on Key <em>(only for Add-on items)</em></span>
            <select name="slug">

                <option value="">&mdash; N/A &mdash;</option>
                <option value="press_ironing" <?= ($editing["slug"] ?? "") === "press_ironing" ? "selected" : "" ?>>Press & Ironing</option>
                <option value="pickup_delivery" <?= ($editing["slug"] ?? "") === "pickup_delivery" ? "selected" : "" ?>>Pickup & Delivery</option>

            </select>
          </label>

          <div class="ap-form-actions">
            <button type="submit" class="ap-btn-solid"><?= $editing ? "Save Changes" : "Add Service" ?></button>
            <?php if ($editing): ?>
              <a href="pricing.php" class="ap-link">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </section>

      <section class="ap-card ap-panel ap-panel-flush">
        <div class="ap-panel-head">
          <h2 class="ap-section-title">Current Price List</h2>
          <span class="ap-sub-plain"><?= count($services) ?> item<?= count($services) === 1 ? "" : "s" ?></span>
        </div>

        <table class="ap-table ap-table-tight">
          <thead>
            <tr>
              <th>Name</th>
              <th>Category</th>
              <th>Pricing</th>
              <th>Price</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($services as $s): ?>
              <tr>
                <td class="ap-strong"><?= htmlspecialchars($s["name"]) ?><?= $s["variant"] ? " (" . htmlspecialchars($s["variant"]) . ")" : "" ?></td>
                <td><?= htmlspecialchars($categoryLabels[$s["category"]] ?? $s["category"]) ?></td>
                <td><?= htmlspecialchars($pricingLabels[$s["pricing_type"]] ?? $s["pricing_type"]) ?></td>
                <td class="ap-money ap-nowrap">&#8369;<?= number_format((float) $s["price"], 2) ?></td>
                <td>
                  <div class="ap-actions">
                    <a class="ap-btn ap-btn-next" href="pricing.php?edit=<?= (int) $s["id"] ?>">Edit</a>
                    <form method="post" onsubmit="return confirm('Remove this item from the price list?');">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int) $s["id"] ?>">
                      <button type="submit" class="ap-btn ap-btn-delete">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$services): ?>
              <tr><td colspan="5" class="ap-empty">No services yet. Add your first one above.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

    </main>
  </div>

</body>
</html>