document.addEventListener('DOMContentLoaded', function () {
    const stripe = Stripe(stripePlugin.publishableKey);

    // Handle checkout-popup button click
    document.querySelectorAll('.checkout-popup').forEach(button => {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            document.getElementById('checkout-contact-modal').style.display = 'block';
        });
    });

    // Close modal handlers
    const checkoutModal = document.getElementById('checkout-contact-modal');
    if (checkoutModal) {
        const closeBtn = checkoutModal.querySelector('.checkout-modal-close');
        const closeBtnFooter = checkoutModal.querySelector('.checkout-btn-close');
        
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                checkoutModal.style.display = 'none';
            });
        }
        
        if (closeBtnFooter) {
            closeBtnFooter.addEventListener('click', function() {
                checkoutModal.style.display = 'none';
            });
        }
        
        // Close when clicking outside the modal
        checkoutModal.addEventListener('click', function(e) {
            if (e.target === checkoutModal) {
                checkoutModal.style.display = 'none';
            }
        });
        
        // Validation function
        function validateCheckoutForm(form) {
            const fieldErrors = new Map();
            
            // Remove previous error messages and styling
            form.querySelectorAll('.checkout-field-error').forEach(el => el.remove());
            form.querySelectorAll('.checkout-form-group').forEach(group => {
                group.classList.remove('checkout-has-error');
            });
            
            // Required fields with their error messages
            const requiredFields = {
                'company_company_name': 'Company Name is required',
                'company_email': 'Company Email is required',
                'company_phone': 'Company Phone is required',
                'company_country': 'Company Country is required',
                'company_country_code': 'Company Country Code is required',
            };
            
            // Validate required fields
            Object.keys(requiredFields).forEach(fieldName => {
                const field = form.querySelector(`[name="${fieldName}"]`);
                if (field) {
                    const value = field.value.trim();
                    if (!value) {
                        fieldErrors.set(field, requiredFields[fieldName]);
                    }
                }
            });
            
            // Validate email format
            const emailField = form.querySelector('[name="company_email"]');
            if (emailField && emailField.value.trim()) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailField.value.trim())) {
                    fieldErrors.set(emailField, 'Please enter a valid email address');
                }
            }
            
            // Display errors
            if (fieldErrors.size > 0) {
                const firstErrorField = fieldErrors.keys().next().value;
                
                fieldErrors.forEach((errorMessage, field) => {
                    const formGroup = field.closest('.checkout-form-group');
                    if (formGroup) {
                        formGroup.classList.add('checkout-has-error');
                        const errorMsg = document.createElement('span');
                        errorMsg.className = 'checkout-field-error';
                        errorMsg.textContent = errorMessage;
                        formGroup.appendChild(errorMsg);
                    }
                });
                
                // Scroll to first error
                if (firstErrorField) {
                    firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstErrorField.focus();
                }
                
                return false;
            }
            
            return true;
        }
        
        // Add real-time validation - clear errors as user types
        const form = checkoutModal.querySelector('#checkout-contact-form');
        if (form) {
            const fields = form.querySelectorAll('input[required], input[type="email"]');
            fields.forEach(field => {
                field.addEventListener('input', function() {
                    const formGroup = this.closest('.checkout-form-group');
                    if (formGroup && formGroup.classList.contains('checkout-has-error')) {
                        const errorMsg = formGroup.querySelector('.checkout-field-error');
                        if (errorMsg) {
                            const value = this.value.trim();
                            // Clear error if field is now valid
                            if (value && (this.type !== 'email' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value))) {
                                formGroup.classList.remove('checkout-has-error');
                                errorMsg.remove();
                            }
                        }
                    }
                });
            });
        }
        
        // Save button handler
        const saveBtn = checkoutModal.querySelector('.checkout-btn-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', function() {
                const form = document.getElementById('checkout-contact-form');
                
                // Validate form before submitting
                if (!validateCheckoutForm(form)) {
                    return;
                }
                
                const formData = new FormData(form);
                const data = {};
                
                formData.forEach((value, key) => {
                    data[key] = value.trim();
                });
                
                // Show loading
                const originalText = saveBtn.textContent;
                saveBtn.disabled = true;
                saveBtn.textContent = stripePlugin.savingText || 'Saving...';
                
                // Save contact info via AJAX
                fetch(stripePlugin.ajaxUrl + '?action=save_checkout_contact_info', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data),
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        // Close modal and reload page to show updated button
                        checkoutModal.style.display = 'none';
                        window.location.reload();
                    } else {
                        alert(result.error || (stripePlugin.errorSavingText || 'Error saving contact information'));
                        saveBtn.disabled = false;
                        saveBtn.textContent = originalText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert(stripePlugin.errorSavingText || 'Error saving contact information');
                    saveBtn.disabled = false;
                    saveBtn.textContent = originalText;
                });
            });
        }
    }

    document.querySelectorAll('.checkout-button').forEach(button => {
        button.addEventListener('click', function () {
            const priceId = this.dataset.priceId;
            const packageId = this.dataset.packageId;
            jQuery(this).parent().parent().find(".load-div").find(".loading").show();


            fetch(stripePlugin.ajaxUrl + '?action=create_checkout_session', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ price_id: priceId, package_id: packageId }),
            })
            .then(response => response.json())
            .then(data => {
                jQuery(this).parent().parent().find(".load-div").find(".loading").hide();
                if (data.error) {
                    alert(data.error);
                } else {
                    if(data.subscription_id){
                       window.location.reload();
                    }else{
                        return stripe.redirectToCheckout({ sessionId: data.id });
                    }
                    
                }
            }).catch(error => {
                console.error('Error:', error);
                jQuery(this).parent().parent().find(".load-div").find(".loading").hide(); // Use .loading class selector
            });
        });
    });
    // Update Subscription
    document.addEventListener('click', function (event) {

        if(jQuery(event.target).closest(".gibbs_modal")){
            jQuery(".gibbs_modal").find("iframe").each(function(){
                let src = this.src;

                // Add parameters if they are not already present
                const params = new URLSearchParams(src.split('?')[1]);
                params.set('autoplay', '0');
                
                // Update the iframe src
                this.src = `${src.split('?')[0]}?${params.toString()}`;
            })
        }

        
        // Cancel Subscription
        if (event.target.classList.contains('cancel-subscription')) {
            if(confirm("Are you sure!")){
                const subscriptionId = event.target.dataset.subscriptionId;
                const packageId = event.target.dataset.packageId;


                jQuery(event.target).parent().parent().find(".load-div").find(".loading").show();

                fetch(stripePlugin.ajaxUrl + '?action=cancel_subscription', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ subscription_id: subscriptionId, package_id: packageId }),
                })
                .then(response => response.json())
                .then(data => {
                    jQuery(event.target).parent().parent().find(".load-div").find(".loading").show();
                    if (data.error) {
                        alert(data.error);
                    } else {
                       // alert('Subscription canceled successfully!');
                        location.reload(); // Optionally reload to reflect changes
                    }
                }).catch(error => {
                    console.error('Error:', error);
                    jQuery(event.target).parent().parent().find(".load-div").find(".loading").hide(); // Use .loading class selector
                });
            }
        }
    });
    document.querySelectorAll('.sms-payment-button').forEach(button => {
        button.addEventListener('click', function () {
            jQuery(this).parent().parent().find(".load-div").find(".loading").show();

            fetch(stripePlugin.ajaxUrl + '?action=sms_payment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
            })
            .then(response => response.json())
            .then(data => {
                jQuery(this).parent().parent().find(".load-div").find(".loading").hide();
                if(data.error){ 
                    alert(data.error);
                }else if(data.success){
                    window.location.reload();
                }else{
                    alert("Something went wrong");
                }
            })
            .catch(error => {
                console.error('Error:', error);
                jQuery(this).parent().parent().find(".load-div").find(".loading").hide();
            });
        });
    });
});
