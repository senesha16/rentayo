<?php
// filepath: c:\xampp\htdocs\RENTayo-main\api\notifications_count.php
include_once dirname(__DIR__) . '/connections.php';
session_start();
header('Content-Type: application/json');

$uid = (int)($_SESSION['ID'] ?? 0);
if ($uid <= 0) { echo json_encode(['count' => 0]); exit; }

$hasIsRead = false;
if ($r = mysqli_query($connections, "SHOW COLUMNS FROM `notifications` LIKE 'is_read'")) {
    $hasIsRead = mysqli_num_rows($r) > 0;
}

$sql = "SELECT COUNT(*) AS c FROM `notifications` WHERE `user_id` = $uid";
if ($hasIsRead) $sql .= " AND `is_read` = 0";

$res = mysqli_query($connections, $sql);
$row = $res ? mysqli_fetch_assoc($res) : ['c' => 0];

echo json_encode(['count' => (int)$row['c']]);
exit;

// filepath: c:\xampp\htdocs\RENTayo-main\api\notifications_list.php
include_once dirname(__DIR__) . '/connections.php';
session_start();
header('Content-Type: application/json');

$uid = (int)($_SESSION['ID'] ?? 0);
if ($uid <= 0) { echo json_encode(['items'=>[]]); exit; }

function table_has_col(mysqli $conn, string $table, string $col): bool {
    $r = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$col'");
    return $r && mysqli_num_rows($r) > 0;
}

$hasTitle = table_has_col($connections, 'notifications', 'title');
$hasBody  = table_has_col($connections, 'notifications', 'body');
$hasMsg   = table_has_col($connections, 'notifications', 'message');
$hasLink  = table_has_col($connections, 'notifications', 'link');
$hasRead  = table_has_col($connections, 'notifications', 'is_read');

$cols = "id";
if (table_has_col($connections, 'notifications', 'created_at')) $cols .= ", created_at";
if ($hasTitle) $cols .= ", title";
if ($hasBody)  $cols .= ", body";
if ($hasMsg)   $cols .= ", message";
if ($hasLink)  $cols .= ", link";
if ($hasRead)  $cols .= ", is_read";

$q = mysqli_query($connections, "SELECT $cols FROM `notifications` WHERE `user_id` = $uid ORDER BY `id` DESC LIMIT 10");
$items = [];
if ($q) {
    while ($row = mysqli_fetch_assoc($q)) {
        $text = '';
        if ($hasTitle && !empty($row['title'])) $text = $row['title'];
        if ($hasBody && !empty($row['body'])) $text = $text ? "$text — {$row['body']}" : $row['body'];
        if (!$text && $hasMsg && !empty($row['message'])) $text = $row['message'];
        $items[] = [
            'id' => (int)$row['id'],
            'text' => $text ?: 'Notification',
            'link' => $hasLink ? ($row['link'] ?? null) : null,
            'is_read' => $hasRead ? (int)$row['is_read'] : 0,
            'created_at' => $row['created_at'] ?? null,
        ];
    }
}
echo json_encode(['items' => $items]);
exit;

// filepath: c:\xampp\htdocs\RENTayo-main\api\notifications_mark_read.php
<?php
include_once dirname(__DIR__) . '/connections.php';
session_start();
header('Content-Type: application/json');

$uid = (int)($_SESSION['ID'] ?? 0);
if ($uid <= 0) { echo json_encode(['ok'=>false]); exit; }

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

$table = 'notifications';
$idCol   = pick_col($connections, $table, ['id','notification_id','notif_id','n_id']);
$userCol = pick_col($connections, $table, ['user_id','receiver_id','to_user_id','to_id','uid','account_id','owner_id']);
$readCol = pick_col($connections, $table, ['is_read','read','seen']);

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

// Mark-all when no id
if ($id <= 0 || !$idCol) {
  if ($userCol && $readCol) {
    @mysqli_query($connections, "UPDATE `$table` SET `$readCol` = 1 WHERE `$userCol` = $uid AND `$readCol` = 0");
  }
  echo json_encode(['ok'=>true]); exit;
}

// Resolve chat URL for this single notification
$linkCol   = pick_col($connections, $table, ['link','url','href']);
$titleCol  = pick_col($connections, $table, ['title','subject']);
$bodyCol   = pick_col($connections, $table, ['message','body','content','details','description','text']);
$renterCol = pick_col($connections, $table, ['renter_id','borrower_id']);
$lenderCol = pick_col($connections, $table, ['lender_id','owner_id','seller_id']);
$senderCol = pick_col($connections, $table, ['sender_id','from_user_id','from_id','sender']);
$recvCol   = pick_col($connections, $table, ['receiver_id','to_user_id','to_id','user_to']);
$itemCol   = pick_col($connections, $table, ['item_id','product_id']);
$rentalCol = pick_col($connections, $table, ['rental_id','order_id','booking_id']);

$cols = ["`$idCol`","`$userCol`"];
foreach ([$readCol,$linkCol,$titleCol,$bodyCol,$renterCol,$lenderCol,$senderCol,$recvCol,$itemCol,$rentalCol] as $c)
  if ($c) $cols[] = "`$c`";

$sql = "SELECT ".implode(',', $cols)." FROM `$table` WHERE `$idCol` = $id AND `$userCol` = $uid LIMIT 1";
$q = mysqli_query($connections, $sql);
if (!$q || !($row = mysqli_fetch_assoc($q))) {
  echo json_encode(['ok'=>true, 'url'=>'messaging.php']); exit;
}

// Mark this one read
if ($readCol) {
  @mysqli_query($connections, "UPDATE `$table` SET `$readCol` = 1 WHERE `$userCol` = $uid AND `$idCol` = $id");
}

// Derive other user -> chat.php
$link      = $linkCol   ? trim((string)($row[$linkCol] ?? '')) : '';
$renter_id = $renterCol ? (int)$row[$renterCol] : 0;
$lender_id = $lenderCol ? (int)$row[$lenderCol] : 0;
$sender_id = $senderCol ? (int)$row[$senderCol] : 0;
$to_id     = $recvCol   ? (int)$row[$recvCol]   : 0;
$item_id   = $itemCol   ? (int)$row[$itemCol]   : 0;
$rental_id = $rentalCol ? (int)$row[$rentalCol] : 0;

// Try to extract query ids from link if any
if ($link) {
  $parts = parse_url($link);
  if (!empty($parts['query'])) {
    parse_str($parts['query'], $qv);
    if (!$item_id   && !empty($qv['item_id']))   $item_id   = (int)$qv['item_id'];
    if (!$rental_id && !empty($qv['rental_id'])) $rental_id = (int)$qv['rental_id'];
  }
}

$other = 0;
if ($renter_id && $lender_id) {
  $other = ($uid === $renter_id) ? $lender_id : $renter_id;
} elseif ($sender_id && $to_id) {
  $other = ($uid === $sender_id) ? $to_id : $sender_id;
} elseif ($sender_id && $sender_id != $uid) {
  $other = $sender_id;
} elseif ($lender_id && $lender_id != $uid) {
  $other = $lender_id;
} elseif ($renter_id && $renter_id != $uid) {
  $other = $renter_id;
}

$url = 'messaging.php';
if ($other > 0) {
  $url = 'chat.php?user_id=' . $other;
  if ($item_id)   $url .= '&item_id=' . $item_id;
  if ($rental_id) $url .= '&rental_id=' . $rental_id;
} elseif ($link) {
  $url = $link;
}

echo json_encode(['ok'=>true, 'url'=>$url]);