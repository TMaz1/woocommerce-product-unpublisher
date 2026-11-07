const Dates = require('../../assets/js/date-utils.js');

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const NOW = [2026, 11, 5, 9, 30]; // 5 Nov 2026 09:30 site time

describe('daysInMonth', () => {
	it('handles leap years', () => {
		expect(Dates.daysInMonth(2024, 2)).toBe(29);
		expect(Dates.daysInMonth(2100, 2)).toBe(28);
		expect(Dates.daysInMonth(2000, 2)).toBe(29);
		expect(Dates.daysInMonth(2027, 2)).toBe(28);
	});

	it('returns 30 or 31 for other months', () => {
		expect(Dates.daysInMonth(2026, 4)).toBe(30);
		expect(Dates.daysInMonth(2026, 12)).toBe(31);
	});
});

describe('toParts', () => {
	it('parses valid values', () => {
		expect(Dates.toParts(['03', '15', '2027', '10', '05'])).toEqual({ month: 3, day: 15, year: 2027, hour: 10, minute: 5 });
	});

	it('trims whitespace', () => {
		expect(Dates.toParts([' 3 ', ' 1', '2027 ', '0', '0'])).toEqual({ month: 3, day: 1, year: 2027, hour: 0, minute: 0 });
	});

	it('rejects impossible calendar dates', () => {
		expect(Dates.toParts(['02', '30', '2027', '10', '00'])).toBeNull();
		expect(Dates.toParts(['02', '29', '2027', '10', '00'])).toBeNull();
		expect(Dates.toParts(['02', '29', '2028', '10', '00'])).not.toBeNull();
		expect(Dates.toParts(['04', '31', '2027', '10', '00'])).toBeNull();
	});

	it('rejects out-of-range and non-numeric values', () => {
		expect(Dates.toParts(['13', '01', '2027', '10', '00'])).toBeNull();
		expect(Dates.toParts(['00', '01', '2027', '10', '00'])).toBeNull();
		expect(Dates.toParts(['01', '01', '1999', '10', '00'])).toBeNull();
		expect(Dates.toParts(['01', '01', '2027', '24', '00'])).toBeNull();
		expect(Dates.toParts(['01', '01', '2027', '10', '60'])).toBeNull();
		expect(Dates.toParts(['01', 'ab', '2027', '10', '00'])).toBeNull();
		expect(Dates.toParts(['01', '', '2027', '10', '00'])).toBeNull();
		expect(Dates.toParts(['01', '-1', '2027', '10', '00'])).toBeNull();
		expect(Dates.toParts(['01', '1.5', '2027', '10', '00'])).toBeNull();
	});

	it('rejects the wrong number of values', () => {
		expect(Dates.toParts(['01', '01', '2027'])).toBeNull();
		expect(Dates.toParts(null)).toBeNull();
	});
});

describe('isFuture / validate', () => {
	it('accepts a later minute', () => {
		expect(Dates.validate(['11', '05', '2026', '09', '31'], NOW).valid).toBe(true);
	});

	it('rejects the current minute', () => {
		expect(Dates.validate(['11', '05', '2026', '09', '30'], NOW)).toEqual({ valid: false, reason: 'past' });
	});

	it('rejects earlier times, including earlier years', () => {
		expect(Dates.validate(['11', '05', '2026', '09', '29'], NOW).reason).toBe('past');
		expect(Dates.validate(['10', '31', '2026', '23', '59'], NOW).reason).toBe('past');
		expect(Dates.validate(['12', '31', '2025', '23', '59'], NOW).reason).toBe('past');
	});

	it('accepts a later year even with earlier month/day', () => {
		expect(Dates.validate(['01', '01', '2027', '00', '00'], NOW).valid).toBe(true);
	});

	it('reports invalid before past', () => {
		expect(Dates.validate(['02', '31', '2020', '00', '00'], NOW).reason).toBe('invalid');
	});
});

describe('format', () => {
	const parts = { month: 3, day: 1, year: 2027, hour: 15, minute: 5 };

	it('formats common WordPress date formats', () => {
		expect(Dates.format(parts, 'F j, Y', MONTHS, DAYS)).toBe('March 1, 2027');
		expect(Dates.format(parts, 'd/m/Y', MONTHS, DAYS)).toBe('01/03/2027');
		expect(Dates.format(parts, 'jS M y', MONTHS, DAYS)).toBe('1st Mar 27');
		expect(Dates.format(parts, 'l, D', MONTHS, DAYS)).toBe('Monday, Mon');
	});

	it('formats 12 and 24 hour times', () => {
		expect(Dates.format(parts, 'g:i a', MONTHS, DAYS)).toBe('3:05 pm');
		expect(Dates.format(parts, 'H:i', MONTHS, DAYS)).toBe('15:05');
		expect(Dates.format({ month: 1, day: 1, year: 2027, hour: 0, minute: 0 }, 'g:i A', MONTHS, DAYS)).toBe('12:00 AM');
	});

	it('honours backslash escapes', () => {
		expect(Dates.format(parts, '\\a\\t H', MONTHS, DAYS)).toBe('at 15');
	});

	it('uses the correct ordinal suffix', () => {
		const suffix = (day) => Dates.format({ month: 1, day: day, year: 2027, hour: 0, minute: 0 }, 'jS', MONTHS, DAYS);
		expect([suffix(1), suffix(2), suffix(3), suffix(4), suffix(11), suffix(12), suffix(13), suffix(21), suffix(22), suffix(23)])
			.toEqual(['1st', '2nd', '3rd', '4th', '11th', '12th', '13th', '21st', '22nd', '23rd']);
	});

	it('joins date and time with the localised "at"', () => {
		const out = Dates.formatDateTime(parts, { dateFormat: 'F j, Y', timeFormat: 'g:i a', monthNames: MONTHS, weekdays: DAYS, atLabel: 'um' });
		expect(out).toBe('March 1, 2027 um 3:05 pm');
	});
});
