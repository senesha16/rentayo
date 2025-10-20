<?php
include("connections.php");
session_start();

// Check if user is logged in
if (!isset($_SESSION["ID"])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to send messages']);
    exit;
}

$sender_id = $_SESSION["ID"];
$receiver_id = $_POST['receiver_id'] ?? null;
$item_id = $_POST['item_id'] ?? null;
$message_text = $_POST['message_text'] ?? null;

if (!$receiver_id || !$message_text) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Check if messages table exists, if not create it
$check_table = "SHOW TABLES LIKE 'messages'";
$table_exists = mysqli_query($connections, $check_table);

if (mysqli_num_rows($table_exists) == 0) {
    // Create the messages table
    $create_table = "CREATE TABLE `messages` (
        `message_id` int(11) NOT NULL AUTO_INCREMENT,
        `sender_id` int(11) NOT NULL,
        `receiver_id` int(11) NOT NULL,
        `item_id` int(11) DEFAULT NULL,
        `message_text` text NOT NULL,
        `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `is_read` tinyint(1) DEFAULT 0,
        PRIMARY KEY (`message_id`),
        KEY `sender_id` (`sender_id`),
        KEY `receiver_id` (`receiver_id`),
        KEY `item_id` (`item_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if (!mysqli_query($connections, $create_table)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create messages table: ' . mysqli_error($connections)]);
        exit;
    }
}

// Insert message directly
$message_text = mysqli_real_escape_string($connections, $message_text);
$item_id_value = $item_id ? intval($item_id) : "NULL";

$insert_message = "INSERT INTO messages (sender_id, receiver_id, item_id, message_text) 
                   VALUES ($sender_id, $receiver_id, $item_id_value, '$message_text')";

if (mysqli_query($connections, $insert_message)) {
    echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send message: ' . mysqli_error($connections)]);
}
?>
