const { test, expect } = require('@playwright/test');

const BASE = 'http://localhost/bikinweb/flappybird';

test.describe('API - User', () => {
  const testUser = `test_${Date.now()}`;

  test('create user', async ({ request }) => {
    const res = await request.post(`${BASE}/api/user.php?action=create`, {
      data: { username: testUser },
    });
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data.user.username).toBe(testUser);
  });

  test('get user', async ({ request }) => {
    await request.post(`${BASE}/api/user.php?action=create`, {
      data: { username: testUser },
    });
    const res = await request.get(`${BASE}/api/user.php?action=get&username=${testUser}`);
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data.user.username).toBe(testUser);
    expect(data.rewards).toBeDefined();
  });

  test('create duplicate returns existing', async ({ request }) => {
    const res1 = await request.post(`${BASE}/api/user.php?action=create`, {
      data: { username: testUser },
    });
    const d1 = await res1.json();
    const res2 = await request.post(`${BASE}/api/user.php?action=create`, {
      data: { username: testUser },
    });
    const d2 = await res2.json();
    expect(d2.user.id).toBe(d1.user.id);
    expect(d2.exists).toBe(true);
  });

  test('get nonexistent user returns 404', async ({ request }) => {
    const res = await request.get(`${BASE}/api/user.php?action=get&username=nonexistent_user_xyz`);
    expect(res.status()).toBe(404);
  });

  test('link telegram', async ({ request }) => {
    await request.post(`${BASE}/api/user.php?action=create`, {
      data: { username: testUser },
    });
    const res = await request.post(`${BASE}/api/user.php?action=link_tg`, {
      data: { username: testUser, telegram_id: '123456' },
    });
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data.success).toBe(true);
  });
});

test.describe('API - Reward', () => {
  const rUser = `reward_test_${Date.now()}`;

  test('submit score awards coins', async ({ request }) => {
    await request.post(`${BASE}/api/user.php?action=create`, {
      data: { username: rUser },
    });
    const res = await request.post(`${BASE}/api/reward.php?action=submit`, {
      data: { username: rUser, score: 15 },
    });
    const data = await res.json();
    expect(data.coins_earned).toBe(15);
    expect(data.pending_coins).toBe(15);
  });

  test('balance reflects submitted scores', async ({ request }) => {
    await request.post(`${BASE}/api/reward.php?action=submit`, {
      data: { username: rUser, score: 10 },
    });
    const res = await request.get(`${BASE}/api/reward.php?action=balance&username=${rUser}`);
    const data = await res.json();
    expect(data.pending_coins).toBeGreaterThanOrEqual(10);
  });

  test('claim marks coins as claimed', async ({ request }) => {
    const res = await request.post(`${BASE}/api/reward.php?action=claim`, {
      data: { username: rUser },
    });
    const data = await res.json();
    expect(data.claimed).toBeGreaterThan(0);

    const bal = await request.get(`${BASE}/api/reward.php?action=balance&username=${rUser}`);
    const b = await bal.json();
    expect(b.pending_coins).toBe(0);
  });

  test('claim with no pending returns 0', async ({ request }) => {
    const res = await request.post(`${BASE}/api/reward.php?action=claim`, {
      data: { username: rUser },
    });
    const data = await res.json();
    expect(data.claimed).toBe(0);
  });

  test('history returns scores', async ({ request }) => {
    const res = await request.get(`${BASE}/api/reward.php?action=history&username=${rUser}`);
    const data = await res.json();
    expect(Array.isArray(data.history)).toBe(true);
  });

  test('submit for nonexistent user', async ({ request }) => {
    const res = await request.post(`${BASE}/api/reward.php?action=submit`, {
      data: { username: 'nobody_xyz', score: 5 },
    });
    expect(res.status()).toBe(404);
  });
});
