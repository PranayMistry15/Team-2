-- Support chat Schema Patch

CREATE TABLE IF NOT EXISTS support_conversations (
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
);

CREATE TABLE IF NOT EXISTS support_messages (
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
);
