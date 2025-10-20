<?php
// filepath: c:\xampp\htdocs\RENTayo-main\api\renew_item.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/connections.php';

$uid = (int)($_SESSION['ID'] ?? 0);
$item_id = (int)($_POST['item_id'] ?? 0);
if ($uid <= 0 || $item_id <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid request']); exit; }

// Ensure marker column exists
function col_exists(mysqli $c, string $t, string $col): bool {
  $r = mysqli_query($c, "SHOW COLUMNS FROM `$t` LIKE '$col'");
  return $r && mysqli_num_rows($r) > 0;
}
if (!col_exists($connections, 'items', 'expiry_notified')) {
  @mysqli_query($connections, "ALTER TABLE `items` ADD `expiry_notified` TINYINT(1) NOT NULL DEFAULT 0");
}

// Verify ownership
$own = mysqli_query($connections, "SELECT lender_id FROM items WHERE item_id = $item_id LIMIT 1");
if (!$own || !($r = mysqli_fetch_assoc($own)) || (int)$r['lender_id'] !== $uid) {
  echo json_encode(['success'=>false,'message'=>'Not allowed']); exit;
}

// Renew by bumping created_at and clearing notify flag
$ok = mysqli_query($connections, "UPDATE items SET created_at = NOW(), expiry_notified = 0 WHERE item_id = $item_id LIMIT 1");
if (!$ok) { echo json_encode(['success'=>false,'message'=>'Failed to renew']); exit; }

$exp = date('Y-m-d H:i:s', time() + 30*86400);
echo json_encode(['success'=>true, 'message'=>'Listing renewed for 30 days.', 'expires_at'=>$exp]);