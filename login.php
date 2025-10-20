<?php
session_start();
require_once 'connections.php'; // must define $connections (mysqli)

// If already logged in, go home
if (isset($_SESSION['ID'])) {
    header('Location: index.php');
    exit;
}

// Defaults for the form/errors
$email = '';
$emailErr = '';
$passwordErr = '';
$generalErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accept either "identifier" or legacy "email" field name
    $identifier = trim($_POST['identifier'] ?? $_POST['email'] ?? '');
    $password   = (string)($_POST['password'] ?? '');

    $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;

    if ($identifier === '') {
        $emailErr = 'Please enter your email or username.';
    }
    if ($password === '') {
        $passwordErr = 'Please enter your password.';
    }

    if ($emailErr === '' && $passwordErr === '') {
        // Discover available columns
        $cols = [];
        if ($colRes = mysqli_query($connections, "SHOW COLUMNS FROM users")) {
            while ($r = mysqli_fetch_assoc($colRes)) { $cols[] = $r['Field']; }
            mysqli_free_result($colRes);
        }

        // Column detection
        $idCol       = in_array('ID', $cols, true) ? 'ID' : (in_array('id', $cols, true) ? 'id' : null);
        $emailCols   = array_values(array_intersect($cols, ['email','user_email','email_address','mail']));
        $userCols    = array_values(array_intersect($cols, ['username','user_name','login','handle']));
        $hasHash     = in_array('password_hash', $cols, true);
        $hasPass     = in_array('password', $cols, true);
        $hasBanned   = in_array('is_banned', $cols, true);
        // detect possible admin/role columns
        $possible_admin_cols = ['is_admin','admin','is_staff','is_superuser','is_root','role','user_type','role_id','role_name'];
        $adminCols = array_values(array_intersect($cols, $possible_admin_cols));
        $adminCol = !empty($adminCols) ? $adminCols[0] : null;

        if (!$idCol) {
            $generalErr = 'Server error: users table has no ID column.';
        } elseif (!$hasHash && !$hasPass) {
            $generalErr = 'Server error: users table has no password column.';
        } elseif (empty($emailCols) && empty($userCols)) {
            $generalErr = 'Server error: users table missing email/username columns.';
        } else {
            // Select list with aliases
            $usernameCol = !empty($userCols) ? $userCols[0] : null;
            $emailCol    = !empty($emailCols) ? $emailCols[0] : null;

            $fields = "$idCol AS ID";
            if ($usernameCol) $fields .= ", $usernameCol AS username";
            if ($emailCol)    $fields .= ", $emailCol AS email";
            if ($hasHash)     $fields .= ", password_hash";
            if ($hasPass)     $fields .= ", password AS legacy_password";
            if ($hasBanned)   $fields .= ", is_banned";
            if ($adminCol)    $fields .= ", $adminCol AS admin_flag";

            // Helper: fetch by a set of columns (OR-equals)
            $fetchBy = function(array $colsSet) use ($connections, $fields, $identifier) {
                if (empty($colsSet)) return null;
                $where = '(' . implode(' OR ', array_map(fn($c) => "$c = ?", $colsSet)) . ')';
                $sql   = "SELECT $fields FROM users WHERE $where LIMIT 1";
                $types = str_repeat('s', count($colsSet));
                $params = array_fill(0, count($colsSet), $identifier);

                $stmt = mysqli_prepare($connections, $sql);
                if (!$stmt) return null;
                mysqli_stmt_bind_param($stmt, $types, ...$params);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = $res ? mysqli_fetch_assoc($res) : null;
                if ($res instanceof mysqli_result) mysqli_free_result($res);
                mysqli_stmt_close($stmt);
                return $row ?: null;
            };

            // Strategy: prefer email if input is an email, otherwise username.
            $user = null;
            if ($isEmail && !empty($emailCols)) {
                $user = $fetchBy($emailCols);
                // Optional fallback to username only if not found
                if (!$user && !empty($userCols)) $user = $fetchBy($userCols);
            } else {
                $user = $fetchBy($userCols);
                // Optional fallback to email only if not found
                if (!$user && !empty($emailCols)) $user = $fetchBy($emailCols);
            }

            if ($user) {
                if ($hasBanned && (int)$user['is_banned'] === 1) {
                    $generalErr = 'Your account is banned. Please contact support.';
                } else {
                    $ok = false;

                    if ($hasHash) {
                        $hash = (string)($user['password_hash'] ?? '');
                        if ($hash !== '' && password_get_info($hash)['algo'] !== 0) {
                            $ok = password_verify($password, $hash);
                        }
                    }
                    if (!$ok && $hasPass) {
                        $legacy = (string)($user['legacy_password'] ?? '');
                        if ($legacy !== '') {
                            $ok = hash_equals($legacy, md5($password)) || hash_equals($legacy, $password);
                        }
                    }

                    if ($ok) {
                        session_regenerate_id(true);
                        $_SESSION['ID']       = (int)$user['ID'];
                        $_SESSION['username'] = $user['username'] ?? '';
                        $_SESSION['email']    = $user['email'] ?? ($isEmail ? $identifier : '');

                        // Determine admin status from the aliased admin_flag (supports boolean/int or role string)
                        $isAdmin = false;
                        if (isset($user['admin_flag'])) {
                            $af = $user['admin_flag'];
                            if (is_numeric($af)) {
                                $isAdmin = ((int)$af) > 0;
                            } else {
                                $afstr = strtolower((string)$af);
                                $isAdmin = in_array($afstr, ['admin','administrator','staff','superuser','owner'], true);
                            }
                        }
                        $_SESSION['is_admin'] = $isAdmin ? 1 : 0;

                        // Redirect admins to admin dashboard
                        if ($isAdmin) {
                            header('Location: admin/index.php');
                            exit;
                        } else {
                            header('Location: index.php');
                            exit;
                        }
                    } else {
                        $generalErr = 'Incorrect email/username or password.';
                    }
                }
            } else {
                $generalErr = 'Incorrect email/username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RenTayo</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons (fixed: load UMD build) -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <style>
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        @keyframes ping {
            0% { transform: scale(1); opacity: 1; }
            75%, 100% { transform: scale(2); opacity: 0; }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col lg:flex-row bg-gradient-to-br from-sky-50 via-white to-blue-50">
    
    <!-- Left Panel - Hero Section -->
    <div class="hidden lg:flex lg:w-1/2 xl:w-3/5 bg-gradient-to-br from-sky-500 via-sky-600 to-cyan-600 p-12 relative overflow-hidden">
        <!-- Animated background elements -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-20 left-20 w-64 h-64 bg-white rounded-full blur-3xl animate-pulse"></div>
            <div class="absolute bottom-20 right-20 w-96 h-96 bg-cyan-300 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
        </div>

        <!-- Decorative floating elements -->
        <div class="absolute top-1/4 right-12 w-20 h-20 border-4 border-white/30 rounded-2xl rotate-12 animate-bounce" style="animation-duration: 3s;"></div>
        <div class="absolute bottom-1/3 left-16 w-16 h-16 border-4 border-cyan-300/40 rounded-full animate-bounce" style="animation-duration: 4s; animation-delay: 0.5s;"></div>
        <div class="absolute top-1/2 right-32 w-12 h-12 bg-yellow-400/20 rounded-lg rotate-45 animate-pulse" style="animation-duration: 2s;"></div>
        <div class="absolute bottom-1/4 left-1/3 w-8 h-8 bg-emerald-400/20 rounded-full animate-ping" style="animation-duration: 3s;"></div>

        <!-- Animated icons floating around -->
        <div class="absolute top-1/3 left-1/4 animate-bounce" style="animation-duration: 2.5s; animation-delay: 0.3s;">
            <div class="w-10 h-10 bg-white/10 backdrop-blur-sm rounded-lg flex items-center justify-center border border-white/20">
                <i data-lucide="package" class="w-5 h-5 text-white/60"></i>
            </div>
        </div>
        <div class="absolute top-2/3 right-1/4 animate-bounce" style="animation-duration: 3.5s; animation-delay: 1s;">
            <div class="w-10 h-10 bg-white/10 backdrop-blur-sm rounded-lg flex items-center justify-center border border-white/20">
                <i data-lucide="sparkles" class="w-5 h-5 text-white/60"></i>
            </div>
        </div>

        <div class="relative z-10 flex flex-col justify-between w-full max-w-xl mx-auto">
            <!-- Logo & Brand -->
            <div>
                <div class="flex items-center gap-3 mb-8 group">
                    <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center border border-white/30 shadow-lg group-hover:scale-110 transition-transform">
                        <i data-lucide="package" class="w-6 h-6 text-white"></i>
                    </div>
                    <div>
                        <span class="text-white font-medium tracking-tight block text-xl">RENTayo</span>
                        <span class="text-sky-100 text-xs">Student Rentals</span>
                    </div>
                </div>
                
                <div class="mb-6">
                    <h1 class="text-white text-4xl font-bold mb-2 leading-tight">
                        Rent what you need,<br>share what you have
                    </h1>
                    <div class="flex items-center gap-2 text-yellow-300">
                        <i data-lucide="sparkles" class="w-5 h-5 animate-pulse"></i>
                        <span class="text-sm">Join our growing campus community!</span>
                    </div>
                </div>
                
                <p class="text-sky-100 text-lg leading-relaxed mb-12">
                    The easiest way for students to rent and lend items on campus. From textbooks to bikes, find everything you need.
                </p>

                <!-- Feature badges -->
                <div class="space-y-4">
                    <div class="flex items-start gap-4 bg-white/10 backdrop-blur-sm rounded-xl p-5 border border-white/20 hover:bg-white/20 hover:scale-105 transition-all shadow-lg cursor-pointer group">
                        <div class="w-12 h-12 bg-gradient-to-br from-cyan-400 to-cyan-500 rounded-xl flex items-center justify-center flex-shrink-0 shadow-lg group-hover:rotate-12 transition-transform">
                            <i data-lucide="zap" class="w-6 h-6 text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold mb-1">Quick & Easy</h3>
                            <p class="text-sky-100 text-sm">List or rent items in minutes</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-4 bg-white/10 backdrop-blur-sm rounded-xl p-5 border border-white/20 hover:bg-white/20 hover:scale-105 transition-all shadow-lg cursor-pointer group">
                        <div class="w-12 h-12 bg-gradient-to-br from-emerald-400 to-emerald-500 rounded-xl flex items-center justify-center flex-shrink-0 shadow-lg group-hover:rotate-12 transition-transform">
                            <i data-lucide="key" class="w-6 h-6 text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold mb-1">Safe & Trusted</h3>
                            <p class="text-sky-100 text-sm">Student-verified community</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-4 bg-white/10 backdrop-blur-sm rounded-xl p-5 border border-white/20 hover:bg-white/20 hover:scale-105 transition-all shadow-lg cursor-pointer group">
                        <div class="w-12 h-12 bg-gradient-to-br from-yellow-400 to-orange-400 rounded-xl flex items-center justify-center flex-shrink-0 shadow-lg group-hover:rotate-12 transition-transform">
                            <i data-lucide="home" class="w-6 h-6 text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold mb-1">Save Money</h3>
                            <p class="text-sky-100 text-sm">Rent instead of buying new</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom decoration -->
            <div class="text-sky-200 text-sm">
                © 2025 RENTayo. All rights reserved.
            </div>
        </div>
    </div>

    <!-- Right Panel - Login Form -->
    <div class="flex-1 flex items-center justify-center p-6 lg:p-12 relative">
        <!-- Decorative elements -->
        <div class="absolute top-10 right-10 w-32 h-32 bg-sky-200 rounded-full blur-3xl opacity-50 animate-pulse"></div>
        <div class="absolute bottom-10 left-10 w-40 h-40 bg-cyan-200 rounded-full blur-3xl opacity-50 animate-pulse" style="animation-delay: 1s;"></div>
        <div class="absolute top-1/2 left-1/4 w-24 h-24 bg-blue-200 rounded-full blur-3xl opacity-40 animate-pulse" style="animation-delay: 0.5s;"></div>

        <div class="w-full max-w-md relative z-10">
            <!-- Mobile logo -->
            <div class="lg:hidden flex items-center gap-3 mb-8">
                <div class="w-10 h-10 bg-gradient-to-br from-sky-500 to-cyan-600 rounded-xl flex items-center justify-center shadow-lg">
                    <i data-lucide="package" class="w-5 h-5 text-white"></i>
                </div>
                <div>
                    <span class="text-gray-900 font-medium tracking-tight block">RENTayo</span>
                    <span class="text-sky-600 text-xs">Student Rentals</span>
                </div>
            </div>

            <!-- Form card -->
            <div class="bg-white rounded-2xl shadow-2xl border-2 border-sky-100 p-8 relative overflow-hidden hover:shadow-sky-200/50 transition-shadow">
                <!-- Decorative corner accents -->
                <div class="absolute top-0 right-0 w-40 h-40 bg-gradient-to-br from-sky-200 via-cyan-200 to-blue-200 rounded-bl-full opacity-40"></div>
                <div class="absolute -top-4 -right-4 w-24 h-24 border-4 border-sky-100 rounded-full"></div>
                <div class="absolute bottom-0 left-0 w-32 h-32 bg-gradient-to-tr from-cyan-100 to-transparent rounded-tr-full opacity-30"></div>
                
                <div class="relative z-10">
                    <!-- Form header -->
                    <div class="mb-8">
                        <div class="flex items-center gap-2 mb-3">
                            <h2 class="text-2xl font-semibold text-gray-900">Welcome back</h2>
                            <i data-lucide="sparkles" class="w-5 h-5 text-yellow-500 animate-pulse"></i>
                        </div>
                        <p class="text-gray-600">
                            Login to manage your rentals and listings
                        </p>
                    </div>

                    <!-- PHP General Error Display -->
                    <?php if (!empty($generalErr)) { ?>
                        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start gap-3">
                            <i data-lucide="alert-circle" class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5"></i>
                            <p class="text-sm text-red-800"><?php echo htmlspecialchars($generalErr); ?></p>
                        </div>
                    <?php } ?>

                    <!-- Login Form -->
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-5">
                        
                        <!-- Email Field -->
                        <div class="space-y-2">
                            <label for="email" class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                <div class="w-5 h-5 rounded-full bg-sky-100 flex items-center justify-center">
                                    <i data-lucide="mail" class="w-3 h-3 text-sky-600"></i>
                                </div>
                                Email
                            </label>
                            <input
                                id="email"
                                name="email"
                                type="text"
                                placeholder="your.email@example.com"
                                value="<?php echo htmlspecialchars($email); ?>"
                                class="w-full h-12 px-4 border-2 <?php echo $emailErr ? 'border-red-500' : 'border-gray-200'; ?> rounded-lg focus:outline-none focus:border-sky-400 focus:ring-4 focus:ring-sky-400/20 transition-all"
                                autocomplete="email"
                                autofocus
                            />
                            <?php if ($emailErr) { ?>
                                <p class="text-sm text-red-600"><?php echo $emailErr; ?></p>
                            <?php } ?>
                        </div>

                        <!-- Password Field -->
                        <div class="space-y-2">
                            <label for="password" class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                <div class="w-5 h-5 rounded-full bg-sky-100 flex items-center justify-center">
                                    <i data-lucide="lock" class="w-3 h-3 text-sky-600"></i>
                                </div>
                                Password
                            </label>
                            <div class="relative">
                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    placeholder="Enter your password"
                                    class="w-full h-12 px-4 pr-12 border-2 <?php echo $passwordErr ? 'border-red-500' : 'border-gray-200'; ?> rounded-lg focus:outline-none focus:border-sky-400 focus:ring-4 focus:ring-sky-400/20 transition-all"
                                    autocomplete="current-password"
                                />
                                <button
                                    type="button"
                                    onclick="togglePassword()"
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-sky-600 hover:scale-110 transition-all"
                                    tabindex="-1"
                                >
                                    <i data-lucide="eye" id="eye-icon" class="w-5 h-5"></i>
                                </button>
                            </div>
                            <?php if ($passwordErr) { ?>
                                <p class="text-sm text-red-600"><?php echo $passwordErr; ?></p>
                            <?php } ?>
                        </div>

                        <!-- Forgot password link -->
                        <div class="flex justify-end">
                            <a href="#" class="text-sm text-sky-600 hover:text-sky-700 hover:underline transition-colors inline-flex items-center gap-1 group">
                                Forgot your password?
                                <i data-lucide="arrow-right" class="w-3 h-3 group-hover:translate-x-1 transition-transform"></i>
                            </a>
                        </div>

                        <!-- Submit buttons -->
                        <div class="space-y-3 pt-2">
                            <button
                                type="submit"
                                class="w-full h-12 bg-gradient-to-r from-sky-500 to-cyan-600 hover:from-sky-600 hover:to-cyan-700 text-white font-medium rounded-lg shadow-lg shadow-sky-500/50 hover:shadow-xl hover:shadow-sky-500/60 hover:scale-105 transition-all flex items-center justify-center gap-2 group"
                            >
                                Login
                                <i data-lucide="arrow-right" class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
                            </button>

                            <div class="relative">
                                <div class="absolute inset-0 flex items-center">
                                    <div class="w-full border-t border-gray-200"></div>
                                </div>
                                <div class="relative flex justify-center text-xs">
                                    <span class="bg-white px-3 text-gray-500">or</span>
                                </div>
                            </div>

                            <a
                                href="register.php"
                                class="w-full h-12 border-2 border-sky-200 hover:bg-sky-50 hover:border-sky-300 text-gray-700 font-medium rounded-lg hover:scale-105 transition-all flex items-center justify-center gap-2 group"
                            >
                                Create an account
                                <i data-lucide="sparkles" class="w-4 h-4 text-sky-500 group-hover:rotate-12 transition-transform"></i>
                            </a>
                        </div>
                    </form>

                    <!-- Trust badges -->
                    <div class="mt-8 pt-6 border-t border-gray-100">
                        <div class="flex items-center justify-center gap-6 text-xs text-gray-600">
                            <div class="flex items-center gap-1.5 bg-sky-50 px-3 py-2 rounded-full">
                                <i data-lucide="shield" class="w-4 h-4 text-sky-600"></i>
                                <span>Secure</span>
                            </div>
                            <div class="flex items-center gap-1.5 bg-emerald-50 px-3 py-2 rounded-full">
                                <i data-lucide="zap" class="w-4 h-4 text-emerald-600"></i>
                                <span>Fast</span>
                            </div>
                            <div class="flex items-center gap-1.5 bg-cyan-50 px-3 py-2 rounded-full">
                                <i data-lucide="sparkles" class="w-4 h-4 text-cyan-600"></i>
                                <span>Easy</span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer links -->
                    <div class="mt-6 text-center">
                        <p class="text-xs text-gray-500">
                            By continuing, you agree to our 
                            <a href="#" class="text-sky-600 hover:underline">Terms</a> 
                            and 
                            <a href="#" class="text-sky-600 hover:underline">Privacy Policy</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Initialize Lucide Icons -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide && typeof lucide.createIcons === 'function') {
                lucide.createIcons();
            }
        });
        
        // Password toggle function
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            if (!passwordInput || !eyeIcon) return;

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.setAttribute('data-lucide', 'eye-off');
            } else {
                passwordInput.type = 'password';
                eyeIcon.setAttribute('data-lucide', 'eye');
            }
            // Re-render the swapped icon
            if (window.lucide && typeof lucide.createIcons === 'function') {
                lucide.createIcons();
            }
        }
    </script>
</body>
</html>
