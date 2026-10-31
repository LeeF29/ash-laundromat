<?php
require __DIR__ . "/auth-check.php";
require __DIR__ . "/db.php";
require __DIR__ . "/panel-helpers.php";
require __DIR__ . "/order-number.php";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Finds the customer a phone number and/or an email belongs to.
// A registered account is a customers row with a password (made on the website); a row without
// one is a record created from an earlier order (walk-in). Both are looked up by phone AND email,
// but they are never treated as the same thing, and an earlier order is not proof of an account.
//
// kind: none | walkin | account | conflict
function find_customer(PDO $pdo, string $phone, string $email): array
{
    $cols = "id, full_name, contact_number, email, address, (password_hash IS NOT NULL) AS has_account";

    $byPhone = null;
    $phone09 = preg_match('/^\+639[0-9]{9}$/', $phone) ? "0" . substr($phone, 3) : $phone;
    if (preg_match('/^09[0-9]{9}$/', $phone09)) {
        $stmt = $pdo->prepare(
            "SELECT $cols FROM customers
             WHERE contact_number IN (?, ?)
             ORDER BY (password_hash IS NOT NULL) DESC, id ASC
             LIMIT 1"
        );
        $stmt->execute([$phone09, "+63" . substr($phone09, 1)]);
        $byPhone = $stmt->fetch() ?: null;
    }

    $byEmail = null;
    if ($email !== "" && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare("SELECT $cols FROM customers WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $byEmail = $stmt->fetch() ?: null;
    }

    $result = ["kind" => "none", "customer" => null, "email_free" => true, "note" => "", "message" => ""];

    if (!$byPhone && !$byEmail) {
        return $result;
    }

    $phoneIsAccount = $byPhone && (int) $byPhone["has_account"] === 1;
    $emailIsAccount = $byEmail && (int) $byEmail["has_account"] === 1;
    $differentRecords = $byPhone && $byEmail && (int) $byPhone["id"] !== (int) $byEmail["id"];

    // phone and email belong to two different registered accounts: never combine them
    if ($differentRecords && $phoneIsAccount && $emailIsAccount) {
        $result["kind"] = "conflict";
        $result["message"] = "This phone number belongs to the registered account of " . $byPhone["full_name"]
            . " and this email belongs to the registered account of " . $byEmail["full_name"]
            . ". Nothing was combined or saved. Please check which one is correct.";
        return $result;
    }

    if (!$byPhone) {
        $chosen = $byEmail;
        $result["note"] = "Found by email. The phone number on file (" . $byEmail["contact_number"] . ") is kept.";
    } elseif ($differentRecords && $emailIsAccount) {
        $chosen = $byEmail;
        $result["note"] = "The phone number you entered belongs to a different record, so it was not used.";
    } else {
        $chosen = $byPhone;
        if ($differentRecords) {
            $result["note"] = "The email you entered belongs to a different record, so it was not used.";
        }
    }

    $result["customer"]   = $chosen;
    $result["kind"]       = (int) $chosen["has_account"] === 1 ? "account" : "walkin";
    $result["email_free"] = !$byEmail || (int) $byEmail["id"] === (int) $chosen["id"];

    return $result;
}

// read-only lookup used by the form to fill in a customer who is already on file
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["lookup"])) {

    $phone = trim((string) ($_GET["phone"] ?? ""));
    $email = trim((string) ($_GET["email"] ?? ""));

    $found = find_customer($pdo, $phone, $email);
    $reply = ["status" => $found["kind"], "message" => $found["message"], "customer" => null];

    if ($found["customer"]) {
        $c = $found["customer"];

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ?");
        $stmt->execute([$c["id"]]);
        $count = (int) $stmt->fetchColumn();
        $earlier = $count === 0 ? "no earlier orders" : $count . " earlier order" . ($count === 1 ? "" : "s");

        if ($found["kind"] === "account") {
            $reply["message"] = "Registered website account found: " . $c["full_name"] . " (" . $earlier . "). "
                . "The order will be saved under this account. Its details are not changed.";
        } else {
            $reply["message"] = "Returning customer found: " . $c["full_name"] . " (" . $earlier . ", no website account). "
                . "The order will be added to their record.";
        }
        if ($found["note"] !== "") {
            $reply["message"] .= " " . $found["note"];
        }

        $reply["customer"] = [
            "name"    => $c["full_name"],
            "phone"   => $c["contact_number"],
            "email"   => (string) $c["email"],
            "address" => (string) $c["address"],
        ];
    } elseif ($found["kind"] === "none" && (preg_match('/^(09|\+639)[0-9]{9}$/', $phone) || ($email !== "" && filter_var($email, FILTER_VALIDATE_EMAIL)))) {
        $reply["message"] = "No existing customer found. Enter the details normally and a new customer record will be created.";
    } elseif ($found["kind"] === "none") {
        $reply["status"] = "idle";
    }

    header("Content-Type: application/json; charset=UTF-8");
    header("Cache-Control: no-store");
    echo json_encode($reply);
    exit;
}

$laundry = $pdo->query(
    "SELECT id, name, variant, price FROM services WHERE category = 'laundry' AND is_active = 1 ORDER BY name"
)->fetchAll();

$itemRows = $pdo->query(
    "SELECT id, name, variant, category, pricing_type, price
     FROM services
     WHERE category IN ('bedding', 'footwear', 'special') AND is_active = 1
     ORDER BY FIELD(category, 'bedding', 'footwear', 'special'), name, variant"
)->fetchAll();

$itemData = ["bedding" => [], "footwear" => [], "special" => []];
foreach ($itemRows as $r) {
    $itemData[$r["category"]][] = [
        "id"           => (int) $r["id"],
        "name"         => $r["name"],
        "variant"      => $r["variant"],
        "pricing_type" => $r["pricing_type"],
        "price"        => (float) $r["price"],
    ];
}

$press = null;
$pickup = null;
foreach ($pdo->query("SELECT slug, price FROM services WHERE category = 'addon' AND is_active = 1")->fetchAll() as $row) {
    if ($row["slug"] === "press_ironing") $press = $row;
    if ($row["slug"] === "pickup_delivery") $pickup = $row;
}

$error = "";
$success = $_SESSION["order_flash"] ?? null;
unset($_SESSION["order_flash"]);

$old = [
    "customer_name"  => "",
    "contact_number" => "",
    "email"          => "",
    "address"        => "",
    "service_id"     => 0,
    "weight"         => "",
    "pickup_date"    => "",
    "payment_status" => "unpaid",
    "add_press"      => false,
    "add_pickup"     => false,
    "items"          => [],
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $old["customer_name"]  = trim($_POST["customer_name"] ?? "");
    $old["contact_number"] = trim($_POST["contact_number"] ?? "");
    $old["email"]          = trim($_POST["customer_email"] ?? "");
    $old["address"]        = trim($_POST["customer_address"] ?? "");
    $old["service_id"]     = (int) ($_POST["service_id"] ?? 0);
    $old["weight"]         = trim($_POST["weight"] ?? "");
    $old["pickup_date"]    = trim($_POST["pickup_date"] ?? "");
    $old["payment_status"] = $_POST["payment_status"] ?? "unpaid";
    $old["add_press"]      = !empty($_POST["add_press"]);
    $old["add_pickup"]     = !empty($_POST["add_pickup"]);

    $cats = is_array($_POST["item_category"] ?? null) ? array_values($_POST["item_category"]) : [];
    $ids  = is_array($_POST["item_id"] ?? null) ? array_values($_POST["item_id"]) : [];
    $qtys = is_array($_POST["item_qty"] ?? null) ? array_values($_POST["item_qty"]) : [];

    foreach ($ids as $i => $itemId) {
        $old["items"][] = [
            "category" => is_string($cats[$i] ?? null) ? $cats[$i] : "",
            "id"       => (int) $itemId,
            "qty"      => is_string($qtys[$i] ?? null) ? trim($qtys[$i]) : "",
        ];
    }

    $weight = (float) $old["weight"];

    // normalise the phone so +639... and 09... find the same customer
    $phone = $old["contact_number"];
    $phone09 = preg_match('/^\+639[0-9]{9}$/', $phone) ? "0" . substr($phone, 3) : $phone;

    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ? AND category = 'laundry' AND is_active = 1");
    $stmt->execute([$old["service_id"]]);
    $service = $stmt->fetch();

    if (!hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
        $error = "Your session expired. Please try again.";
    } elseif (mb_strlen($old["customer_name"]) < 2 || mb_strlen($old["customer_name"]) > 100) {
        $error = "Please enter the customer's name.";
    } elseif (!preg_match('/^(09|\+639)[0-9]{9}$/', $phone)) {
        $error = "Please enter a valid phone number (e.g. 09xxxxxxxxx).";
    } elseif ($old["email"] !== "" && (!filter_var($old["email"], FILTER_VALIDATE_EMAIL) || mb_strlen($old["email"]) > 100)) {
        $error = "Please enter a valid email address, or leave it blank.";
    } elseif ($old["address"] !== "" && (mb_strlen($old["address"]) < 5 || mb_strlen($old["address"]) > 255)) {
        $error = "Please enter the full address, or leave it blank.";
    } elseif (!$service) {
        $error = "Please select a valid service.";
    } elseif ($weight < 0.5 || $weight > 100) {
        $error = "Please enter a valid weight (0.5 to 100 kg).";
    } elseif (!in_array($old["payment_status"], ["paid", "unpaid"], true)) {
        $error = "Please choose a payment status.";
    } elseif ($old["add_pickup"] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $old["pickup_date"])) {
        $error = "Please choose a pickup date.";
    } elseif ($old["add_press"] && !$press) {
        $error = "Press & Ironing isn't available right now.";
    } elseif ($old["add_pickup"] && !$pickup) {
        $error = "Pickup & Delivery isn't available right now.";
    } else {

        $validItems = [];

        foreach ($old["items"] as $item) {

            $qty = (float) $item["qty"];

            if (!in_array($item["category"], ["bedding", "footwear", "special"], true)) {
                $error = "Invalid item category.";
            } elseif ($qty <= 0 || $qty > 100) {
                $error = "Invalid item quantity.";
            } else {
                $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ? AND category = ? AND is_active = 1");
                $stmt->execute([$item["id"], $item["category"]]);
                $row = $stmt->fetch();

                if (!$row) {
                    $error = "One of the added items is no longer available.";
                } else {
                    $unitLabel = $row["pricing_type"] === "per_pair"
                        ? ($qty == 1 ? " pair" : " pairs")
                        : ($row["pricing_type"] === "per_piece" ? " pc" : "kg");

                    $validItems[] = [
                        "category" => $item["category"],
                        "name"     => $row["name"] . ($row["variant"] ? " (" . $row["variant"] . ")" : ""),
                        "detail"   => $qty . $unitLabel,
                        "price"    => round($row["price"] * $qty, 2),
                    ];
                }
            }

            if ($error !== "") break;
        }

        // the customer this order belongs to: found by phone or email (registered account first),
        // or a new walk-in record when neither is on file
        $customer = false;
        $match = ["note" => "", "email_free" => true];
        if ($error === "") {
            $match = find_customer($pdo, $phone, $old["email"]);

            if ($match["kind"] === "conflict") {
                $error = $match["message"];
            } else {
                $customer = $match["customer"] ?: false;
            }

            if ($error === "" && $old["add_pickup"] && $old["address"] === "" && !($customer && trim((string) $customer["address"]) !== "")) {
                $error = "Please enter the customer's address for Pickup & Delivery.";
            }
        }

        if ($error === "") {

            $total = round($service["price"] * $weight, 2);
            if ($old["add_press"]) {
                $total += round($press["price"] * $weight, 2);
            }
            if ($old["add_pickup"]) {
                $total += round($pickup["price"], 2);
            }
            foreach ($validItems as $item) {
                $total += $item["price"];
            }

            try {

                $pdo->beginTransaction();

                if ($customer) {
                    $customerId   = (int) $customer["id"];
                    $customerName = $customer["full_name"];
                    $linked       = (int) $customer["has_account"] === 1 ? "website account" : "existing walk-in customer";

                    // a walk-in row (no website account) gets any missing email / address filled in; accounts are never changed.
                    // An email that is already on a different record is never copied over (emails are unique).
                    if ((int) $customer["has_account"] !== 1) {
                        $fillEmail   = trim((string) $customer["email"]) === "" && $old["email"] !== "" && $match["email_free"];
                        $fillAddress = trim((string) $customer["address"]) === "" && $old["address"] !== "";
                        if ($fillEmail || $fillAddress) {
                            $stmt = $pdo->prepare("UPDATE customers SET email = ?, address = ? WHERE id = ?");
                            $stmt->execute([
                                $fillEmail ? $old["email"] : $customer["email"],
                                $fillAddress ? $old["address"] : $customer["address"],
                                $customerId,
                            ]);
                        }
                    }
                } else {
                    $stmt = $pdo->prepare("INSERT INTO customers (full_name, contact_number, email, address) VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $old["customer_name"],
                        $phone09,
                        $old["email"] !== "" ? $old["email"] : null,
                        $old["address"] !== "" ? $old["address"] : null,
                    ]);
                    $customerId   = (int) $pdo->lastInsertId();
                    $customerName = $old["customer_name"];
                    $linked       = "new walk-in customer";
                }

                $placeholder = "T" . bin2hex(random_bytes(6));

                $stmt = $pdo->prepare(
                    "INSERT INTO orders
                        (order_no, customer_id, service_id, weight_kg, press_ironing, pickup_delivery, pickup_date, total_amount, payment_status, status)
                     VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, 'waiting')"
                );
                $stmt->execute([
                    $placeholder,
                    $customerId,
                    $service["id"],
                    $weight,
                    $old["add_press"] ? 1 : 0,
                    $old["add_pickup"] ? 1 : 0,
                    $old["add_pickup"] ? $old["pickup_date"] : null,
                    $total,
                    $old["payment_status"],
                ]);

                $orderId = (int) $pdo->lastInsertId();
                $orderNo = generate_order_no($pdo);

                $stmt = $pdo->prepare("UPDATE orders SET order_no = ? WHERE id = ?");
                $stmt->execute([$orderNo, $orderId]);

                if ($validItems) {
                    $stmt = $pdo->prepare(
                        "INSERT INTO order_addon_items (order_id, category, item_name, detail, price) VALUES (?, ?, ?, ?, ?)"
                    );
                    foreach ($validItems as $item) {
                        $stmt->execute([$orderId, $item["category"], $item["name"], $item["detail"], $item["price"]]);
                    }
                }

                $pdo->commit();

                $_SESSION["order_flash"] = [
                    "id"       => $orderId,
                    "order_no" => $orderNo,
                    "customer" => $customerName,
                    "linked"   => $linked,
                    "note"     => $match["note"],
                    "total"    => $total,
                ];
                header("Location: create-order.php");
                exit;

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Something went wrong saving the order. Please try again.";
            }

        }

    }

}

$activePage = "create-order";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Order | Ash Laundromat Admin</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="admin-panel.css">
  <script src="create-order.js" defer></script>
</head>
<body class="ap-body">

  <?php require __DIR__ . "/sidebar.php"; ?>

  <div class="ap-main">
    <header class="ap-header">
      <h1>Create Laundry Order</h1>
      <p>ASH Laundry &mdash; Admin</p>
    </header>

    <main class="ap-content ap-content-narrow">

      <?php if ($success): ?>
        <p class="ap-alert ap-alert-ok">
          Order <?= htmlspecialchars($success["order_no"]) ?> created for <?= htmlspecialchars($success["customer"]) ?>
          (<?= htmlspecialchars($success["linked"]) ?>) &mdash; <?= peso($success["total"]) ?>.
          <a href="receipt.php?id=<?= (int) $success["id"] ?>" target="_blank" rel="noopener">View receipt</a>
          <?php if (!empty($success["note"])): ?>
            <small class="ap-flash-note"><?= htmlspecialchars($success["note"]) ?></small>
          <?php endif; ?>
        </p>
      <?php endif; ?>
      <?php if ($error): ?>
        <p class="ap-alert"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <form method="post" id="orderForm" class="ap-order-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">

        <section class="ap-card ap-panel">
          <h2 class="ap-section-title">Customer Registration</h2>
          <p class="ap-lookup" id="custLookup" role="status" hidden></p>

          <div class="ap-form-grid">
            <label class="ap-field">
              <span>Customer Name *</span>
              <input type="text" name="customer_name" value="<?= htmlspecialchars($old["customer_name"]) ?>" placeholder="Full name" maxlength="100" required>
            </label>

            <label class="ap-field">
              <span>Contact Number *</span>
              <input type="text" name="contact_number" value="<?= htmlspecialchars($old["contact_number"]) ?>" placeholder="09xxxxxxxxx" maxlength="20" required>
            </label>

            <label class="ap-field">
              <span>Email <em>(optional)</em></span>
              <input type="email" name="customer_email" value="<?= htmlspecialchars($old["email"]) ?>" placeholder="name@example.com" maxlength="100">
            </label>

            <label class="ap-field">
              <span>Address <em>(needed for Pickup &amp; Delivery)</em></span>
              <input type="text" name="customer_address" value="<?= htmlspecialchars($old["address"]) ?>" placeholder="House no., street, barangay, city" maxlength="255">
            </label>

            <label class="ap-field">
              <span>Service</span>
              <select name="service_id" id="serviceSelect" required>
                <?php foreach ($laundry as $s): ?>
                  <option value="<?= (int) $s["id"] ?>" data-price="<?= htmlspecialchars((string) $s["price"]) ?>" <?= (int) $old["service_id"] === (int) $s["id"] ? "selected" : "" ?>>
                    <?= htmlspecialchars($s["name"]) ?><?= $s["variant"] ? " (" . htmlspecialchars($s["variant"]) . ")" : "" ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>

            <label class="ap-field">
              <span>Weight (kg) <em id="weightRate"></em></span>
              <input type="number" name="weight" id="weightInput" value="<?= htmlspecialchars($old["weight"]) ?>" placeholder="e.g. 5" min="0.5" max="100" step="any" required>
            </label>

            <label class="ap-field">
              <span>Pickup Date</span>
              <input type="date" name="pickup_date" id="pickupDate" value="<?= htmlspecialchars($old["pickup_date"]) ?>">
            </label>

            <div class="ap-field">
              <span>Payment Status</span>
              <div class="ap-toggle">
                <label><input type="radio" name="payment_status" value="paid" <?= $old["payment_status"] === "paid" ? "checked" : "" ?>><span>Paid</span></label>
                <label><input type="radio" name="payment_status" value="unpaid" <?= $old["payment_status"] !== "paid" ? "checked" : "" ?>><span>Unpaid</span></label>
              </div>
            </div>
          </div>

          <div class="ap-checks">
            <label class="ap-check">
              <input type="checkbox" name="add_press" id="addPress" value="1" data-price="<?= $press ? htmlspecialchars((string) $press["price"]) : "0" ?>" <?= $old["add_press"] ? "checked" : "" ?> <?= $press ? "" : "disabled" ?>>
              <span><strong>Press &amp; Ironing</strong><small><?= $press ? "+" . peso($press["price"]) . "/kg" : "Not available" ?></small></span>
            </label>
            <label class="ap-check">
              <input type="checkbox" name="add_pickup" id="addPickup" value="1" data-price="<?= $pickup ? htmlspecialchars((string) $pickup["price"]) : "0" ?>" <?= $old["add_pickup"] ? "checked" : "" ?> <?= $pickup ? "" : "disabled" ?>>
              <span><strong>Pickup &amp; Delivery</strong><small><?= $pickup ? "+" . peso($pickup["price"]) : "Not available" ?></small></span>
            </label>
          </div>
        </section>

        <section class="ap-card ap-panel">
          <h2 class="ap-section-title">Add-on Items <small>(optional)</small></h2>
          <p class="ap-sub">Chosen from your Pricing list, so prices always match.</p>

          <div class="ap-item-row">
            <select id="itemCategory">
              <option value="bedding">Bedding/Linen</option>
              <option value="footwear">Footwear</option>
              <option value="special">Special Item</option>
            </select>
            <select id="itemSelect"></select>
            <input type="number" id="itemQty" placeholder="Qty">
            <button type="button" id="itemAdd" class="ap-btn-solid">+ Add</button>
          </div>

          <ul id="itemList" class="ap-item-list"></ul>
          <div id="itemHidden"></div>
        </section>

        <section class="ap-order-total">
          <div>
            <div class="ap-order-total-label">Estimated Total</div>
            <div class="ap-order-total-num" id="totalNum">&#8369;0</div>
            <div class="ap-order-total-sub" id="totalSub">0kg &times; &#8369;0 base</div>
          </div>
          <button type="submit" class="ap-btn-solid ap-btn-solid-lg">Create Order</button>
        </section>
      </form>

    </main>
  </div>

  <script>
    window.AP_ITEM_DATA = <?= json_encode($itemData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.AP_OLD_ITEMS = <?= json_encode($old["items"], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  </script>

</body>
</html>