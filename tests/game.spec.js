const { test, expect } = require('@playwright/test');

const URL = 'http://localhost/bikinweb/flappybird/';

test.describe('Game Page', () => {
  test('page loads with login screen', async ({ page }) => {
    await page.goto(URL);
    await expect(page.locator('#login-screen')).toBeVisible();
    await expect(page.locator('#username-input')).toBeVisible();
    await expect(page.locator('#start-btn')).toBeVisible();
  });

  test('canvas is hidden before login', async ({ page }) => {
    await page.goto(URL);
    await expect(page.locator('canvas')).toBeHidden();
  });

  test('error on empty username', async ({ page }) => {
    await page.goto(URL);
    await page.locator('#start-btn').click();
    await expect(page.locator('#login-msg')).toContainText('at least');
  });

  test('error on short username', async ({ page }) => {
    await page.goto(URL);
    await page.locator('#username-input').fill('a');
    await page.locator('#start-btn').click();
    await expect(page.locator('#login-msg')).toContainText('at least');
  });

  test('successful login shows canvas', async ({ page }) => {
    const name = `player_${Date.now()}`;
    await page.goto(URL);

    // Mock the API response so test works offline
    await page.route('**/api/user.php*', async route => {
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          user: { id: 1, username: name, telegram_id: null, created_at: '2024-01-01' },
          created: true
        })
      });
    });

    await page.locator('#username-input').fill(name);
    await page.locator('#start-btn').click();
    await page.waitForTimeout(500);
    await expect(page.locator('#login-screen')).toBeHidden();
    await expect(page.locator('canvas')).toBeVisible();
  });

  test('spacebar flaps bird', async ({ page }) => {
    const name = `player_${Date.now()}`;
    await page.goto(URL);

    await page.route('**/api/user.php*', async route => {
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          user: { id: 1, username: name },
          created: true
        })
      });
    });

    await page.locator('#username-input').fill(name);
    await page.locator('#start-btn').click();
    await page.waitForTimeout(300);
    await expect(page.locator('canvas')).toBeVisible();

    // Game should respond to spacebar
    await page.keyboard.press('Space');
    await page.waitForTimeout(100);
    // Bird should still be alive (not game over yet)
    await expect(page.locator('#game-overlay')).toBeHidden();
  });

  test('game over overlay appears after death', async ({ page }) => {
    const name = `player_${Date.now()}`;
    await page.goto(URL);

    await page.route('**/api/user.php*', async route => {
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          user: { id: 1, username: name },
          created: true
        })
      });
    });

    await page.route('**/api/reward.php*', async route => {
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          score: 0, coins_earned: 0, total_coins: 0,
          claimed_coins: 0, unclaimed_coins: 0
        })
      });
    });

    await page.locator('#username-input').fill(name);
    await page.locator('#start-btn').click();
    await page.waitForTimeout(300);

    // Wait for bird to hit ground (gravity pulls it down, no flaps)
    await page.waitForSelector('#game-overlay', { state: 'visible', timeout: 10000 });
    await expect(page.locator('#overlay-title')).toBeVisible();
  });

  test('restart button works', async ({ page }) => {
    const name = `player_${Date.now()}`;
    await page.goto(URL);

    await page.route('**/api/user.php*', async route => {
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          user: { id: 1, username: name },
          created: true
        })
      });
    });

    await page.route('**/api/reward.php*', async route => {
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          score: 0, coins_earned: 0, total_coins: 0,
          claimed_coins: 0, unclaimed_coins: 0
        })
      });
    });

    await page.locator('#username-input').fill(name);
    await page.locator('#start-btn').click();
    await page.waitForTimeout(300);
    await page.waitForSelector('#game-overlay', { state: 'visible', timeout: 10000 });

    await page.locator('#restart-btn').click();
    await page.waitForTimeout(300);
    await expect(page.locator('#game-overlay')).toBeHidden();
    await expect(page.locator('canvas')).toBeVisible();
  });
});
