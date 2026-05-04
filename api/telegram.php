<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bot.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse(['error' => 'Invalid request'], 400);
}

$bot = new BahlilBot();

if (isset($input['message'])) {
    $bot->handleMessage($input['message']);
} elseif (isset($input['callback_query'])) {
    $bot->handleCallback($input['callback_query']);
}

jsonResponse(['ok' => true]);
