<?php
session_start();
const ADMIN_USER = 'admin';
const ADMIN_PASS = 'bahlil';
const BOT_TOKEN = '8790094820:AAG0gWoRouQmkwY66fFTRpON8WMYkuys_ms';

function getDbConfig(): array {
    static $cfg = null;
    if ($cfg === null) {
        $path = __DIR__ . '/../config/db.php';
        if (file_exists($path)) { $cfg = require $path; }
        else {
            $path = __DIR__ . '/../config/db.default.php';
            $cfg = file_exists($path) ? require $path : ['host' => 'localhost', 'dbname' => 'flappybird', 'user' => 'root', 'pass' => ''];
        }
    }
    return $cfg;
}

function getDb($dbName = null) {
    try {
        $c = getDbConfig();
        return new PDO('mysql:host=' . $c['host'] . ($dbName ? ';dbname=' . $dbName : '') . ';charset=utf8mb4',
            $c['user'], $c['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    } catch (PDOException $e) { return null; }
}

function testDb() {
    $c = getDbConfig();
    $pdo = getDb();
    if (!$pdo) return ['ok' => false, 'error' => 'Cannot connect to MySQL at ' . $c['host'], 'step' => 'connection'];
    try {
        $pdo->query('USE ' . $c['dbname']);
        $pdo->query('SELECT 1 FROM rewards LIMIT 1');
        return ['ok' => true, 'message' => 'Connected to ' . $c['dbname'] . ', tables exist'];
    } catch (PDOException $e) {
        $code = (string)$e->getCode();
        if ($code == '1049') return ['ok' => false, 'error' => "Database '" . $c['dbname'] . "' does not exist", 'step' => 'db'];
        if ($code == '42S02') return ['ok' => false, 'error' => 'Database exists but tables are missing', 'step' => 'tables'];
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function saveConfig($host, $dbname, $user, $pass) {
    $content = '<?php' . "\nreturn [\n"
        . "    'host' => " . var_export($host, true) . ",\n"
        . "    'dbname' => " . var_export($dbname, true) . ",\n"
        . "    'user' => " . var_export($user, true) . ",\n"
        . "    'pass' => " . var_export($pass, true) . ",\n"
        . "];\n";
    $path = __DIR__ . '/../config/db.php';
    if (@file_put_contents($path, $content) === false) {
        return ['ok' => false, 'error' => 'Cannot write config file at config/db.php. Check permissions.'];
    }
    return ['ok' => true, 'message' => 'Configuration saved'];
}

function runMigration() {
    $c = getDbConfig();
    $pdo = getDb();
    if (!$pdo) return ['ok' => false, 'error' => 'Cannot connect to MySQL'];
    try {
        $pdo->query('CREATE DATABASE IF NOT EXISTS `' . $c['dbname'] . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->query('USE `' . $c['dbname'] . '`');
        $sql = file_get_contents(__DIR__ . '/../sql/schema.sql');
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmt) {
            $s = strtoupper(substr(ltrim($stmt), 0, 15));
            if (!empty($stmt) && !str_starts_with($s, 'CREATE DATABASE') && !str_starts_with($s, 'USE ')) {
                $pdo->exec($stmt);
            }
        }
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
    switch ($action) {
        case 'step_status':
            $dbStatus = testDb();
            $whStatus = checkWebhook();
            $configExists = file_exists(__DIR__ . '/../config/db.php');
            echo json_encode(['ok' => true, 'config_exists' => $configExists, 'db' => $dbStatus, 'webhook' => $whStatus]);
            exit;
        case 'save_config':
            echo json_encode(saveConfig($_POST['db_host'] ?? 'localhost', $_POST['db_name'] ?? 'flappybird', $_POST['db_user'] ?? 'root', $_POST['db_pass'] ?? ''));
            exit;
        case 'test_connection':
            $c = $_POST;
            $pdo = null;
            try {
                $pdo = new PDO('mysql:host=' . ($c['db_host'] ?? 'localhost') . ';charset=utf8mb4',
                    $c['db_user'] ?? 'root', $c['db_pass'] ?? '',
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]);
                echo json_encode(['ok' => true, 'message' => 'Connection successful']);
            } catch (PDOException $e) {
                echo json_encode(['ok' => false, 'error' => 'Connection failed: ' . $e->getMessage()]);
            }
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
    .form-grid { display: grid; gap: 12px; margin-bottom: 16px; }
    .form-grid label { color: #aaa; font-size: 0.85em; display: block; margin-bottom: 4px; }
    .form-grid input { width: 100%; padding: 10px 14px; border: 2px solid #0f3460; border-radius: 8px; background: #1a1a2e; color: #fff; font-size: 0.95em; outline: none; }
    .form-grid input:focus { border-color: #4dc9f6; }
    .step-box button { padding: 10px 24px; border: none; border-radius: 8px; background: #e94560; color: #fff; font-size: 1em; font-weight: 600; cursor: pointer; margin-right: 8px; margin-bottom: 8px; }
    .step-box button:hover { background: #ff6b81; }
    .step-box button:disabled { opacity: 0.6; cursor: not-allowed; }
    .step-box button.secondary { background: #0f3460; }
    .step-box button.secondary:hover { background: #16213e; }
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
    <p>Configure your database and Telegram bot</p>

    <div class="progress">
        <div class="step" id="prog-1">1. Credentials</div>
        <div class="step" id="prog-2">2. Migration</div>
        <div class="step" id="prog-3">3. Webhook</div>
        <div class="step" id="prog-4">4. Done</div>
    </div>

    <div class="wizard-step" id="step-1">
        <div class="step-box">
            <h2>Step 1: Database Credentials</h2>
            <p>Enter your MySQL database connection details. These will be saved to <code>config/db.php</code>.</p>
            <div class="form-grid">
                <div><label>Host</label><input type="text" id="db-host" value="localhost" placeholder="localhost"></div>
                <div><label>Database Name</label><input type="text" id="db-name" value="flappybird" placeholder="flappybird"></div>
                <div><label>Username</label><input type="text" id="db-user" value="root" placeholder="root"></div>
                <div><label>Password</label><input type="password" id="db-pass" value="" placeholder="(leave empty if none)"></div>
            </div>
            <button onclick="testConnection()">🔌 Test Connection</button>
            <button id="btn-save-config" onclick="saveConfig()" disabled>💾 Save & Continue</button>
            <p id="db-cred-msg" class="msg"></p>
        </div>
    </div>

    <div class="wizard-step" id="step-2">
        <div class="step-box">
            <h2>Step 2: Database Migration</h2>
            <p>Creates the database tables (<code>users</code>, <code>scores</code>, <code>rewards</code>).</p>
            <div id="migrate-status" class="status"><p class="loading">Checking...</p></div>
            <button id="btn-migrate" onclick="runMigrate()">Run Migration</button>
            <p id="migrate-msg" class="msg"></p>
            <button class="secondary" onclick="goBack(1)">← Back to Credentials</button>
        </div>
    </div>

    <div class="wizard-step" id="step-3">
        <div class="step-box">
            <h2>Step 3: Telegram Webhook</h2>
            <p>Register the Telegram bot webhook so the bot can receive messages.</p>
            <div id="wh-status" class="status"><p class="loading">Checking...</p></div>
            <input type="url" id="webhook-url" placeholder="https://yourdomain.com/bikinweb/flappybird/api/telegram.php" style="width:100%;padding:10px 14px;border:2px solid #0f3460;border-radius:8px;background:#1a1a2e;color:#fff;font-size:0.95em;outline:none;margin-bottom:12px;">
            <button onclick="registerWebhook()">Register Webhook</button>
            <p id="wh-msg" class="msg"></p>
            <button class="secondary" onclick="goBack(2)">← Back to Migration</button>
        </div>
    </div>

    <div class="wizard-step" id="step-4">
        <div class="step-box wizard-done">
            <h2>✅ All Done!</h2>
            <p>Bahlil Birds is fully set up and ready to go.</p>
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

let currentStep = 1;

function goToStep(n) {
    currentStep = n;
    document.querySelectorAll('.wizard-step').forEach(s => s.classList.remove('active'));
    document.getElementById('step-' + n).classList.add('active');
    document.querySelectorAll('.progress .step').forEach(s => s.classList.remove('active'));
    for (let i = 1; i <= 4; i++) {
        const el = document.getElementById('prog-' + i);
        el.classList.remove('active', 'done');
        if (i < n) el.classList.add('done');
        else if (i === n) el.classList.add('active');
    }
}

function goBack(n) { goToStep(n); }

async function checkStatus() {
    const d = await api('step_status');

    if (!d.config_exists) {
        goToStep(1);
        document.getElementById('db-cred-msg').className = 'msg';
        document.getElementById('db-cred-msg').textContent = '';
        return;
    }

    if (!d.db.ok) {
        goToStep(2);
        if (d.db.step === 'connection') {
            document.getElementById('migrate-status').innerHTML = '<p>❌ Cannot connect — check credentials in Step 1</p>';
        } else if (d.db.step === 'db') {
            document.getElementById('migrate-status').innerHTML = '<p class="info">Database does not exist yet — click "Run Migration" to create it.</p>';
            document.getElementById('btn-migrate').disabled = false;
        } else if (d.db.step === 'tables') {
            document.getElementById('migrate-status').innerHTML = '<p class="info">Database exists but tables are missing — click "Run Migration".</p>';
            document.getElementById('btn-migrate').disabled = false;
        } else {
            document.getElementById('migrate-status').innerHTML = '<p>❌ ' + d.db.error + '</p>';
        }
        return;
    }

    // DB ready, check webhook
    document.getElementById('prog-2').classList.add('done');
    if (d.webhook.ok && d.webhook.is_set) {
        goToStep(4);
    } else if (d.webhook.ok) {
        goToStep(3);
        document.getElementById('wh-status').innerHTML = '<p class="info">Webhook not set yet — enter your URL below.</p>';
    } else {
        goToStep(3);
        document.getElementById('wh-status').innerHTML = '<p class="info">' + d.webhook.error + '</p>';
    }
}

async function testConnection() {
    const host = document.getElementById('db-host').value;
    const dbname = document.getElementById('db-name').value;
    const user = document.getElementById('db-user').value;
    const pass = document.getElementById('db-pass').value;
    const d = await api('test_connection', { db_host: host, db_name: dbname, db_user: user, db_pass: pass });
    const el = document.getElementById('db-cred-msg');
    if (d.ok) {
        el.className = 'msg ok';
        el.textContent = '✅ ' + d.message;
        document.getElementById('btn-save-config').disabled = false;
    } else {
        el.className = 'msg err';
        el.textContent = '❌ ' + d.error;
        document.getElementById('btn-save-config').disabled = true;
    }
}

async function saveConfig() {
    const host = document.getElementById('db-host').value;
    const dbname = document.getElementById('db-name').value;
    const user = document.getElementById('db-user').value;
    const pass = document.getElementById('db-pass').value;
    const d = await api('save_config', { db_host: host, db_name: dbname, db_user: user, db_pass: pass });
    const el = document.getElementById('db-cred-msg');
    if (d.ok) {
        el.className = 'msg ok';
        el.textContent = '✅ ' + d.message;
        goToStep(2);
        checkStatus();
    } else {
        el.className = 'msg err';
        el.textContent = '❌ ' + d.error;
    }
}

async function runMigrate() {
    const btn = document.getElementById('btn-migrate');
    btn.disabled = true;
    btn.textContent = 'Running...';
    const d = await api('migrate');
    const el = document.getElementById('migrate-msg');
    if (d.ok) {
        el.className = 'msg ok';
        el.textContent = '✅ ' + d.message;
        document.getElementById('migrate-status').innerHTML = '<p>✅ Database is ready!</p>';
        btn.textContent = 'Done';
        goToStep(3);
        checkStatus();
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
        document.getElementById('wh-status').innerHTML = '<p>✅ Webhook active!</p>';
        goToStep(4);
    } else {
        el.className = 'msg err';
        el.textContent = '❌ ' + d.error;
    }
}

checkStatus();
</script>
</body>
</html>
