<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function llRequest(string $endpoint, string $method = 'GET', $body = null, array $headers = []): array {
    $url = rtrim(GAME_URL, '/') . '/' . ltrim($endpoint, '/');
    $ch = curl_init($url);

    $defaultHeaders = [
        'Accept: application/json',
        'Content-Type: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    ];

    $allHeaders = array_merge($defaultHeaders, $headers);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, strpos($endpoint, '/send') !== false ? 35 : 12);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
        }
    } elseif (strtoupper($method) === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
        }
    }

    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'error' => $error ?: 'Network connection error'];
    }

    $headerStr = substr($raw, 0, $headerSize);
    $bodyStr   = substr($raw, $headerSize);

    $json = json_decode($bodyStr, true);
    if ($json === null) {
        $json = ['raw' => $bodyStr];
    }

    return [
        'ok'       => $httpCode >= 200 && $httpCode < 300,
        'code'     => $httpCode,
        'headers'  => $headerStr,
        'data'     => $json,
    ];
}

function llLogin(string $username, string $password): array {
    $res = llRequest('/api/auth/login', 'POST', [
        'username' => trim($username),
        'password' => trim($password),
    ]);

    if (!$res['ok'] || empty($res['data']['ok'])) {
        $errMsg = $res['data']['error'] ?? $res['data']['message'] ?? 'Invalid username or password';
        return ['ok' => false, 'error' => $errMsg];
    }

    // Extract ll_session cookie
    $cookie = '';
    if (preg_match('/ll_session=([^;]+)/i', $res['headers'], $m)) {
        $cookie = 'll_session=' . $m[1];
    }

    $userId = $res['data']['user']['id'] ?? '';
    $name   = $res['data']['user']['name'] ?? $username;

    // Fetch /api/auth/me to get exact user profile & player ID
    if (!empty($cookie)) {
        $meRes = llRequest('/api/auth/me', 'GET', null, ['Cookie: ' . $cookie]);
        if ($meRes['ok'] && !empty($meRes['data']['user']['id'])) {
            $userId = (string)$meRes['data']['user']['id'];
            if (!empty($meRes['data']['user']['name'])) {
                $name = (string)$meRes['data']['user']['name'];
            }
        }
    }

    return [
        'ok'     => true,
        'cookie' => $cookie,
        'userId' => $userId,
        'name'   => $name,
    ];
}

function requestAsUser(string $chatId, string $endpoint, string $method = 'GET', $body = null): array {
    $db = getDb();
    $stmt = $db->prepare("SELECT ll_session_cookie FROM bot_users WHERE chat_id = ? AND is_authenticated = 1");
    $stmt->execute([$chatId]);
    $row = $stmt->fetch();

    if (!$row || empty($row['ll_session_cookie'])) {
        return ['ok' => false, 'error' => 'Not logged in'];
    }

    return llRequest($endpoint, $method, $body, ['Cookie: ' . $row['ll_session_cookie']]);
}

function getBalance(string $chatId): ?int {
    $res = requestAsUser($chatId, '/api/save?_t=' . time());
    if ($res['ok'] && isset($res['data']['game']['money'])) {
        return (int)$res['data']['game']['money'];
    }
    if ($res['ok'] && isset($res['data']['money'])) {
        return (int)$res['data']['money'];
    }
    return null;
}

function getProfile(string $chatId): ?array {
    $res = requestAsUser($chatId, '/api/auth/me');
    if ($res['ok'] && !empty($res['data']['user']['id'])) {
        return [
            'id'       => (string)$res['data']['user']['id'],
            'username' => $res['data']['user']['username'] ?? '',
            'name'     => $res['data']['user']['name'] ?? '',
        ];
    }
    return null;
}

function resolvePlayer(string $chatId, string $username): ?array {
    $clean = ltrim(trim($username), '@');
    if ($clean === '') return null;

    if (strtolower($clean) === strtolower(LLBM_MOVEMENT_USERNAME)) {
        return [
            'id'       => LLBM_MOVEMENT_PLAYER_ID,
            'username' => LLBM_MOVEMENT_USERNAME,
            'name'     => LLBM_MOVEMENT_NAME,
        ];
    }

    $res = requestAsUser($chatId, '/api/players?q=' . urlencode($clean));
    if ($res['ok'] && !empty($res['data']['players']) && is_array($res['data']['players'])) {
        foreach ($res['data']['players'] as $p) {
            if (strtolower($p['username'] ?? '') === strtolower($clean) || strtolower($p['name'] ?? '') === strtolower($clean)) {
                return [
                    'id'       => (string)$p['id'],
                    'username' => $p['username'] ?? $clean,
                    'name'     => $p['name'] ?? $clean,
                ];
            }
        }
        $hit = $res['data']['players'][0];
        if (!empty($hit['id'])) {
            return [
                'id'       => (string)$hit['id'],
                'username' => $hit['username'] ?? $clean,
                'name'     => $hit['name'] ?? $clean,
            ];
        }
    }

    // Fallback: check /api/messages threads
    $msgRes = requestAsUser($chatId, '/api/messages');
    if ($msgRes['ok'] && !empty($msgRes['data']['threads']) && is_array($msgRes['data']['threads'])) {
        foreach ($msgRes['data']['threads'] as $t) {
            if (strtolower($t['username'] ?? '') === strtolower($clean) || strtolower($t['name'] ?? '') === strtolower($clean)) {
                return [
                    'id'       => (string)$t['id'],
                    'username' => $t['username'] ?? $clean,
                    'name'     => $t['name'] ?? $clean,
                ];
            }
        }
    }

    return null;
}

function sendMoney(string $chatId, string $recipientPlayerId, int $amount, string $note, ?string $pin = null): array {
    $body = [
        'to'     => $recipientPlayerId,
        'amount' => $amount,
        'note'   => $note,
    ];
    if (!empty($pin)) {
        $body['pin'] = $pin;
    }

    $res = requestAsUser($chatId, '/api/send', 'POST', $body);
    if ($res['ok'] && empty($res['data']['error']) && ($res['data']['ok'] ?? true) !== false) {
        return ['ok' => true, 'txId' => $res['data']['txId'] ?? $res['data']['id'] ?? ''];
    }

    $err = $res['data']['error'] ?? $res['data']['message'] ?? ('HTTP ' . ($res['code'] ?? 'Failed'));
    return ['ok' => false, 'error' => $err];
}
