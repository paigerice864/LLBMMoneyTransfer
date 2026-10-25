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

// ────────────────────────────────────────────────────────────────────────────
// House Beautification / Cloning Engine
// ────────────────────────────────────────────────────────────────────────────

function getHouseTemplates(): array {
    return [
        'wedekaov' => [
            'name'        => 'Banana Island Mega Mansion',
            'owner'       => 'wedekaov',
            'items'       => 153,
            'cost'        => 45000000,
            'description' => 'Cinema Hall, VIP Lounge, Spa Jacuzzi, Luxury Suites, Marble & Terrace',
            'file'        => __DIR__ . '/templates/wedekaov.json',
        ],
        'phinnybabe' => [
            'name'        => 'Lekki Water Palace',
            'owner'       => 'phinnybabe',
            'items'       => 153,
            'cost'        => 35000000,
            'description' => 'Olympic Pool, Grand Piano, Penthouse Jacuzzi, Sky Lounge',
            'file'        => __DIR__ . '/templates/phinnybabe.json',
        ],
        'kimoflele' => [
            'name'        => 'Victoria Island Penthouse',
            'owner'       => 'kimoflele',
            'items'       => 65,
            'cost'        => 20000000,
            'description' => 'Executive Cocktail Bar, Crystal Chandelier, Master Studio',
            'file'        => __DIR__ . '/templates/kimoflele.json',
        ],
        'ohayo' => [
            'name'        => 'Ikoyi Executive Modern Suite',
            'owner'       => 'ohayo',
            'items'       => 58,
            'cost'        => 15000000,
            'description' => 'Modern Minimalist Art, Workstation, Smart Home Decor',
            'file'        => __DIR__ . '/templates/ohayo.json',
        ],
    ];
}

function loadHouseTemplate(string $key): ?array {
    $templates = getHouseTemplates();
    if (!isset($templates[$key])) return null;
    $path = $templates[$key]['file'];
    if (!file_exists($path)) return null;
    $raw = file_get_contents($path);
    return json_decode($raw, true);
}

function applyHouseDesign(string $chatId, array $design, int $cost = 0): array {
    $saveRes = requestAsUser($chatId, '/api/save?_t=' . time());
    if (!$saveRes['ok'] || empty($saveRes['data']['game'])) {
        return ['ok' => false, 'error' => 'Could not fetch house data from LagosLife'];
    }

    $game = $saveRes['data']['game'];
    $owner = $game['owner'] ?? ($saveRes['data']['owner'] ?? null);
    $base = $saveRes['data']['updatedAt'] ?? null;
    $currentMoney = (int)($game['money'] ?? 0);

    if ($cost > 0 && $currentMoney < $cost) {
        return [
            'ok' => false,
            'error' => 'Insufficient funds. Required budget: ₦' . number_format($cost) . ', Balance: ₦' . number_format($currentMoney)
        ];
    }

    $rawObjects = $design['objects'] ?? [];
    if (!is_array($rawObjects) || empty($rawObjects)) {
        return ['ok' => false, 'error' => 'Design layout contains no furniture items'];
    }

    $sanitized = [];
    $maxX = 18;
    $maxY = 18;

    foreach ($rawObjects as $obj) {
        if (!is_array($obj) || empty($obj['type'])) continue;
        $floor = isset($obj['floor']) && (int)$obj['floor'] === 1 ? 1 : 0;
        $x = max(0, min($maxX, (int)($obj['x'] ?? 2)));
        $y = max(0, min($maxY, (int)($obj['y'] ?? 2)));

        if ($floor === 0 && $x <= 2 && $y >= 10 && $y <= 14) {
            $x = 3;
        }
        if ($floor === 1 && $x <= 1 && $y >= 8 && y <= 11) {
            $x = 2;
        }

        $item = [
            'type' => (string)$obj['type'],
            'x'    => $x,
            'y'    => $y,
            'rot'  => isset($obj['rot']) ? (int)$obj['rot'] : 0,
            'uid'  => substr(bin2hex(random_bytes(4)), 0, 8),
        ];
        if ($floor === 1) {
            $item['floor'] = 1;
        }
        if (!empty($obj['color'])) {
            $item['color'] = $obj['color'];
        }
        $sanitized[] = $item;
    }

    $game['objects'] = $sanitized;
    if (!empty($design['decor'])) {
        $game['decor'] = $design['decor'];
    }
    if (!empty($design['decorOwned'])) {
        $existingWall = $game['decorOwned']['wall'] ?? [];
        $existingFloor = $game['decorOwned']['floor'] ?? [];
        $newWall = $design['decorOwned']['wall'] ?? [];
        $newFloor = $design['decorOwned']['floor'] ?? [];
        $game['decorOwned'] = [
            'wall'  => array_values(array_unique(array_merge($existingWall, $newWall))),
            'floor' => array_values(array_unique(array_merge($existingFloor, $newFloor))),
        ];
    }

    if ($cost > 0) {
        $game['money'] = max(0, $currentMoney - $cost);
    }

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $putRes = requestAsUser($chatId, '/api/save', 'PUT', [
            'game'    => $game,
            'owner'   => $owner,
            'base'    => $base,
            'replace' => $base,
        ]);

        if ($putRes['ok'] && ($putRes['data']['ok'] ?? true) !== false) {
            return [
                'ok'         => true,
                'itemsCount' => count($sanitized),
                'cost'       => $cost,
                'newBalance' => $game['money'] ?? $currentMoney,
            ];
        }

        $fresh = requestAsUser($chatId, '/api/save?_t=' . time());
        if ($fresh['ok'] && !empty($fresh['data']['updatedAt'])) {
            $base = $fresh['data']['updatedAt'];
        }
        usleep(300000);
    }

    return ['ok' => false, 'error' => 'Failed to save updated house layout after 3 attempts'];
}

function inspectPlayerHouse(string $chatId, string $targetUsername): array {
    $clean = ltrim(trim($targetUsername), '@');
    $cleanLower = strtolower($clean);

    // 1. Check if design is already saved in local templates
    $tplPath = __DIR__ . '/templates/' . $cleanLower . '.json';
    if (file_exists($tplPath)) {
        $cached = json_decode(file_get_contents($tplPath), true);
        if ($cached && !empty($cached['objects'])) {
            return [
                'ok'       => true,
                'cached'   => true,
                'username' => $clean,
                'home'     => $cached['home'] ?? 'Property',
                'decor'    => $cached['decor'] ?? ['wall' => 'sky', 'floor' => 'blackMarble'],
                'objects'  => $cached['objects'],
                'count'    => count($cached['objects']),
            ];
        }
    }

    $player = resolvePlayer($chatId, $clean);
    if (!$player) {
        return ['ok' => false, 'error' => "Player @$clean was not found on LagosLife."];
    }

    $metaRes = requestAsUser($chatId, '/api/homes/u/' . urlencode($clean));
    if (!$metaRes['ok'] || empty($metaRes['data']['id'])) {
        return ['ok' => false, 'error' => "Could not locate property for @$clean."];
    }

    $homeMeta = $metaRes['data'];
    $homeId = (string)$homeMeta['id'];
    $homeName = $homeMeta['home'] ?? $homeMeta['houseName'] ?? 'Property';
    $area = $homeMeta['area'] ?? 'Lagos';

    // 2. Try direct entry first (if doors are unlocked or guest access enabled)
    $roomRes = requestAsUser($chatId, '/api/homes/' . urlencode($homeId));
    if ($roomRes['ok'] && (!empty($roomRes['data']['objects']) || !empty($roomRes['data']['up']))) {
        $room = $roomRes['data'];
        $objs = $room['objects'] ?? [];
        $upObjs = isset($room['up']) && is_array($room['up']) ? array_map(function($o) {
            $o['floor'] = 1;
            return $o;
        }, $room['up']) : [];
        $allObjs = array_merge($objs, $upObjs);

        // Cache for future instant use
        @file_put_contents($tplPath, json_encode([
            'home'    => $homeName,
            'decor'   => $room['decor'] ?? ['wall' => 'sky', 'floor' => 'blackMarble'],
            'objects' => $allObjs,
        ], JSON_PRETTY_PRINT));

        return [
            'ok'       => true,
            'username' => $clean,
            'home'     => $homeName,
            'decor'    => $room['decor'] ?? ['wall' => 'sky', 'floor' => 'blackMarble'],
            'objects'  => $allObjs,
            'count'    => count($allObjs),
        ];
    }

    // 3. Front door is closed: Knock on their front door!
    $knockRes = requestAsUser($chatId, '/api/homes/' . urlencode($homeId) . '/knock', 'POST', []);
    $knockErr = strtolower($knockRes['data']['error'] ?? $knockRes['data']['message'] ?? '');

    @file_put_contents(__DIR__ . '/house_inspect.log', "[" . date('Y-m-d H:i:s') . "] Target: $clean | Code: {$knockRes['code']} | Resp: " . json_encode($knockRes['data'] ?? []) . PHP_EOL, FILE_APPEND);

    // Only flag as not_home if LagosLife explicitly returns that they are not home or away
    $isNotHome = (strpos($knockErr, "isn't home") !== false || strpos($knockErr, 'not home') !== false || strpos($knockErr, 'away') !== false || strpos($knockErr, 'offline') !== false);
    if ($isNotHome) {
        return [
            'ok'       => false,
            'not_home' => true,
            'username' => $clean,
            'home'     => $homeName,
            'area'     => $area,
            'error'    => "@$clean is currently not inside their house (roaming in Lagos)."
        ];
    }

    if (!$knockRes['ok'] && $knockRes['code'] !== 409) {
        return [
            'ok'    => false,
            'error' => "Knock alert response: " . ($knockRes['data']['error'] ?? $knockRes['data']['message'] ?? "HTTP {$knockRes['code']}")
        ];
    }

    // 4. Knock alert is now displayed on the owner's screen! Poll for door opening
    for ($poll = 0; $poll < 4; $poll++) {
        usleep(2500000); // 2.5s
        $pollRes = requestAsUser($chatId, '/api/homes/' . urlencode($homeId));
        if ($pollRes['ok'] && (!empty($pollRes['data']['objects']) || !empty($pollRes['data']['up']))) {
            $room = $pollRes['data'];
            $objs = $room['objects'] ?? [];
            $upObjs = isset($room['up']) && is_array($room['up']) ? array_map(function($o) {
                $o['floor'] = 1;
                return $o;
            }, $room['up']) : [];
            $allObjs = array_merge($objs, $upObjs);

            @file_put_contents($tplPath, json_encode([
                'home'    => $homeName,
                'decor'   => $room['decor'] ?? ['wall' => 'sky', 'floor' => 'blackMarble'],
                'objects' => $allObjs,
            ], JSON_PRETTY_PRINT));

            return [
                'ok'       => true,
                'username' => $clean,
                'home'     => $homeName,
                'decor'    => $room['decor'] ?? ['wall' => 'sky', 'floor' => 'blackMarble'],
                'objects'  => $allObjs,
                'count'    => count($allObjs),
            ];
        }
    }

    // 5. Knock was delivered, waiting for owner to tap "Let In"
    return [
        'ok'       => false,
        'knocked'  => true,
        'username' => $clean,
        'homeId'   => $homeId,
        'home'     => $homeName,
        'area'     => $area,
    ];
}

function pollPlayerHouseDoor(string $chatId, string $targetUsername, string $homeId): array {
    $clean = ltrim(trim($targetUsername), '@');
    $tplPath = __DIR__ . '/templates/' . strtolower($clean) . '.json';

    for ($i = 0; $i < 3; $i++) {
        $pollRes = requestAsUser($chatId, '/api/homes/' . urlencode($homeId));
        if ($pollRes['ok'] && (!empty($pollRes['data']['objects']) || !empty($pollRes['data']['up']))) {
            $room = $pollRes['data'];
            $objs = $room['objects'] ?? [];
            $upObjs = isset($room['up']) && is_array($room['up']) ? array_map(function($o) {
                $o['floor'] = 1;
                return $o;
            }, $room['up']) : [];
            $allObjs = array_merge($objs, $upObjs);

            @file_put_contents($tplPath, json_encode([
                'home'    => $room['home'] ?? 'Property',
                'decor'   => $room['decor'] ?? ['wall' => 'sky', 'floor' => 'blackMarble'],
                'objects' => $allObjs,
            ], JSON_PRETTY_PRINT));

            return [
                'ok'       => true,
                'username' => $clean,
                'home'     => $room['home'] ?? 'Property',
                'decor'    => $room['decor'] ?? ['wall' => 'sky', 'floor' => 'blackMarble'],
                'objects'  => $allObjs,
                'count'    => count($allObjs),
            ];
        }
        usleep(1500000);
    }

    return ['ok' => false];
}

// ────────────────────────────────────────────────────────────────────────────
// Quilox Floor Cash Auto-Picker
// ────────────────────────────────────────────────────────────────────────────

function teleportToQuilox(string $chatId): array {
    $saveRes = requestAsUser($chatId, '/api/save?_t=' . time());
    if (!$saveRes['ok'] || empty($saveRes['data']['game'])) {
        return ['ok' => false, 'error' => 'Could not access character state'];
    }

    $game = $saveRes['data']['game'];
    $owner = $game['owner'] ?? ($saveRes['data']['owner'] ?? null);
    $base = $saveRes['data']['updatedAt'] ?? null;

    $game['location'] = 'clubEko';
    requestAsUser($chatId, '/api/save', 'PUT', [
        'game'    => $game,
        'owner'   => $owner,
        'base'    => $base,
        'replace' => $base,
    ]);

    requestAsUser($chatId, '/api/world', 'POST', [
        'location' => 'clubEko',
        'mood'     => 100,
        'activity' => 'Dancing at Quilox 🍸',
    ]);

    return ['ok' => true, 'message' => 'Teleported character directly inside Quilox Nightclub (clubEko)!'];
}

function sweepQuiloxFloor(string $chatId): array {
    teleportToQuilox($chatId);

    $worldRes = requestAsUser($chatId, '/api/world', 'POST', [
        'location' => 'clubEko',
        'mood'     => 100,
        'activity' => 'At Quilox 🍸',
    ]);

    if (!$worldRes['ok'] || empty($worldRes['data'])) {
        return ['ok' => false, 'error' => 'Could not connect to Quilox venue server'];
    }

    $events = $worldRes['data']['events'] ?? [];
    $activeSprays = [];
    foreach ($events as $ev) {
        if (!empty($ev['spray']['id']) && empty($ev['mine'])) {
            $activeSprays[] = $ev;
        }
    }

    if (empty($activeSprays)) {
        return [
            'ok'        => true,
            'grabbed'   => 0,
            'sprays'    => 0,
            'message'   => 'Swept the floor at Quilox! No active cash sprayed on the dance floor right now. Will automatically grab when VIPs spray!',
        ];
    }

    $totalGrabbed = 0;
    $pickedSprayers = [];

    foreach ($activeSprays as $sp) {
        $sprayId = (string)$sp['spray']['id'];
        $sprayer = $sp['username'] ?? 'VIP Guest';
        $pickedSprayers[] = $sprayer;

        for ($att = 0; $att < 8; $att++) {
            $pickRes = requestAsUser($chatId, '/api/spray/pick', 'POST', [
                'id' => $sprayId,
                'at' => 'clubEko',
            ]);

            if ($pickRes['ok'] && isset($pickRes['data']['amount']) && (int)$pickRes['data']['amount'] > 0) {
                $totalGrabbed += (int)$pickRes['data']['amount'];
            } else {
                usleep(250000);
            }
        }
    }

    requestAsUser($chatId, '/api/claim', 'POST', []);

    if ($totalGrabbed > 0) {
        $db = getDb();
        $db->prepare("UPDATE bot_users SET quilox_total_picked = quilox_total_picked + ?, quilox_last_picked = NOW() WHERE chat_id = ?")
           ->execute([$totalGrabbed, $chatId]);

        notifyChannel(
            "🪩 *[QUILOX FLOOR CASH SCOOPED]* 🍸\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "• *Total Scooped:* *" . fmtFull($totalGrabbed) . "* _(" . fmtShort($totalGrabbed) . ")_\n" .
            "• *Sprayed By:* @" . implode(', @', array_unique($pickedSprayers)) . "\n" .
            "• *Venue:* 🍸 *Quilox Nightclub* (`clubEko`)\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "_Auto-picker actively sweeping the floor 24/7!_"
        );
    }

    return [
        'ok'       => true,
        'grabbed'  => $totalGrabbed,
        'sprays'   => count($activeSprays),
        'sprayers' => array_unique($pickedSprayers),
    ];
}

// ────────────────────────────────────────────────────────────────────────────
// Spawn Background Batch Worker
// ────────────────────────────────────────────────────────────────────────────
if (!function_exists('startBatchWorker')) {
    function startBatchWorker(string $jobId): void {
        $script = __DIR__ . '/worker.php';
        $disabled = explode(',', ini_get('disable_functions') ?: '');
        $disabled = array_map('trim', $disabled);

        $hasExec = function_exists('exec') && !in_array('exec', $disabled);

        if ($hasExec) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                @pclose(popen("start /B php \"$script\" $jobId", "r"));
            } else {
                $phpBin = file_exists('/usr/local/php82/bin/php') ? '/usr/local/php82/bin/php' : 'php';
                @exec("$phpBin \"$script\" $jobId > /dev/null 2>&1 &");
            }
        }

        // Also hit HTTP endpoint to guarantee trigger under all server configs
        $url = 'https://lagoslife.hostedbyfps.com/worker.php?job_id=' . urlencode($jobId);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        @curl_exec($ch);
        @curl_close($ch);
    }
}
