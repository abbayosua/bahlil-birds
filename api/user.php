<?php
require_once __DIR__ . '/config.php';

$input = getInput();
$action = $input['action'] ?? '';

switch ($action) {
    case 'create':
        $username = trim($input['username'] ?? '');
        if (empty($username)) {
            jsonResponse(['error' => 'Username required'], 400);
        }
        $db = getDB();
        $stmt = $db->prepare('INSERT IGNORE INTO users (username) VALUES (?)');
        $stmt->execute([$username]);
        if ($stmt->rowCount() === 0) {
            $stmt = $db->prepare('SELECT id, username, telegram_id, created_at FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            jsonResponse(['user' => $user, 'exists' => true]);
        }
        $userId = $db->lastInsertId();
        $db->prepare('INSERT INTO rewards (user_id, total_coins, claimed_coins) VALUES (?, 0, 0)')->execute([$userId]);
        $stmt = $db->prepare('SELECT id, username, telegram_id, created_at FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        jsonResponse(['user' => $stmt->fetch(), 'created' => true]);
        break;

    case 'get':
        $username = trim($input['username'] ?? '');
        if (empty($username)) {
            jsonResponse(['error' => 'Username required'], 400);
        }
        $db = getDB();
        $stmt = $db->prepare('SELECT id, username, telegram_id, created_at FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user) {
            jsonResponse(['error' => 'User not found'], 404);
        }
        $stmt = $db->prepare('SELECT total_coins, claimed_coins FROM rewards WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $rewards = $stmt->fetch();
        jsonResponse(['user' => $user, 'rewards' => $rewards]);
        break;

    case 'link_tg':
        $username = trim($input['username'] ?? '');
        $telegramId = trim($input['telegram_id'] ?? '');
        if (empty($username) || empty($telegramId)) {
            jsonResponse(['error' => 'Username and telegram_id required'], 400);
        }
        $db = getDB();
        $stmt = $db->prepare('UPDATE users SET telegram_id = ? WHERE username = ?');
        $stmt->execute([$telegramId, $username]);
        if ($stmt->rowCount() === 0) {
            jsonResponse(['error' => 'User not found'], 404);
        }
        jsonResponse(['success' => true, 'message' => 'Telegram linked']);
        break;

    default:
        jsonResponse(['error' => 'Invalid action. Use: create, get, link_tg'], 400);
}
