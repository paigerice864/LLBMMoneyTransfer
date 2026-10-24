<?php
require_once __DIR__ . '/config.php';

function getDb(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        initTables($pdo);
        return $pdo;
    } catch (PDOException $e) {
        error_log("[DB Error] " . $e->getMessage());
        throw $e;
    }
}

function initTables(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS bot_users (
            chat_id VARCHAR(64) PRIMARY KEY,
            tg_username VARCHAR(128) NULL,
            tg_first_name VARCHAR(128) NULL,
            ll_username VARCHAR(128) NOT NULL,
            ll_session_cookie TEXT NULL,
            ll_user_id VARCHAR(128) NULL,
            ll_name VARCHAR(128) NULL,
            pin VARCHAR(64) NULL,
            is_authenticated TINYINT(1) DEFAULT 1,
            authenticated_at DATETIME NULL,
            updated_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS pending_transfers (
            chat_id VARCHAR(64) PRIMARY KEY,
            mode VARCHAR(32) NULL,
            step VARCHAR(32) NULL,
            recipient_username VARCHAR(128) NULL,
            recipient_player_id VARCHAR(128) NULL,
            amount BIGINT NULL,
            note VARCHAR(255) NULL,
            data MEDIUMTEXT NULL,
            updated_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS transfers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            chat_id VARCHAR(64) NOT NULL,
            sender_ll_username VARCHAR(128) NOT NULL,
            recipient_username VARCHAR(128) NOT NULL,
            recipient_player_id VARCHAR(128) NULL,
            amount BIGINT NOT NULL,
            note VARCHAR(255) NULL,
            status VARCHAR(32) NOT NULL,
            tx_response TEXT NULL,
            error_reason TEXT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_chat (chat_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS batch_jobs (
            id VARCHAR(64) PRIMARY KEY,
            chat_id VARCHAR(64) NOT NULL,
            sender_username VARCHAR(128) NOT NULL,
            mode VARCHAR(32) NOT NULL,
            total_chunks INT NOT NULL,
            completed_chunks INT DEFAULT 0,
            failed_chunks INT DEFAULT 0,
            total_amount BIGINT NOT NULL,
            sent_amount BIGINT DEFAULT 0,
            status VARCHAR(32) DEFAULT 'pending',
            progress_msg_id BIGINT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS batch_chunks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_id VARCHAR(64) NOT NULL,
            chunk_index INT NOT NULL,
            recipient_username VARCHAR(128) NOT NULL,
            recipient_player_id VARCHAR(128) NOT NULL,
            amount BIGINT NOT NULL,
            note VARCHAR(255) NULL,
            status VARCHAR(32) DEFAULT 'pending',
            error_reason TEXT NULL,
            processed_at DATETIME NULL,
            INDEX idx_job (job_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}
