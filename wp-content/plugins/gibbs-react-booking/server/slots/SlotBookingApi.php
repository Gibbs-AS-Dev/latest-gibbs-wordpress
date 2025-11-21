<?php
/**
 * Slot Booking API Handler
 * Extends CoreApiHandler for slot booking functionality
 */

require_once  '../ApiHandler.php';

class SlotBookingApi extends CoreApiHandler {
    
    private $slots_table;
    
    public function __construct() {
        parent::__construct();
        $this->slots_table = $this->getDatabase()->getTablePrefix() . 'gibbs_slots';
        
    }
    
    /**
     * Handle slot booking specific requests
     */
    public function handleSlotBookingRequest() {
        try {
            $method = CoreResponse::getRequestMethod();
            $data = CoreResponse::getRequestData();
            
            switch ($method) {
                case 'GET':
                    $this->handleSlotGetRequest($data);
                    break;
                case 'POST':
                    $this->handleSlotPostRequest($data);
                    break;
                case 'PUT':
                    $this->handleSlotPutRequest($data);
                    break;
                case 'DELETE':
                    $this->handleSlotDeleteRequest($data);
                    break;
                default:
                    CoreResponse::error('Method not allowed', 405);
            }
        } catch (Exception $e) {
            CoreResponse::serverError($e->getMessage());
        }
    }
    
    /**
     * Handle GET requests for slot booking
     */
    private function handleSlotGetRequest($data) {
        $action = isset($data['action']) ? $data['action'] : 'get_available_dates'; 
        $listing_id = isset($data['listing_id']) ? $data['listing_id'] : null;

        if(isset($data['code']) && $data['code'] != ""){
           $action = 'access_login';
        }
        if(isset($data['error']) && $data['error'] != ""){
           $action = 'error_login';
        }

        
        switch ($action) {
            case 'get_available_dates':
                $this->getAvailableDates($listing_id);
                break;
            case 'get_post_meta':
                $this->getPostMeta($data);
                break;
            case 'UserListingInfo':
                $this->UserListingInfo($data);
                break;
            case 'get_booking_by_id':
                $this->getBookingById($data);
                break;
            case 'getExtraFields':
                $this->getExtraFields($data);
                break;
            case 'getListingImage':
                $this->getListingImage($data);
                break;
            case 'getListingMeta':
                $this->getListingMeta($data);
                break;
            case 'getPaymentMethods':
                $this->getPaymentMethods($data);
                break;
            case 'social_login':
                $this->socialLogin($data);
                break;
            case 'access_login':
                $this->accessLogin($data);
                break;
            case 'error_login':
                $this->errorLogin($data);
                break;
            default:
                CoreResponse::error('Invalid action', 400);
        }
    }
    
    /**
     * Handle POST requests for slot booking
     */
    private function handleSlotPostRequest($data) {
        $action = isset($data['action']) ? $data['action'] : 'book_slot';
        
        switch ($action) {
            case 'getSlotPrice':
                $this->getSlotPrice($data);
                break;
            case 'bookConfirmation':
                $this->bookConfirmation($data);
                break;
            case 'getSlotBookingConfirmation':
                $this->getSlotBookingConfirmation($data);
                break;
            case 'applyCoupon':
                $this->applyCoupon($data);
                break;
            case 'email_user_data':
                $this->email_user_data($data);
                break;
            case 'fetch_user_data_by_email_or_phone':
                $this->fetch_user_data_by_email_or_phone($data);
                break;
            case 'submitSlotBooking':
                $this->submitSlotBooking($data);
                break;
            case 'savePaymentId':
                $this->savePaymentId($data);
                break;
            case 'updateDibsPaymentStatus':
                $this->updateDibsPaymentStatus($data);
                break;
            case 'exchange_social_token':
                $this->exchangeSocialToken($data);
                break;
            default:
                CoreResponse::error('Invalid action', 400);
        }
    }
    
    /**
     * Handle PUT requests for slot booking
     */
    private function handleSlotPutRequest($data) {
        $action = isset($data['action']) ? $data['action'] : 'update_booking';
        
        switch ($action) {
            case 'update_booking':
                $this->updateBooking($data);
                break;
            default:
                CoreResponse::error('Invalid action', 400);
        }
    }
    
    /**
     * Handle DELETE requests for slot booking
     */
    private function handleSlotDeleteRequest($data) {
        $action = isset($data['action']) ? $data['action'] : 'cancel_booking';
        
        switch ($action) {
            case 'cancel_booking':
                $this->cancelBooking($data);
                break;
            default:
                CoreResponse::error('Invalid action', 400);
        }
    }
    public function errorLogin($data) {
        try{
            ?>
            <script>
                window.opener.postMessage(
                    { error: 'No response from the popup' },
                    "*"
                );
                window.close();
            </script>
            <?php
            
            die('Error logging in');
        }catch(Exception $e){
            CoreResponse::error('Error logging in', 400);
        }
    }

    public function accessLogin($data) {
        try{
            ?>
            <script>
                window.opener.postMessage(
                    { code: '<?php echo $data['code']; ?>' },
                    "*"
                );
                window.close();
            </script>
            <?php
            die('Redirecting...');
           //coreResponse::success($data, 'Login successful', 200);
        }catch(Exception $e){
            CoreResponse::error('Error accessing login', 400);
        }
    }

    public function exchangeSocialToken($data) {
        try{
            $provider = $data['provider'];
            $code = $data['code'];
            $booking_token = $data['booking_token'];
            $apiUrl = $data['apiUrl'];

           

            if($provider == 'vipps'){
                $this->exchangeVippsToken($code, $booking_token, $apiUrl);
            }

            coreResponse::success($data, 'Social token exchanged successfully', 200);
        }catch(Exception $e){
            CoreResponse::error('Error exchanging social token', 400);
        }
    }
    public function exchangeVippsToken($code, $booking_token, $apiUrl) {

        if ( ! function_exists( 'get_current_user_id' ) ) {
            $wp_load_path = dirname( __FILE__, 6 ) . '/wp-load.php';
            if ( file_exists( $wp_load_path ) ) {
                require_once( $wp_load_path );
            } else {
                CoreResponse::error('WordPress core not found', 400);
            }
        }

        try {
            $vipps_data = get_option('rmp_oauth_settings',array());
            $vipps_client_id = $vipps_data['vipps']['client_id'];
            $vipps_client_secret = $vipps_data['vipps']['client_secret'];
            $vipps_redirect_uri = $vipps_data['vipps']['redirect_uri'];
            $token_url = "https://api.vipps.no/access-management-1.0/access/oauth2/token";

            $postData = [
                "grant_type" => "authorization_code",
                "code" => $code,
                "redirect_uri" => $vipps_redirect_uri,
            ];

            // Use HTTP Basic Authentication in the header instead of sending client credentials in POST data
            $auth = base64_encode($vipps_client_id . ':' . $vipps_client_secret);

            $ch = curl_init($token_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/x-www-form-urlencoded",
                "Authorization: Basic $auth"
            ]);
            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                CoreResponse::error('CURL error: ' . curl_error($ch), 400);
            }
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $data = json_decode($response, true);

            if ($httpCode === 200 && isset($data['access_token'])) {
                // Fetch user info with access_token
                $access_token = $data['access_token'];
                $userinfo_url = "https://api.vipps.no/vipps-userinfo-api/userinfo";

                $ch_userinfo = curl_init($userinfo_url);
                curl_setopt($ch_userinfo, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch_userinfo, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer $access_token"
                ]);
                $userinfo_response = curl_exec($ch_userinfo);

                if (curl_errno($ch_userinfo)) {
                    CoreResponse::error('CURL error fetching user info: ' . curl_error($ch_userinfo), 400);
                }
                $userinfo_httpCode = curl_getinfo($ch_userinfo, CURLINFO_HTTP_CODE);
                curl_close($ch_userinfo);

                $userinfo = json_decode($userinfo_response, true);

                if ($userinfo_httpCode === 200 && $userinfo) {

                    $this->vippsLogin($userinfo, $booking_token);

                } else {
                    $error_msg = isset($userinfo['error_description']) ? $userinfo['error_description'] : 'Failed to fetch user info';
                    CoreResponse::error($error_msg, 400);
                }
            } else {
                $error_msg = isset($data['error_description']) ? $data['error_description'] : 'Failed to exchange Vipps token';
                CoreResponse::error($error_msg, 400);
            }
        } catch(Exception $e) {
            CoreResponse::error('Error exchanging Vipps token: ' . $e->getMessage(), 400);
        }
    }

    public function vippsLogin($userinfo, $booking_token) {
        try{
            $userinfo = $userinfo;
            $booking_token = $booking_token;

            $nameParts = explode(" ", $userinfo['name']);
            $firstName = isset($nameParts[0]) ? ucfirst($nameParts[0]) : '';
            $lastName = isset($nameParts[1]) ? ucfirst(implode(" ", array_slice($nameParts, 1))) : '';

            $email = $userinfo['email'];
            $phone = $userinfo['phone_number'];

            // Extract the country code from the phone number
            $countryCode = $this->getCountryCodeFromPhoneNumber($phone);


            $phone = "+" .$phone;

            $phone = str_replace($countryCode, "", $phone);

            $data = [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'countryCode' => $countryCode,
            ];

            $user_dd = $this->registerUser($data);

            coreResponse::success($user_dd, 'User registered successfully', 200);
            

        }catch(Exception $e){
            CoreResponse::error('Error logging in with Vipps', 400);
        }
    }

    public function getCountryCodeFromPhoneNumber($phoneNumber) {
        $country_codes = $this->getCountryCode(); // Call your country code array function
    
        // Strip non-numeric characters (e.g., hyphens, spaces)
        $phoneNumber = preg_replace('/\D/', '', $phoneNumber);
    
        // Iterate over the country codes and check for a match
        foreach ($country_codes as $country => $code) {
            if (strpos($phoneNumber, $code) === 0) {
                return "+" . $code; // Return the country code if matched
            }
        }
    
        // If no match, return null or some other default value
        return null;
    }
    public function getCountryCode() {
        $country_codes = [
            'United States/Canada'           => '1',
            'Egypt'                          => '20',
            'Greece'                         => '30',
            'Netherlands'                    => '31',
            'Belgium'                        => '32',
            'France'                         => '33',
            'Spain'                          => '34',
            'Hungary'                        => '36',
            'Italy'                          => '39',
            'Romania'                        => '40',
            'Switzerland'                    => '41',
            'Czech Republic'                 => '420',
            'Austria'                        => '43',
            'United Kingdom'                 => '44',
            'Denmark'                        => '45',
            'Sweden'                         => '46',
            'Norway'                         => '47',
            'Poland'                         => '48',
            'Germany'                        => '49',
            'Liechtenstein'                  => '50',
            'Peru'                           => '51',
            'Mexico'                         => '52',
            'Cuba'                           => '53',
            'Argentina'                      => '54',
            'Brazil'                         => '55',
            'Chile'                          => '56',
            'Colombia'                       => '57',
            'Venezuela'                      => '58',
            'Malaysia'                       => '60',
            'Australia'                      => '61',
            'Indonesia'                      => '62',
            'Philippines'                    => '63',
            'New Zealand'                    => '64',
            'Singapore'                      => '65',
            'Thailand'                       => '66',
            'Japan'                          => '81',
            'South Korea'                    => '82',
            'Vietnam'                        => '84',
            'China'                          => '86',
            'Turkey'                         => '90',
            'India'                          => '91',
            'Pakistan'                       => '92',
            'Afghanistan'                    => '93',
            'Sri Lanka'                      => '94',
            'Myanmar (Burma)'                => '95',
            'Iran'                           => '98',
            'Morocco'                        => '212',
            'Algeria'                        => '213',
            'Tunisia'                        => '216',
            'Libya'                          => '218',
            'Gambia'                         => '220',
            'Senegal'                        => '221',
            'Mauritania'                     => '222',
            'Mali'                           => '223',
            'Guinea'                         => '224',
            'Ivory Coast'                    => '225',
            'Burkina Faso'                   => '226',
            'Niger'                          => '227',
            'Togo'                           => '228',
            'Benin'                          => '229',
            'Mauritius'                      => '230',
            'Liberia'                        => '231',
            'Sierra Leone'                   => '232',
            'Ghana'                          => '233',
            'Nigeria'                        => '234',
            'Chad'                           => '235',
            'Central African Republic'        => '236',
            'Cameroon'                       => '237',
            'Cape Verde'                     => '238',
            'São Tomé and Príncipe'          => '239',
            'Equatorial Guinea'              => '240',
            'Gabon'                          => '241',
            'Congo-Brazzaville'              => '242',
            'Congo-Kinshasa'                 => '243',
            'Angola'                         => '244',
            'Guinea-Bissau'                  => '245',
            'British Indian Ocean Territory' => '246',
            'Ascension Island'               => '247',
            'Seychelles'                     => '248',
            'Sudan'                          => '249',
            'Rwanda'                         => '250',
            'Ethiopia'                       => '251',
            'Somalia'                        => '252',
            'Djibouti'                       => '253',
            'Kenya'                          => '254',
            'Tanzania'                       => '255',
            'Uganda'                         => '256',
            'Burundi'                        => '257',
            'Mozambique'                     => '258',
            'Zambia'                         => '260',
            'Madagascar'                     => '261',
            'Réunion (France)'               => '262',
            'Zimbabwe'                       => '263',
            'Namibia'                        => '264',
            'Malawi'                         => '265',
            'Lesotho'                        => '266',
            'Botswana'                       => '267',
            'Eswatini (Swaziland)'           => '268',
            'Comoros'                        => '269',
            'Saint Helena'                   => '290',
            'Eritrea'                        => '291',
            'Aruba'                          => '297',
            'Faroe Islands'                  => '298',
            'Greenland'                      => '299',
            'Gibraltar'                      => '350',
            'Portugal'                       => '351',
            'Luxembourg'                     => '352',
            'Ireland'                        => '353',
            'Iceland'                        => '354',
            'Albania'                        => '355',
            'Malta'                          => '356',
            'Cyprus'                         => '357',
            'Finland'                        => '358',
            'Bulgaria'                       => '359',
            'Lithuania'                      => '370',
            'Latvia'                         => '371',
            'Estonia'                        => '372',
            'Moldova'                        => '373',
            'Armenia'                        => '374',
            'Belarus'                        => '375',
            'Andorra'                        => '376',
            'Monaco'                         => '377',
            'San Marino'                     => '378',
            'Vatican City'                   => '379',
            'Ukraine'                        => '380',
            'Serbia'                         => '381',
            'Montenegro'                     => '382',
            'Kosovo'                         => '383',
            'North Macedonia'                => '389',
            'Croatia'                        => '385',
            'Slovenia'                       => '386',
            'Bosnia and Herzegovina'         => '387',
            'Serbia and Montenegro'          => '388',
            'Slovakia'                       => '421',
            'Liechtenstein'                  => '423',
            'Falkland Islands'               => '500',
            'Belize'                         => '501',
            'Guatemala'                      => '502',
            'El Salvador'                    => '503',
            'Honduras'                       => '504',
            'Nicaragua'                      => '505',
            'Costa Rica'                     => '506',
            'Panama'                         => '507',
            'Saint Pierre and Miquelon'      => '508',
            'Haiti'                          => '509',
            'French Guiana'                  => '521',
            'Guyana'                         => '523',
            'Trinidad and Tobago'            => '524',
            'Saint Lucia'                    => '525',
            'Saint Vincent and the Grenadines'=> '526',
            'Barbados'                       => '527',
            'Grenada'                        => '528',
            'Saint Kitts and Nevis'          => '529',
            'Bermuda'                        => '531',
            'Jamaica'                        => '532',
            'Dominica'                       => '533',
            'Anguilla'                       => '534',
            'Antigua and Barbuda'            => '535',
            'Saint Barthélemy'               => '536',
            'Saint Martin'                   => '537',
            'Sint Eustatius'                 => '538',
            'Sint Maarten'                   => '539',
            'Cayman Islands'                 => '541',
            'Turks and Caicos Islands'       => '542',
            'Montserrat'                     => '543'
        ];

        return $country_codes;
    }

    public function socialLogin($data) {
        try{
            $provider = $data['provider'];
            $booking_token = $data['booking_token'];
            $apiUrl = $data['apiUrl'];

            if ( ! function_exists( 'get_current_user_id' ) ) {
                $wp_load_path = dirname( __FILE__, 6 ) . '/wp-load.php';
                if ( file_exists( $wp_load_path ) ) {
                    require_once( $wp_load_path );
                } else {
                    CoreResponse::error('WordPress core not found', 400);
                }
            }

            if ($provider == 'google') {
                $google_data = get_option('rmp_oauth_settings',array());
                $google_client_id = $google_data['google']['client_id'];
                $google_redirect_uri = $google_data['google']['redirect_uri'];
                $google_scope = $google_data['google']['scope'];
                $google_response_type = "code";
                // Example: Handle Google OAuth flow
                // This should redirect to Google's OAuth endpoint with your credentials
                $googleAuthUrl = sprintf(
                    "https://accounts.google.com/o/oauth2/v2/auth?response_type=%s&client_id=%s&redirect_uri=%s&scope=%s&state=%s",
                    urlencode($google_response_type),
                    urlencode($google_client_id),
                    urlencode($google_redirect_uri),
                    urlencode($google_scope),
                    urlencode($booking_token)
                );
                coreResponse::success(array(
                    'url' => $googleAuthUrl
                ), 'Google authentication URL generated successfully');
                die;
            
                // Send the user to the Google OAuth URL (will open in the popup)
                header("Location: $googleAuthUrl");
                exit();
            } elseif ($provider == 'microsoft') {

                $microsoft_data = get_option('rmp_oauth_settings',array());
                $microsoft_client_id = $microsoft_data['microsoft']['client_id'];
                $microsoft_redirect_uri = $microsoft_data['microsoft']['redirect_uri'];
                $microsoft_scope = $microsoft_data['microsoft']['scope'];
                $microsoft_response_type = "code";
                $microsoftAuthUrl = sprintf(
                    "https://login.microsoftonline.com/common/oauth2/v2.0/authorize?client_id=%s&response_type=%s&redirect_uri=%s&scope=%s&state=%s",
                    urlencode($microsoft_client_id),
                    urlencode($microsoft_response_type),
                    urlencode($microsoft_redirect_uri),
                    urlencode($microsoft_scope),
                    urlencode($booking_token)
                );
                coreResponse::success(array(
                    'url' => $microsoftAuthUrl
                ), 'Microsoft authentication URL generated successfully');
                die;
                header("Location: $microsoftAuthUrl");
                exit();
            } elseif ($provider == 'vipps') {

                $vipps_data = get_option('rmp_oauth_settings',array());

                $vipps_client_id = $vipps_data['vipps']['client_id'];
                $vipps_redirect_uri = $vipps_data['vipps']['redirect_uri'];
                $vipps_scope = $vipps_data['vipps']['scope'];
                $vipps_response_type = "code";
                $vippsAuthUrl = sprintf(
                    "https://api.vipps.no/access-management-1.0/access/oauth2/auth?client_id=%s&scope=%s&redirect_uri=%s&response_type=%s&state=%s",
                    urlencode($vipps_client_id),
                    urlencode($vipps_scope),
                    urlencode($vipps_redirect_uri),
                    urlencode($vipps_response_type),
                    urlencode($booking_token)
                );

                coreResponse::success(array(
                    'url' => $vippsAuthUrl
                ), 'Vipps authentication URL generated successfully');

                die;
               // $athur_url = "https://staging5.dev.gibbs.no/wp-content/plugins/gibbs-react-booking/server/slots/slot-booking-endpoint.php?code=hO_T-CC-ofMKgozPlLqB9qXk6D90FXNZn2Kw2VtAHcsxTuAVB_8-Wmubc6Yjwa-dOlTitNLa_KIiGARYh-VHukwMjz0OInoZA0bVA9c9DyM4NOQjV1FugXnGega0mfOC&scope=phoneNumber%20address%20openid%20name%20email&state=6992e05519b52ddf2881ea1c321fae27";
                header("Location: $vippsAuthUrl");
                exit();

            } else {
                CoreResponse::error('Invalid provider', 400);
                // Handle unsupported provider or error
                echo json_encode(["error" => "Invalid provider"]);
                exit();
            }


        }catch(Exception $e){
            CoreResponse::error('Error social login', 400);
        }
    }

    public function savePaymentId($data) {
        try{

            $order_id = $data['order_id'];
            $payment_id = $data['payment_id'];
            $checkout_url = $data['checkout_url'];

            $this->getDatabase()->updatePostMeta($order_id, 'dintero_payment_id', $payment_id);
            $this->getDatabase()->updatePostMeta($order_id, 'dintero_payment_url', $checkout_url);

            $payment_id_return = $this->getDatabase()->getPostMeta($order_id, 'dintero_payment_id', true);

            if($payment_id_return){ 
                CoreResponse::success(array(
                    'payment_id' => $payment_id_return
                ), 'Payment id saved successfully');
            }else{
                CoreResponse::error('Payment id not found', 400);
            }
        }catch(Exception $e){
            CoreResponse::error('Error saving payment id', 400);
            return;
        }
    }

    public function getPaymentMethods($data) {
        // Ensure WordPress core is loaded if this is called outside normal WP context
        if ( ! function_exists( 'get_current_user_id' ) ) {
            $wp_load_path = dirname( __FILE__, 6 ) . '/wp-load.php';
            if ( file_exists( $wp_load_path ) ) {
                require_once( $wp_load_path );
            } else {
                CoreResponse::error('WordPress core not found', 400);
            }
        }

        $available_gateways = $this->getWooCommercePaymentMethods();

       // $manual_invoice =  get_post_meta($data['listing_id'],"_manual_invoice_payment",true);
        $manual_invoice =  $this->getDatabase()->get_post_meta($data['listing_id'],"_manual_invoice_payment");


        //echo "<pre>"; print_r($manual_invoice); die;

        $payment_methods = array();
        foreach ($available_gateways as $gateway) {
            if($gateway->id == "dibs_easy"){
                continue;
            }
            if($manual_invoice == "dont_show_invoice"){
                if($gateway->id != "nets_easy"){
                    continue;
                }
            }
            if($manual_invoice == "only_show_invoice"){
                if($gateway->id != "cod"){
                    continue;
                }
            }
            $payment_methods[] = array(
                'id' => isset($gateway->id) ? $gateway->id : '',
                'title' => method_exists($gateway, 'get_title') ? $gateway->get_title() : (isset($gateway->title) ? $gateway->title : ''),
                'enabled' => isset($gateway->enabled) ? ($gateway->enabled === 'yes') : false,
                'description' => method_exists($gateway, 'get_description') ? $gateway->get_description() : '',
                'supports' => isset($gateway->supports) ? $gateway->supports : array(),
            );
        }

        CoreResponse::success($payment_methods, 'Payment methods retrieved successfully');
    }

    public function getExtraFields($data) {
        try{
            if ( ! function_exists( 'get_current_user_id' ) ) {
                // Try to include WordPress core if not already loaded
                $wp_load_path = dirname( __FILE__, 6 ) . '/wp-load.php';
                if ( file_exists( $wp_load_path ) ) {
                    require_once( $wp_load_path );
                }else{
                    CoreResponse::error('WordPress core not found', 400);
                }
            }

            
            if(!is_user_logged_in()){
                CoreResponse::error('User not logged in', 400);
            }
            $group_id = "";

            $fields_data = array();

            if (function_exists('advanced_fields')) {

                $listing_id = $data['listing_id'];

                $listing_data = $this->getDatabase()->getPost($listing_id);

                if(isset($listing_data['users_groups_id']) && $listing_data['users_groups_id'] != ""){
                   $group_id = $listing_data['users_groups_id'];

                    $fields_rowssss = advanced_fields(0, $group_id, 0, array(), 0, true, "booking_summery");

                    if(!empty($fields_rowssss)){
                        $fields_data[] = $fields_rowssss;
                    }
                }
            }

            if(!empty($fields_data)){

                $field_btn_action = "";

                if($group_id != ""){
                    $group_admin = $this->getDatabase()->getGroupAdmin($group_id);

                    if($group_admin != ""){

                        $user_meta_keys = array("field_btn_action");
                        $user_meta = $this->getDatabase()->getUserMetaMultiple($group_admin, $user_meta_keys);
                        if(isset($user_meta['field_btn_action']) && $user_meta['field_btn_action'] != ""){
                            $field_btn_action = $user_meta['field_btn_action'];
                        }
                    }
                }
                $fields_rows = array(
                    'fields' => $fields_data,
                    'field_btn_action' => $field_btn_action
                );
            }else{
                $fields_rows = array();
            }

            CoreResponse::success($fields_rows, 'Extra fields retrieved successfully');
            //echo "<pre>"; print_r($fields_rows); die;
        }catch(Exception $e){
            CoreResponse::error('Error fetching extra fields', 400);
        }
    }

    public function getListingImage($data) {

        $listing_id = $data['listing_id'];

        if ( ! function_exists( 'get_current_user_id' ) ) {
            // Try to include WordPress core if not already loaded
            $wp_load_path = dirname( __FILE__, 6 ) . '/wp-load.php';
            if ( file_exists( $wp_load_path ) ) {
                require_once( $wp_load_path );
            }else{
                CoreResponse::error('WordPress core not found', 400);
            }
        }

        $data = array();
        if ($listing_id && isset($listing_id)) {
            $thumbnail_id = get_post_thumbnail_id($listing_id);
            if ($thumbnail_id) {
                $listing_image = wp_get_attachment_url($thumbnail_id);
                $data['listing_image'] = $listing_image;

                $data['listing_image'] = str_replace("staging5.dev.gibbs.no", "gibbs.no", $listing_image);
            }
        }

        CoreResponse::success($data, 'Listing image retrieved successfully');
    }

    public function getListingMeta($data) {

        try{
            $listing_id = $data['listing_id'];

            $listing_meta_keys = array(
                "hide_booking_message"
            );

            $listing_meta = $this->getDatabase()->getPostMetaMultiple($listing_id, $listing_meta_keys);

            if($listing_meta){
                CoreResponse::success($listing_meta, 'Listing meta retrieved successfully');
            }else{
                CoreResponse::error('Listing meta not found', 400);
            }
        }catch(Exception $e){
            CoreResponse::error('Error fetching listing meta', 400);
        }

        
    }

    public function getBookingExtraData($bookingData, $priceData){

        $booking_extra_data = null;

        if(isset($bookingData["coupon"]) && $bookingData["coupon"] != ""){

            $coupon = $bookingData["coupon"];
            $listing_id = $bookingData["listing_id"];

            $booking_extra_data = array("coupon_data" => $bookingData["coupon"]);
            

            $gift_data = array(); 
            $gift_price = 0;   
            $gift_i = 0;

            $check_gifts_code = $this->check_gifts_code($coupon,$listing_id);

            if($check_gifts_code['success'] && $check_gifts_code['coupon'] != "empty"){

                $gift_data[$gift_i]["code"] = $check_gifts_code['coupon'];
                $gift_data[$gift_i]["coupon_balance"] = $check_gifts_code['remaining_saldo'];
                $gift_data[$gift_i]["booking_price"] = $priceData['org_total_price'] + $priceData['coupon_discount'];

                $booking_extra_data["gift_data"] = $gift_data;
            }
            
            $booking_extra_data = json_encode($booking_extra_data);
        }

        return $booking_extra_data;
    }

    public function submitSlotBooking($data) {

        

        


        if ( ! function_exists( 'get_current_user_id' ) ) {
            // Try to include WordPress core if not already loaded
            $wp_load_path = dirname( __FILE__, 6 ) . '/wp-load.php';
            if ( file_exists( $wp_load_path ) ) {
                require_once( $wp_load_path );
            }else{
                CoreResponse::error('WordPress core not found', 400);
            }
        }



        if(!is_user_logged_in()){

            $user_dd = $this->registerUser($data);

            if(isset($user_dd["success"]) && $user_dd["user_id"] > 0){
                wp_set_current_user($user_dd["user_id"]);
            }else{
                CoreResponse::error('User not found', 400);
            }
        }

        if(!is_user_logged_in()){
            CoreResponse::error('User not logged in', 400);
        }

        $this->updateUserData(get_current_user_id(),$data);

        if(isset($data['previousBookingId']) && $data['previousBookingId'] != "" && $data['previousBookingId'] != null && $data['previousBookingId'] != 0){
            $previous_booking_id = $data['previousBookingId'];
            $this->getDatabase()->updateBookingData($previous_booking_id, array('status' => 'cancelled'));
        }

        
        $payment_method = $data['paymentMethod'];


        if($payment_method != "dintero" && $payment_method != "dibs_easy" && $payment_method != "cod" && $payment_method != "nets_easy"){
            CoreResponse::error('Payment method not found', 400);
        }
        

        //$payment_data = $this->netsEasyPayment(82809,"nets_easy");

       

        $bookingToken = $data['bookingToken'];
        $bookingData = $this->getDatabase()->getBookingByToken($bookingToken);
        if($bookingData && isset($bookingData['id'])){
            $booking_data = array(
                'cr_user_id' => get_current_user_id(),
                'listing_id' => $bookingData['listing_id'],
                'slot_text' => $bookingData['slot'],
                'slot_id' => $bookingData['slot_id'],
                'start_date' => $bookingData['start_date'],
                'end_date' => $bookingData['end_date'],
                'adults' => $bookingData['adults'],
                'price_type' => $bookingData['price_type'],
                'coupon' => $bookingData['coupon'],
                'services' => ($bookingData['services'])?json_decode($bookingData['services'], true):array(),
            );
            

            $internal_booking_email_list = get_post_meta($bookingData['listing_id'],"_internal_booking_email_list",true);

            if($internal_booking_email_list != "" && $internal_booking_email_list != null){
                $internal_booking_email_list = explode(",",$internal_booking_email_list);
                $internal_booking_email_list = array_map('trim',$internal_booking_email_list);

                if(!empty($internal_booking_email_list)){
                    if(!in_array($data['email'],$internal_booking_email_list)){
                        CoreResponse::error('You are not authorized to book this listing', 400);
                        return;
                    }
                }
            }
            

            $priceData = $this->getSlotPrice($booking_data, true);

            $booking_extra_data = $this->getBookingExtraData($bookingData, $priceData);


            $wihout_coupen_price = $priceData['org_total_price'] + $priceData['coupon_discount'];

            $comment_data = array(
                'first_name' => $data['firstName'],
                'last_name' => $data['lastName'],
                'email' => $data['email'],
                'country_code'  => $data['country_code'],
                'phone' => $data['phone'],
                'message'       => $data['message'],
                'adults' => $priceData['adults'],
                'service'       => $priceData['services'],
                'billing_address_1' => $data['billingAddress1'],
                'billing_postcode'  => $data['billingPostcode'],
                'billing_city'      => $data['billingCity'],
                'billing_country'   => $data['billingCountry'],
                'total_tax'         => $priceData['tax'],
                'wihout_coupen_price'   => $wihout_coupen_price,
            );

            $comment_data = json_encode($comment_data);

           

            $listing_owner = get_post_field( 'post_author', $bookingData['listing_id'] );

            $booking_cal_data = array (
                'owner_id' => $listing_owner,
                'bookings_author' => get_current_user_id(),
                'listing_id' => $bookingData['listing_id'],
                'date_start' => $bookingData['start_date'] . " " . $priceData['start_time'] . ":00",
                'date_end' => $bookingData['end_date'] . " " . $priceData['end_time'] . ":00",
                'comment' =>  $comment_data,
                'type' =>  'reservation',
                'booking_extra_data'    =>  $booking_extra_data,
                'price' => $priceData['org_total_price'],
            );

            $booking_id = $this->getDatabase()->insertBookingData($booking_cal_data);

            if($booking_id){
                $log_args = array(
                    'action' => "booking_created",
                    'related_to_id' => $listing_owner,
                    'user_id' => get_current_user_id(),
                    'post_id' => $booking_id,
                );
                listeo_insert_log($log_args);
            }
            //$booking_id = 415601;

            $data_calculation = array(
                'customer_data' => $data,
                'price_data' => $priceData,
            );

            $this->getDatabase()->insertBookingMeta($booking_id,'booking_confirmation_data', json_encode($data_calculation));


            $this->getDatabase()->insertBookingMeta($booking_id, 'number_of_guests', $booking_data['adults']);

            $booking_from_info = array(
                "booking_from" => "customer",
                "booking_type" => "insert",
                "date" => date("Y-m-d H:i:s"),
            );

            $this->getDatabase()->insertBookingMeta($booking_id, 'booking_from', json_encode($booking_from_info));

            $instant_booking = get_post_meta($bookingData['listing_id'], '_instant_booking', true );
            $status = apply_filters( 'listeo_service_slots_default_status', 'waiting');
            if($instant_booking == 'check_on' || $instant_booking == 'on') {
                $status = 'confirmed'; 
                if(get_option('listeo_instant_booking_require_payment')){
                    $status = "pay_to_confirm";
                }
            }

            //echo "<pre>"; print_r($status); die;
           

            // $booking_update_data = array(
            //     'status' => $status
            // );
            // $this->getDatabase()->updateBookingData($booking_id, $booking_update_data);
            
            $status_data = $this->statusData($booking_id, $status);

           
            $return_data = array();

            $thank_you_page = $this->getDatabase()->getPostMeta($bookingData['listing_id'],"_thank_you_page");

            if(isset($status_data['status']) && $status_data['status'] == "waiting"){

                $return_data =  $status_data;
                $return_data['payment_method'] = "waiting";
                CoreResponse::success($return_data, 'Booking waiting for approval');
                exit;
            }


            if(isset($status_data['order_id']) && $status_data['order_id'] > 0){
                if(isset($status_data['order_received_url']) && $status_data['order_received_url'] != ""){
                    $return_data['redirect_url'] = $status_data['order_received_url'];
                    $return_data['payment_method'] = "free";
                    CoreResponse::success($return_data, 'Booking created successfully');
                    exit;
                }else if(isset($status_data['payment_url']) && $status_data['payment_url'] != ""){

                    if($payment_method == "dintero"){
                        $payment_data = $this->dinteroPayment($booking_id, $status_data['order_id'],"dintero");
                    }else if($payment_method == "nets_easy"){
                         $payment_data = $this->netsEasyPayment($booking_id, $status_data['order_id'],"nets_easy");
                    }else if($payment_method == "dibs_easy"){
                         $payment_data = $this->dibsEasyPayment($booking_id, $status_data['order_id'],"dibs_easy");
                    }else if($payment_method == "cod"){

                        $payment_data = $this->codPayment($booking_id, $status_data['order_id'],"cod");
                        if(isset($payment_data['redirect_url']) && $payment_data['redirect_url'] != ""){
                            $update_data = array(
                                'status' => "paid",
                            );
                            $this->getDatabase()->updateBookingData($booking_id, $update_data);
                        }
                        
                    }else{
                         CoreResponse::error('Payment method not found', 400);
                    }

                    $payment_data['thank_you_page'] = $thank_you_page;

                    //echo "<pre>"; print_r($payment_data); die;

                    if(isset($payment_data['payment_method']) && $payment_data['payment_method'] != ""){
                        //return $payment_data;
                        CoreResponse::success($payment_data, 'Booking created successfully');
                    }else{
                        CoreResponse::error('Failed to fetch payment url', 400, array("booking_id" => $booking_id));
                    }



                    //$payment_data = $this->paymentGetway($status_data['order_id']);

                    // if(isset($payment_data['checkout_url']) && isset($payment_data['id']) && isset($payment_data['payment_method']) && $payment_data['payment_method'] == ){
                    //     $return_data['checkout_url'] = $payment_data['checkout_url'];
                    //     $return_data['id'] = $payment_data['id'];
                    //     CoreResponse::success($return_data, 'Booking created successfully');
                    // }else{
                    //     CoreResponse::error('Failed to fetch payment url', 400);
                    // }
                    exit;
                }else{
                    CoreResponse::error('Failed to fetch payment url', 400);
                }
            }else{
                CoreResponse::error('Failed to create order', 400);
            }
        }else{
            CoreResponse::error('Booking not found', 400);
        }

      
    }

    public function directCheckoutDinteroPayment(){

        if ( ! function_exists( 'get_current_user_id' ) ) {
            // Try to include WordPress core if not already loaded
            $wp_load_path = dirname( __FILE__, 6 ) . '/wp-load.php';
            if ( file_exists( $wp_load_path ) ) {
                require_once( $wp_load_path );
            }else{
                CoreResponse::error('WordPress core not found', 400);
            }
        }

        

        
        // Create Dintero gateway instance
        $dintero_gateway = new WC_Gateway_Dintero();

        
        
        // Check if Dintero is enabled
        if ($dintero_gateway->enabled !== 'yes') {
            CoreResponse::error('Dintero payment gateway is not enabled', 400);
        }

        // Process payment with Dintero
        $payment_result = $dintero_gateway->process_direct_checkout_payment();

        echo "<pre>"; print_r($payment_result); die;
        
        // if (isset($payment_result['result']) && $payment_result['result'] === 'success') {
        //     if (isset($payment_result['redirect']) && isset($payment_result['id'])) {

        //         $order->add_order_note("Dintero payment redirect with id: " . $payment_result['id'], true); 

        //         update_post_meta($order_id, 'dintero_payment_id', $payment_result['id']);
        //         update_post_meta($order_id, 'dintero_payment_url', $payment_result['redirect']);
                
        //         return $return_data = array(
        //             'checkout_url' => $payment_result['redirect'],
        //             'id' => $payment_result['id'],
        //             'payment_method' => "dintero",
        //         );
        //     } else {
        //         CoreResponse::error('Payment processed but no redirect URL received', 400);
        //     }
        // } else if (isset($payment_result['result']) && $payment_result['result'] === 'failure') {
        //     CoreResponse::error(isset($payment_result['message'])?$payment_result['message']:'Payment processing failed', 400);
        // } else {
        //     // Get error message from WooCommerce notices
        //     $error_message = 'Payment processing failed';
        //     if (function_exists('wc_get_notices')) {
        //         $notices = wc_get_notices('error');
        //         if (!empty($notices)) {
        //             $error_message = $notices[0]['notice'];
        //         }
        //     }
        //     CoreResponse::error($error_message, 400);
        //         }
    }

    /**
     * Update DIBS payment status
     */
    public function updateDibsPaymentStatus($data) {
        // $payment_id = sanitize_text_field($data['payment_id'] ?? '');
        // $status = sanitize_text_field($data['status'] ?? '');
        // $payment_data = $data['payment_data'] ?? array();
        
        // if (empty($payment_id) || empty($status)) {
        //     CoreResponse::error('Payment ID and status are required', 400);
        // }

        // try {
        //     // Find order by payment ID
        //     $orders = wc_get_orders(array(
        //         'meta_key' => 'nets_easy_payment_id',
        //         'meta_value' => $payment_id,
        //         'limit' => 1
        //     ));

        //     if (empty($orders)) {
        //         CoreResponse::error('Order not found for payment ID', 404);
        //     }

        //     $order = $orders[0];
            
        //     // Update order status based on payment status
        //     if ($status === 'completed') {
        //         $order->payment_complete($payment_id);
        //         $order->add_order_note('Payment completed via DIBS Checkout. Payment ID: ' . $payment_id);
                
        //         // Update booking status
        //         global $wpdb;
        //         $booking_table = $wpdb->prefix . 'bookings_calendar';
        //         $wpdb->update(
        //             $booking_table,
        //             array('status' => 'paid'),
        //             array('order_id' => $order->get_id()),
        //             array('%s'),
        //             array('%d')
        //         );
        //     } else {
        //         $order->add_order_note('DIBS Payment status updated: ' . $status . '. Payment ID: ' . $payment_id);
        //     }

        //     CoreResponse::success(array(
        //         'order_id' => $order->get_id(),
        //         'status' => $status,
        //         'payment_id' => $payment_id
        //     ), 'Payment status updated successfully');

        // } catch (Exception $e) {
        //     CoreResponse::error('Error updating payment status: ' . $e->getMessage(), 500);
        // }
    }
    
    public function netsEasyPayment($booking_id, $order_id, $payment_method_id){

        $payment_gateways = $this->getWooCommercePaymentMethods();
        $gateway = null;

        foreach ( $payment_gateways as $pg_id => $pg_instance ) {
            if ( $pg_id === $payment_method_id ) {
                $gateway = $pg_instance;
                break;
            }
        }


        $payment_method = array();

        if ( $gateway ) {   
            $payment_method["id"] = $gateway->id;
            $payment_method["title"] = $gateway->title;
        }else{
            CoreResponse::error('Payment method not found', 400, array("booking_id" => $booking_id));
        }
        

        // Get the order
        $order = wc_get_order($order_id);
        if (!$order) {
            CoreResponse::error('Order not found', 400, array("booking_id" => $booking_id));
        }

        

        // Set Dintero as the default payment method for this order
        $this->setDefaultPaymentMethod($order, $payment_method);

        // Create Dintero gateway instance
        $dibs_gateway = new WC_Gateway_Gibbs_DIBS_Payment();
        
        // Check if Dintero is enabled
        if ($dibs_gateway->enabled !== 'yes') {
            CoreResponse::error('Dintero payment gateway is not enabled', 400, array("booking_id" => $booking_id));
        }

        // Process payment with Dintero
        $payment_result = $dibs_gateway->process_payment($order_id, true);

        
        if (isset($payment_result['result']) && $payment_result['result'] === 'success') {

            if (isset($payment_result['paymentId'])) {

                // $order->add_order_note("Dintero payment redirect with id: " . $payment_result['id'], true); 

                // update_post_meta($order_id, 'dintero_payment_id', $payment_result['id']);
                // update_post_meta($order_id, 'dintero_payment_url', $payment_result['redirect']);

                
                return $return_data = array(
                    'paymentId' => $payment_result['paymentId'],
                    'token' => $payment_result['checkoutKey'],
                    'mode' => $payment_result['mode'],
                    'order_id' => $order_id,
                    'payment_method' => "nets_easy"
                );
            } else {
                CoreResponse::error('Payment processed but no redirect URL received', 400, array("booking_id" => $booking_id));
            }
        } else if (isset($payment_result['result']) && $payment_result['result'] === 'failure') {
            CoreResponse::error(isset($payment_result['message'])?$payment_result['message']:'Payment processing failed', 400, array("booking_id" => $booking_id));
        } else {
            // Get error message from WooCommerce notices
            $error_message = 'Payment processing failed';
            if (function_exists('wc_get_notices')) {
                $notices = wc_get_notices('error');
                if (!empty($notices)) {
                    $error_message = $notices[0]['notice'];
                }
            }
            CoreResponse::error($error_message, 400, array("booking_id" => $booking_id));
        }
    }
    public function dinteroPayment($booking_id, $order_id, $payment_method_id){

        $payment_gateways = $this->getWooCommercePaymentMethods();
        $gateway = null;

        foreach ( $payment_gateways as $pg_id => $pg_instance ) {
            if ( $pg_id === $payment_method_id ) {
                $gateway = $pg_instance;
                break;
            }
        }


        $payment_method = array();

        if ( $gateway ) {   
            $payment_method["id"] = $gateway->id;
            $payment_method["title"] = $gateway->title;
        }else{
            CoreResponse::error('Payment method not found', 400, array("booking_id" => $booking_id));
        }
        

        // Get the order
        $order = wc_get_order($order_id);
        if (!$order) {
            CoreResponse::error('Order not found', 400, array("booking_id" => $booking_id));
        }

        // Set Dintero as the default payment method for this order
        $this->setDefaultPaymentMethod($order, $payment_method);

        // Create Dintero gateway instance
        $dintero_gateway = new WC_Gateway_Dintero();
        
        // Check if Dintero is enabled
        if ($dintero_gateway->enabled !== 'yes') {
            CoreResponse::error('Dintero payment gateway is not enabled', 400, array("booking_id" => $booking_id));
        }

        // Process payment with Dintero
        $payment_result = $dintero_gateway->process_payment($order_id, true);

        //echo "<pre>"; print_r($payment_result); die;
        
        if (isset($payment_result['result']) && $payment_result['result'] === 'success') {

            if (isset($payment_result['session']) && isset($payment_result['access_token'])) {

                // $order->add_order_note("Dintero payment redirect with id: " . $payment_result['id'], true); 

                // update_post_meta($order_id, 'dintero_payment_id', $payment_result['id']);
                // update_post_meta($order_id, 'dintero_payment_url', $payment_result['redirect']);
                
                return $return_data = array(
                    'session' => $payment_result['session'],
                    'access_token' => $payment_result['access_token'],
                    'order_id' => $order_id,
                    'payment_method' => "dintero",
                );
            }else if (isset($payment_result['redirect']) && isset($payment_result['id'])) {

                $order->add_order_note("Dintero payment redirect with id: " . $payment_result['id'], true); 

                update_post_meta($order_id, 'dintero_payment_id', $payment_result['id']);
                update_post_meta($order_id, 'dintero_payment_url', $payment_result['redirect']);
                
                return $return_data = array(
                    'checkout_url' => $payment_result['redirect'],
                    'id' => $payment_result['id'],
                    'payment_method' => "dintero",
                );
            } else {
                CoreResponse::error('Payment processed but no redirect URL received', 400, array("booking_id" => $booking_id));
            }
        } else if (isset($payment_result['result']) && $payment_result['result'] === 'failure') {
            CoreResponse::error(isset($payment_result['message'])?$payment_result['message']:'Payment processing failed', 400, array("booking_id" => $booking_id));
        } else {
            // Get error message from WooCommerce notices
            $error_message = 'Payment processing failed';
            if (function_exists('wc_get_notices')) {
                $notices = wc_get_notices('error');
                if (!empty($notices)) {
                    $error_message = $notices[0]['notice'];
                }
            }
            CoreResponse::error($error_message, 400, array("booking_id" => $booking_id));
        }
    }

    public function dibsEasyPayment($booking_id, $order_id, $payment_method_id){
         // Load Dintero gateway classes if not already loaded
         $payment_gateways = $this->getWooCommercePaymentMethods();
         $gateway = null;
 
         foreach ( $payment_gateways as $pg_id => $pg_instance ) {
             if ( $pg_id === $payment_method_id ) {
                 $gateway = $pg_instance;
                 break;
             }
         }
 
 
         $payment_method = array();
 
         if ( $gateway ) {   
             $payment_method["id"] = $gateway->id;
             $payment_method["title"] = $gateway->title;
         }else{
             CoreResponse::error('Payment method not found', 400, array("booking_id" => $booking_id));
         }
        // Get the order
        $order = wc_get_order($order_id);
        if (!$order) {
            CoreResponse::error('Order not found', 400, array("booking_id" => $booking_id));
        }

        // Set Dintero as the default payment method for this order
        $this->setDefaultPaymentMethod($order, $payment_method);

        // Create Dintero gateway instance
        $dintero_gateway = new WC_Gateway_Dintero();
        
        // Check if Dintero is enabled
        if ($dintero_gateway->enabled !== 'yes') {
            CoreResponse::error('Dintero payment gateway is not enabled', 400, array("booking_id" => $booking_id));
        }

        // Process payment with Dintero
        $payment_result = $dintero_gateway->process_payment($order_id);

        //echo "<pre>"; print_r($payment_result); die;
        
        if (isset($payment_result['result']) && $payment_result['result'] === 'success') {
            if (isset($payment_result['redirect']) && isset($payment_result['id'])) {
                return $return_data = array(
                    'checkout_url' => $payment_result['redirect'],
                    'id' => $payment_result['id'],
                    'payment_method' => "dibs_easy",
                );
            } else {
                CoreResponse::error('Payment processed but no redirect URL received', 400, array("booking_id" => $booking_id));
            }
        } else if (isset($payment_result['result']) && $payment_result['result'] === 'failure') {
            CoreResponse::error(isset($payment_result['message'])?$payment_result['message']:'Payment processing failed', 400, array("booking_id" => $booking_id));
        } else {
            // Get error message from WooCommerce notices
            $error_message = 'Payment processing failed';
            if (function_exists('wc_get_notices')) {
                $notices = wc_get_notices('error');
                if (!empty($notices)) {
                    $error_message = $notices[0]['notice'];
                }
            }
            CoreResponse::error($error_message, 400, array("booking_id" => $booking_id));
        }
    }

    public function getWooCommercePaymentMethods(){
        if ( ! class_exists( 'WC_Payment_Gateways' ) ) {
            CoreResponse::error('WooCommerce payment gateways not available', 400);
        }
        $gateways_instance = WC_Payment_Gateways::instance();
        $available_gateways = method_exists($gateways_instance, 'get_available_payment_gateways')
            ? $gateways_instance->get_available_payment_gateways()
            : array();

        if (empty($available_gateways) && method_exists($gateways_instance, 'get_payment_gateways')) {
            $available_gateways = $gateways_instance->get_payment_gateways();
        }

        return $available_gateways;
    }

    public function codPayment($booking_id, $order_id, $payment_method_id){

        $payment_gateways = $this->getWooCommercePaymentMethods();
        $gateway = null;

        foreach ( $payment_gateways as $pg_id => $pg_instance ) {
            if ( $pg_id === $payment_method_id ) {
                $gateway = $pg_instance;
                break;
            }
        }


        $payment_method = array();

        if ( $gateway ) {   
            $payment_method["id"] = $gateway->id;
            $payment_method["title"] = $gateway->title;
        }else{
            CoreResponse::error('Payment method not found', 400, array("booking_id" => $booking_id));
        }

        //echo "<pre>"; print_r($payment_method); die;


        $order = wc_get_order($order_id);
        if (!$order) {
            CoreResponse::error('Order not found', 400, array("booking_id" => $booking_id));
        }

        $this->setDefaultPaymentMethod($order, $payment_method);

        wp_schedule_single_event(
            time() + 5, // Delay by 5 seconds
            'order_completed_event',
            [ $order_id ]
        );

       // $order->update_status( 'completed' );

        $order_received_url =  $order->get_checkout_order_received_url();
        $order_received_url = str_replace("/en", "", $order_received_url);

        return $return_data = array(
            'redirect_url' => $order_received_url,
            'payment_method' => "cod",
        );
    }

    /**
     * Set Dintero as the default payment method for the order
     */
    private function setDefaultPaymentMethod($order, $payment_method) {
        // Set the payment method for the order
        $order->set_payment_method($payment_method['id']);
        $order->set_payment_method_title($payment_method['title']);
        $order->save();
    }

   

    public function statusData($booking_id, $status){

        $order_data = array();

        switch ( $status ) {

            case 'confirmed' :
            case 'pay_to_confirm' :
                $order_data = $this->createBookingOrder($booking_id);

               
                if(isset($order_data['order_id']) && $order_data['order_id'] > 0){

                    $update_data = array(   
                        'order_id' => $order_data['order_id']
                    );

                    if(isset($order_data['order_status']) && $order_data['order_status'] == "paid"){
                        $update_data['status'] = "paid";
                    }else{
                        $update_data['status'] = $status;
                    }
                   
                    $this->getDatabase()->updateBookingData($booking_id, $update_data);

                    $this->sendOrderEmail($booking_id, $order_data);
                }
                break;
            case 'waiting' :
                $this->sendWaitingEmail($booking_id);
                $order_data = array(
                    'status' => $status,
                    'message' => 'Booking waiting for approval',
                );
                $this->getDatabase()->updateBookingData($booking_id, array('status' => $status));
                break;
        }

        return $order_data;
    }

    public function sendOrderEmail($booking_id, $order_data, $send_mail = true){

        $booking_data = $this->getDatabase()->getBookingById($booking_id);

        if(isset($booking_data['id'])){

            try{

                $instant_booking = get_post_meta( $booking_data['listing_id'], '_instant_booking', true);

                $user_info = $this->getDatabase()->getUserById($booking_data['bookings_author']);
                $owner_info = $this->getDatabase()->getUserById($booking_data['owner_id']);

                if(isset($user_info['ID']) && isset($owner_info['ID'])){

                    if($instant_booking) {

                        if($send_mail != false){

                            $mail_to_user_args = array(
                                'email' => $user_info['user_email'],
                                'booking'  => $booking_data,
                                'mail_to_user'  => "buyer",
                            ); 
                            do_action('listeo_mail_to_user_instant_approval',$mail_to_user_args);
                            
                            // mail for owner
                            $mail_to_owner_args = array(
                                'email'     => $owner_info['user_email'],
                                'booking'  => $booking_data,
                                'mail_to_user'  => "owner",
                            );
                            
                            do_action('listeo_mail_to_owner_new_instant_reservation',$mail_to_owner_args);
                        }    

                    }

                    

                    if(isset($order_data['order_status']) && $order_data['order_status'] == "paid"){

                        $mail_args = array(
                            'email'     => $user_info['user_email'],
                            'booking'  => $booking_data,
                            'mail_to_user'  => "buyer",
                            );
                        if($send_mail != false){
                            do_action('listeo_mail_to_user_free_confirmed',$mail_args);
                        }  
                        
                    }

                    if(isset($order_data['order_id']) && isset($order_data['payment_url']) && $order_data['order_id'] > 0){

                        $mail_args = array(
                            'email'         => $user_info['user_email'],
                            'booking'       => $booking_data,
                            'payment_url'   => $order_data['payment_url'],
                            'order_id'   => $order_data['order_id'],
                            'mail_to_user'  => "buyer",
                        );
                        if($send_mail != false){

                            if($order_data['order_status'] != "paid"){
                                do_action('listeo_mail_to_user_pay',$mail_args);
                            }
                        
                        }   
                    }   

                }

            }catch(Exception $e){
               
            }
                    

        }

    }

    public function sendWaitingEmail($booking_id, $send_mail = true){

        $booking_data = $this->getDatabase()->getBookingById($booking_id);

        if(isset($booking_data['id'])){

            $user_info = $this->getDatabase()->getUserById($booking_data['bookings_author']);
            $owner_info = $this->getDatabase()->getUserById($booking_data['owner_id']);

            if(isset($user_info['ID']) && isset($owner_info['ID'])){

                $mail_to_user_args = array(
                    'email' => $user_info['user_email'],
                    'booking'  => $booking_data,
                    'mail_to_user'  => "buyer",
                );
                if($send_mail != false){
                    do_action('listeo_mail_to_user_waiting_approval',$mail_to_user_args);
                    // mail for owner
                    $mail_to_owner_args = array(
                        'email'     => $owner_info['user_email'],
                        'booking'  => $booking_data,
                        'mail_to_user'  => "owner",
                    );
                    
                    do_action('listeo_mail_to_owner_new_reservation',$mail_to_owner_args);
                }

            }
                    

        }

    }

    public function createBookingOrder($booking_id){
        
        $booking_data = $this->getDatabase()->getBookingById($booking_id);

        if(!isset($booking_data['id'])){
            CoreResponse::error('Booking not found', 400);
        }

        $booking_confirmation_data = $this->getDatabase()->getBookingMeta($booking_id,'booking_confirmation_data');

        if($booking_confirmation_data && isset($booking_confirmation_data['meta_value']) && $booking_confirmation_data['meta_value'] != ""){

            $booking_confirmation_data = json_decode($booking_confirmation_data['meta_value'], true);

            if(isset($booking_confirmation_data['customer_data']) && isset($booking_confirmation_data['price_data'])){

                $customer_data = $booking_confirmation_data['customer_data'];
                $price_data = $booking_confirmation_data['price_data'];

                return $order_data = $this->createOrder($booking_id, $customer_data, $price_data, $booking_data);
                
            }else{
                CoreResponse::error('Booking confirmation data not found', 400);
            }   

        }else{
            CoreResponse::error('Booking confirmation data not found', 400);
        }
    }

    public function createOrder($booking_id, $customer_data, $price_data, $booking_data){

        $return_data = array();

        $user_id = $booking_data['bookings_author'];
        $product_id = get_post_meta( $price_data['listing_id'], 'product_id', true);

        $first_name = $customer_data['firstName'];
        $last_name = $customer_data['lastName']??' ';
        $email = $customer_data['email'];
        $phone = $customer_data['phone'];
        $billing_address_1 = $customer_data['billingAddress1']??'N/A';
        $billing_city = $customer_data['billingCity']??'N/A';
        $billing_postcode = $customer_data['billingPostcode']??'1111';
        $billing_country = strtoupper($customer_data['billingCountry']??'NO');

        $order = wc_create_order();
        $product = wc_get_product($product_id);

        $post_info = get_post($booking_data['listing_id']);
        if(isset($post_info->post_author) && $post_info->post_author != ""){
            $user_currency_data = get_user_meta( $post_info->post_author, 'currency', true );
            if($user_currency_data != ""){
                $order->set_currency($user_currency_data);
            }
        }

        $currency = $order->get_currency();
        $currency_symbol = get_woocommerce_currency_symbol($currency);


        $subtotal = round($price_data['totalPrice'] + $price_data['total_service_price'] + $price_data['tax']); 


        $item = new WC_Order_Item_Product();
        $item->set_product($product);
        $item->set_quantity(1);
        $item->set_subtotal($subtotal);
        $item->set_total($price_data['org_total_price']);    
        $order->add_item($item);


        // $item->add_meta_data(
        //     "Slot Price (". $price_data['adults'] ."x)", 
        //     $price_data['totalPrice'] . ' ' . $currency_symbol
        // );




        // if(isset($price_data["services"]) && !empty($price_data["services"])){
        //     foreach($price_data["services"] as $service){
        //         // $item = new WC_Order_Item_Fee();
        //         // $item->set_name($service['name']." (". $service['countable'] ."x)");
        //         // $item->set_amount(round($service['price']));
        //         // $item->set_total("---");
        //         // $order->add_item($item);

        //         $item->add_meta_data(
        //             $service['name']." (". $service['countable'] ."x)", 
        //             round($service['price']) . ' ' . $currency_symbol
        //         );
        //     }
        // }

        // $taxAmount = 0;
        // if(isset($price_data['tax']) && $price_data['tax'] > 0){
            
        //     // $tax_item = new WC_Order_Item_Fee();
        //     // $tax_item->set_name("Total mva");
        //     // $tax_item->set_amount(round($price_data['tax']));
        //     // $tax_item->set_total("----");
        //     // $order->add_item($tax_item);

        //     $item->add_meta_data(
        //         "Total mva", 
        //         round($price_data['tax']) . ' ' . $currency_symbol
        //     ); 
        // }


        // $coupon = $price_data['coupon'];
        // if($coupon && $price_data['coupon_discount'] > 0){
            
        //     $gift_code = $coupon;
        //     $gift_discount = $price_data['coupon_discount']; 
    
        //     // $discount_item = new WC_Order_Item_Fee();
        //     // $discount_item->set_name('Coupon Code: ' . $gift_code);
        //     // $discount_item->set_amount(-round($gift_discount));
        //     // $discount_item->set_total(-round($gift_discount));
        //     // $order->add_item($discount_item);

        //     $item->add_meta_data(
        //         "Coupon Code: " . $gift_code, 
        //         -round($gift_discount) . ' ' . $currency_symbol
        //     ); 
        // }


        

        $address = array(
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'address_1' => $billing_address_1,
            'city' => $billing_city,
            'postcode'  => $billing_postcode,
            'country'   => $billing_country,
        );

        $order->set_address( $address, 'billing' );
        $order->set_address( $address, 'shipping' );
        $order->set_billing_phone( $phone );
        $order->set_customer_id($user_id);
        $order->set_billing_email( $email );

        
        //$order->set_subtotal($subtotal);  

       // $order->set_discount_total($price_data['coupon_discount'] ?? 0);  // Apply coupon discount

       // $order->set_total_tax(round($price_data['tax']??0));  // Set the tax amount
       // $order->set_total(round($price_data['org_total_price']??0));  // Final total (after tax and discounts)


        

        $payment_url = $order->get_checkout_payment_url();
        
        $order->calculate_totals();

        // echo "<pre>";
        // print_r($order);
        // echo "</pre>";
        // die();

        

        
        // if(isset($price_data['tax']) && $price_data['tax'] > 0){
        //    $order->set_total_tax($price_data['tax']);
        // }
        // if($coupon && $price_data['coupon_discount'] > 0){
        //     $order->set_discount_total($price_data['coupon_discount']);
        // }

        // echo "<pre>";
        // print_r($subtotal);
        // echo "</pre>";
        // die();

        // echo "<pre>";
        // print_r($order);
        // echo "</pre>";
        // die();

        

        // $taxAmount = 0;
        // if(isset($price_data['tax']) && $price_data['tax'] > 0){
        //     $taxAmount = $price_data['tax'];
        // }


        // $args['totals']['subtotal'] = $price_data['totalPrice'] + $price_data['coupon_discount'] + $price_data['total_service_price'];

        // if($price_data['org_total_price'] < 0){
        //     $args['totals']['total'] = $price_data['org_total_price'];
        // }else{
        //     $args['totals']['total'] = $price_data['org_total_price'] - $taxAmount;
        // }

        // $order->add_product( wc_get_product( $product_id ), 1, $args );


       

       

        // $calculate_tax_for = array(
        //     'country' => $billing_country, 
        //     'state' => '', 
        //     'postcode' => $billing_postcode, 
        //     'city' => $billing_city
        // );

        // $taxAmount = 0;
        // if(isset($price_data['tax']) && $price_data['tax'] > 0){
            
        //     $item_fee = new WC_Order_Item_Fee();

        //     $item_fee->set_name( "Total mva" ); // Generic fee name
        //     $item_fee->set_amount( $price_data['tax'] ); // Fee amount
        //     $item_fee->set_tax_class( '' ); // default for ''
        //     $item_fee->set_tax_status( 'taxable' ); // or 'none'
        //     $item_fee->set_total( $price_data['tax'] ); // Fee amount

        //     // Calculating Fee taxes
        //     $item_fee->calculate_taxes( $calculate_tax_for );

        //     // Add Fee item to the order
        //     $order->add_item( $item_fee );

        //     $taxAmount = $price_data['tax'];
        // }

       

       

        $order->save();
        
        $order->update_meta_data('booking_id', $booking_id);
        $order->update_meta_data('owner_id', $booking_data['owner_id']);
        $order->update_meta_data('listing_id', $booking_data['listing_id']);

        
        if(isset($price_data['services'])){
            $order->update_meta_data('listeo_services', $price_data['services']);
        }

        $order->save_meta_data();
        
        
        $update_values['order_id'] = $order->get_order_number();

        $order_id = $order->id;

        if(isset($order_id) && $order_id > 0){
            $return_data['order_id'] = $order_id;
        }else{
            CoreResponse::error('Failed to create order', 400, array("booking_id" => $booking_id));
        }

        $return_data['order_id'] = $order_id;
        $return_data['payment_url'] = $payment_url;
        $return_data['order_status'] = "confirmed";


        if ($price_data['org_total_price'] == 0 && $order_id && order)
        {
            $return_data['order_status'] = "paid";
            //$order->update_status( 'completed' );
            wp_schedule_single_event(
                time() + 5, // Delay by 5 seconds
                'order_completed_event',
                [ $order_id ]
            );

            $order_received_url =  $order->get_checkout_order_received_url();
            $order_received_url = str_replace("/en", "", $order_received_url);

            $return_data['order_received_url'] = $order_received_url;
        }


        return $return_data;
    }

    public function email_user_data($data) {
        $email = $data['email'];
        $user_data = $this->getDatabase()->getUserByEmail($email);

        if(isset($user_data['ID'])){

            $user_data = $this->UserData($user_data);
        }else{
            $user_data = array();
        }    

        CoreResponse::success($user_data, 'User data retrieved successfully');
    }
    public function fetch_user_data_by_email_or_phone($data) {
       try {
        $input = $data['input'];
        $user_data = $this->getDatabase()->getUserByEmailOrPhone($input);
        if(isset($user_data['ID'])){

            $user_data = $this->UserData($user_data);
        }else{
            $user_data = array();
        }  
        CoreResponse::success($user_data, 'User data retrieved successfully');
       } catch (Exception $e) {
        CoreResponse::error('Failed to get user data: ' . $e->getMessage(), 500);
       }
    }

    public function UserData($user_data) {

        if(isset($user_data['ID'])){
            $user_id = $user_data['ID'];

            $display_name = $user_data['display_name'];

            $meta_keys = array(
                "phone",
                "billing_address_1",
                "billing_city",
                "billing_postcode",
                "billing_country",
                "company_number",
                "profile_type",
                "country_code",
                "first_name",
                "last_name",
            );
            $user_meta = $this->getDatabase()->getUserMetaMultiple($user_id, $meta_keys);
            
            $user_data = array_merge($user_data, $user_meta);

            if(isset($user_data['profile_type']) && $user_data['profile_type'] != ""){
                $user_data['profile_type'] = strtolower($user_data['profile_type']);
            }
            

            if($user_meta['first_name'] == ""){
                $user_data['first_name'] = $display_name;
            }
        }    

        return $user_data;
    }


    public function UserListingInfo($data) {
        $bookingToken = $data['bookingToken'];
        $cr_user_id = $data['cr_user_id'];
        $bookingData = $this->getDatabase()->getBookingByToken($bookingToken);

        if($bookingData && isset($bookingData['id'])){
            $listing_id = $bookingData['listing_id'];

            $pdf_keys = array(
                "_pdf_document0",
                "_pdf_document1",
                "_pdf_document2",
                "_pdf_document3",
                "_pdf_document4",
                "_pdf_document5",
                "_pdf_document6",
                "_pdf_document7",
                "_pdf_document8",
                "_pdf_document9",
                "_pdf_document10",
            );

            $pdf_meta = $this->getDatabase()->getPostMetaMultiple($listing_id, $pdf_keys);
            

            $post_meta_keys = array(
                "_manual_invoice_payment",
                "_show_hide_amount",
                "_hide_price_div",
            );
            $post_meta = $this->getDatabase()->getPostMetaMultiple($listing_id, $post_meta_keys);
           

            $listing_data = $this->getDatabase()->getPost($listing_id);


            $listing_image = '';
            
            if($cr_user_id != ""){

                $user_id = $cr_user_id;

                $user_data = $this->getDatabase()->getUserById($user_id);

                $user_data = $this->UserData($user_data);

            }else{
                $user_data = array();
            }    

            $data = array(  
                'listing_title' => (isset($listing_data["post_title"]))?$listing_data["post_title"]:"",
                'listing_image' => $listing_image,
                'user_data' => $user_data,
                'post_meta' => $post_meta,
                'pdf_meta' => $pdf_meta,
            );

            CoreResponse::success($data, 'User data retrieved successfully');  
            
        }else{
            CoreResponse::error('Listing not found', 400);
        }
    }

    public function getSlotBookingConfirmation($data) {
        $bookingToken = $data['bookingToken'];
        $bookingData = $this->getDatabase()->getBookingByToken($bookingToken);
        if($bookingData && isset($bookingData['id'])){

            $data = array(
                'cr_user_id' => $data['cr_user_id'],
                'listing_id' => $bookingData['listing_id'],
                'slot_text' => $bookingData['slot'],
                'slot_id' => $bookingData['slot_id'],
                'start_date' => $bookingData['start_date'],
                'end_date' => $bookingData['end_date'],
                'adults' => $bookingData['adults'],
                'price_type' => $bookingData['price_type'],
                'coupon' => $bookingData['coupon'],
                'services' => ($bookingData['services'])?json_decode($bookingData['services'], true):array(),
            );

            $priceData = $this->getSlotPrice($data);
           
            CoreResponse::success($priceData, 'Booking data retrieved successfully');
        }else{
            CoreResponse::error('Booking not found', 400);
        }
    }
    

    public function bookConfirmation($data) {

        if(isset($data['booking_token']) && $data['booking_token'] != ""){
            $booking_token = $data['booking_token'];
            $booking_data = $this->getDatabase()->deleteBookingByToken($booking_token);
        }

        if(isset($data['isDirectCheckout']) && $data['isDirectCheckout'] == "true"){

           
           $dintero_payment_url = $this->directCheckoutDinteroPayment();

           echo "<pre>";
           print_r($dintero_payment_url);
           echo "</pre>";
           die;
        }


        $booking_data = $this->getDatabase()->bookConfirmation($data);




        if(isset($booking_data['booking_id']) && $booking_data['booking_token']){
            CoreResponse::success($booking_data, 'Booking data retrieved successfully');
        }else{
            CoreResponse::error('Failed to book slot', 400);
        }
        
    }

    public function getSeasonDiscountData($season_discount_data, $get_total_discount = false){
        $season_discount_data_array = array();
        $total_discount = 0;
        $current_date = date('Y-m-d'); 
        foreach($season_discount_data as $season_discount){
            if(isset($season_discount['season_name']) && $season_discount['season_name'] != "" && isset($season_discount['season_price_percent']) && $season_discount['season_price_percent'] != "" && isset($season_discount['season_price_active']) && $season_discount['season_price_active'] == "on"){
                if(isset($season_discount['season_price_from']) && isset($season_discount['season_price_to']) && 
                $current_date >= $season_discount['season_price_from'] && 
                $current_date <= $season_discount['season_price_to']) {
                 
                    $season_discount_data_array[] = $season_discount;
                    $total_discount += $season_discount['season_price_percent'];
                }

            }
        }

        if($get_total_discount){    
            return $total_discount;
        }else{
            return $season_discount_data_array;
        }
    }

    /**
     * Get available dates
     */
    public function getAvailableDates($listing_id) {
        try {
            $listing_id = $listing_id;
            $booking_data = $this->getDatabase()->getAllBookings($listing_id);

            $bookingIds = array_column($booking_data, 'id');

            $metaMap = [];

            if (!empty($bookingIds)) {
                $metaResults = $this->getDatabase()->getBookingMetaByGuest($bookingIds);
                foreach ($metaResults as $row) {
                    $metaMap[$row['booking_id']] = (int)$row['meta_value'];
                }
            } 

            foreach ($booking_data as &$booking) {
                $bookingId = $booking['id'];
                $booking['count_slot'] = $metaMap[$bookingId] ?? 1;  // Default to 1 if no meta_value found
            }

            // echo "<pre>";
            // print_r($booking_data);
            // echo "</pre>";
            // die();

            

            // $_booking_slots = $this->getDatabase()->get_post_meta($listing_id,"_booking_slots");
            // $enable_slot_duration = $this->getDatabase()->get_post_meta($listing_id,"enable_slot_duration");
            // $slot_price_label = $this->getDatabase()->get_post_meta($listing_id,"slot_price_label");
            // $all_slot_price_label = $this->getDatabase()->get_post_meta($listing_id,"all_slot_price_label");
            // $enable_slot_price = $this->getDatabase()->get_post_meta($listing_id,"enable_slot_price");
            // $all_post_meta = $this->getDatabase()->get_All_Postmeta($listing_id);

            $meta_keys = array(
                "_booking_slots",
                "enable_slot_duration",
                "slot_price_label",
                "all_slot_price_label",
                "enable_slot_price",
                "_booking_system_service",
                "_menu",
                "additional_service_label_name",
                "_count_per_guest",
                "_min_amount_guests",
                "_max_amount_guests",
                "first_booking_minimum_guests",
                "season_status",
                "season_discount_data",
                "_tax",
                "_guest_slot",
                "_max_book_days",
                "_min_book_days"
            );

            $getPostMetaMultiple = $this->getDatabase()->getPostMetaMultiple($listing_id, $meta_keys);


            $services = $this->getListingServices($listing_id);

            $booking_slots = array();

            if(!empty($getPostMetaMultiple["_booking_slots"])){
                $booking_slots = unserialize($getPostMetaMultiple["_booking_slots"]);
            }

            if(isset($getPostMetaMultiple["season_discount_data"]) && $getPostMetaMultiple["season_discount_data"] != ""){
                $season_discount_data_unserialize = unserialize($getPostMetaMultiple["season_discount_data"]);
                $season_discount_data = $this->getSeasonDiscountData($season_discount_data_unserialize);
            }else{
                $season_discount_data = array();
            }

            

            $postData = array(
                'booking_data' => $booking_data,
                'booking_slots' => $booking_slots,
                'enable_slot_duration' => $getPostMetaMultiple["enable_slot_duration"],
                'slot_price_label' => $getPostMetaMultiple["slot_price_label"],
                'all_slot_price_label' => $getPostMetaMultiple["all_slot_price_label"],
                'enable_slot_price' => $getPostMetaMultiple["enable_slot_price"],
                'booking_system_service' => $getPostMetaMultiple["_booking_system_service"],
                'services' => $services,
                'additional_service_label_name' => $getPostMetaMultiple["additional_service_label_name"],
                'count_per_guest' => $getPostMetaMultiple["_count_per_guest"],
                'min_amount_guests' => $getPostMetaMultiple["_min_amount_guests"],
                'max_amount_guests' => $getPostMetaMultiple["_max_amount_guests"],
                'first_booking_minimum_guests' => $getPostMetaMultiple["first_booking_minimum_guests"],
                'season_status' => $getPostMetaMultiple["season_status"],
                'season_discount_data' => $season_discount_data,
                'taxPercentage' => $getPostMetaMultiple["_tax"],
                'max_book_days' => $getPostMetaMultiple["_max_book_days"],
                'min_book_days' => $getPostMetaMultiple["_min_book_days"],
                'guest_slot' => (isset($getPostMetaMultiple["_guest_slot"])?strtolower($getPostMetaMultiple["_guest_slot"]):'')
            );

            CoreResponse::success($postData, 'Post data retrieved successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to get post data: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get available times for a specific date
     */
    public function getAvailableTimes($data) {
        if (!isset($data['date'])) {
            CoreResponse::validationError(array('date' => 'Date is required'));
        }
        
        try {
            $date = $data['date'];
            $times = $this->getDatabase()->getAvailableTimes($date);
            CoreResponse::success(array('availableTimes' => $times), 'Available times retrieved successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to get available times: ' . $e->getMessage(), 500);
        }
    }
    

    public function get_total_price($listing_id, $booking_slot, $adults, $slotPriceType){

      
        $from_day = $booking_slot["from_day"];
        $from_time = $booking_slot["from_time"];
        $to_day = $booking_slot["to_day"];
        $to_time = $booking_slot["to_time"];
        $slot_price = $booking_slot["slot_price"];
        $slots = $booking_slot["slots"];
        $slot_id = $booking_slot["slot_id"];
        $closed = (isset($booking_slot["closed"]))?$booking_slot["closed"]:"0";
        $all_slot_price = (isset($booking_slot["all_slot_price"]))?$booking_slot["all_slot_price"]:0;

        if($slotPriceType == "all_slot_price"){
            $slot_price = $all_slot_price;
        }
        $multiply = 1;


        if ($slotPriceType !== "all_slot_price") {
            
            $count_per_guest = $this->getDatabase()->get_post_meta($listing_id,"_count_per_guest");
            if ($count_per_guest) {
                $multiply = $adults;
            }
        } 

        $totalPrice = $slot_price * $multiply;

        return $totalPrice;
        
    }

    public function get_slot_explode($booking_slot){
        $slott = explode("|", $booking_slot); 

        return array(
            'from_day' => $slott[0],
            'from_time' => $slott[1],
            'to_day' => $slott[2],
            'to_time' => $slott[3],
            'slot_price' => $slott[4],
            'slots' => $slott[5],
            'slot_id' => $slott[6],
            'closed' => (isset($slott[7]))?$slott[7]:"0",
            'all_slot_price' => (isset($slott[8]))?$slott[8]:0,
            'slot_label' => (isset($slott[9]))?$slott[9]:"",
        );  
    }

    public function getRemainingSlots($listing_id, $booking_slot, $date_start, $date_end, $slotPriceType){

        $from_day = $booking_slot["from_day"];
        $from_time = $booking_slot["from_time"];
        $to_day = $booking_slot["to_day"];
        $to_time = $booking_slot["to_time"];
        $slot_price = $booking_slot["slot_price"];
        $slots = intval($booking_slot["slots"]);
        $slot_id = $booking_slot["slot_id"];
        $closed = (isset($booking_slot["closed"]))?$booking_slot["closed"]:"0";
        $all_slot_price = (isset($booking_slot["all_slot_price"]))?$booking_slot["all_slot_price"]:0;

        


        $date_start = $date_start." ".$from_time;
        $date_end = $date_end." ".$to_time;

        $booking_data = $this->getDatabase()->get_slots_bookings( $listing_id, $date_start, $date_end );

       

        if($booking_data && !empty($booking_data)){

            

            $bookingIds = array_column($booking_data, 'id');

            $metaMap = [];

            if (!empty($bookingIds)) {
                $metaResults = $this->getDatabase()->getBookingMetaByGuest($bookingIds);
                foreach ($metaResults as $row) {
                    $metaMap[$row['booking_id']] = (int)$row['meta_value'];
                }
            } 

            $booking_count = 0;
            

            foreach ($booking_data as &$booking) {
                $bookingId = $booking['id'];

                if(isset($metaMap[$bookingId])){
                    $booking_count += intval($metaMap[$bookingId]);
                }else{
                    $booking_count += 1;
                }
            }

            $remaining_slots = intval($slots) - $booking_count;
            if($remaining_slots < 0){
                $remaining_slots = 0;
            }

            if($slotPriceType == "all_slot_price" && $remaining_slots != $slots){
                $remaining_slots = 0;
            }

            return $remaining_slots;
        }

        return $slots;
    }

    public function getSubscriptionDiscount($listing_id, $cr_user_id, $total_price){

        $return_data = array();

        $postData = $this->getDatabase()->getPost($listing_id);
        if($postData && isset($postData['post_author']) && $postData['post_author'] != ""){
            $post_author = $postData['post_author'];
        }

        $user_subscriptions = $this->getDatabase()->getUserSubscriptions($cr_user_id, $post_author);

        if(empty($user_subscriptions)){
            return null;
        }

        $subscription_product_ids = array_column($user_subscriptions, 'product_type_id');

        $subscription_discounts = $this->getDatabase()->getSubscriptionDiscount($post_author, $listing_id, $cr_user_id, $subscription_product_ids);

        
        if($subscription_discounts && !empty($subscription_discounts)){

            $discount_totals = array();

            foreach($subscription_discounts as $subscription_discount){
                if($subscription_discount['discount_type'] == "percentage"){
                    $discount_amount = $total_price * ($subscription_discount['discount_value'] / 100);
                    if($discount_amount < 1){
                        $discount_amount = $total_price;
                    }
                    $discount_totals[] = round($discount_amount);
                }else{
                    $discount_totals[] = round($subscription_discount['discount_value']);
                }
            }

            if(!empty($discount_totals)){
                $max_discount = max($discount_totals);
                

                if($max_discount > $total_price){
                    $max_discount = $total_price;
                }

                $total_price = $total_price - $max_discount;

                $return_data['max_discount'] = $max_discount;
                $return_data['total_price'] = $total_price;

                return $return_data;
            } 

        }
        return return_data;

    }
    
    public function getSlotPrice($data, $return_access = false) {
        // Use WordPress functions to get slot price

        // Validate required parameters
        if (!isset($data['listing_id']) || !isset($data['slot_text'])) {
            CoreResponse::error('listing_id ID or slot text not found', 400);
        }

        $coupon = "";

        if(isset($data['coupon']) && $data['coupon'] != ""){
            $coupon = $data['coupon'];
        }

       
       

        $listing_id = intval($data['listing_id']);

        


        $adults = isset($data['adults']) ? intval($data['adults']) : 1;
        $slotPriceType = isset($data['price_type']) ? $data['price_type'] : '';

        $taxPercentage = $this->getDatabase()->get_post_meta($listing_id,"_tax");


        $meta_keys = array(
            "_booking_slots",
            "enable_slot_duration",
            "slot_price_label",
            "all_slot_price_label",
            "enable_slot_price",
            "_booking_system_service",
            "_menu",
            "additional_service_label_name",
            "season_status",
            "season_discount_data",
            "_count_per_guest",
            "_guest_slot"
        );

        $getPostMetaMultiple = $this->getDatabase()->getPostMetaMultiple($listing_id, $meta_keys);

        $booking_slots = $getPostMetaMultiple["_booking_slots"];

        $booking_slots = unserialize($booking_slots);

        $slot_id = $data['slot_id'];

        $booking_slot = "";

        if(isset($booking_slots[$slot_id])){
            $listing_slot = $booking_slots[$slot_id];

            if($listing_slot == $data['slot_text']){

                $booking_slot = $listing_slot;

            }
        }

        if(!$booking_slot){
            CoreResponse::error('Slot not found', 400);
        }

        $slot_data = $this->get_slot_explode($booking_slot);

        $slot_label = (isset($slot_data['slot_label']) && $slot_data['slot_label'] != "") ? $slot_data['slot_label'] : "";

        $remaining_slots = $this->getRemainingSlots($listing_id, $slot_data, $data['start_date'], $data['end_date'], $slotPriceType);

     
        if($remaining_slots == 0 || $remaining_slots < 0){
            CoreResponse::error('Slot is not available. Please choose another time.', 400);
        }
        if($remaining_slots < $adults && strtolower($getPostMetaMultiple["_guest_slot"]) == "yes"){
            CoreResponse::error('slot_max_guests_error', 400, array('remaining_slots' => $remaining_slots, 'adults' => $adults));
        }

        if($slotPriceType == "all_slot_price"){
            $adults = $remaining_slots;
        }

        $totalPrice = $this->get_total_price($listing_id, $slot_data, $adults, $slotPriceType);

        if($taxPercentage && $taxPercentage > 0){
            $tax = ($totalPrice * $taxPercentage) / 100;
        }else{
            $tax = 0;
        }


        $services = array();
        

        if(isset($data['services']) && !empty($data['services'])){  

            //$listing_services = $this->getListingServices($listing_id);

            foreach($data['services'] as $serviceData){

                $service_single = $serviceData['service'];

                $service_name = $service_single['name'];
                $service_price = floatval($service_single['price']);

                $service_return = $this->calculate_service_price($service_single, $adults, 1, $serviceData['quantity']);

                $services[] = array(
                    'name' => $service_name,
                    'guests' => $adults,
                    'days' => 1,
                    'countable' => $serviceData['quantity'],
                    'price' => $service_return['service_price'] + $service_return['service_tax'],
                    'service_price' => $service_return['service_price'],
                    'tax' => $service_return['service_tax'],
                    'quantity' => $serviceData['quantity'],
                    'service' => $service_single
                );

            }
        }

        // echo "<pre>";
        // print_r($services);
        // echo "</pre>";
        // exit;

        $service_price_data = $this->getTotalServicePrice($services);

        $total_service_price = $service_price_data['total_service_price'];

        $total_service_tax = $service_price_data['total_service_tax'];
        $total_service_price = round($total_service_price);
        $total_service_tax = round($total_service_tax);

        $total_slot_price_with_tax = $totalPrice + $tax;

        // apply season discount


        $season_discount_data_total = 0;
        $season_discount_data = array();


        if(isset($getPostMetaMultiple["season_discount_data"]) && $getPostMetaMultiple["season_discount_data"] != ""){
            $season_discount_data_unserialize = unserialize($getPostMetaMultiple["season_discount_data"]);
            $season_discount_data_total = $this->getSeasonDiscountData($season_discount_data_unserialize, true);
            $season_discount_data = $this->getSeasonDiscountData($season_discount_data_unserialize);
        }
        

        

        $season_discount = 0;
        if($season_discount_data_total > 0){
            if($season_discount_data_total > 100){
                $season_discount = $total_slot_price_with_tax;
                $total_slot_price_with_tax = 0;
            }else{
                $season_discount = $total_slot_price_with_tax * ($season_discount_data_total / 100);
                $total_slot_price_with_tax = $total_slot_price_with_tax - $season_discount;
            }
        }

        // end season discount


        $org_total_price = $total_slot_price_with_tax + $total_service_price;

        $org_total_price = round($org_total_price);
        

        $remaining_saldo = 0;
        $coupon_discount = 0;
        $is_gift = false;

        if($coupon != ""){

            $coupon_price_data = $this->apply_coupon_to_price($org_total_price, $coupon);


            if($coupon_price_data['success'] && $coupon_price_data['type'] == "coupon"){
                $coupon_discount = $coupon_price_data['discount_price'];
                $org_total_price = $coupon_price_data['price'];
            }else if($coupon_price_data['success'] && $coupon_price_data['type'] == "gift"){
                $coupon_discount = $coupon_price_data['discount_price'];
                $org_total_price = $coupon_price_data['price'];
                $is_gift = true;

                if($coupon_price_data['remaining_saldo'] != ""){
                    $remaining_saldo = $coupon_price_data['remaining_saldo'];
                }
            }

        }

        $org_total_price = round($org_total_price);

        $subscription_discount = 0;


        if(isset($data['cr_user_id']) && $data['cr_user_id'] != "" && $org_total_price > 0){
            $subscription_discount_Data = $this->getSubscriptionDiscount($listing_id, $data['cr_user_id'], $org_total_price);

            if(isset($subscription_discount_Data['max_discount']) && isset($subscription_discount_Data['total_price']) && $subscription_discount_Data['max_discount'] > 0){
                $org_total_price = $subscription_discount_Data['total_price'];
                $subscription_discount = $subscription_discount_Data['max_discount'];
            }
        }

       
        

        

        // echo "<pre>";
        // print_r($org_total_price);
        // print_r($coupon_discount);
        // echo "</pre>";
        // die;

        $response_data = array(
            'tax' => round($tax),
            'totalPrice' => round($totalPrice),
            'org_total_price' => $org_total_price,
            'total_service_price' => round($total_service_price),
            'total_service_tax' => round($total_service_tax),
            'remaining_slots' => $remaining_slots,
            'total_slots' => $slot_data['slots'],
            'adults' => $adults,
            'price_type' => $slotPriceType,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],    
            'start_time' => $slot_data['from_time'],
            'end_time' => $slot_data['to_time'],
            'booking_slot' => $booking_slot,
            'listing_id' => $listing_id,
            'services' => $services,
            'remaining_saldo' => $remaining_saldo,
            'coupon' => $coupon,
            'coupon_discount' => round($coupon_discount),
            'is_gift' => $is_gift,
            'season_discount' => round($season_discount),
            'season_discount_data' => $season_discount_data,
            'slot_label' => $slot_label,
            'subscription_discount' => round($subscription_discount)
        );

        if($return_access){
            return $response_data;
        }

        CoreResponse::success($response_data, 'Slot price calculated successfully');
    }

    public function apply_coupon_to_price($price, $coupon){

        $data_return = array();
        $data_return['success'] = false;
        $data_return['type'] = "";
        $data_return['remaining_saldo'] = "";
        $data_return['price'] = "";

        $gift_booking = $this->getDatabase()->getGiftDataByGiftCode($coupon);


        if($gift_booking && isset($gift_booking["post_id"])){
            $gift_booking_id = $gift_booking["post_id"];
            $data_return['success'] = true;
            $data_return['type'] = "gift";
            $remaining_saldo = $this->getDatabase()->get_post_meta($gift_booking_id, 'remaining_saldo');
            

            if($remaining_saldo > $price){
                $data_return['discount_price'] = $price;
                $data_return['remaining_saldo'] = $remaining_saldo - $price;
                $data_return['price'] = 0;
            }else{
                $data_return['discount_price'] = $remaining_saldo;
                $data_return['remaining_saldo'] = 0;
                $data_return['price'] = $price - $remaining_saldo;
            }

            return $data_return;
        }

        $discount_amount = $this->get_discount_amount($coupon, $price);

        if($discount_amount > 0){
            $data_return['success'] = true;
            $data_return['type'] = "coupon";
            $data_return['discount_price'] = $discount_amount;
            
            if($discount_amount > $price){
                $data_return['discount_price'] = $price;
                $data_return['price'] = 0;
            }else{
                $data_return['discount_price'] = $discount_amount;
                $data_return['price'] = $price - $discount_amount;
            }

            return $data_return;
        }

    }

    public function get_discount_amount($coupon, $price){
        // Get coupon data from database using core PHP
        $db = $this->getDatabase();
        $coupon_row = $db->check_if_coupon_exists($coupon);
        
        if (!$coupon_row) {
            return 0; // Return original price if coupon doesn't exist
        }
        
        $coupon_id = $coupon_row['ID'];
        
        // Get coupon meta data
        $meta_keys = array('discount_type', 'coupon_amount');
        $coupon_meta = $db->getPostMetaMultiple($coupon_id, $meta_keys);
        
        $discount_type = isset($coupon_meta['discount_type']) ? $coupon_meta['discount_type'] : 'fixed_product';
        $amount = isset($coupon_meta['coupon_amount']) ? floatval($coupon_meta['coupon_amount']) : 0;
        
        if ($amount <= 0) {
            return 0; // Return original price if amount is invalid
        }

        
        // Apply discount based on type
        if ($discount_type == 'fixed_product') {
            return $amount;
        } else {
            // Percentage discount
            $discount_amount = $price * ($amount / 100);
            return $discount_amount;
        }
    }

    public function getTotalServicePrice($services){

        $total_service_price = 0;
        $total_service_tax = 0;

        foreach($services as $service){
            $total_service_price += $service['price'];
            $total_service_tax += $service['tax'];
        }

        return array('total_service_price' => $total_service_price, 'total_service_tax' => $total_service_tax);
    }

    public function calculate_service_price($service, $guests, $days, $countable ){

        $return_data = array();
        $return_data['service_price'] = 0;
        $return_data['service_tax'] = 0;

        if(isset($service['bookable_options'])) {
            switch ($service['bookable_options']) {
                case 'onetime':
                    $price = $service['price'];
                    break;
                case 'byguest':
                    $price = $service['price'] * (int) $guests;
                    
                    break;
                case 'bydays':
                    $price = $service['price'] * (int) $days;
                    break;
                case 'byguestanddays':
                    $price = $service['price'] * (int) $days * (int) $guests;
                    break;
                default:
                    $price = $service['price'];
                    break;
            }
            $service_price = $price * (int)$countable;
        } else {
            $service_price = $service['price'] * (int)$countable;
        }

        if(isset($service['tax']) && intval($service['tax']) > 0) {
            $service_tax = ($service_price * (intval($service['tax']) / 100));
            $return_data['service_tax'] = $service_tax;

        }
        $return_data['service_price'] = $service_price;
        return $return_data;
    }

    public function getListingServices($listing_id){

        $listing_services = array();

        $bookable_services = $this->getDatabase()->get_post_meta($listing_id,"_menu");

        if(isset($bookable_services)){
            $menus = unserialize($bookable_services);
            if($menus) {
                foreach ($menus as $menu) { 
                
                    if(isset($menu['menu_elements']) && !empty($menu['menu_elements'])) :
                        foreach ($menu['menu_elements'] as $item) {
                            if(isset($item['bookable'])){
        
                                $listing_services[] = $item;	
                            }
                        }
                    endif;
            
                }
            }
        }

        return $listing_services;
    }
    
    public function check_gifts_code($coupon,$listing_id){

        $data_return = array();
        $data_return['success'] = false;
        $data_return['coupon'] = "";
        $data_return['message'] = "";
         
        $gift_booking = $this->getDatabase()->getGiftDataByGiftCode($coupon);

        if($gift_booking && isset($gift_booking["post_id"])){

            $gift_booking_id = $gift_booking["post_id"];

            $giftcard_id = $this->getDatabase()->get_post_meta($gift_booking_id, 'giftcard_id');
            $remaining_saldo = $this->getDatabase()->get_post_meta($gift_booking_id, 'remaining_saldo');

            $listing_ids = $this->getDatabase()->get_post_meta($giftcard_id, 'listing_ids');

            if(!empty($listing_ids)){
                $listing_ids = unserialize($listing_ids);
                if(!in_array($listing_id,$listing_ids)){
                    $data_return['message'] = "Coupon is not valid for this listing";
                    return  $data_return;
                }
            }

            $expire_date = $this->getDatabase()->get_post_meta($gift_booking_id, 'expire_date'); 

            if($expire_date && $expire_date != ""){


                $cr_date = date("Y-m-d")." 00:00:00";
                $cp_expire_date =  $expire_date." 00:00:00";

                if(  strtotime($cr_date) > strtotime($cp_expire_date)  ){
                    $data_return['message'] = "Coupon is expired";
                    return  $data_return;
                }

            }

            if($remaining_saldo < 1){
                    
                $data_return['message'] = "Coupon limit used";
                return  $data_return;

            }

            $data_return['success'] = true;
            $data_return['coupon'] = $coupon;
            $data_return['remaining_saldo'] = $remaining_saldo;
            return  $data_return;
            
        }else{
            $data_return['success'] = true;
            $data_return['coupon'] = "empty";
            return  $data_return;
        }



    }


    public function applyCoupon($data){


        $listing_id = $data['listing_id'];
        $coupon = $data['couponCode'];

        if(empty($coupon)){
            CoreResponse::error('Coupon not found!', 400);
        }
        $response_data = array();

        $check_gifts_code = $this->check_gifts_code($coupon,$listing_id);


        if($check_gifts_code['success'] && $check_gifts_code['coupon'] != "empty"){
            
            $response_data['success'] = true;
            $response_data['coupon'] = $check_gifts_code['coupon'];
            $response_data['message'] = $check_gifts_code['message'];
            
            CoreResponse::success($response_data, 'Apply coupon successfully');
            
        }else if($check_gifts_code['success'] && $check_gifts_code['coupon'] == "empty"){

        }else{
            CoreResponse::error($check_gifts_code['message'], 400);
        }

        // Get coupon data from core database
        $db = $this->getDatabase();
        $coupon_row = $db->check_if_coupon_exists($coupon);

        if (!$coupon_row) {
            CoreResponse::error('Coupon not found!', 400);
        }

        $coupon_id = $coupon_row['ID'];

        // Get coupon meta
        $meta_keys = array(
            'individual_use',
            'minimum_amount',
            'maximum_amount',
            'usage_limit_per_user',
            'usage_limit',
            'usage_count',
            'date_expires',
            'listing_ids'
        );
        $coupon_meta = $db->getPostMetaMultiple($coupon_id, $meta_keys);

        

        // Get price and coupons from $data if available
        $price = isset($data['price']) ? floatval($data['price']) : 0;

        // Individual use check
        // if (!empty($coupon_meta['individual_use']) && $coupon_meta['individual_use'] == 'yes') {
        //     if (is_array($coupons) && count($coupons) > 1) {
        //         CoreResponse::error("This coupon can't be used with others.", 400);
        //     }
        // }

        // Minimum amount check
        if (!empty($coupon_meta['minimum_amount']) && floatval($coupon_meta['minimum_amount']) > 0 && floatval($coupon_meta['minimum_amount']) > $price) {
            CoreResponse::error('The minimum spend for this coupon is ' . $coupon_meta['minimum_amount'], 400);
        }

        // Maximum amount check
        if (!empty($coupon_meta['maximum_amount']) && floatval($coupon_meta['maximum_amount']) > 0 && floatval($coupon_meta['maximum_amount']) < $price) {
            CoreResponse::error('The maximum spend for this coupon is ' . $coupon_meta['maximum_amount'], 400);
        }

        

        // Usage limit per user check
        // Ensure WordPress session is started
        if ( ! session_id() ) {
            if ( function_exists( 'session_start' ) ) {
                @session_start();
            }
        }
        $session_data  = $_SESSION;

       
        
        // Ensure WordPress is loaded so we can use get_current_user_id()
        if ( ! function_exists( 'get_current_user_id' ) ) {
            // Try to include WordPress core if not already loaded
            $wp_load_path = dirname( __FILE__, 6 ) . '/wp-load.php';
            if ( file_exists( $wp_load_path ) ) {
                require_once( $wp_load_path );
            }
        }
        $user_id = function_exists('get_current_user_id') ? get_current_user_id() : 0;
        $wc_coupon = new WC_Coupon($coupon);

        if($wc_coupon->get_usage_limit_per_user() && $user_id){
            $data_store  = $wc_coupon->get_data_store();
            $usage_count = $data_store->get_usage_by_user_id( $wc_coupon, $user_id );
            
            if ( $usage_count >= $wc_coupon->get_usage_limit_per_user() ) {
               CoreResponse::error('Coupon usage limit has been reached', 400);
            }   
        }
       
        if ( $wc_coupon->get_date_expires() &&  time() > $wc_coupon->get_date_expires()->getTimestamp()  ) {
            CoreResponse::error('Coupon has expired', 400);
        }

        //check author of coupon, check if he is admin
        $author_ID = get_post_field( 'post_author', $wc_coupon->get_ID() );
        $authorData = get_userdata( $author_ID );
        if (in_array( 'administrator', $authorData->roles)):
            $admins_coupon = true;
        else:
            $admins_coupon = false;
        endif;
        if($wc_coupon->get_usage_limit() > 0) {

             $usage_left = $wc_coupon->get_usage_limit() - $wc_coupon->get_usage_count();

            if ($usage_left > 0 || $admins_coupon) {
                
            } else {
                CoreResponse::error('Coupon usage limit has been reached', 400);
            }  
             
        }

        if($admins_coupon){
            $response_data['success'] = true;
            $response_data['coupon'] = $coupon;
            CoreResponse::success($response_data, 'Apply coupon successfully');
        } else {
            $available_listings = $wc_coupon->get_meta('listing_ids');
            $available_listings_array = explode(',',$available_listings);
            if(in_array($listing_id,$available_listings_array)) {
                $response_data['success'] = true;
                $response_data['coupon'] = $coupon;
                CoreResponse::success($response_data, 'Apply coupon successfully');
            } else {
                CoreResponse::error('This coupon is not applicable for this listing', 400);
            }
        }

        CoreResponse::error('Coupon is not valid', 400);

       
    }
    
    
    /**
     * Get post meta value
     */
    public function getPostMeta($data) {
        if (!isset($data['post_id'])) {
            CoreResponse::validationError(array('post_id' => 'Post ID is required'));
        }
        
        if (!isset($data['meta_key'])) {
            CoreResponse::validationError(array('meta_key' => 'Meta key is required'));
        }
        
        try {
            $post_id = intval($data['post_id']);
            $meta_key = sanitize_text_field($data['meta_key']);
            $single = isset($data['single']) ? (bool)$data['single'] : true;
            
            $meta_value = $this->getDatabase()->get_post_meta($post_id, $meta_key, $single);
            
            $responseData = array(
                'post_id' => $post_id,
                'meta_key' => $meta_key,
                'meta_value' => $meta_value,
                'single' => $single
            );
            
            CoreResponse::success($responseData, 'Post meta retrieved successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to get post meta: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get booking by ID
     */
    public function getBookingById($data) {
        if (!isset($data['id'])) {
            CoreResponse::validationError(array('id' => 'Booking ID is required'));
        }
        
        try {
            $id = intval($data['id']);
            $booking = $this->getDatabase()->getBookingById($id);
            
            if (!$booking) {
                CoreResponse::notFound('Booking not found');
            }
            
            CoreResponse::success($booking, 'Booking retrieved successfully');
        } catch (Exception $e) {
            CoreResponse::error('Failed to get booking: ' . $e->getMessage(), 500);
        }
    }

    public function updateUserData($user_id, $data){

        $_POST = $data;


        if ( isset( $_POST['phone'] ) ){
            update_user_meta($user_id, 'billing_phone', esc_attr( $_POST['phone'] ) );
            update_user_meta($user_id, 'phone', $_POST['phone'] );
        }
        if ( isset( $_POST['countryCode'] ) ){
            update_user_meta($user_id, 'billing_country_code', esc_attr( $_POST['countryCode'] ) );
            update_user_meta( $user_id, 'country_code',$_POST["countryCode"] );
        }
        if ( isset( $_POST['contactType'] ) ){
            update_user_meta($user_id, 'profile_type', strtolower($_POST['contactType'])  );
        }
        if ( isset( $_POST['companyNumber'] ) ){
            update_user_meta($user_id, 'company_number', $_POST['companyNumber']);
        }
        if ( isset( $_POST['firstName'] ) ){
            update_user_meta($user_id, 'billing_first_name', esc_attr( $_POST['firstName'] ) );
            update_user_meta($user_id, 'first_name', $_POST['firstName'] );
        }
        if ( isset( $_POST['lastName'] ) ){
            update_user_meta($user_id, 'billing_last_name', esc_attr( $_POST['lastName'] ) );
            update_user_meta($user_id, 'last_name', $_POST['lastName'] );
        }
        if ( isset( $_POST['email'] ) ){
            update_user_meta($user_id, 'billing_email', esc_attr( $_POST['email'] ) );
        }
        if ( isset( $_POST['billingAddress1'] ) ){
            update_user_meta($user_id, 'billing_address_1', esc_attr( $_POST['billingAddress1'] ) );
        }
        if ( isset( $_POST['billing_address_2'] ) ){
            update_user_meta($user_id, 'billing_address_2', esc_attr( $_POST['billing_address_2'] ) );
        }
        if ( isset( $_POST['billingCity'] ) ){
            update_user_meta($user_id, 'billing_city', esc_attr( $_POST['billingCity'] ) );
        }
        if ( isset( $_POST['billingPostcode'] ) ){
            update_user_meta($user_id, 'billing_postcode', esc_attr( $_POST['billingPostcode'] ) );
        }
        if ( isset( $_POST['billingCountry'] ) ){
            update_user_meta($user_id, 'billing_country', esc_attr( $_POST['billingCountry'] ) );
        }
    }

    public static function registerUser($data){

        $_POST = $data;

        $return = array("success" => 0, "user_id" => 0);

        $email = $_POST['email'];
        $user_login = $email;


       

        if ( email_exists( $_POST["email"] )  ) {
            $user = get_user_by( 'email', $_POST["email"] );
            $return["success"] = 1;
            $return["user_id"] = $user->ID;
            return $return;
        }
        if ( username_exists( $user_login ) ) {
            $user = get_user_by( 'login', $user_login );
            $return["success"] = 1;
            $return["user_id"] = $user->ID;
            return $return;
        }


        $password = wp_generate_password( 12, false );

        $first_name = (isset($_POST['firstName'])) ? sanitize_text_field( $_POST['firstName'] ) : '' ;
        $last_name = (isset($_POST['lastName'])) ? sanitize_text_field( $_POST['lastName'] ) : '' ;

        $role =  "owner";

        $user_data = array(
            'user_login'    => $user_login,
            'user_email'    => $email,
            'user_pass'     => $password,
            'first_name'    => $first_name,
            'last_name'     => $last_name,
            'nickname'      => $first_name,
            'role'          => $role
        );

        $user_id = wp_insert_user( $user_data );

        if($user_id && $user_id > 0){
            if(isset($_POST["countryCode"])){
                update_user_meta( $user_id, 'country_code',$_POST["countryCode"] );
            }

            if ( isset( $_POST['phone'] ) ){
                update_user_meta($user_id, 'phone', $_POST['phone'] );
            }   
            if ( isset( $_POST['firstName'] ) ){
                update_user_meta($user_id, 'first_name', $_POST['firstName'] );
                update_user_meta($user_id, 'billing_first_name', $_POST['firstName'] );
            }   
            if ( isset( $_POST['lastName'] ) ){
                update_user_meta($user_id, 'last_name', $_POST['lastName'] );
                update_user_meta($user_id, 'billing_last_name', esc_attr( $_POST['lastName'] ) );
            }
            if ( isset( $_POST['email']  )){
                update_user_meta($user_id, 'billing_email', esc_attr( $_POST['email'] ) );
            } 

            if ( isset( $_POST['billingAddress1'] ) ){
                update_user_meta($user_id, 'billing_address_1', esc_attr( $_POST['billingAddress1'] ) );
            }
            if ( isset( $_POST['billing_address_2'] ) ){
                update_user_meta($user_id, 'billing_address_2', esc_attr( $_POST['billing_address_2'] ) );
            }
            if ( isset( $_POST['billingCity'] ) ){
                update_user_meta($user_id, 'billing_city', esc_attr( $_POST['billingCity'] ) );
            }
            if ( isset( $_POST['billingPostcode'] ) ){
                update_user_meta($user_id, 'billing_postcode', esc_attr( $_POST['billingPostcode'] ) );
            }
             if ( isset( $_POST['billingCountry'] ) ){
                update_user_meta($user_id, 'billing_country', esc_attr( $_POST['billingCountry'] ) );
            }
            if ( isset( $_POST['contactType'] ) ){

                update_user_meta($user_id, 'profile_type', strtolower($_POST['contactType'])  );
            }

            if ( isset( $_POST['phone'] ) ){
                update_user_meta($user_id, 'billing_phone', esc_attr( $_POST['phone'] ) );
            }
            if ( isset( $_POST['countryCode'] ) ){
                update_user_meta($user_id, 'billing_country_code', esc_attr( $_POST['countryCode'] ) );
            }
            if ( isset( $_POST['companyNumber'] ) ){

                update_user_meta($user_id, 'company_number', $_POST['companyNumber']);
            }

            $return["success"] = 1;
            $return["user_id"] = $user_id;
            
        }

        return $return;


    }
    
    
} 