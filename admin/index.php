<?php
include("../connections.php");
session_start();

// Admin gate
if (empty($_SESSION['ID'])) { header("Location: ../login.php"); exit; }
$uid = (int)$_SESSION['ID'];
$chk = mysqli_query($connections, "SELECT is_admin FROM users WHERE ID = $uid LIMIT 1");
$row = $chk ? mysqli_fetch_assoc($chk) : null;
if (!$row || (int)$row['is_admin'] !== 1) { header("Location: ../login.php"); exit; }

// Helpers
function getPaymentSettings(mysqli $connections): array {
    $create = "CREATE TABLE IF NOT EXISTS `payment_settings` (
        `id` TINYINT(1) NOT NULL PRIMARY KEY,
        `gcash_number` VARCHAR(30) NOT NULL DEFAULT '',
        `gcash_qr_url` VARCHAR(255) NOT NULL DEFAULT '',
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    mysqli_query($connections, $create);

    $check = mysqli_query($connections, "SELECT * FROM payment_settings WHERE id = 1");
    if (!$check || mysqli_num_rows($check) === 0) {
        mysqli_query($connections, "INSERT INTO payment_settings (id, gcash_number, gcash_qr_url) VALUES (1, '09123456789', '')");
        return ['gcash_number' => '09123456789', 'gcash_qr_url' => ''];
    }
    return mysqli_fetch_assoc($check) ?: ['gcash_number' => '09123456789', 'gcash_qr_url' => ''];
}

// Light stats
$settings = getPaymentSettings($connections);
$pnd = 0;
$repCntRes = @mysqli_query($connections, "SELECT COUNT(*) AS c FROM reports WHERE status='pending'");
if ($repCntRes && ($rc = mysqli_fetch_assoc($repCntRes))) $pnd = (int)$rc['c'];

// Count pending items
$pendingItems = 0;
$piRes = @mysqli_query($connections, "SELECT COUNT(*) AS c FROM items WHERE status='pending'");
if ($piRes && ($pr = mysqli_fetch_assoc($piRes))) $pendingItems = (int)$pr['c'];

// Additional stats
$totalUsers = 0;
$userRes = @mysqli_query($connections, "SELECT COUNT(*) AS c FROM users");
if ($userRes && ($ur = mysqli_fetch_assoc($userRes))) $totalUsers = (int)$ur['c'];

$totalItems = 0;
$itemRes = @mysqli_query($connections, "SELECT COUNT(*) AS c FROM items");
if ($itemRes && ($ir = mysqli_fetch_assoc($itemRes))) $totalItems = (int)$ir['c'];

$activeRentals = 0;
$rentalRes = @mysqli_query($connections, "SELECT COUNT(*) AS c FROM rentals WHERE status='active'");
if ($rentalRes && ($rr = mysqli_fetch_assoc($rentalRes))) $activeRentals = (int)$rr['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - RenTayo</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #0ea5e9, #06b6d4);
            border-radius: 4px;
        }

        /* Animations */
        @keyframes pulse {
            0%, 100% { opacity: 0.2; }
            50% { opacity: 0.3; }
        }

        .animate-pulse-slow {
            animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        /* Footer layout fixes */
        .site-footer-wrapper { margin-top: 2rem; }
        .site-footer-wrapper > * { position: relative !important; width: 100% !important; }
        body { padding-bottom: 3.5rem; }
    </style>
</head>
<body class="bg-gradient-to-br from-sky-50 via-white to-blue-50 min-h-screen">
    
    <!-- Decorative background -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute top-20 right-20 w-96 h-96 bg-sky-200 rounded-full blur-3xl opacity-20 animate-pulse"></div>
        <div class="absolute bottom-20 left-20 w-96 h-96 bg-cyan-200 rounded-full blur-3xl opacity-20 animate-pulse" style="animation-delay: 1s;"></div>
    </div>

    <!-- NAVBAR PLACEHOLDER -->
    <?php include __DIR__ . '/admin_navbar.php'; ?>

    <!-- Main Content -->
    <div class="relative z-10 max-w-7xl mx-auto px-4 py-8">
        
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-sky-500 to-cyan-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i data-lucide="layout-dashboard" class="w-8 h-8 text-white"></i>
                </div>
                <div>
                    <h1 class="text-4xl font-bold text-gray-900">Admin Dashboard</h1>
                    <p class="text-gray-600">Manage your RenTayo platform</p>
                </div>
            </div>
        </div>

        <!-- Stats Overview Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Users -->
            <div class="bg-white rounded-2xl border-2 border-sky-100 p-6 shadow-lg shadow-sky-200/30 hover:scale-105 transition-all cursor-pointer group">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-sky-100 to-cyan-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i data-lucide="users" class="w-6 h-6 text-sky-600"></i>
                    </div>
                    <span class="text-3xl font-bold text-gray-900"><?php echo $totalUsers; ?></span>
                </div>
                <p class="text-gray-600 font-medium">Total Users</p>
            </div>

            <!-- Total Items -->
            <div class="bg-white rounded-2xl border-2 border-emerald-100 p-6 shadow-lg shadow-emerald-200/30 hover:scale-105 transition-all cursor-pointer group">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-emerald-100 to-emerald-200 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i data-lucide="package" class="w-6 h-6 text-emerald-600"></i>
                    </div>
                    <span class="text-3xl font-bold text-gray-900"><?php echo $totalItems; ?></span>
                </div>
                <p class="text-gray-600 font-medium">Total Items</p>
            </div>

            <!-- Active Rentals -->
            <div class="bg-white rounded-2xl border-2 border-purple-100 p-6 shadow-lg shadow-purple-200/30 hover:scale-105 transition-all cursor-pointer group">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-purple-100 to-purple-200 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i data-lucide="trending-up" class="w-6 h-6 text-purple-600"></i>
                    </div>
                    <span class="text-3xl font-bold text-gray-900"><?php echo $activeRentals; ?></span>
                </div>
                <p class="text-gray-600 font-medium">Active Rentals</p>
            </div>

            <!-- Pending Reports -->
            <div class="bg-white rounded-2xl border-2 border-amber-100 p-6 shadow-lg shadow-amber-200/30 hover:scale-105 transition-all cursor-pointer group">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-amber-100 to-amber-200 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i data-lucide="alert-circle" class="w-6 h-6 text-amber-600"></i>
                    </div>
                    <span class="text-3xl font-bold text-gray-900"><?php echo $pnd; ?></span>
                </div>
                <p class="text-gray-600 font-medium">Pending Reports</p>
            </div>
        </div>

        <!-- Action Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Pending Items Card -->
            <div class="bg-white rounded-3xl shadow-2xl shadow-sky-200/50 border-2 border-sky-100 overflow-hidden hover:shadow-xl hover:shadow-sky-300/40 transition-all group">
                <div class="bg-gradient-to-br from-sky-50 to-cyan-50 p-6 border-b-2 border-sky-100">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 bg-gradient-to-br from-sky-500 to-cyan-600 rounded-xl flex items-center justify-center shadow-lg">
                            <i data-lucide="clock" class="w-5 h-5 text-white"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900">Pending Items</h2>
                    </div>
                    <p class="text-gray-600 text-sm">Items awaiting approval</p>
                </div>
                
                <div class="p-6">
                    <div class="mb-6">
                        <div class="flex items-baseline gap-2 mb-2">
                            <span class="text-5xl font-bold text-gray-900"><?php echo $pendingItems; ?></span>
                            <span class="text-gray-500">items</span>
                        </div>
                        <p class="text-gray-600 text-sm">
                            <?php if ($pendingItems > 0): ?>
                                Review and approve new item listings
                            <?php else: ?>
                                All items have been reviewed
                            <?php endif; ?>
                        </p>
                    </div>

                    <?php if ($pendingItems > 0): ?>
                    <div class="bg-amber-50 border-2 border-amber-200 rounded-xl p-4 mb-4 flex items-start gap-3">
                        <i data-lucide="bell" class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5"></i>
                        <p class="text-sm text-amber-800">
                            <strong><?php echo $pendingItems; ?></strong> item<?php echo $pendingItems !== 1 ? 's' : ''; ?> waiting for your review
                        </p>
                    </div>
                    <?php endif; ?>

                    <a href="pending_items.php" class="flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-sky-500 to-cyan-600 hover:from-sky-600 hover:to-cyan-700 text-white font-bold rounded-xl shadow-lg shadow-sky-500/30 transition-all hover:scale-105 group">
                        <span>Review Items</span>
                        <i data-lucide="arrow-right" class="w-5 h-5 group-hover:translate-x-1 transition-transform"></i>
                    </a>
                </div>
            </div>

            <!-- Reports Card -->
            <div class="bg-white rounded-3xl shadow-2xl shadow-red-200/50 border-2 border-red-100 overflow-hidden hover:shadow-xl hover:shadow-red-300/40 transition-all group">
                <div class="bg-gradient-to-br from-red-50 to-rose-50 p-6 border-b-2 border-red-100">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 bg-gradient-to-br from-red-500 to-rose-600 rounded-xl flex items-center justify-center shadow-lg">
                            <i data-lucide="flag" class="w-5 h-5 text-white"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900">Reports</h2>
                    </div>
                    <p class="text-gray-600 text-sm">User-submitted reports</p>
                </div>
                
                <div class="p-6">
                    <div class="mb-6">
                        <div class="flex items-baseline gap-2 mb-2">
                            <span class="text-5xl font-bold text-gray-900"><?php echo $pnd; ?></span>
                            <span class="text-gray-500">pending</span>
                        </div>
                        <p class="text-gray-600 text-sm">
                            <?php if ($pnd > 0): ?>
                                Address reported issues and violations
                            <?php else: ?>
                                No pending reports
                            <?php endif; ?>
                        </p>
                    </div>

                    <?php if ($pnd > 0): ?>
                    <div class="bg-red-50 border-2 border-red-200 rounded-xl p-4 mb-4 flex items-start gap-3">
                        <i data-lucide="alert-triangle" class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5"></i>
                        <p class="text-sm text-red-800">
                            <strong><?php echo $pnd; ?></strong> report<?php echo $pnd !== 1 ? 's' : ''; ?> need<?php echo $pnd === 1 ? 's' : ''; ?> attention
                        </p>
                    </div>
                    <?php endif; ?>

                    <a href="reports.php" class="flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-red-500 to-rose-600 hover:from-red-600 hover:to-rose-700 text-white font-bold rounded-xl shadow-lg shadow-red-500/30 transition-all hover:scale-105 group">
                        <span>View Reports</span>
                        <i data-lucide="arrow-right" class="w-5 h-5 group-hover:translate-x-1 transition-transform"></i>
                    </a>
                </div>
            </div>

            <!-- Payment Settings Card -->
            <div class="bg-white rounded-3xl shadow-2xl shadow-emerald-200/50 border-2 border-emerald-100 overflow-hidden hover:shadow-xl hover:shadow-emerald-300/40 transition-all group">
                <div class="bg-gradient-to-br from-emerald-50 to-teal-50 p-6 border-b-2 border-emerald-100">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-xl flex items-center justify-center shadow-lg">
                            <i data-lucide="credit-card" class="w-5 h-5 text-white"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900">Payment Settings</h2>
                    </div>
                    <p class="text-gray-600 text-sm">Configure payment methods</p>
                </div>
                
                <div class="p-6">
                    <div class="mb-6">
                        <div class="mb-4">
                            <p class="text-xs text-gray-500 mb-1">GCash Number</p>
                            <p class="text-lg font-bold text-gray-900">
                                <?php echo htmlspecialchars($settings['gcash_number'] ?? 'Not set'); ?>
                            </p>
                        </div>

                        <?php if (!empty($settings['gcash_qr_url'])): ?>
                        <div class="bg-gradient-to-br from-emerald-50 to-teal-50 border-2 border-emerald-200 rounded-xl p-4">
                            <p class="text-xs text-gray-600 mb-3 font-medium">Current QR Code</p>
                            <div class="flex justify-center">
                                <img 
                                    src="<?php echo '../' . htmlspecialchars($settings['gcash_qr_url']); ?>" 
                                    alt="GCash QR Code" 
                                    class="max-w-full h-auto rounded-lg border-2 border-emerald-300 shadow-lg"
                                    style="max-height: 180px;"
                                />
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="bg-gray-50 border-2 border-gray-200 rounded-xl p-6 text-center">
                            <i data-lucide="image-off" class="w-12 h-12 text-gray-400 mx-auto mb-2"></i>
                            <p class="text-sm text-gray-600">No QR code uploaded</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <a href="payment_settings.php" class="flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-bold rounded-xl shadow-lg shadow-emerald-500/30 transition-all hover:scale-105 group">
                        <i data-lucide="settings" class="w-5 h-5"></i>
                        <span>Edit Settings</span>
                    </a>
                </div>
            </div>

        </div>

       

    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
