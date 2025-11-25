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
        
        // Save button handler
        const saveBtn = checkoutModal.querySelector('.checkout-btn-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', function() {
                const form = document.getElementById('checkout-contact-form');
                const formData = new FormData(form);
                const data = {};
                
                formData.forEach((value, key) => {
                    data[key] = value;
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
