<?php

// No whitespace before this tag
include_once dirname(__DIR__) . '/connections.php';
session_start();
header('Content-Type: application/json');

$uid = (int)($_SESSION['ID'] ?? 0);
if ($uid <= 0) { echo json_encode(['count' => 0]); exit; }

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

$userCol = pick_col($connections, $table, ['user_id','receiver_id','to_user_id','to_id','account_id','owner_id','uid']);
$readCol = pick_col($connections, $table, ['is_read','read','seen']);

if (!$userCol) { echo json_encode(['count'=>0]); exit; }

$where = "`$userCol` = $uid";
if ($readCol) $where .= " AND `$readCol` = 0";

$sql = "SELECT COUNT(*) AS c FROM `$table` WHERE $where";
$res = mysqli_query($connections, $sql);
$row = $res ? mysqli_fetch_assoc($res) : ['c'=>0];

echo json_encode(['count'=>(int)$row['c']]);
exit;