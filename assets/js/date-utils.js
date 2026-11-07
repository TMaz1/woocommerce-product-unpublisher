/**
 * Date helpers shared by the product editor and bulk edit scripts.
 *
 * Works in the browser (exposes window.WPUDate) and in Node/Jest (module.exports).
 * All comparisons use WordPress site time supplied by the server, never the browser's timezone.
 */
(function (root, factory) {
    'use strict';

    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.WPUDate = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    var MIN_YEAR = 2000;

    function isLeapYear(year) {
        return (year % 400 === 0) || (year % 4 === 0 && year % 100 !== 0);
    }

    function daysInMonth(year, month) {
        return [31, isLeapYear(year) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][month - 1];
    }

    function pad(value) {
        return String(value).length < 2 ? '0' + value : String(value);
    }

    /**
     * Convert [month, day, year, hour, minute] strings to numeric parts.
     * Returns null when anything is missing, non-numeric or out of range.
     */
    function toParts(values) {
        if (!Array.isArray(values) || values.length !== 5) {
            return null;
        }

        var numbers = values.map(function (value) {
            var text = String(value === null || value === undefined ? '' : value).trim();

            return /^\d{1,4}$/.test(text) ? parseInt(text, 10) : NaN;
        });

        if (numbers.some(function (n) { return isNaN(n); })) {
            return null;
        }

        var month = numbers[0];
        var day = numbers[1];
        var year = numbers[2];
        var hour = numbers[3];
        var minute = numbers[4];

        if (
            month < 1 || month > 12 ||
            year < MIN_YEAR || year > 9999 ||
            day < 1 || day > daysInMonth(year, month) ||
            hour < 0 || hour > 23 ||
            minute < 0 || minute > 59
        ) {
            return null;
        }

        return { month: month, day: day, year: year, hour: hour, minute: minute };
    }

    /**
     * Whether the parts are strictly later than `now` ([Y, n, j, G, i] in site time).
     * The current minute is rejected, matching the server.
     */
    function isFuture(parts, now) {
        var selected = [parts.year, parts.month, parts.day, parts.hour, parts.minute];

        for (var i = 0; i < selected.length; i++) {
            if (selected[i] > now[i]) {
                return true;
            }

            if (selected[i] < now[i]) {
                return false;
            }
        }

        return false;
    }

    /**
     * Validate raw field values.
     * Returns { valid: true, parts } or { valid: false, reason: 'invalid' | 'past' }.
     */
    function validate(values, now) {
        var parts = toParts(values);

        if (!parts) {
            return { valid: false, reason: 'invalid' };
        }

        if (!isFuture(parts, now)) {
            return { valid: false, reason: 'past' };
        }

        return { valid: true, parts: parts };
    }

    function ordinalSuffix(day) {
        if (day % 100 >= 11 && day % 100 <= 13) {
            return 'th';
        }

        return { 1: 'st', 2: 'nd', 3: 'rd' }[day % 10] || 'th';
    }

    /**
     * Format a PHP date()-style string for the supported tokens:
     * d j S D l m n M F Y y a A g G h H i s. Backslash escapes the next character.
     */
    function format(parts, phpFormat, monthNames, weekdays) {
        var weekday = new Date(Date.UTC(parts.year, parts.month - 1, parts.day)).getUTCDay();
        var dayName = (weekdays && weekdays[weekday]) || '';
        var hour12 = parts.hour % 12 || 12;

        var tokens = {
            d: pad(parts.day),
            j: parts.day,
            S: ordinalSuffix(parts.day),
            D: dayName.substring(0, 3),
            l: dayName,
            m: pad(parts.month),
            n: parts.month,
            M: monthNames[parts.month - 1].substring(0, 3),
            F: monthNames[parts.month - 1],
            Y: parts.year,
            y: String(parts.year).slice(-2),
            a: parts.hour < 12 ? 'am' : 'pm',
            A: parts.hour < 12 ? 'AM' : 'PM',
            g: hour12,
            G: parts.hour,
            h: pad(hour12),
            H: pad(parts.hour),
            i: pad(parts.minute),
            s: '00'
        };

        return String(phpFormat).replace(/\\(.)|([a-zA-Z])/g, function (match, escaped, token) {
            if (escaped) {
                return escaped;
            }

            return Object.prototype.hasOwnProperty.call(tokens, token) ? tokens[token] : token;
        });
    }

    /**
     * "<date> at <time>" using the site's formats.
     */
    function formatDateTime(parts, config) {
        return format(parts, config.dateFormat, config.monthNames, config.weekdays) +
            ' ' + (config.atLabel || 'at') + ' ' +
            format(parts, config.timeFormat, config.monthNames, config.weekdays);
    }

    return {
        MIN_YEAR: MIN_YEAR,
        daysInMonth: daysInMonth,
        toParts: toParts,
        isFuture: isFuture,
        validate: validate,
        format: format,
        formatDateTime: formatDateTime
    };
}));
