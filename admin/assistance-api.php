<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/support_chat.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$conn = getDBConnection();
support_chat_ensure_tables($conn);

function assistance_verify_csrf_or_json_error() {
    initSession();
    $valid = isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    if ($valid) {
        return;
    }

    http_response_code(403);
    echo json_encode(['error' => 'Invalid or missing security token. Please refresh the page and try again.']);
    exit;
}

function assistance_support_cutoff_id(PDO $conn, array $conversation) {
    if (strpos((string)($conversation['session_key'] ?? ''), 'contact:') === 0) {
        return 0;
    }

    $stmt = $conn->prepare("SELECT MAX(id) FROM support_messages WHERE conversation_id = ? AND sender_type = 'system' AND message_text LIKE 'Your message has been passed to support.%'");
    $stmt->execute([(int)$conversation['id']]);
    return (int)$stmt->fetchColumn();
}

function assistance_last_close_id(PDO $conn, array $conversation) {
    if (strpos((string)($conversation['session_key'] ?? ''), 'contact:') === 0) {
        return 0;
    }

    $stmt = $conn->prepare("SELECT MAX(id) FROM support_messages WHERE conversation_id = ? AND sender_type = 'system' AND (message_text = 'An admin has closed this conversation.' OR message_text = 'Customer has ended this conversation.')");
    $stmt->execute([(int)$conversation['id']]);
    return (int)$stmt->fetchColumn();
}

function assistance_visible_messages(PDO $conn, array $conversation, $sinceId = 0, $limit = 200) {
    $cutoffId = assistance_support_cutoff_id($conn, $conversation);
    $closeId = assistance_last_close_id($conn, $conversation);
    if ($closeId >= $cutoffId && strpos((string)($conversation['session_key'] ?? ''), 'contact:') !== 0) {
        return [];
    }
    $messages = support_chat_get_messages($conn, (int)$conversation['id'], $limit, max((int)$sinceId, $cutoffId));

    return array_values(array_filter($messages, function ($message) {
        return in_array((string)($message['sender_type'] ?? ''), ['customer', 'admin'], true);
    }));
}

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $stmt = $conn->query("SELECT c.id, c.status, c.user_id, c.session_key, c.assigned_admin_id, c.updated_at,
                          u.name AS customer_name, u.email AS customer_email
                          FROM support_conversations c
                          LEFT JOIN users u ON c.user_id = u.id
                          WHERE c.status = 'pending_admin'
                             OR c.session_key LIKE 'contact:%'
                          ORDER BY c.updated_at DESC
                          LIMIT 200");
    $rows = $stmt->fetchAll();
    $rows = array_values(array_filter($rows, function ($row) use ($conn) {
        if (strpos((string)($row['session_key'] ?? ''), 'contact:') === 0) {
            return true;
        }
        if (($row['status'] ?? '') === 'pending_admin') {
            return true;
        }
        return !empty(assistance_visible_messages($conn, $row, 0, 1));
    }));
    foreach ($rows as &$row) {
        $visibleMessages = assistance_visible_messages($conn, $row, 0, 200);
        $lastMessage = $visibleMessages ? end($visibleMessages) : null;
        $row['last_message'] = $lastMessage['message_text'] ?? null;
        $row['last_sender'] = $lastMessage['sender_type'] ?? null;
        $row['last_message_at'] = $lastMessage['created_at'] ?? null;
    }
    unset($row);
    echo json_encode(['conversations' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'messages') {
    $conversationId = (int)($_GET['conversation_id'] ?? 0);
    if ($conversationId <= 0) {
        http_response_code(422);
        echo json_encode(['error' => 'conversation_id is required']);
        exit;
    }

    $sinceId = (int)($_GET['since_id'] ?? 0);

    $conversationStmt = $conn->prepare("SELECT * FROM support_conversations WHERE id = ? LIMIT 1");
    $conversationStmt->execute([$conversationId]);
    $conversation = $conversationStmt->fetch();
    if (!$conversation) {
        http_response_code(404);
        echo json_encode(['error' => 'Conversation not found']);
        exit;
    }

    $messages = assistance_visible_messages($conn, $conversation, $sinceId, 200);
    echo json_encode([
        'conversation' => $conversation,
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
    assistance_verify_csrf_or_json_error();

    $conversationId = (int)($_POST['conversation_id'] ?? 0);
    $text = trim((string)($_POST['message'] ?? ''));

    if ($conversationId <= 0) {
        http_response_code(422);
        echo json_encode(['error' => 'conversation_id is required']);
        exit;
    }
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

    $conversationStmt = $conn->prepare("SELECT * FROM support_conversations WHERE id = ? LIMIT 1");
    $conversationStmt->execute([$conversationId]);
    $conversation = $conversationStmt->fetch();
    if (!$conversation) {
        http_response_code(404);
        echo json_encode(['error' => 'Conversation not found']);
        exit;
    }
    if ($conversation['status'] === 'closed') {
        $conn->prepare("UPDATE support_conversations SET status = 'open', assigned_admin_id = ?, updated_at = NOW() WHERE id = ?")
            ->execute([(int)getUserId(), $conversationId]);
        $conversation['status'] = 'open';
        $conversation['assigned_admin_id'] = (int)getUserId();
    }

    $insert = $conn->prepare("INSERT INTO support_messages (conversation_id, sender_type, sender_user_id, message_text) VALUES (?, 'admin', ?, ?)");
    $insert->execute([$conversationId, (int)getUserId(), $text]);

    $conn->prepare("UPDATE support_conversations SET status = 'pending_admin', assigned_admin_id = ?, updated_at = NOW() WHERE id = ?")
        ->execute([(int)getUserId(), $conversationId]);

    $msgId = (int)$conn->lastInsertId();
    $messages = assistance_visible_messages($conn, $conversation, max(0, $msgId - 5), 5);

    echo json_encode([
        'ok' => true,
        'messages' => $messages,
        'last_id' => $msgId
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'close') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    assistance_verify_csrf_or_json_error();

    $conversationId = (int)($_POST['conversation_id'] ?? 0);
    if ($conversationId <= 0) {
        http_response_code(422);
        echo json_encode(['error' => 'conversation_id is required']);
        exit;
    }

    $update = $conn->prepare("UPDATE support_conversations SET status = 'closed', updated_at = NOW(), assigned_admin_id = NULL WHERE id = ?");
    $update->execute([$conversationId]);

    $notice = 'An admin has closed this conversation.';
    $insert = $conn->prepare("INSERT INTO support_messages (conversation_id, sender_type, sender_user_id, message_text) VALUES (?, 'system', NULL, ?)");
    $insert->execute([$conversationId, $notice]);

    echo json_encode(['ok' => true, 'message' => $notice], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Unknown action']);
