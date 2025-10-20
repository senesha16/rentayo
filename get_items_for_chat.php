<?php
include("connections.php");
session_start();

header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION["ID"])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$current_user_id = (int)$_SESSION["ID"];
$other_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0; // fixed key, sanitized

if ($other_user_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID required']);
    exit;
}

// Items the two users discussed OR items owned by either user (approved only)
$sql = "
    SELECT 
        i.item_id, i.title
    FROM Items i
    LEFT JOIN messages m ON m.item_id = i.item_id
    WHERE 
        i.status = 'approved'
        AND (
            i.lender_id IN (?, ?)
            OR (
                (m.sender_id = ? AND m.receiver_id = ?)
                OR
                (m.sender_id = ? AND m.receiver_id = ?)
            )
        )
    GROUP BY i.item_id, i.title
    ORDER BY i.title
";

$stmt = mysqli_prepare($connections, $sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to prepare statement']);
    exit;
}

mysqli_stmt_bind_param(
    $stmt,
    "iiiiii",
    $current_user_id, $other_user_id,
    $current_user_id, $other_user_id,
    $other_user_id, $current_user_id
);

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$items = [];
while ($row = mysqli_fetch_assoc($result)) {
    if (!empty($row['item_id'])) {
        $items[] = [
            'item_id' => (int)$row['item_id'],
            'title'   => $row['title'],
        ];
    }
}

mysqli_stmt_close($stmt);

echo json_encode($items);
?>