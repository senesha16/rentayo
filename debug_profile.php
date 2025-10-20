<?php
include("connections.php");
session_start();

echo "<h3>Profile Picture Debug for User ID: " . ($_SESSION["ID"] ?? 'Not logged in') . "</h3>";

if (isset($_SESSION["ID"])) {
    $user_id = $_SESSION["ID"];
    $user_query = "SELECT username, profile_picture_url FROM users WHERE ID = '$user_id'";
    $user_result = mysqli_query($connections, $user_query);
    
    if ($user_result && mysqli_num_rows($user_result) > 0) {
        $user_data = mysqli_fetch_assoc($user_result);
        echo "<p><strong>Username:</strong> " . htmlspecialchars($user_data['username']) . "</p>";
        echo "<p><strong>Profile Picture URL from DB:</strong> '" . htmlspecialchars($user_data['profile_picture_url']) . "'</p>";
        echo "<p><strong>URL Length:</strong> " . strlen($user_data['profile_picture_url']) . "</p>";
        echo "<p><strong>Is Empty:</strong> " . (empty($user_data['profile_picture_url']) ? 'YES' : 'NO') . "</p>";
        
        $profile_picture = $user_data['profile_picture_url'];
        
        // Test different paths
        $paths_to_test = [
            $profile_picture,
            "uploads/" . $profile_picture,
            "uploads/" . basename($profile_picture),
        ];
        
        echo "<h4>Path Tests:</h4>";
        foreach ($paths_to_test as $index => $path) {
            $exists = file_exists($path);
            echo "<p>Path " . ($index + 1) . ": '" . htmlspecialchars($path) . "' - EXISTS: " . ($exists ? 'YES' : 'NO') . "</p>";
            if ($exists) {
                echo "<p style='margin-left: 20px;'>✅ <img src='" . htmlspecialchars($path) . "' width='50' height='50' style='border-radius: 50%;'> Found!</p>";
            }
        }
        
        // List files in uploads directory
        echo "<h4>Files in uploads/ directory:</h4>";
        $upload_files = glob("uploads/*");
        foreach ($upload_files as $file) {
            if (is_file($file)) {
                echo "<p>" . htmlspecialchars($file) . "</p>";
            }
        }
    }
} else {
    echo "<p>User not logged in</p>";
}
?>
