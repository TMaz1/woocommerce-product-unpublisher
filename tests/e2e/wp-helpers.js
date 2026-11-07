const { execSync } = require('child_process');

/** Run a WP-CLI command inside the wp-env tests container and return trimmed stdout. */
function wp(command) {
	return execSync(`npx wp-env run tests-cli wp ${command}`, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] })
		.split('\n')
		.filter((line) => line && !line.startsWith('ℹ') && !line.startsWith('✔'))
		.join('\n')
		.trim();
}

function createProduct(title, status = 'publish') {
	return Number(wp(`post create --post_type=product --post_status=${status} --post_title="${title}" --porcelain`));
}

function status(id) {
	return wp(`post get ${id} --field=post_status`);
}

function scheduledMeta(id) {
	try {
		return wp(`post meta get ${id} _wps_unpublish_time`);
	} catch (error) {
		return ''; // WP-CLI exits non-zero when the meta key does not exist.
	}
}

function cronEvents() {
	return JSON.parse(wp('cron event list --hook=wpu_unpublish_product --format=json') || '[]');
}

async function login(page) {
	await page.goto('/wp-login.php');
	await page.fill('#user_login', 'admin');
	await page.fill('#user_pass', 'password');
	await page.click('#wp-submit');
	await page.waitForURL(/wp-admin/);
}

module.exports = { wp, createProduct, status, scheduledMeta, cronEvents, login };
