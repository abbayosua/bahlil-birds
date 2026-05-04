<?php
session_start();

const ADMIN_USER = 'admin';
const ADMIN_PASS = 'bahlil';
const BOT_TOKEN = '8790094820:AAG0gWoRouQmkwY66fFTRpON8WMYkuys_ms';
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'flappybird');

function getDb($dbName = null) {
    try {
        return new PDO('mysql:host=' . DB_HOST . ($dbName ? ';dbname=' . $dbName : '') . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    } catch (PDOException $e) { return null; }
}

function testDb() {
    $pdo = getDb();
    if (!$pdo) return ['ok' => false, 'error' => 'Cannot connect to MySQL at ' . DB_HOST, 'step' => 'connection'];
    try {
        $pdo->query('USE ' . DB_NAME);
        $pdo->query('SELECT 1 FROM rewards LIMIT 1');
        return ['ok' => true, 'message' => 'Connected to ' . DB_NAME . ', tables exist'];
    } catch (PDOException $e) {
        $code = (string)$e->getCode();
        if ($code == '1049') return ['ok' => false, 'error' => "Database '" . DB_NAME . "' does not exist", 'step' => 'db'];
        if ($code == '42S02') return ['ok' => false, 'error' => 'Database exists but tables are missing', 'step' => 'tables'];
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function runMigration() {
    $pdo = getDb();
    if (!$pdo) return ['ok' => false, 'error' => 'Cannot connect to MySQL'];
    try {
        $pdo->query('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->query('USE `' . DB_NAME . '`');
        $sql = file_get_contents(__DIR__ . '/../sql/schema.sql');
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmt) { if (!empty($stmt)) $pdo->exec($stmt); }
        return ['ok' => true, 'message' => 'Migration completed successfully'];
    } catch (PDOException $e) { return ['ok' => false, 'error' => 'Migration failed: ' . $e->getMessage()]; }
}

function checkWebhook() {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/getWebhookInfo";
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $r = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($r, true);
    if ($data['ok'] ?? false) {
        $url = $data['result']['url'] ?? '';
        return ['ok' => true, 'is_set' => !empty($url) && $url !== '', 'url' => $url];
    }
    return ['ok' => false, 'error' => 'Cannot reach Telegram API'];
}

function setWebhook($webhookUrl) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/setWebhook";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['url' => $webhookUrl]), CURLOPT_TIMEOUT => 10,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($r, true);
    return $data['ok'] ?? false
        ? ['ok' => true, 'message' => 'Webhook set to: ' . $webhookUrl]
        : ['ok' => false, 'error' => $data['description'] ?? 'Unknown error'];
}

if (!($_SESSION['admin_logged_in'] ?? false)) {
    header('Location: index.php'); exit;
}

$action = $_POST['action'] ?? '';
if ($action) {
    header('Content-Type: application/json');
    $dbStatus = testDb();
    switch ($action) {
        case 'step_status':
            $whStatus = checkWebhook();
            echo json_encode(['ok' => true, 'db' => $dbStatus, 'webhook' => $whStatus]);
            exit;
        case 'migrate':
            echo json_encode(runMigration());
            exit;
        case 'set_webhook':
            $wh = $_POST['webhook_url'] ?? '';
            echo json_encode(empty($wh) ? ['ok' => false, 'error' => 'Webhook URL required'] : setWebhook($wh));
            exit;
    }
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Wizard - Bahlil Birds</title>
    <link rel="stylesheet" href="style.css">
    <style>
    .wizard { max-width: 640px; margin: 0 auto; }
    .wizard h1 { color: #e94560; text-align: center; margin-bottom: 8px; font-size: 1.5em; }
    .wizard > p { text-align: center; color: #888; margin-bottom: 32px; }
    .progress { display: flex; gap: 4px; margin-bottom: 32px; }
    .progress .step { flex: 1; text-align: center; padding: 10px; background: #0f3460; border-radius: 8px; font-size: 0.85em; color: #666; }
    .progress .step.active { background: #e94560; color: #fff; font-weight: 600; }
    .progress .step.done { background: #1b5e20; color: #fff; }
    .wizard-step { display: none; }
    .wizard-step.active { display: block; }
    .step-box { background: #16213e; border: 1px solid #0f3460; border-radius: 10px; padding: 24px; }
    .step-box h2 { color: #4dc9f6; margin-bottom: 12px; font-size: 1.1em; }
    .step-box p { line-height: 1.6; margin-bottom: 12px; }
    .step-box .status { margin: 16px 0; padding: 12px; border-radius: 8px; }
    .step-box .status.ok { background: rgba(46,204,113,0.15); border: 1px solid #2ecc71; color: #2ecc71; }
    .step-box .status.fail { background: rgba(231,76,60,0.15); border: 1px solid #e74c3c; color: #ff6b81; }
    .step-box .status.info { background: rgba(77,201,246,0.1); border: 1px solid #4dc9f6; color: #4dc9f6; }
    .step-box input[type="url"] { width: 100%; padding: 10px 14px; border: 2px solid #0f3460; border-radius: 8px; background: #1a1a2e; color: #fff; font-size: 0.95em; outline: none; margin-bottom: 12px; }
    .step-box input[type="url"]:focus { border-color: #4dc9f6; }
    .step-box button { padding: 10px 24px; border: none; border-radius: 8px; background: #e94560; color: #fff; font-size: 1em; font-weight: 600; cursor: pointer; }
    .step-box button:hover { background: #ff6b81; }
    .step-box button:disabled { opacity: 0.6; cursor: not-allowed; }
    .step-box .msg { margin-top: 12px; font-size: 0.9em; }
    .step-box .msg.ok { color: #2ecc71; }
    .step-box .msg.err { color: #ff6b81; }
    .wizard-done { text-align: center; }
    .wizard-done .links { display: flex; gap: 12px; justify-content: center; margin-top: 24px; flex-wrap: wrap; }
    .wizard-done .links a { padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; }
    .wizard-done .links a.game { background: #e94560; color: #fff; }
    .wizard-done .links a.admin { background: #0f3460; color: #fff; }
    .wizard-done .links a.game:hover { background: #ff6b81; }
    .wizard-done .links a.admin:hover { background: #16213e; }
    code { background: #0f3460; padding: 2px 6px; border-radius: 4px; font-size: 0.9em; }
    </style>
</head>
<body>
<div class="admin-wrap wizard">
    <h1>🚀 Setup Wizard</h1>
    <p>Let's get your Bahlil Birds app ready</p>

    <div class="progress">
        <div class="step" id="prog-1">1. Database</div>
        <div class="step" id="prog-2">2. Webhook</div>
        <div class="step" id="prog-3">3. Done</div>
    </div>

    <div class="wizard-step active" id="step-1">
        <div class="step-box">
            <h2>Step 1: Database</h2>
            <p>Set up the MySQL database. This creates the <code>flappybird</code> database and all required tables.</p>
            <div id="db-status" class="status"><p class="loading">Checking...</p></div>
            <button id="btn-migrate" onclick="runMigrate()">Create Database &amp; Tables</button>
            <p id="db-msg" class="msg"></p>
        </div>
    </div>

    <div class="wizard-step" id="step-2">
        <div class="step-box">
            <h2>Step 2: Telegram Webhook</h2>
            <p>Register the Telegram bot webhook so the bot can receive messages from players.</p>
            <p>Use your domain URL + <code>/bikinweb/flappybird/api/telegram.php</code></p>
            <div id="wh-status" class="status"><p class="loading">Checking...</p></div>
            <input type="url" id="webhook-url" placeholder="https://yourdomain.com/bikinweb/flappybird/api/telegram.php">
            <button onclick="registerWebhook()">Register Webhook</button>
            <p id="wh-msg" class="msg"></p>
        </div>
    </div>

    <div class="wizard-step" id="step-3">
        <div class="step-box wizard-done">
            <h2>✅ All Done!</h2>
            <p>Everything is set up and ready to go.</p>
            <div class="links">
                <a href="../" class="game">🎮 Play Game</a>
                <a href="index.php" class="admin">🔧 Admin Panel</a>
            </div>
        </div>
    </div>
</div>

<script>
function api(action, body) {
    const fd = new FormData();
    fd.append('action', action);
    if (body) for (let [k, v] of Object.entries(body)) fd.append(k, v);
    return fetch('?', { method: 'POST', body: fd }).then(r => r.json());
}

function goToStep(n) {
    document.querySelectorAll('.wizard-step').forEach(s => s.classList.remove('active'));
    document.getElementById('step-' + n).classList.add('active');
    document.querySelectorAll('.progress .step').forEach(s => s.classList.remove('active'));
    for (let i = 1; i <= 3; i++) {
        const el = document.getElementById('prog-' + i);
        el.classList.remove('active', 'done');
        if (i < n) el.classList.add('done');
        else if (i === n) el.classList.add('active');
    }
}

async function checkStatus() {
    const d = await api('step_status');
    if (d.db.ok) {
        document.getElementById('db-status').innerHTML = '<p>✅ Database ready</p>';
        document.getElementById('btn-migrate').disabled = true;
        document.getElementById('btn-migrate').textContent = '✅ Database Ready';
        step1Done();
    } else {
        document.getElementById('db-status').innerHTML = '<p>❌ ' + d.db.error + '</p>';
    }
    if (d.webhook.ok && d.webhook.is_set) {
        document.getElementById('wh-status').innerHTML = '<p>✅ Webhook active: <code>' + d.webhook.url + '</code></p>';
        document.getElementById('webhook-url').value = d.webhook.url;
        document.getElementById('webhook-url').disabled = true;
        step2Done();
    } else if (d.webhook.ok) {
        document.getElementById('wh-status').innerHTML = '<p>⚠️ Webhook not set yet — enter your URL below</p>';
    } else {
        document.getElementById('wh-status').innerHTML = '<p>❌ ' + d.webhook.error + '</p>';
    }
}

function step1Done() {
    if (document.getElementById('step-1').classList.contains('active')) {
        goToStep(2);
        checkStatus();
    }
}

function step2Done() {
    if (document.getElementById('step-2').classList.contains('active')) {
        goToStep(3);
    }
}

async function runMigrate() {
    const btn = document.getElementById('btn-migrate');
    btn.disabled = true;
    btn.textContent = 'Running...';
    const d = await api('migrate');
    const el = document.getElementById('db-msg');
    if (d.ok) {
        el.className = 'msg ok';
        el.textContent = '✅ ' + d.message;
        btn.textContent = '✅ Database Ready';
        step1Done();
    } else {
        el.className = 'msg err';
        el.textContent = '❌ ' + d.error;
        btn.disabled = false;
        btn.textContent = 'Try Again';
    }
}

async function registerWebhook() {
    const url = document.getElementById('webhook-url').value;
    if (!url) { document.getElementById('wh-msg').textContent = 'Please enter a webhook URL'; return; }
    const d = await api('set_webhook', { webhook_url: url });
    const el = document.getElementById('wh-msg');
    if (d.ok) {
        el.className = 'msg ok';
        el.textContent = '✅ ' + d.message;
        document.getElementById('webhook-url').disabled = true;
        step2Done();
    } else {
        el.className = 'msg err';
        el.textContent = '❌ ' + d.error;
    }
}

checkStatus();
</script>
</body>
</html>
