<?php
require __DIR__ . "/session-start.php";
require __DIR__ . "/db.php";

if (!empty($_SESSION["admin_id"])) {
    header("Location: dashboard.php");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$error = "";
$MAX_ATTEMPTS    = 5;
$LOCKOUT_MINUTES = 15;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
        $error = "Your session expired. Please try again.";
    } else {

        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";

        if ($username === "" || $password === "") {
            $error = "Please enter both your username and password.";
        } else {

            $stmt = $pdo->prepare(
                "SELECT id, username, password_hash, failed_attempts, locked_until
                 FROM admin_users WHERE username = ? LIMIT 1"
            );
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && $admin["locked_until"] && strtotime($admin["locked_until"]) > time()) {

                $minutesLeft = (int) ceil((strtotime($admin["locked_until"]) - time()) / 60);
                $error = "Too many failed attempts. Try again in {$minutesLeft} minute"
                        . ($minutesLeft === 1 ? "" : "s") . ".";

            } elseif ($admin && password_verify($password, $admin["password_hash"])) {

                $reset = $pdo->prepare(
                    "UPDATE admin_users SET failed_attempts = 0, locked_until = NULL WHERE id = ?"
                );
                $reset->execute([$admin["id"]]);

                session_regenerate_id(true);

                $_SESSION["admin_id"]   = $admin["id"];
                $_SESSION["admin_name"] = $admin["username"];
                $_SESSION["admin_role"] = "admin";

                header("Location: dashboard.php");
                exit;

            } else {

                $error = "Incorrect username or password.";

                if ($admin) {
                    $attempts = $admin["failed_attempts"] + 1;

                    if ($attempts >= $MAX_ATTEMPTS) {
                        $lockUntil = date("Y-m-d H:i:s", time() + $LOCKOUT_MINUTES * 60);
                        $upd = $pdo->prepare(
                            "UPDATE admin_users SET failed_attempts = ?, locked_until = ? WHERE id = ?"
                        );
                        $upd->execute([$attempts, $lockUntil, $admin["id"]]);
                        $error = "Too many failed attempts. Your account is locked for {$LOCKOUT_MINUTES} minutes.";
                    } else {
                        $upd = $pdo->prepare(
                            "UPDATE admin_users SET failed_attempts = ? WHERE id = ?"
                        );
                        $upd->execute([$attempts, $admin["id"]]);
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | Ash Laundromat</title>
  <link rel="stylesheet" href="admin.css">
  <link rel="stylesheet" href="admin-login.css">
</head>
<body class="admin-login-page">
  <!-- ===== Background scene: picture, bubbles, waves (decoration only) ===== -->
  <div class="al-scene" aria-hidden="true">
    <span class="al-bubble" style="--x:6%;--s:44px;--d:15s;--t:-0s;--y:62%"></span>
    <span class="al-bubble" style="--x:14%;--s:22px;--d:11s;--t:-3s;--y:30%"></span>
    <span class="al-bubble" style="--x:23%;--s:64px;--d:19s;--t:-6s;--y:78%"></span>
    <span class="al-bubble" style="--x:33%;--s:30px;--d:13s;--t:-1s;--y:44%"></span>
    <span class="al-bubble" style="--x:44%;--s:18px;--d:10s;--t:-8s;--y:20%"></span>
    <span class="al-bubble" style="--x:52%;--s:54px;--d:17s;--t:-4s;--y:70%"></span>
    <span class="al-bubble" style="--x:61%;--s:26px;--d:12s;--t:-9s;--y:36%"></span>
    <span class="al-bubble" style="--x:70%;--s:72px;--d:21s;--t:-2s;--y:58%"></span>
    <span class="al-bubble" style="--x:79%;--s:34px;--d:14s;--t:-7s;--y:26%"></span>
    <span class="al-bubble" style="--x:88%;--s:48px;--d:16s;--t:-5s;--y:66%"></span>
    <span class="al-bubble" style="--x:95%;--s:20px;--d:10s;--t:-11s;--y:48%"></span>
    <div class="al-waves">
      <svg class="al-wave al-wave-2" viewBox="0 0 1200 160" preserveAspectRatio="none"><path d="M0,80 C100,40 200,40 300,80 C400,120 500,120 600,80 C700,40 800,40 900,80 C1000,120 1100,120 1200,80 L1200,160 L0,160 Z"/></svg>
      <svg class="al-wave al-wave-1" viewBox="0 0 1200 160" preserveAspectRatio="none"><path d="M0,70 C100,110 200,110 300,70 C400,30 500,30 600,70 C700,110 800,110 900,70 C1000,30 1100,30 1200,70 L1200,160 L0,160 Z"/></svg>
    </div>
  </div>

  <main class="admin-login-card">
    <!-- ===== Little washing drum (decoration only) ===== -->
    <div class="al-drum" aria-hidden="true">
      <svg viewBox="0 0 64 64">
        <defs><clipPath id="alDrumClip"><circle cx="32" cy="32" r="23"/></clipPath></defs>
        <circle cx="32" cy="32" r="29" fill="#ffffff" stroke="#003b73" stroke-width="3"/>
        <circle cx="32" cy="32" r="23" fill="#e8f6fc"/>
        <g clip-path="url(#alDrumClip)">
          <g class="al-drum-water"><path d="M-28,36 q7,-6 14,0 t14,0 t14,0 t14,0 t14,0 t14,0 t14,0 L110,70 L-28,70 Z" fill="#00a8e8"/></g>
          <g class="al-drum-spin"><circle cx="32" cy="32" r="17" fill="none" stroke="#ffffff" stroke-width="3" stroke-dasharray="7 11" stroke-linecap="round" opacity="0.85"/></g>
          <g fill="#ffffff"><circle class="al-drum-dot" cx="25" cy="46" r="2.4"/><circle class="al-drum-dot" cx="34" cy="48" r="1.8"/><circle class="al-drum-dot" cx="40" cy="45" r="2.1"/></g>
        </g>
        <path d="M17,25 A16,16 0 0 1 28,16" fill="none" stroke="#ffffff" stroke-width="3" stroke-linecap="round" opacity="0.9"/>
        <circle cx="52" cy="12" r="2.4" fill="#ffd166"/>
      </svg>
    </div>

    <h1>Ash Laundromat</h1>
    <p class="admin-login-sub">Employee / Admin Login</p>

    <?php if ($error): ?>
      <p class="admin-login-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" action="login.php" class="admin-login-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" required autocomplete="username" autofocus>
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
      <button type="submit">Log In</button>
    </form>
  </main>
</body>
</html>