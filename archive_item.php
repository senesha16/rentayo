<?php
session_start();
include("connections.php");

// Check if user is logged in
if (!isset($_SESSION["ID"])) {
    header("Location: login.php");
    exit;
}

// Check if item_id is provided
if (!isset($_GET['item_id']) || empty($_GET['item_id'])) {
    header("Location: index.php");
    exit;
}

$item_id = intval($_GET['item_id']);
$user_id = $_SESSION["ID"];

// Verify that the user owns this item
$verify_query = "SELECT lender_id FROM items WHERE item_id = ? AND archived = 0";
$verify_stmt = mysqli_prepare($connections, $verify_query);
mysqli_stmt_bind_param($verify_stmt, "i", $item_id);
mysqli_stmt_execute($verify_stmt);
$verify_result = mysqli_stmt_get_result($verify_stmt);

if (mysqli_num_rows($verify_result) == 0) {
    header("Location: index.php?error=item_not_found");
    exit;
}

$item = mysqli_fetch_assoc($verify_result);

if ($item['lender_id'] != $user_id) {
    header("Location: index.php?error=not_authorized");
    exit;
}

// Archive the item (soft delete)
$archive_query = "UPDATE items SET archived = 1, archived_at = NOW() WHERE item_id = ? AND lender_id = ?";
$archive_stmt = mysqli_prepare($connections, $archive_query);
mysqli_stmt_bind_param($archive_stmt, "ii", $item_id, $user_id);

if (mysqli_stmt_execute($archive_stmt)) {
    // Redirect to index with success message
    header("Location: index.php?success=item_archived");
    exit;
} else {
    // Redirect with error message
    header("Location: item_details.php?item_id=" . $item_id . "&error=archive_failed");
    exit;
}
?>