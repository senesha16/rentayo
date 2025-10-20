<?php
include("connections.php");
session_start();

// Check if user is logged in
if (!isset($_SESSION["ID"])) {
    header("Location: login.php");
    exit;
}

// Get item_id from URL
$item_id = $_GET['item_id'] ?? null;

if (!$item_id) {
    header("Location: index.php");
    exit;
}

// Fetch item details (use lowercase tables; Linux is case-sensitive)
$query = "SELECT i.*, COALESCE(GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', '), '') AS categories, u.username AS lender_username 
          FROM items i 
          LEFT JOIN itemcategories ic ON ic.item_id = i.item_id 
          LEFT JOIN categories c ON c.category_id = ic.category_id 
          LEFT JOIN users u ON u.ID = i.lender_id 
          WHERE i.item_id = " . intval($item_id) . " 
          GROUP BY i.item_id";

$result = mysqli_query($connections, $query);

if (!$result) {
    error_log('item_details.php: Item query failed: ' . mysqli_error($connections) . ' | SQL: ' . $query);
}
if (!$result || mysqli_num_rows($result) == 0) {
    header("Location: index.php");
    exit;
}

$item = mysqli_fetch_assoc($result);

// Fetch all images for this item
$images = [];
$imageTableExists = false;

// Check if item_images table exists
$imageTableCheck = mysqli_query($connections, "SHOW TABLES LIKE 'item_images'");
if ($imageTableCheck && mysqli_num_rows($imageTableCheck) > 0) {
    $imageTableExists = true;
    $imageQuery = "SELECT image_url, is_primary, sort_order 
                   FROM item_images 
                   WHERE item_id = " . intval($item_id) . " 
                   ORDER BY is_primary DESC, sort_order ASC";
    $imageResult = mysqli_query($connections, $imageQuery);
    
    if ($imageResult && mysqli_num_rows($imageResult) > 0) {
        while ($img = mysqli_fetch_assoc($imageResult)) {
            // Push web path directly; browser will handle if missing
            $images[] = $img['image_url'];
        }
    }
}

// Always add main image_url as fallback/primary if no images found in item_images table
// or if the main image isn't already in the images array
if (!empty($item['image_url'])) {
    if (empty($images) || !in_array($item['image_url'], $images)) {
        array_unshift($images, $item['image_url']);
    }
}

// Fetch other items from the same lender (excluding current item)
$otherItemsQuery = "SELECT i.*, COALESCE(GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', '), '') AS categories 
                    FROM items i 
                    LEFT JOIN itemcategories ic ON ic.item_id = i.item_id 
                    LEFT JOIN categories c ON c.category_id = ic.category_id 
                    WHERE i.lender_id = " . intval($item['lender_id']) . " 
                    AND i.item_id != " . intval($item_id) . " 
                    GROUP BY i.item_id 
                    ORDER BY i.created_at DESC 
                    LIMIT 6";

$otherItemsResult = mysqli_query($connections, $otherItemsQuery);
$otherItems = [];
if ($otherItemsResult && mysqli_num_rows($otherItemsResult) > 0) {
    while ($otherItem = mysqli_fetch_assoc($otherItemsResult)) {
        $otherItems[] = $otherItem;
    }
}

// Add: load payment settings (GCash) for the modal
if (!function_exists('getPaymentSettings')) {
    function getPaymentSettings(mysqli $connections): array {
        $create = "CREATE TABLE IF NOT EXISTS `payment_settings` (
            `id` TINYINT(1) NOT NULL PRIMARY KEY,
            `gcash_number` VARCHAR(30) NOT NULL DEFAULT '',
            `gcash_qr_url` VARCHAR(255) NOT NULL DEFAULT '',
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        mysqli_query($connections, $create);

        $row = null;
        $res = mysqli_query($connections, "SELECT gcash_number, gcash_qr_url FROM payment_settings WHERE id=1");
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
        } else {
            mysqli_query($connections, "INSERT INTO payment_settings (id, gcash_number, gcash_qr_url) VALUES (1, '09123456789', '')");
            $row = ['gcash_number' => '09123456789', 'gcash_qr_url' => ''];
        }
        return $row ?: ['gcash_number' => '09123456789', 'gcash_qr_url' => ''];
    }
}
$__pay = getPaymentSettings($connections);
$__gcash_number = $__pay['gcash_number'] ?? '09123456789';
$__gcash_qr_url = $__pay['gcash_qr_url'] ?? '';

// Add: per-user payment settings for the lender (NOT admin/global)
function getUserPaymentSettings(mysqli $connections, int $userId): array {
    mysqli_query($connections, "CREATE TABLE IF NOT EXISTS `user_payment_settings` (
        `user_id` INT NOT NULL PRIMARY KEY,
        `gcash_number` VARCHAR(30) NOT NULL DEFAULT '',
        `gcash_qr_url` VARCHAR(255) NOT NULL DEFAULT '',
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $res = mysqli_query($connections, "SELECT gcash_number, gcash_qr_url FROM user_payment_settings WHERE user_id = $userId LIMIT 1");
    return $res && mysqli_num_rows($res) ? mysqli_fetch_assoc($res) : ['gcash_number'=>'', 'gcash_qr_url'=>''];
}

$lenderPay = getUserPaymentSettings($connections, (int)$item['lender_id']);
$lender_gcash_number = trim($lenderPay['gcash_number'] ?? '');
$lender_gcash_qr_url = trim($lenderPay['gcash_qr_url'] ?? '');
$gcash_available = $lender_gcash_number !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($item['title']); ?> - RenTayo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/item.css">
    <style>
    /* Fixed Action Buttons Styling */
    .item-actions {
        margin-top: 24px;
        padding-top: 20px;
        border-top: 2px solid #e5e7eb;
    }

    .actions-wrapper {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        width: 100%;
    }

    .action-btn {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 14px 20px;
        border: none;
        border-radius: 12px;
        font-family: 'Poppins', sans-serif;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        width: 100%;           /* fill grid cell width */
        min-height: 52px;      /* consistent button height */
    }

    .action-btn::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        transform: translate(-50%, -50%);
        transition: width 0.6s ease, height 0.6s ease;
    }

    .action-btn:hover::before {
        width: 300px;
        height: 300px;
    }

    .action-btn:active {
        transform: scale(0.96);
    }

    .btn-icon {
        font-size: 20px;
        transition: transform 0.3s ease;
        position: relative;
        z-index: 1;
    }

    .action-btn:hover .btn-icon {
        transform: scale(1.15);
    }

    .action-btn span:not(.btn-icon) {
        position: relative;
        z-index: 1;
    }

    /* Rent Button - Primary Blue Gradient */
    .rent-btn {
        background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%);
        color: white;
        border: 2px solid transparent;
    }

    .rent-btn:hover {
        background: linear-gradient(135deg, #0891b2 0%, #2563eb 100%);
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        transform: translateY(-2px);
    }

    .rent-btn:focus {
        outline: none;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.3);
    }

    /* Message Button - Secondary Gradient */
    .message-btn {
        background: linear-gradient(135deg, #eef2ff 0%, #ddd6fe 100%); /* lighter for black text */
        color: #111; /* black text for readability */
        border: 2px solid #c7d2fe;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); /* match other buttons */
    }

    .message-btn:hover {
        background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
        color: #111; /* keep text black on hover */
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.16); /* same hover depth as others */
        transform: translateY(-2px);
    }

    .message-btn:focus {
        outline: none;
        color: #111;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.25), 0 8px 20px rgba(0, 0, 0, 0.12);
    }

    /* force label visibility */
    .message-btn .btn-label {
        color: #111 !important;
        opacity: 1 !important;
        visibility: visible !important;
        display: inline-block;
        font-weight: 600; /* match other buttons */
    }

    /* Report Button - Warning Red */
    .report-btn {
        background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
        color: #991b1b;
        border: 2px solid #fca5a5;
    }

    .report-btn:hover {
        background: linear-gradient(135deg, #fca5a5 0%, #f87171 100%);
        box-shadow: 0 6px 20px rgba(239, 68, 68, 0.3);
        transform: translateY(-2px);
        color: #7f1d1d;
    }

    .report-btn:focus {
        outline: none;
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.2);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .actions-wrapper {
            grid-template-columns: 1fr;
        }
        
        .action-btn {
            padding: 16px 24px;
            font-size: 16px;
        }
    }

    /* Loading State */
    .action-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .action-btn:disabled:hover {
        transform: none;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    /* Report popup modal styles */
    .modal-overlay {
      position: fixed; inset: 0; background: rgba(0,0,0,.45);
      display: grid; place-items: center; z-index: 10000;
    }
    .modal-overlay[hidden] { display: none; }
    .modal-window {
      width: min(560px, 92vw); background: #fff; border-radius: 12px;
      box-shadow: 0 20px 50px rgba(0,0,0,.25); overflow: hidden;
      animation: modalPop .18s ease-out;
    }
    @keyframes modalPop { from { transform: translateY(8px); opacity: 0; } to { transform:none; opacity: 1; } }
    .modal-header {
      display:flex; align-items:center; justify-content:space-between;
      padding: 14px 16px; background:#f8fafc; border-bottom:1px solid #eef2f7;
    }
    .modal-close {
      background: transparent; border: 0; font-size: 22px; line-height: 1; cursor: pointer; color:#64748b;
    }
    .modal-body { padding: 16px; display: grid; gap: 10px; }
    .field-label { font-weight: 600; color:#334155; font-size: 14px; }
    .modal-body select, .modal-body textarea, .modal-body input[type="number"], .modal-body input[type="text"] {
      width: 100%; border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px;
      font-family: inherit; font-size: 14px;
    }
    .modal-actions { display:flex; justify-content:flex-end; gap: 10px; margin-top: 4px; }
    .btn-primary {
      background:#ef4444; color:#fff; border:0; padding:10px 14px; border-radius:10px; cursor:pointer;
    }
    .btn-primary:disabled { opacity:.6; cursor:not-allowed; }
    .btn-secondary {
      background:#e2e8f0; color:#0f172a; border:0; padding:10px 14px; border-radius:10px; cursor:pointer;
    }
    body.modal-open { overflow: hidden; }

    .row-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .radio-group{display:flex;gap:14px;align-items:center;margin-top:6px}
    .gcash-block{background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:12px}
    .gcash-grid{display:grid;grid-template-columns:140px 1fr;gap:12px}
    .gcash-number{font-weight:700;font-size:18px;margin:4px 0}
    .qr img{max-width:140px;border:1px solid #e5e7eb;border-radius:8px}
    .qr-placeholder{width:140px;height:140px;display:grid;place-items:center;border:1px dashed #cbd5e1;border-radius:8px;color:#64748b}
    .delivery-block{background:#fff7ed;border:1px solid #fde68a;border-radius:10px;padding:12px}
    .totals{display:flex;justify-content:space-between;align-items:center;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:10px;padding:10px;margin-top:6px}
    .totals .total{font-weight:700}
    </style>
</head>
<body>
    <?php include("nav_switch.php"); ?>

    <!-- Content -->
    <div class="content item-detail-simple">
        <div class="item-detail-card">
            <?php if (isset($_SESSION["ID"]) && $_SESSION["ID"] == $item['lender_id']): ?>
                <div class="item-owner-actions">
                    <a href="edit_item.php?item_id=<?php echo $item['item_id']; ?>" class="edit-btn" title="Edit Item">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                        </svg>
                    </a>
                </div>
            <?php endif; ?>
            <!-- Two-column layout: IMAGE left, all details on right -->
            <div class="item-header">
                <div class="item-image-section">
                    <?php if (!empty($images)): ?>
                        <div class="image-gallery">
                            <!-- Main Image Display -->
                            <div class="gallery-main">
                                <img id="mainImage" 
                                     src="<?php echo htmlspecialchars($images[0]); ?>" 
                                     alt="<?php echo htmlspecialchars($item['title']); ?>" 
                                     class="item-image"
                                     onclick="openImageModal(0)">
                                
                                <!-- Navigation arrows (only show if multiple images) -->
                                <?php if (count($images) > 1): ?>
                                    <button class="gallery-nav prev" onclick="changeImage(-1)">
                                        <svg viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                                        </svg>
                                    </button>
                                    <button class="gallery-nav next" onclick="changeImage(1)">
                                        <svg viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                                        </svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Thumbnail Gallery (only show if multiple images) -->
                            <?php if (count($images) > 1): ?>
                                <div class="gallery-thumbnails">
                                    <?php foreach ($images as $index => $image_url): ?>
                                        <div class="thumbnail-item <?php echo $index === 0 ? 'active' : ''; ?>" 
                                             onclick="selectImage(<?php echo $index; ?>)">
                                            <img src="<?php echo htmlspecialchars($image_url); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['title']); ?> - Image <?php echo $index + 1; ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="item-image-placeholder">
                            <span>📷</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="item-content-right">
                    <div class="item-title-section">
                        <h1 class="item-name"><?php echo htmlspecialchars($item['title']); ?></h1>
                    </div>

                    <!-- Price Row -->
                    <div class="item-price-row">
                        <div class="price-label">Price</div>
                        <div class="price-value">
                            <span class="currency">₱</span>
                            <span class="amount"><?php echo number_format($item['price_per_day'], 2); ?></span>
                            <span class="period">/day</span>
                        </div>
                    </div>

                    <!-- Category and Availability Row -->
                    <div class="item-meta-row">
                        <div class="meta-group">
                            <div class="meta-label">Category</div>
                            <div class="meta-value categories">
                                <?php 
                                $categories = $item['categories'] ?: "Uncategorized";
                                echo htmlspecialchars($categories);
                                ?>
                            </div>
                        </div>
                        
                        <div class="meta-group">
                            <div class="meta-label">Items Available</div>
                            <div class="meta-value availability">
                                    <?php 
                                    if (isset($item['available_items_count'])) {
                                        $cnt = intval($item['available_items_count']);
                                        echo '<span class="availability-badge"><span class="count">' . $cnt . '</span>' . ($cnt > 0 ? ' Available' : ' Unavailable') . ($cnt > 0 ? ' <span class="available-icon">✅</span>' : ' <span class="unavailable-icon">❌</span>') . '</span>';
                                    } else {
                                        echo $item['is_available'] ? 'Available <span class="available-icon">✅</span>' : 'Not Available <span class="unavailable-icon">❌</span>';
                                    }
                                    ?>
                                </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="item-description-section">
                        <div class="description-label">Description</div>
                        <div class="description-content">
                            <?php echo nl2br(htmlspecialchars($item['description'])); ?>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="section-divider"></div>

                    <!-- Lender Info Row -->
                    <div class="item-info-row">
                        <div class="info-group">
                            <div class="info-label">Lender Name:</div>
                            <div class="info-value">
                                <?php if ((int)$item['lender_id'] === (int)$_SESSION['ID']): ?>
                                    You
                                <?php else: ?>
                                    <a href="profile.php?user_id=<?php echo (int)$item['lender_id']; ?>" class="profile-link">
                                        <?php echo htmlspecialchars($item['lender_username'] ?: "Unknown"); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="info-group">
                            <div class="info-label">Posted Date:</div>
                            <div class="info-value"><?php echo date('F j, Y', strtotime($item['created_at'])); ?></div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="item-actions">
                        <div class="actions-wrapper">
                            <button type="button" id="openRentBtn" class="action-btn rent-btn">
                                <span class="btn-icon">🛒</span>
                                <span>Rent Now</span>
                            </button>
                            <button type="button" onclick="startChat()" class="action-btn message-btn">
                                <span class="btn-icon">💬</span>
                                <span class="btn-label">Message</span>
                            </button>
                            <button type="button" class="action-btn report-btn" id="openReportBtn">
                                <span class="btn-icon">⚠️</span>
                                <span>Report</span>
                            </button>
                        </div>
                    </div>

                    
                </div>
            </div>
        </div>
    </div>

    <!-- Other Items from Same Lender Section -->
    <?php if (!empty($otherItems)): ?>
    <div class="other-items-section">
        <div class="other-items-container">
            <h2 class="other-items-title">
                More items from <?php echo htmlspecialchars($item['lender_username']); ?>
                <span class="items-count">(<?php echo count($otherItems); ?> items)</span>
            </h2>
            
            <div class="other-items-grid">
                <?php foreach ($otherItems as $otherItem): ?>
                    <div class="other-item-card" onclick="window.location.href='item_details.php?item_id=<?php echo $otherItem['item_id']; ?>'">
                        <div class="other-item-image">
                            <?php if (!empty($otherItem['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($otherItem['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($otherItem['title']); ?>" 
                                     loading="lazy">
                            <?php else: ?>
                                <div class="other-item-placeholder">
                                    <span>📷</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="other-item-info">
                            <h3 class="other-item-title"><?php echo htmlspecialchars($otherItem['title']); ?></h3>
                            
                            <div class="other-item-price">
                                <span class="currency">₱</span>
                                <span class="amount"><?php echo number_format($otherItem['price_per_day'], 2); ?></span>
                                <span class="period">/day</span>
                            </div>
                            
                            <?php if (!empty($otherItem['categories'])): ?>
                                <div class="other-item-category">
                                    <?php echo htmlspecialchars($otherItem['categories']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="other-item-overlay">
                            <span class="view-details">View Details →</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($otherItems) >= 6): ?>
                <div class="view-all-items">
                    <a href="index.php?lender_id=<?php echo $item['lender_id']; ?>" class="view-all-btn">
                        View All Items from <?php echo htmlspecialchars($item['lender_username']); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Message Modal -->
    <div id="messageModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Send Message to <?php echo htmlspecialchars($item['lender_username']); ?></h3>
                <span class="close" onclick="closeMessageModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="messageForm">
                    <input type="hidden" name="receiver_id" value="<?php echo $item['lender_id']; ?>">
                    <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                    <div class="form-group">
                        <label>Message about: <?php echo htmlspecialchars($item['title']); ?></label>
                        <textarea name="message_text" placeholder="Type your message here..." required></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="button" onclick="closeMessageModal()" class="btn-cancel">Cancel</button>
                        <button type="submit" class="btn-send">Send Message</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Report Modal -->
    <div id="reportOverlay" class="modal-overlay" hidden>
      <div class="modal-window" role="dialog" aria-modal="true" aria-labelledby="reportTitle">
        <div class="modal-header">
          <h3 id="reportTitle">Report Listing</h3>
          <button class="modal-close" id="closeReportBtn" aria-label="Close">×</button>
        </div>
        <form id="reportForm" class="modal-body">
          <input type="hidden" name="item_id" value="<?php echo (int)$item['item_id']; ?>">
          <input type="hidden" name="reported_user_id" value="<?php echo (int)$item['lender_id']; ?>">

          <label class="field-label" for="reason">Reason</label>
          <select name="reason" id="reason" required>
            <option value="">Select a reason</option>
            <option>Scam/Fraud</option>
            <option>Fake Listing</option>
            <option>Prohibited Item</option>
            <option>Harassment</option>
            <option>Other</option>
          </select>

          <label class="field-label" for="details">Details (optional)</label>
          <textarea name="details" id="details" placeholder="Provide any details or evidence..." rows="5"></textarea>

          <div class="modal-actions">
            <button type="button" class="btn-secondary" id="cancelReportBtn">Cancel</button>
            <button type="submit" class="btn-primary" id="submitReportBtn">Submit Report</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Rent modal -->
    <div id="rentOverlay" class="modal-overlay" hidden>
      <div class="modal-window" role="dialog" aria-modal="true" aria-labelledby="rentTitle">
        <div class="modal-header">
          <h3 id="rentTitle">Rent This Item</h3>
          <button class="modal-close" id="closeRentBtn" aria-label="Close">×</button>
        </div>

        <form id="rentForm" class="modal-body">
          <input type="hidden" name="item_id" value="<?php echo (int)$item['item_id']; ?>">
          <input type="hidden" name="lender_id" value="<?php echo (int)$item['lender_id']; ?>">
          <input type="hidden" id="pricePerDay" value="<?php echo (float)$item['price_per_day']; ?>">

          <label class="field-label" for="days">Days to rent</label>
          <input type="number" id="days" name="days" min="1" max="60" value="1" required>

          <div class="row-2">
            <div>
              <label class="field-label">Payment method</label>
              <div class="radio-group">
                <label><input type="radio" name="payment_method" value="cash" <?php echo $gcash_available ? '' : 'checked'; ?>> Cash</label>
                <label title="<?php echo $gcash_available ? '' : 'Lender has not set a GCash number'; ?>">
                  <input type="radio" name="payment_method" value="gcash" <?php echo $gcash_available ? 'checked' : 'disabled'; ?>> GCash
                </label>
              </div>
              <?php if (!$gcash_available): ?>
                <small style="color:#64748b">GCash is unavailable for this lender.</small>
              <?php endif; ?>
            </div>
            <div>
              <label class="field-label">Delivery method</label>
              <div class="radio-group">
                <label><input type="radio" name="delivery_method" value="pickup" checked> Pickup</label>
                <label><input type="radio" name="delivery_method" value="delivery"> Delivery</label>
              </div>
            </div>
          </div>

          <!-- GCash details: show lender's number/QR -->
          <div id="gcashBlock" class="gcash-block" <?php echo $gcash_available ? '' : 'hidden'; ?>>
            <div class="gcash-grid">
              <div class="qr">
                <?php if ($lender_gcash_qr_url !== ''): ?>
                  <img src="<?php echo htmlspecialchars($lender_gcash_qr_url); ?>" alt="Lender GCash QR" onerror="this.style.display='none'">
                <?php else: ?>
                  <div class="qr-placeholder">QR not set</div>
                <?php endif; ?>
                <small>If QR doesn't show, use the number.</small>
              </div>
              <div class="gcash-info">
                <div class="gcash-label">Send to GCash number:</div>
                <div class="gcash-number"><?php echo htmlspecialchars($lender_gcash_number ?: 'Not set'); ?></div>
                <label class="field-label" for="gcash_ref">GCash Reference No. (required)</label>
                <input type="text" id="gcash_ref" name="gcash_ref" placeholder="Enter GCash reference"
                       minlength="6" maxlength="20" pattern="[A-Za-z0-9-]{6,20}" inputmode="text">
              </div>
            </div>
          </div>

          <!-- Delivery details -->
          <div id="deliveryBlock" class="delivery-block" hidden>
            <label class="field-label" for="delivery_address">Delivery address (required for delivery)</label>
            <textarea id="delivery_address" name="delivery_address" placeholder="House/Street/Barangay, City, Province" rows="3"></textarea>
          </div>

          <div class="totals">
            <div>Price per day: ₱<span id="ppd"><?php echo number_format($item['price_per_day'], 2); ?></span></div>
            <div class="total">Total: ₱<span id="totalAmount"><?php echo number_format($item['price_per_day'], 2); ?></span></div>
          </div>

          <div class="modal-actions">
            <button type="button" class="btn-secondary" id="cancelRentBtn">Cancel</button>
            <button type="submit" class="btn-primary" id="submitRentBtn">Confirm Rent</button>
          </div>
        </form>
      </div>
    </div>

    <script>
        // Image gallery functionality
        const images = <?php echo json_encode($images); ?>;
        let currentImageIndex = 0;

        // Only initialize gallery functions if we have images
        if (images && images.length > 0) {

        function selectImage(index) {
            currentImageIndex = index;
            const mainImage = document.getElementById('mainImage');
            mainImage.src = images[index];
            
            // Update thumbnail active state
            document.querySelectorAll('.thumbnail-item').forEach((thumb, i) => {
                thumb.classList.toggle('active', i === index);
            });
        }

        function changeImage(direction) {
            const newIndex = (currentImageIndex + direction + images.length) % images.length;
            selectImage(newIndex);
        }

        function openImageModal(index = null) {
            if (index !== null) {
                currentImageIndex = index;
            }
            
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            
            modal.style.display = 'flex';
            modalImage.src = images[currentImageIndex];
            
            if (images.length > 1) {
                document.getElementById('currentImageNum').textContent = currentImageIndex + 1;
            }
            
            // Prevent body scroll
            document.body.style.overflow = 'hidden';
            
            // Update thumbnail active state to match modal
            document.querySelectorAll('.thumbnail-item').forEach((thumb, i) => {
                thumb.classList.toggle('active', i === currentImageIndex);
            });
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function changeModalImage(direction) {
            const newIndex = (currentImageIndex + direction + images.length) % images.length;
            currentImageIndex = newIndex;
            
            const modalImage = document.getElementById('modalImage');
            
            // Smooth transition effect
            modalImage.style.opacity = '0.3';
            
            setTimeout(() => {
                modalImage.src = images[currentImageIndex];
                modalImage.style.opacity = '1';
                
                if (images.length > 1) {
                    document.getElementById('currentImageNum').textContent = currentImageIndex + 1;
                }
                
                // Also update the main gallery image and thumbnail active state
                selectImage(currentImageIndex);
            }, 150);
        }

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            const modal = document.getElementById('imageModal');
            if (modal.style.display === 'flex') {
                if (e.key === 'Escape') {
                    closeImageModal();
                } else if (e.key === 'ArrowLeft' && images.length > 1) {
                    changeModalImage(-1);
                } else if (e.key === 'ArrowRight' && images.length > 1) {
                    changeModalImage(1);
                }
            }
        });

        // Close modal when clicking on the background (not the image)
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('imageModal');
            if (e.target === modal) {
                closeImageModal();
            }
        });
        } // End of images check

        function startChat() {
            // Redirect to chat page with the lender
            window.location.href = `chat.php?user_id=<?php echo $item['lender_id']; ?>&item_id=<?php echo $item['item_id']; ?>`;
        }

        function openMessageModal() {
            document.getElementById('messageModal').style.display = 'block';
        }

        function closeMessageModal() {
            document.getElementById('messageModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('messageModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Handle form submission
        document.getElementById('messageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('send_message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Message sent successfully!');
                    closeMessageModal();
                    this.reset();
                } else {
                    alert('Failed to send message: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while sending the message');
            });
        });

        // Report modal script
        (function(){
          const overlay  = document.getElementById('reportOverlay');
          const openBtn  = document.getElementById('openReportBtn');
          const closeBtn = document.getElementById('closeReportBtn');
          const cancelBtn= document.getElementById('cancelReportBtn');
          const form     = document.getElementById('reportForm');
          const submitBtn= document.getElementById('submitReportBtn');

          function openModal(){
            overlay.hidden = false;
            document.body.classList.add('modal-open');
            document.getElementById('reason').focus();
          }
          function closeModal(){
            overlay.hidden = true;
            document.body.classList.remove('modal-open');
            form.reset();
          }

          openBtn?.addEventListener('click', openModal);
          closeBtn?.addEventListener('click', closeModal);
          cancelBtn?.addEventListener('click', closeModal);

          // close on backdrop click
          overlay?.addEventListener('click', e => { if (e.target === overlay) closeModal(); });

          // close on Esc
          document.addEventListener('keydown', e => { if (!overlay.hidden && e.key === 'Escape') closeModal(); });

          form?.addEventListener('submit', async function(e){
            e.preventDefault();
            if (!this.reason.value) { alert('Please select a reason.'); return; }

            submitBtn.disabled = true;
            try {
              const res = await fetch('report.php', { method:'POST', body: new FormData(this) });
              const data = await res.json().catch(() => ({}));
              alert(data.message || 'Report submitted.');
              if (data.success) closeModal();
            } catch {
              alert('Network error. Please try again.');
            } finally {
              submitBtn.disabled = false;
            }
          });
        })();

        // Rent modal logic
        (function(){
          const rentOverlay = document.getElementById('rentOverlay');
          const openRentBtn = document.getElementById('openRentBtn');
          const closeRentBtn = document.getElementById('closeRentBtn');
          const cancelRentBtn= document.getElementById('cancelRentBtn');
          const rentForm    = document.getElementById('rentForm');
          const daysInput   = document.getElementById('days');
          const pricePerDay = parseFloat(document.getElementById('pricePerDay').value || '0');
          const totalSpan   = document.getElementById('totalAmount');
          const gcashBlock  = document.getElementById('gcashBlock');
          const gcashRef    = document.getElementById('gcash_ref');
          const deliveryBlock = document.getElementById('deliveryBlock');
          const deliveryAddr  = document.getElementById('delivery_address');
          const gcashRadio    = document.querySelector('input[name="payment_method"][value="gcash"]');

          function openRent(){ rentOverlay.hidden = false; document.body.classList.add('modal-open'); updateTotal(); updateBlocks(); }
          function closeRent(){ rentOverlay.hidden = true; document.body.classList.remove('modal-open'); rentForm.reset(); updateBlocks(); updateTotal(); }

          function updateTotal(){
            const d = Math.max(1, Math.min(60, parseInt(daysInput.value || '1', 10)));
            daysInput.value = d;
            const total = (d * pricePerDay);
            totalSpan.textContent = total.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
          }

          function updateBlocks(){
            const method = document.querySelector('input[name="payment_method"]:checked')?.value;
            const dmethod= document.querySelector('input[name="delivery_method"]:checked')?.value;
            gcashBlock.hidden = method !== 'gcash';
            gcashRef.disabled = method !== 'gcash' || (gcashRadio && gcashRadio.disabled);
            deliveryBlock.hidden = dmethod !== 'delivery';
          }

          document.querySelectorAll('input[name="payment_method"]').forEach(r => r.addEventListener('change', updateBlocks));
          document.querySelectorAll('input[name="delivery_method"]').forEach(r => r.addEventListener('change', updateBlocks));
          daysInput.addEventListener('input', updateTotal);

          openRentBtn?.addEventListener('click', openRent);
          closeRentBtn?.addEventListener('click', closeRent);
          cancelRentBtn?.addEventListener('click', closeRent);
          rentOverlay?.addEventListener('click', e => { if (e.target === rentOverlay) closeRent(); });
          document.addEventListener('keydown', e => { if (!rentOverlay.hidden && e.key === 'Escape') closeRent(); });

          rentForm?.addEventListener('submit', async function(e){
            e.preventDefault();
            const method = document.querySelector('input[name="payment_method"]:checked')?.value;
            const dmethod= document.querySelector('input[name="delivery_method"]:checked')?.value;
            if (method === 'gcash') {
              if (!gcashRef.value || gcashRef.value.trim().length < 6) {
                alert('Please enter a valid GCash reference number.');
                return;
              }
            }
            if (dmethod === 'delivery' && (!deliveryAddr.value || deliveryAddr.value.trim().length < 8)) {
              alert('Please enter a delivery address.');
              return;
            }
            const fd = new FormData(this);
            fd.append('payment_method', method);
            fd.append('delivery_method', dmethod);
            try {
              const res = await fetch('create_rental.php', { method: 'POST', body: fd });
              const text = await res.text();
              let data = {};
              try { data = JSON.parse(text); } catch (e) {}
              alert(data.message || ('Failed to submit.\n' + text));
              if (data.success) closeRent();
            } catch { alert('Network error.'); }
          });
        })();
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>
