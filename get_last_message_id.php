<?php
include("connections.php");
session_start();

// Check if user is logged in
if (!isset($_SESSION["ID"])) {
    echo json_encode(['last_message_id' => 0]);
    exit;
}

$current_user_id = $_SESSION["ID"];
$other_user_id = $_GET['user_id'] ?? null;
$item_id = $_GET['item_id'] ?? null;

if (!$other_user_id) {
    echo json_encode(['last_message_id' => 0]);
    exit;
}

// Get the latest message ID in the conversation
$query = "SELECT MAX(message_id) as last_id 
          FROM messages 
          WHERE ((sender_id = $current_user_id AND receiver_id = $other_user_id) 
                 OR (sender_id = $other_user_id AND receiver_id = $current_user_id))";

if ($item_id) {
    $query .= " AND item_id = $item_id";
}

$result = mysqli_query($connections, $query);
$last_id = 0;

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $last_id = $row['last_id'] ?? 0;
}

echo json_encode(['last_message_id' => (int)$last_id]);
?>
