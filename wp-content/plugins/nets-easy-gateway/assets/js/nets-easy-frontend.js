jQuery(document).ready(function($) {
    'use strict';

    // Nets Easy Frontend JavaScript

    // Handle settings form submission
    $(document).on('submit', '.nets-easy-settings-form', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitBtn = $form.find('.nets-easy-submit-btn');
        var $loading = $form.find('.nets-easy-loading');
        var $message = $form.find('.nets-easy-message');
        
        // Show loading state
        $submitBtn.prop('disabled', true);
        $loading.addClass('show');
        $message.removeClass('success error info').hide();
        
        // Prepare form data
        var formData = $form.serialize();
        formData += '&action=save_nets_easy_settings&_wpnonce=' + nets_easy_ajax.nonce;
        
        $.ajax({
            url: nets_easy_ajax.ajax_url,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showMessage($message, response.data || 'Settings saved successfully!', 'success');
                } else {
                    showMessage($message, response.data || 'Error saving settings.', 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage($message, 'Network error: ' + error, 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false);
                $loading.removeClass('show');
            }
        });
    });

    // Handle payment status toggle
    $(document).on('change', 'input[name="nets_easy_payment_enabled"]', function() {
        var $checkbox = $(this);
        var $message = $('.nets-easy-message');
        var isEnabled = $checkbox.is(':checked');
        
        $.ajax({
            url: nets_easy_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'save_nets_easy_payment_status',
                nets_easy_payment_enabled: isEnabled ? 'on' : 'off',
                _wpnonce: nets_easy_ajax.payment_nonce
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showMessage($message, 'Payment status updated successfully!', 'success');
                } else {
                    showMessage($message, response.data || 'Error updating payment status.', 'error');
                    // Revert checkbox state
                    $checkbox.prop('checked', !isEnabled);
                }
            },
            error: function(xhr, status, error) {
                showMessage($message, 'Network error: ' + error, 'error');
                // Revert checkbox state
                $checkbox.prop('checked', !isEnabled);
            }
        });
    });

    // Handle seller creation
    $(document).on('click', '.create-nets-easy-seller-btn', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var $message = $('.nets-easy-message');
        
        if (!confirm('Are you sure you want to create a new Nets Easy seller account? This action cannot be undone.')) {
            return;
        }
        
        $btn.prop('disabled', true).text('Creating...');
        
        $.ajax({
            url: nets_easy_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'create_nets_easy_seller',
                _wpnonce: nets_easy_ajax.seller_nonce,
                // Add any additional form data here
                nets_easy_bank_name: $('input[name="nets_easy_bank_name"]').val(),
                nets_easy_bank_account_number: $('input[name="nets_easy_bank_account_number"]').val(),
                nets_easy_bank_account_number_type: $('select[name="nets_easy_bank_account_number_type"]').val(),
                nets_easy_bank_account_country_code: $('select[name="nets_easy_bank_account_country_code"]').val(),
                nets_easy_bank_account_currency: $('select[name="nets_easy_bank_account_currency"]').val(),
                nets_easy_payout_currency: $('select[name="nets_easy_payout_currency"]').val(),
                nets_easy_bank_identification_code: $('input[name="nets_easy_bank_identification_code"]').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showMessage($message, response.data || 'Seller account created successfully!', 'success');
                    // Reload page after successful creation
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showMessage($message, response.data || 'Error creating seller account.', 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage($message, 'Network error: ' + error, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Create Seller Account');
            }
        });
    });

    // Handle payment method selection
    $(document).on('change', 'input[name="nets_easy_payment_method"]', function() {
        var $selected = $(this);
        var $methods = $('.nets-easy-payment-method');
        
        $methods.removeClass('selected');
        $selected.closest('.nets-easy-payment-method').addClass('selected');
    });

    // Form validation
    $(document).on('blur', '.nets-easy-form-group input[required], .nets-easy-form-group select[required]', function() {
        var $field = $(this);
        var $group = $field.closest('.nets-easy-form-group');
        var value = $field.val().trim();
        
        $group.find('.field-error').remove();
        
        if (!value) {
            $field.after('<span class="field-error" style="color: red; font-size: 12px;">This field is required</span>');
            $field.addClass('error');
        } else {
            $field.removeClass('error');
        }
    });

    // Real-time form validation
    $(document).on('input', '.nets-easy-form-group input, .nets-easy-form-group select', function() {
        var $field = $(this);
        $field.removeClass('error');
        $field.closest('.nets-easy-form-group').find('.field-error').remove();
    });

    // Show/hide additional fields based on country selection
    $(document).on('change', 'select[name="nets_easy_bank_account_country_code"]', function() {
        var countryCode = $(this).val();
        var $currencyField = $('select[name="nets_easy_bank_account_currency"]');
        var $payoutCurrencyField = $('select[name="nets_easy_payout_currency"]');
        
        // Update currency options based on country
        updateCurrencyOptions($currencyField, countryCode);
        updateCurrencyOptions($payoutCurrencyField, countryCode);
    });

    // Initialize form
    initializeNetsEasyForm();

    // Helper functions
    function showMessage($element, message, type) {
        $element.removeClass('success error info')
               .addClass(type)
               .text(message)
               .show()
               .delay(5000)
               .fadeOut();
    }

    function updateCurrencyOptions($select, countryCode) {
        var currencies = {
            'NO': ['NOK'],
            'SE': ['SEK'],
            'DK': ['DKK'],
            'FI': ['EUR'],
            'DE': ['EUR'],
            'FR': ['EUR'],
            'GB': ['GBP'],
            'US': ['USD']
        };
        
        var options = currencies[countryCode] || ['NOK', 'SEK', 'DKK', 'EUR', 'GBP', 'USD'];
        
        $select.empty();
        $.each(options, function(index, currency) {
            $select.append($('<option>', {
                value: currency,
                text: currency
            }));
        });
    }

    function initializeNetsEasyForm() {
        // Set default values
        var $countrySelect = $('select[name="nets_easy_bank_account_country_code"]');
        if ($countrySelect.length && !$countrySelect.val()) {
            $countrySelect.val('NO');
            $countrySelect.trigger('change');
        }
        
        // Initialize payment method selection
        var $selectedMethod = $('input[name="nets_easy_payment_method"]:checked');
        if ($selectedMethod.length) {
            $selectedMethod.closest('.nets-easy-payment-method').addClass('selected');
        }
    }

    // Auto-save form data to localStorage
    $(document).on('input change', '.nets-easy-settings-form input, .nets-easy-settings-form select', function() {
        var $form = $(this).closest('form');
        var formData = $form.serialize();
        localStorage.setItem('nets_easy_form_data', formData);
    });

    // Restore form data from localStorage
    $(document).ready(function() {
        var savedData = localStorage.getItem('nets_easy_form_data');
        if (savedData) {
            var $form = $('.nets-easy-settings-form');
            if ($form.length) {
                // Parse and restore form data
                var params = new URLSearchParams(savedData);
                params.forEach(function(value, key) {
                    var $field = $form.find('[name="' + key + '"]');
                    if ($field.length) {
                        if ($field.is(':checkbox, :radio')) {
                            $field.prop('checked', value === 'on' || value === $field.val());
                        } else {
                            $field.val(value);
                        }
                    }
                });
                
                // Trigger change events for dependent fields
                $form.find('select').trigger('change');
            }
        }
    });

    // Clear saved form data on successful submission
    $(document).on('submit', '.nets-easy-settings-form', function() {
        var $form = $(this);
        $form.one('ajaxSuccess', function() {
            localStorage.removeItem('nets_easy_form_data');
        });
    });

    // Add loading states to buttons
    $(document).on('click', '.nets-easy-submit-btn, .create-nets-easy-seller-btn', function() {
        var $btn = $(this);
        var originalText = $btn.text();
        
        $btn.prop('disabled', true)
            .data('original-text', originalText)
            .text('Processing...');
        
        // Re-enable button after 10 seconds as failsafe
        setTimeout(function() {
            $btn.prop('disabled', false)
                .text($btn.data('original-text'));
        }, 10000);
    });

    // Handle form reset
    $(document).on('click', '.nets-easy-reset-btn', function(e) {
        e.preventDefault();
        
        if (confirm('Are you sure you want to reset the form? All unsaved changes will be lost.')) {
            $('.nets-easy-settings-form')[0].reset();
            localStorage.removeItem('nets_easy_form_data');
            $('.nets-easy-message').hide();
            $('.nets-easy-payment-method').removeClass('selected');
            initializeNetsEasyForm();
        }
    });

    // Add tooltips for help text
    $(document).on('mouseenter', '.nets-easy-form-group label[title]', function() {
        var $label = $(this);
        var title = $label.attr('title');
        
        if (title) {
            $label.attr('data-original-title', title).removeAttr('title');
            $('<div class="nets-easy-tooltip">' + title + '</div>')
                .appendTo('body')
                .fadeIn(200);
        }
    });

    $(document).on('mouseleave', '.nets-easy-form-group label[data-original-title]', function() {
        $('.nets-easy-tooltip').remove();
    });

    // Position tooltips
    $(document).on('mousemove', '.nets-easy-form-group label[data-original-title]', function(e) {
        $('.nets-easy-tooltip').css({
            top: e.pageY - 30,
            left: e.pageX + 10
        });
    });
});
