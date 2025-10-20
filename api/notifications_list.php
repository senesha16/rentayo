<?php
// filepath: c:\xampp\htdocs\RENTayo-main\api\notifications_list.php
include_once dirname(__DIR__) . '/connections.php';
session_start();
header('Content-Type: application/json');

$uid = (int)($_SESSION['ID'] ?? 0);
if ($uid <= 0) { echo json_encode(['items'=>[]]); exit; }

$table = 'notifications';

function col_exists(mysqli $c, string $t, string $col): bool {
  $r = mysqli_query($c, "SHOW COLUMNS FROM `$t` LIKE '$col'");
  return $r && mysqli_num_rows($r) > 0;
}
function pick_col(mysqli $c, string $t, array $cands): ?string {
  foreach ($cands as $x) if (col_exists($c,$t,$x)) return $x;
  // fallback: first column containing needle (case-insensitive)
  if ($r = mysqli_query($c, "SHOW COLUMNS FROM `$t`")) {
    while ($row = mysqli_fetch_assoc($r)) {
      $name = $row['Field'];
      foreach ($cands as $x) if (stripos($name, $x) !== false) return $name;
    }
  }
  return null;
}

$idCol    = pick_col($connections, $table, ['id','notification_id','notif_id','n_id']);
$userCol  = pick_col($connections, $table, ['user_id','receiver_id','to_user_id','to_id','account_id','owner_id','uid']);
$titleCol = pick_col($connections, $table, ['title','subject']);
$bodyCol  = pick_col($connections, $table, ['body','content','details','description']);
$msgCol   = pick_col($connections, $table, ['message','text','msg']);
$linkCol  = pick_col($connections, $table, ['link','url','href']);
$readCol  = pick_col($connections, $table, ['is_read','read','seen']);
$timeCol  = pick_col($connections, $table, ['created_at','created','date','datetime','timestamp','time_created','createdOn']);

if (!$idCol) { echo json_encode(['items'=>[]]); exit; }
if (!$userCol) { echo json_encode(['items'=>[]]); exit; }

$cols = ["`$idCol`"];
if ($timeCol)  $cols[] = "`$timeCol`";
if ($titleCol) $cols[] = "`$titleCol`";
if ($bodyCol)  $cols[] = "`$bodyCol`";
if ($msgCol)   $cols[] = "`$msgCol`";
if ($linkCol)  $cols[] = "`$linkCol`";
if ($readCol)  $cols[] = "`$readCol`";

$cols_sql = implode(', ', $cols);
$sql = "SELECT $cols_sql FROM `$table` WHERE `$userCol` = $uid ORDER BY `$idCol` DESC LIMIT 20";
$q = mysqli_query($connections, $sql);

$items = [];
if ($q) {
  while ($row = mysqli_fetch_assoc($q)) {
    $text = '';
    if ($titleCol && !empty($row[$titleCol])) $text = $row[$titleCol];
    if ($bodyCol && !empty($row[$bodyCol]))   $text = $text ? "$text — {$row[$bodyCol]}" : $row[$bodyCol];
    if (!$text && $msgCol && !empty($row[$msgCol])) $text = $row[$msgCol];

    $items[] = [
      'id'         => (int)$row[$idCol],
      'text'       => $text ?: 'Notification',
      'link'       => $linkCol ? ($row[$linkCol] ?? null) : null,
      'is_read'    => $readCol ? (int)$row[$readCol] : 0,
      'created_at' => $timeCol ? ($row[$timeCol] ?? null) : null,
    ];
  }
}
echo json_encode(['items'=>$items], JSON_UNESCAPED_SLASHES);