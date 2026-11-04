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
        var savedAction = '';

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
            clearErrors();

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
            var values = getValues().map(Number);

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

        function validate() {
            clearErrors();

            var date = getDateParts();

            if (!date) {
                return showError(wpuAdmin.invalid);
            }

            return true;
        }

        function formatDate(date) {
            var month = wpuAdmin.monthNames[date.month - 1];

            var day = String(date.day).padStart(2, '0');
            var hour = String(date.hour).padStart(2, '0');
            var minute = String(date.minute).padStart(2, '0');

            return month + ' ' +
                day + ', ' +
                date.year + ' ' +
                wpuAdmin.at + ' ' +
                hour + ':' + minute;
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

        $edit.on('click', function (event) {
            event.preventDefault();
            openEditor();
        });

        $('#wpu-ok').on('click', function () {

            if (!validate()) {
                return;
            }

            var date = getDateParts();

            $action.val('save');

            $current.text(formatDate(date));

            $label.text('Unpublish on:');

            closeEditor();
        });

        $('#wpu-cancel').on('click', function (event) {

            event.preventDefault();

            setValues(savedValues);

            $current.text(savedText);
            $label.text(savedLabel);

            $action.val(savedAction);

            clearErrors();

            closeEditor();
        });

        $('#wpu-remove').on('click', function (event) {

            event.preventDefault();

            if (!window.confirm(wpuAdmin.confirmRemove)) {
                return;
            }

            $action.val('remove');

            $current.text(
                'Schedule will be removed when you update.'
            );

            closeEditor();
        });

        fields.forEach(function ($field) {

            $field.on('input change', clearErrors);

            $field.on('keydown', function (event) {

                if (event.key === 'Enter') {
                    event.preventDefault();
                    $('#wpu-ok').trigger('click');
                }
            });
        });

        $form.on('submit', function (event) {

            if (
                $action.val() === 'save' &&
                !validate()
            ) {

                event.preventDefault();

                $panel.prop('hidden', false);
                $edit.hide();

                return false;
            }
        });

    });

})(jQuery);