const API_BASE = 'api/reward.php';
const USER_API = 'api/user.php';

const canvas = document.getElementById('game-canvas');
const ctx = canvas.getContext('2d');

const loginScreen = document.getElementById('login-screen');
const gameOverlay = document.getElementById('game-overlay');
const usernameInput = document.getElementById('username-input');
const startBtn = document.getElementById('start-btn');
const loginMsg = document.getElementById('login-msg');
const restartBtn = document.getElementById('restart-btn');
const overlayTitle = document.getElementById('overlay-title');
const overlayScore = document.getElementById('overlay-score');
const overlayCoins = document.getElementById('overlay-coins');
const overlayTotal = document.getElementById('overlay-total');
const telegramClaimLink = document.getElementById('telegram-claim-link');

let username = '';
let gameRunning = false;
let gameLoopId = null;

const GRAVITY = 0.5;
const FLAP_VELOCITY = -8;
const PIPE_WIDTH = 70;
const PIPE_GAP = 150;
const PIPE_SPACING = 180;
const GROUND_Y = 580;
const BIRD_RADIUS = 16;

// Sprite sheet
const SPRITE_SRC = 'game/assets/bird-sprite-sm.png';
const FRAME_COUNT = 4;
const FRAME_W = 48;
const FRAME_H = 128;

// Display and forgiving collision box
const BIRD_DISP_W = 30;
const BIRD_DISP_H = 80;
const BIRD_COL_W = 10;
const BIRD_COL_H = 24;
const ANIM_SPEED = 6;

let bird = { x: 80, y: 300, vy: 0, frame: 0, animTick: 0 };
let pipes = [];
let score = 0;
let frameCount = 0;

const spriteImg = new Image();
spriteImg.src = SPRITE_SRC;

function loginUser(name) {
    return fetch(USER_API + '?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username: name })
    }).then(r => r.json());
}

function submitScore(name, sc) {
    return fetch(API_BASE + '?action=submit', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username: name, score: sc })
    }).then(r => r.json());
}

startBtn.addEventListener('click', async () => {
    const name = usernameInput.value.trim();
    if (!name || name.length < 2) {
        loginMsg.textContent = 'Username must be at least 2 characters';
        return;
    }
    loginMsg.textContent = 'Loading...';
    try {
        const res = await loginUser(name);
        if (res.error) {
            loginMsg.textContent = res.error;
            return;
        }
        username = name;
        loginScreen.style.display = 'none';
        canvas.style.display = 'block';
        telegramClaimLink.href = 'https://t.me/flappy1212_bot?start=' + encodeURIComponent(name);
        startGame();
    } catch {
        loginMsg.textContent = 'Connection error. Is the server running?';
    }
});

usernameInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') startBtn.click();
});

restartBtn.addEventListener('click', () => {
    gameOverlay.style.display = 'none';
    startGame();
});

function startGame() {
    bird = { x: 80, y: 300, vy: 0, frame: 0, animTick: 0 };
    pipes = [];
    score = 0;
    frameCount = 0;
    addPipe();
    if (!gameRunning) {
        gameRunning = true;
        gameLoopId = requestAnimationFrame(gameLoop);
    }
}

function addPipe() {
    const minH = 60;
    const maxH = GROUND_Y - PIPE_GAP - 60;
    const topH = minH + Math.random() * (maxH - minH);
    pipes.push({
        x: canvas.width,
        topHeight: topH,
        scored: false
    });
}

function update() {
    frameCount++;

    bird.vy += GRAVITY;
    bird.y += bird.vy;

    if (bird.y - BIRD_COL_H < 0 || bird.y + BIRD_COL_H > GROUND_Y) {
        endGame();
        return;
    }

    if (pipes.length === 0 || pipes[pipes.length - 1].x < canvas.width - PIPE_SPACING) {
        addPipe();
    }

    for (let i = pipes.length - 1; i >= 0; i--) {
        pipes[i].x -= 2;

        if (pipes[i].x + PIPE_WIDTH < 0) {
            pipes.splice(i, 1);
            continue;
        }

        if (!pipes[i].scored && pipes[i].x + PIPE_WIDTH < bird.x) {
            pipes[i].scored = true;
            score++;
        }

        const pipeRight = pipes[i].x + PIPE_WIDTH;
        const pipeLeft = pipes[i].x;
        const topBottom = pipes[i].topHeight;
        const bottomTop = pipes[i].topHeight + PIPE_GAP;

        if (
            bird.x + BIRD_COL_W > pipeLeft &&
            bird.x - BIRD_COL_W < pipeRight &&
            (bird.y - BIRD_COL_H < topBottom || bird.y + BIRD_COL_H > bottomTop)
        ) {
            endGame();
            return;
        }
    }

    bird.animTick++;
    if (bird.animTick >= ANIM_SPEED) {
        bird.animTick = 0;
        bird.frame = (bird.frame + 1) % FRAME_COUNT;
    }

    while (pipes.length > 4) pipes.shift();
}

function draw() {
    // Sky gradient
    const grad = ctx.createLinearGradient(0, 0, 0, canvas.height);
    grad.addColorStop(0, '#4dc9f6');
    grad.addColorStop(0.6, '#87ceeb');
    grad.addColorStop(1, '#228b22');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // Clouds
    ctx.fillStyle = 'rgba(255,255,255,0.6)';
    drawCloud(100 - (frameCount * 0.3 % 600), 80, 60);
    drawCloud(350 - (frameCount * 0.2 % 600), 50, 45);
    drawCloud(550 - (frameCount * 0.25 % 700), 100, 50);

    // Ground
    ctx.fillStyle = '#8B4513';
    ctx.fillRect(0, GROUND_Y, canvas.width, canvas.height - GROUND_Y);
    ctx.fillStyle = '#228B22';
    ctx.fillRect(0, GROUND_Y, canvas.width, 8);

    // Pipes
    for (const pipe of pipes) {
        const x = pipe.x;
        const topH = pipe.topHeight;
        const bottomY = topH + PIPE_GAP;

        // Top pipe
        ctx.fillStyle = '#228B22';
        ctx.fillRect(x, 0, PIPE_WIDTH, topH);
        ctx.fillStyle = '#2E7D32';
        ctx.fillRect(x - 4, topH - 20, PIPE_WIDTH + 8, 20);
        ctx.strokeStyle = '#1B5E20';
        ctx.lineWidth = 2;
        ctx.strokeRect(x, 0, PIPE_WIDTH, topH);

        // Bottom pipe
        ctx.fillStyle = '#228B22';
        ctx.fillRect(x, bottomY, PIPE_WIDTH, canvas.height - bottomY);
        ctx.fillStyle = '#2E7D32';
        ctx.fillRect(x - 4, bottomY, PIPE_WIDTH + 8, 20);
        ctx.strokeRect(x, bottomY, PIPE_WIDTH, canvas.height - bottomY);
    }

    // Bird shadow
    ctx.fillStyle = 'rgba(0,0,0,0.15)';
    ctx.beginPath();
    ctx.ellipse(bird.x + 3, bird.y + 6, 14, 6, -0.2, 0, Math.PI * 2);
    ctx.fill();

    // Bird sprite
    const rotation = Math.min(Math.max(bird.vy * 0.06, -0.5), 0.8);
    ctx.save();
    ctx.translate(bird.x, bird.y);
    ctx.rotate(rotation);

    if (spriteImg.complete && spriteImg.naturalWidth > 0) {
        ctx.drawImage(
            spriteImg,
            bird.frame * FRAME_W, 0, FRAME_W, FRAME_H,
            -BIRD_DISP_W / 2, -BIRD_DISP_H / 2, BIRD_DISP_W, BIRD_DISP_H
        );
    } else {
        ctx.fillStyle = '#FFD700';
        ctx.beginPath();
        ctx.arc(0, 0, 16, 0, Math.PI * 2);
        ctx.fill();
    }

    ctx.restore();

    // Score
    ctx.fillStyle = '#fff';
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 4;
    ctx.font = 'bold 32px "Segoe UI", system-ui';
    ctx.textAlign = 'center';
    ctx.strokeText(score, canvas.width / 2, 50);
    ctx.fillText(score, canvas.width / 2, 50);

    // Instructions
    if (score === 0 && frameCount < 120 && bird.y > 250 && bird.y < 350) {
        ctx.fillStyle = 'rgba(255,255,255,0.8)';
        ctx.font = '16px "Segoe UI", system-ui';
        ctx.fillText('Press SPACE or tap to flap!', canvas.width / 2, 200);
    }
}

function drawCloud(x, y, size) {
    ctx.beginPath();
    ctx.arc(x, y, size * 0.5, 0, Math.PI * 2);
    ctx.arc(x + size * 0.4, y - size * 0.2, size * 0.4, 0, Math.PI * 2);
    ctx.arc(x + size * 0.8, y, size * 0.45, 0, Math.PI * 2);
    ctx.arc(x + size * 0.4, y + size * 0.15, size * 0.35, 0, Math.PI * 2);
    ctx.fill();
}

function gameLoop() {
    if (!gameRunning) return;
    update();
    draw();
    gameLoopId = requestAnimationFrame(gameLoop);
}

async function endGame() {
    gameRunning = false;
    if (gameLoopId) cancelAnimationFrame(gameLoopId);
    gameLoopId = null;

    let coins = 0;
    let pending = 0;
    try {
        const res = await submitScore(username, score);
        coins = res.coins_earned || score;
        pending = res.pending_coins || '?';
    } catch {
        coins = score;
        pending = '?';
    }

    overlayTitle.textContent = score > 0 ? 'Game Over' : 'Oh no!';
    overlayScore.textContent = 'Score: ' + score;
    overlayCoins.textContent = 'Ready to claim: ' + coins + ' coins';
    overlayTotal.textContent = 'Claim on @flappy1212_bot';

    gameOverlay.style.display = 'flex';
}

document.addEventListener('keydown', (e) => {
    if (e.code === 'Space' || e.code === 'ArrowUp') {
        e.preventDefault();
        if (gameRunning) {
            bird.vy = FLAP_VELOCITY;
        }
    }
});

canvas.addEventListener('click', () => {
    if (gameRunning) {
        bird.vy = FLAP_VELOCITY;
    }
});

canvas.addEventListener('touchstart', (e) => {
    e.preventDefault();
    if (gameRunning) {
        bird.vy = FLAP_VELOCITY;
    }
}, { passive: false });
