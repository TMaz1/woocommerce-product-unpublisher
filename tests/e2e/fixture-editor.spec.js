const { test, expect } = require('@playwright/test');
const { loadFixture, EDITOR_CONFIG } = require('./fixture-helpers');

async function open(page, scheduled) {
	await loadFixture(page, 'editor-row.html', 'wpuAdmin', EDITOR_CONFIG, ['date-utils.js', 'unpublisher.js'], async (p) => {
		if (scheduled) {
			await p.evaluate(() => {
				document.querySelector('#wpu-current-value').textContent = 'December 24, 2026 at 10:00 am';
			});
		}
	});
}

test('OK with a valid date updates the preview and marks the form for saving', async ({ page }) => {
	await open(page, false);
	await page.click('#wpu-edit-toggle');
	await page.fill('#wpu_jj', '25');
	await page.click('#wpu-ok');

	await expect(page.locator('#wpu-current-value')).toHaveText('December 25, 2026 at 10:00 am');
	await expect(page.locator('#wpu_action')).toHaveValue('save');
});

test('an invalid date shows an error and keeps the editor open', async ({ page }) => {
	await open(page, false);
	await page.click('#wpu-edit-toggle');
	await page.fill('#wpu_jj', '31');
	await page.selectOption('#wpu_mm', '01');
	await page.fill('#wpu_aa', '2026');
	await page.click('#wpu-ok');

	await expect(page.locator('#wpu-error')).toHaveText('Choose a date and time in the future.');
	await expect(page.locator('#wpu_action')).toHaveValue('');
});

test('Cancel restores the previous values', async ({ page }) => {
	await open(page, false);
	await page.click('#wpu-edit-toggle');
	await page.fill('#wpu_jj', '30');
	await page.click('#wpu-cancel');

	await expect(page.locator('#wpu_jj')).toHaveValue('24');
	await expect(page.locator('#wpu-current-value')).toHaveText('Not scheduled');
});

test('Update with the editor open and a valid date saves it without clicking OK', async ({ page }) => {
	await open(page, false);
	await page.click('#wpu-edit-toggle');
	await page.click('#publish');

	expect(await page.evaluate(() => window.__submitted)).toBe(1);
	await expect(page.locator('#wpu_action')).toHaveValue('save');
});

test('Update with the editor open and an invalid date is blocked', async ({ page }) => {
	await open(page, false);
	await page.click('#wpu-edit-toggle');
	await page.fill('#wpu_jj', '99');
	await page.click('#publish');

	expect(await page.evaluate(() => window.__submitted)).toBe(0);
});

test('removing an existing schedule asks for confirmation and is committed on Update', async ({ page }) => {
	await open(page, true);
	const messages = [];
	page.on('dialog', async (dialog) => { messages.push(dialog.message()); await dialog.accept(); });

	await page.click('#wpu-edit-toggle');
	await page.click('#wpu-remove');

	expect(messages).toEqual(['Remove the unpublishing schedule?']);
	await expect(page.locator('#wpu_action')).toHaveValue('remove');
	await expect(page.locator('#wpu-current-value')).toHaveText('Schedule will be removed when you update.');

	await page.click('#publish');
	expect(await page.evaluate(() => window.__submitted)).toBe(1);
});

test('dismissing the removal confirmation changes nothing', async ({ page }) => {
	await open(page, true);
	page.on('dialog', (dialog) => dialog.dismiss());

	await page.click('#wpu-edit-toggle');
	await page.click('#wpu-remove');

	await expect(page.locator('#wpu_action')).toHaveValue('');
});

test('removing an unsaved first-time schedule needs no confirmation', async ({ page }) => {
	await open(page, false);
	let prompted = false;
	page.on('dialog', async (dialog) => { prompted = true; await dialog.dismiss(); });

	await page.click('#wpu-edit-toggle');
	await page.fill('#wpu_jj', '26');
	await page.click('#wpu-ok');
	await page.click('#wpu-edit-toggle');
	await page.evaluate(() => { document.querySelector('#wpu-remove').hidden = false; });
	await page.click('#wpu-remove');

	expect(prompted).toBe(false);
	await expect(page.locator('#wpu-current-value')).toHaveText('Not scheduled');
	await expect(page.locator('#wpu_action')).toHaveValue('');
});
