<p align="center">
  <img src="game/assets/bahlil-logo.png" alt="Bahlil Birds" width="300">
</p>

<h1 align="center">🐦 Bahlil Birds</h1>

<p align="center">
  <strong>A Flappy Bird-inspired game with Telegram bot integration, coin rewards, and AI-powered chat.</strong>
</p>

<p align="center">
  <a href="https://t.me/flappy1212_bot"><img src="https://img.shields.io/badge/Telegram-Bot-0088cc?logo=telegram" alt="Telegram Bot"></a>
  <img src="https://img.shields.io/badge/PHP-8.0+-777BB4?logo=php" alt="PHP">
  <img src="https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql" alt="MySQL">
  <img src="https://img.shields.io/badge/Playwright-Tests-2EAD33?logo=playwright" alt="Playwright">
</p>

---

## ✨ Features

- **Canvas-based Flappy Bird** — Smooth physics, pipe generation, sprite animation, responsive touch/click/space controls
- **Custom Sprite Support** — Drop in your own sprite sheets (4-frame animation)
- **Username Auth** — No password needed. Just enter a username and play
- **Coin Reward System** — Earn 1 coin per point. Coins go to a **pending pool** until you claim them via Telegram
- **Telegram Bot** — Claim coins, check balance, switch accounts, rename, view stats — all through natural chat
- **AI-powered Bot** — Uses LLM7 API for human-like, context-aware conversations. Say "claim my coins" and the bot executes the action
- **Account Switching** — Link multiple game accounts to one Telegram. Use `/switch` or say "login as angga"
- **Admin Panel** — Webhook registration, DB migration, system status monitoring
- **Setup Wizard** — First-time deployment wizard auto-detects missing setup and guides step by step
- **Playwright Tests** — 19 automated tests covering API endpoints and browser gameplay

---

## 🚀 Quick Start

### Requirements

| Dependency | Version |
|------------|---------|
| PHP | 8.0+ |
| MySQL | 8.0+ |
| Web Server | Apache / Nginx (or Laragon) |
| Node.js | 18+ (for tests only) |

### Installation

```bash
# 1. Clone the repo
git clone https://github.com/abbayosua/bahlil-birds.git
cd bahlil-birds

# 2. Create the database
mysql -u root < sql/schema.sql

# 3. Set up the Telegram bot webhook
#    Open in your browser:
#    http://localhost/bikinweb/flappybird/admin/wizard.php
#    Login: admin / bahlil
#    Follow the 3-step wizard
```

### Or use the setup wizard

1. Open `http://localhost/bikinweb/flappybird/admin/`
2. Login with `admin` / `bahlil`
3. If DB is not ready → redirected to the **wizard** automatically
4. Step 1: **Database** → click "Create Database & Tables"
5. Step 2: **Webhook** → enter your domain URL + register
6. Step 3: **Done** → links to play the game

---

## 🎮 How to Play

```
1. Open http://localhost/bikinweb/flappybird/
2. Enter any username (e.g. "soeb")
3. Press SPACE or click to flap
4. Avoid the pipes — each pipe passed = 1 point = 1 coin
5. Game over → coins go to your "pending" pool
6. Open Telegram → @flappy1212_bot → claim your coins!
```

### Controls

| Input | Action |
|-------|--------|
| `SPACE` / `↑` | Flap |
| Click / Tap | Flap |

---

## 🤖 Telegram Bot

Bot: **[@flappy1212_bot](https://t.me/flappy1212_bot)**

### Commands

| Command | Description |
|---------|-------------|
| `/start` | Start bot & link your game account |
| `/claim` | Claim pending coins to your balance |
| `/balance` | Check your claimed vs pending coins |
| `/info` | Full account information |
| `/name <new>` | Change your username |
| `/switch <user>` | Switch to a different game account |
| `/help` | Show all commands |

### Natural Language

You don't need to remember commands. Just chat naturally:

```
You: "claim my coins"
Bot: ✅ 50 coins claimed! Your balance is now 150.

You: "what's my balance"
Bot: Balance: 150 claimed, 20 pending to claim.

You: "login as angga"
Bot: Switched to angga! You have 10 coins pending.

You: "call me superbird"
Bot: Done! You're now superbird.
```

---

## 🗄️ Database Schema

### `users`
| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK) | Auto-increment |
| `username` | VARCHAR(50) | Game username (unique) |
| `telegram_id` | VARCHAR(50) | Linked Telegram account ID |
| `created_at` | DATETIME | Account creation date |

### `scores`
| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK) | Auto-increment |
| `user_id` | INT (FK) | References users.id |
| `score` | INT | Points scored |
| `coins_earned` | INT | Coins earned (= score) |
| `created_at` | DATETIME | When the game was played |

### `rewards`
| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK) | Auto-increment |
| `user_id` | INT (FK) | References users.id |
| `claimed_coins` | INT | Coins claimed via bot (balance) |
| `pending_coins` | INT | Coins earned but not yet claimed |
| `updated_at` | DATETIME | Last update timestamp |

---

## 📁 Project Structure

```
bahlil-birds/
├── index.html              ← Game page (first page visitors see)
├── game/
│   ├── game.js             ← Game engine (physics, rendering, API)
│   ├── style.css           ← Game styling
│   └── assets/
│       ├── bahlil-logo.png ← Splash screen logo
│       ├── bird-sprite.png ← Original sprite sheet
│       ├── bird-sprite-sm.png ← Resized sprite sheet
│       └── newbahlil.png   ← Alternative sprite sheet
├── api/
│   ├── config.php          ← DB connection + helpers
│   ├── user.php            ← User CRUD endpoints
│   ├── reward.php          ← Score/reward endpoints
│   ├── telegram.php        ← Telegram webhook endpoint
│   └── bot.php             ← Bot logic + LLM7 API
├── admin/
│   ├── index.php           ← Admin panel (login + dashboard)
│   ├── wizard.php          ← Setup wizard
│   └── style.css           ← Admin styling
├── sql/
│   └── schema.sql          ← Database schema
├── tests/
│   ├── api.spec.js         ← API endpoint tests (12)
│   └── game.spec.js        ← Browser gameplay tests (7)
├── scripts/
│   └── set-webhook.php     ← CLI webhook setup script
├── package.json
├── playwright.config.js
└── README.md
```

---

## 🧪 Running Tests

```bash
# Install Playwright (uses system Chrome, no download)
set PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1
pnpm install

# Run all 19 tests
pnpm test

# Run with browser visible
pnpm test:headed
```

---

## 🛠️ Admin Panel

| URL | `http://localhost/bikinweb/flappybird/admin/` |
|-----|-----------------------------------------------|
| Login | `admin` / `bahlil` |

| Tab | Purpose |
|-----|---------|
| **Dashboard** | System status: DB connection + Webhook status |
| **Webhook** | Register/update Telegram bot webhook URL |
| **Migration** | Run database migrations |
| **Wizard** | First-time setup (auto-redirects if not configured) |

---

## 🌐 API Endpoints

### Game API

| Endpoint | Methods | Actions |
|----------|---------|---------|
| `api/user.php` | GET/POST | `create`, `get`, `link_tg` |
| `api/reward.php` | GET/POST | `submit`, `balance`, `claim`, `history` |
| `api/telegram.php` | POST | Bot webhook (Telegram → server) |

---

## 📜 License

This project is open source. Feel free to modify and use.

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/abbayosua">abbayosua</a>
</p>
