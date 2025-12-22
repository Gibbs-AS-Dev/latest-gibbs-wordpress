/**
 * Customer Role Admin JavaScript
 * Handles "Select All" functionality for columns and actions
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Select All functionality for columns
        $('#select-all-columns').on('change', function() {
            var checked = $(this).is(':checked');
            $('input[name="column_permissions[]"]').prop('checked', checked);
        });

        // Update "Select All" checkbox state when individual checkboxes change
        $('input[name="column_permissions[]"]').on('change', function() {
            var totalCheckboxes = $('input[name="column_permissions[]"]').length;
            var checkedCheckboxes = $('input[name="column_permissions[]"]:checked').length;
            
            if (checkedCheckboxes === 0) {
                $('#select-all-columns').prop('checked', false);
                $('#select-all-columns').prop('indeterminate', false);
            } else if (checkedCheckboxes === totalCheckboxes) {
                $('#select-all-columns').prop('checked', true);
                $('#select-all-columns').prop('indeterminate', false);
            } else {
                $('#select-all-columns').prop('checked', false);
                $('#select-all-columns').prop('indeterminate', true);
            }
        });

        // Initialize "Select All" checkbox state for columns on page load
        var totalColumns = $('input[name="column_permissions[]"]').length;
        var checkedColumns = $('input[name="column_permissions[]"]:checked').length;
        if (checkedColumns > 0 && checkedColumns < totalColumns) {
            $('#select-all-columns').prop('indeterminate', true);
        }

        // Select All functionality for actions
        $('#select-all-actions').on('change', function() {
            var checked = $(this).is(':checked');
            $('input[name="action_permissions[]"]').prop('checked', checked);
        });

        // Update "Select All" checkbox state when individual action checkboxes change
        $('input[name="action_permissions[]"]').on('change', function() {
            var totalCheckboxes = $('input[name="action_permissions[]"]').length;
            var checkedCheckboxes = $('input[name="action_permissions[]"]:checked').length;
            
            if (checkedCheckboxes === 0) {
                $('#select-all-actions').prop('checked', false);
                $('#select-all-actions').prop('indeterminate', false);
            } else if (checkedCheckboxes === totalCheckboxes) {
                $('#select-all-actions').prop('checked', true);
                $('#select-all-actions').prop('indeterminate', false);
            } else {
                $('#select-all-actions').prop('checked', false);
                $('#select-all-actions').prop('indeterminate', true);
            }
        });

        // Initialize "Select All" checkbox state for actions on page load
        var totalActions = $('input[name="action_permissions[]"]').length;
        var checkedActions = $('input[name="action_permissions[]"]:checked').length;
        if (checkedActions > 0 && checkedActions < totalActions) {
            $('#select-all-actions').prop('indeterminate', true);
        }

    });

})(jQuery);

