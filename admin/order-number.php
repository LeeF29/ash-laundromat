<?php
// Order numbers: "ASH-" followed by 9 random digits, e.g. ASH-482917365.
//
// They are random instead of counting up (ASH-1001, ASH-1002 ...) so nobody can work out
// other customers' order numbers just by trying the next one. Used by both places that
// create orders: the website (api/create-order.php) and the admin (admin/create-order.php).
//
// Call it inside the same transaction that saves the order. It makes sure the number is not
// already used by another order, and tries again with a new random number if it is.
function generate_order_no(PDO $pdo, int $min = 100000000, int $max = 999999999): string
{
    $check = $pdo->prepare("SELECT 1 FROM orders WHERE order_no = ? LIMIT 1");

    for ($attempt = 0; $attempt < 20; $attempt++) {
        $candidate = "ASH-" . random_int($min, $max);

        $check->execute([$candidate]);

        if (!$check->fetch()) {
            return $candidate;
        }
    }

    // Practically impossible; the callers already turn a database error into a friendly message
    throw new PDOException("Could not find an unused order number.");
}