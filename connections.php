<?php
// Centralized PHP error logging (avoid white screen). Logs to php-error.log in project root.
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@ini_set('error_reporting', (string)E_ALL);
if (!ini_get('error_log')) {
    @ini_set('error_log', __DIR__ . '/php-error.log');
}

// Allow overrides via environment variables when available
$DB_HOST = getenv('DB_HOST') ?: null;
$DB_USER = getenv('DB_USER') ?: 'u679323211_rentayo';
$DB_PASS = getenv('DB_PASS') ?: 'Joshcumpas@1';
$DB_NAME = getenv('DB_NAME') ?: 'u679323211_rentayo';

$hostsToTry = [];
if ($DB_HOST) { $hostsToTry[] = $DB_HOST; }
// Common Hostinger/local hosts
$hostsToTry = array_unique(array_merge($hostsToTry, ['localhost','127.0.0.1','mysql.hostinger.com']));

$connections = null;
foreach ($hostsToTry as $host) {
    $conn = @mysqli_connect($host, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn) {
        $connections = $conn;
        @mysqli_set_charset($connections, 'utf8mb4');
        break;
    } else {
        error_log('DB connect failed host=' . $host . ' err=' . mysqli_connect_error());
    }
}

if (!$connections) {
    error_log('All DB connection attempts failed. Check credentials/host.');
    // Optional: stop execution if DB is down
    // die('Database connection error');
}
?>


