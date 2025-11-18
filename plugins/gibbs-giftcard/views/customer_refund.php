<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$order_id = isset($_GET['order']) ? base64_decode($_GET['order']) : 0;
$hours_left = isset($_GET['hours']) ? intval($_GET['hours']) : 0;

// Get booking start time from bookings table
global $wpdb;
$bookings_table = 'ptn_bookings_calendar';

// Get the booking details
$sql = $wpdb->prepare("SELECT * FROM $bookings_table WHERE order_id = %s", $order_id);

// Get the booking details
$booking = $wpdb->get_row($sql);
$amount = 0;


if ($booking) {

    $booking_id = $booking->id;
    
    // Use the calculate_refund_amount function
    $refund_data = $this->calculate_refund_amount($booking);
    
    // echo "<pre>";
    // print_r($refund_data);  
    // die;
    // Get the listing owner's email
    $listing_id = $booking->listing_id;
    $owner_id = $booking->owner_id;
    $owner_data = get_userdata($owner_id);
    $owner_email = $owner_data ? $owner_data->user_email : '';
    
    $amount = $refund_data['amount'];
    $current_policy = $refund_data['policy'];
    $total_hours = $refund_data['total_hours'];
    $refund_policy_type = $refund_data['policy_type'];

    

    $days = $refund_data['days'];
    $remaining_hours = $refund_data['remaining_hours'];
    $minutes = $refund_data['minutes'];

 
    
} else {
    error_log("No booking found for order ID: " . $order_id);
    error_log("Last DB Error: " . $wpdb->last_error);
}

// Check if order is already refunded
$order = wc_get_order($order_id);
$is_refunded = false;
$voucher_code = get_post_meta($order_id, 'voucher_code', true);
$canceled_booking_zero = get_post_meta($order_id, 'canceled_booking_zero', true);


?>
<div class="refund_container">
    <h2>Kanseller ordre #<?php echo esc_html($order_id); ?></h2>
    
    <div class="refund-form-container">
        <?php if ($voucher_code != "" || $canceled_booking_zero == "true"): ?>
            <div class="refund-status notification-box success">
                <div class="notification-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="notification-content">
                    <h3>Kansellering bekreftet</h3>
                    <p>Denne ordren er allerede kansellert, og en tilgodelapp er utstedt.</p>
                    <?php if($canceled_booking_zero != "true"): ?>
                        <div class="button-container">
                            <form method="post" action="<?php echo admin_url('admin-ajax.php');?>">
                                <input type="hidden" name="action" value="downloadVoucherPDF">
                                <input type="hidden" name="refund_code" value="<?php echo esc_attr($voucher_code); ?>">
                                <button type="submit" class="btn btn-primary">Last ned tilgodelapp</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($days <= 0 && $remaining_hours <= 0 && $minutes <= 0): ?>
            <div class="refund-status notification-box">
                <div class="notification-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="notification-content">
                    <h3>Kansellering ikke mulig</h3>
                    <p>Bookingen er for nær start eller har allerede passert for å kunne kanselleres.</p>
                  
                </div>
            </div>
        <?php elseif (empty($refund_policy_type) || $refund_policy_type === 'no_refund'): ?>
            <div class="refund-status notification-box">
                <div class="notification-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="notification-content">
                    <h3>Kansellering må gjøres av verten</h3>
                    <p>Selvbetjent kansellering er ikke aktivert på dette utleieobjektet. Alle kanselleringer må gjøres av verten.</p>
                    <p class="contact-info">Ta kontakt med verten på <strong>"<?php echo esc_html($owner_email); ?>"</strong> for å be om kansellering.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="refund-reason">
                <label for="cancel_reason">Årsak til kansellering</label>
                <select id="cancel_reason" name="cancel_reason" class="cancel-reason-select">
                    <option value="">Velg en årsak...</option>
                    <option value="change_of_plans">Endring i planer</option>
                    <option value="found_better_option">Funnet bedre alternativ</option>
                    <option value="personal_emergency">Personlig nødstilfelle</option>
                    <option value="weather_concerns">Værforhold</option>
                    <option value="booking_mistake">Bestillingsfeil</option>
                    <option value="other">Annet (spesifiser)</option>
                </select>
                <div id="other_reason_container" style="display: none; margin-top: 10px;">
                    <textarea id="other_reason" name="other_reason" placeholder="Vennligst spesifiser årsaken" rows="3"></textarea>
                </div>
            </div>

            <div class="refund-actions">
                <button type="button" class="confirm-refund-btn">Bekreft kansellering</button>
            </div>

            <div class="refund-info text-center">
                <p>Det er <strong><?php 
                    if ($days > 0) {
                        echo esc_html($days) . ' dager';
                        if ($remaining_hours > 0) {
                            echo ', ' . esc_html($remaining_hours) . ' timer';
                        }
                        if ($minutes > 0) {
                            echo ' og ' . esc_html($minutes) . ' minutter';
                        }
                    } else if ($remaining_hours > 0) {
                        echo esc_html($remaining_hours) . ' timer';
                        if ($minutes > 0) {
                            echo ' og ' . esc_html($minutes) . ' minutter';
                        }
                    } else {
                        echo esc_html($minutes) . ' minutter';
                    }
                ?></strong> igjen før fristen for kansellering utløper.</p>
                
                <?php if ($current_policy['hours'] > 0 && $total_hours >= $current_policy['hours']): ?>
                    <p>Du får en tilgodelapp på  <strong>kr <?php echo number_format($amount, 2, ',', ' '); ?></strong> etter kanselleringen</p>
                <?php else: ?>
                    <p>Ingen tilgodelapp er tilgjengelig for denne bookingen.</p>
                <?php endif; ?>
            </div>

            <div class="refund-policy">
<!--                 <p style="margin-bottom: 10px;">Kanselleringsvilkår: <?php echo esc_html($current_policy['name']); ?></p> -->
                <?php if ($current_policy['hours'] > 0): ?>
               
                <?php else: ?>
                    <p>Kunder kan ikke kansellere selv. Alle kanselleringer må gjøres av administrator.</p>
                <?php endif; ?>
                <!-- <a href="#">Se alle kanselleringsvilkår</a> -->
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.refund_container {
    max-width: 840px;
    margin: 0 auto;
    padding: 20px;
    font-family: Arial, sans-serif;
}

.refund_container h2 {
    margin-bottom: 20px;
    color: #333;
    font-size: 24px;
}

.refund-form-container {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    border: 0.1px solid #e5e4e4;
    padding: 20px;
}

/* Notification styling */
.notification-box {
    display: flex;
    background-color: #f8f8f8;
    border-left: 4px solid #e74c3c;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
    align-items: flex-start;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.notification-box.success {
    border-left-color: #2ecc71;
    background-color: #f8fff8;
}

.notification-box.success .notification-icon {
    color: #2ecc71;
}

.notification-box.success h3 {
    color: #2ecc71;
}

.notification-icon {
    flex: 0 0 60px;
    font-size: 32px;
    color: #e74c3c;
    text-align: center;
}

.notification-content {
    flex: 1;
}

.notification-content h3 {
    margin: 0 0 10px 0;
    color: #e74c3c;
    font-size: 18px;
}

.notification-content p {
    margin: 0 0 10px 0;
    color: #555;
    line-height: 1.5;
}

.contact-info {
    margin-top: 15px;
    font-size: 14px;
    color: #666;
}

.contact-info strong {
    color: #333;
}

.button-container {
    margin-top: 20px;
}

.btn-primary {
    background-color: #3c8c6c;
    color: #ffffff;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: background-color 0.3s ease;
}

.btn-primary:hover {
    background-color: #2a6e50;
}

.refund-reason {
    margin-bottom: 20px;
}

.refund-reason label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 5px;
}

.refund-reason select {
    width: 100%;
    padding: 10px;
    font-size: 14px;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-sizing: border-box;
    background-color: #fff;
    height: 45px;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23555' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 16px;
    cursor: pointer;
}

.refund-reason select:focus {
    outline: none;
    border-color: #008474;
}

#other_reason_container textarea {
    width: 100%;
    padding: 10px;
    font-size: 14px;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-sizing: border-box;
    resize: vertical;
}

#other_reason_container textarea:focus {
    outline: none;
    border-color: #008474;
}

.refund-info {
    background-color: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.refund-info p {
    margin: 0;
    font-size: 14px;
    line-height: 1.5;
}

.refund-actions {
    text-align: center;
    margin: 25px 0;
    padding: 15px 0;
    border-top: 1px solid #eee;
    border-bottom: 1px solid #eee;
}

.confirm-refund-btn, .refund-receipt-btn {
    padding: 12px 30px;
    font-size: 16px;
    color: white;
    background-color: #008474;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s ease;
    min-width: 200px;
    display: inline-block;
    text-decoration: none;
}

.confirm-refund-btn:hover, .refund-receipt-btn:hover {
    background-color: #006b5c;
}

.refund-policy {
    text-align: center;
}

.refund-policy a {
    color: #008474;
    text-decoration: none;
    font-size: 14px;
}

.refund-policy a:hover {
    text-decoration: underline;
}

.refund-status {
    text-align: center;
    padding: 20px;
}

.loader {
    display: none;
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-radius: 50%;
    border-top: 4px solid #008474;
    animation: spin 1s linear infinite;
    margin: 20px auto;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.confirm-refund-btn.loading {
    opacity: 0.7;
    cursor: not-allowed;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Show/hide the "Other" reason textarea based on selection
    $('#cancel_reason').on('change', function() {
        if ($(this).val() === 'other') {
            $('#other_reason_container').show();
        } else {
            $('#other_reason_container').hide();
        }
    });

    $('.confirm-refund-btn').click(function() {
        var $button = $(this);
        var reasonOption = $('#cancel_reason').val();
        
        if (!reasonOption) {
            alert('Vennligst velg en årsak til kansellering');
            return;
        }
        
        // Get the display reason text (either selected option text or custom reason)
        var reasonText = $('#cancel_reason option:selected').text();
        var otherReasonText = '';
        
        // If "Other" is selected, get the custom reason
        if (reasonOption === 'other') {
            otherReasonText = $('#other_reason').val();
            if (!otherReasonText) {
                alert('Vennligst spesifiser årsaken');
                return;
            }
        }

        // Disable button and show loader
        $button.addClass('loading').prop('disabled', true);
        $button.after('<div class="loader" style="display: block;"></div>');
        $button.hide()

        // Here you would typically make an AJAX call to process the refund
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'process_refund',
                order_id: '<?php echo esc_js($order_id); ?>',
                booking_id: '<?php echo esc_js($booking_id); ?>',
                reason_option: reasonOption,
                reason_text: reasonOption === 'other' ? otherReasonText : reasonText
            },
            success: function(response) {
                if (response.success) {
                    jQuery('.refund_container').html(response.data.html);
                } else {
                    $button.show()
                    alert('Feil ved behandling av kansellering:');
                    // Re-enable button and hide loader
                    $button.removeClass('loading').prop('disabled', false);
                    $('.loader').remove();
                }
            },
            error: function() {
                $button.show()
                alert('Feil ved behandling av kansellering. Vennligst prøv igjen.');
                // Re-enable button and hide loader
                $button.removeClass('loading').prop('disabled', false);
                $('.loader').remove();
            }
        });
    });
});
</script> 