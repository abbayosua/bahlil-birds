<?php

class BahlilBot {
    private const BOT_TOKEN = '8790094820:AAG0gWoRouQmkwY66fFTRpON8WMYkuys_ms';
    private const LLM7_TOKEN = 'TAHNAetu7VD0lmGDCqU/016OdMdCnWYi0HD4Df1yVcqSpm884UmoKAWy9SUi5G5UUvQfMHFHdWXTWgg+8djJJ+rlT4Y8EdmCLvyCSgUCGivB3DP5F+bs3PbVbnms0GIEAcegkw==';
    private const LLM7_URL = 'https://api.llm7.io/v1';

    public function handleMessage(array $message): void {
        $chatId = $message['chat']['id'];
        $text = trim($message['text'] ?? '');
        $telegramId = (string)$message['from']['id'];
        $firstName = $message['from']['first_name'] ?? 'Player';

        if (empty($text)) {
            return;
        }

        if (str_starts_with($text, '/start')) {
            $this->cmdStart($chatId, $telegramId, $firstName, $text);
        } elseif (str_starts_with($text, '/claim')) {
            $this->cmdClaim($chatId, $telegramId);
        } elseif (str_starts_with($text, '/balance')) {
            $this->cmdBalance($chatId, $telegramId);
        } elseif (str_starts_with($text, '/name')) {
            $newName = trim(mb_substr($text, 5));
            $this->cmdChangeName($chatId, $telegramId, $newName);
        } elseif (str_starts_with($text, '/info')) {
            $this->cmdInfo($chatId, $telegramId);
        } elseif (str_starts_with($text, '/switch')) {
            $newAccount = trim(mb_substr($text, 7));
            $this->cmdSwitch($chatId, $telegramId, $newAccount);
        } elseif (str_starts_with($text, '/help')) {
            $this->cmdHelp($chatId);
        } else {
            $this->chatAI($chatId, $telegramId, $firstName, $text);
        }
    }

    public function handleCallback(array $callback): void {
        $chatId = $callback['message']['chat']['id'];
        $data = $callback['data'];
        $telegramId = (string)$callback['from']['id'];

        switch ($data) {
            case 'claim':
                $this->cmdClaim($chatId, $telegramId);
                break;
            case 'balance':
                $this->cmdBalance($chatId, $telegramId);
                break;
            case 'info':
                $this->cmdInfo($chatId, $telegramId);
                break;
        }
    }

    private function cmdStart(int $chatId, string $telegramId, string $firstName, string $text): void {
        $user = $this->getUserByTelegram($telegramId);

        if ($user) {
            $db = getDB();
            $stmt = $db->prepare('SELECT pending_coins, claimed_coins FROM rewards WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            $rewards = $stmt->fetch();
            $pending = (int)$rewards['pending_coins'];

            $msg = "Welcome back, {$user['username']}! \n\n"
                . "Balance: {$rewards['claimed_coins']} | Pending: $pending\n\n"
                . "/claim - Claim your pending coins\n"
                . "/balance - Check your coins\n"
                . "/info - Account information\n"
                . "Or just chat with me!";
            $this->sendMessage($chatId, $msg);
            return;
        }

        $deepLink = trim(mb_substr($text, 6));
        if (!empty($deepLink)) {
            $linked = $this->tryLinkUsername($telegramId, $deepLink);
            if ($linked) {
                $db = getDB();
                $stmt = $db->prepare('SELECT pending_coins, claimed_coins FROM rewards WHERE user_id = ?');
                $stmt->execute([$linked['id']]);
                $rewards = $stmt->fetch();
                $pending = (int)$rewards['pending_coins'];

                $msg = "Account linked! Welcome, {$linked['username']}! \n\n"
                    . "Balance: {$rewards['claimed_coins']} | Pending: $pending\n\n"
                    . "Use /claim to collect your coins, or just chat with me!";
                $this->sendMessage($chatId, $msg);
                return;
            } else {
                $this->sendMessage($chatId, "I couldn't find a player named \"$deepLink\". Make sure you've played the game first with this username!");
                return;
            }
        }

            $msg = "Hello $firstName! Welcome to Bahlil Birds! \n\n"
            . "Play the game first, then just tell me your game username and I'll link your account automatically.\n\n"
            . "Commands: /help";
        $this->sendMessage($chatId, $msg);
    }

    private function cmdSwitch(int $chatId, string $telegramId, string $newAccount): void {
        $currentUser = $this->getUserByTelegram($telegramId);
        if (!$currentUser) {
            $this->sendMessage($chatId, "You don't have any account linked yet. Play the game first, then tell me your username!");
            return;
        }

        if (empty($newAccount)) {
            $this->sendMessage($chatId, "Usage: /switch username — switch to a different game account.");
            return;
        }

        if ($newAccount === $currentUser['username']) {
            $this->sendMessage($chatId, "You're already on \"$newAccount\"!");
            return;
        }

        $db = getDB();
        $stmt = $db->prepare('SELECT id, username, telegram_id, created_at FROM users WHERE username = ?');
        $stmt->execute([$newAccount]);
        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            $this->sendMessage($chatId, "No player named \"$newAccount\" found. Have you created that account in the game?");
            return;
        }

        if ($targetUser['telegram_id'] !== null && $targetUser['telegram_id'] !== $telegramId) {
            $this->sendMessage($chatId, "\"$newAccount\" is already linked to a different Telegram account.");
            return;
        }

        // Warn about unclaimed coins on current account
        $stmt = $db->prepare('SELECT pending_coins, claimed_coins FROM rewards WHERE user_id = ?');
        $stmt->execute([$currentUser['id']]);
        $currentRewards = $stmt->fetch();
        $pending = (int)$currentRewards['pending_coins'];

        if ($pending > 0) {
            $this->sendMessage($chatId,
                "⚠️ You have $pending unclaimed coins on \"{$currentUser['username']}\". "
                . "Claim them first? Use /claim before switching.");
        }

        // Switch: unlink old, link new
        $db->prepare('UPDATE users SET telegram_id = NULL WHERE id = ?')->execute([$currentUser['id']]);
        $db->prepare('UPDATE users SET telegram_id = ? WHERE id = ?')->execute([$telegramId, $targetUser['id']]);

        $stmt = $db->prepare('SELECT pending_coins, claimed_coins FROM rewards WHERE user_id = ?');
        $stmt->execute([$targetUser['id']]);
        $newRewards = $stmt->fetch();

        $stmt = $db->prepare('SELECT COUNT(*) as games, MAX(score) as high_score FROM scores WHERE user_id = ?');
        $stmt->execute([$targetUser['id']]);
        $s = $stmt->fetch();

        $this->sendMessage($chatId,
            "Switched from \"{$currentUser['username']}\" to \"{$newAccount}\"! 🐦\n"
            . "New account — Games: {$s['games']} | High score: {$s['high_score']} | Balance: {$newRewards['claimed_coins']} | Pending: {$newRewards['pending_coins']}");
    }

    private function cmdClaim(int $chatId, string $telegramId): void {
        $user = $this->getUserByTelegram($telegramId);
        if (!$user) {
            $this->sendMessage($chatId, "Your Telegram is not linked to any account yet. Play the game first to create a username, then tell me your username!");
            return;
        }

        $db = getDB();
        $stmt = $db->prepare('SELECT pending_coins, claimed_coins FROM rewards WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $rewards = $stmt->fetch();
        $pending = (int)$rewards['pending_coins'];

        if ($pending <= 0) {
            $this->sendMessage($chatId, "No coins to claim right now. Play the game and earn some first!");
            return;
        }

        $db->prepare('UPDATE rewards SET claimed_coins = claimed_coins + pending_coins, pending_coins = 0 WHERE user_id = ?')->execute([$user['id']]);
        $this->sendMessage($chatId, "You claimed $pending coins! Balance: {$rewards['claimed_coins']} + $pending = " . ((int)$rewards['claimed_coins'] + $pending) . ". Keep flapping!");
    }

    private function cmdBalance(int $chatId, string $telegramId): void {
        $user = $this->getUserByTelegram($telegramId);
        if (!$user) {
            $this->sendMessage($chatId, "Your Telegram is not linked to any account yet. Tell me your game username!");
            return;
        }

        $db = getDB();
        $stmt = $db->prepare('SELECT pending_coins, claimed_coins FROM rewards WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $rewards = $stmt->fetch();
        $pending = (int)$rewards['pending_coins'];

        $msg = "Account: {$user['username']}\n"
            . "Balance (claimed): {$rewards['claimed_coins']}\n"
            . "Pending (to claim): $pending";

        $this->sendMessage($chatId, $msg);
    }

    private function cmdChangeName(int $chatId, string $telegramId, string $newName): void {
        if (empty($newName) || mb_strlen($newName) < 2 || mb_strlen($newName) > 50) {
            $this->sendMessage($chatId, "Username must be 2-50 characters. Usage: /name YourNewName");
            return;
        }

        $user = $this->getUserByTelegram($telegramId);
        if (!$user) {
            $this->sendMessage($chatId, "Your Telegram is not linked to any account yet. Tell me your current game username first!");
            return;
        }

        $db = getDB();
        try {
            $stmt = $db->prepare('UPDATE users SET username = ? WHERE id = ? AND telegram_id = ?');
            $stmt->execute([$newName, $user['id'], $telegramId]);
            $this->sendMessage($chatId, "Your username has been changed to \"$newName\"! Use this name when playing the game now.");
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $this->sendMessage($chatId, "Username \"$newName\" is already taken. Please choose another one.");
            } else {
                $this->sendMessage($chatId, "Failed to update username. Please try again.");
            }
        }
    }

    private function cmdInfo(int $chatId, string $telegramId): void {
        $user = $this->getUserByTelegram($telegramId);
        if (!$user) {
            $this->sendMessage($chatId, "Your Telegram is not linked yet. Tell me your game username and I'll link your account!");
            return;
        }

        $db = getDB();
        $stmt = $db->prepare('SELECT pending_coins, claimed_coins FROM rewards WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $rewards = $stmt->fetch();

        $stmt = $db->prepare('SELECT COUNT(*) as games, MAX(score) as high_score FROM scores WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $stats = $stmt->fetch();

        $msg = "=== Account Info ===\n"
            . "Username: {$user['username']}\n"
            . "Joined: {$user['created_at']}\n"
            . "Games played: {$stats['games']}\n"
            . "High score: {$stats['high_score']}\n"
            . "Claimed: {$rewards['claimed_coins']}\n"
            . "Pending: {$rewards['pending_coins']}";

        $this->sendMessage($chatId, $msg);
    }

    private function cmdHelp(int $chatId): void {
        $msg = "=== Bahlil Birds Bot ===\n\n"
            . "/start - Start bot & link account\n"
            . "/claim - Claim your earned coins\n"
            . "/balance - Check coin balance\n"
            . "/name <new> - Change username\n"
            . "/info - Account information\n"
            . "/help - This message\n\n"
            . "You can also chat with me naturally! Try asking \"How many coins do I have?\"";
        $this->sendMessage($chatId, $msg);
    }

    private function chatAI(int $chatId, string $telegramId, string $firstName, string $text): void {
        $user = $this->getUserByTelegram($telegramId);

        if (!$user) {
            $linked = $this->tryAutoLink($telegramId, $text);
            if ($linked) {
                $this->sendMessage($chatId,
                    "Got it, {$linked['username']}! Your account is now linked. "
                    . "Ask me anything — check your balance, claim coins, whatever!");
                return;
            }
            $this->chatResponse($chatId, null, $firstName, $text, null);
            return;
        }

        $intent = $this->classifyIntent($text);
        $actionResult = null;

        if ($intent['intent'] !== 'chat' && $intent['intent'] !== 'help') {
            $actionResult = $this->executeAction($intent, $user);
        }

        $this->chatResponse($chatId, $user, $firstName, $text, $actionResult);
    }

    private function classifyIntent(string $text): array {
        $systemPrompt = "classify the user message into exactly one intent. reply with ONLY the intent word.\n\n"
            . "claim - user wants to claim/redeem/collect their coins or rewards\n"
            . "balance - user asks about their balance, coins, or how many they have\n"
            . "info - user asks about account, stats, profile, high score, games played\n"
            . "help - user asks for help, what you can do, available commands\n"
            . "rename:NEWNAME - user wants to change their username to NEWNAME\n"
            . "switch:NEWNAME - user wants to switch to a different account / login as someone else / use another account\n"
            . "chat - casual talk, greeting, thanks, or anything else\n\n"
            . "examples:\n"
            . "i want to claim my coins -> claim\n"
            . "how many coins do i have -> balance\n"
            . "what's my high score -> info\n"
            . "show my stats -> info\n"
            . "change my name to superbird -> rename:superbird\n"
            . "call me speedy -> rename:speedy\n"
            . "login as angga -> switch:angga\n"
            . "switch to soeb2 -> switch:soeb2\n"
            . "use my other account angga -> switch:angga\n"
            . "i want to login as angga please claim angga coin -> switch:angga\n"
            . "what can you do -> help\n"
            . "hello -> chat\n"
            . "nice game -> chat\n\n"
            . "reply with the exact intent word only. for rename or switch use name:newusername";

        try {
            $response = strtolower(trim($this->callLLM7($systemPrompt, $text, 20, 0)));
            if (str_contains($response, 'rename:')) {
                $parts = explode(':', $response, 2);
                return ['intent' => 'rename', 'param' => trim($parts[1] ?? '')];
            }
            if (str_contains($response, 'switch:')) {
                $parts = explode(':', $response, 2);
                return ['intent' => 'switch', 'param' => trim($parts[1] ?? '')];
            }
            $valid = ['claim', 'balance', 'info', 'help', 'chat', 'switch'];
            if (in_array($response, $valid)) {
                return ['intent' => $response, 'param' => null];
            }
        } catch (Exception $e) {
        }
        return ['intent' => 'chat', 'param' => null];
    }

    private function executeAction(array $intent, array $user): array {
        $db = getDB();

        switch ($intent['intent']) {
            case 'claim':
                $stmt = $db->prepare('SELECT pending_coins, claimed_coins FROM rewards WHERE user_id = ?');
                $stmt->execute([$user['id']]);
                $r = $stmt->fetch();
                $pending = (int)$r['pending_coins'];
                if ($pending <= 0) {
                    return ['action' => 'claim', 'success' => true, 'claimed' => 0, 'claimed_coins' => (int)$r['claimed_coins'], 'pending_coins' => 0];
                }
                $db->prepare('UPDATE rewards SET claimed_coins = claimed_coins + pending_coins, pending_coins = 0 WHERE user_id = ?')->execute([$user['id']]);
                return ['action' => 'claim', 'success' => true, 'claimed' => $pending, 'claimed_coins' => (int)$r['claimed_coins'] + $pending, 'pending_coins' => 0];

            case 'balance':
                $stmt = $db->prepare('SELECT pending_coins, claimed_coins FROM rewards WHERE user_id = ?');
                $stmt->execute([$user['id']]);
                $r = $stmt->fetch();
                return ['action' => 'balance', 'success' => true,
                    'claimed_coins' => (int)$r['claimed_coins'],
                    'pending_coins' => (int)$r['pending_coins']];

            case 'info':
                $stmt = $db->prepare('SELECT total_coins, claimed_coins FROM rewards WHERE user_id = ?');
                $stmt->execute([$user['id']]);
                $r = $stmt->fetch();
                $stmt = $db->prepare('SELECT COUNT(*) as games, MAX(score) as high_score FROM scores WHERE user_id = ?');
                $stmt->execute([$user['id']]);
                $s = $stmt->fetch();
                return ['action' => 'info', 'success' => true,
                    'username' => $user['username'],
                    'joined' => $user['created_at'],
                    'games' => (int)$s['games'],
                    'high_score' => (int)$s['high_score'],
                    'total_coins' => (int)$r['total_coins'],
                    'claimed_coins' => (int)$r['claimed_coins']];

            case 'rename':
                $newName = trim($intent['param'] ?? '');
                if (empty($newName) || mb_strlen($newName) < 2 || mb_strlen($newName) > 50) {
                    return ['action' => 'rename', 'success' => false, 'error' => 'Username must be 2-50 characters'];
                }
                try {
                    $stmt = $db->prepare('UPDATE users SET username = ? WHERE id = ?');
                    $stmt->execute([$newName, $user['id']]);
                    return ['action' => 'rename', 'success' => true, 'new_name' => $newName, 'old_name' => $user['username']];
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        return ['action' => 'rename', 'success' => false, 'error' => "Username \"$newName\" is already taken"];
                    }
                    return ['action' => 'rename', 'success' => false, 'error' => 'Failed to update username'];
                }

            case 'switch':
                $newAccount = trim($intent['param'] ?? '');
                if (empty($newAccount)) {
                    return ['action' => 'switch', 'success' => false, 'error' => 'Which account do you want to switch to?'];
                }
                if ($newAccount === $user['username']) {
                    return ['action' => 'switch', 'success' => false, 'error' => "You're already on \"$newAccount\""];
                }
                $stmt = $db->prepare('SELECT id, username, telegram_id FROM users WHERE username = ?');
                $stmt->execute([$newAccount]);
                $target = $stmt->fetch();
                if (!$target) {
                    return ['action' => 'switch', 'success' => false, 'error' => "No account named \"$newAccount\" found. Create it in the game first."];
                }
                if ($target['telegram_id'] !== null && $target['telegram_id'] !== $user['telegram_id']) {
                    return ['action' => 'switch', 'success' => false, 'error' => "\"$newAccount\" is already linked to another Telegram account."];
                }
                $stmt = $db->prepare('SELECT pending_coins, claimed_coins FROM rewards WHERE user_id = ?');
                $stmt->execute([$user['id']]);
                $oldRewards = $stmt->fetch();
                $pending = (int)$oldRewards['pending_coins'];
                $db->prepare('UPDATE users SET telegram_id = NULL WHERE id = ?')->execute([$user['id']]);
                $db->prepare('UPDATE users SET telegram_id = ? WHERE id = ?')->execute([$user['telegram_id'], $target['id']]);
                return [
                    'action' => 'switch', 'success' => true,
                    'old_account' => $user['username'],
                    'old_pending' => $pending,
                    'new_account' => $target['username'],
                ];

            default:
                return ['action' => 'unknown', 'success' => false];
        }
    }

    private function chatResponse(int $chatId, ?array $user, string $firstName, string $text, ?array $actionResult): void {
        if ($user) {
            $db = getDB();
            $stmt = $db->prepare('SELECT pending_coins, claimed_coins FROM rewards WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            $r = $stmt->fetch();
            $pending = (int)$r['pending_coins'];

            $userContext = "Current user: {$user['username']}\n"
                . "Balance (claimed): {$r['claimed_coins']} | Pending (to claim): $pending\n";

            if ($actionResult) {
                $userContext .= "\nAction that was just executed (tell user about it in a fun way): "
                    . json_encode($actionResult) . "\n";
            }

            $systemPrompt = "You are BahlilBot, a friendly game assistant for Bahlil Birds. "
                . "You speak like an excited game buddy. Keep responses short (1-3 sentences) and fun. "
                . "Use emojis occasionally. The player earns 1 coin per point scored. "
                . "Pending coins are earned but not yet claimed. "
                . "The balance only increases when they claim pending coins.\n\n"
                . "$userContext";
        } else {
            $systemPrompt = "You are BahlilBot for Bahlil Birds game. "
                . "Keep responses short (1-3 sentences). "
                . "This user is NOT yet linked to a game account. "
                . "Encourage them to tell you their game username so you can link their Telegram.\n\n"
                . "Commands: /help";
        }

        try {
            $response = $this->callLLM7($systemPrompt, $text, 200, 0.8);
            $this->sendMessage($chatId, $response);
        } catch (Exception $e) {
            $this->sendFallback($chatId, $user, $firstName, $actionResult);
        }
    }

    private function sendFallback(int $chatId, ?array $user, string $firstName, ?array $actionResult): void {
        if ($actionResult && $actionResult['success']) {
            switch ($actionResult['action']) {
                case 'claim':
                    if ($actionResult['claimed'] > 0) {
                        $this->sendMessage($chatId, "Done! {$actionResult['claimed']} coins claimed. Balance: {$actionResult['claimed_coins']}. Keep flapping!");
                    } else {
                        $this->sendMessage($chatId, "Nothing to claim yet. Go play and come back!");
                    }
                    break;
                case 'balance':
                    $this->sendMessage($chatId, "Your balance: {$actionResult['claimed_coins']} claimed, {$actionResult['pending_coins']} pending.");
                    break;
                case 'info':
                    $this->sendMessage($chatId, "{$actionResult['username']} — {$actionResult['games']} games, high score {$actionResult['high_score']}, {$actionResult['claimed_coins']} claimed, {$actionResult['pending_coins']} pending.");
                    break;
                case 'rename':
                    $this->sendMessage($chatId, "Renamed to {$actionResult['new_name']}!");
                    break;
                case 'switch':
                    if ($actionResult['success']) {
                        $msg = "Switched from {$actionResult['old_account']} to {$actionResult['new_account']}! 🐦";
                        if ($actionResult['old_pending'] > 0) {
                            $msg .= " (You left {$actionResult['old_pending']} pending coins on {$actionResult['old_account']})";
                        }
                        $this->sendMessage($chatId, $msg);
                    } else {
                        $this->sendMessage($chatId, $actionResult['error'] ?? "Couldn't switch accounts.");
                    }
                    break;
                default:
                    $this->sendMessage($chatId, $user ? "Hey {$user['username']}! Use /help for commands." : "Hey $firstName! Tell me your game username.");
            }
        } elseif ($user) {
            $this->sendMessage($chatId, "Hey {$user['username']}! Use /help for commands, or ask me anything.");
        } else {
            $this->sendMessage($chatId, "Hey $firstName! Tell me your game username to get started.");
        }
    }

    private function tryAutoLink(string $telegramId, string $text): ?array {
        $words = preg_split('/\s+/', $text);
        $stopWords = ['my', 'is', 'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on',
            'at', 'to', 'for', 'of', 'with', 'username', 'name', 'account', 'called',
            'i', 'me', 'you', 'it', 'hello', 'hi', 'hey', 'please', 'can', 'help',
            'no', 'yes', 'not', 'this', 'that', 'am', 'its', 'here', 'there',
            'want', 'need', 'have', 'has', 'was', 'were', 'be', 'been', 'being',
            'link', 'linked', 'connect', 'register', 'add', 'use', 'using'];

        $candidates = [];
        foreach ($words as $word) {
            $word = preg_replace('/[^a-zA-Z0-9_-]/', '', $word);
            $len = mb_strlen($word);
            if ($len >= 2 && $len <= 50 && !in_array(strtolower($word), $stopWords)) {
                $candidates[] = $word;
            }
        }

        foreach (array_unique($candidates) as $candidate) {
            $found = $this->tryLinkUsername($telegramId, $candidate);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    private function tryLinkUsername(string $telegramId, string $username): ?array {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, username, telegram_id, created_at FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        if ($user['telegram_id'] === $telegramId) {
            return $user;
        }

        if ($user['telegram_id'] !== null) {
            return null;
        }

        $db->prepare('UPDATE users SET telegram_id = ? WHERE id = ?')->execute([$telegramId, $user['id']]);
        return $user;
    }

    private function callLLM7(string $systemPrompt, string $userMessage, int $maxTokens = 200, float $temperature = 0.8): string {
        $ch = curl_init(self::LLM7_URL . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . self::LLM7_TOKEN,
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'default',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage],
                ],
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
            ]),
            CURLOPT_TIMEOUT => 15,
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$result) {
            throw new Exception("LLM7 API error: $httpCode");
        }

        $data = json_decode($result, true);
        return trim($data['choices'][0]['message']['content'] ?? '');
    }

    private function getUserByTelegram(string $telegramId): ?array {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, username, telegram_id, created_at FROM users WHERE telegram_id = ?');
        $stmt->execute([$telegramId]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    private function sendMessage(int $chatId, string $text): void {
        $url = "https://api.telegram.org/bot" . self::BOT_TOKEN . "/sendMessage";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]),
            CURLOPT_TIMEOUT => 10,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
