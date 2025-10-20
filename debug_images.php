<?php
include("connections.php");
session_start();

$item_id = $_GET['item_id'] ?? 1; // Use a test item ID

// Check if item_images table exists
$table_check = mysqli_query($connections, "SHOW TABLES LIKE 'item_images'");
echo "Table exists: " . (mysqli_num_rows($table_check) > 0 ? "Yes" : "No") . "<br>";

if (mysqli_num_rows($table_check) > 0) {
    // Check table structure
    $structure = mysqli_query($connections, "DESCRIBE item_images");
    echo "<h3>Table Structure:</h3>";
    while ($row = mysqli_fetch_assoc($structure)) {
        echo $row['Field'] . " - " . $row['Type'] . "<br>";
    }
    
    // Try to fetch images for the item
    $image_query = "SELECT * FROM item_images WHERE item_id = ? ORDER BY is_primary DESC, sort_order ASC";
    $image_stmt = mysqli_prepare($connections, $image_query);
    mysqli_stmt_bind_param($image_stmt, "i", $item_id);
    mysqli_stmt_execute($image_stmt);
    $image_result = mysqli_stmt_get_result($image_stmt);
    
    echo "<h3>Images for item $item_id:</h3>";
    while ($row = mysqli_fetch_assoc($image_result)) {
        echo "<pre>";
        print_r($row);
        echo "</pre>";
    }
} else {
    echo "item_images table does not exist!";
}
?>