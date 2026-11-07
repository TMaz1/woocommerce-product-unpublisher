/**
 * @jest-environment jsdom
 */
const $ = require('jquery');
const { init } = require('../../assets/js/bulk-edit.js');

const CONFIG = {
	now: [2026, 11, 5, 9, 30],
	monthNames: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
	invalid: 'Please enter a valid date and time.',
	past: 'Choose a date and time in the future.',
	confirmNowOne: 'Unpublish %d product now?',
	confirmNowMany: 'Unpublish %d products now?',
	confirmNowSkipped: '%d is not published.',
	confirmNowSkippedMany: '%d are not published.',
	nothingToUnpublish: 'Nothing to unpublish.',
	confirmRemoveOne: 'Remove schedule from %d product?',
	confirmRemoveMany: 'Remove schedules from %d products?',
};

function buildDom() {
	document.body.innerHTML = `
	<form id="posts-filter">
		<table><tbody id="the-list">
			<tr id="post-1" class="status-publish"><td><input type="checkbox" name="post[]" value="1"></td></tr>
			<tr id="post-2" class="status-publish"><td><input type="checkbox" name="post[]" value="2"></td></tr>
			<tr id="post-3" class="status-draft"><td><input type="checkbox" name="post[]" value="3"></td></tr>
		</tbody></table>
		<table><tbody>
		<tr id="bulk-edit" style="display:table-row"><td>
			<label><input type="radio" name="wpu_bulk_action" value="none" checked> none</label>
			<label><input type="radio" name="wpu_bulk_action" value="schedule"> schedule</label>
			<label><input type="radio" name="wpu_bulk_action" value="remove"> remove</label>
			<label><input type="radio" name="wpu_bulk_action" value="unpublish_now"> now</label>
			<div id="wpu-bulk-date-fields" hidden>
				<select name="wpu_bulk_mm"><option value="01">01</option><option value="12" selected>12</option></select>
				<input type="text" name="wpu_bulk_jj" value="24">
				<input type="text" name="wpu_bulk_aa" value="2026">
				<input type="text" name="wpu_bulk_hh" value="10">
				<input type="text" name="wpu_bulk_mn" value="00">
				<p id="wpu-bulk-error" hidden></p>
			</div>
			<button type="button" class="cancel">Cancel</button>
			<button type="submit" id="bulk_edit">Update</button>
		</td></tr>
		</tbody></table>
	</form>`;
}

function choose(value) {
	$('input[name="wpu_bulk_action"][value="' + value + '"]').prop('checked', true).trigger('change');
}

function select(...ids) {
	ids.forEach((id) => $('input[name="post[]"][value="' + id + '"]').prop('checked', true));
}

// Returns true when the submit proceeds (was not prevented).
function submit() {
	const event = $.Event('submit');
	$('#posts-filter').trigger(event);
	return !event.isDefaultPrevented();
}

beforeEach(() => {
	buildDom();
	jest.spyOn(HTMLFormElement.prototype, 'submit').mockImplementation(() => {});
	window.alert = jest.fn();
	window.confirm = jest.fn(() => true);
	window.WPUDate = require('../../assets/js/date-utils.js');
	init($, CONFIG, window);
});

afterEach(() => {
	jest.restoreAllMocks();
	$('#posts-filter').off();
});

describe('date field toggling', () => {
	it('hides the date fields initially and disables them', () => {
		expect($('#wpu-bulk-date-fields').prop('hidden')).toBe(true);
		expect($('input[name="wpu_bulk_jj"]').prop('disabled')).toBe(true);
	});

	it('shows and enables them only for "Schedule unpublishing"', () => {
		choose('schedule');
		expect($('#wpu-bulk-date-fields').prop('hidden')).toBe(false);
		expect($('input[name="wpu_bulk_jj"]').prop('disabled')).toBe(false);

		['none', 'remove', 'unpublish_now'].forEach((value) => {
			choose(value);
			expect($('#wpu-bulk-date-fields').prop('hidden')).toBe(true);
			expect($('input[name="wpu_bulk_jj"]').prop('disabled')).toBe(true);
		});
	});

	it('Cancel resets to "No change" and hides the fields', () => {
		choose('schedule');
		$('#bulk-edit .cancel').trigger('click');
		expect($('input[name="wpu_bulk_action"]:checked').val()).toBe('none');
		expect($('#wpu-bulk-date-fields').prop('hidden')).toBe(true);
	});
});

describe('submit with "No change"', () => {
	it('never prompts', () => {
		select(1, 2);
		expect(submit()).toBe(true);
		expect(window.confirm).not.toHaveBeenCalled();
	});
});

describe('submit with "Schedule unpublishing"', () => {
	beforeEach(() => { choose('schedule'); select(1); });

	it('accepts a valid future date without prompting', () => {
		expect(submit()).toBe(true);
		expect(window.confirm).not.toHaveBeenCalled();
	});

	it('blocks and explains a past date', () => {
		$('input[name="wpu_bulk_aa"]').val('2026');
		$('select[name="wpu_bulk_mm"]').val('01');
		expect(submit()).toBe(false);
		expect($('#wpu-bulk-error').prop('hidden')).toBe(false);
		expect($('#wpu-bulk-error').text()).toBe(CONFIG.past);
	});

	it('blocks and explains an impossible date', () => {
		$('input[name="wpu_bulk_jj"]').val('31');
		$('select[name="wpu_bulk_mm"]').val('01').val('12');
		$('input[name="wpu_bulk_jj"]').val('45');
		expect(submit()).toBe(false);
		expect($('#wpu-bulk-error').text()).toBe(CONFIG.invalid);
	});

	it('clears the error when a field changes', () => {
		$('input[name="wpu_bulk_jj"]').val('45');
		submit();
		$('input[name="wpu_bulk_jj"]').val('24').trigger('input');
		expect($('#wpu-bulk-error').prop('hidden')).toBe(true);
	});
});

describe('submit with "Unpublish published products now"', () => {
	beforeEach(() => choose('unpublish_now'));

	it('asks for confirmation with the number of published products and proceeds when accepted', () => {
		select(1, 2);
		expect(submit()).toBe(true);
		expect(window.confirm).toHaveBeenCalledTimes(1);
		expect(window.confirm.mock.calls[0][0]).toContain('Unpublish 2 products now?');
	});

	it('uses the singular wording for one product', () => {
		select(1);
		submit();
		expect(window.confirm.mock.calls[0][0]).toContain('Unpublish 1 product now?');
	});

	it('mentions products that will be skipped', () => {
		select(1, 3);
		submit();
		expect(window.confirm.mock.calls[0][0]).toContain('1 is not published.');
	});

	it('blocks the submit when the user cancels', () => {
		window.confirm = jest.fn(() => false);
		select(1, 2);
		expect(submit()).toBe(false);
	});

	it('blocks and alerts when no selected product is published', () => {
		select(3);
		expect(submit()).toBe(false);
		expect(window.confirm).not.toHaveBeenCalled();
		expect(window.alert).toHaveBeenCalledWith(CONFIG.nothingToUnpublish);
	});

	it('leaves WordPress to handle an empty selection', () => {
		expect(submit()).toBe(true);
		expect(window.confirm).not.toHaveBeenCalled();
	});
});

describe('submit with "Remove scheduled unpublishing"', () => {
	beforeEach(() => choose('remove'));

	it('asks for confirmation and proceeds when accepted', () => {
		select(1, 3);
		expect(submit()).toBe(true);
		expect(window.confirm.mock.calls[0][0]).toBe('Remove schedules from 2 products?');
	});

	it('blocks when cancelled', () => {
		window.confirm = jest.fn(() => false);
		select(1);
		expect(submit()).toBe(false);
	});
});

describe('when the bulk panel is closed', () => {
	it('does not interfere with other form submissions', () => {
		choose('unpublish_now');
		select(1);
		$('#bulk-edit').css('display', 'none');
		expect(submit()).toBe(true);
		expect(window.confirm).not.toHaveBeenCalled();
	});
});
