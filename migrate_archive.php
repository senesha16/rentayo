<?php
include("connections.php");

// Add archived column to items table if it doesn't exist
$check_column = "SHOW COLUMNS FROM items LIKE 'archived'";
$result = mysqli_query($connections, $check_column);

if (mysqli_num_rows($result) == 0) {
    $add_column = "ALTER TABLE items ADD COLUMN archived TINYINT(1) DEFAULT 0 AFTER is_available";
    if (mysqli_query($connections, $add_column)) {
        echo "Successfully added 'archived' column to items table.<br>";
    } else {
        echo "Error adding archived column: " . mysqli_error($connections) . "<br>";
    }
} else {
    echo "Archived column already exists.<br>";
}

// Add archived_at column for timestamp
$check_archived_at = "SHOW COLUMNS FROM items LIKE 'archived_at'";
$result_archived_at = mysqli_query($connections, $check_archived_at);

if (mysqli_num_rows($result_archived_at) == 0) {
    $add_archived_at = "ALTER TABLE items ADD COLUMN archived_at TIMESTAMP NULL AFTER archived";
    if (mysqli_query($connections, $add_archived_at)) {
        echo "Successfully added 'archived_at' column to items table.<br>";
    } else {
        echo "Error adding archived_at column: " . mysqli_error($connections) . "<br>";
    }
} else {
    echo "Archived_at column already exists.<br>";
}

echo "Migration completed!";
?>