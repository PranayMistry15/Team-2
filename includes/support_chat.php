<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/url-helper.php';

function support_chat_ensure_tables(PDO $conn) {
    $conn->exec("CREATE TABLE IF NOT EXISTS support_conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_key VARCHAR(190) NOT NULL,
        user_id INT NULL,
        status ENUM('open','pending_admin','closed') NOT NULL DEFAULT 'open',
        assigned_admin_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_support_conversations_session_key (session_key),
        KEY idx_support_conversations_user_id (user_id),
        KEY idx_support_conversations_status (status),
        CONSTRAINT fk_support_conversations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_support_conversations_admin FOREIGN KEY (assigned_admin_id) REFERENCES users(id) ON DELETE SET NULL
    )");

    $conn->exec("CREATE TABLE IF NOT EXISTS support_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        sender_type ENUM('customer','admin','system') NOT NULL,
        sender_user_id INT NULL,
        message_text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_support_messages_conversation_id (conversation_id),
        KEY idx_support_messages_created_at (created_at),
        CONSTRAINT fk_support_messages_conversation FOREIGN KEY (conversation_id) REFERENCES support_conversations(id) ON DELETE CASCADE,
        CONSTRAINT fk_support_messages_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL
    )");
}

function support_chat_get_or_create_conversation(PDO $conn, $userId, $sessionKey) {
    $stmt = $conn->prepare("SELECT * FROM support_conversations WHERE session_key = ? LIMIT 1");
    $stmt->execute([$sessionKey]);
    $conversation = $stmt->fetch();

    if ($conversation) {
        if ($userId && (int)$conversation['user_id'] !== (int)$userId) {
            $updateStmt = $conn->prepare("UPDATE support_conversations SET user_id = ? WHERE id = ?");
            $updateStmt->execute([(int)$userId, (int)$conversation['id']]);
            $conversation['user_id'] = (int)$userId;
        }
        return $conversation;
    }

    $createStmt = $conn->prepare("INSERT INTO support_conversations (session_key, user_id, status) VALUES (?, ?, 'open')");
    $createStmt->execute([$sessionKey, $userId ? (int)$userId : null]);
    $conversationId = (int)$conn->lastInsertId();

    $systemMessage = "Hi. You can ask here about products, stock, prices, or your orders. If you'd rather speak to someone, use Message Support.";
    $msgStmt = $conn->prepare("INSERT INTO support_messages (conversation_id, sender_type, sender_user_id, message_text) VALUES (?, 'system', NULL, ?)");
    $msgStmt->execute([$conversationId, $systemMessage]);

    $stmt = $conn->prepare("SELECT * FROM support_conversations WHERE id = ? LIMIT 1");
    $stmt->execute([$conversationId]);
    return $stmt->fetch();
}

function support_chat_get_messages(PDO $conn, $conversationId, $limit = 50, $sinceId = 0) {
    $limit = max(1, min(200, (int)$limit));
    $sinceId = max(0, (int)$sinceId);

    $sql = "SELECT id, conversation_id, sender_type, sender_user_id, message_text, created_at
            FROM support_messages
            WHERE conversation_id = ?";
    $params = [(int)$conversationId];

    if ($sinceId > 0) {
        $sql .= " AND id > ?";
        $params[] = $sinceId;
    }

    $sql .= " ORDER BY id ASC LIMIT " . $limit;

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function support_chat_create_contact_request(PDO $conn, array $payload) {
    support_chat_ensure_tables($conn);

    $name = trim((string)($payload['name'] ?? ''));
    $email = trim((string)($payload['email'] ?? ''));
    $subject = trim((string)($payload['subject'] ?? ''));
    $message = trim((string)($payload['message'] ?? ''));
    $orderId = trim((string)($payload['order_id'] ?? ''));
    $userId = !empty($payload['user_id']) ? (int)$payload['user_id'] : null;

    $sessionKey = 'contact:' . bin2hex(random_bytes(12));

    $createStmt = $conn->prepare("INSERT INTO support_conversations (session_key, user_id, status) VALUES (?, ?, 'pending_admin')");
    $createStmt->execute([$sessionKey, $userId]);
    $conversationId = (int)$conn->lastInsertId();

    $systemStmt = $conn->prepare("INSERT INTO support_messages (conversation_id, sender_type, sender_user_id, message_text) VALUES (?, 'system', NULL, ?)");
    $systemStmt->execute([$conversationId, 'New contact form request submitted via the website.']);

    $body = "Contact request\n"
        . "Subject: " . $subject . "\n"
        . "Name: " . $name . "\n"
        . "Email: " . $email . "\n"
        . "Order reference: " . ($orderId !== '' ? $orderId : 'Not provided') . "\n\n"
        . $message;

    $messageStmt = $conn->prepare("INSERT INTO support_messages (conversation_id, sender_type, sender_user_id, message_text) VALUES (?, 'customer', ?, ?)");
    $messageStmt->execute([$conversationId, $userId, $body]);

    return $conversationId;
}

function support_chat_add_message(PDO $conn, $conversationId, $senderType, $senderUserId, $messageText) {
    $stmt = $conn->prepare("INSERT INTO support_messages (conversation_id, sender_type, sender_user_id, message_text) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        (int)$conversationId,
        (string)$senderType,
        $senderUserId !== null ? (int)$senderUserId : null,
        (string)$messageText
    ]);
    return (int)$conn->lastInsertId();
}

function support_chat_normalize_text($text) {
    $text = strtolower(trim((string)$text));
    $text = preg_replace('/[^a-z0-9\s#-]+/', ' ', $text);
    return trim(preg_replace('/\s+/', ' ', $text));
}

function support_chat_product_candidates(PDO $conn) {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $stmt = $conn->query("SELECT id, name, brand, category, stock, price, cpu, ram, storage, gpu, screen_size, resolution, os FROM products ORDER BY name ASC");
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['_search'] = support_chat_normalize_text($row['name'] . ' ' . $row['brand'] . ' ' . $row['category']);
    }
    unset($row);

    $cache = $rows;
    return $cache;
}

function support_chat_match_products(PDO $conn, $message, $limit = 3) {
    $messageNorm = support_chat_normalize_text($message);
    if ($messageNorm === '') {
        return [];
    }

    $stopWords = [
        'a','an','and','are','availability','available','can','chatbot','do','for','have','hello','help','hi','i','in',
        'is','it','item','laptop','me','need','of','on','order','please','price','product','products','show','stock',
        'support','tell','the','there','to','want','what','with','you'
    ];
    $tokens = array_values(array_unique(array_filter(explode(' ', $messageNorm), function ($token) use ($stopWords) {
        return $token !== '' && !in_array($token, $stopWords, true) && strlen($token) >= 2;
    })));

    $scored = [];
    foreach (support_chat_product_candidates($conn) as $product) {
        $score = 0;
        foreach ($tokens as $token) {
            if (strpos($product['_search'], $token) !== false) {
                $score += strlen($token) >= 4 ? 3 : 1;
            }
        }
        if ($score > 0 || strpos($product['_search'], $messageNorm) !== false) {
            if (strpos($product['_search'], $messageNorm) !== false) {
                $score += 8;
            }
            $product['_score'] = $score;
            $scored[] = $product;
        }
    }

    usort($scored, function ($a, $b) {
        if ($a['_score'] === $b['_score']) {
            return strcmp($a['name'], $b['name']);
        }
        return $b['_score'] <=> $a['_score'];
    });

    if (count($scored) > 1 && $scored[0]['_score'] >= ($scored[1]['_score'] + 3)) {
        return [$scored[0]];
    }

    return array_slice($scored, 0, max(1, (int)$limit));
}

function support_chat_product_specs_text(array $product) {
    $parts = [];

    if (!empty($product['cpu'])) {
        $parts[] = "CPU: " . $product['cpu'];
    }
    if (!empty($product['ram'])) {
        $parts[] = "RAM: " . $product['ram'];
    }
    if (!empty($product['storage'])) {
        $parts[] = "Storage: " . $product['storage'];
    }
    if (!empty($product['gpu'])) {
        $parts[] = "GPU: " . $product['gpu'];
    }
    if (!empty($product['screen_size']) || !empty($product['resolution'])) {
        $screen = trim(($product['screen_size'] ?? '') . (!empty($product['resolution']) ? ' ' . $product['resolution'] : ''));
        if ($screen !== '') {
            $parts[] = "Screen: " . $screen;
        }
    }
    if (!empty($product['os'])) {
        $parts[] = "OS: " . $product['os'];
    }

    return implode('; ', $parts);
}

function support_chat_order_summary(PDO $conn, $userId, $orderId = null) {
    if (!$userId) {
        return null;
    }

    if ($orderId !== null) {
        $stmt = $conn->prepare("SELECT id, status, total_amount, created_at FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([(int)$orderId, (int)$userId]);
        return $stmt->fetch() ?: null;
    }

    $stmt = $conn->prepare("SELECT id, status, total_amount, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
    $stmt->execute([(int)$userId]);
    return $stmt->fetchAll();
}

function support_chat_request_human_support(PDO $conn, $conversationId, $reason = '') {
    $conn->prepare("UPDATE support_conversations SET status = 'pending_admin', updated_at = NOW() WHERE id = ?")
        ->execute([(int)$conversationId]);

    $message = "Your message has been passed to support. Someone from the team will reply here shortly.";
    if ($reason !== '') {
        $message .= "\nReason: " . $reason;
    }
    support_chat_add_message($conn, $conversationId, 'system', null, $message);
    return $message;
}

function support_chat_build_bot_reply(PDO $conn, $message, $userId = null) {
    $text = support_chat_normalize_text($message);
    if ($text === '') {
        return ['reply' => 'You can ask about products, stock, prices, categories, or your orders.', 'handoff' => false];
    }

    if (preg_match('/\b(human|agent|person|support|representative|someone)\b/', $text) && !preg_match('/\b(order|stock|price|product)\b/', $text)) {
        return [
            'reply' => "I can pass this to support now.",
            'handoff' => true
        ];
    }

    if (preg_match('/\b(hi|hello|hey)\b/', $text)) {
        return [
            'reply' => "Hi. I can help with product details, stock, categories, and order status. If you'd rather speak to support, use Message Support.",
            'handoff' => false
        ];
    }

    if (preg_match('/\b(order|delivery|shipment|shipping|track|tracking)\b/', $text)) {
        if (!$userId) {
            return [
                'reply' => "I can only check order details for logged-in customers. Please log in, or use Message Support if you need help.",
                'handoff' => false
            ];
        }

        if (preg_match('/#?(\d{1,10})/', $text, $m)) {
            $order = support_chat_order_summary($conn, $userId, (int)$m[1]);
            if ($order) {
                return [
                    'reply' => "Order #{$order['id']} is currently {$order['status']}. Total: " . formatPrice($order['total_amount']) . ". Placed on " . date('M d, Y', strtotime($order['created_at'])) . ".",
                    'handoff' => false
                ];
            }
            return [
                'reply' => "I couldn't find that order on your account. You can check My Orders in the dashboard, or send it to support for a closer look.",
                'handoff' => false
            ];
        }

        $orders = support_chat_order_summary($conn, $userId);
        if (!$orders) {
            return [
                'reply' => "I can't see any orders on your account yet.",
                'handoff' => false
            ];
        }

        $parts = [];
        foreach ($orders as $order) {
            $parts[] = "#" . $order['id'] . " is " . $order['status'];
        }
        return [
            'reply' => "Your latest orders: " . implode('; ', $parts) . ". If you want someone to check one for you, use Message Support.",
            'handoff' => false
        ];
    }

    if (preg_match('/\b(category|categories|gaming|business|portable|budget|performance|high speed)\b/', $text)) {
        return [
            'reply' => "The main categories are High Performance, Portable, Gaming, Business, and Budget Friendly. You can open the Products page and filter from there.",
            'handoff' => false
        ];
    }

    $matches = support_chat_match_products($conn, $message, 3);
    if ($matches) {
        $wantsStock = preg_match('/\b(stock|available|availability|in stock|out of stock)\b/', $text);
        $wantsPrice = preg_match('/\b(price|cost|how much)\b/', $text);
        $wantsSpecs = preg_match('/\b(spec|specs|specification|specifications|cpu|processor|ram|memory|storage|ssd|gpu|graphics|screen|display|resolution|os)\b/', $text);

        if (count($matches) === 1) {
            $product = $matches[0];
            $stockText = stock_status_meta((int)$product['stock'])['label'];
            $specsText = support_chat_product_specs_text($product);
            $reply = $product['name'] . " is in the " . ucfirst(str_replace('-', ' ', $product['category'])) . " category.";
            if ($wantsPrice || !$wantsStock) {
                $reply .= " Price: " . formatPrice($product['price']) . ".";
            }
            if ($wantsStock || !$wantsPrice) {
                $reply .= " Stock: " . $stockText . ".";
            }
            if ($specsText !== '') {
                $reply .= " Specs: " . $specsText . ".";
            }
            $reply .= " You can open the product page to see the full details.";
            return ['reply' => $reply, 'handoff' => false];
        }

        $lines = [];
        foreach ($matches as $product) {
            $line = $product['name'] . " (" . formatPrice($product['price']) . ", " . stock_status_meta((int)$product['stock'])['label'];
            if ($wantsSpecs) {
                $specsText = support_chat_product_specs_text($product);
                if ($specsText !== '') {
                    $line .= ", " . $specsText;
                }
            }
            $line .= ")";
            $lines[] = $line;
        }
        return [
            'reply' => "I found a few possible matches: " . implode('; ', $lines) . ". If you want, send the exact laptop name or use Message Support.",
            'handoff' => false
        ];
    }

    return [
        'reply' => "I can help with product names, prices, stock, categories, and your orders. If you'd rather speak to someone, use Message Support.",
        'handoff' => false
    ];
}
