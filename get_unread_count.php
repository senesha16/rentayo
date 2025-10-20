<?php
include("connections.php");
session_start();

// Check if user is logged in
if (!isset($_SESSION["ID"])) {
    echo json_encode(['unread_count' => 0]);
    exit;
}

$user_id = $_SESSION["ID"];

// Count unread messages
$unread_query = "SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = '$user_id' AND is_read = 0";
$unread_result = mysqli_query($connections, $unread_query);

$unread_count = 0;
if ($unread_result && mysqli_num_rows($unread_result) > 0) {
    $unread_data = mysqli_fetch_assoc($unread_result);
    $unread_count = (int)$unread_data['unread_count'];
}

echo json_encode(['unread_count' => $unread_count]);
?>

echo json_encode(['count' => $count]);
?>
