<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$dbPath = __DIR__ . '/database.json';

function loadDb() {
    global $dbPath;
    if (!file_exists($dbPath)) {
        return ['keys' => [], 'revoked' => []];
    }
    return json_decode(file_get_contents($dbPath), true);
}

function saveDb($db) {
    global $dbPath;
    file_put_contents($dbPath, json_encode($db, JSON_PRETTY_PRINT));
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        if ($method === 'GET') {
            $db = loadDb();
            echo json_encode(['status' => true, 'keys' => $db['keys'], 'revoked' => $db['revoked']]);
        }
        break;

    case 'create':
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $db = loadDb();

            $sdkKey = 'SDK-' . strtoupper(bin2hex(random_bytes(8)));
            $duration = $input['duration'] ?? '30';
            $label = $input['label'] ?? '';
            $maxDevices = $input['max_devices'] ?? 5;

            $expiresAt = 'lifetime';
            if ($duration !== 'lifetime' && $duration !== '0') {
                $days = (int)$duration;
                $expiresAt = date('Y-m-d H:i:s', strtotime("+{$days} days"));
            }

            $newKey = [
                'id' => uniqid(),
                'sdk_key' => $sdkKey,
                'label' => $label,
                'duration' => $duration,
                'expires_at' => $expiresAt,
                'max_devices' => (int)$maxDevices,
                'devices' => [],
                'created_at' => date('Y-m-d H:i:s'),
                'active' => true
            ];

            $db['keys'][] = $newKey;
            saveDb($db);

            echo json_encode(['status' => true, 'key' => $newKey]);
        }
        break;

    case 'revoke':
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $db = loadDb();
            $targetKey = $input['sdk_key'] ?? '';

            foreach ($db['keys'] as $i => $key) {
                if ($key['sdk_key'] === $targetKey) {
                    $revokedEntry = $db['keys'][$i];
                    $revokedEntry['revoked_at'] = date('Y-m-d H:i:s');
                    $db['revoked'][] = $revokedEntry;
                    unset($db['keys'][$i]);
                    $db['keys'] = array_values($db['keys']);
                    saveDb($db);
                    echo json_encode(['status' => true, 'message' => 'Key revoked successfully']);
                    exit;
                }
            }
            echo json_encode(['status' => false, 'reason' => 'Key not found']);
        }
        break;

    case 'delete':
        if ($method === 'POST' || $method === 'DELETE') {
            $input = json_decode(file_get_contents('php://input'), true);
            $db = loadDb();
            $targetKey = $input['sdk_key'] ?? '';

            foreach ($db['keys'] as $i => $key) {
                if ($key['sdk_key'] === $targetKey) {
                    unset($db['keys'][$i]);
                    $db['keys'] = array_values($db['keys']);
                    saveDb($db);
                    echo json_encode(['status' => true, 'message' => 'Key deleted permanently']);
                    exit;
                }
            }

            foreach ($db['revoked'] as $i => $key) {
                if ($key['sdk_key'] === $targetKey) {
                    unset($db['revoked'][$i]);
                    $db['revoked'] = array_values($db['revoked']);
                    saveDb($db);
                    echo json_encode(['status' => true, 'message' => 'Revoked key deleted']);
                    exit;
                }
            }
            echo json_encode(['status' => false, 'reason' => 'Key not found']);
        }
        break;

    default:
        echo json_encode(['status' => false, 'reason' => 'Invalid action']);
        break;
}
?>
