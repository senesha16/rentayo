<?php
include("connections.php");

$query = "UPDATE users SET profile_picture_url = REPLACE(profile_picture_url, 'C:\\\\xampp\\\\htdocs\\\\Rentayo\\\\uploads\\\\', 'uploads/') WHERE profile_picture_url LIKE 'C:\\\\xampp\\\\htdocs\\\\Rentayo\\\\uploads\\\\%'";

$result = mysqli_query($connections, $query);

if ($result) {
    echo "Updated " . mysqli_affected_rows($connections) . " rows.";
} else {
    echo "Error: " . mysqli_error($connections);
}
?>
