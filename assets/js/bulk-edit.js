/**
 * Bulk Edit controls for the Products list.
 *
 * - Shows the date fields only while "Schedule unpublishing" is selected.
 * - Validates the date before the form is submitted.
 * - Asks for confirmation before "Unpublish published products now" and "Remove scheduled unpublishing".
 *
 * PHP validates everything again; this script is a convenience, not a security control.
 *
 * Exposed as `init($, config, win)` for tests; runs automatically in the browser.
 */
(function (root, factory) {
    'use strict';

    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        var api = factory();

        root.WPUBulkEdit = api;

        if (root.jQuery) {
            root.jQuery(function () {
                api.init(root.jQuery, root.wpuBulk, root);
            });
        }
    }
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    var ACTION_FIELD = 'wpu_bulk_action';
    var SUFFIXES = ['mm', 'jj', 'aa', 'hh', 'mn'];

    function sprintfCount(template, count) {
        return String(template).replace('%d', String(count));
    }

    function init($, config, win) {
        var Dates = (win && win.WPUDate) || (typeof require === 'function' ? require('./date-utils.js') : null);

        var $form = $('#posts-filter');
        var $bulk = $('#bulk-edit');

        if (!config || !Dates || !$form.length || !$bulk.length) {
            return null;
        }

        var $radios = $bulk.find('input[name="' + ACTION_FIELD + '"]');
        var $dateWrap = $bulk.find('#wpu-bulk-date-fields');
        var $error = $bulk.find('#wpu-bulk-error');
        var $dateFields = SUFFIXES.map(function (suffix) {
            return $bulk.find('[name="wpu_bulk_' + suffix + '"]');
        });

        if (!$radios.length || !$dateWrap.length) {
            return null;
        }

        function selectedAction() {
            return $radios.filter(':checked').val() || 'none';
        }

        function dateValues() {
            return $dateFields.map(function ($field) { return $field.val(); });
        }

        function clearError() {
            $error.text('').prop('hidden', true);
            $dateFields.forEach(function ($field) {
                $field.removeClass('wpu-invalid').removeAttr('aria-invalid');
            });
        }

        function showError(message) {
            $error.text(message).prop('hidden', false);
            $dateFields.forEach(function ($field) {
                $field.addClass('wpu-invalid').attr('aria-invalid', 'true');
            });
            $dateFields[0].trigger('focus');
        }

        // Show the date fields only for "Schedule unpublishing"; disabled fields are not submitted.
        function toggleDateFields() {
            var show = selectedAction() === 'schedule';

            $dateWrap.prop('hidden', !show);
            $dateFields.forEach(function ($field) { $field.prop('disabled', !show); });

            if (!show) {
                clearError();
            }
        }

        function bulkIsOpen() {
            return !$bulk.prop('hidden') && $bulk.css('display') !== 'none';
        }

        function selectedRows() {
            return $form.find('input[name="post[]"]:checked');
        }

        function publishedCount($rows) {
            return $rows.filter(function () {
                return $form.find('#post-' + this.value).hasClass('status-publish');
            }).length;
        }

        function reset() {
            $radios.filter('[value="none"]').prop('checked', true);
            toggleDateFields();
        }

        function block(event) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return false;
        }

        function confirmUnpublishNow(event, $rows) {
            var published = publishedCount($rows);

            if (published === 0) {
                win.alert(config.nothingToUnpublish);
                return block(event);
            }

            var message = sprintfCount(published === 1 ? config.confirmNowOne : config.confirmNowMany, published);
            var skipped = $rows.length - published;

            if (skipped > 0) {
                message += '\n\n' + sprintfCount(skipped === 1 ? config.confirmNowSkipped : config.confirmNowSkippedMany, skipped);
            }

            return win.confirm(message) ? true : block(event);
        }

        function confirmRemove(event, $rows) {
            var count = $rows.length;
            var message = sprintfCount(count === 1 ? config.confirmRemoveOne : config.confirmRemoveMany, count);

            return win.confirm(message) ? true : block(event);
        }

        function validateSchedule(event) {
            clearError();

            var result = Dates.validate(dateValues(), config.now);

            if (!result.valid) {
                showError('past' === result.reason ? config.past : config.invalid);
                return block(event);
            }

            return true;
        }

        $radios.on('change', toggleDateFields);
        $dateFields.forEach(function ($field) { $field.on('input change', clearError); });

        // Cancel resets the choice so a later bulk edit always starts at "No change".
        $bulk.on('click', '.cancel', reset);

        $form.on('submit', function (event) {
            var action = selectedAction();

            if (!bulkIsOpen() || action === 'none') {
                return true;
            }

            var $rows = selectedRows();

            // Nothing selected: leave WordPress to handle it.
            if (!$rows.length) {
                return true;
            }

            if (action === 'schedule') {
                return validateSchedule(event);
            }

            if (action === 'unpublish_now') {
                return confirmUnpublishNow(event, $rows);
            }

            if (action === 'remove') {
                return confirmRemove(event, $rows);
            }

            return true;
        });

        toggleDateFields();

        return {
            toggleDateFields: toggleDateFields,
            selectedAction: selectedAction,
            reset: reset
        };
    }

    return { init: init };
}));
