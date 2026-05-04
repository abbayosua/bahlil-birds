<?php
require_once __DIR__ . '/config.php';

$input = getInput();
$action = $input['action'] ?? '';

switch ($action) {
    case 'submit':
        $username = trim($input['username'] ?? '');
        $score = (int)($input['score'] ?? 0);
        if (empty($username)) {
            jsonResponse(['error' => 'Username required'], 400);
        }
        if ($score < 0) {
            jsonResponse(['error' => 'Score must be non-negative'], 400);
        }

        $db = getDB();
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user) {
            jsonResponse(['error' => 'User not found'], 404);
        }

        $coins = $score;

        $db->prepare('INSERT INTO scores (user_id, score, coins_earned) VALUES (?, ?, ?)')
            ->execute([$user['id'], $score, $coins]);

        $db->prepare('UPDATE rewards SET pending_coins = pending_coins + ? WHERE user_id = ?')
            ->execute([$coins, $user['id']]);

        $stmt = $db->prepare('SELECT pending_coins, claimed_coins FROM rewards WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $rewards = $stmt->fetch();

        jsonResponse([
            'score' => $score,
            'coins_earned' => $coins,
            'pending_coins' => (int)$rewards['pending_coins'],
            'claimed_coins' => (int)$rewards['claimed_coins'],
        ]);
        break;

    case 'balance':
        $username = trim($input['username'] ?? '');
        if (empty($username)) {
            jsonResponse(['error' => 'Username required'], 400);
        }
        $db = getDB();
        $stmt = $db->prepare('SELECT r.claimed_coins, r.pending_coins FROM rewards r JOIN users u ON r.user_id = u.id WHERE u.username = ?');
        $stmt->execute([$username]);
        $rewards = $stmt->fetch();
        if (!$rewards) {
            jsonResponse(['error' => 'User not found'], 404);
        }
        jsonResponse([
            'claimed_coins' => (int)$rewards['claimed_coins'],
            'pending_coins' => (int)$rewards['pending_coins'],
        ]);
        break;

    case 'claim':
        $username = trim($input['username'] ?? '');
        if (empty($username)) {
            jsonResponse(['error' => 'Username required'], 400);
        }
        $db = getDB();
        $stmt = $db->prepare('SELECT r.id, r.pending_coins, r.claimed_coins, r.user_id FROM rewards r JOIN users u ON r.user_id = u.id WHERE u.username = ?');
        $stmt->execute([$username]);
        $rewards = $stmt->fetch();
        if (!$rewards) {
            jsonResponse(['error' => 'User not found'], 404);
        }

        $pending = (int)$rewards['pending_coins'];
        if ($pending <= 0) {
            jsonResponse(['message' => 'No coins to claim', 'claimed' => 0, 'claimed_coins' => (int)$rewards['claimed_coins'], 'pending_coins' => 0]);
        }

        $db->prepare('UPDATE rewards SET claimed_coins = claimed_coins + pending_coins, pending_coins = 0 WHERE id = ?')
            ->execute([$rewards['id']]);

        jsonResponse([
            'message' => "Claimed $pending coins!",
            'claimed' => $pending,
            'claimed_coins' => (int)$rewards['claimed_coins'] + $pending,
            'pending_coins' => 0,
        ]);
        break;

    case 'history':
        $username = trim($input['username'] ?? '');
        $limit = min((int)($input['limit'] ?? 20), 100);
        if (empty($username)) {
            jsonResponse(['error' => 'Username required'], 400);
        }
        $db = getDB();
        $stmt = $db->prepare('SELECT s.score, s.coins_earned, s.created_at FROM scores s JOIN users u ON s.user_id = u.id WHERE u.username = ? ORDER BY s.created_at DESC LIMIT ?');
        $stmt->execute([$username, $limit]);
        jsonResponse(['history' => $stmt->fetchAll()]);
        break;

    default:
        jsonResponse(['error' => 'Invalid action. Use: submit, balance, claim, history'], 400);
}
