<?php
session_start();
require_once 'connections.php';
require_once 'ban_guard.php'; // auto-logout if current user is banned

// If DB connection is missing, avoid fatal errors and show a minimal message
if (!isset($connections) || !$connections) {
    error_log('index.php: No DB connection available.');
}

// Check if user is logged in
if (!isset($_SESSION["ID"])) {
    header("Location: login.php");
    exit;
}



// Enable hamburger button for this page
$showHamburger = true;

// Categories query (case-sensitive table names on Linux)
$categories = mysqli_query($connections, "SELECT * FROM categories");
if ($categories === false) {
    error_log('SQL error (categories): ' . mysqli_error($connections));
}

// If category filter, search, or lender filter is selected
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$searchQuery    = isset($_GET['search']) ? trim($_GET['search']) : '';
$lenderFilter   = isset($_GET['lender_id']) ? (int)$_GET['lender_id'] : 0;
$categoryName   = null;
$lenderName     = null;

if ($categoryFilter) {
    $categoryQuery = "SELECT name FROM categories WHERE category_id = " . (int)$categoryFilter;
    $categoryResult = mysqli_query($connections, $categoryQuery);
    if ($categoryResult && mysqli_num_rows($categoryResult) > 0) {
        $categoryRow = mysqli_fetch_assoc($categoryResult);
        $categoryName = $categoryRow['name'];
    }
}

if ($lenderFilter) {
    $lenderQuery = "SELECT username FROM users WHERE ID = " . (int)$lenderFilter;
    $lenderResult = mysqli_query($connections, $lenderQuery);
    if ($lenderResult && mysqli_num_rows($lenderResult) > 0) {
        $lenderRow = mysqli_fetch_assoc($lenderResult);
        $lenderName = $lenderRow['username'];
    }
}

// Build items query (apply filters). Guard optional columns for portability
$where = [];
// Only filter by status if column exists
$col = mysqli_query($connections, "SHOW COLUMNS FROM items LIKE 'status'");
if ($col && mysqli_num_rows($col) > 0) {
    $where[] = "i.status = 'approved'";
}

if ($categoryFilter) {
    // filter by category via join table
    $where[] = "ic.category_id = " . (int)$categoryFilter;
}
    
if ($searchQuery !== '') {
    $q = mysqli_real_escape_string($connections, $searchQuery);
    $where[] = "(i.title LIKE '%$q%' OR i.description LIKE '%$q%')";
}
    
if ($lenderFilter) {
    $where[] = "i.lender_id = " . (int)$lenderFilter;
}

// Show only items added in the last 30 days
$where[] = "i.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";

// Build final SQL
$items_sql = "
    SELECT 
        i.*,
        u.username,
        COALESCE(GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', '), '') AS categories
    FROM items i
    INNER JOIN users u ON u.ID = i.lender_id
    LEFT JOIN itemcategories ic ON ic.item_id = i.item_id
    LEFT JOIN categories c ON c.category_id = ic.category_id
    " . (count($where) ? "WHERE " . implode(" AND ", $where) : "") . "
    GROUP BY i.item_id
    ORDER BY i.item_id DESC
";
$items_res = mysqli_query($connections, $items_sql);
if ($items_res === false) {
    error_log('SQL error (items list): ' . mysqli_error($connections));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RENTayo - Student Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Poppins', sans-serif; }
        /* Sidebar + layout (categories removed from UI) */
        .sidebar { position: fixed; left:0; top:0; height:100%; width:280px; background:#fff; box-shadow:0 20px 25px rgba(0,0,0,0.08); transform:translateX(-100%); transition:transform .3s ease; z-index:40; border-right:2px solid #e0f2fe; }
        .sidebar.open { transform: translateX(0); }
        @media(min-width:1024px){
            .sidebar { transform: translateX(0); }
            .main { margin-left:280px; }
            /* Align footer with main content when sidebar is visible */
            .site-footer { margin-left:280px; width: calc(100% - 280px); }
        }
        .main { transition: margin-left .3s ease; }
        .main.shifted { margin-left:280px; }
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:30; }
        .sidebar-overlay.show { display:block; }

        /* Slightly larger sidebar category links (bigger but subtle) */
        .sidebar .category-link {
            display: block;
            font-size: 1.03rem; /* slightly larger */
            padding: 0.65rem 0.95rem; /* a bit more vertical/horizontal space */
            border-radius: 0.75rem;
            font-weight: 600;
            transition: background-color .15s ease, transform .08s ease;
            line-height: 1.25;
        }
        .sidebar .category-link:hover { transform: translateY(-2px); background: #e6f7ff; }

        /* Bigger categories heading (noticeable but balanced) */
        .sidebar .categories-heading {
            font-size: 1.125rem; /* ~18px */
            font-weight: 700;
            color: #0f172a;
            margin-top: 0.5rem;
            line-height: 1.1;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-sky-50 via-white to-blue-50 min-h-screen">
    <!-- decorative -->
    <div class="fixed inset-0 pointer-events-none z-0">
        <div class="absolute top-20 right-20 w-96 h-96 bg-sky-200 rounded-full blur-3xl opacity-20 animate-pulse"></div>
        <div class="absolute bottom-20 left-20 w-96 h-96 bg-cyan-200 rounded-full blur-3xl opacity-20 animate-pulse" style="animation-delay:1s;"></div>
    </div>

    <?php include "navbar.php"; ?>

    <div class="sidebar" id="sidebar" aria-hidden="true">
        <div class="h-full flex flex-col">
            <div class="p-6 border-b-2 border-sky-100 bg-gradient-to-br from-sky-50 to-cyan-50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-sky-500 to-cyan-600 rounded-xl flex items-center justify-center">
                        <i data-lucide="grid-3x3" class="w-5 h-5 text-white"></i>
                    </div>
                    <h2 class="text-xl font-semibold text-gray-900">Browse</h2>
                </div>
                <div>
                    <div class="categories-heading">Categories</div>
                </div>
            </div>

            <div class="flex-1 p-4">
                <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl mb-2 bg-gradient-to-r from-sky-500 to-cyan-600 text-white shadow-lg">
                    <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                        <i data-lucide="layers" class="w-4 h-4 text-white"></i>
                    </div>
                    <span class="font-medium">All Items</span>
                </a>

                <!-- Categories list re-added -->
                <?php if ($categories && mysqli_num_rows($categories) > 0): ?>
                    <div class="mt-4">
                        <?php while ($category = mysqli_fetch_assoc($categories)): ?>
                            <a href="index.php?category=<?php echo $category['category_id']; ?>" class="category-link text-gray-900 hover:bg-sky-100 transition-colors">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </a>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="p-4 border-t-2 border-sky-100 bg-gradient-to-br from-sky-50 to-cyan-50">
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <i data-lucide="sparkles" class="w-4 h-4 text-sky-500 animate-pulse"></i>
                    <span>Discover amazing rentals</span>
                </div>
            </div>
        </div>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()" aria-hidden="true"></div>

    <div class="main relative z-10" id="mainContent">
        <div class="max-w-[1600px] mx-auto p-4 md:p-6 lg:p-8">
            <!-- Page Header -->
            <div class="mb-8">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-12 h-12 bg-gradient-to-br from-sky-500 to-cyan-600 rounded-xl flex items-center justify-center shadow-lg">
                        <i data-lucide="shopping-bag" class="w-6 h-6 text-white"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl md:text-4xl font-bold text-gray-900">Available Items</h1>
                        <div class="flex items-center gap-2 mt-1">
                            <i data-lucide="sparkles" class="w-4 h-4 text-sky-500 animate-pulse"></i>
                            <p class="text-gray-600">Discover what you need</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Grid -->
            <div id="itemsContainer" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php if ($items_res && mysqli_num_rows($items_res) > 0): ?>
                    <?php while ($item = mysqli_fetch_assoc($items_res)): 
                        $imagePath = !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : '';
                        $imageSrc = $imagePath ?: 'images/default-item.jpg';
                    ?>
                    <a href="item_details.php?item_id=<?php echo (int)$item['item_id']; ?>" class="group bg-white rounded-2xl shadow-lg border-2 border-sky-100 overflow-hidden transition-all hover:scale-105">
                        <div class="relative h-56 bg-gradient-to-br from-sky-100 to-cyan-100 overflow-hidden">
                            <?php if ($imagePath): ?>
                                <img src="<?php echo $imageSrc; ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform">
                            <?php else: ?>
                                <div class="flex items-center justify-center h-full">
                                    <i data-lucide="image" class="w-12 h-12 text-sky-400"></i>
                                </div>
                            <?php endif; ?>
                            <div class="absolute top-3 right-3">
                                <div class="bg-white/95 px-4 py-2 rounded-xl shadow-lg border-2 border-sky-100">
                                    <div class="flex items-center gap-1">
                                        <span class="text-lg font-bold text-sky-600">₱<?php echo number_format($item['price_per_day'], 2); ?></span>
                                        <span class="text-xs text-gray-600">/day</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="p-5">
                            <h3 class="text-lg font-bold text-gray-900 mb-2 line-clamp-1"><?php echo htmlspecialchars($item['title']); ?></h3>
                            <p class="text-sm text-gray-600 mb-4 line-clamp-2"><?php echo htmlspecialchars($item['description']); ?></p>
                            <div class="flex items-center justify-between pt-4 border-t-2 border-gray-100">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 bg-gradient-to-br from-sky-400 to-cyan-500 rounded-full flex items-center justify-center">
                                        <i data-lucide="user" class="w-4 h-4 text-white"></i>
                                    </div>
                                    <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($item['username'] ?? 'Unknown'); ?></span>
                                </div>
                                <div class="flex items-center gap-1 text-sky-600">
                                    <span class="text-sm font-medium">View</span>
                                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                </div>
                            </div>
                        </div>
                    </a>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-full flex flex-col items-center justify-center py-20">
                        <div class="w-24 h-24 bg-gradient-to-br from-sky-100 to-cyan-100 rounded-3xl flex items-center justify-center mb-6 animate-pulse">
                            <i data-lucide="inbox" class="w-12 h-12 text-sky-400"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">No items found</h3>
                        <p class="text-gray-600 mb-6">Try adjusting your filters or check back later</p>
                        <a href="index.php" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-sky-500 to-cyan-600 text-white rounded-xl shadow-lg">
                            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                            View All Items
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        lucide.createIcons();
        function toggleSidebar(){
            const sidebar = document.getElementById('sidebar');
            const main = document.getElementById('mainContent');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('open');
            main.classList.toggle('shifted');
            overlay.classList.toggle('show');
            setTimeout(()=>lucide.createIcons(),100);
        }
    </script>
</body>
</html>