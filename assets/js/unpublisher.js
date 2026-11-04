(function ($) {
    'use strict';

    $(function () {

        var $row = $('#wpu-publish-row');
        var $panel = $('#wpu-edit-panel');
        var $edit = $('#wpu-edit-toggle');
        var $current = $('#wpu-current-value');
        var $label = $('#wpu-label');
        var $error = $('#wpu-error');
        var $action = $('#wpu_action');
        var $form = $('#post');

        if (!$row.length || !$panel.length || !$form.length) {
            return;
        }

        var fields = [
            $('#wpu_mm'),
            $('#wpu_jj'),
            $('#wpu_aa'),
            $('#wpu_hh'),
            $('#wpu_mn')
        ];

        if (fields.some(function ($field) {
            return !$field.length;
        })) {
            return;
        }

        var savedValues = getValues();
        var savedText = $current.text();
        var savedLabel = $label.text();
        var savedAction = $action.val();

        var monthNames = wpuAdmin.monthNames;

        function getValues() {
            return fields.map(function ($field) {
                return $field.val();
            });
        }

        function setValues(values) {
            fields.forEach(function ($field, index) {
                $field.val(values[index]);
            });
        }

        function clearErrors() {
            fields.forEach(function ($field) {
                $field
                    .removeClass('wpu-invalid')
                    .removeAttr('aria-invalid');
            });

            $error
                .text('')
                .prop('hidden', true);
        }

        function showError(message) {
            fields.forEach(function ($field) {
                $field
                    .addClass('wpu-invalid')
                    .attr('aria-invalid', 'true');
            });

            $error
                .text(message)
                .prop('hidden', false);

            fields[0].trigger('focus');

            return false;
        }

        function getDateParts() {
            var values = getValues().map(function (value) {
                return /^\d+$/.test(value) ? Number(value) : NaN;
            });

            if (!values.every(Number.isInteger)) {
                return null;
            }

            var month = values[0];
            var day = values[1];
            var year = values[2];
            var hour = values[3];
            var minute = values[4];

            if (
                month < 1 || month > 12 ||
                day < 1 || day > 31 ||
                year < 2000 ||
                hour < 0 || hour > 23 ||
                minute < 0 || minute > 59
            ) {
                return null;
            }

            var daysInMonth = [
                31,
                (year % 400 === 0 ||
                    (year % 4 === 0 && year % 100 !== 0))
                    ? 29 : 28,
                31, 30, 31, 30,
                31, 31, 30, 31, 30, 31
            ];

            if (day > daysInMonth[month - 1]) {
                return null;
            }

            return {
                month: month,
                day: day,
                year: year,
                hour: hour,
                minute: minute
            };
        }

        /*
         * Compare against WordPress-local time.
         *
         * This avoids relying on the visitor's browser timezone.
         */

        function isFutureDate(date) {
            var selected = [
                date.year,
                date.month,
                date.day,
                date.hour,
                date.minute
            ];

            var now = wpuAdmin.now;

            for (var i = 0; i < selected.length; i++) {

                if (selected[i] > now[i]) {
                    return true;
                }

                if (selected[i] < now[i]) {
                    return false;
                }
            }

            // Reject the current minute as well.
            return false;
        }

        function validate() {
            clearErrors();

            var date = getDateParts();

            if (!date) {
                return showError(wpuAdmin.invalid);
            }

            if (!isFutureDate(date)) {
                return showError(wpuAdmin.past);
            }

            return true;
        }

        /*
         * Format dates using the WordPress site settings.
         *
         * Supports the common PHP date() format characters
         * used by WordPress date and time settings.
         */

        function formatDate(date) {

            var values = {
                j: date.day,
                d: String(date.day).padStart(2, '0'),
                F: monthNames[date.month - 1],
                M: monthNames[date.month - 1].substring(0, 3),
                n: date.month,
                m: String(date.month).padStart(2, '0'),
                Y: date.year,
                y: String(date.year).slice(-2),

                G: date.hour,
                H: String(date.hour).padStart(2, '0'),
                g: date.hour % 12 || 12,
                h: String(date.hour % 12 || 12).padStart(2, '0'),

                i: String(date.minute).padStart(2, '0'),

                a: date.hour < 12 ? 'am' : 'pm',
                A: date.hour < 12 ? 'AM' : 'PM'
            };

            function applyFormat(format) {

                return format.replace(
                    /\\(.)|([a-zA-Z])/g,
                    function (match, escaped, token) {

                        if (escaped) {
                            return escaped;
                        }

                        return Object.prototype.hasOwnProperty.call(
                            values,
                            token
                        )
                            ? values[token]
                            : token;
                    }
                );
            }

            return applyFormat(wpuAdmin.dateFormat) +
                ' at ' +
                applyFormat(wpuAdmin.timeFormat);
        }

        function openEditor() {

            savedValues = getValues();
            savedText = $current.text();
            savedLabel = $label.text();
            savedAction = $action.val();

            clearErrors();

            $panel.prop('hidden', false);
            $edit.hide();

            fields[0].trigger('focus');
        }

        function closeEditor() {
            $panel.prop('hidden', true);
            $edit.show();
        }

        /*
         * Edit
         */

        $edit.on('click', function (event) {
            event.preventDefault();
            openEditor();
        });

        /*
         * OK
         *
         * Updates the preview only.
         * Actual saving happens through the normal
         * WordPress product Update button.
         */

        $('#wpu-ok').on('click', function (event) {

            event.preventDefault();

            if (!validate()) {
                return;
            }

            var date = getDateParts();

            $action.val('save');

            $current.text(formatDate(date));

            $label.text(
                wpuAdmin.unpublishLabel
            );

            closeEditor();
        });

        /*
         * Cancel
         *
         * Restores the state from when the editor
         * was opened.
         */

        $('#wpu-cancel').on('click', function (event) {

            event.preventDefault();

            setValues(savedValues);

            $current.text(savedText);
            $label.text(savedLabel);

            $action.val(savedAction);

            clearErrors();

            closeEditor();
        });

        /*
         * Remove
         *
         * Removal is committed only when the
         * product is subsequently updated.
         */

        $('#wpu-remove').on('click', function (event) {

            event.preventDefault();

            if (!window.confirm(wpuAdmin.confirmRemove)) {
                return;
            }

            $action.val('remove');

            $current.text(
                wpuAdmin.removalPending
            );

            closeEditor();
        });

        /*
         * Input changes
         */

        fields.forEach(function ($field) {

            $field.on('input change', function () {
                clearErrors();
            });

            $field.on('keydown', function (event) {

                if (event.key === 'Enter') {
                    event.preventDefault();
                    $('#wpu-ok').trigger('click');
                }

                if (event.key === 'Escape') {
                    event.preventDefault();
                    $('#wpu-cancel').trigger('click');
                }
            });
        });

        /*
         * Final server submission.
         *
         * PHP still performs the authoritative
         * date validation and scheduling.
         */

        $form.on('submit', function (event) {

            if ($action.val() !== 'save') {
                return;
            }

            if (!validate()) {

                event.preventDefault();

                $panel.prop('hidden', false);
                $edit.hide();

                return false;
            }
        });

    });

})(jQuery);