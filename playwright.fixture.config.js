// Runs the admin scripts against static, WordPress-shaped markup. No WordPress needed.
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
	testDir: './tests/e2e',
	testMatch: /fixture-.*\.spec\.js/,
	reporter: [['list']],
	use: { headless: true },
});
