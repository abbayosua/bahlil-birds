<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function getDBConfig(): array {
    static $cfg = null;
    if ($cfg === null) {
        $path = __DIR__ . '/../config/db.php';
        if (file_exists($path)) {
            $cfg = require $path;
        } else {
            $path = __DIR__ . '/../config/db.default.php';
            $cfg = file_exists($path) ? require $path : ['host' => 'localhost', 'dbname' => 'flappybird', 'user' => 'root', 'pass' => ''];
        }
    }
    return $cfg;
}

function getDB(): PDO {
    static $db = null;
    if ($db === null) {
        $c = getDBConfig();
        $db = new PDO(
            'mysql:host=' . $c['host'] . ';dbname=' . $c['dbname'] . ';charset=utf8mb4',
            $c['user'],
            $c['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
    return $db;
}

function jsonResponse($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getInput(): array {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if (!is_array($data)) {
        $data = [];
    }
    return array_merge($_GET, $_POST, $data);
}
