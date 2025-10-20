<?php
// Important: no whitespace before <?php
include("connections.php");
session_start();
header('Content-Type: application/json');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Helpers
function table_has_col(mysqli $conn, string $table, string $col): bool {
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$col'");
    return $res && mysqli_num_rows($res) > 0;
}
function ensure_rent_orders(mysqli $conn) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `rent_orders` (
      `order_id` INT AUTO_INCREMENT PRIMARY KEY,
      `item_id` INT NOT NULL,
      `lender_id` INT NOT NULL,
      `renter_id` INT NOT NULL,
      `days` INT NOT NULL,
      `amount_total` DECIMAL(10,2) NOT NULL,
      `payment_method` ENUM('cash','gcash') NOT NULL,
      `gcash_ref` VARCHAR(64) DEFAULT NULL,
      `delivery_method` ENUM('pickup','delivery') NOT NULL,
      `delivery_address` TEXT DEFAULT NULL,
      `status` ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY `item_id` (`item_id`),
      KEY `lender_id` (`lender_id`),
      KEY `renter_id` (`renter_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function ensure_rental_payments(mysqli $conn) {
    // Base table (some installs already have this without reference_no)
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `rental_payments` (
      `payment_id` INT AUTO_INCREMENT PRIMARY KEY,
      `rental_id` INT NOT NULL,
      `amount` DECIMAL(10,2) NOT NULL,
      `method` ENUM('cash','gcash') NOT NULL,
      `status` ENUM('pending','paid','failed','submitted') NOT NULL DEFAULT 'pending',
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY `rental_id` (`rental_id`),
      KEY `method` (`method`),
      KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Add reference_no if missing
    if (!table_has_col($conn, 'rental_payments', 'reference_no')) {
        @mysqli_query($conn, "ALTER TABLE `rental_payments` ADD COLUMN `reference_no` VARCHAR(64) NULL AFTER `method`");
    }
}
function ensure_items_status(mysqli $conn) {
    $col = mysqli_query($conn, "SHOW COLUMNS FROM Items LIKE 'status'");
    if (!$col || mysqli_num_rows($col) === 0) {
        mysqli_query($conn, "ALTER TABLE Items ADD COLUMN `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'");
    }
}
function get_stock_column(mysqli $conn): ?string {
    foreach (['available_items_count','available_count','quantity','stock'] as $c) {
        if (table_has_col($conn, 'Items', $c)) return $c;
    }
    return null;
}
function insert_notification(mysqli $conn, int $userId, string $title, string $body, string $link = null) {
    // Make notifications table if missing (with title/body)
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `notifications` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NOT NULL,
      `title` VARCHAR(150) NOT NULL,
      `body` TEXT NULL,
      `link` VARCHAR(255) NULL,
      `is_read` TINYINT(1) NOT NULL DEFAULT 0,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY `user_id` (`user_id`),
      KEY `is_read` (`is_read`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Adapt to existing schemas
    $hasTitle = table_has_col($conn, 'notifications', 'title');
    $hasBody  = table_has_col($conn, 'notifications', 'body');
    $hasMsg   = table_has_col($conn, 'notifications', 'message');
    $hasLink  = table_has_col($conn, 'notifications', 'link');

    if ($hasTitle && $hasBody) {
        if ($hasLink) {
            $stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, title, body, link) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "isss", $userId, $title, $body, $link);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, title, body) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iss", $userId, $title, $body);
        }
    } elseif ($hasMsg) {
        if ($hasLink) {
            $stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iss", $userId, $body, $link);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "is", $userId, $body);
        }
    } else {
        if (!table_has_col($conn, 'notifications', 'message')) {
            @mysqli_query($conn, "ALTER TABLE notifications ADD COLUMN `message` TEXT NULL");
        }
        if ($hasLink) {
            $stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iss", $userId, $body, $link);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "is", $userId, $body);
        }
    }
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
    }
    if (empty($_SESSION['ID'])) {
        echo json_encode(['success' => false, 'message' => 'Please log in first.']); exit;
    }

    ensure_rent_orders($connections);
    ensure_rental_payments($connections);
    ensure_items_status($connections);

    // Payload
    $renter_id = (int)$_SESSION['ID'];
    $item_id   = (int)($_POST['item_id'] ?? 0);
    $lender_id = (int)($_POST['lender_id'] ?? 0);
    $days      = (int)($_POST['days'] ?? 1);
    $payment_method   = ($_POST['payment_method'] ?? '') === 'gcash' ? 'gcash' : 'cash';
    $gcash_ref        = trim($_POST['gcash_ref'] ?? '');
    $delivery_method  = ($_POST['delivery_method'] ?? '') === 'delivery' ? 'delivery' : 'pickup';
    $delivery_address = trim($_POST['delivery_address'] ?? '');

    // Validate
    if ($item_id <= 0 || $lender_id <= 0 || $days < 1 || $days > 60) {
        echo json_encode(['success' => false, 'message' => 'Invalid rental details.']); exit;
    }
    if ($renter_id === $lender_id) {
        echo json_encode(['success' => false, 'message' => 'You cannot rent your own item.']); exit;
    }
    if ($payment_method === 'gcash') {
        if (!preg_match('/^[A-Za-z0-9\-]{6,20}$/', $gcash_ref)) {
            echo json_encode(['success' => false, 'message' => 'Invalid GCash reference number.']); exit;
        }
    } else {
        $gcash_ref = '';
    }
    if ($delivery_method === 'delivery' && strlen($delivery_address) < 8) {
        echo json_encode(['success' => false, 'message' => 'Please provide a delivery address.']); exit;
    }

    // Start transaction to lock stock + insert order + payment
    mysqli_begin_transaction($connections);

    // Detect stock column
    $stockCol = get_stock_column($connections);

    // Lock item row and verify status and stock
    $selCols = "price_per_day, lender_id, status" . ($stockCol ? ", `$stockCol` AS stock" : "");
    $itRes = mysqli_query($connections, "SELECT $selCols FROM Items WHERE item_id = $item_id FOR UPDATE");
    $it = $itRes ? mysqli_fetch_assoc($itRes) : null;

    if (!$it) { mysqli_rollback($connections); echo json_encode(['success'=>false,'message'=>'Item not found.']); exit; }
    if ((int)$it['lender_id'] !== $lender_id) {
        mysqli_rollback($connections); echo json_encode(['success'=>false,'message'=>'Invalid item owner.']); exit;
    }
    if ($it['status'] !== 'approved') {
        mysqli_rollback($connections); echo json_encode(['success'=>false,'message'=>'This item is not available for renting yet.']); exit;
    }

    // If we track stock, make sure there is at least 1 available and decrement it
    $inventoryNote = '';
    if ($stockCol) {
        $currentStock = (int)$it['stock'];
        if ($currentStock < 1) {
            mysqli_rollback($connections);
            echo json_encode(['success'=>false,'message'=>'This item is out of stock.']); exit;
        }
        // Decrement atomically
        $updSql = "UPDATE Items SET `$stockCol` = `$stockCol` - 1 WHERE item_id = ? AND `$stockCol` > 0";
        $upd = mysqli_prepare($connections, $updSql);
        mysqli_stmt_bind_param($upd, "i", $item_id);
        mysqli_stmt_execute($upd);
        if (mysqli_stmt_affected_rows($upd) !== 1) {
            mysqli_stmt_close($upd);
            mysqli_rollback($connections);
            echo json_encode(['success'=>false,'message'=>'Failed to reserve stock for this item.']); exit;
        }
        mysqli_stmt_close($upd);
    } else {
        $inventoryNote = ' (Note: stock column not found, availability not reduced.)';
    }

    $amount_total = (float)$it['price_per_day'] * $days;

    // Insert order
    $sql = "INSERT INTO rent_orders
            (item_id, lender_id, renter_id, `days`, amount_total, payment_method, gcash_ref, delivery_method, delivery_address)
            VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, NULLIF(?, ''))";
    $stmt = mysqli_prepare($connections, $sql);
    mysqli_stmt_bind_param($stmt, "iiiidssss",
        $item_id, $lender_id, $renter_id, $days, $amount_total,
        $payment_method, $gcash_ref, $delivery_method, $delivery_address
    );
    mysqli_stmt_execute($stmt);
    $order_id = mysqli_insert_id($connections);
    mysqli_stmt_close($stmt);

    // Record payment row
    $status = $payment_method === 'gcash' ? 'submitted' : 'pending';
    if (table_has_col($connections, 'rental_payments', 'reference_no')) {
        $ps = mysqli_prepare($connections, "INSERT INTO rental_payments (rental_id, amount, method, reference_no, status) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($ps, "idsss", $order_id, $amount_total, $payment_method, $gcash_ref, $status);
    } else {
        $ps = mysqli_prepare($connections, "INSERT INTO rental_payments (rental_id, amount, method, status) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($ps, "idss", $order_id, $amount_total, $payment_method, $status);
    }
    mysqli_stmt_execute($ps);
    mysqli_stmt_close($ps);

    // Commit atomic operations (stock + order + payment)
    mysqli_commit($connections);

    // Notify lender (ignore notification failure; order is already committed)
    $rname = '';
    if ($u = mysqli_query($connections, "SELECT username FROM users WHERE ID=$renter_id LIMIT 1")) {
        if ($ur = mysqli_fetch_assoc($u)) $rname = $ur['username'];
    }
    $title = "New rental request";
    $body  = ($rname ?: 'Someone')." wants to rent your item #$item_id for $days day(s). Total ₱".number_format($amount_total,2);
    $link  = "item_details.php?item_id=".$item_id."#orders";
    try { insert_notification($connections, $lender_id, $title, $body, $link); } catch (Throwable $__) {}

    echo json_encode(['success' => true, 'message' => 'Rental request submitted.' . $inventoryNote, 'order_id' => $order_id]);
} catch (Throwable $e) {
    // Rollback in case of any error during transaction
    if (mysqli_errno($connections)) { @mysqli_rollback($connections); }
    echo json_encode(['success' => false, 'message' => 'Submit failed: '.$e->getMessage()]);
}