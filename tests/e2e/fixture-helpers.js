const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');

function jqueryPath() {
	if (process.env.JQUERY_PATH) {
		return process.env.JQUERY_PATH;
	}

	return require.resolve('jquery/dist/jquery.min.js');
}

const BASE_CONFIG = {
	now: [2026, 11, 5, 9, 30],
	monthNames: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
	weekdays: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
	dateFormat: 'F j, Y',
	timeFormat: 'g:i a',
	atLabel: 'at',
	invalid: 'Please enter a valid date and time.',
	past: 'Choose a date and time in the future.',
};

const BULK_CONFIG = Object.assign({}, BASE_CONFIG, {
	confirmNowOne: 'Unpublish %d selected published product now?',
	confirmNowMany: 'Unpublish %d selected published products now?',
	confirmNowSkipped: '%d selected product is not published and will be left unchanged.',
	confirmNowSkippedMany: '%d selected products are not published and will be left unchanged.',
	nothingToUnpublish: 'None of the selected products are published, so there is nothing to unpublish.',
	confirmRemoveOne: 'Remove the scheduled unpublishing from %d selected product?',
	confirmRemoveMany: 'Remove the scheduled unpublishing from %d selected products?',
});

const EDITOR_CONFIG = Object.assign({}, BASE_CONFIG, {
	before: 'Unpublishing must be later than scheduled publication.',
	confirmRemove: 'Remove the unpublishing schedule?',
	unpublishLabel: 'Unpublish on:',
	removalPending: 'Schedule will be removed when you update.',
	notScheduled: 'Not scheduled',
});

/**
 * Load a fixture page and inject jQuery, the config global and the plugin scripts.
 * A submit recorder is attached after the plugin handlers: it only runs when the plugin did not block.
 */
async function loadFixture(page, fixture, globalName, config, scripts, existing) {
	await page.goto('file://' + path.join(ROOT, 'tests', 'e2e', 'fixtures', fixture));
	await page.addScriptTag({ path: jqueryPath() });
	await page.evaluate(([name, value]) => { window[name] = value; }, [globalName, config]);

	// Adjust the markup (e.g. pretend a schedule exists) before the plugin scripts initialise.
	if (existing) {
		await existing(page);
	}

	for (const script of scripts) {
		await page.addScriptTag({ path: path.join(ROOT, 'assets', 'js', script) });
	}

	await page.evaluate(() => {
		window.__submitted = 0;
		document.querySelectorAll('form').forEach((form) => {
			form.addEventListener('submit', (event) => {
				// Count only submissions the plugin did not block.
				if (!event.defaultPrevented) {
					window.__submitted += 1;
				}
				event.preventDefault();
			});
		});
	});

	await page.waitForTimeout(100); // let jQuery's ready handlers run
}

module.exports = { loadFixture, BULK_CONFIG, EDITOR_CONFIG };
