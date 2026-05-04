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
        return new PDO(
            'mysql:host=' . DB_HOST . ($dbName ? ';dbname=' . $dbName : '') . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        return null;
    }
}

function testDb() {
    $pdo = getDb();
    if (!$pdo) return ['ok' => false, 'error' => 'Cannot connect to MySQL at ' . DB_HOST];
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
        foreach ($statements as $stmt) {
            if (!empty($stmt)) $pdo->exec($stmt);
        }
        return ['ok' => true, 'message' => 'Migration completed successfully'];
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => 'Migration failed: ' . $e->getMessage()];
    }
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
        return ['ok' => true, 'is_set' => !empty($url) && $url !== '', 'url' => $url, 'pending_count' => $data['result']['pending_update_count'] ?? 0];
    }
    return ['ok' => false, 'error' => 'Cannot reach Telegram API'];
}

function setWebhook($webhookUrl) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/setWebhook";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['url' => $webhookUrl]),
        CURLOPT_TIMEOUT => 10,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($r, true);
    return $data['ok'] ?? false ? ['ok' => true, 'message' => 'Webhook set to: ' . $webhookUrl] : ['ok' => false, 'error' => $data['description'] ?? 'Unknown error'];
}

function isLoggedIn() {
    return ($_SESSION['admin_logged_in'] ?? false) === true;
}

function handleAction($action) {
    switch ($action) {
        case 'login':
            $user = $_POST['username'] ?? '';
            $pass = $_POST['password'] ?? '';
            if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
                $_SESSION['admin_logged_in'] = true;
                $dbStatus = testDb();
                if (!$dbStatus['ok']) return ['ok' => true, 'redirect' => 'wizard.php'];
                return ['ok' => true];
            }
            return ['ok' => false, 'error' => 'Invalid credentials'];
        case 'logout':
            $_SESSION['admin_logged_in'] = false;
            session_destroy();
            return ['ok' => true];
        case 'status':
            $dbStatus = testDb();
            $whStatus = checkWebhook();
            return ['ok' => true, 'db' => $dbStatus, 'webhook' => $whStatus];
        case 'migrate':
            return runMigration();
        case 'set_webhook':
            $wh = $_POST['webhook_url'] ?? '';
            if (empty($wh)) return ['ok' => false, 'error' => 'Webhook URL required'];
            return setWebhook($wh);
        default:
            return ['ok' => false, 'error' => 'Unknown action'];
    }
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
if ($action) {
    header('Content-Type: application/json');
    $pdo = null;
    echo json_encode(handleAction($action));
    exit;
}

if (isLoggedIn() && !testDb()['ok']) {
    header('Location: wizard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bahlil Birds Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="admin-wrap">
<?php if (!isLoggedIn()): ?>
    <div class="card login-card">
        <h1>🔧 Admin Panel</h1>
        <form id="login-form">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
            <p id="login-error" class="error"></p>
        </form>
        <p class="back-link"><a href="../">← Back to Game</a></p>
    </div>

    <script>
    document.getElementById('login-form').onsubmit = async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.append('action', 'login');
        const r = await fetch('?', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            if (d.redirect) location.href = d.redirect;
            else location.reload();
        }
        else document.getElementById('login-error').textContent = d.error;
    };
    </script>
<?php else: ?>
    <div class="admin-header">
        <h1>🔧 Bahlil Birds Admin</h1>
        <div class="admin-nav">
            <a href="#" class="tab active" data-tab="dashboard">Dashboard</a>
            <a href="#" class="tab" data-tab="webhook">Webhook</a>
            <a href="#" class="tab" data-tab="migration">Migration</a>
            <a href="?action=logout" class="logout">Logout</a>
        </div>
    </div>

    <div id="tab-dashboard" class="tab-content active">
        <h2>System Status</h2>
        <div id="status-cards"><p class="loading">Loading...</p></div>
    </div>

    <div id="tab-webhook" class="tab-content">
        <h2>Telegram Webhook</h2>
        <div id="webhook-status"></div>
        <form id="webhook-form">
            <label>Webhook URL</label>
            <input type="url" name="webhook_url" id="webhook-url-input"
                placeholder="https://yourdomain.com/bikinweb/flappybird/api/telegram.php" required>
            <button type="submit">Register Webhook</button>
            <p id="webhook-msg" class="msg"></p>
        </form>
    </div>

    <div id="tab-migration" class="tab-content">
        <h2>Database Migration</h2>
        <div id="migration-status"></div>
        <button id="run-migration" class="btn-primary">Run Migration</button>
        <p id="migration-msg" class="msg"></p>
    </div>

    <script>
    async function api(action, body) {
        const fd = new FormData();
        fd.append('action', action);
        if (body) for (let [k, v] of Object.entries(body)) fd.append(k, v);
        const r = await fetch('?', { method: 'POST', body: fd });
        return r.json();
    }

    function statusHtml(d) {
        const db = d.db;
        const wh = d.webhook;
        let html = '';
        html += '<div class="status-card ' + (db.ok ? 'ok' : 'fail') + '">';
        html += '<h3>Database</h3>';
        if (db.ok) html += '<p>✅ ' + db.message + '</p>';
        else html += '<p>❌ ' + db.error + '</p>';
        html += '</div>';

        html += '<div class="status-card ' + (wh.ok ? (wh.is_set ? 'ok' : 'warn') : 'fail') + '">';
        html += '<h3>Telegram Webhook</h3>';
        if (wh.ok && wh.is_set) html += '<p>✅ Active: ' + wh.url + '</p><p>Pending updates: ' + wh.pending_count + '</p>';
        else if (wh.ok && !wh.is_set) html += '<p>⚠️ Not set</p>';
        else html += '<p>❌ ' + wh.error + '</p>';
        html += '</div>';

        html += '<div class="status-card info">';
        html += '<h3>Bot Token</h3>';
        html += '<p>Token: <code>8790094820:...ms</code></p>';
        html += '<p>Bot: <a href="https://t.me/flappy1212_bot" target="_blank">@flappy1212_bot</a></p>';
        html += '</div>';

        return html;
    }

    async function loadStatus() {
        const d = await api('status');
        if (d.ok) document.getElementById('status-cards').innerHTML = statusHtml(d);
        else document.getElementById('status-cards').innerHTML = '<p class="error">Failed to get status</p>';
    }

    document.querySelectorAll('.tab').forEach(t => {
        t.onclick = (e) => {
            if (e.target.classList.contains('logout')) return;
            e.preventDefault();
            document.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(x => x.classList.remove('active'));
            e.target.classList.add('active');
            document.getElementById('tab-' + e.target.dataset.tab).classList.add('active');
            if (e.target.dataset.tab === 'dashboard') loadStatus();
            if (e.target.dataset.tab === 'webhook') loadWebhookStatus();
            if (e.target.dataset.tab === 'migration') loadMigrationStatus();
        };
    });

    async function loadWebhookStatus() {
        const d = await api('status');
        const el = document.getElementById('webhook-status');
        if (d.ok && d.webhook.ok) {
            el.innerHTML = d.webhook.is_set
                ? '<p class="ok">✅ Webhook is active: <code>' + d.webhook.url + '</code></p>'
                : '<p class="warn">⚠️ Webhook is not set</p>';
            if (d.webhook.url) document.getElementById('webhook-url-input').value = d.webhook.url;
        } else {
            el.innerHTML = '<p class="error">❌ Cannot check webhook</p>';
        }
    }

    async function loadMigrationStatus() {
        const d = await api('status');
        const el = document.getElementById('migration-status');
        if (d.ok && d.db.ok) el.innerHTML = '<p class="ok">✅ Database is up to date</p>';
        else if (d.ok && !d.db.ok && d.db.step === 'db') el.innerHTML = '<p class="warn">⚠️ Database does not exist. Click "Run Migration" to create it.</p>';
        else if (d.ok && !d.db.ok && d.db.step === 'tables') el.innerHTML = '<p class="warn">⚠️ Database exists but tables are missing. Click "Run Migration" to create them.</p>';
        else if (d.ok) el.innerHTML = '<p class="error">❌ Database error: ' + d.db.error + '</p>';
        else el.innerHTML = '<p class="error">❌ Cannot check status</p>';
    }

    document.getElementById('webhook-form').onsubmit = async (e) => {
        e.preventDefault();
        const url = document.getElementById('webhook-url-input').value;
        const d = await api('set_webhook', { webhook_url: url });
        const el = document.getElementById('webhook-msg');
        el.className = d.ok ? 'msg ok' : 'msg error';
        el.textContent = d.ok ? d.message : d.error;
    };

    document.getElementById('run-migration').onclick = async () => {
        const btn = document.getElementById('run-migration');
        btn.disabled = true;
        btn.textContent = 'Running...';
        const d = await api('migrate');
        const el = document.getElementById('migration-msg');
        el.className = d.ok ? 'msg ok' : 'msg error';
        el.textContent = d.ok ? d.message : d.error;
        btn.disabled = false;
        btn.textContent = 'Run Migration';
        loadMigrationStatus();
    };

    loadStatus();
    </script>
<?php endif; ?>
</div>
</body>
</html>
