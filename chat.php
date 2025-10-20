<?php
/*
═══════════════════════════════════════════════════════════════════
MODERN CHAT PAGE - Copy-Paste Ready
═══════════════════════════════════════════════════════════════════

INSTRUCTIONS:
1. BACKUP your current chat.php
2. REPLACE your entire chat.php file with this code
3. DELETE styles/chat.css (styles are now embedded)
4. Save and refresh - Done!

═══════════════════════════════════════════════════════════════════
*/

include("connections.php");
session_start();

// Check if user is logged in
if (!isset($_SESSION["ID"])) {
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION["ID"];
$other_user_id = $_GET['user_id'] ?? null;
$item_id = $_GET['item_id'] ?? null;

// Lightweight AJAX poll endpoint: returns new messages after last_ts
if (isset($_GET['ajax']) && $_GET['ajax'] === 'poll') {
    header('Content-Type: application/json');
    if (!$other_user_id) { http_response_code(400); echo json_encode(['error'=>'user_id required']); exit; }
    $other_id = (int)$other_user_id;
    $me = (int)$current_user_id;
    $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    if ($last_id > 0) {
        $sql = "SELECT m.message_id, m.sender_id, m.receiver_id, m.message_text, m.sent_at
                FROM messages m
                WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                  AND m.message_id > ?
                ORDER BY m.message_id ASC
                LIMIT 100";
        $stmt = mysqli_prepare($connections, $sql);
        if (!$stmt) { http_response_code(500); echo json_encode(['error'=>'prepare failed']); exit; }
        mysqli_stmt_bind_param($stmt, "iiiii", $me, $other_id, $other_id, $me, $last_id);
    } else {
        $last_ts = isset($_GET['last_ts']) ? substr($_GET['last_ts'], 0, 19) : '1970-01-01 00:00:00';
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $last_ts)) { $last_ts = '1970-01-01 00:00:00'; }
        $sql = "SELECT m.message_id, m.sender_id, m.receiver_id, m.message_text, m.sent_at
                FROM messages m
                WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                  AND m.sent_at > ?
                ORDER BY m.sent_at ASC
                LIMIT 100";
        $stmt = mysqli_prepare($connections, $sql);
        if (!$stmt) { http_response_code(500); echo json_encode(['error'=>'prepare failed']); exit; }
        mysqli_stmt_bind_param($stmt, "iiiss", $me, $other_id, $other_id, $me, $last_ts);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $out[] = [
            'message_id' => (int)($row['message_id'] ?? 0),
            'sender_id'  => (int)$row['sender_id'],
            'receiver_id'=> (int)$row['receiver_id'],
            'message_text'=> $row['message_text'],
            'sent_at'    => $row['sent_at'],
        ];
    }
    mysqli_stmt_close($stmt);
    echo json_encode($out);
    exit;
}

if (!$other_user_id) {
    header("Location: messaging.php");
    exit;
}

// Get other user info
$user_query = "SELECT username, profile_picture_url FROM users WHERE ID = $other_user_id";
$user_result = mysqli_query($connections, $user_query);
$other_user = mysqli_fetch_assoc($user_result);

if (!$other_user) {
    header("Location: messaging.php");
    exit;
}

// Get item info if item_id is provided OR find the most recent item discussed between these users
$item_info = null;
if ($item_id) {
    // Direct item_id from URL
    $item_query = "SELECT i.*, u.username as lender_username 
                   FROM items i 
                   LEFT JOIN users u ON i.lender_id = u.ID
                   WHERE i.item_id = $item_id";
    $item_result = mysqli_query($connections, $item_query);
    $item_info = mysqli_fetch_assoc($item_result);
} else {
    // Find the most recent item discussed between these two users
    $recent_item_query = "SELECT i.*, u.username as lender_username 
                          FROM messages m
                          JOIN items i ON m.item_id = i.item_id
                          LEFT JOIN users u ON i.lender_id = u.ID
                          WHERE ((m.sender_id = $current_user_id AND m.receiver_id = $other_user_id) 
                                 OR (m.sender_id = $other_user_id AND m.receiver_id = $current_user_id))
                          AND m.item_id IS NOT NULL
                          ORDER BY m.sent_at DESC
                          LIMIT 1";
    $recent_item_result = mysqli_query($connections, $recent_item_query);
    if ($recent_item_result && mysqli_num_rows($recent_item_result) > 0) {
        $item_info = mysqli_fetch_assoc($recent_item_result);
        $item_id = $item_info['item_id']; // Set item_id for the form
    }
}

// Get conversation history (all messages between these users)
$messages_query = "SELECT m.*, u.username as sender_name, i.title as item_title, i.image_url as item_image
                   FROM messages m 
                   JOIN users u ON m.sender_id = u.ID 
                   LEFT JOIN items i ON m.item_id = i.item_id
                   WHERE ((m.sender_id = $current_user_id AND m.receiver_id = $other_user_id) 
                          OR (m.sender_id = $other_user_id AND m.receiver_id = $current_user_id))
                   ORDER BY m.sent_at ASC";
$messages = mysqli_query($connections, $messages_query);

// Mark messages as read
$mark_read = "UPDATE messages SET is_read = 1 
              WHERE sender_id = $other_user_id AND receiver_id = $current_user_id";
mysqli_query($connections, $mark_read);

// Get items these users have discussed or that are available for discussion
$items_query = "SELECT DISTINCT i.item_id, i.title 
               FROM items i
               LEFT JOIN messages m ON i.item_id = m.item_id
               WHERE (i.lender_id = $current_user_id OR i.lender_id = $other_user_id)
               OR (m.sender_id IN ($current_user_id, $other_user_id) AND m.receiver_id IN ($current_user_id, $other_user_id))
               ORDER BY i.title";
$items_result = mysqli_query($connections, $items_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with <?php echo htmlspecialchars($other_user['username']); ?> - RENTayo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ═══════════════════════════════════════════════════════════════
           MODERN CHAT STYLES - Blue Gradient Theme
           ═══════════════════════════════════════════════════════════ */
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 50%, #eef2ff 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ═══════════════════════════════════════════════════════════════
           CHAT HEADER
           ═══════════════════════════════════════════════════════════ */

        .chat-header {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #6366f1 100%);
            color: #fff;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 
                0 4px 6px -1px rgba(0, 0, 0, 0.1),
                0 2px 4px -1px rgba(0, 0, 0, 0.06);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            text-decoration: none;
            font-size: 20px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateX(-2px);
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.25);
            border: 3px solid rgba(255, 255, 255, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            overflow: hidden;
            flex-shrink: 0;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .chat-info {
            flex: 1;
            min-width: 0;
        }

        .chat-info h2 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 2px;
            color: #fff;
        }

        .chat-subtitle {
            font-size: 14px;
            opacity: 0.9;
            color: rgba(255, 255, 255, 0.85);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ═══════════════════════════════════════════════════════════════
           CHAT CONTAINER
           ═══════════════════════════════════════════════════════════ */

        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            height: calc(100vh - 80px);
        }

        /* ═══════════════════════════════════════════════════════════════
           ITEM CONTEXT CARD
           ═══════════════════════════════════════════════════════════ */

        .item-context-card {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            margin: 20px 20px 0;
            box-shadow: 
                0 4px 6px -1px rgba(0, 0, 0, 0.1),
                0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
        }

        .item-context-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
        }

        .context-icon {
            font-size: 20px;
        }

        .context-text {
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
        }

        .item-context-content {
            display: grid;
            grid-template-columns: 80px 1fr auto;
            gap: 16px;
            align-items: center;
        }

        .item-context-image {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            overflow: hidden;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .item-context-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-placeholder {
            font-size: 32px;
        }

        .item-context-details {
            min-width: 0;
        }

        .item-context-title {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .item-context-price {
            font-size: 18px;
            font-weight: 700;
            color: #3b82f6;
            margin-bottom: 2px;
        }

        .item-context-lender {
            font-size: 13px;
            color: #64748b;
        }

        .view-item-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%);
            color: #fff;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
        }

        .view-item-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
        }

        /* ═══════════════════════════════════════════════════════════════
           MESSAGES AREA
           ═══════════════════════════════════════════════════════════ */

        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .messages-area::-webkit-scrollbar {
            width: 8px;
        }

        .messages-area::-webkit-scrollbar-track {
            background: transparent;
        }

        .messages-area::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .messages-area::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Item Separator (when multiple items discussed) */
        .item-separator {
            display: flex;
            justify-content: center;
            margin: 16px 0;
        }

        .item-label {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 6px 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
        }

        .item-icon {
            font-size: 16px;
        }

        /* Message Bubbles */
        .message {
            display: flex;
            margin-bottom: 4px;
        }

        .message.sent {
            justify-content: flex-end;
        }

        .message.received {
            justify-content: flex-start;
        }

        .message-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 16px;
            position: relative;
            animation: messageSlideIn 0.2s ease;
        }

        @keyframes messageSlideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.sent .message-bubble {
            background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%);
            color: #fff;
            border-bottom-right-radius: 4px;
        }

        .message.received .message-bubble {
            background: #fff;
            color: #1e293b;
            border: 1px solid #e2e8f0;
            border-bottom-left-radius: 4px;
        }

        .message-time {
            font-size: 11px;
            margin-top: 6px;
            opacity: 0.7;
        }

        .no-messages {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #94a3b8;
            font-size: 15px;
        }

        /* ═══════════════════════════════════════════════════════════════
           MESSAGE INPUT AREA
           ═══════════════════════════════════════════════════════════ */

        .message-input-area {
            background: #fff;
            border-top: 2px solid #f1f5f9;
            padding: 16px 20px;
            box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .item-selector {
            margin-bottom: 12px;
        }

        .item-select {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            color: #475569;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .item-select:focus {
            outline: none;
            border-color: #3b82f6;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .message-form {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .message-input {
            flex: 1;
            min-height: 44px;
            max-height: 120px;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            font-family: inherit;
            resize: none;
            background: #f8fafc;
            color: #1e293b;
            transition: all 0.2s ease;
        }

        .message-input:focus {
            outline: none;
            border-color: #3b82f6;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .message-input::placeholder {
            color: #94a3b8;
        }

        .send-btn {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%);
            color: #fff;
            border: none;
            font-size: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            flex-shrink: 0;
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
        }

        .send-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(59, 130, 246, 0.4);
        }

        .send-btn:active {
            transform: translateY(0);
        }

        /* ═══════════════════════════════════════════════════════════════
           RESPONSIVE DESIGN
           ═══════════════════════════════════════════════════════════ */

        @media (max-width: 768px) {
            .chat-header {
                padding: 12px 16px;
            }

            .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }

            .chat-info h2 {
                font-size: 18px;
            }

            .chat-subtitle {
                font-size: 13px;
            }

            .item-context-card {
                margin: 16px;
                padding: 16px;
            }

            .item-context-content {
                grid-template-columns: 60px 1fr;
                gap: 12px;
            }

            .item-context-image {
                width: 60px;
                height: 60px;
            }

            .item-context-actions {
                grid-column: 1 / -1;
            }

            .view-item-btn {
                width: 100%;
                text-align: center;
            }

            .message-bubble {
                max-width: 85%;
            }

            .messages-area {
                padding: 16px;
            }

            .message-input-area {
                padding: 12px 16px;
            }
        }
    </style>
</head>
<body>
    <div class="chat-header">
        <a href="messaging.php" class="back-btn">←</a>
        <div class="user-avatar">
            <?php if ($other_user['profile_picture_url'] && file_exists($other_user['profile_picture_url'])): ?>
                <img src="<?php echo htmlspecialchars($other_user['profile_picture_url']); ?>" alt="Profile">
            <?php else: ?>
                <?php echo strtoupper(substr($other_user['username'], 0, 1)); ?>
            <?php endif; ?>
        </div>
        <div class="chat-info">
            <h2><?php echo htmlspecialchars($other_user['username']); ?></h2>
            <p class="chat-subtitle">
                <?php if ($item_info): ?>
                    About: <?php echo htmlspecialchars($item_info['title']); ?>
                <?php else: ?>
                    All conversations
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="chat-container">
        <?php if ($item_info): ?>
            <!-- Item Context Card -->
            <div class="item-context-card">
                <div class="item-context-header">
                    <span class="context-icon">💬</span>
                    <span class="context-text">You're discussing this item:</span>
                </div>
                <div class="item-context-content">
                    <div class="item-context-image">
                        <?php if ($item_info['image_url'] && file_exists($item_info['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($item_info['image_url']); ?>" alt="<?php echo htmlspecialchars($item_info['title']); ?>">
                        <?php else: ?>
                            <div class="item-placeholder">📦</div>
                        <?php endif; ?>
                    </div>
                    <div class="item-context-details">
                        <h4 class="item-context-title"><?php echo htmlspecialchars($item_info['title']); ?></h4>
                        <p class="item-context-price">₱<?php echo number_format($item_info['price_per_day'], 2); ?>/day</p>
                        <p class="item-context-lender">by <?php echo htmlspecialchars($item_info['lender_username']); ?></p>
                    </div>
                    <div class="item-context-actions">
                        <a href="item_details.php?item_id=<?php echo $item_info['item_id']; ?>" class="view-item-btn" target="_blank">
                            View Details
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="messages-area" id="messagesArea">
            <?php if (mysqli_num_rows($messages) > 0): ?>
                <?php 
                $current_item = null;
                while($message = mysqli_fetch_assoc($messages)): 
                    // Show item header when item changes
                    if ($message['item_id'] && $current_item != $message['item_id'] && !$item_info):
                        $current_item = $message['item_id'];
                ?>
                        <div class="item-separator">
                            <div class="item-label">
                                <span class="item-icon">📦</span>
                                <span class="item-name"><?php echo htmlspecialchars($message['item_title']); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="message <?php echo $message['sender_id'] == $current_user_id ? 'sent' : 'received'; ?>" 
                         data-ts="<?php echo htmlspecialchars($message['sent_at']); ?>"
                         data-id="<?php echo (int)$message['message_id']; ?>">
                        <div class="message-bubble">
                            <?php echo nl2br(htmlspecialchars($message['message_text'])); ?>
                            <div class="message-time">
                                <?php echo date('M j, g:i A', strtotime($message['sent_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-messages">
                    <p>No messages yet. Start the conversation!</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="message-input-area">
            <?php if (!$item_info): ?>
                <div class="item-selector">
                    <select name="selected_item_id" id="selectedItemId" class="item-select">
                        <option value="">General conversation</option>
                        <?php 
                        mysqli_data_seek($items_result, 0);
                        while($item = mysqli_fetch_assoc($items_result)): 
                        ?>
                            <option value="<?php echo $item['item_id']; ?>">
                                <?php echo htmlspecialchars($item['title']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <form class="message-form" id="messageForm">
                <input type="hidden" name="receiver_id" value="<?php echo $other_user_id; ?>">
                <input type="hidden" name="item_id" value="<?php echo $item_id ?? ''; ?>" id="hiddenItemId">
                <textarea 
                    name="message_text" 
                    class="message-input" 
                    placeholder="Type a message..." 
                    required
                    rows="1"
                    id="messageInput"
                ></textarea>
                <button type="submit" class="send-btn" id="sendBtn">→</button>
            </form>
        </div>
    </div>

    <script>
        const messageForm = document.getElementById('messageForm');
        const messageInput = document.getElementById('messageInput');
        const messagesArea = document.getElementById('messagesArea');
        const sendBtn = document.getElementById('sendBtn');
        const selectedItemId = document.getElementById('selectedItemId');
        const hiddenItemId = document.getElementById('hiddenItemId');
        
        // Sync item selector with hidden field
        if (selectedItemId) {
            selectedItemId.addEventListener('change', function() {
                hiddenItemId.value = this.value;
            });
        }
        
        // Auto-resize textarea
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });

        // Send message on Enter (Shift+Enter for new line)
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                messageForm.dispatchEvent(new Event('submit'));
            }
        });

        // Handle form submission
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('send_message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageInput.value = '';
                    messageInput.style.height = 'auto';
                    addMessageToChat(formData.get('message_text'), true);
                    scrollToBottom();
                } else {
                    alert('Failed to send message: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while sending the message');
            });
        });

        // Helper: scroll to bottom
        function scrollToBottom() {
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }

        // Current user for rendering context
        const CURRENT_USER_ID = <?php echo (int)$current_user_id; ?>;
        const OTHER_USER_ID = <?php echo (int)$other_user_id; ?>;
        
        // Determine last watermark from DOM
        function getLastTs() {
            const nodes = document.querySelectorAll('.message[data-ts]');
            if (nodes.length) return nodes[nodes.length - 1].dataset.ts;
            return '1970-01-01 00:00:00';
        }
        function getLastId() {
            const nodes = document.querySelectorAll('.message[data-id]');
            if (!nodes.length) return 0;
            const last = nodes[nodes.length - 1].dataset.id;
            return parseInt(last || '0', 10) || 0;
        }
        let lastTs = getLastTs();
        let lastId = getLastId();
        
        // Append a message bubble to the UI
        function appendMessage(msg) {
            const isSent = (msg.sender_id === CURRENT_USER_ID);
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isSent ? 'sent' : 'received'}`;
            messageDiv.dataset.ts = msg.sent_at;
            if (msg.message_id) messageDiv.dataset.id = String(msg.message_id);
            const timeString = new Date(msg.sent_at.replace(' ', 'T')).toLocaleString('en-US', {
                month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true
            });
            messageDiv.innerHTML = `
                <div class="message-bubble">
                    ${String(msg.message_text).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>')}
                    <div class="message-time">${timeString}</div>
                </div>
            `;
            messagesArea.appendChild(messageDiv);
            if (msg.message_id) lastId = Math.max(lastId, Number(msg.message_id) || 0);
            lastTs = msg.sent_at; // keep timestamp updated too (fallback)
        }
        
        // Poll server for new messages
        async function pollMessages() {
            try {
                const base = `chat.php?ajax=poll&user_id=${encodeURIComponent(OTHER_USER_ID)}`;
                const url = lastId > 0 
                    ? `${base}&last_id=${encodeURIComponent(lastId)}`
                    : `${base}&last_ts=${encodeURIComponent(lastTs)}`;
                const res = await fetch(url, { cache: 'no-store' });
                if (!res.ok) return;
                const data = await res.json();
                if (Array.isArray(data) && data.length) {
                    const atBottom = (messagesArea.scrollHeight - messagesArea.scrollTop - messagesArea.clientHeight) < 40;
                    data.forEach(m => {
                        appendMessage(m);
                    });
                    if (atBottom) scrollToBottom();
                }
            } catch (_) { /* ignore network errors */ }
        }
        // Start polling every 3 seconds and also once immediately
        setInterval(pollMessages, 3000);
        pollMessages();
        // Refresh when window gains focus
        window.addEventListener('focus', pollMessages);
        
        // When we locally add a message (after send), update lastTs/lastId
        function addMessageToChat(text, isSent) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isSent ? 'sent' : 'received'}`;
            const now = new Date();
            const iso = now.toISOString().slice(0,19).replace('T',' ');
            messageDiv.dataset.ts = iso;
            // do not set data-id here (unknown until server assigns)
            const timeString = now.toLocaleDateString('en-US', {
                month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true
            });
            messageDiv.innerHTML = `
                <div class="message-bubble">
                    ${String(text).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>')}
                    <div class="message-time">${timeString}</div>
                </div>
            `;
            messagesArea.appendChild(messageDiv);
            // keep lastId unchanged; server poll will deliver the row with its real ID
        }
        
        // keep initial view at bottom
        window.addEventListener('load', scrollToBottom);
    </script>
    
    <!-- Provided script (added) -->
    <script>
        if (window.lucide && typeof lucide.createIcons === 'function') { lucide.createIcons(); }
        function toggleSidebar(){
            const sidebar = document.getElementById('sidebar');
            const main = document.getElementById('mainContent');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar) sidebar.classList.toggle('open');
            if (main) main.classList.toggle('shifted');
            if (overlay) {
                overlay.classList.toggle('show');
                setTimeout(() => { if (window.lucide && lucide.createIcons) lucide.createIcons(); }, 100);
            }
        }
    </script>
</body>
</html>
