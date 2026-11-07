/**
 * Unpublishing row in the classic product editor Publish box.
 *
 * Mirrors the core "Publish immediately" editor: Edit / OK / Cancel, plus Remove schedule. Changes are
 * committed when the product is saved; PHP performs the authoritative validation.
 */
(function ($, window) {
    'use strict';

    function init() {
        var config = window.wpuAdmin;
        var Dates = window.WPUDate;

        var $row = $('#wpu-publish-row');
        var $panel = $('#wpu-edit-panel');
        var $edit = $('#wpu-edit-toggle');
        var $current = $('#wpu-current-value');
        var $label = $('#wpu-label');
        var $error = $('#wpu-error');
        var $action = $('#wpu_action');
        var $remove = $('#wpu-remove');
        var $form = $('#post');

        if (!config || !Dates || !$row.length || !$panel.length || !$form.length) {
            return;
        }

        var fields = ['mm', 'jj', 'aa', 'hh', 'mn'].map(function (suffix) {
            return $('#wpu_' + suffix);
        });

        if (fields.some(function ($field) { return !$field.length; })) {
            return;
        }

        var savedValues = getValues();
        var savedText = $current.text();
        var savedLabel = $label.text();
        var savedAction = $action.val();
        var hadExistingSchedule = $current.text().trim() !== config.notScheduled;

        // Respect the reduced-motion preference.
        var animationDuration = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 150;

        function getValues() {
            return fields.map(function ($field) { return $field.val(); });
        }

        function setValues(values) {
            fields.forEach(function ($field, index) { $field.val(values[index]); });
        }

        function clearErrors() {
            fields.forEach(function ($field) {
                $field.removeClass('wpu-invalid').removeAttr('aria-invalid');
            });

            $error.text('').prop('hidden', true);
        }

        function showError(message) {
            fields.forEach(function ($field) {
                $field.addClass('wpu-invalid').attr('aria-invalid', 'true');
            });

            $error.text(message).prop('hidden', false);
            fields[0].trigger('focus');

            return false;
        }

        function validate() {
            clearErrors();

            var result = Dates.validate(getValues(), config.now);

            if (!result.valid) {
                return showError('past' === result.reason ? config.past : config.invalid);
            }

            return true;
        }

        function showPanel() {
            // Finish any running animation first so visibility is measured in its end state.
            $panel.stop(true, true);

            var needsAnimation = $panel.prop('hidden') || !$panel.is(':visible');

            $panel.removeAttr('hidden');

            if (needsAnimation) {
                $panel.hide().slideDown(animationDuration);
            }

            $edit.attr('aria-expanded', 'true').hide();
            fields[0].trigger('focus');
        }

        function hidePanel() {
            $panel.stop(true, true).slideUp(animationDuration, function () {
                $(this).prop('hidden', true);
            });

            $edit.attr('aria-expanded', 'false').show().trigger('focus');
        }

        function openEditor() {
            savedValues = getValues();
            savedText = $current.text();
            savedLabel = $label.text();
            savedAction = $action.val();

            clearErrors();
            $remove.prop('hidden', savedText.trim() === config.notScheduled);
            showPanel();
        }

        $edit.on('click', function (event) {
            event.preventDefault();
            openEditor();
        });

        // OK only updates the preview; the product's Update button saves.
        $('#wpu-ok').on('click', function (event) {
            event.preventDefault();

            if (!validate()) {
                return;
            }

            var parts = Dates.toParts(getValues());

            $action.val('save');
            $current.text(Dates.formatDateTime(parts, config));
            $label.text(config.unpublishLabel);
            hidePanel();
        });

        $('#wpu-cancel').on('click', function (event) {
            event.preventDefault();

            setValues(savedValues);
            $current.text(savedText);
            $label.text(savedLabel);
            $action.val(savedAction);
            clearErrors();
            hidePanel();
        });

        $remove.on('click', function (event) {
            event.preventDefault();

            // Nothing has been saved yet: just discard the preview.
            if (!hadExistingSchedule) {
                setValues(savedValues);
                $current.text(config.notScheduled);
                $label.text(config.unpublishLabel);
                $action.val('');
                clearErrors();
                hidePanel();
                return;
            }

            if (!window.confirm(config.confirmRemove)) {
                return;
            }

            $action.val('remove');
            $current.text(config.removalPending);
            hidePanel();
        });

        fields.forEach(function ($field) {
            $field.on('input change', clearErrors);

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

        // Validate on Update, including when the editor is open and OK was not clicked.
        $form.on('submit', function (event) {
            var action = $action.val();

            if (action === 'remove') {
                return;
            }

            if (action === 'save' || !$panel.prop('hidden')) {
                if (!validate()) {
                    event.preventDefault();
                    showPanel();
                    return false;
                }

                $action.val('save');
            }
        });
    }

    $(init);
})(jQuery, window);
