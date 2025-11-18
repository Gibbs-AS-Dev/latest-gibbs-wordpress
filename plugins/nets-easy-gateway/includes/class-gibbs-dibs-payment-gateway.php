<?php
defined('ABSPATH') || exit;

class WC_Gateway_Gibbs_DIBS_Payment extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'nets_easy';
        $this->method_title = 'DIBS Payment Gateway';
        $this->method_description = 'Pay with DIBS Payment';
        $this->has_fields = false;
        $this->supports = array(
            'products',
            'refunds'
        );

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title', 'Nets Easy');
        $this->enabled = $this->get_option('enabled');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title' => 'Enable/Disable',
                'label' => 'Enable DIBS Payment Gateway',
                'type' => 'checkbox',
                'default' => 'yes'
            ],
            'title' => [
                'title' => 'Title',
                'type' => 'text',
                'default' => 'DIBS Payment Gateway'
            ],
            'description' => [
                'title' => 'Description',
                'type' => 'textarea',
                'default' => 'Pay securely with DIBS Payment'
            ],
            'environment' => [
                'title' => 'Environment',
                'type' => 'select',
                'description' => 'Choose between Test and Live mode.',
                'default' => 'test',
                'options' => [
                    'test' => 'Test',
                    'live' => 'Live'
                ]
            ],
            'test_secret_key' => [
                'title' => 'Test Secret Key',
                'type' => 'password',
                'description' => 'Enter your test secret key from DIBS Payment'
            ],
            'test_checkout_key' => [
                'title' => 'Test Checkout Key',
                'type' => 'text',
                'description' => 'Enter your test checkout key from DIBS Payment'
            ],
            'live_secret_key' => [
                'title' => 'Live Secret Key',
                'type' => 'password',
                'description' => 'Enter your live secret key from DIBS Payment'
            ],
            'live_checkout_key' => [
                'title' => 'Live Checkout Key',
                'type' => 'text',
                'description' => 'Enter your live checkout key from DIBS Payment'
            ],
            'merchant_id' => [
                'title' => 'Merchant ID',
                'type' => 'text',
                'description' => 'Enter your DIBS Payment merchant ID'
            ],
            'split_percentage' => [
                'title' => 'Admin Split Percentage',
                'type' => 'number',
                'default' => '20',
                'description' => 'Percentage of payment that goes to admin (e.g., 20 for 20%)'
            ],
            'webhook_secret' => [
                'title' => 'Webhook Secret',
                'type' => 'password',
                'description' => 'Enter webhook secret for security verification'
            ]
        ];
    }

    public function process_payment($order_id, $return_data_only = false) {
        global $wpdb;

        $order = wc_get_order($order_id);
        $booking_table = $wpdb->prefix . "bookings_calendar";

        $booking = $wpdb->get_row("SELECT * FROM ".$booking_table." WHERE order_id = '".$order->get_id()."'", ARRAY_A);

        $listing_id = $booking['listing_id'];

        $author_id = $booking['bookings_author'];

        $booking_user = get_user_by('ID', $author_id);

        $billing_phone = $order->get_billing_phone();
        
        $billing_country_code = get_user_meta($author_id, 'billing_country_code', true);

        if($billing_phone == "" || $billing_phone == null){
            $billing_phone = get_user_meta($author_id, 'phone', true);
        }
        if($billing_country_code == "" || $billing_country_code == null){
            $billing_country_code = get_user_meta($author_id, 'country_code', true);
        }

        if($billing_country_code != ""){
            $billing_country_code = str_replace("+", "", $billing_country_code);
            $billing_country_code = "+" . $billing_country_code;
        }else{
            $billing_country_code = "+47";
        }
        
        
      //  echo $billing_country_code; die;

        $settings = get_option('woocommerce_nets_easy_settings');
        $this->environment = isset($settings['environment']) ? $settings['environment'] : 'test';

        // Gather order items and build payment data
        $items = [];
        $line_ids = [];
        $product_ids = [];
        $item_names = [];
        
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $product_ids[] = (string)$item->get_product_id();
            $line_ids[] = (string)$item_id;
            $item_names[] = $item->get_name();
        }

        $order_amount = $order->get_total();
        $order_amount_int = intval(round($order_amount * 100));

        // Convert 2-letter country codes to 3-letter ISO codes
        $billing_country_3 = $this->convert_country_code_to_3_letter($order->get_billing_country());
        $shipping_country_3 = $this->convert_country_code_to_3_letter($order->get_shipping_country());

        // Build payment data for DIBS Payment
        $payment_data = [
            'order' => [
                'items' => [
                    [
                        'reference' => implode(",", $product_ids),
                        'name' => implode(",", $item_names),
                        'quantity' => 1,
                        'unit' => 'pcs',
                        'unitPrice' => $order_amount_int,
                        'taxRate' => 0,
                        'taxAmount' => 0,
                        'grossTotalAmount' => $order_amount_int,
                        'netTotalAmount' => $order_amount_int,
                        'imageUrl' => ''
                    ]
                ],
                'amount' => $order_amount_int,
                'currency' => $order->get_currency(),
                'reference' => (string)$order->get_id()
            ],
            'checkout' => [
                'url' => $this->get_return_url($order),
                'integrationType' => 'EmbeddedCheckout',
                "merchantHandlesConsumerData" => true,
                'consumer' => array_filter([
                    'reference' => (string)$order->get_id(),
                    'email' => $order->get_billing_email() ?: null,
                    'shippingAddress' => $this->build_address_data($order, 'shipping', $shipping_country_3),
                    'billingAddress' => $this->build_address_data($order, 'billing', $billing_country_3),
                    'phoneNumber' => $this->build_phone_data($billing_phone, $billing_country_code),
                ], function($value) {
                    return $value !== null && $value !== [];
                }),
                'termsUrl' => get_privacy_policy_url(),
                'merchantTermsUrl' => get_privacy_policy_url(),
                'charge' => true,
                'preferredPaymentMethod' => 'Vipps'
            ],
            'notifications' => [
                'webHooks' => [
                    [
                        'eventName' => 'payment.checkout.completed',
                        'url' => Gibbs_DIBS_Payment_Webhook_Handler::get_webhook_url(),
                        'authorization' => $this->get_webhook_authorization()
                    ],
                    [
                        'eventName' => 'payment.charge.created',
                        'url' => Gibbs_DIBS_Payment_Webhook_Handler::get_webhook_url(),
                        'authorization' => $this->get_webhook_authorization()
                    ]
                ]
            ]
        ];

        //echo "<pre>"; print_r($payment_data); die;

        $nets_easy_api = new Gibbs_DIBS_Payment_API();

        // if($return_data_only){
        //     return [
        //         'result' => 'success',
        //         'payment' => $payment_data,
        //         'access_token' => $nets_easy_api->getAccessToken(),
        //     ];
        // }
       

        try {
            $checkout = $nets_easy_api->create_checkout($payment_data);

           

            if (!empty($checkout['paymentId'])) {
                // Store payment ID for webhook handling
                $order->update_meta_data('nets_easy_payment_id', $checkout['paymentId']);
                $order->update_meta_data('_dibs_payment_id', $checkout['paymentId']);
                $order->save_meta_data();

                return [
                    'result' => 'success',
                    'paymentId' => $checkout['paymentId'],
                    'checkoutKey' => $checkout['checkoutKey'],
                    'mode' => $checkout['mode']
                ];
            } else {
                wc_add_notice(__('Could not initiate DIBS Payment. Please try again.'), 'error');
                return ['result' => 'failure', 'message' => 'Could not initiate DIBS Payment. Please try again.'];
            }
        } catch (Exception $e) {
            wc_add_notice(__('Payment error: ', 'woocommerce') . $e->getMessage(), 'error');
            return ['result' => 'failure', 'message' => $e->getMessage()];
        }
    }

    /**
     * Get webhook authorization header
     */
    private function get_webhook_authorization() {
        $settings = get_option('woocommerce_nets_easy_settings');
        $webhook_secret = isset($settings['webhook_secret']) ? $settings['webhook_secret'] : '';
        
        if (empty($webhook_secret)) {
            return 'Basic ' . base64_encode('merchant:' . $this->get_secret_key());
        }
        
        return 'Basic ' . base64_encode('merchant:' . $webhook_secret);
    }

    /**
     * Get secret key based on environment
     */
    private function get_secret_key() {
        $settings = get_option('woocommerce_nets_easy_settings');
        $environment = isset($settings['environment']) ? $settings['environment'] : 'test';
        
        if ($environment === 'live') {
            return isset($settings['live_secret_key']) ? $settings['live_secret_key'] : '';
        } else {
            return isset($settings['test_secret_key']) ? $settings['test_secret_key'] : '';
        }
    }

    /**
     * Get order data from DIBS Payment transaction
     */
    public function get_order_data_from_dibs_payment($payment_id) {
        try {
            $dibs_payment_api = new Gibbs_DIBS_Payment_API();
            $payment_data = $dibs_payment_api->get_payment($payment_id);
            
            if ($payment_data && isset($payment_data['order'])) {
                return $payment_data['order'];
            }
            
            return null;
        } catch (Exception $e) {
            error_log('Error getting order data from DIBS Payment: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get payment status from DIBS Payment
     */
    public function get_payment_status_from_dibs_payment($payment_id) {
        try {
            $dibs_payment_api = new Gibbs_DIBS_Payment_API();
            $payment_data = $dibs_payment_api->get_payment($payment_id);
            
            if ($payment_data && isset($payment_data['summary'])) {
                return $payment_data['summary']['state'];
            }
            
            return null;
        } catch (Exception $e) {
            error_log('Error getting payment status from DIBS Payment: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Convert 2-letter country code to 3-letter ISO code
     */
    private function convert_country_code_to_3_letter($country_code_2) {
        $country_mapping = [
            'AD' => 'AND', 'AE' => 'ARE', 'AF' => 'AFG', 'AG' => 'ATG', 'AI' => 'AIA',
            'AL' => 'ALB', 'AM' => 'ARM', 'AO' => 'AGO', 'AQ' => 'ATA', 'AR' => 'ARG',
            'AS' => 'ASM', 'AT' => 'AUT', 'AU' => 'AUS', 'AW' => 'ABW', 'AX' => 'ALA',
            'AZ' => 'AZE', 'BA' => 'BIH', 'BB' => 'BRB', 'BD' => 'BGD', 'BE' => 'BEL',
            'BF' => 'BFA', 'BG' => 'BGR', 'BH' => 'BHR', 'BI' => 'BDI', 'BJ' => 'BEN',
            'BL' => 'BLM', 'BM' => 'BMU', 'BN' => 'BRN', 'BO' => 'BOL', 'BQ' => 'BES',
            'BR' => 'BRA', 'BS' => 'BHS', 'BT' => 'BTN', 'BV' => 'BVT', 'BW' => 'BWA',
            'BY' => 'BLR', 'BZ' => 'BLZ', 'CA' => 'CAN', 'CC' => 'CCK', 'CD' => 'COD',
            'CF' => 'CAF', 'CG' => 'COG', 'CH' => 'CHE', 'CI' => 'CIV', 'CK' => 'COK',
            'CL' => 'CHL', 'CM' => 'CMR', 'CN' => 'CHN', 'CO' => 'COL', 'CR' => 'CRI',
            'CU' => 'CUB', 'CV' => 'CPV', 'CW' => 'CUW', 'CX' => 'CXR', 'CY' => 'CYP',
            'CZ' => 'CZE', 'DE' => 'DEU', 'DJ' => 'DJI', 'DK' => 'DNK', 'DM' => 'DMA',
            'DO' => 'DOM', 'DZ' => 'DZA', 'EC' => 'ECU', 'EE' => 'EST', 'EG' => 'EGY',
            'EH' => 'ESH', 'ER' => 'ERI', 'ES' => 'ESP', 'ET' => 'ETH', 'FI' => 'FIN',
            'FJ' => 'FJI', 'FK' => 'FLK', 'FM' => 'FSM', 'FO' => 'FRO', 'FR' => 'FRA',
            'GA' => 'GAB', 'GB' => 'GBR', 'GD' => 'GRD', 'GE' => 'GEO', 'GF' => 'GUF',
            'GG' => 'GGY', 'GH' => 'GHA', 'GI' => 'GIB', 'GL' => 'GRL', 'GM' => 'GMB',
            'GN' => 'GIN', 'GP' => 'GLP', 'GQ' => 'GNQ', 'GR' => 'GRC', 'GS' => 'SGS',
            'GT' => 'GTM', 'GU' => 'GUM', 'GW' => 'GNB', 'GY' => 'GUY', 'HK' => 'HKG',
            'HM' => 'HMD', 'HN' => 'HND', 'HR' => 'HRV', 'HT' => 'HTI', 'HU' => 'HUN',
            'ID' => 'IDN', 'IE' => 'IRL', 'IL' => 'ISR', 'IM' => 'IMN', 'IN' => 'IND',
            'IO' => 'IOT', 'IQ' => 'IRQ', 'IR' => 'IRN', 'IS' => 'ISL', 'IT' => 'ITA',
            'JE' => 'JEY', 'JM' => 'JAM', 'JO' => 'JOR', 'JP' => 'JPN', 'KE' => 'KEN',
            'KG' => 'KGZ', 'KH' => 'KHM', 'KI' => 'KIR', 'KM' => 'COM', 'KN' => 'KNA',
            'KP' => 'PRK', 'KR' => 'KOR', 'KW' => 'KWT', 'KY' => 'CYM', 'KZ' => 'KAZ',
            'LA' => 'LAO', 'LB' => 'LBN', 'LC' => 'LCA', 'LI' => 'LIE', 'LK' => 'LKA',
            'LR' => 'LBR', 'LS' => 'LSO', 'LT' => 'LTU', 'LU' => 'LUX', 'LV' => 'LVA',
            'LY' => 'LBY', 'MA' => 'MAR', 'MC' => 'MCO', 'MD' => 'MDA', 'ME' => 'MNE',
            'MF' => 'MAF', 'MG' => 'MDG', 'MH' => 'MHL', 'MK' => 'MKD', 'ML' => 'MLI',
            'MM' => 'MMR', 'MN' => 'MNG', 'MO' => 'MAC', 'MP' => 'MNP', 'MQ' => 'MTQ',
            'MR' => 'MRT', 'MS' => 'MSR', 'MT' => 'MLT', 'MU' => 'MUS', 'MV' => 'MDV',
            'MW' => 'MWI', 'MX' => 'MEX', 'MY' => 'MYS', 'MZ' => 'MOZ', 'NA' => 'NAM',
            'NC' => 'NCL', 'NE' => 'NER', 'NF' => 'NFK', 'NG' => 'NGA', 'NI' => 'NIC',
            'NL' => 'NLD', 'NO' => 'NOR', 'NP' => 'NPL', 'NR' => 'NRU', 'NU' => 'NIU',
            'NZ' => 'NZL', 'OM' => 'OMN', 'PA' => 'PAN', 'PE' => 'PER', 'PF' => 'PYF',
            'PG' => 'PNG', 'PH' => 'PHL', 'PK' => 'PAK', 'PL' => 'POL', 'PM' => 'SPM',
            'PN' => 'PCN', 'PR' => 'PRI', 'PS' => 'PSE', 'PT' => 'PRT', 'PW' => 'PLW',
            'PY' => 'PRY', 'QA' => 'QAT', 'RE' => 'REU', 'RO' => 'ROU', 'RS' => 'SRB',
            'RU' => 'RUS', 'RW' => 'RWA', 'SA' => 'SAU', 'SB' => 'SLB', 'SC' => 'SYC',
            'SD' => 'SDN', 'SE' => 'SWE', 'SG' => 'SGP', 'SH' => 'SHN', 'SI' => 'SVN',
            'SJ' => 'SJM', 'SK' => 'SVK', 'SL' => 'SLE', 'SM' => 'SMR', 'SN' => 'SEN',
            'SO' => 'SOM', 'SR' => 'SUR', 'SS' => 'SSD', 'ST' => 'STP', 'SV' => 'SLV',
            'SX' => 'SXM', 'SY' => 'SYR', 'SZ' => 'SWZ', 'TC' => 'TCA', 'TD' => 'TCD',
            'TF' => 'ATF', 'TG' => 'TGO', 'TH' => 'THA', 'TJ' => 'TJK', 'TK' => 'TKL',
            'TL' => 'TLS', 'TM' => 'TKM', 'TN' => 'TUN', 'TO' => 'TON', 'TR' => 'TUR',
            'TT' => 'TTO', 'TV' => 'TUV', 'TW' => 'TWN', 'TZ' => 'TZA', 'UA' => 'UKR',
            'UG' => 'UGA', 'UM' => 'UMI', 'US' => 'USA', 'UY' => 'URY', 'UZ' => 'UZB',
            'VA' => 'VAT', 'VC' => 'VCT', 'VE' => 'VEN', 'VG' => 'VGB', 'VI' => 'VIR',
            'VN' => 'VNM', 'VU' => 'VUT', 'WF' => 'WLF', 'WS' => 'WSM', 'YE' => 'YEM',
            'YT' => 'MYT', 'ZA' => 'ZAF', 'ZM' => 'ZMB', 'ZW' => 'ZWE'
        ];
        
        return isset($country_mapping[strtoupper($country_code_2)]) ? $country_mapping[strtoupper($country_code_2)] : 'NOR';
    }

    private function build_phone_data($billing_phone, $billing_country_code) {
        if($billing_phone != "" && $billing_phone != null && $billing_country_code != "" && $billing_country_code != null){
            return [
                'prefix' => $billing_country_code,
                'number' => $billing_phone
            ];
        }else{
            return null;
        }
    }

    /**
     * Build address data only if it exists
     */
    private function build_address_data($order, $type, $country_3) {
        $address_data = [];
        
        if ($type === 'shipping') {
            $address1 = $order->get_billing_address_1();
            $address2 = $order->get_billing_address_2();
            $postal_code = $order->get_billing_postcode();
            $city = $order->get_billing_city();
        } else {
            $address1 = $order->get_billing_address_1();
            $address2 = $order->get_billing_address_2();
            $postal_code = $order->get_billing_postcode();
            $city = $order->get_billing_city();
        }
        
        if (!empty($address1)) {
            $address_data['addressLine1'] = $address1;
        }else{
            $address_data['addressLine1'] = "Ikke valgt";
        }
        if (!empty($address2)) {
            $address_data['addressLine2'] = $address2;
        }
        if (!empty($postal_code)) {
            $address_data['postalCode'] = $postal_code;
        }else{
            $address_data['postalCode'] = 1111;
        }
        if (!empty($city)) {
            $address_data['city'] = $city;
        }else{
            $address_data['city'] = "Ikke valgt";
        }
        if (!empty($country_3)) {
            $address_data['country'] = $country_3;
        }else{
            $address_data['country'] = "NOR";
        }
        
        return !empty($address_data) ? $address_data : null;
    }

    

    /**
     * Build person data only if it exists
     */
    private function build_person_data($order) {
        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();
        
        if (empty($first_name) && empty($last_name)) {
            return null;
        }
        
        $person_data = [];
        if (!empty($first_name)) {
            $person_data['firstName'] = $first_name;
        }
        if (!empty($last_name)) {
            $person_data['lastName'] = $last_name;
        }
        
        return $person_data;
    }

    /**
     * Process refund
     *
     * @param  int    $order_id WooCommerce order ID.
     * @param  string $amount Refund amount.
     * @param  string $reason Reason text message for the refund.
     *
     * @return bool|WP_Error
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('refund_error', 'Order not found');
        }

        try {
            $api = new Gibbs_DIBS_Payment_API();
            $response = $api->process_refund($order_id, $amount, $reason);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            if ($response === true) {
                // Refund was successful
                $order->add_order_note(
                    sprintf(
                        __('Refund processed successfully via DIBS Payment. Amount: %s. Reason: %s', 'woocommerce'),
                        wc_price($amount),
                        $reason
                    )
                );
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log('Gateway refund error: ' . $e->getMessage());
            return new WP_Error('refund_error', $e->getMessage());
        }
    }
}
