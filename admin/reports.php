<?php
include("../connections.php");
session_start();

// Admin gate
if (empty($_SESSION['ID'])) { header("Location: ../login.php"); exit; }
$uid = (int)$_SESSION['ID'];
$chk = mysqli_query($connections, "SELECT is_admin FROM users WHERE ID = $uid LIMIT 1");
$row = $chk ? mysqli_fetch_assoc($chk) : null;
if (!$row || (int)$row['is_admin'] !== 1) { header("Location: ../login.php"); exit; }

// Ensure tables/columns exist
mysqli_query($connections, "CREATE TABLE IF NOT EXISTS `reports` (
  `report_id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_id` INT NULL,
  `reported_user_id` INT NOT NULL,
  `reporter_id` INT NOT NULL,
  `reason` VARCHAR(50) NOT NULL,
  `details` TEXT NULL,
  `status` ENUM('pending','resolved') NOT NULL DEFAULT 'pending',
  `action_taken` VARCHAR(30) DEFAULT NULL,
  `admin_note` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($connections, "ALTER TABLE users ADD COLUMN IF NOT EXISTS `is_banned` TINYINT(1) NOT NULL DEFAULT 0");

// Actions
$msg = $err = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_id = (int)($_POST['report_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $admin_note = trim($_POST['admin_note'] ?? '');

    $repRes = mysqli_query($connections, "SELECT * FROM reports WHERE report_id=$report_id");
    $rep = $repRes ? mysqli_fetch_assoc($repRes) : null;
    if (!$rep) {
        $err = "Report not found.";
    } else {
        if ($action === 'ban_user') {
            $uid2 = (int)$rep['reported_user_id'];
            mysqli_query($connections, "UPDATE users SET is_banned=1 WHERE ID=$uid2");
            mysqli_query($connections, "UPDATE reports SET status='resolved', action_taken='ban_user', admin_note='".mysqli_real_escape_string($connections,$admin_note)."', resolved_at=NOW() WHERE report_id=$report_id");
            $msg = "User banned.";
        } elseif ($action === 'delete_item') {
            $iid = (int)$rep['item_id'];
            if ($iid > 0) {
                // best-effort related deletes
                mysqli_query($connections, "DELETE FROM item_images WHERE item_id=$iid");
                mysqli_query($connections, "DELETE FROM itemcategories WHERE item_id=$iid");
                mysqli_query($connections, "DELETE FROM items WHERE item_id=$iid");
            }
            mysqli_query($connections, "UPDATE reports SET status='resolved', action_taken='delete_item', admin_note='".mysqli_real_escape_string($connections,$admin_note)."', resolved_at=NOW() WHERE report_id=$report_id");
            $msg = "Item deleted.";
        } elseif ($action === 'resolve') {
            mysqli_query($connections, "UPDATE reports SET status='resolved', action_taken='none', admin_note='".mysqli_real_escape_string($connections,$admin_note)."', resolved_at=NOW() WHERE report_id=$report_id");
            $msg = "Report marked resolved.";
        } else {
            $err = "Unknown action.";
        }
    }
}

// Fetch reports with item titles and usernames
$q = "
SELECT r.*, 
       i.title AS item_title,
       u1.username AS reported_username,
       u2.username AS reporter_username
FROM reports r
LEFT JOIN items i ON i.item_id = r.item_id
LEFT JOIN users u1 ON u1.ID = r.reported_user_id
LEFT JOIN users u2 ON u2.ID = r.reporter_id
ORDER BY r.status='pending' DESC, r.created_at DESC
";
$res = mysqli_query($connections, $q);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin • Reports</title>
  <style>
    body{font-family: Arial, sans-serif; background:#f5f7fb; margin:0;}
    .wrap{max-width:1100px;margin:30px auto;background:#fff;padding:20px;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.06);}
    table{width:100%;border-collapse:collapse;}
    th,td{padding:10px;border-bottom:1px solid #eee;vertical-align:top;}
    th{background:#f9fafb;text-align:left;}
    .status{padding:2px 8px;border-radius:10px;font-size:12px;display:inline-block;}
    .pending{background:#fef3c7;color:#92400e;}
    .resolved{background:#d1fae5;color:#065f46;}
    .actions{display:flex;gap:6px;flex-wrap:wrap; align-items:center;}
    .btn{padding:6px 10px;border:none;border-radius:8px;cursor:pointer;text-decoration:none;display:inline-block}
    .ban{background:#fee2e2;color:#991b1b;}
    .del{background:#fde68a;color:#92400e;}
    .res{background:#d1fae5;color:#065f46;}
    .view{background:#e0e7ff;color:#1e1b4b;}
    .note{width:180px}
    .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
    .msg{padding:8px;border-radius:8px;margin-bottom:10px}
    .ok{background:#d1fae5;color:#065f46}.err{background:#fee2e2;color:#991b1b}
  </style>
</head>
<body>
<?php include __DIR__ . '/admin_navbar.php'; ?>
<div class="wrap">
  <div class="topbar"><h2>Reports</h2></div>
  <?php if($msg):?><div class="msg ok"><?php echo htmlspecialchars($msg);?></div><?php endif;?>
  <?php if($err):?><div class="msg err"><?php echo htmlspecialchars($err);?></div><?php endif;?>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>When</th>
        <th>Status</th>
        <th>Reason</th>
        <th>Item</th>
        <th>Reported User</th>
        <th>Reporter</th>
        <th>Details</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php while($r = mysqli_fetch_assoc($res)): ?>
      <tr>
        <td><?php echo (int)$r['report_id']; ?></td>
        <td><?php echo htmlspecialchars($r['created_at']); ?></td>
        <td><span class="status <?php echo $r['status']; ?>"><?php echo ucfirst($r['status']); ?></span></td>
        <td><?php echo htmlspecialchars($r['reason']); ?></td>
        <td>
          <?php if (!empty($r['item_id'])): ?>
            #<?php echo (int)$r['item_id']; ?> — <?php echo htmlspecialchars($r['item_title'] ?? ''); ?>
            <div style="margin-top:6px;">
              <a class="btn view" href="<?php echo '../item_details.php?item_id='.(int)$r['item_id']; ?>" target="_blank" rel="noopener">View item</a>
            </div>
          <?php else: ?>
            -
          <?php endif; ?>
        </td>
        <td><?php echo '#'.(int)$r['reported_user_id'].' '.htmlspecialchars($r['reported_username'] ?? ''); ?></td>
        <td><?php echo '#'.(int)$r['reporter_id'].' '.htmlspecialchars($r['reporter_username'] ?? ''); ?></td>
        <td style="max-width:260px"><?php echo nl2br(htmlspecialchars($r['details'] ?? '')); ?></td>
        <td>
          <form method="post" class="actions">
            <input type="hidden" name="report_id" value="<?php echo (int)$r['report_id']; ?>">
            <input type="text" name="admin_note" class="note" placeholder="Note (optional)">
            <?php if ($r['status'] === 'pending'): ?>
              <button name="action" value="ban_user" class="btn ban" onclick="return confirm('Ban this user?')">Ban user</button>
              <?php if (!empty($r['item_id'])): ?>
                <button name="action" value="delete_item" class="btn del" onclick="return confirm('Delete this item?')">Delete item</button>
              <?php endif; ?>
              <button name="action" value="resolve" class="btn res">Mark resolved</button>
            <?php else: ?>
              <em>No actions</em>
            <?php endif; ?>
          </form>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
</body>
</html>