<?php
include("connections.php");
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['ID'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in first.']);
    exit;
}

// Ensure tables/columns
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

$reporter_id = (int)$_SESSION['ID'];
$item_id = (int)($_POST['item_id'] ?? 0);
$reported_user_id = (int)($_POST['reported_user_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$details = trim($_POST['details'] ?? '');

if ($reported_user_id <= 0 || $reason === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid report data.']);
    exit;
}

$stmt = mysqli_prepare($connections, "INSERT INTO reports (item_id, reported_user_id, reporter_id, reason, details) VALUES (?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "iiiss", $item_id, $reported_user_id, $reporter_id, $reason, $details);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

echo json_encode([
    'success' => (bool)$ok,
    'message' => $ok ? 'Report submitted. Our admins will review it.' : 'Failed to submit report.'
]);