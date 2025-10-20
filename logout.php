<?php 
session_start();

// Destroy all session data
unset($_SESSION['ID']);
session_unset();
session_destroy();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logging Out - RenTayo</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/navbar.css">
</head>
<body>
    <div class="logout-container">
        <h2>Logging Out</h2>
        <p>Please wait while we securely log you out...</p>
        <div class="spinner"></div>
    </div>
    
    <script>
        // Redirect after 3 seconds
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 3000);
    </script>
</body>
</html>