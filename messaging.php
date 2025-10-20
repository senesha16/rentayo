<?php
/*
═══════════════════════════════════════════════════════════════════
MODERN MESSAGING PAGE - Copy-Paste Ready
═══════════════════════════════════════════════════════════════════

INSTRUCTIONS:
1. BACKUP your current messaging.php
2. REPLACE your entire messaging.php file with this code
3. DELETE styles/messaging.css (styles are now embedded)
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

// Get conversations (users who have messaged with current user)
$conversations_query = "SELECT DISTINCT 
    CASE 
        WHEN m.sender_id = $current_user_id THEN m.receiver_id 
        ELSE m.sender_id 
    END as other_user_id,
    u.username,
    u.profile_picture_url,
    MAX(m.sent_at) as last_message_time,
    (SELECT message_text FROM messages 
     WHERE ((sender_id = $current_user_id AND receiver_id = (CASE WHEN m.sender_id = $current_user_id THEN m.receiver_id ELSE m.sender_id END)) 
            OR (sender_id = (CASE WHEN m.sender_id = $current_user_id THEN m.receiver_id ELSE m.sender_id END) AND receiver_id = $current_user_id))
     ORDER BY sent_at DESC LIMIT 1) as last_message,
    (SELECT COUNT(*) FROM messages 
     WHERE sender_id = (CASE WHEN m.sender_id = $current_user_id THEN m.receiver_id ELSE m.sender_id END) 
     AND receiver_id = $current_user_id 
     AND is_read = 0) as unread_count
FROM messages m
JOIN users u ON (
    CASE 
        WHEN m.sender_id = $current_user_id THEN m.receiver_id 
        ELSE m.sender_id 
    END = u.ID
)
WHERE m.sender_id = $current_user_id OR m.receiver_id = $current_user_id
GROUP BY other_user_id, u.username, u.profile_picture_url
ORDER BY last_message_time DESC";

$conversations = mysqli_query($connections, $conversations_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - RENTayo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ═══════════════════════════════════════════════════════════════
           MODERN MESSAGING STYLES - Blue Gradient Theme
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
            color: #1e293b;
        }

        .main {
            padding-top: 0;
        }

        /* ═══════════════════════════════════════════════════════════════
           MESSAGING CONTAINER
           ═══════════════════════════════════════════════════════════ */

        .messaging-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 24px;
            height: calc(100vh - 140px);
        }

        /* ═══════════════════════════════════════════════════════════════
           CONVERSATIONS LIST
           ═══════════════════════════════════════════════════════════ */

        .conversations-list {
            background: #fff;
            border-radius: 20px;
            box-shadow: 
                0 4px 6px -1px rgba(0, 0, 0, 0.1),
                0 2px 4px -1px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border: 1px solid #e2e8f0;
        }

        .conversation-item {
            padding: 16px 20px;
            display: flex;
            gap: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            border-bottom: 1px solid #f1f5f9;
        }

        .conversation-item:first-child {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #6366f1 100%);
            color: #fff;
            cursor: default;
            padding: 20px;
        }

        .conversation-item:first-child .conversation-name {
            font-size: 24px;
            font-weight: 700;
            color: #fff;
        }

        .conversation-item:not(:first-child):hover {
            background: #f8fafc;
        }

        .conversation-item:not(:first-child):active {
            background: #f1f5f9;
        }

        .conversation-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            border: 3px solid #e2e8f0;
        }

        .conversation-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            color: #fff;
        }

        .conversation-content {
            flex: 1;
            min-width: 0;
        }

        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .conversation-name {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .items-count {
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            color: #fff;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 12px;
            font-weight: 700;
            min-width: 20px;
            text-align: center;
            flex-shrink: 0;
        }

        .last-message {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .discussed-items {
            font-size: 12px;
            color: #94a3b8;
        }

        /* Empty State */
        .no-conversation {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }

        .no-conversation h3 {
            font-size: 20px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 8px;
        }

        .no-conversation p {
            font-size: 15px;
            color: #94a3b8;
            line-height: 1.6;
        }

        /* ═══════════════════════════════════════════════════════════════
           CHAT AREA (Empty State)
           ═══════════════════════════════════════════════════════════ */

        .chat-area {
            background: #fff;
            border-radius: 20px;
            box-shadow: 
                0 4px 6px -1px rgba(0, 0, 0, 0.1),
                0 2px 4px -1px rgba(0, 0, 0, 0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e2e8f0;
            position: relative;
            overflow: hidden;
        }

        .chat-area::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .chat-area .no-conversation {
            position: relative;
            z-index: 1;
        }

        /* ═══════════════════════════════════════════════════════════════
           RESPONSIVE DESIGN
           ═══════════════════════════════════════════════════════════ */

        @media (max-width: 1024px) {
            .messaging-container {
                grid-template-columns: 350px 1fr;
                gap: 20px;
            }
        }

        @media (max-width: 768px) {
            .messaging-container {
                grid-template-columns: 1fr;
                height: auto;
                margin: 20px auto;
            }

            .chat-area {
                display: none;
            }

            .conversations-list {
                max-height: calc(100vh - 160px);
                overflow-y: auto;
            }

            .conversation-avatar {
                width: 48px;
                height: 48px;
            }

            .avatar-placeholder {
                font-size: 20px;
            }
        }

        /* Scrollbar Styling */
        .conversations-list::-webkit-scrollbar {
            width: 8px;
        }

        .conversations-list::-webkit-scrollbar-track {
            background: #f8fafc;
        }

        .conversations-list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .conversations-list::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Loading Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .conversation-item:not(:first-child) {
            animation: fadeIn 0.3s ease;
            animation-fill-mode: both;
        }

        .conversation-item:nth-child(2) { animation-delay: 0.05s; }
        .conversation-item:nth-child(3) { animation-delay: 0.1s; }
        .conversation-item:nth-child(4) { animation-delay: 0.15s; }
        .conversation-item:nth-child(5) { animation-delay: 0.2s; }
    </style>
</head>
<body>
    <?php include("navbar.php"); ?>
    
    <div class="main">
        <div class="messaging-container">
            <!-- Conversations List -->
            <div class="conversations-list">
                <!-- Header -->
                <div class="conversation-item">
                    <div class="conversation-content">
                        <div class="conversation-header">
                            <div class="conversation-name">Messages</div>
                        </div>
                    </div>
                </div>
                
                <!-- Conversation Items -->
                <?php if (mysqli_num_rows($conversations) > 0): ?>
                    <?php while($conversation = mysqli_fetch_assoc($conversations)): ?>
                        <div class="conversation-item" onclick="window.location.href='chat.php?user_id=<?php echo $conversation['other_user_id']; ?>'">
                            <div class="conversation-avatar">
                                <?php if ($conversation['profile_picture_url'] && file_exists($conversation['profile_picture_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($conversation['profile_picture_url']); ?>" alt="Profile">
                                <?php else: ?>
                                    <div class="avatar-placeholder">
                                        <?php echo strtoupper(substr($conversation['username'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="conversation-content">
                                <div class="conversation-header">
                                    <div class="conversation-name">
                                        <?php echo htmlspecialchars($conversation['username']); ?>
                                    </div>
                                    <?php if ($conversation['unread_count'] > 0): ?>
                                        <div class="items-count"><?php echo $conversation['unread_count']; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="last-message">
                                    <?php 
                                    $last_msg = $conversation['last_message'] ?? 'No messages yet';
                                    echo htmlspecialchars(substr($last_msg, 0, 50));
                                    if (strlen($last_msg) > 50) echo '...';
                                    ?>
                                </div>
                                <?php if ($conversation['last_message_time']): ?>
                                    <div class="discussed-items">
                                        <?php echo date('M j, g:i A', strtotime($conversation['last_message_time'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-conversation">
                        <div>
                            <h3>No conversations yet</h3>
                            <p>Start a conversation by messaging someone from an item listing!</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Chat Area (Empty State) -->
            <div class="chat-area">
                <div class="no-conversation">
                    <div>
                        <h3>Select a conversation to start chatting</h3>
                        <p>Choose a conversation from the left to view and send messages</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
