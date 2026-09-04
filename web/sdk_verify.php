<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'reason' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['device_id']) || empty($input['sdk_key'])) {
    http_response_code(400);
    echo json_encode(['status' => false, 'reason' => 'Missing device_id or sdk_key']);
    exit;
}

$deviceId = $input['device_id'];
$sdkKey = $input['sdk_key'];

$dbPath = __DIR__ . '/database.json';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['status' => false, 'reason' => 'Database not found']);
    exit;
}

$db = json_decode(file_get_contents($dbPath), true);
if (!$db || !isset($db['keys'])) {
    http_response_code(500);
    echo json_encode(['status' => false, 'reason' => 'Invalid database']);
    exit;
}

foreach ($db['revoked'] as $revokedEntry) {
    if ($revokedEntry['sdk_key'] === $sdkKey) {
        echo json_encode(['status' => false, 'reason' => 'SDK Key has been revoked']);
        exit;
    }
}

$found = false;
foreach ($db['keys'] as $keyEntry) {
    if ($keyEntry['sdk_key'] === $sdkKey) {
        $found = true;

        if (!empty($keyEntry['devices']) && in_array($deviceId, $keyEntry['devices'])) {
            if (isset($keyEntry['expires_at']) && $keyEntry['expires_at'] !== 'lifetime') {
                $expiryDate = new DateTime($keyEntry['expires_at']);
                $now = new DateTime();
                if ($now > $expiryDate) {
                    echo json_encode(['status' => false, 'reason' => 'SDK Key has expired']);
                    exit;
                }
            }

            $token = bin2hex(random_bytes(32));
            echo json_encode([
                'status' => true,
                'expiry' => $keyEntry['expires_at'] ?? 'lifetime',
                'token' => $token
            ]);
            exit;
        }

        if (empty($keyEntry['devices'])) {
            $keyEntry['devices'] = [];
        }

        if (!in_array($deviceId, $keyEntry['devices'])) {
            if (isset($keyEntry['max_devices']) && count($keyEntry['devices']) >= $keyEntry['max_devices']) {
                echo json_encode(['status' => false, 'reason' => 'Maximum device limit reached']);
                exit;
            }
            $keyEntry['devices'][] = $deviceId;
            foreach ($db['keys'] as &$k) {
                if ($k['sdk_key'] === $sdkKey) {
                    $k = $keyEntry;
                    break;
                }
            }
            unset($k);
            file_put_contents($dbPath, json_encode($db, JSON_PRETTY_PRINT));
        }

        if (isset($keyEntry['expires_at']) && $keyEntry['expires_at'] !== 'lifetime') {
            $expiryDate = new DateTime($keyEntry['expires_at']);
            $now = new DateTime();
            if ($now > $expiryDate) {
                echo json_encode(['status' => false, 'reason' => 'SDK Key has expired']);
                exit;
            }
        }

        $token = bin2hex(random_bytes(32));
        echo json_encode([
            'status' => true,
            'expiry' => $keyEntry['expires_at'] ?? 'lifetime',
            'token' => $token
        ]);
        exit;
    }
}

if (!$found) {
    echo json_encode(['status' => false, 'reason' => 'Invalid or Expired SDK Key']);
}
?>
