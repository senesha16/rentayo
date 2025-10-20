<?php
session_start();
require_once 'connections.php'; // Database connection

// Check if user is logged in
if (!isset($_SESSION['ID'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['ID'];

// Fetch user's items from database
$items = [];
$stats = [
    'total' => 0,
    'available' => 0,
    'rented' => 0,
    'total_rentals' => 0
];

// First, let's check what columns exist in the items table
$columns_query = "SHOW COLUMNS FROM items";
$columns_result = mysqli_query($connections, $columns_query);
$item_columns = [];
while ($col = mysqli_fetch_assoc($columns_result)) {
    $item_columns[] = $col['Field'];
}

// Try to find the user/owner column
$user_column = null;
$possible_user_columns = ['user_id', 'owner_id', 'seller_id', 'lender_id', 'created_by'];
foreach ($possible_user_columns as $possible) {
	if (in_array($possible, $item_columns, true)) {
		$user_column = $possible;
		break;
	}
}

// Detect image column (common names)
$image_col = null;
$possible_image_cols = ['image','image_url','image_path','images','photos','photo','thumbnail','thumb','image1'];
foreach ($possible_image_cols as $pc) {
	if (in_array($pc, $item_columns, true)) {
		$image_col = $pc;
		break;
	}
}

// Check if rentals table exists and detect linking columns
$rentals_exists = false;
$rentals_item_col = null;
$rentals_columns = [];
$tables_result = mysqli_query($connections, "SHOW TABLES LIKE 'rentals'");
if (mysqli_num_rows($tables_result) > 0) {
    $rentals_exists = true;
    $rentals_cols_res = mysqli_query($connections, "SHOW COLUMNS FROM rentals");
    while ($rc = mysqli_fetch_assoc($rentals_cols_res)) {
        $rentals_columns[] = $rc['Field'];
    }

    // Attempt to detect common FK column name in rentals that references items
    $possible_rental_item_cols = ['item_id','listing_id','product_id','itemID','itemId','id_item','listingId'];
    foreach ($possible_rental_item_cols as $pc) {
        if (in_array($pc, $rentals_columns, true)) {
            $rentals_item_col = $pc;
            break;
        }
    }
}

// Detect items primary/key column to use in JOIN ON (common names)
$possible_item_pk = ['id','item_id','ID','itemID','itemId','listing_id'];
$item_pk = null;
foreach ($possible_item_pk as $pc) {
    if (in_array($pc, $item_columns, true)) {
        $item_pk = $pc;
        break;
    }
}
// fallback to first column if none of the common names matched
if (!$item_pk) {
    $item_pk = $item_columns[0] ?? 'id';
}

// If we couldn't find a rentals FK column, disable rentals aggregation to avoid invalid JOIN
if ($rentals_exists && !$rentals_item_col) {
    $rentals_exists = false;
}

// Build query
if ($rentals_exists) {
    // Use detected column names in SELECT / JOIN
    $query = "SELECT 
        i.*,
        COUNT(r.`$rentals_item_col`) AS rental_count,
        SUM(CASE WHEN r.status = 'active' THEN 1 ELSE 0 END) AS active_rentals
    FROM items i
    LEFT JOIN rentals r ON i.`$item_pk` = r.`$rentals_item_col`";

    if ($user_column) {
        $query .= " WHERE i.`$user_column` = ?";
    }

    $query .= " GROUP BY i.`$item_pk`";

    if (in_array('created_at', $item_columns, true)) {
        $query .= " ORDER BY i.created_at DESC";
    } else {
        $query .= " ORDER BY i.`$item_pk` DESC";
    }
} else {
    // No reliable rentals join available — simple items query
    $query = "SELECT * FROM items";
    if ($user_column) {
        $query .= " WHERE `$user_column` = ?";
    }
    if (in_array('created_at', $item_columns, true)) {
        $query .= " ORDER BY created_at DESC";
    } else {
        // use detected item pk or fallback to id
        $query .= " ORDER BY `$item_pk` DESC";
    }
}

$stmt = mysqli_prepare($connections, $query);
if ($stmt) {
	if ($user_column) {
		mysqli_stmt_bind_param($stmt, "i", $user_id);
	}
	mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);
	
	while ($row = mysqli_fetch_assoc($result)) {
		// Normalize quantity (default to 1 if missing)
		$quantity = isset($row['quantity']) ? (int)$row['quantity'] : 1;

		if ($rentals_exists) {
			$active_rentals = isset($row['active_rentals']) ? (int)$row['active_rentals'] : 0;
			$rental_count = isset($row['rental_count']) ? (int)$row['rental_count'] : 0;
			// fixed typo: use $active_rentals (was $active_rental)
			$available_qty = max(0, $quantity - $active_rentals);

			// update stats
			if ($available_qty > 0) {
				$stats['available']++;
			} else {
				$stats['rented']++;
			}
			$stats['total_rentals'] += $rental_count;
		} else {
			// No rentals table: treat all quantity as available
			$available_qty = $quantity;
			$rental_count = 0;
			$stats['available']++;
		}

		// Determine display image from detected image column
		$display_image = 'images/default-item.jpg';
		if ($image_col && !empty($row[$image_col])) {
			$raw = $row[$image_col];

			// try JSON decode (array of urls or objects)
			$decoded = json_decode($raw, true);
			if (is_array($decoded) && count($decoded)) {
				$first = $decoded[0];
				if (is_array($first)) {
					$display_image = $first['url'] ?? $first['path'] ?? reset($first) ?? $display_image;
				} else {
					$display_image = (string)$first;
				}
			} else {
				// comma or pipe separated list -> take first
				if (strpos($raw, ',') !== false) {
					$parts = explode(',', $raw);
					$display_image = trim($parts[0]);
				} elseif (strpos($raw, '|') !== false) {
					$parts = explode('|', $raw);
					$display_image = trim($parts[0]);
				} else {
					$display_image = trim($raw);
				}
			}
			if ($display_image === '') {
				$display_image = 'images/default-item.jpg';
			}
		}

		// Ensure the row contains keys the template expects
		$row['quantity'] = $quantity;
		$row['available_quantity'] = $available_qty;
		$row['rental_count'] = $rental_count;
		$row['display_image'] = $display_image; // <-- normalized image for template

		$items[] = $row;
		$stats['total']++;
	}
	
	mysqli_stmt_close($stmt);
}

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $item_id = (int)$_POST['item_id'];
    
    // Verify ownership using the detected user column
    if ($user_column) {
        $delete_query = "DELETE FROM items WHERE id = ? AND $user_column = ?";
        if ($stmt = mysqli_prepare($connections, $delete_query)) {
            mysqli_stmt_bind_param($stmt, "ii", $item_id, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            header('Location: my_items.php?deleted=1');
            exit;
        }
    } else {
        // No user column found, delete without ownership check (not recommended for production)
        $delete_query = "DELETE FROM items WHERE id = ?";
        if ($stmt = mysqli_prepare($connections, $delete_query)) {
            mysqli_stmt_bind_param($stmt, "i", $item_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            header('Location: my_items.php?deleted=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Items - RenTayo</title>
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

        /* Footer layout: wrapper spans full width; included footer content is centered */
        .site-footer-wrapper {
            margin-top: 2rem;
            width: 100%;
            padding: 1.25rem 0;
            box-sizing: border-box;
            position: relative;
            z-index: 0; /* sit behind any floating UI */
            background: transparent;
        }
        /* inner container constrains the included footer content without modifying footer.php */
        .site-footer-wrapper .footer-inner {
            max-width: 1120px;
            margin: 0 auto;
            padding: 0 1rem;
            box-sizing: border-box;
            position: static !important;
            width: 100%;
        }
        /* defensively ensure footer elements are not absolutely positioned by their own CSS */
        .site-footer-wrapper .footer-inner * { position: static !important; }
        /* Give the page bottom breathing room so footer doesn't overlap fixed content */
        body { padding-bottom: 140px; }
    </style>
</head>
<body class="bg-gradient-to-br from-sky-50 via-white to-blue-50 min-h-screen">
    
    <!-- Decorative background -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute top-20 right-20 w-96 h-96 bg-sky-200 rounded-full blur-3xl opacity-20 animate-pulse"></div>
        <div class="absolute bottom-20 left-20 w-96 h-96 bg-cyan-200 rounded-full blur-3xl opacity-20 animate-pulse" style="animation-delay: 1s;"></div>
    </div>

    <!-- NAVBAR PLACEHOLDER -->
    <?php include 'navbar.php'; ?>

    <!-- Main Content -->
    <div class="relative z-10 max-w-7xl mx-auto px-4 py-8">
        
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-sky-500 to-cyan-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i data-lucide="package" class="w-8 h-8 text-white"></i>
                </div>
                <div>
                    <h1 class="text-4xl font-bold text-gray-900">My Items</h1>
                    <p class="text-gray-600">Manage your rental listings</p>
                </div>
            </div>

            <!-- Success message -->
            <?php if (isset($_GET['deleted'])): ?>
            <div class="mb-4 p-4 bg-emerald-50 border-2 border-emerald-200 rounded-xl flex items-center gap-3">
                <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600"></i>
                <p class="text-emerald-700 font-medium">Item deleted successfully!</p>
            </div>
            <?php endif; ?>

            <!-- Action Bar -->
            <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                <div class="flex-1 w-full sm:max-w-md relative">
                    <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                    <input
                        type="text"
                        id="searchInput"
                        placeholder="Search your items..."
                        class="w-full pl-12 pr-4 py-3 bg-white border-2 border-sky-200 rounded-xl focus:outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-500/20 transition-all"
                    />
                </div>

                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <!-- View Toggle -->
                    <div class="flex items-center gap-1 bg-white border-2 border-sky-200 rounded-xl p-1">
                        <button
                            id="gridViewBtn"
                            onclick="setViewMode('grid')"
                            class="p-2 rounded-lg transition-all bg-gradient-to-br from-sky-500 to-cyan-600 text-white"
                        >
                            <i data-lucide="grid-3x3" class="w-5 h-5"></i>
                        </button>
                        <button
                            id="listViewBtn"
                            onclick="setViewMode('list')"
                            class="p-2 rounded-lg transition-all text-gray-600 hover:bg-sky-50"
                        >
                            <i data-lucide="list" class="w-5 h-5"></i>
                        </button>
                    </div>

                    <a href="add_item.php" class="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-sky-500 to-cyan-600 hover:from-sky-600 hover:to-cyan-700 text-white font-bold rounded-xl shadow-lg shadow-sky-500/30 transition-all hover:scale-105 group">
                        <i data-lucide="plus-circle" class="w-5 h-5"></i>
                        <span>Add Item</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-2xl border-2 border-sky-100 p-6 shadow-lg shadow-sky-200/30 hover:scale-105 transition-all cursor-pointer group">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-12 h-12 bg-gradient-to-br from-sky-100 to-cyan-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i data-lucide="package" class="w-6 h-6 text-sky-600"></i>
                    </div>
                    <span class="text-2xl font-bold text-gray-900"><?php echo $stats['total']; ?></span>
                </div>
                <p class="text-gray-600 font-medium">Total Items</p>
            </div>

            <div class="bg-white rounded-2xl border-2 border-emerald-100 p-6 shadow-lg shadow-emerald-200/30 hover:scale-105 transition-all cursor-pointer group">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-12 h-12 bg-gradient-to-br from-emerald-100 to-emerald-200 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i data-lucide="eye" class="w-6 h-6 text-emerald-600"></i>
                    </div>
                    <span class="text-2xl font-bold text-gray-900"><?php echo $stats['available']; ?></span>
                </div>
                <p class="text-gray-600 font-medium">Available</p>
            </div>

            <div class="bg-white rounded-2xl border-2 border-amber-100 p-6 shadow-lg shadow-amber-200/30 hover:scale-105 transition-all cursor-pointer group">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-12 h-12 bg-gradient-to-br from-amber-100 to-amber-200 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i data-lucide="package" class="w-6 h-6 text-amber-600"></i>
                    </div>
                    <span class="text-2xl font-bold text-gray-900"><?php echo $stats['rented']; ?></span>
                </div>
                <p class="text-gray-600 font-medium">Currently Rented</p>
            </div>

            <div class="bg-white rounded-2xl border-2 border-purple-100 p-6 shadow-lg shadow-purple-200/30 hover:scale-105 transition-all cursor-pointer group">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-12 h-12 bg-gradient-to-br from-purple-100 to-purple-200 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i data-lucide="trending-up" class="w-6 h-6 text-purple-600"></i>
                    </div>
                    <span class="text-2xl font-bold text-gray-900"><?php echo $stats['total_rentals']; ?></span>
                </div>
                <p class="text-gray-600 font-medium">Total Rentals</p>
            </div>
        </div>

        <!-- Items Display -->
        <?php if (empty($items)): ?>
        <!-- Empty State -->
        <div class="bg-white rounded-3xl shadow-2xl shadow-sky-200/50 border-2 border-sky-100 p-16 text-center">
            <div class="w-24 h-24 bg-gradient-to-br from-sky-100 to-cyan-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i data-lucide="package" class="w-12 h-12 text-sky-600"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-2">No items yet</h3>
            <p class="text-gray-600 mb-6">Start by adding your first item to rent out</p>
            <a href="add_item.php" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-sky-500 to-cyan-600 hover:from-sky-600 hover:to-cyan-700 text-white font-bold rounded-xl shadow-lg shadow-sky-500/30 transition-all hover:scale-105">
                <i data-lucide="plus-circle" class="w-5 h-5"></i>
                Add Your First Item
            </a>
        </div>
        <?php else: ?>

        <!-- Grid View -->
        <div id="gridView" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($items as $item): 
                $available_qty = (int)($item['available_quantity'] ?? $item['quantity']);
                $total_qty = (int)$item['quantity'];
                
                // Determine status
                if ($available_qty > 0) {
                    $status = 'available';
                    $statusClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                    $statusText = 'Available';
                } else {
                    $status = 'rented';
                    $statusClass = 'bg-amber-50 text-amber-700 border-amber-200';
                    $statusText = 'Rented Out';
                }
                
                // Use normalized display image (uploaded by user) or local default
                $image = $item['display_image'] ?? 'images/default-item.jpg';
                $title = htmlspecialchars($item['title'] ?? '');
                $description = htmlspecialchars($item['description'] ?? '');
                $price = number_format((float)($item['price_per_day'] ?? 0), 2);
                $rental_count = (int)($item['rental_count'] ?? 0);
            ?>
            <div class="item-card bg-white rounded-2xl shadow-lg shadow-sky-200/30 border-2 border-sky-100 overflow-hidden hover:shadow-xl hover:shadow-sky-300/40 hover:scale-105 transition-all group" data-title="<?php echo strtolower($title); ?>" data-description="<?php echo strtolower($description); ?>">
                <!-- Image -->
                <div class="relative h-48 overflow-hidden bg-gradient-to-br from-sky-100 to-cyan-100">
                    <img
                        src="<?php echo htmlspecialchars($image); ?>"
                        alt="<?php echo $title; ?>"
                        class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                        onerror="this.src='images/default-item.jpg'"
                    />
                    <div class="absolute top-3 right-3 px-3 py-1 rounded-full text-xs font-semibold border-2 backdrop-blur-sm <?php echo $statusClass; ?>">
                        <?php echo $statusText; ?>
                    </div>
                    <?php if ($rental_count > 0): ?>
                    <div class="absolute top-3 left-3 px-3 py-1 bg-white/90 backdrop-blur-sm rounded-full text-xs font-semibold border-2 border-sky-200 text-sky-700">
                        <?php echo $rental_count; ?> rentals
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Content -->
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-2 line-clamp-1"><?php echo $title; ?></h3>
                    <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?php echo $description; ?></p>

                    <!-- Price and Quantity -->
                    <div class="flex items-center justify-between mb-4 pb-4 border-b border-sky-100">
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Price per Day</p>
                            <p class="text-lg font-bold text-gray-900">
                                <span class="text-sky-600">₱</span><?php echo $price; ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-500 mb-1">Available</p>
                            <p class="text-lg font-bold text-gray-900">
                                <?php echo $available_qty; ?> / <?php echo $total_qty; ?>
                            </p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2">
                        <a href="edit_item.php?id=<?php echo urlencode($item[$item_pk]); ?>" class="flex-1 px-4 py-2 bg-sky-50 hover:bg-sky-100 text-sky-600 font-semibold border-2 border-sky-200 rounded-xl transition-all flex items-center justify-center gap-2 group">
                            <i data-lucide="edit" class="w-4 h-4 group-hover:rotate-12 transition-transform"></i>
                            Edit
                        </a>
                        <button onclick="confirmDelete(<?php echo htmlspecialchars(json_encode($item[$item_pk])); ?>, '<?php echo addslashes($title); ?>')" class="px-4 py-2 bg-red-50 hover:bg-red-100 text-red-600 font-semibold border-2 border-red-200 rounded-xl transition-all group">
                            <i data-lucide="trash-2" class="w-4 h-4 group-hover:scale-110 transition-transform"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- List View -->
        <div id="listView" class="hidden bg-white rounded-3xl shadow-2xl shadow-sky-200/50 border-2 border-sky-100 overflow-hidden">
            <div class="divide-y divide-sky-100">
                <?php foreach ($items as $item): 
                    $available_qty = (int)($item['available_quantity'] ?? $item['quantity']);
                    $total_qty = (int)$item['quantity'];
                    
                    if ($available_qty > 0) {
                        $status = 'available';
                        $statusClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                        $statusText = 'Available';
                    } else {
                        $status = 'rented';
                        $statusClass = 'bg-amber-50 text-amber-700 border-amber-200';
                        $statusText = 'Rented Out';
                    }
                    
                    $image = $item['display_image'] ?? 'images/default-item.jpg';
                    $title = htmlspecialchars($item['title'] ?? '');
                    $description = htmlspecialchars($item['description'] ?? '');
                    $price = number_format((float)($item['price_per_day'] ?? 0), 2);
                    $rental_count = (int)($item['rental_count'] ?? 0);
                ?>
                <div class="item-card p-6 hover:bg-sky-50 transition-all group" data-title="<?php echo strtolower($title); ?>" data-description="<?php echo strtolower($description); ?>">
                    <div class="flex flex-col sm:flex-row gap-6">
                        <!-- Image -->
                        <div class="relative w-full sm:w-32 h-32 flex-shrink-0 rounded-xl overflow-hidden bg-gradient-to-br from-sky-100 to-cyan-100">
                            <img
                                src="<?php echo htmlspecialchars($image); ?>"
                                alt="<?php echo $title; ?>"
                                class="w-full h-full object-cover group-hover:scale-110 transition-transform"
                                onerror="this.src='images/default-item.jpg'"
                            />
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between mb-2">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 mb-1"><?php echo $title; ?></h3>
                                    <p class="text-gray-600 text-sm mb-3 line-clamp-2"><?php echo $description; ?></p>
                                </div>
                                <div class="ml-4 px-3 py-1 rounded-full text-xs font-semibold border-2 whitespace-nowrap <?php echo $statusClass; ?>">
                                    <?php echo $statusText; ?>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-4 mb-4">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-500">Price:</span>
                                    <span class="text-base font-bold text-gray-900">
                                        <span class="text-sky-600">₱</span><?php echo $price; ?>/day
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-500">Available:</span>
                                    <span class="text-base font-bold text-gray-900">
                                        <?php echo $available_qty; ?> / <?php echo $total_qty; ?>
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-500">Total Rentals:</span>
                                    <span class="text-base font-bold text-gray-900"><?php echo $rental_count; ?></span>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <a href="edit_item.php?id=<?php echo urlencode($item[$item_pk]); ?>" class="px-4 py-2 bg-sky-50 hover:bg-sky-100 text-sky-600 font-semibold border-2 border-sky-200 rounded-xl transition-all flex items-center gap-2 group">
                                    <i data-lucide="edit" class="w-4 h-4 group-hover:rotate-12 transition-transform"></i>
                                    Edit
                                </a>
                                <button onclick="confirmDelete(<?php echo htmlspecialchars(json_encode($item[$item_pk])); ?>, '<?php echo addslashes($title); ?>')" class="px-4 py-2 bg-red-50 hover:bg-red-100 text-red-600 font-semibold border-2 border-red-200 rounded-xl transition-all group">
                                    <i data-lucide="trash-2" class="w-4 h-4 group-hover:scale-110 transition-transform"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php endif; ?>
    </div>

    <!-- FOOTER PLACEHOLDER -->
    <div class="site-footer-wrapper">
        <div class="footer-inner" role="contentinfo" aria-label="Site footer">
            <?php include 'footer.php'; ?>
        </div>
    </div>
 
    <!-- Delete Confirmation Modal (Hidden by default) -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 transform scale-95 transition-all" id="modalContent">
            <div class="text-center">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="alert-triangle" class="w-8 h-8 text-red-600"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Delete Item?</h3>
                <p class="text-gray-600 mb-6">Are you sure you want to delete "<span id="deleteItemName"></span>"? This action cannot be undone.</p>
                
                <form method="POST" class="flex gap-3">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="item_id" id="deleteItemId">
                    <button type="button" onclick="closeDeleteModal()" class="flex-1 px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition-all">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white font-bold rounded-xl shadow-lg shadow-red-500/30 transition-all hover:scale-105">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // View mode switching
        let currentView = 'grid';

        function setViewMode(mode) {
            currentView = mode;
            const gridView = document.getElementById('gridView');
            const listView = document.getElementById('listView');
            const gridBtn = document.getElementById('gridViewBtn');
            const listBtn = document.getElementById('listViewBtn');

            if (mode === 'grid') {
                gridView.classList.remove('hidden');
                listView.classList.add('hidden');
                gridBtn.classList.add('bg-gradient-to-br', 'from-sky-500', 'to-cyan-600', 'text-white');
                gridBtn.classList.remove('text-gray-600', 'hover:bg-sky-50');
                listBtn.classList.remove('bg-gradient-to-br', 'from-sky-500', 'to-cyan-600', 'text-white');
                listBtn.classList.add('text-gray-600', 'hover:bg-sky-50');
            } else {
                gridView.classList.add('hidden');
                listView.classList.remove('hidden');
                listBtn.classList.add('bg-gradient-to-br', 'from-sky-500', 'to-cyan-600', 'text-white');
                listBtn.classList.remove('text-gray-600', 'hover:bg-sky-50');
                gridBtn.classList.remove('bg-gradient-to-br', 'from-sky-500', 'to-cyan-600', 'text-white');
                gridBtn.classList.add('text-gray-600', 'hover:bg-sky-50');
            }

            lucide.createIcons();
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase();
            const items = document.querySelectorAll('.item-card');

            items.forEach(item => {
                const title = item.dataset.title || '';
                const description = item.dataset.description || '';
                const matches = title.includes(query) || description.includes(query);

                if (matches) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // Delete modal functions
        function confirmDelete(itemId, itemName) {
            document.getElementById('deleteItemId').value = itemId;
            document.getElementById('deleteItemName').textContent = itemName;
            const modal = document.getElementById('deleteModal');
            modal.classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('modalContent').classList.remove('scale-95');
                document.getElementById('modalContent').classList.add('scale-100');
            }, 10);
            lucide.createIcons();
        }

        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            document.getElementById('modalContent').classList.remove('scale-100');
            document.getElementById('modalContent').classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 200);
        }

        // Close modal on background click
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>