<?php
include("connections.php");
session_start();

$username = $password = $email = $phone_number = "";
$profile_picture_url = ""; // web path like 'uploads/profile_xxx.jpg'

$usernameErr = $passwordErr = $emailErr = $phone_numberErr = $profile_picture_urlErr = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Trim inputs
    $username = trim($_POST["username"] ?? "");
    $password = trim($_POST["password"] ?? "");
    $email    = trim($_POST["email"] ?? "");
    $phone_number = trim($_POST["phone_number"] ?? "");

    // Validate required text fields
    if ($username === "") { $usernameErr = "Username is required"; }
    if ($password === "") { $passwordErr = "Password is required"; }

    if ($email === "") {
        $emailErr = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $emailErr = "Invalid email format";
    }

    if ($phone_number === "") {
        $phone_numberErr = "Phone number is required";
    } elseif (!preg_match('/^[0-9]{11}$/', $phone_number)) {
        $phone_numberErr = "Phone number must be 11 digits (numbers only)";
    }

    // Handle optional profile picture upload (store web path)
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['profile_picture']['tmp_name'];
            $name = basename($_FILES['profile_picture']['name']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($ext, $allowed)) {
                $profile_picture_urlErr = "Only JPG, JPEG, PNG & GIF allowed.";
            } elseif (($_FILES['profile_picture']['size'] ?? 0) > 2 * 1024 * 1024) {
                $profile_picture_urlErr = "File too large (max 2 MB).";
            } else {
                $uploadDirAbs = __DIR__ . '/uploads/';
                $uploadDirWeb = 'uploads/';

                if (!is_dir($uploadDirAbs)) {
                    @mkdir($uploadDirAbs, 0755, true);
                }
                if (!is_dir($uploadDirAbs) || !is_writable($uploadDirAbs)) {
                    $profile_picture_urlErr = "Upload directory is not writable: " . $uploadDirAbs;
                } else {
                    $filename = 'profile_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $destAbs = $uploadDirAbs . $filename;
                    $destWeb = $uploadDirWeb . $filename;

                    if (!is_uploaded_file($tmp)) {
                        $profile_picture_urlErr = "Temporary upload file is missing. Please try again.";
                    } elseif (!move_uploaded_file($tmp, $destAbs)) {
                        $profile_picture_urlErr = "Failed to save uploaded file.";
                    } else {
                        $profile_picture_url = $destWeb; // save web path
                    }
                }
            }
        } else {
            $profile_picture_urlErr = "Upload error: " . (int)$_FILES['profile_picture']['error'];
        }
    }

    // If no validation errors, insert user
    if ($usernameErr === "" && $passwordErr === "" && $emailErr === "" && $phone_numberErr === "" && $profile_picture_urlErr === "") {
        // Ensure email is unique
        $safeEmail = mysqli_real_escape_string($connections, $email);
        $check_email = mysqli_query($connections, "SELECT 1 FROM users WHERE email = '$safeEmail' LIMIT 1");
        if ($check_email && mysqli_num_rows($check_email) > 0) {
            $emailErr = "Email already exists!";
        } else {
            // Optional: also ensure username unique
            $safeUsername = mysqli_real_escape_string($connections, $username);
            $check_user = mysqli_query($connections, "SELECT 1 FROM users WHERE username = '$safeUsername' LIMIT 1");
            if ($check_user && mysqli_num_rows($check_user) > 0) {
                $usernameErr = "Username already exists!";
            } else {
                // Insert (profile_picture_url may be empty string)
                $sql = sprintf(
                    "INSERT INTO users (username, password, email, phone_number, profile_picture_url) VALUES ('%s','%s','%s','%s','%s')",
                    $safeUsername,
                    mysqli_real_escape_string($connections, $password),
                    $safeEmail,
                    mysqli_real_escape_string($connections, $phone_number),
                    mysqli_real_escape_string($connections, $profile_picture_url)
                );
                $insert = mysqli_query($connections, $sql);

                if ($insert) {
                    echo "<script>alert('Account created successfully'); window.location.href='login.php';</script>";
                    exit;
                } else {
                    $emailErr = "Database error: " . mysqli_error($connections);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - RenTayo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/navbar.css">
    <style>
        .error { color: #b91c1c; font-size: 13px; }
        .register-container { max-width: 500px; margin: 40px auto; padding: 24px; background: #fff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,.06); }
        .form-group { margin-bottom: 14px; }
        .form-group input { width: 100%; padding: 10px 12px; border: 1.5px solid #e5e7eb; border-radius: 10px; }
        .submit-btn { padding: 10px 16px; border: none; background: #6366f1; color: #fff; border-radius: 10px; cursor: pointer; }
        .submit-btn:hover { background: #4f46e5; }
        .login-link { margin-top: 14px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>Create an Account</h2>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data" novalidate>
            <div class="form-group">
                <label for="username">Username</label>
                <input required type="text" name="username" id="username" value="<?php echo htmlspecialchars($username); ?>">
                <span class="error"><?php echo $usernameErr; ?></span>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input required type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>">
                <span class="error"><?php echo $emailErr; ?></span>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input required type="password" name="password" id="password" value="<?php echo htmlspecialchars($password); ?>">
                <span class="error"><?php echo $passwordErr; ?></span>
            </div>

            <div class="form-group">
                <label for="phone_number">Phone Number</label>
                <input required type="text" name="phone_number" id="phone_number"
                       value="<?php echo htmlspecialchars($phone_number); ?>"
                       inputmode="numeric" pattern="[0-9]{11}" maxlength="11"
                       title="Enter 11 digits (numbers only)">
                <span class="error"><?php echo $phone_numberErr; ?></span>
            </div>

            <div class="form-group">
                <label for="profile_picture">Profile Picture (optional)</label>
                <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg,image/png,image/gif">
                <span class="error"><?php echo $profile_picture_urlErr; ?></span>
            </div>

            <button type="submit" class="submit-btn">Register</button>
        </form>
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>

    <script>
    // Enforce numeric-only and 11 digits on the client
    (function () {
        const phone = document.getElementById('phone_number');
        if (phone) {
            phone.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
            });
        }
    })();
    </script>
</body>
</html>