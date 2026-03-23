<?php
require_once __DIR__ . '/support_chat.php';
require_once __DIR__ . '/functions.php';

initSession();
$conn = getDBConnection();
support_chat_ensure_tables($conn);

function chatbot_filter_visible_messages(array $messages, array $conversation) {
    $isHumanSupport = (($conversation['status'] ?? '') === 'pending_admin') || !empty($conversation['assigned_admin_id']);
    if (!$isHumanSupport) {
        return $messages;
    }

    return array_values(array_filter($messages, function ($message) {
        $sender = (string)($message['sender_type'] ?? '');
        if (in_array($sender, ['customer', 'admin'], true)) {
            return true;
        }

        $text = (string)($message['message_text'] ?? '');
        return strpos($text, 'passed to support') !== false
            || strpos($text, 'reply here shortly') !== false
            || strpos($text, 'closed this conversation') !== false
            || strpos($text, 'ended this conversation') !== false;
    }));
}

$sessionKey = isLoggedIn() ? ('user:' . (int)getUserId()) : ('guest:' . getCartSessionId());

// API mode
$action = $_GET['action'] ?? null;
if ($action) {
    header('Content-Type: application/json');

    $conversation = support_chat_get_or_create_conversation(
        $conn,
        isLoggedIn() ? (int)getUserId() : null,
        $sessionKey
    );
    $conversationId = (int)$conversation['id'];

    if ($action === 'messages') {
        $sinceId = (int)($_GET['since_id'] ?? 0);
        $messages = chatbot_filter_visible_messages(
            support_chat_get_messages($conn, $conversationId, 200, $sinceId),
            $conversation
        );
        echo json_encode([
            'conversation_id' => $conversationId,
            'status' => (string)$conversation['status'],
            'messages' => $messages
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'send') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $text = trim((string)($_POST['message'] ?? ''));
        if ($text === '') {
            http_response_code(422);
            echo json_encode(['error' => 'Message is required']);
            exit;
        }
        if (mb_strlen($text) > 1000) {
            http_response_code(422);
            echo json_encode(['error' => 'Message is too long']);
            exit;
        }

        $stmt = $conn->prepare("SELECT status FROM support_conversations WHERE id = ? LIMIT 1");
        $stmt->execute([$conversationId]);
        $row = $stmt->fetch();
        if ($row && $row['status'] === 'closed') {
            $conn->prepare("UPDATE support_conversations SET status = 'open', assigned_admin_id = NULL, updated_at = NOW() WHERE id = ?")
                ->execute([$conversationId]);
            $row['status'] = 'open';
        }

        $msgId = support_chat_add_message($conn, $conversationId, 'customer', isLoggedIn() ? (int)getUserId() : null, $text);
        $latestStatus = (string)($row['status'] ?? 'open');

        if ($latestStatus === 'pending_admin') {
            $conn->prepare("UPDATE support_conversations SET updated_at = NOW() WHERE id = ?")
                ->execute([$conversationId]);
        } else {
            $botResult = support_chat_build_bot_reply($conn, $text, isLoggedIn() ? (int)getUserId() : null);
            support_chat_add_message($conn, $conversationId, 'system', null, $botResult['reply']);

            if (!empty($botResult['handoff'])) {
                $conn->prepare("UPDATE support_conversations SET status = 'pending_admin', assigned_admin_id = NULL, updated_at = NOW() WHERE id = ?")
                    ->execute([$conversationId]);
            } else {
                $conn->prepare("UPDATE support_conversations SET status = 'open', assigned_admin_id = NULL, updated_at = NOW() WHERE id = ?")
                    ->execute([$conversationId]);
            }
        }

        $messages = support_chat_get_messages($conn, $conversationId, 5, max(0, $msgId - 5));

        echo json_encode([
            'ok' => true,
            'messages' => $messages,
            'last_id' => $msgId
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'request_support') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $stmt = $conn->prepare("SELECT status FROM support_conversations WHERE id = ? LIMIT 1");
        $stmt->execute([$conversationId]);
        $row = $stmt->fetch();
        if ($row && $row['status'] === 'closed') {
            $conn->prepare("UPDATE support_conversations SET status = 'open', assigned_admin_id = NULL, updated_at = NOW() WHERE id = ?")
                ->execute([$conversationId]);
        }

        $reason = trim((string)($_POST['reason'] ?? 'Customer asked to speak to support.'));
        $message = support_chat_request_human_support($conn, $conversationId, $reason);

        echo json_encode([
            'ok' => true,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'close') {
        $conn->prepare("UPDATE support_conversations SET status = 'closed', updated_at = NOW() WHERE id = ?")
            ->execute([$conversationId]);

        $notice = 'Customer has ended this conversation.';
        $conn->prepare("INSERT INTO support_messages (conversation_id, sender_type, sender_user_id, message_text) VALUES (?, 'system', NULL, ?)")
            ->execute([$conversationId, $notice]);

        echo json_encode(['ok' => true, 'message' => $notice], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

// UI render mode
$conversation = support_chat_get_or_create_conversation($conn, isLoggedIn() ? (int)getUserId() : null, $sessionKey);
$messages = chatbot_filter_visible_messages(
    support_chat_get_messages($conn, (int)$conversation['id'], 100),
    $conversation
);

$chatConfig = [
    'conversationId' => (int)$conversation['id'],
    'conversationStatus' => (string)$conversation['status'],
    'messages' => $messages,
    'isLoggedIn' => isLoggedIn(),
    'apiMessagesUrl' => url('includes/chatbot.php?action=messages'),
    'apiSendUrl' => url('includes/chatbot.php?action=send'),
    'apiSupportUrl' => url('includes/chatbot.php?action=request_support'),
    'apiCloseUrl' => url('includes/chatbot.php?action=close'),
];
?>

<style>
#laptro-chatbot {
    position: fixed !important;
    right: 20px !important;
    bottom: 20px !important;
    z-index: 9999 !important;
}

#laptro-chatbot-launcher {
    position: fixed !important;
    right: 20px !important;
    bottom: 20px !important;
    z-index: 10000 !important;
}

#laptro-chatbot-panel {
    position: fixed !important;
    right: 20px !important;
    bottom: 20px !important;
    z-index: 10000 !important;
}

@media (max-width: 767.98px) {
    #laptro-chatbot,
    #laptro-chatbot-launcher,
    #laptro-chatbot-panel {
        right: 12px !important;
        bottom: 12px !important;
    }
}
</style>

<div class="laptro-chatbot" id="laptro-chatbot">
    <button type="button" class="laptro-chatbot-launcher" id="laptro-chatbot-launcher" aria-label="Open help and support">
        <i class="fas fa-headset" aria-hidden="true"></i>
        <span>Need Help?</span>
    </button>

    <section class="laptro-chatbot-panel" id="laptro-chatbot-panel" aria-label="Help and support" aria-hidden="true">
        <header class="laptro-chatbot-header">
            <div>
                <strong>Help & Support</strong>
                <p>Ask a quick question or send a message to support.</p>
            </div>
            <button type="button" class="laptro-chatbot-close" id="laptro-chatbot-close" aria-label="Close chat">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </header>

        <div class="laptro-chatbot-messages" id="laptro-chatbot-messages" aria-live="polite"></div>
        <div class="laptro-chatbot-actions">
            <button type="button" class="laptro-chatbot-action" id="laptro-chatbot-product-help">Product Help</button>
            <button type="button" class="laptro-chatbot-action" id="laptro-chatbot-order-help">Order Help</button>
            <button type="button" class="laptro-chatbot-action laptro-chatbot-action-support" id="laptro-chatbot-support">Message Support</button>
        </div>

        <div class="laptro-chatbot-input-wrap">
            <input type="text" class="laptro-chatbot-input" id="laptro-chatbot-input" maxlength="500" placeholder="Ask about a product, stock, or your order...">
            <button type="button" class="laptro-chatbot-send" id="laptro-chatbot-send">Send</button>
        </div>
    </section>
</div>

<script type="application/json" id="laptro-support-config"><?php echo json_encode($chatConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
<?php $chatbotVersion = file_exists(__DIR__ . '/../js/chatbot.js') ? filemtime(__DIR__ . '/../js/chatbot.js') : time(); ?>
<script src="<?php echo url('js/chatbot.js?v=' . $chatbotVersion); ?>"></script>
