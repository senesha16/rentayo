<?php
include("connections.php");
session_start();

// Check if user is logged in
if (!isset($_SESSION["ID"])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$current_user_id = $_SESSION["ID"];
$other_user_id = $_GET['user_id'] ?? null;

if (!$other_user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID required']);
    exit;
}

// Get conversation history (all messages between these users)
$messages_query = "SELECT m.*, u.username as sender_name, i.title as item_title 
                   FROM messages m 
                   JOIN users u ON m.sender_id = u.ID 
                   LEFT JOIN items i ON m.item_id = i.item_id
                   WHERE ((m.sender_id = ? AND m.receiver_id = ?) 
                          OR (m.sender_id = ? AND m.receiver_id = ?))
                   ORDER BY m.sent_at ASC";

$stmt = mysqli_prepare($connections, $messages_query);
mysqli_stmt_bind_param($stmt, "iiii", $current_user_id, $other_user_id, $other_user_id, $current_user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$messages = [];
while ($row = mysqli_fetch_assoc($result)) {
    $messages[] = $row;
}

// Mark messages as read
$mark_read = "UPDATE messages SET is_read = 1 
              WHERE sender_id = ? AND receiver_id = ?";
$mark_stmt = mysqli_prepare($connections, $mark_read);
mysqli_stmt_bind_param($mark_stmt, "ii", $other_user_id, $current_user_id);
mysqli_stmt_execute($mark_stmt);

header('Content-Type: application/json');
echo json_encode($messages);
?>