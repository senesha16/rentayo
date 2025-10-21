<?php
// Initialize session and DB so navbar and this page can query
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/connections.php';
require_once __DIR__ . '/ban_guard.php';
 
// Optional: require login to add items
if (empty($_SESSION['ID'])) {
    header('Location: login.php');
    exit;
}
 
// Fetch categories from DB (case-sensitive names on Linux)
$categories = [];
if (isset($connections) && $connections) {
    $res = @mysqli_query($connections, "SELECT category_id, name FROM categories ORDER BY name ASC");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) { $categories[] = $row; }
        mysqli_free_result($res);
    } else {
        error_log('add_item.php: categories query failed: ' . mysqli_error($connections));
    }
} else {
    error_log('add_item.php: No DB connection available.');
}
 
// Preserve selected categories if the page re-renders after validation errors
$selectedCategories = isset($_POST['categories']) && is_array($_POST['categories'])
    ? array_map('intval', $_POST['categories']) : [];
 
// Feedback messages for the UI
$error = '';
$success = '';
 
// Load payment settings (fee percent, fixed amount, QR)
$platformFeePercent = 5.0;   // default if not set in DB
$paymentFixedAmount = null;  // if set in DB, overrides percent calculation
$paymentQrUrl = '';          // QR image URL from DB
if (isset($connections) && $connections) {
    $setRes = @mysqli_query($connections, "SELECT * FROM payment_settings ORDER BY id DESC LIMIT 1");
    if ($setRes && mysqli_num_rows($setRes) > 0) {
        $row = mysqli_fetch_assoc($setRes);
        mysqli_free_result($setRes);
        foreach (['fee_percent','percent','listing_fee_percent','platform_fee_percent'] as $c) {
            if (isset($row[$c]) && is_numeric($row[$c])) { $platformFeePercent = (float)$row[$c]; break; }
        }
        foreach (['fixed_amount','amount','listing_fee_amount'] as $c) {
            if (isset($row[$c]) && is_numeric($row[$c]) && (float)$row[$c] > 0) { $paymentFixedAmount = (float)$row[$c]; break; }
        }
        foreach (['qr_image_url','qr_url','qr_path','qr','image_url'] as $c) {
            if (!empty($row[$c])) { $paymentQrUrl = (string)$row[$c]; break; }
        }
    }
}
 
// Preserve selected categories if the page re-renders after validation errors
$selectedCategories = isset($_POST['categories']) && is_array($_POST['categories'])
    ? array_map('intval', $_POST['categories']) : [];
 
// Feedback messages for the UI
$error = '';
$success = '';
 
// Add platform fee config (single-step flow: no prompt/session)
$platformFeePercent = 5.0; // 5% of price_per_day
$feeAmount = null;
 
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic form values
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price_per_day'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $lenderId = (int)($_SESSION['ID'] ?? 0);
    $paymentRef = trim($_POST['payment_reference'] ?? '');
 
    // NEW: early DB connection check (prevents false error on GET and cleanly fails only on POST)
    if (!isset($connections) || !$connections) {
        $error = 'Database connection is not available.';
    }
 
    // Validate item fields
    if ($error === '' && ($title === '' || $description === '')) {
        $error = 'Please provide a title and description.';
    } elseif ($error === '' && $price <= 0) {
        $error = 'Price must be greater than 0.';
    } elseif ($error === '' && $quantity < 1) {
        $error = 'Quantity must be at least 1.';
    } elseif ($error === '' && empty($selectedCategories)) {
        $error = 'Please select at least one category.';
    } elseif ($error === '' && !$lenderId) {
        $error = 'You must be logged in to add an item.';
    }
 
    // NEW: Require reference
    if ($error === '' && $paymentRef === '') {
        $error = 'Payment reference is required.';
    }
 
    // Compute listing fee (used in validation and success message)
    $feeAmount = ($paymentFixedAmount !== null && $paymentFixedAmount > 0)
        ? (float)$paymentFixedAmount
        : round($price * ($platformFeePercent / 100.0), 2);
 
    // Require proof of payment upload
    $proofPathWeb = '';
    if ($error === '') {
        if (!isset($_FILES['proof_of_payment']) || $_FILES['proof_of_payment']['error'] === UPLOAD_ERR_NO_FILE) {
            $error = 'Proof of payment is required.';
        } elseif ($_FILES['proof_of_payment']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Error uploading payment proof (code ' . (int)$_FILES['proof_of_payment']['error'] . ').';
        } else {
            $tmp = $_FILES['proof_of_payment']['tmp_name'];
            $name = basename($_FILES['proof_of_payment']['name']);
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp','pdf'];
            if (!in_array($ext, $allowed, true)) {
                $error = 'Payment proof must be an image or PDF.';
            } elseif (($_FILES['proof_of_payment']['size'] ?? 0) > 5 * 1024 * 1024) {
                $error = 'Payment proof is too large (max 5 MB).';
            } elseif (!is_uploaded_file($tmp)) {
                $error = 'Invalid payment proof upload.';
            } else {
                $payDirFs = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'payments';
                $payDirWeb = 'uploads/payments';
                if (!is_dir($payDirFs)) { @mkdir($payDirFs, 0775, true); }
                if (!is_dir($payDirFs) || !is_writable($payDirFs)) {
                    $error = 'Payment uploads directory is not writable.';
                } else {
                    $newName = 'proof_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    $destFs = $payDirFs . DIRECTORY_SEPARATOR . $newName;
                    $destWeb = $payDirWeb . '/' . $newName;
                    if (!@move_uploaded_file($tmp, $destFs)) {
                        $error = 'Failed to save payment proof.';
                    } else {
                        $proofPathWeb = $destWeb;
                    }
                }
            }
        }
    }
 
    if ($error === '') {
        // Detect optional item columns
        $itemCols = [];
        if ($colRes = @mysqli_query($connections, "SHOW COLUMNS FROM items")) {
            while ($r = mysqli_fetch_assoc($colRes)) { $itemCols[] = $r['Field']; }
            mysqli_free_result($colRes);
        }
        $hasAvailCount = in_array('available_items_count', $itemCols, true);
        $hasIsAvailable = in_array('is_available', $itemCols, true);
        $hasImageUrl = in_array('image_url', $itemCols, true);
        $hasStatus = in_array('status', $itemCols, true);
 
        // Optional tables
        $hasItemImages = false;
        if ($tRes = @mysqli_query($connections, "SHOW TABLES LIKE 'item_images'")) {
            $hasItemImages = mysqli_num_rows($tRes) > 0; @mysqli_free_result($tRes);
        }
        $hasItemCategories = false;
        if ($tRes = @mysqli_query($connections, "SHOW TABLES LIKE 'itemcategories'")) {
            $hasItemCategories = mysqli_num_rows($tRes) > 0; @mysqli_free_result($tRes);
        }
 
        // Detect a payments table (required to record payment)
        $paymentsTableName = '';
        foreach (['payments','transactions','item_payments'] as $cand) {
            $tRes = @mysqli_query($connections, "SHOW TABLES LIKE '" . mysqli_real_escape_string($connections, $cand) . "'");
            if ($tRes && mysqli_num_rows($tRes) > 0) { $paymentsTableName = $cand; @mysqli_free_result($tRes); break; }
            if ($tRes) { @mysqli_free_result($tRes); }
        }
        if ($paymentsTableName === '') {
            $error = 'Payment system is not available. Please contact support.';
        }
 
        // Handle item images upload
        $savedImages = [];
        if ($error === '' && !empty($_FILES['item_images']) && is_array($_FILES['item_images']['name'])) {
            $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'items';
            if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
            $names = $_FILES['item_images']['name'];
            $tmps  = $_FILES['item_images']['tmp_name'];
            $errs  = $_FILES['item_images']['error'];
            for ($i = 0; $i < count($names); $i++) {
                if (!isset($tmps[$i]) || (int)$errs[$i] !== UPLOAD_ERR_OK) continue;
                if (!is_uploaded_file($tmps[$i])) continue;
                $ext = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) continue;
                $newName = 'item_' . uniqid('', true) . '.' . $ext;
                $destFs = $uploadDir . DIRECTORY_SEPARATOR . $newName;
                $destWeb = 'uploads/items/' . $newName;
                if (@move_uploaded_file($tmps[$i], $destFs)) { $savedImages[] = $destWeb; }
                if (count($savedImages) >= 5) break;
            }
        }
        $mainImage = $savedImages[0] ?? '';
 
        if ($error === '') {
            @mysqli_begin_transaction($connections);
            $ok = true; $itemId = null;
 
            // Insert item (status pending)
            $cols = ['lender_id','title','description','price_per_day'];
            $vals = ['i','s','s','d'];
            $data = [$lenderId, $title, $description, $price];
            if ($hasAvailCount) { $cols[] = 'available_items_count'; $vals[] = 'i'; $data[] = $quantity; }
            if ($hasIsAvailable) { $cols[] = 'is_available'; $vals[] = 'i'; $data[] = 1; }
            if ($hasImageUrl) { $cols[] = 'image_url'; $vals[] = 's'; $data[] = $mainImage; }
            if ($hasStatus) { $cols[] = 'status'; $vals[] = 's'; $data[] = 'pending'; }
 
            $sql = 'INSERT INTO items (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
            $stmt = @mysqli_prepare($connections, $sql);
            if ($stmt) {
                $types = implode('', $vals); $bind = [$stmt, $types];
                foreach ($data as $k => $v) { $bind[] = &$data[$k]; }
                call_user_func_array('mysqli_stmt_bind_param', $bind);
                if (!@mysqli_stmt_execute($stmt)) { $ok = false; $error = 'Failed to save item. ' . mysqli_stmt_error($stmt); }
                @mysqli_stmt_close($stmt);
                if ($ok) { $itemId = mysqli_insert_id($connections); }
            } else { $ok = false; $error = 'Failed to prepare item insert.'; }
 
            // Insert extra images
            if ($ok && $hasItemImages && !empty($savedImages)) {
                $imgSql = 'INSERT INTO item_images (item_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)';
                $imgStmt = @mysqli_prepare($connections, $imgSql);
                if ($imgStmt) {
                    foreach ($savedImages as $idx => $imgPath) {
                        $isPrimary = ($idx === 0) ? 1 : 0; $sort = $idx;
                        @mysqli_stmt_bind_param($imgStmt, 'isii', $itemId, $imgPath, $isPrimary, $sort);
                        if (!@mysqli_stmt_execute($imgStmt)) { $ok = false; $error = 'Failed to save images. ' . mysqli_stmt_error($imgStmt); break; }
                    }
                    @mysqli_stmt_close($imgStmt);
                }
            }
 
            // Insert categories
            if ($ok && $hasItemCategories && $itemId && !empty($selectedCategories)) {
                $catSql = 'INSERT INTO itemcategories (item_id, category_id) VALUES (?, ?)';
                $catStmt = @mysqli_prepare($connections, $catSql);
                if ($catStmt) {
                    foreach ($selectedCategories as $cid) {
                        $cid = (int)$cid; if ($cid <= 0) continue;
                        @mysqli_stmt_bind_param($catStmt, 'ii', $itemId, $cid);
                        if (!@mysqli_stmt_execute($catStmt)) { $ok = false; $error = 'Failed to save categories. ' . mysqli_stmt_error($catStmt); break; }
                    }
                    @mysqli_stmt_close($catStmt);
                }
            }
 
            // Insert payment record (pending verification) with proof
            if ($ok && $itemId && $paymentsTableName !== '') {
                // Discover payments table columns
                $payCols = []; $payColsMap = [];
                if ($colRes = @mysqli_query($connections, "SHOW COLUMNS FROM `{$paymentsTableName}`")) {
                    while ($r = mysqli_fetch_assoc($colRes)) { $payCols[] = $r['Field']; $payColsMap[$r['Field']] = $r; }
                    mysqli_free_result($colRes);
                }
 
                // Build insert
                $insCols = []; $insPh = []; $insTypes = ''; $insVals = [];
                if (in_array('item_id', $payCols, true)) { $insCols[] = 'item_id'; $insPh[] = '?'; $insTypes .= 'i'; $insVals[] = $itemId; }
                if (in_array('user_id', $payCols, true)) { $insCols[] = 'user_id'; $insPh[] = '?'; $insTypes .= 'i'; $insVals[] = $lenderId; }
                if (!in_array('user_id', $insCols, true) && in_array('lender_id', $payCols, true)) { $insCols[] = 'lender_id'; $insPh[] = '?'; $insTypes .= 'i'; $insVals[] = $lenderId; }
 
                // Amount-like column
                if (in_array('amount', $payCols, true)) { $insCols[] = 'amount'; $insPh[] = '?'; $insTypes .= 'd'; $insVals[] = $feeAmount; }
                elseif (in_array('fee', $payCols, true)) { $insCols[] = 'fee'; $insPh[] = '?'; $insTypes .= 'd'; $insVals[] = $feeAmount; }
                elseif (in_array('total', $payCols, true)) { $insCols[] = 'total'; $insPh[] = '?'; $insTypes .= 'd'; $insVals[] = $feeAmount; }
 
                // Status-like column -> pending (awaiting verification)
                if (in_array('status', $payCols, true)) { $insCols[] = 'status'; $insPh[] = '?'; $insTypes .= 's'; $insVals[] = 'pending'; }
                elseif (in_array('payment_status', $payCols, true)) { $insCols[] = 'payment_status'; $insPh[] = '?'; $insTypes .= 's'; $insVals[] = 'pending'; }
 
                // Method/reference/proof columns if present
                if (in_array('payment_method', $payCols, true)) { $insCols[] = 'payment_method'; $insPh[] = '?'; $insTypes .= 's'; $insVals[] = 'qr'; }
                elseif (in_array('method', $payCols, true)) { $insCols[] = 'method'; $insPh[] = '?'; $insTypes .= 's'; $insVals[] = 'qr'; }
 
                if ($paymentRef !== '') {
                    if (in_array('reference', $payCols, true)) { $insCols[] = 'reference'; $insPh[] = '?'; $insTypes .= 's'; $insVals[] = $paymentRef; }
                    elseif (in_array('transaction_reference', $payCols, true)) { $insCols[] = 'transaction_reference'; $insPh[] = '?'; $insTypes .= 's'; $insVals[] = $paymentRef; }
                }
 
                if ($proofPathWeb !== '') {
                    if (in_array('proof_url', $payCols, true)) { $insCols[] = 'proof_url'; $insPh[] = '?'; $insTypes .= 's'; $insVals[] = $proofPathWeb; }
                    elseif (in_array('receipt_url', $payCols, true)) { $insCols[] = 'receipt_url'; $insPh[] = '?'; $insTypes .= 's'; $insVals[] = $proofPathWeb; }
                    elseif (in_array('attachment', $payCols, true)) { $insCols[] = 'attachment'; $insPh[] = '?'; $insTypes .= 's'; $insVals[] = $proofPathWeb; }
                }
 
                // Ensure we have at least an amount-like column
                $hasAmountLike = false; foreach (['amount','fee','total'] as $a) { if (in_array($a, $insCols, true)) { $hasAmountLike = true; break; } }
                if (!$hasAmountLike) { $ok = false; $error = 'Payment system is not configured correctly.'; }
 
                if ($ok) {
                    $paySql = 'INSERT INTO `' . $paymentsTableName . '` (' . implode(',', $insCols) . ') VALUES (' . implode(',', $insPh) . ')';
                    $payStmt = @mysqli_prepare($connections, $paySql);
                    if ($payStmt) {
                        $bind = [$payStmt, $insTypes]; foreach ($insVals as $k => $v) { $bind[] = &$insVals[$k]; }
                        call_user_func_array('mysqli_stmt_bind_param', $bind);
                        if (!@mysqli_stmt_execute($payStmt)) { $ok = false; $error = 'Failed to record payment. ' . mysqli_stmt_error($payStmt); }
                        @mysqli_stmt_close($payStmt);
                    } else { $ok = false; $error = 'Failed to prepare payment record.'; }
                }
            }
 
            if ($ok) {
                @mysqli_commit($connections);
                $success = 'Item and payment submitted. Your listing is pending admin approval.';
                $selectedCategories = [];
            } else {
                @mysqli_rollback($connections);
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
    <title>Add New Item - RenTayo</title>
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
 
        /* Layout: footer pinned below content, full-width */
        html, body { height: 100%; }
        body { display: flex; flex-direction: column; }
        .page-content { flex: 1 0 auto; }
        .site-footer-wrapper {
            flex: 0 0 auto;
            margin-top: 2rem;
            width: 100%;
            padding: 0;
            box-sizing: border-box;
            position: relative;
            z-index: 0;
            background: transparent;
        }
        /* Make included footer edge-to-edge */
        .site-footer-wrapper > * {
            max-width: none !important;
            width: 100% !important;
            margin: 0 !important;
            position: static !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }
        /* Extra breathing room below */
        body { padding-bottom: 0; }
 
        /* Footer layout: wrapper spans full width; included footer content is centered.
           Use non-intrusive positioning (no forced z-index) to avoid overlapping other UI. */
        .site-footer-wrapper {
            margin-top: 2rem;
            width: 100%;
            padding: 1rem 0;
            box-sizing: border-box;
            position: relative;
            z-index: 0; /* keep footer behind interactive overlays by default */
            background: transparent;
        }
        /* Constrain and center the inner footer content coming from footer.php */
        .site-footer-wrapper > * {
            max-width: 64rem;
            margin-left: auto;
            margin-right: auto;
            position: static !important;
            width: auto !important;
        }
        /* Give the page bottom breathing room so footer doesn't overlap fixed content */
        body { padding-bottom: 5rem; }
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
    <div class="page-content relative z-10 max-w-5xl mx-auto px-4 py-8">
       
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-sky-500 to-cyan-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i data-lucide="plus-circle" class="w-8 h-8 text-white"></i>
                </div>
                <div>
                    <h1 class="text-4xl font-bold text-gray-900">Add New Item</h1>
                    <p class="text-gray-600">List an item for rent on RenTayo</p>
                </div>
            </div>
            <a href="my_items.php" class="inline-flex items-center gap-2 px-4 py-2 bg-white hover:bg-sky-50 border-2 border-sky-200 rounded-xl text-sky-600 font-medium transition-all group">
                <i data-lucide="arrow-left" class="w-4 h-4 group-hover:-translate-x-1 transition-transform"></i>
                Back to My Items
            </a>
        </div>
 
        <!-- Form Card -->
        <form method="POST" enctype="multipart/form-data" class="bg-white rounded-3xl shadow-2xl shadow-sky-200/50 border-2 border-sky-100 overflow-hidden">
           
            <!-- Image Upload Section -->
            <div class="bg-gradient-to-br from-sky-50 to-cyan-50 p-8 border-b-2 border-sky-100">
                <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center gap-3">
                    <i data-lucide="image" class="w-6 h-6 text-sky-600"></i>
                    Item Images
                </h2>
               
                <div class="space-y-4">
                    <!-- Main Image Upload -->
                    <div class="relative">
                        <input type="file" id="itemImages" name="item_images[]" accept="image/*" multiple class="hidden">
                        <label for="itemImages" class="flex flex-col items-center justify-center h-64 bg-white border-3 border-dashed border-sky-300 rounded-2xl cursor-pointer hover:border-sky-500 hover:bg-sky-50 transition-all group">
                            <div class="text-center">
                                <div class="w-20 h-20 bg-gradient-to-br from-sky-100 to-cyan-100 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform">
                                    <i data-lucide="upload" class="w-10 h-10 text-sky-600"></i>
                                </div>
                                <p class="text-lg font-semibold text-gray-900 mb-2">Click to upload images</p>
                                <p class="text-sm text-gray-600">PNG, JPG up to 5MB (Max 5 images)</p>
                            </div>
                        </label>
                    </div>
 
                    <!-- Image Preview Grid -->
                    <div id="imagePreview" class="hidden grid grid-cols-2 md:grid-cols-5 gap-4">
                        <!-- Preview items will be inserted here by JavaScript -->
                    </div>
                </div>
            </div>
 
            <!-- Form Fields -->
            <div class="p-8 space-y-6">
               
                <!-- Item Title -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Item Title <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="title"
                        required
                        placeholder="e.g., MacBook Pro 13-inch"
                        class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-sky-500 focus:bg-white transition-all"
                    >
                </div>
 
                <!-- Description -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Description <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        name="description"
                        required
                        rows="4"
                        placeholder="Describe your item, its condition, and any special features..."
                        class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-sky-500 focus:bg-white transition-all resize-none"
                    ></textarea>
                </div>
 
                <!-- Price and Quantity Row -->
                <div class="grid md:grid-cols-2 gap-6">
                    <!-- Price -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Price per Day <span class="text-red-500">*</span>
                        </label>
                        <div class="flex items-center gap-2">
                            <span class="px-4 py-3 bg-gradient-to-br from-sky-50 to-cyan-50 border-2 border-sky-200 rounded-xl text-sky-600 font-bold">₱</span>
                            <input
                                type="number"
                                name="price_per_day"
                                required
                                min="1"
                                step="0.01"
                                placeholder="50.00"
                                class="flex-1 px-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-sky-500 focus:bg-white transition-all"
                            >
                        </div>
                    </div>
 
                    <!-- Quantity -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Quantity Available <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            name="quantity"
                            required
                            min="1"
                            value="1"
                            class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-sky-500 focus:bg-white transition-all"
                        >
                    </div>
                </div>
 
                <!-- Categories -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-3">
                        Categories <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $cat): ?>
                                <?php
                                    $cid = (int)$cat['category_id'];
                                    $checked = in_array($cid, $selectedCategories, true) ? 'checked' : '';
                                ?>
                                <label class="flex items-center gap-3 p-4 bg-gray-50 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-sky-500 hover:bg-sky-50 transition-all group">
                                    <input type="checkbox" name="categories[]" value="<?php echo $cid; ?>" class="w-5 h-5 text-sky-600 rounded focus:ring-sky-500" <?php echo $checked; ?>>
                                    <span class="font-medium text-gray-700 group-hover:text-sky-600"><?php echo htmlspecialchars($cat['name']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-span-full p-4 bg-amber-50 border-2 border-amber-200 rounded-xl text-amber-800">
                                No categories available. Please import data or add categories in the database.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
 
                <!-- Payment Details (fetched from payment_settings) + Proof Upload -->
                <div class="p-4 rounded-xl border-2 border-sky-200 bg-sky-50">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Payment Details</h3>
                    <p class="text-gray-700 mb-2">
                        <?php if ($paymentFixedAmount !== null && $paymentFixedAmount > 0): ?>
                            Listing fee: ₱<?php echo number_format($paymentFixedAmount, 2); ?> (fixed)
                        <?php else: ?>
                            Listing fee: <?php echo number_format($platformFeePercent, 2); ?>% of Price per Day
                        <?php endif; ?>
                    </p>
                    <p class="text-gray-800 font-semibold">
                        Amount to pay: ₱<span id="amountDueText">
                            <?php
                                // Initial render based on current POST (or zero)
                                $priceForCalc = isset($_POST['price_per_day']) ? (float)$_POST['price_per_day'] : 0;
                                $initialFee = ($paymentFixedAmount !== null && $paymentFixedAmount > 0)
                                    ? (float)$paymentFixedAmount
                                    : round($priceForCalc * ($platformFeePercent / 100.0), 2);
                                echo number_format(max(0, $initialFee), 2);
                            ?>
                        </span>
                    </p>
                    <?php if (!empty($paymentQrUrl)): ?>
                        <div class="mt-4">
                            <img src="<?php echo htmlspecialchars($paymentQrUrl); ?>" alt="Payment QR" class="w-56 h-56 object-contain border-2 border-gray-200 rounded-xl bg-white">
                        </div>
                    <?php else: ?>
                        <p class="mt-2 text-sm text-gray-500">QR code is not configured. Please contact support.</p>
                    <?php endif; ?>
 
                    <div class="mt-4 grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Proof of Payment (image/PDF) <span class="text-red-500">*</span></label>
                            <input type="file" name="proof_of_payment" accept="image/*,application/pdf" required class="w-full px-4 py-2 bg-white border-2 border-gray-200 rounded-xl">
                        </div>
                        <div>
                            <!-- CHANGED: make reference required and update label -->
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Reference Number <span class="text-red-500">*</span></label>
                            <input type="text" name="payment_reference" required placeholder="Transaction reference number" class="w-full px-4 py-2 bg-white border-2 border-gray-200 rounded-xl">
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">Submit your payment screenshot/receipt and reference. Admin will verify it before approving the listing.</p>
                </div>
 
                <!-- Error/Success Messages -->
                <?php if (!empty($error)): ?>
                <div class="p-4 bg-red-50 border-2 border-red-200 rounded-xl flex items-center gap-3">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-red-600"></i>
                    <p class="text-red-700 font-medium"><?php echo htmlspecialchars($error); ?></p>
                </div>
                <?php endif; ?>
 
                <?php if (!empty($success)): ?>
                <div class="p-4 bg-emerald-50 border-2 border-emerald-200 rounded-xl flex items-center gap-3">
                    <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600"></i>
                    <p class="text-emerald-700 font-medium"><?php echo htmlspecialchars($success); ?></p>
                </div>
                <?php endif; ?>
            </div>
 
            <!-- Action Buttons -->
            <div class="bg-gradient-to-br from-sky-50 to-cyan-50 p-6 border-t-2 border-sky-100">
                <div class="flex flex-col sm:flex-row gap-4 justify-end">
                    <a href="my_items.php" class="px-6 py-3 bg-white hover:bg-gray-50 text-gray-700 font-semibold rounded-xl border-2 border-gray-200 transition-all text-center">
                        Cancel
                    </a>
                    <!-- No change to button; JS will intercept to pop out modal -->
                    <button type="submit" class="px-8 py-3 bg-gradient-to-r from-sky-500 to-cyan-600 hover:from-sky-600 hover:to-cyan-700 text-white font-bold rounded-xl shadow-lg shadow-sky-500/30 transition-all hover:scale-105 flex items-center justify-center gap-2">
                        <i data-lucide="check" class="w-5 h-5"></i>
                        Add Item
                    </button>
                </div>
            </div>
        </form>
 
        <!-- NEW: Payment confirmation modal (pops out on Add Item) -->
        <div id="paymentModal" class="fixed inset-0 hidden items-center justify-center bg-black/50 z-50">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 border-2 border-sky-100">
                <h3 class="text-xl font-bold text-gray-900 mb-2">Confirm Payment & Submit</h3>
                <p class="text-gray-700 mb-3">Please confirm the listing fee and payment details before submitting.</p>
                <p class="text-gray-900 font-semibold mb-2">
                    Amount due: ₱<span id="modalAmount">0.00</span>
                </p>
                <?php if (!empty($paymentQrUrl)): ?>
                    <div class="flex items-center justify-center mb-4">
                        <img src="<?php echo htmlspecialchars($paymentQrUrl); ?>" alt="Payment QR" class="w-56 h-56 object-contain border-2 border-gray-200 rounded-xl bg-white">
                    </div>
                <?php else: ?>
                    <div class="mb-4 p-3 rounded bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                        QR code is not configured. Please contact support.
                    </div>
                <?php endif; ?>
                <div class="flex gap-3 justify-end mt-4">
                    <button type="button" id="cancelModal" class="px-4 py-2 bg-white border-2 border-gray-200 rounded-lg text-gray-700 font-medium hover:bg-gray-50">Cancel</button>
                    <button type="button" id="proceedSubmit" class="px-4 py-2 bg-gradient-to-r from-sky-500 to-cyan-600 text-white font-semibold rounded-lg hover:from-sky-600 hover:to-cyan-700">Submit Now</button>
                </div>
            </div>
        </div>
    </div>
 
    <!-- FOOTER PLACEHOLDER -->
    <div class="site-footer-wrapper">
        <?php include 'footer.php'; ?>
    </div>
 
    <script>
        lucide.createIcons();
 
        // Live update of "Amount to pay"
        (function () {
            const priceInput = document.querySelector('input[name="price_per_day"]');
            const amountEl = document.getElementById('amountDueText');
            const fixedAmount = <?php echo ($paymentFixedAmount !== null && $paymentFixedAmount > 0) ? json_encode((float)$paymentFixedAmount) : 'null'; ?>;
            const feePercent = <?php echo json_encode((float)$platformFeePercent); ?>;
            function updateAmount() {
                let amount = 0;
                if (fixedAmount !== null) {
                    amount = Number(fixedAmount);
                } else {
                    const p = parseFloat(priceInput?.value || '0') || 0;
                    amount = (p * feePercent) / 100;
                }
                if (amountEl) amountEl.textContent = amount.toFixed(2);
            }
            if (priceInput) { priceInput.addEventListener('input', updateAmount); updateAmount(); }
        })();
 
        // Pop-out modal before final submission
        (function () {
            const form = document.querySelector('form[method="POST"]');
            const modal = document.getElementById('paymentModal');
            const proceed = document.getElementById('proceedSubmit');
            const cancelBtn = document.getElementById('cancelModal');
            const modalAmount = document.getElementById('modalAmount');
            const amountText = document.getElementById('amountDueText');
            let confirmShown = false;
 
            function openModal() {
                if (modalAmount && amountText) {
                    modalAmount.textContent = amountText.textContent || '0.00';
                }
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
            function closeModal() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
 
            if (form && modal && proceed && cancelBtn) {
                form.addEventListener('submit', function (e) {
                    // Only show the modal the first time; after confirm, allow submit
                    if (!confirmShown) {
                        e.preventDefault();
                        openModal();
                    }
                });
 
                proceed.addEventListener('click', function () {
                    confirmShown = true;
                    closeModal();
                    // Trigger the actual submit after confirmation
                    form.submit();
                });
 
                cancelBtn.addEventListener('click', function () {
                    confirmShown = false;
                    closeModal();
                });
            }
        })();
 
        // Image Preview Handler
        document.getElementById('itemImages').addEventListener('change', function(e) {
            const previewContainer = document.getElementById('imagePreview');
            previewContainer.innerHTML = '';
           
            if (this.files.length > 0) {
                previewContainer.classList.remove('hidden');
               
                Array.from(this.files).forEach((file, index) => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const div = document.createElement('div');
                        div.className = 'relative group';
                        div.innerHTML = `
                            <img src="${e.target.result}" class="w-full h-32 object-cover rounded-xl border-2 border-sky-200">
                            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity rounded-xl flex items-center justify-center">
                                <span class="text-white text-sm font-semibold">${index === 0 ? 'Main Image' : `Image ${index + 1}`}</span>
                            </div>
                        `;
                        previewContainer.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                });
            } else {
                previewContainer.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
 