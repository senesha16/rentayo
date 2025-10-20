<?php
// filepath: c:\xampp\htdocs\RENTayo-main\api\notifications_resolve.php
include_once dirname(__DIR__) . '/connections.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

$uid = (int)($_SESSION['ID'] ?? 0);
$id  = (int)($_POST['id'] ?? 0);
if ($uid <= 0 || $id <= 0) { echo json_encode(['success'=>false, 'url'=>'messaging.php', 'text'=>'Notification']); exit; }

$table = 'notifications';

function col_exists(mysqli $c, string $t, string $col): bool {
  $r = mysqli_query($c, "SHOW COLUMNS FROM `$t` LIKE '$col'");
  return $r && mysqli_num_rows($r) > 0;
}
function pick_col(mysqli $c, string $t, array $cands): ?string {
  foreach ($cands as $x) if (col_exists($c,$t,$x)) return $x;
  if ($r = mysqli_query($c, "SHOW COLUMNS FROM `$t`")) {
    while ($row = mysqli_fetch_assoc($r)) {
      $name = $row['Field'];
      foreach ($cands as $x) if (stripos($name, $x) !== false) return $name;
    }
  }
  return null;
}
function get_item_title(mysqli $c, int $item_id): ?string {
  if ($item_id <= 0) return null;
  $q = mysqli_query($c, "SELECT title FROM items WHERE item_id = $item_id LIMIT 1");
  if ($q && ($r = mysqli_fetch_assoc($q))) return (string)$r['title'];
  return null;
}

// Detect columns
$idCol     = pick_col($connections, $table, ['id','notification_id','notif_id','n_id']);
$userCol   = pick_col($connections, $table, ['user_id','receiver_id','to_user_id','to_id','account_id','owner_id','uid']);
$linkCol   = pick_col($connections, $table, ['link','url','href']);
$titleCol  = pick_col($connections, $table, ['title','subject']);
$bodyCol   = pick_col($connections, $table, ['message','body','content','details','description','text']);
$renterCol = pick_col($connections, $table, ['renter_id','borrower_id']);
$lenderCol = pick_col($connections, $table, ['lender_id','owner_id','seller_id']);
$senderCol = pick_col($connections, $table, ['sender_id','from_user_id','from_id','sender']);
$recvCol   = pick_col($connections, $table, ['receiver_id','to_user_id','to_id','user_to']);
$itemCol   = pick_col($connections, $table, ['item_id','product_id']);
$rentalCol = pick_col($connections, $table, ['rental_id','order_id','booking_id']);

if (!$idCol || !$userCol) { echo json_encode(['success'=>false, 'url'=>'messaging.php', 'text'=>'Notification']); exit; }

// Build select
$sel = ["`$idCol`", "`$userCol`"];
foreach ([$linkCol,$titleCol,$bodyCol,$renterCol,$lenderCol,$senderCol,$recvCol,$itemCol,$rentalCol] as $c) if ($c) $sel[] = "`$c`";
$sql = "SELECT ".implode(',', $sel)." FROM `$table` WHERE `$idCol` = $id AND `$userCol` = $uid LIMIT 1";
$q = mysqli_query($connections, $sql);
if (!$q || !($row = mysqli_fetch_assoc($q))) {
  echo json_encode(['success'=>false, 'url'=>'messaging.php', 'text'=>'Notification']); exit;
}

// Raw fields
$link      = $linkCol   ? trim((string)($row[$linkCol] ?? '')) : '';
$title     = $titleCol  ? trim((string)($row[$titleCol] ?? '')) : '';
$body      = $bodyCol   ? trim((string)($row[$bodyCol] ?? '')) : '';
$renter_id = $renterCol ? (int)$row[$renterCol] : 0;
$lender_id = $lenderCol ? (int)$row[$lenderCol] : 0;
$sender_id = $senderCol ? (int)$row[$senderCol] : 0;
$to_id     = $recvCol   ? (int)$row[$recvCol]   : 0;
$item_id   = $itemCol   ? (int)$row[$itemCol]   : 0;
$rental_id = $rentalCol ? (int)$row[$rentalCol] : 0;

// Derive other user
$other = 0;
if ($renter_id && $lender_id)      $other = ($uid === $renter_id) ? $lender_id : $renter_id;
elseif ($sender_id && $to_id)       $other = ($uid === $sender_id) ? $to_id : $sender_id;
elseif ($sender_id && $sender_id!=$uid) $other = $sender_id;

// If link already points to chat with user_id, prefer it
if ($link) {
  $parts = parse_url($link);
  if (!empty($parts['query'])) {
    parse_str($parts['query'], $qv);
    if (!empty($qv['user_id'])) $other = (int)$qv['user_id'];
    if (!$item_id && !empty($qv['item_id'])) $item_id = (int)$qv['item_id'];
    if (!$rental_id && !empty($qv['rental_id'])) $rental_id = (int)$qv['rental_id'];
  }
}

// Resolve item title and improve text
$item_title = get_item_title($connections, $item_id);
$display = $title ?: $body ?: 'Notification';
if ($item_title) {
  $display = preg_replace('/item\s*#?\s*'.preg_quote((string)$item_id, '/').'\b/i', $item_title, $display);
  if (stripos($display, $item_title) === false) {
    $display = ($display ? "$display — " : '') . $item_title;
  }
}

// Final URL
$url = 'messaging.php';
if ($other > 0) {
  $url = 'chat.php?user_id=' . $other;
  if ($item_id)   $url .= '&item_id=' . $item_id;
  if ($rental_id) $url .= '&rental_id=' . $rental_id;
} elseif ($link) {
  $url = $link;
}

echo json_encode(['success'=>true, 'url'=>$url, 'text'=>$display], JSON_UNESCAPED_SLASHES);