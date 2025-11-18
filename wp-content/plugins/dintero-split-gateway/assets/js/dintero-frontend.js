jQuery(document).ready(function($) {
    
    // Dintero Checkbox and Modal Logic
    $('#dintero_payment_checkbox').change(function(){
        var isChecked = $(this).is(':checked');
        if(isChecked){
            $('#open_dintero_settings').show();
        } else {
            $('#open_dintero_settings').hide();
            $('#dintero_settings_modal').hide();
        }
        
        // Save the checkbox status via AJAX
        var formData = 'action=save_dintero_payment_status&dintero_payment_enabled=' + (isChecked ? 'on' : 'off') + '&_wpnonce=' + dintero_ajax.payment_nonce;
        
        $.ajax({
            url: dintero_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response){
                if(response.success){
                    // Update button visibility based on checkbox state
                    
                } else {
                    showMessage('Feil ved lagring av betalingsstatus: ' + (response.data || 'Ukjent feil'), 'error');
                }
            },
            error: function(xhr, status, error){
                showMessage('Feil ved lagring av betalingsstatus: ' + error, 'error');
            }
        });
    });
    
    $('#open_dintero_settings').click(function(){
        $('#dintero_settings_modal').show();
    });
    
    $('#open_dintero_seller').click(function(){
        $('#dintero_seller_modal').show();
    });
    
    $('.dintero-close').click(function(){
        $('.dintero-modal').hide();
    });
    
    // Close modal when clicking outside
    $(window).click(function(event) {
        if ($(event.target).hasClass('dintero-modal')) {
            $('#dintero_settings_modal').hide();
        }
    });
    
    // Handle Dintero form submission
    $('#dintero_settings_form').submit(function(e){
        e.preventDefault();
        
        var $form = $(this);
        var $submitButton = $('#save_dintero_settings');
        var originalText = $submitButton.text();
        
        // Add loading state
        $submitButton.prop('disabled', true).text('Lagrer...');
        
        // Add nonce to form data
        var formData = $form.serialize() + '&_wpnonce=' + dintero_ajax.nonce;
        
        $.ajax({
            url: dintero_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response){
                if(response.success){
                    showMessage('Dintero innstillinger lagret!', 'success');
                    $('#dintero_settings_modal').hide();
                } else {
                    showMessage('Feil ved lagring av innstillinger: ' + (response.data || 'Ukjent feil'), 'error');
                }
            },
            error: function(xhr, status, error){
                showMessage('Feil ved lagring av innstillinger: ' + error, 'error');
            },
            complete: function(){
                // Reset button state
                $submitButton.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Create Dintero seller
    $('#create_dintero_seller').click(function(){
        var $button = $(this);
        var originalText = $button.text();
        
        // Add loading state
        $button.prop('disabled', true).text('Oppretter...');
        
        var formData = 'action=create_dintero_seller&_wpnonce=' + dintero_ajax.seller_nonce;
        
        $.ajax({
            url: dintero_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response){
                if(response.success){
                    showMessage('Seller opprettet vellykket!', 'success');
                    $('#dintero_seller_modal').hide();
                    window.location.reload();
                } else {
                    showMessage('Feil ved opprettelse av seller: ' + (response.data || 'Ukjent feil'), 'error');
                }
            },
            error: function(xhr, status, error){
                showMessage('Feil ved opprettelse av seller: ' + error, 'error');
            },
            complete: function(){
                // Reset button state
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Function to show messages
    function showMessage(message, type) {
        var messageClass = type === 'success' ? 'dintero-success' : 'dintero-error';
        var $message = $('<div class="dintero-message ' + messageClass + '">' + message + '</div>');
        
        // Remove existing messages
        $('.dintero-message').remove();
        
        // Add new message
        $('body').append($message);
        
        // Auto remove after 5 seconds
        setTimeout(function(){
            $message.fadeOut(function(){
                $(this).remove();
            });
        }, 5000);
    }
    
    // Load saved settings when modal opens
    $('#open_dintero_settings').click(function(){
        // You can add AJAX call here to load saved settings if needed
        // For now, the form will use the default values or values from PHP
    });
    
    // Form validation
    $('#dintero_settings_form input[type="text"]').on('blur', function(){
        var $input = $(this);
        var value = $input.val().trim();
        
        // Basic validation
        if ($input.attr('id') === 'dintero_bank_account_number' && value && !isValidIBAN(value)) {
            $input.addClass('error');
            showFieldError($input, 'Ugyldig IBAN format');
        } else if ($input.attr('id') === 'dintero_bank_account_country_code' && value && value.length !== 2) {
            $input.addClass('error');
            showFieldError($input, 'Landkode må være 2 tegn');
        } else {
            $input.removeClass('error');
            hideFieldError($input);
        }
    });
    
    function showFieldError($input, message) {
        var $error = $input.siblings('.field-error');
        if ($error.length === 0) {
            $error = $('<div class="field-error">' + message + '</div>');
            $input.after($error);
        } else {
            $error.text(message);
        }
    }
    
    function hideFieldError($input) {
        $input.siblings('.field-error').remove();
    }
    
    // Simple IBAN validation (basic check)
    function isValidIBAN(iban) {

        return true;
        // Remove spaces and convert to uppercase
        iban = iban.replace(/\s/g, '').toUpperCase();
        
        // Check if it starts with NO and has correct length for Norwegian IBAN
        if (iban.startsWith('NO') && iban.length === 15) {
            return true;
        }
        
        return false;
    }
}); 