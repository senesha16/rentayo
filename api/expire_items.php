<?php
// filepath: c:\xampp\htdocs\RENTayo-main\api\expire_items.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/connections.php';

$uid = (int)($_SESSION['ID'] ?? 0);
if ($uid <= 0) { echo json_encode(['success'=>false,'message'=>'Not authenticated']); exit; }

function col_exists(mysqli $c, string $t, string $col): bool {
  $r = mysqli_query($c, "SHOW COLUMNS FROM `$t` LIKE '$col'");
  return $r && mysqli_num_rows($r) > 0;
}
function add_col_if_missing(mysqli $c, string $t, string $name, string $def): void {
  if (!col_exists($c, $t, $name)) { @mysqli_query($c, "ALTER TABLE `$t` ADD `$name` $def"); }
}

// Ensure marker column to avoid duplicate notifications
add_col_if_missing($connections, 'items', 'expiry_notified', "TINYINT(1) NOT NULL DEFAULT 0");

@mysqli_query($connections, "CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `message` TEXT DEFAULT NULL,
  `link` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Find this lender’s items older than 30 days that we haven’t notified about
$sql = "
  SELECT item_id, title, created_at
  FROM items
  WHERE lender_id = $uid
    AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND (expiry_notified IS NULL OR expiry_notified = 0)
  ORDER BY created_at ASC
  LIMIT 50
";
$res = mysqli_query($connections, $sql);

$expired = [];
if ($res) {
  while ($row = mysqli_fetch_assoc($res)) {
    $expired[] = $row;
  }
}

$count = 0;
foreach ($expired as $it) {
  $title = trim((string)$it['title']);
  if ($title === '') $title = 'Your listing';
  $safeTitle = mysqli_real_escape_string($connections, $title);
  $msg = "Your listing '{$title}' has expired (over 30 days). Renew it to make it visible again.";
  $safeMsg = mysqli_real_escape_string($connections, $msg);
  $link = "edit_item.php?item_id=".(int)$it['item_id']."&renew=1";
  $safeLink = mysqli_real_escape_string($connections, $link);
  @mysqli_query($connections, "INSERT INTO notifications (user_id, title, message, link, is_read)
                               VALUES ($uid, 'Listing expired', '$safeMsg', '$safeLink', 0)");
  @mysqli_query($connections, "UPDATE items SET expiry_notified = 1 WHERE item_id = ".(int)$it['item_id']." LIMIT 1");
  $count++;
}

echo json_encode(['success'=>true, 'notified'=>$count]);