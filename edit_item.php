<?php
// COMPLETE ERROR SUPPRESSION
@ob_start();
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);
ini_set('error_log', '/dev/null');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    return true;
});

set_exception_handler(function($exception) {
    // Do nothing
});

include("connections.php");
session_start();

if (!isset($_SESSION["ID"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["ID"];
$item_id = intval($_GET['item_id'] ?? 0);
$message = "";
$error = "";

// Verify item exists and belongs to user
$verify_query = "SELECT * FROM items WHERE item_id = ? AND lender_id = ?";
$verify_stmt = @mysqli_prepare($connections, $verify_query);

if (!$verify_stmt) {
    header("Location: my_items.php");
    exit;
}

@mysqli_stmt_bind_param($verify_stmt, "ii", $item_id, $user_id);
@mysqli_stmt_execute($verify_stmt);
$verify_result = @mysqli_stmt_get_result($verify_stmt);

if (!$verify_result || @mysqli_num_rows($verify_result) == 0) {
    header("Location: my_items.php");
    exit;
}

$item = @mysqli_fetch_assoc($verify_result);

// Get all categories
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories_result = @mysqli_query($connections, $categories_query);

// Get current item categories
$item_categories_query = "SELECT category_id FROM itemcategories WHERE item_id = ?";
$item_cat_stmt = @mysqli_prepare($connections, $item_categories_query);
@mysqli_stmt_bind_param($item_cat_stmt, "i", $item_id);
@mysqli_stmt_execute($item_cat_stmt);
$item_cat_result = @mysqli_stmt_get_result($item_cat_stmt);

$selected_categories = [];
if ($item_cat_result) {
    while ($row = @mysqli_fetch_assoc($item_cat_result)) {
        $selected_categories[] = $row['category_id'];
    }
}

// Get current item images from item_images table (the correct table with data)
$item_images_query = "SELECT * FROM item_images WHERE item_id = ? ORDER BY is_primary DESC, sort_order ASC";
$item_images_stmt = @mysqli_prepare($connections, $item_images_query);
@mysqli_stmt_bind_param($item_images_stmt, "i", $item_id);
@mysqli_stmt_execute($item_images_stmt);
$item_images_result = @mysqli_stmt_get_result($item_images_stmt);

$existing_images = [];
if ($item_images_result) {
    while ($row = @mysqli_fetch_assoc($item_images_result)) {
        $existing_images[] = $row;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    $categories = $_POST['categories'] ?? [];
    $delete_images = $_POST['delete_images'] ?? [];

    // Validation
    $errors = [];
    
    if (empty($title)) $errors[] = "Title is required.";
    if (empty($description)) $errors[] = "Description is required.";
    if ($price <= 0) $errors[] = "Price must be greater than 0.";
    if ($quantity < 1) $errors[] = "Quantity must be at least 1.";
    if (empty($categories)) $errors[] = "Please select at least one category.";

    // Handle image deletions
    if (!empty($delete_images)) {
        foreach ($delete_images as $image_id) {
            $image_id = intval($image_id);
            
            // Get image info before deletion
            $get_image_query = "SELECT image_url FROM item_images WHERE image_id = ? AND item_id = ?";
            $get_image_stmt = @mysqli_prepare($connections, $get_image_query);
            @mysqli_stmt_bind_param($get_image_stmt, "ii", $image_id, $item_id);
            @mysqli_stmt_execute($get_image_stmt);
            $get_image_result = @mysqli_stmt_get_result($get_image_stmt);
            
            if ($get_image_result && $image_row = @mysqli_fetch_assoc($get_image_result)) {
                // Delete physical file
                if (@file_exists($image_row['image_url'])) {
                    @unlink($image_row['image_url']);
                }
                
                // Delete from database
                $delete_image_query = "DELETE FROM item_images WHERE image_id = ? AND item_id = ?";
                $delete_image_stmt = @mysqli_prepare($connections, $delete_image_query);
                @mysqli_stmt_bind_param($delete_image_stmt, "ii", $image_id, $item_id);
                @mysqli_stmt_execute($delete_image_stmt);
            }
        }
    }
    
    // Handle new image uploads
    if (isset($_FILES['images']) && $_FILES['images']['error'][0] != UPLOAD_ERR_NO_FILE) {
        $upload_dir = 'uploads/items/';
        
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0755, true);
        }
        
        $files = $_FILES['images'];
        $file_count = count($files['name']);
        
        // Get current max sort order
        $max_sort_query = "SELECT COALESCE(MAX(sort_order), -1) + 1 as next_sort FROM item_images WHERE item_id = ?";
        $max_sort_stmt = @mysqli_prepare($connections, $max_sort_query);
        @mysqli_stmt_bind_param($max_sort_stmt, "i", $item_id);
        @mysqli_stmt_execute($max_sort_stmt);
        $max_sort_result = @mysqli_stmt_get_result($max_sort_stmt);
        $next_sort_order = 0;
        if ($max_sort_result && $sort_row = @mysqli_fetch_assoc($max_sort_result)) {
            $next_sort_order = $sort_row['next_sort'];
        }
        
        // DON'T change primary image - only set as primary if NO images exist at all
        $check_primary_query = "SELECT COUNT(*) as image_count FROM item_images WHERE item_id = ?";
        $check_primary_stmt = @mysqli_prepare($connections, $check_primary_query);
        @mysqli_stmt_bind_param($check_primary_stmt, "i", $item_id);
        @mysqli_stmt_execute($check_primary_stmt);
        $check_primary_result = @mysqli_stmt_get_result($check_primary_stmt);
        $has_no_images = true;
        if ($check_primary_result && $count_row = @mysqli_fetch_assoc($check_primary_result)) {
            $has_no_images = ($count_row['image_count'] == 0);
        }
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] == UPLOAD_ERR_OK) {
                $file_info = @pathinfo($files['name'][$i]);
                $file_extension = strtolower($file_info['extension'] ?? '');
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    $errors[] = "Only JPG, JPEG, PNG, and GIF files are allowed for " . $files['name'][$i];
                    continue;
                }
                
                if ($files['size'][$i] > 5 * 1024 * 1024) {
                    $errors[] = "Image " . $files['name'][$i] . " must be less than 5MB.";
                    continue;
                }
                
                $new_filename = 'item_' . $item_id . '_' . time() . '_' . $i . '.' . $file_extension;
                $new_image_path = $upload_dir . $new_filename;
                
                if (@move_uploaded_file($files['tmp_name'][$i], $new_image_path)) {
                    // Insert into item_images table - only set first image as primary if no images existed before
                    $insert_image_query = "INSERT INTO item_images (item_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)";
                    $insert_image_stmt = @mysqli_prepare($connections, $insert_image_query);
                    $is_primary = ($has_no_images && $i == 0) ? 1 : 0;
                    $current_sort = $next_sort_order + $i;
                    @mysqli_stmt_bind_param($insert_image_stmt, "isii", $item_id, $new_image_path, $is_primary, $current_sort);
                    @mysqli_stmt_execute($insert_image_stmt);
                    
                    // After first image is set as primary, don't set any more as primary
                    if ($has_no_images && $i == 0) {
                        $has_no_images = false;
                    }
                } else {
                    $errors[] = "Failed to upload " . $files['name'][$i];
                }
            }
        }
    }

    // Update item if no errors - REMOVE location field completely
    if (empty($errors)) {
        $update_query = "UPDATE items SET title = ?, description = ?, price_per_day = ?, available_items_count = ? WHERE item_id = ?";
        $update_stmt = @mysqli_prepare($connections, $update_query);
        
        if ($update_stmt) {
            @mysqli_stmt_bind_param($update_stmt, "ssdii", $title, $description, $price, $quantity, $item_id);
            
            if (@mysqli_stmt_execute($update_stmt)) {
                // Delete existing categories
                $delete_cat_query = "DELETE FROM itemcategories WHERE item_id = ?";
                $delete_cat_stmt = @mysqli_prepare($connections, $delete_cat_query);
                if ($delete_cat_stmt) {
                    @mysqli_stmt_bind_param($delete_cat_stmt, "i", $item_id);
                    @mysqli_stmt_execute($delete_cat_stmt);
                }
                
                // Insert new categories
                $insert_cat_query = "INSERT INTO itemcategories (item_id, category_id) VALUES (?, ?)";
                $insert_cat_stmt = @mysqli_prepare($connections, $insert_cat_query);
                
                if ($insert_cat_stmt) {
                    foreach ($categories as $category_id) {
                        @mysqli_stmt_bind_param($insert_cat_stmt, "ii", $item_id, $category_id);
                        @mysqli_stmt_execute($insert_cat_stmt);
                    }
                }
                
                // Redirect to my_items.php after successful update
                header("Location: my_items.php?success=Item updated successfully!");
                exit;
                
            } else {
                $errors[] = "Failed to update item. Please try again.";
            }
        } else {
            $errors[] = "Database error occurred.";
        }
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
    
    // FIX: Refresh existing images after operations - get fresh result
    $refresh_images_query = "SELECT * FROM item_images WHERE item_id = ? ORDER BY is_primary DESC, sort_order ASC";
    $refresh_images_stmt = @mysqli_prepare($connections, $refresh_images_query);
    @mysqli_stmt_bind_param($refresh_images_stmt, "i", $item_id);
    @mysqli_stmt_execute($refresh_images_stmt);
    $refresh_images_result = @mysqli_stmt_get_result($refresh_images_stmt);

    $existing_images = [];
    if ($refresh_images_result) {
        while ($row = @mysqli_fetch_assoc($refresh_images_result)) {
            $existing_images[] = $row;
        }
    }
}

@ob_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Item - RENTayo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles/style.css">
    <style>
        body {
            margin-top: 80px;
            background: #f8fafc;
            font-family: 'Poppins', sans-serif;
        }
        
        .edit-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .edit-form {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .form-title {
            font-size: 32px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .current-image-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 15px;
        }
        
        .current-image-container {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .current-image {
            width: 200px;
            height: 150px;
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .current-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .image-placeholder {
            font-size: 48px;
            color: #d1d5db;
        }
        
        .image-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 10px;
        }
        
        .delete-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 8px 12px;
            background: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            color: #991b1b;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .delete-checkbox:hover {
            background: #fecaca;
        }
        
        .delete-checkbox input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .delete-warning {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            color: #92400e;
            padding: 12px 16px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 14px;
            display: none;
        }
        
        .no-image-text {
            color: #6b7280;
            font-style: italic;
            margin-top: 10px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }
        
        input, textarea, select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .file-upload {
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-upload:hover {
            border-color: #6366f1;
            background: #f8fafc;
        }
        
        .file-upload input {
            display: none;
        }
        
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }
        
        .category-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .category-item:hover {
            background: #f9fafb;
        }
        
        .category-item input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e5e7eb;
        }
        
        .btn {
            padding: 14px 32px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.3);
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: 2px solid #e5e7eb;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
            text-decoration: none;
            color: #374151;
        }
        
        @media (max-width: 768px) {
            .edit-container {
                padding: 0 15px;
            }
            
            .edit-form {
                padding: 25px 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .categories-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .current-image-container {
                flex-direction: column;
            }
        }
        
        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .image-item {
            position: relative;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .image-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        
        .image-item .primary-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: #10b981;
            color: white;
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .image-item .delete-option {
            position: absolute;
            bottom: 8px;
            right: 8px;
        }
        
        .image-item .delete-option input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .image-item .delete-option label {
            background: rgba(239, 68, 68, 0.9);
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            margin: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .file-upload-multiple {
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-upload-multiple:hover {
            border-color: #6366f1;
            background: #f8fafc;
        }
        
        .file-upload-multiple input {
            display: none;
        }
        
        .selected-files {
            margin-top: 15px;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
            display: none;
        }
        
        .selected-files h4 {
            margin: 0 0 10px 0;
            color: #374151;
            font-size: 14px;
        }
        
        .selected-files ul {
            margin: 0;
            padding-left: 20px;
            color: #6b7280;
            font-size: 14px;
        }
        
        .image-preview-container {
            margin-top: 15px;
            display: none;
        }
        
        .preview-images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        
        .preview-image-item {
            position: relative;
            border: 2px solid #d1d5db;
            border-radius: 8px;
            overflow: hidden;
            background: white;
        }
        
        .preview-image-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }
        
        .preview-image-info {
            padding: 8px;
            font-size: 12px;
            color: #6b7280;
            background: #f9fafb;
            text-align: center;
        }
        
        .preview-remove-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .preview-remove-btn:hover {
            background: rgba(239, 68, 68, 1);
        }
        
        .preview-primary-badge {
            position: absolute;
            top: 5px;
            left: 5px;
            background: #10b981;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php @include("nav_switch.php"); ?>
    
    <div class="edit-container">
        <div class="edit-form">
            <h1 class="form-title">Edit Item</h1>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <!-- Current Images -->
                <div class="current-image-section">
                    <h3 class="section-title">Current Item Images</h3>
                    <?php if (!empty($existing_images)): ?>
                        <div class="images-grid">
                            <?php foreach ($existing_images as $image): ?>
                                <div class="image-item">
                                    <?php if (@file_exists($image['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($image['image_url']); ?>" alt="Item Image">
                                        <?php if ($image['is_primary']): ?>
                                            <div class="primary-badge">Primary</div>
                                        <?php endif; ?>
                                        <div class="delete-option">
                                            <label>
                                                <input type="checkbox" name="delete_images[]" value="<?php echo $image['image_id']; ?>">
                                                🗑️ Delete
                                            </label>
                                        </div>
                                    <?php else: ?>
                                        <div style="height: 150px; display: flex; align-items: center; justify-content: center; background: #f3f4f6; color: #6b7280;">
                                            Image not found
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="current-image">
                            <div class="image-placeholder">📦</div>
                        </div>
                        <p class="no-image-text">No images uploaded yet</p>
                    <?php endif; ?>
                </div>
                
                <!-- Add New Images -->
                <div class="form-group">
                    <h3 class="section-title">Add New Images</h3>
                    <div class="file-upload-multiple" onclick="document.getElementById('imageUpload').click()">
                        <div style="font-size: 48px; margin-bottom: 10px;">📷</div>
                        <p>Click to add new images</p>
                        <small>Select multiple JPG, PNG, GIF up to 5MB each</small>
                        <input type="file" id="imageUpload" name="images[]" accept="image/*" multiple onchange="handleFileSelection(this)">
                    </div>
                    
                    <div class="selected-files" id="selectedFiles">
                        <h4>Selected files:</h4>
                        <ul id="fileList"></ul>
                    </div>
                    
                    <div class="image-preview-container" id="imagePreviewContainer">
                        <h4 style="margin: 10px 0; color: #374151; font-size: 14px;">Preview of new images:</h4>
                        <div class="preview-images-grid" id="previewImagesGrid"></div>
                        <p style="font-size: 12px; color: #6b7280; margin-top: 10px; font-style: italic;">
                            Note: These are preview images only. Click "Update Item" to save them.
                        </p>
                    </div>
                </div>
                
                <!-- Basic Information -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Item Name *</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($item['title'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="price">Price Per Day *</label>
                        <input type="number" id="price" name="price" step="0.01" min="0.01" value="<?php echo $item['price_per_day'] ?? ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity">Quantity Available *</label>
                        <input type="number" id="quantity" name="quantity" min="1" value="<?php echo $item['available_items_count'] ?? ''; ?>" required>
                    </div>
                </div>
                
                <!-- Categories -->
                <div class="form-group">
                    <label>Categories *</label>
                    <div class="categories-grid">
                        <?php 
                        if ($categories_result) {
                            @mysqli_data_seek($categories_result, 0);
                            while($category = @mysqli_fetch_assoc($categories_result)): 
                        ?>
                            <div class="category-item">
                                <input type="checkbox" 
                                       id="category_<?php echo $category['category_id']; ?>" 
                                       name="categories[]" 
                                       value="<?php echo $category['category_id']; ?>"
                                       <?php echo in_array($category['category_id'], $selected_categories) ? 'checked' : ''; ?>>
                                <label for="category_<?php echo $category['category_id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </label>
                            </div>
                        <?php 
                            endwhile; 
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Description -->
                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" required placeholder="Describe your item..."><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                </div>
                
                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="my_items.php" class="btn btn-secondary">← Back to My Items</a>
                    <button type="submit" class="btn btn-primary">Update Item</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let selectedFiles = [];
        let fileInput = null;
        
        function handleFileSelection(input) {
            fileInput = input;
            selectedFiles = Array.from(input.files);
            showSelectedFiles();
            showImagePreviews();
        }
        
        function showSelectedFiles() {
            const selectedFilesDiv = document.getElementById('selectedFiles');
            const fileList = document.getElementById('fileList');
            
            if (selectedFiles.length > 0) {
                fileList.innerHTML = '';
                selectedFiles.forEach((file, index) => {
                    const li = document.createElement('li');
                    li.textContent = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
                    fileList.appendChild(li);
                });
                selectedFilesDiv.style.display = 'block';
            } else {
                selectedFilesDiv.style.display = 'none';
            }
        }
        
        function showImagePreviews() {
            const previewContainer = document.getElementById('imagePreviewContainer');
            const previewGrid = document.getElementById('previewImagesGrid');
            
            if (selectedFiles.length > 0) {
                previewGrid.innerHTML = '';
                
                selectedFiles.forEach((file, index) => {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const previewItem = document.createElement('div');
                            previewItem.className = 'preview-image-item';
                            
                            // Check if this would be the primary image (first image when no existing images)
                            const hasExistingImages = <?php echo !empty($existing_images) ? 'true' : 'false'; ?>;
                            const wouldBePrimary = !hasExistingImages && index === 0;
                            
                            previewItem.innerHTML = `
                                <img src="${e.target.result}" alt="Preview ${index + 1}">
                                ${wouldBePrimary ? '<div class="preview-primary-badge">Primary</div>' : ''}
                                <button type="button" class="preview-remove-btn" onclick="removePreviewImage(${index})" title="Remove image">×</button>
                                <div class="preview-image-info">
                                    ${file.name}<br>
                                    ${Math.round(file.size / 1024)} KB
                                </div>
                            `;
                            
                            previewGrid.appendChild(previewItem);
                        };
                        reader.readAsDataURL(file);
                    }
                });
                
                previewContainer.style.display = 'block';
            } else {
                previewContainer.style.display = 'none';
            }
        }
        
        function removePreviewImage(index) {
            selectedFiles.splice(index, 1);
            
            // Update the file input with remaining files
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
            
            showSelectedFiles();
            showImagePreviews();
        }
        
        function toggleDeletePhoto() {
            const checkbox = document.getElementById('deletePhoto');
            const warningDiv = document.getElementById('deleteWarning');
            
            if (checkbox.checked) {
                if (!warningDiv) {
                    const warning = document.createElement('div');
                    warning.id = 'deleteWarning';
                    warning.className = 'delete-warning';
                    warning.innerHTML = '⚠️ The current photo will be permanently deleted when you save changes.';
                    checkbox.closest('.image-actions').appendChild(warning);
                }
                
                document.getElementById('deleteWarning').style.display = 'block';
            } else {
                if (warningDiv) {
                    warningDiv.style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>