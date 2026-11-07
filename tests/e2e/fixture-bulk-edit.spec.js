const { test, expect } = require('@playwright/test');
const { loadFixture, BULK_CONFIG } = require('./fixture-helpers');

async function open(page) {
	await loadFixture(page, 'products-list.html', 'wpuBulk', BULK_CONFIG, ['date-utils.js', 'bulk-edit.js']);
}

const choose = (page, value) => page.check('input[name="wpu_bulk_action"][value="' + value + '"]');
const select = async (page, ...ids) => { for (const id of ids) { await page.check('#cb-select-' + id); } };
const submitted = (page) => page.evaluate(() => window.__submitted);

test.describe('bulk edit: date fields', () => {
	test('are hidden by default and toggle with the radio choice', async ({ page }) => {
		await open(page);
		const fields = page.locator('#wpu-bulk-date-fields');

		await expect(fields).toBeHidden();

		await choose(page, 'schedule');
		await expect(fields).toBeVisible();
		await expect(page.locator('#wpu_bulk_jj')).toBeEnabled();

		for (const value of ['remove', 'unpublish_now', 'none']) {
			await choose(page, value);
			await expect(fields).toBeHidden();
			await expect(page.locator('#wpu_bulk_jj')).toBeDisabled();
		}
	});

	test('Cancel resets the choice to "No change"', async ({ page }) => {
		await open(page);
		await choose(page, 'schedule');
		await page.click('#bulk-edit .cancel');

		await expect(page.locator('input[name="wpu_bulk_action"][value="none"]')).toBeChecked();
		await expect(page.locator('#wpu-bulk-date-fields')).toBeHidden();
	});
});

test.describe('bulk edit: schedule validation', () => {
	test('a valid future date submits', async ({ page }) => {
		await open(page);
		await select(page, 101);
		await choose(page, 'schedule');
		await page.click('#bulk_edit');

		expect(await submitted(page)).toBe(1);
	});

	test('a past date is blocked with an explanation', async ({ page }) => {
		await open(page);
		await select(page, 101);
		await choose(page, 'schedule');
		await page.selectOption('#wpu_bulk_mm', '01');
		await page.click('#bulk_edit');

		expect(await submitted(page)).toBe(0);
		await expect(page.locator('#wpu-bulk-error')).toHaveText('Choose a date and time in the future.');
	});

	test('an impossible date is blocked', async ({ page }) => {
		await open(page);
		await select(page, 101);
		await choose(page, 'schedule');
		await page.fill('#wpu_bulk_jj', '45');
		await page.click('#bulk_edit');

		expect(await submitted(page)).toBe(0);
		await expect(page.locator('#wpu-bulk-error')).toHaveText('Please enter a valid date and time.');
	});
});

test.describe('bulk edit: confirmation dialogs', () => {
	test('unpublish now asks first, with counts, and proceeds on accept', async ({ page }) => {
		await open(page);
		const messages = [];
		page.on('dialog', async (dialog) => { messages.push(dialog.message()); await dialog.accept(); });

		await select(page, 101, 102, 103);
		await choose(page, 'unpublish_now');
		await page.click('#bulk_edit');

		expect(messages).toHaveLength(1);
		expect(messages[0]).toContain('Unpublish 2 selected published products now?');
		expect(messages[0]).toContain('1 selected product is not published');
		expect(await submitted(page)).toBe(1);
	});

	test('unpublish now does nothing when the dialog is dismissed', async ({ page }) => {
		await open(page);
		page.on('dialog', (dialog) => dialog.dismiss());

		await select(page, 101);
		await choose(page, 'unpublish_now');
		await page.click('#bulk_edit');

		expect(await submitted(page)).toBe(0);
	});

	test('unpublish now with only non-published products alerts and does not submit', async ({ page }) => {
		await open(page);
		const messages = [];
		page.on('dialog', async (dialog) => { messages.push(dialog.type() + ':' + dialog.message()); await dialog.accept(); });

		await select(page, 103);
		await choose(page, 'unpublish_now');
		await page.click('#bulk_edit');

		expect(messages).toEqual(['alert:None of the selected products are published, so there is nothing to unpublish.']);
		expect(await submitted(page)).toBe(0);
	});

	test('remove schedule asks for confirmation', async ({ page }) => {
		await open(page);
		const messages = [];
		page.on('dialog', async (dialog) => { messages.push(dialog.message()); await dialog.dismiss(); });

		await select(page, 101, 102);
		await choose(page, 'remove');
		await page.click('#bulk_edit');

		expect(messages).toEqual(['Remove the scheduled unpublishing from 2 selected products?']);
		expect(await submitted(page)).toBe(0);
	});

	test('"No change" never prompts', async ({ page }) => {
		await open(page);
		let prompted = false;
		page.on('dialog', async (dialog) => { prompted = true; await dialog.dismiss(); });

		await select(page, 101);
		await page.click('#bulk_edit');

		expect(prompted).toBe(false);
		expect(await submitted(page)).toBe(1);
	});

	test('submitting with nothing selected is left to WordPress', async ({ page }) => {
		await open(page);
		let prompted = false;
		page.on('dialog', async (dialog) => { prompted = true; await dialog.dismiss(); });

		await choose(page, 'unpublish_now');
		await page.click('#bulk_edit');

		expect(prompted).toBe(false);
		expect(await submitted(page)).toBe(1);
	});
});
