<?php
include("connections.php");

echo "<h2>RenTayo Database Setup</h2>";

// Check and create messages table
$check_messages = "SHOW TABLES LIKE 'messages'";
$messages_exists = mysqli_query($connections, $check_messages);

if (mysqli_num_rows($messages_exists) == 0) {
    echo "<p>Creating messages table...</p>";
    
    $create_messages = "CREATE TABLE `messages` (
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
    
    if (mysqli_query($connections, $create_messages)) {
        echo "<p style='color: green;'>✅ Messages table created successfully!</p>";
    } else {
        echo "<p style='color: red;'>❌ Error creating messages table: " . mysqli_error($connections) . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ️ Messages table already exists.</p>";
}

// Check and create item_images table for multiple image support
$check_item_images = "SHOW TABLES LIKE 'item_images'";
$item_images_exists = mysqli_query($connections, $check_item_images);

if (mysqli_num_rows($item_images_exists) == 0) {
    echo "<p>Creating item_images table for multiple image support...</p>";
    
    $create_item_images = "CREATE TABLE `item_images` (
        `image_id` int(11) NOT NULL AUTO_INCREMENT,
        `item_id` int(11) NOT NULL,
        `image_url` varchar(255) NOT NULL,
        `is_primary` tinyint(1) DEFAULT 0,
        `sort_order` int(11) DEFAULT 0,
        `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`image_id`),
        KEY `item_id` (`item_id`),
        KEY `is_primary` (`is_primary`),
        KEY `sort_order` (`sort_order`),
        FOREIGN KEY (`item_id`) REFERENCES `items`(`item_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if (mysqli_query($connections, $create_item_images)) {
        echo "<p style='color: green;'>✅ Item_images table created successfully!</p>";
        echo "<p style='color: green;'>🖼️ Multiple image upload support is now enabled!</p>";
        
        // Migrate existing item images to the new table
        echo "<p>Migrating existing item images...</p>";
        $migrate_query = "SELECT item_id, image_url FROM items WHERE image_url != '' AND image_url IS NOT NULL";
        $migrate_result = mysqli_query($connections, $migrate_query);
        
        $migrated_count = 0;
        if ($migrate_result && mysqli_num_rows($migrate_result) > 0) {
            while ($row = mysqli_fetch_assoc($migrate_result)) {
                if (file_exists($row['image_url'])) {
                    $insert_image = "INSERT INTO item_images (item_id, image_url, is_primary, sort_order) 
                                    VALUES ('{$row['item_id']}', '{$row['image_url']}', 1, 0)";
                    if (mysqli_query($connections, $insert_image)) {
                        $migrated_count++;
                    }
                }
            }
        }
        echo "<p style='color: green;'>✅ Migrated {$migrated_count} existing images to item_images table!</p>";
        
    } else {
        echo "<p style='color: red;'>❌ Error creating item_images table: " . mysqli_error($connections) . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ️ Item_images table already exists.</p>";
    
    // Check if we need to populate it with existing data
    $count_images = mysqli_query($connections, "SELECT COUNT(*) as count FROM item_images");
    $count_result = mysqli_fetch_assoc($count_images);
    
    if ($count_result['count'] == 0) {
        echo "<p>Item_images table is empty. Migrating existing item images...</p>";
        $migrate_query = "SELECT item_id, image_url FROM items WHERE image_url != '' AND image_url IS NOT NULL";
        $migrate_result = mysqli_query($connections, $migrate_query);
        
        $migrated_count = 0;
        if ($migrate_result && mysqli_num_rows($migrate_result) > 0) {
            while ($row = mysqli_fetch_assoc($migrate_result)) {
                if (file_exists($row['image_url'])) {
                    $insert_image = "INSERT INTO item_images (item_id, image_url, is_primary, sort_order) 
                                    VALUES ('{$row['item_id']}', '{$row['image_url']}', 1, 0)";
                    if (mysqli_query($connections, $insert_image)) {
                        $migrated_count++;
                    }
                }
            }
        }
        echo "<p style='color: green;'>✅ Migrated {$migrated_count} existing images to item_images table!</p>";
    }
}

// Check existing tables
echo "<h3>Current Tables in Database:</h3>";
$show_tables = "SHOW TABLES";
$result = mysqli_query($connections, $show_tables);

if ($result) {
    echo "<ul>";
    while ($row = mysqli_fetch_array($result)) {
        echo "<li>" . $row[0] . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>Error getting tables: " . mysqli_error($connections) . "</p>";
}

echo "<br><a href='index.php'>← Back to RenTayo</a>";
?>
