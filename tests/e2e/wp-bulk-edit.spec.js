/**
 * End-to-end: real WordPress + WooCommerce (npm run env:start).
 * Drives the native Bulk Edit panel on Products > All Products.
 */
const { test, expect } = require('@playwright/test');
const { wp, createProduct, status, scheduledMeta, cronEvents, login } = require('./wp-helpers');

const LIST = '/wp-admin/edit.php?post_type=product';

async function openBulkEdit(page, ids) {
	await page.goto(LIST + '&post_status=all');
	for (const id of ids) {
		await page.check(`#cb-select-${id}`);
	}
	await page.selectOption('#bulk-action-selector-top', 'edit');
	await page.click('#doaction');
	await expect(page.locator('#bulk-edit')).toBeVisible();
}

test.describe('Bulk scheduled unpublishing', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('the date fields only appear for "Schedule unpublishing"', async ({ page }) => {
		const id = createProduct('E2E toggle');
		await openBulkEdit(page, [id]);

		await expect(page.locator('#wpu-bulk-date-fields')).toBeHidden();
		await page.check('input[name="wpu_bulk_action"][value="schedule"]');
		await expect(page.locator('#wpu-bulk-date-fields')).toBeVisible();
		await page.check('input[name="wpu_bulk_action"][value="remove"]');
		await expect(page.locator('#wpu-bulk-date-fields')).toBeHidden();
	});

	test('schedules published products, skips drafts, and reports the result after the redirect', async ({ page }) => {
		const a = createProduct('E2E schedule A');
		const b = createProduct('E2E schedule B');
		const draft = createProduct('E2E schedule draft', 'draft');

		await openBulkEdit(page, [a, b, draft]);
		await page.check('input[name="wpu_bulk_action"][value="schedule"]');

		const year = new Date().getFullYear() + 1;
		await page.fill('#wpu_bulk_aa', String(year));
		await page.selectOption('#wpu_bulk_mm', '06');
		await page.fill('#wpu_bulk_jj', '15');
		await page.fill('#wpu_bulk_hh', '09');
		await page.fill('#wpu_bulk_mn', '30');
		await page.click('#bulk_edit');

		await expect(page).toHaveURL(/wpu_bulk_results=/);
		await expect(page.locator('.wpu-bulk-notice')).toContainText('Unpublishing scheduled for 2 products.');
		await expect(page.locator('.wpu-bulk-notice')).toContainText('1 product was skipped');

		expect(scheduledMeta(a)).toBe(`${year}-06-15 09:30:00`);
		expect(scheduledMeta(b)).toBe(`${year}-06-15 09:30:00`);
		expect(scheduledMeta(draft)).toBe('');

		const events = cronEvents();
		for (const id of [a, b]) {
			expect(events.filter((event) => String(event.args).includes(String(id)))).toHaveLength(1);
		}
		expect(events.filter((event) => String(event.args).includes(String(draft)))).toHaveLength(0);
	});

	test('an invalid date is blocked in the browser and nothing is changed', async ({ page }) => {
		const id = createProduct('E2E invalid');
		await openBulkEdit(page, [id]);
		await page.check('input[name="wpu_bulk_action"][value="schedule"]');
		await page.fill('#wpu_bulk_jj', '45');
		await page.click('#bulk_edit');

		await expect(page.locator('#wpu-bulk-error')).toBeVisible();
		await expect(page).not.toHaveURL(/wpu_bulk_results=/);
		expect(scheduledMeta(id)).toBe('');
	});

	test('"Unpublish now" asks for confirmation; dismissing it changes nothing, accepting it moves products to Draft', async ({ page }) => {
		const id = createProduct('E2E unpublish now');
		const dialogs = [];

		await openBulkEdit(page, [id]);
		await page.check('input[name="wpu_bulk_action"][value="unpublish_now"]');

		page.once('dialog', (dialog) => { dialogs.push(dialog.message()); dialog.dismiss(); });
		await page.click('#bulk_edit');
		expect(dialogs[0]).toContain('Unpublish 1 selected published product now?');
		expect(status(id)).toBe('publish');

		page.once('dialog', (dialog) => dialog.accept());
		await page.click('#bulk_edit');

		await expect(page.locator('.wpu-bulk-notice')).toContainText('1 product unpublished and moved to Draft.');
		expect(status(id)).toBe('draft');
	});

	test('"Remove scheduled unpublishing" confirms, then clears metadata and cron events', async ({ page }) => {
		const id = createProduct('E2E remove');
		wp(`eval '\\ProductUnpublisher\\Plugin::instance()->schedules()->replace(${id}, time() + 86400 * 30);'`);
		expect(cronEvents().filter((event) => String(event.args).includes(String(id)))).toHaveLength(1);

		await openBulkEdit(page, [id]);
		await page.check('input[name="wpu_bulk_action"][value="remove"]');

		const messages = [];
		page.once('dialog', (dialog) => { messages.push(dialog.message()); dialog.accept(); });
		await page.click('#bulk_edit');

		await expect(page.locator('.wpu-bulk-notice')).toContainText('Scheduled unpublishing removed from 1 product.');
		expect(messages[0]).toContain('deletes its scheduled date, its cron event and any retry information');
		expect(scheduledMeta(id)).toBe('');
		expect(cronEvents().filter((event) => String(event.args).includes(String(id)))).toHaveLength(0);
		expect(status(id)).toBe('publish');
	});

	test('the Products list shows an Unpublishes column', async ({ page }) => {
		createProduct('E2E column');
		await page.goto(LIST);
		await expect(page.locator('th#wpu_unpublish')).toBeVisible();
	});
});
