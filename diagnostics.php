<?php
@ini_set('display_errors', '1');
@error_reporting(E_ALL);

// Start session diagnostics
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$sessionStarted = session_status() === PHP_SESSION_ACTIVE;
$sessionId = session_id();

$results = [
    'php' => [
        'version' => PHP_VERSION,
        'sapi' => PHP_SAPI,
        'loaded_extensions' => get_loaded_extensions(),
    ],
    'ini' => [
        'display_errors' => ini_get('display_errors'),
        'log_errors' => ini_get('log_errors'),
        'error_log' => ini_get('error_log'),
        'session.save_path' => ini_get('session.save_path'),
        'default_charset' => ini_get('default_charset'),
    ],
    'session' => [
        'started' => $sessionStarted,
        'id' => $sessionId,
        'cookie_params' => session_get_cookie_params(),
        'superglobal' => $_SESSION,
    ],
    'db' => [
        'connected' => false,
        'host_tried' => [],
        'errors' => [],
        'tables' => [],
        'counts' => [],
        'columns' => [],
    ],
];

ob_start();
include __DIR__ . '/connections.php';
$includeOutput = trim(ob_get_clean());
if ($includeOutput !== '') {
    $results['db']['errors'][] = 'connections.php echoed output: ' . $includeOutput;
}

if (isset($connections) && $connections instanceof mysqli) {
    $results['db']['connected'] = true;
    // Show tables
    if ($rs = @mysqli_query($connections, 'SHOW TABLES')) {
        while ($row = mysqli_fetch_row($rs)) { $results['db']['tables'][] = $row[0]; }
        mysqli_free_result($rs);
    } else {
        $results['db']['errors'][] = 'SHOW TABLES failed: ' . mysqli_error($connections);
    }

    // Counts from common tables (lowercase)
    $toCount = ['users','items','categories','itemcategories','messages'];
    foreach ($toCount as $t) {
        $q = @mysqli_query($connections, "SELECT COUNT(*) AS c FROM `$t`");
        if ($q && ($row = mysqli_fetch_assoc($q))) {
            $results['db']['counts'][$t] = (int)$row['c'];
        } else {
            $results['db']['counts'][$t] = 'ERR: ' . mysqli_error($connections);
        }
        if ($q instanceof mysqli_result) mysqli_free_result($q);
    }

    // Column existence
    $columnsToCheck = [
        'users' => ['ID','is_banned','username','email'],
        'items' => ['item_id','status','title','image_url'],
    ];
    foreach ($columnsToCheck as $table => $cols) {
        $res = @mysqli_query($connections, "SHOW COLUMNS FROM `$table`");
        if ($res) {
            $present = [];
            while ($r = mysqli_fetch_assoc($res)) { $present[] = $r['Field']; }
            $results['db']['columns'][$table] = array_intersect($cols, $present);
            mysqli_free_result($res);
        } else {
            $results['db']['errors'][] = "SHOW COLUMNS $table failed: " . mysqli_error($connections);
        }
    }

    // Try the main items query like index.php (without status if missing)
    $hasStatus = false;
    if ($col = @mysqli_query($connections, "SHOW COLUMNS FROM items LIKE 'status'")) {
        $hasStatus = mysqli_num_rows($col) > 0; if ($col) mysqli_free_result($col);
    }
    $where = [];
    if ($hasStatus) { $where[] = "i.status = 'approved'"; }
    $sql = "SELECT i.item_id, i.title, u.username FROM items i INNER JOIN users u ON u.ID = i.lender_id "
         . (count($where) ? 'WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY i.item_id DESC LIMIT 5';

    $itemsPreview = [];
    $q = @mysqli_query($connections, $sql);
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) { $itemsPreview[] = $row; }
        mysqli_free_result($q);
    } else {
        $results['db']['errors'][] = 'Items preview query failed: ' . mysqli_error($connections);
    }
    $results['db']['items_preview'] = $itemsPreview;
} else {
    $results['db']['errors'][] = 'No mysqli connection available.';
}

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>RenTayo Diagnostics</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; padding: 20px; line-height: 1.5; }
    pre { background: #0b1220; color: #e2e8f0; padding: 12px; border-radius: 8px; overflow-x: auto; }
    code { white-space: pre-wrap; word-wrap: break-word; }
    .ok { color: #16a34a; font-weight: 700; }
    .warn { color: #f59e0b; font-weight: 700; }
    .err { color: #dc2626; font-weight: 700; }
    .box { border: 1px solid #e5e7eb; padding: 16px; border-radius: 8px; margin-bottom: 16px; }
  </style>
</head>
<body>
  <h1>RenTayo Diagnostics</h1>
  <p><strong>Note:</strong> Remove this file after troubleshooting.</p>

  <div class="box">
    <h2>PHP & INI</h2>
    <pre><code><?php echo htmlspecialchars(json_encode($results['php'], JSON_PRETTY_PRINT)); ?></code></pre>
    <pre><code><?php echo htmlspecialchars(json_encode($results['ini'], JSON_PRETTY_PRINT)); ?></code></pre>
  </div>

  <div class="box">
    <h2>Session</h2>
    <p>Status: <?php echo $sessionStarted ? '<span class="ok">ACTIVE</span>' : '<span class="err">NOT ACTIVE</span>'; ?> | ID: <code><?php echo htmlspecialchars($sessionId); ?></code></p>
    <pre><code><?php echo htmlspecialchars(json_encode($results['session'], JSON_PRETTY_PRINT)); ?></code></pre>
  </div>

  <div class="box">
    <h2>Database</h2>
    <p>Connected: <?php echo $results['db']['connected'] ? '<span class="ok">YES</span>' : '<span class="err">NO</span>'; ?></p>
    <?php if (!empty($results['db']['errors'])): ?>
      <p>Errors:</p>
      <pre><code><?php echo htmlspecialchars(json_encode($results['db']['errors'], JSON_PRETTY_PRINT)); ?></code></pre>
    <?php endif; ?>
    <h3>Tables</h3>
    <pre><code><?php echo htmlspecialchars(json_encode($results['db']['tables'], JSON_PRETTY_PRINT)); ?></code></pre>
    <h3>Row counts</h3>
    <pre><code><?php echo htmlspecialchars(json_encode($results['db']['counts'], JSON_PRETTY_PRINT)); ?></code></pre>
    <h3>Columns present</h3>
    <pre><code><?php echo htmlspecialchars(json_encode($results['db']['columns'], JSON_PRETTY_PRINT)); ?></code></pre>
    <h3>Items preview</h3>
    <pre><code><?php echo htmlspecialchars(json_encode($results['db']['items_preview'], JSON_PRETTY_PRINT)); ?></code></pre>
  </div>

  <div class="box">
    <h2>Next</h2>
    <ol>
      <li>If Connected = NO, verify credentials/host in connections.php or set DB_HOST/DB_USER/DB_PASS/DB_NAME in hosting env.</li>
      <li>If table counts show errors, ensure table names are lowercase as in rentayo.sql and imported correctly.</li>
      <li>If Session shows empty and ID changes on every refresh, check cookie/HTTPS/domain on Hostinger.</li>
    </ol>
  </div>
</body>
</html>
