<?php

session_start();
include_once dirname(__DIR__) . '/connections.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($_SESSION['ID'])) { exit; }

$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$searchQuery    = isset($_GET['search']) ? trim($_GET['search']) : '';
$lenderFilter   = isset($_GET['lender_id']) ? (int)$_GET['lender_id'] : 0;

$where = ["i.status = 'approved'"];
if ($categoryFilter) $where[] = "c.category_id = " . $categoryFilter;
if ($searchQuery !== '') {
    $q = mysqli_real_escape_string($connections, $searchQuery);
    $where[] = "(i.title LIKE '%$q%' OR i.description LIKE '%$q%')";
}
if ($lenderFilter) $where[] = "i.lender_id = " . $lenderFilter;

$items_sql = "
    SELECT 
        i.*,
        u.username,
        COALESCE(GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', '), '') AS categories
    FROM items i
    INNER JOIN users u ON u.ID = i.lender_id AND u.is_banned = 0
    LEFT JOIN itemcategories ic ON ic.item_id = i.item_id
    LEFT JOIN categories c ON c.category_id = ic.category_id
    " . (count($where) ? "WHERE " . implode(" AND ", $where) : "") . "
    GROUP BY i.item_id
    ORDER BY i.item_id DESC
";
$items_res = mysqli_query($connections, $items_sql);

if ($items_res && mysqli_num_rows($items_res) > 0) {
    while ($item = mysqli_fetch_assoc($items_res)) {
        $imagePath = !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : '';
        $imageSrc = $imagePath ? $imagePath : 'images/default-item.jpg';
        $cats = trim((string)($item['categories'] ?? ''));
        if ($cats === '') $cats = 'Uncategorized';
        ?>
        <a href="item_details.php?item_id=<?php echo (int)$item['item_id']; ?>" class="item">
            <div class="item-image">
                <?php if ($imagePath): ?>
                    <img src="<?php echo $imageSrc; ?>" alt="<?php echo htmlspecialchars($item['title']); ?>"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <?php endif; ?>
                <div class="no-image" style="<?php echo $imagePath ? 'display:none;' : ''; ?>">📷</div>
            </div>
            <div class="item-content">
                <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                <p class="description"><?php echo htmlspecialchars($item['description']); ?></p>
                <p class="price">₱<?php echo number_format((float)$item['price_per_day'], 2); ?>/day</p>
                <p class="categories"><?php echo htmlspecialchars($cats); ?></p>
            </div>
        </a>
        <?php
    }
} else {
    ?>
    <div class="item" style="grid-column: 1 / -1; text-align: center; padding: 40px;">
        <p style="margin: 0; font-size: 16px; color: #64748b;">No items found.</p>
    </div>
    <?php
}