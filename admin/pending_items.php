<?php

include("../connections.php");
session_start();

// Admin gate
if (empty($_SESSION['ID'])) { header("Location: ../login.php"); exit; }
$uid = (int)$_SESSION['ID'];
$chk = mysqli_query($connections, "SELECT is_admin FROM users WHERE ID = $uid LIMIT 1");
$row = $chk ? mysqli_fetch_assoc($chk) : null;
if (!$row || (int)$row['is_admin'] !== 1) { header("Location: ../login.php"); exit; }

// Ensure status column exists (safety)
$col = mysqli_query($connections, "SHOW COLUMNS FROM Items LIKE 'status'");
if (!$col || mysqli_num_rows($col) === 0) {
    mysqli_query($connections, "ALTER TABLE Items ADD COLUMN `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
}
$col2 = mysqli_query($connections, "SHOW COLUMNS FROM Items LIKE 'reject_reason'");
if (!$col2 || mysqli_num_rows($col2) === 0) {
    mysqli_query($connections, "ALTER TABLE Items ADD COLUMN `reject_reason` TEXT NULL");
}

// Actions
$msg = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $iid = (int)($_POST['item_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    if ($iid <= 0) { $err = 'Invalid item.'; }
    else if ($action === 'approve') {
        $ok = mysqli_query($connections, "UPDATE Items SET status='approved', reject_reason=NULL WHERE item_id=$iid");
        $msg = $ok ? 'Item approved.' : 'Failed to approve.';
    } else if ($action === 'reject') {
        $esc = mysqli_real_escape_string($connections, $reason);
        $ok = mysqli_query($connections, "UPDATE Items SET status='rejected', reject_reason='$esc' WHERE item_id=$iid");
        $msg = $ok ? 'Item rejected.' : 'Failed to reject.';
    } else {
        $err = 'Unknown action.';
    }
}

// Fetch pending items
$q = "
SELECT i.item_id, i.title, i.price_per_day, i.image_url, i.description, i.status,
       u.username, u.email, u.phone_number
FROM Items i
JOIN users u ON u.ID = i.lender_id
WHERE i.status = 'pending'
ORDER BY i.item_id DESC";
$res = mysqli_query($connections, $q);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Admin • Pending Items</title>
<style>
body{font-family:Arial, sans-serif;background:#f5f7fb;margin:0}
.wrap{max-width:1100px;margin:30px auto;background:#fff;padding:20px;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.06)}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px}
.card{border:1px solid #e5e7eb;border-radius:12px;padding:12px;background:#fafbff}
.card h3{margin:6px 0 8px}
.meta{color:#64748b;font-size:13px;margin-bottom:8px}
.image{width:100%;height:180px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;display:flex;align-items:center;justify-content:center;overflow:hidden}
.image img{width:100%;height:100%;object-fit:cover}
.row{display:flex;gap:8px;margin-top:8px}
.row .btn{flex:1;padding:8px 10px;border:0;border-radius:8px;cursor:pointer}
.approve{background:#22c55e;color:#fff}
.reject{background:#ef4444;color:#fff}
.reason{width:100%;border:1px solid #e5e7eb;border-radius:8px;padding:8px;margin-top:8px}
.msg{padding:10px;border-radius:8px;margin-bottom:10px}
.ok{background:#d1fae5;color:#065f46}.err{background:#fee2e2;color:#991b1b}
.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.link{color:#4f46e5;text-decoration:none}
</style>
</head>
<body>
<?php include __DIR__ . '/admin_navbar.php'; ?>
<div class="wrap">
  <div class="top">
    <h2>Pending Items</h2>
    <a class="link" href="index.php">Back to Dashboard</a>
  </div>
  <?php if($msg):?><div class="msg ok"><?php echo htmlspecialchars($msg);?></div><?php endif;?>
  <?php if($err):?><div class="msg err"><?php echo htmlspecialchars($err);?></div><?php endif;?>

  <div class="grid">
    <?php while($r = mysqli_fetch_assoc($res)): ?>
    <div class="card">
      <div class="image">
        <?php if(!empty($r['image_url'])): ?>
          <img src="<?php echo '../' . htmlspecialchars($r['image_url']); ?>" alt="">
        <?php else: ?>
          <span>No image</span>
        <?php endif; ?>
      </div>
      <h3>#<?php echo (int)$r['item_id']; ?> • <?php echo htmlspecialchars($r['title']); ?></h3>
      <div class="meta">₱<?php echo number_format((float)$r['price_per_day'],2); ?> / day</div>
      <div class="meta">Owner: <?php echo htmlspecialchars($r['username']); ?> • <?php echo htmlspecialchars($r['email']); ?> <?php echo htmlspecialchars($r['phone_number']); ?></div>
      <p style="font-size:14px;color:#334155;max-height:60px;overflow:auto;"><?php echo nl2br(htmlspecialchars($r['description'])); ?></p>
      <div class="row">
        <form method="post">
          <input type="hidden" name="item_id" value="<?php echo (int)$r['item_id']; ?>">
          <button class="btn approve" name="action" value="approve" onclick="return confirm('Approve this item?')">Approve</button>
        </form>
        <form method="post">
          <input type="hidden" name="item_id" value="<?php echo (int)$r['item_id']; ?>">
          <button class="btn reject" name="action" value="reject" onclick="return confirm('Reject this item?')">Reject</button>
        </form>
      </div>
      <form method="post">
        <input type="hidden" name="item_id" value="<?php echo (int)$r['item_id']; ?>">
        <textarea class="reason" name="reason" placeholder="Optional rejection reason..."></textarea>
        <div class="row">
          <button class="btn reject" name="action" value="reject">Reject with reason</button>
        </div>
      </form>
      <div style="margin-top:8px">
        <a class="link" href="<?php echo '../item_details.php?item_id='.(int)$r['item_id']; ?>" target="_blank">View listing page</a>
      </div>
    </div>
    <?php endwhile; ?>
  </div>
</div>
</body>
</html>