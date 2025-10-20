<?php
include("connections.php");
session_start();

// Check if user is logged in
if (!isset($_SESSION["ID"])) {
    echo json_encode(['messages' => []]);
    exit;
}

$current_user_id = $_SESSION["ID"];
$other_user_id = $_GET['user_id'] ?? null;
$item_id = $_GET['item_id'] ?? null;
$last_message_id = $_GET['last_message_id'] ?? 0; // Use message ID instead of timestamp

if (!$other_user_id) {
    echo json_encode(['messages' => []]);
    exit;
}

// Get new messages since last message ID
$query = "SELECT message_id, message_text, sent_at 
          FROM messages 
          WHERE sender_id = $other_user_id 
          AND receiver_id = $current_user_id 
          AND message_id > $last_message_id";

if ($item_id) {
    $query .= " AND item_id = $item_id";
}

$query .= " ORDER BY sent_at ASC";

$result = mysqli_query($connections, $query);
$new_messages = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $new_messages[] = [
            'id' => $row['message_id'],
            'text' => $row['message_text'],
            'time' => $row['sent_at']
        ];
    }
    
    // Mark new messages as read
    if (!empty($new_messages)) {
        $mark_read = "UPDATE messages SET is_read = 1 
                      WHERE sender_id = $other_user_id 
                      AND receiver_id = $current_user_id 
                      AND message_id > $last_message_id";
        if ($item_id) {
            $mark_read .= " AND item_id = $item_id";
        }
        mysqli_query($connections, $mark_read);
    }
}

echo json_encode(['messages' => $new_messages]);
?>
