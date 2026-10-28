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
            account_id VARCHAR(64) PRIMARY KEY,
            chat_id VARCHAR(64) NOT NULL,
            tg_username VARCHAR(128) NULL,
            tg_first_name VARCHAR(128) NULL,
            ll_username VARCHAR(128) NOT NULL,
            ll_session_cookie TEXT NULL,
            ll_user_id VARCHAR(128) NULL,
            ll_name VARCHAR(128) NULL,
            pin VARCHAR(64) NULL,
            quilox_enabled TINYINT(1) DEFAULT 0,
            quilox_total_picked BIGINT DEFAULT 0,
            quilox_last_picked DATETIME NULL,
            is_authenticated TINYINT(1) DEFAULT 1,
            authenticated_at DATETIME NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uniq_ll_username (ll_username),
            INDEX idx_chat_id (chat_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Ensure columns exist if table was created previously
    try {
        $cols = $db->query("SHOW COLUMNS FROM bot_users")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('account_id', $cols)) {
            $db->exec("ALTER TABLE bot_users ADD COLUMN account_id VARCHAR(64) NULL FIRST");
            $db->exec("UPDATE bot_users SET account_id = CONCAT('acc_', LOWER(REPLACE(ll_username, ' ', ''))) WHERE (account_id IS NULL OR account_id = '') AND ll_username IS NOT NULL AND ll_username != ''");
            $db->exec("UPDATE bot_users SET account_id = CONCAT('acc_', chat_id) WHERE account_id IS NULL OR account_id = ''");
        }
        if (!in_array('quilox_enabled', $cols)) {
            $db->exec("ALTER TABLE bot_users ADD COLUMN quilox_enabled TINYINT(1) DEFAULT 0");
        }
        if (!in_array('quilox_total_picked', $cols)) {
            $db->exec("ALTER TABLE bot_users ADD COLUMN quilox_total_picked BIGINT DEFAULT 0");
        }
        if (!in_array('quilox_last_picked', $cols)) {
            $db->exec("ALTER TABLE bot_users ADD COLUMN quilox_last_picked DATETIME NULL");
        }
    } catch (Exception $e) {}

    // Ensure authorized_users table exists for Anti-Distribution Security Gate
    $db->exec("
        CREATE TABLE IF NOT EXISTS authorized_users (
            chat_id VARCHAR(64) PRIMARY KEY,
            tg_username VARCHAR(128) NULL,
            tg_first_name VARCHAR(128) NULL,
            status VARCHAR(32) DEFAULT 'pending',
            master_unlocked TINYINT(1) DEFAULT 0,
            active_account VARCHAR(128) NULL,
            approved_by VARCHAR(64) NULL,
            requested_at DATETIME NULL,
            approved_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    try {
        $cols = $db->query("SHOW COLUMNS FROM authorized_users")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('master_unlocked', $cols)) {
            $db->exec("ALTER TABLE authorized_users ADD COLUMN master_unlocked TINYINT(1) DEFAULT 0");
        }
        if (!in_array('active_account', $cols)) {
            $db->exec("ALTER TABLE authorized_users ADD COLUMN active_account VARCHAR(128) NULL");
        }
    } catch (Exception $e) {}

    // Master Admin (HeisDaemon) is permanently pre-approved
    if (defined('MASTER_ADMIN_ID') && MASTER_ADMIN_ID) {
        $db->exec("
            INSERT INTO authorized_users (chat_id, tg_username, tg_first_name, status, approved_at)
            VALUES ('" . MASTER_ADMIN_ID . "', 'sinnerzadmin', 'HeisDaemon', 'approved', NOW())
            ON DUPLICATE KEY UPDATE status = 'approved';
        ");
    }

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

function isUserAuthorized(?PDO $db = null, string $chatId = ''): bool {
    if (defined('MASTER_ADMIN_ID') && (string)$chatId === (string)MASTER_ADMIN_ID) {
        return true;
    }
    if (!$db) {
        $db = getDb();
    }
    $stmt = $db->prepare("SELECT status FROM authorized_users WHERE chat_id = ?");
    $stmt->execute([$chatId]);
    $status = $stmt->fetchColumn();
    return $status === 'approved';
}

function getUserAuthStatus(?PDO $db = null, string $chatId = ''): string {
    if (defined('MASTER_ADMIN_ID') && (string)$chatId === (string)MASTER_ADMIN_ID) {
        return 'approved';
    }
    if (!$db) {
        $db = getDb();
    }
    $stmt = $db->prepare("SELECT status FROM authorized_users WHERE chat_id = ?");
    $stmt->execute([$chatId]);
    $status = $stmt->fetchColumn();
    return $status ? (string)$status : 'unregistered';
}

function isBotMasterUnlocked(?PDO $db = null, string $chatId = ''): bool {
    if (!$chatId) return false;
    if (!$db) $db = getDb();
    try {
        $stmt = $db->prepare("SELECT master_unlocked FROM authorized_users WHERE chat_id = ?");
        $stmt->execute([$chatId]);
        $val = $stmt->fetchColumn();
        return (int)$val === 1;
    } catch (Exception $e) {
        return false;
    }
}

function setBotMasterUnlocked(?PDO $db = null, string $chatId = '', bool $unlocked = true): void {
    if (!$chatId) return;
    if (!$db) $db = getDb();
    try {
        $val = $unlocked ? 1 : 0;
        $db->prepare("INSERT INTO authorized_users (chat_id, status, master_unlocked, approved_at) VALUES (?, 'approved', ?, NOW()) ON DUPLICATE KEY UPDATE master_unlocked = ?")
           ->execute([$chatId, $val, $val]);
    } catch (Exception $e) {}
}

function getActiveBotUser(?PDO $db = null, string $chatId = ''): ?array {
    if (!$chatId) return null;
    if (!$db) $db = getDb();
    
    // 1. Check active_account in authorized_users
    try {
        $stmt = $db->prepare("SELECT active_account FROM authorized_users WHERE chat_id = ?");
        $stmt->execute([$chatId]);
        $activeAcc = $stmt->fetchColumn();
        if ($activeAcc) {
            $uStmt = $db->prepare("SELECT * FROM bot_users WHERE (account_id = ? OR ll_username = ?) AND is_authenticated = 1 LIMIT 1");
            $uStmt->execute([$activeAcc, $activeAcc]);
            $user = $uStmt->fetch();
            if ($user) return $user;
        }
    } catch (Exception $e) {}

    // 2. Fallback to latest authenticated account connected under this chat_id
    $uStmt = $db->prepare("SELECT * FROM bot_users WHERE chat_id = ? AND is_authenticated = 1 ORDER BY updated_at DESC LIMIT 1");
    $uStmt->execute([$chatId]);
    $user = $uStmt->fetch();
    if ($user) return $user;

    // 3. Fallback to any authenticated account in the fleet
    $anyStmt = $db->query("SELECT * FROM bot_users WHERE is_authenticated = 1 ORDER BY updated_at DESC LIMIT 1");
    $anyUser = $anyStmt->fetch();
    if ($anyUser) return $anyUser;

    return null;
}

function setActiveBotUser(?PDO $db = null, string $chatId = '', string $accountIdentifier = ''): void {
    if (!$chatId) return;
    if (!$db) $db = getDb();
    try {
        $db->prepare("UPDATE authorized_users SET active_account = ? WHERE chat_id = ?")
           ->execute([$accountIdentifier, $chatId]);
    } catch (Exception $e) {}
}
