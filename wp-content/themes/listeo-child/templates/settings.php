<?php
global $wpdb;
$active_group_id = get_user_meta( get_current_user_id(), '_gibbs_active_group_id',true );

if($active_group_id != ""){
    $user_management_group_id = $active_group_id;
}else{
    $user_management_group_id = "0";
}
?>

<div class="settingsg">
    <form method="post" action="">
        <input type="hidden" name="action" value="save_settings">
        <div class="section">
            <div class="header">
            <button type="button" class="change-department" id="usergroup_addnew_st"><?php echo __("Opprett ny konto","gibbs");?></button>
            <button style="display: none;" id="usergroup_modalbtn"></button>
            </div>
        </div>
        <?php if($user_management_group_id != "0"){ 

            $users_groups_table = $wpdb->prefix . 'users_groups';  // table name
            $sql_user_group_modal = "select * from `$users_groups_table` where id = $user_management_group_id";
            $user_group_data_modal = $wpdb->get_row($sql_user_group_modal);

            $users_group_id = "";
            $users_group_name = "";
            $group_admin = "";
            $group_admin_email = "";
            $admin_emails = "";
            $group_admin = get_group_admin();

            if($group_admin != ""){
                $cr_user_id = $group_admin;
            }else{
                $cr_user_id = get_current_user_id();
            }
            $admin_emails = get_user_meta( $cr_user_id, 'admin_emails', true );
            if($user_currency == ""){
                $user_currency = "NOK";
            }
            if(isset($user_group_data_modal->id) && isset($user_group_data_modal->name)){

                $users_group_id = $user_group_data_modal->id;
                $users_group_name = $user_group_data_modal->name;
                $users_table = $wpdb->prefix . 'users'; 

                if($user_group_data_modal->group_admin != ""){

                    $group_admin = $user_group_data_modal->group_admin;

                    $user_data_sql = "select user_email from `$users_table` where id = $group_admin";

                    $user_data = $wpdb->get_row($user_data_sql);

                    if(isset($user_data->user_email)){
                        $group_admin_email = $user_data->user_email;
                    }

                }
           
            
            ?>
                <input type="hidden" class="users_group_id" name="users_group_id" value="<?php echo $users_group_id;?>">
                <div class="section ">
                    <div class="header2 ">
                        <h2><?php echo __("Kontoinformasjon","gibbs");?></h2>
                        <button type="button" class="change-department" id="group_sidebar">Bytt konto <i class="fa fa-chevron-down"></i></button>
                    </div>
                    <div class="content">
                        <div class="form-group">
                            <label for="gr_name">Navn</label>
                            <input type="text" id="gr_name" name="gr_name" value="<?php echo $users_group_name;?>" />
                        </div>
                        <div class="form-group">
                            <label for="gr_email">Primær e-post (Alle varsler for denne kontoen sendes hit)</label>
                            <input type="email" id="gr_email" name="gr_email" value="<?php echo $group_admin_email;?>" />
                        </div>
                        <div class="form-group">
                            <label for="admin_emails">Kopi til (CC) e-post (f.eks. rengjøring, vaktmester, eier)</label>
                            <input type="text" id="admin_emails" name="admin_emails" value="<?php echo $admin_emails;?>" placeholder="admin@example.com, admin2@example.com" />
                        </div>
                    </div>
                </div>
            <?php } ?>
            
            <?php if($user_management_group_id != "0"){ 

                

                if(class_exists('Class_Gibbs_Subscription')){
                    $Class_Gibbs_Subscription = new Class_Gibbs_Subscription();

                    $super_admin = $Class_Gibbs_Subscription->get_super_admin();
                    if($super_admin != ""){
                        $info_user_id = $super_admin;
                    }else{
                        $info_user_id = get_current_user_id();
                    }
                }else{
                    $info_user_id = get_current_user_id();
                }
                
                $company_name = get_user_meta($info_user_id, 'billing_company', true);
                $street_address = get_user_meta($info_user_id, 'billing_address_1', true);
                $zip_code = get_user_meta($info_user_id, 'billing_postcode', true);
                $city = get_user_meta($info_user_id, 'billing_city', true);
                $organization_number = get_user_meta($info_user_id, 'company_number', true);
                
                $contact_name = get_user_meta($info_user_id, 'display_name', true);
                if(empty($contact_name)){
                    $first_name = get_user_meta($info_user_id, 'billing_first_name', true);
                    $last_name = get_user_meta($info_user_id, 'billing_last_name', true);
                    $contact_name = trim($first_name . ' ' . $last_name);
                }
                $contact_email = get_user_meta($info_user_id, 'billing_email', true);
                $contact_phone = get_user_meta($info_user_id, 'billing_phone', true);
                if(empty($contact_phone)){
                    $contact_phone = get_user_meta($info_user_id, 'phone', true);
                }
            ?>
            <!-- Company Information Section -->
            <div class="section">
                <div class="header2">
                    <h2><?php echo __("Company Information","gibbs");?></h2>
                </div>
                <div class="content">
                    <div class="form-group">
                        <label for="company_name"><?php echo __("Company Name","gibbs");?></label>
                        <input type="text" id="company_name" name="company_name" value="<?php echo esc_attr($company_name);?>" placeholder="<?php echo __("Company Name Inc.","gibbs");?>" />
                    </div>
                    <div class="form-group">
                        <label for="street_address"><?php echo __("Street Address","gibbs");?></label>
                        <input type="text" id="street_address" name="street_address" value="<?php echo esc_attr($street_address);?>" placeholder="<?php echo __("Street Address","gibbs");?>" />
                    </div>
                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="zip_code"><?php echo __("Zip Code","gibbs");?></label>
                            <input type="text" id="zip_code" name="zip_code" value="<?php echo esc_attr($zip_code);?>" placeholder="<?php echo __("0000","gibbs");?>" />
                        </div>
                        <div class="form-group form-group-half">
                            <label for="city"><?php echo __("City","gibbs");?></label>
                            <input type="text" id="city" name="city" value="<?php echo esc_attr($city);?>" placeholder="<?php echo __("City","gibbs");?>" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="organization_number"><?php echo __("Organization Number","gibbs");?></label>
                        <input type="text" id="organization_number" name="organization_number" value="<?php echo esc_attr($organization_number);?>" placeholder="<?php echo __("999 999 999","gibbs");?>" />
                    </div>
                </div>
            </div>
            
            <!-- Contact Person Section -->
            <div class="section">
                <div class="header2">
                    <h2><?php echo __("Contact Person","gibbs");?></h2>
                </div>
                <div class="content">
                    <div class="form-group">
                        <label for="contact_name"><?php echo __("Name","gibbs");?></label>
                        <input type="text" id="contact_name" name="contact_name" value="<?php echo esc_attr($contact_name);?>" placeholder="<?php echo __("Full Name","gibbs");?>" />
                    </div>
                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="contact_email"><?php echo __("Email","gibbs");?></label>
                            <input type="email" id="contact_email" name="contact_email" value="<?php echo esc_attr($contact_email);?>" placeholder="<?php echo __("contact@company.com","gibbs");?>" />
                        </div>
                        <div class="form-group form-group-half">
                            <label for="contact_phone"><?php echo __("Phone Number","gibbs");?></label>
                            <input type="text" id="contact_phone" name="contact_phone" value="<?php echo esc_attr($contact_phone);?>" placeholder="<?php echo __("+47 000 00 000","gibbs");?>" />
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>
            
            <div class="section ">
            <div class="header2 ">
                    <h2>Valuta</h2>
                    </div>
                    <div class="form-group">
                        <label for="currency">
                        Velg hvilken valuta du ønsker å motta betalinger i.</label>
                        <select id="currency" name="currency">
                        <?php
                            $group_admin = get_group_admin();

                            if($group_admin != ""){
                                $currency_user_id = $group_admin;
                            }else{
                                $currency_user_id = get_current_user_id();
                            }
                            $user_currency = get_user_meta( $currency_user_id, 'currency', true );
                            if($user_currency == ""){
                                $user_currency = "NOK";
                            }

                            $currencies = get_woocommerce_currencies();
                            
                            foreach ($currencies as $currency_code => $currency_name) {
                                if(strtolower($currency_code) != "usd" && strtolower($currency_code) != "nok" && strtolower($currency_code) != "dkk"){
                                    continue;
                                }
                                // Get the currency symbol for each currency code
                                $currency_symbol = get_woocommerce_currency_symbol($currency_code);
                        ?>
                                
                                <option value="<?php echo $currency_code;?>" <?php if($user_currency == $currency_code){?>selected<?php }?>><?php echo $currency_name."(".$currency_symbol.")";?></option>
                                
                        <?php	
                            }
                        ?>
                        </select>
                    </div>
                    
            </div>
            <button type="submit" class="btn btn-primary">Lagre</button>
        <?php } ?>
    </form>
   
    <?php 
       
        require(WP_PLUGIN_DIR."/gibbs-user-management/user_group_modal.php");
    ?>
    <?php if (class_exists('Dintero_Frontend')):
        // Load Dintero settings
        $dintero_settings = Dintero_Frontend::get_dintero_settings();

        $bank_data_dintero = [];

        if(isset($dintero_settings['bank_accounts']) && !empty($dintero_settings['bank_accounts'])){
            $bank_data_dintero = $dintero_settings['bank_accounts'][0];
        }

        //echo "<pre>"; print_r($dintero_settings); die;

        
        // Use saved settings or defaults
        $bank_name = isset($bank_data_dintero['bank_name']) ? $bank_data_dintero['bank_name'] : "";
        $bank_account_number = isset($bank_data_dintero['bank_account_number']) ? $bank_data_dintero['bank_account_number'] : "";
        $bank_account_number_type = isset($bank_data_dintero['bank_account_number_type']) ? $bank_data_dintero['bank_account_number_type'] : "";
        $bank_account_country_code = isset($bank_data_dintero['bank_account_country_code']) ? $bank_data_dintero['bank_account_country_code'] : "";
        $bank_account_currency = isset($bank_data_dintero['bank_account_currency']) ? $bank_data_dintero['bank_account_currency'] : "";
        $payout_currency = isset($bank_data_dintero['payout_currency']) ? $bank_data_dintero['payout_currency'] : "";
        $bank_identification_code = isset($bank_data_dintero['bank_identification_code']) ? $bank_data_dintero['bank_identification_code'] : "";
    ?>
        <div class="section mt-5">
            <div class="header2">
                <h2><?php echo __("Dintero Settings","gibbs");?></h2>
            </div>
            <div class="content">
                <div class="form-group">
                    <label for="dintero_payment_checkbox"><?php echo __("Activate payment","gibbs");?></label>
                    <label class="switch">
                        <input type="checkbox" id="dintero_payment_checkbox" name="dintero_payment_checkbox" <?php if(get_user_meta( $currency_user_id, 'dintero_payment', true ) == "on"){?>checked<?php }?>>
                        <span class="slider"></span>
                    </label>
                </div>
                
                <!-- Toggleable Section -->
                <div id="extra_section_content" class="extra-section-content" <?php if(get_user_meta( $currency_user_id, 'dintero_payment', true ) != "on"){?>style="display:none;"<?php }?>>
                    
                    <!-- Dintero Payment Settings -->
                    <?php if (class_exists('Dintero_Frontend')): ?>
                    <div class="dintero-section">
                        <h4><?php echo __("Dintero Payment Settings","gibbs");?></h4>
                        
                        <?php 
                            $user_payout_destination_id = get_user_meta($cr_user_id,"user_payout_destination_id",true);
                        ?>
                        
                        <?php if($user_payout_destination_id != ""): ?>
                            <div class="alert alert-success">
                                <i class="fa fa-check-circle"></i> <?php echo __("Payment activated","gibbs");?>
                            </div>
                            <button type="button" id="open_dintero_seller" class="btn btn-primary"><?php echo __("Seller Details","gibbs");?></button>
                        <?php else: ?>
                            <button type="button" id="open_dintero_settings" <?php if(get_user_meta( $currency_user_id, 'dintero_payment', true ) == "on"){?>style="display:block;"<?php }else{?>style="display:none;"<?php }?> class="btn btn-secondary"><?php echo __("Add bank details","gibbs");?></button>
                        <?php endif; ?>
                        
                        
                    </div>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>

        <?php
        //  $bank_name = 'DNB';
        //  $bank_account_number = 'NO9386011117947';
        //  $bank_account_number_type = 'IBAN';
        //  $bank_account_country_code = 'NO';
        //  $bank_account_currency = 'NOK';
        //  $payout_currency = 'NOK';
        //  $bank_identification_code = 'DNBANOKKXXX';
        ?>

   
        <div id="dintero_settings_modal" class="dintero-modal" style="display:none;">
            <div class="dintero-modal-content">
                <span class="dintero-close">&times;</span>
                <h3><?php echo __("Dintero Bank Settings","gibbs");?></h3>
                <form id="dintero_settings_form" method="post" action="">
                    <input type="hidden" name="action" value="save_dintero_settings">
                    <div class="dintero-form-columns">
                        <div class="dintero-column">
                            <div class="form-group">
                                <label for="dintero_bank_name"><?php echo __("Bank Name","gibbs");?></label>
                                <input type="text" id="dintero_bank_name" name="dintero_bank_name" value="">
                            </div>
                            <div class="form-group">
                                <label for="dintero_bank_account_number"><?php echo __("Bank Account Number","gibbs");?></label>
                                <input type="text" id="dintero_bank_account_number" name="dintero_bank_account_number" value="">
                            </div>
                            <div class="form-group">
                                <label for="dintero_bank_account_number_type"><?php echo __("Account Number Type","gibbs");?></label>
                                <input type="text" id="dintero_bank_account_number_type" name="dintero_bank_account_number_type" value="">
                            </div>
                            <div class="form-group">
                                <label for="dintero_bank_account_country_code"><?php echo __("Country Code","gibbs");?></label>
                                <select id="dintero_bank_account_country_code" name="dintero_bank_account_country_code">
                                    <option value="NO" selected>NO - Norge</option>
                                    <option value="SE">SE - Sverige</option>
                                    <option value="DK">DK - Danmark</option>
                                </select>
                            </div>
                        </div>
                        <div class="dintero-column">
                            <div class="form-group">
                                <label for="dintero_bank_account_currency"><?php echo __("Bank Account Currency","gibbs");?></label>
                                <select id="dintero_bank_account_currency" name="dintero_bank_account_currency">
                                    <option value="NOK" selected>NOK</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="dintero_payout_currency"><?php echo __("Payout Currency","gibbs");?></label>
                                <select id="dintero_payout_currency" name="dintero_payout_currency">
                                    <option value="NOK" selected>NOK</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="dintero_bank_identification_code"><?php echo __("Bank Identification Code","gibbs");?></label>
                                <input type="text" id="dintero_bank_identification_code" name="dintero_bank_identification_code" value="">
                            </div>
                        </div>
                    </div>
                    <button type="submit" id="save_dintero_settings" class="btn btn-primary"><?php echo __("Save Dintero","gibbs");?></button>
                </form>
            </div>
        </div>
        
        <!-- Dintero Seller Creation Modal -->
        <div id="dintero_seller_modal" class="dintero-modal" style="display:none;">
            <div class="dintero-modal-content">
                <span class="dintero-close"></span>
                    <div class="seller-info">
                        <div class="seller-details">
                            <h4><?php echo __("Bank Details","gibbs");?>:</h4>
                            <ul>
                                <!-- <li><strong>Bank:</strong> <?php echo esc_html($bank_name); ?></li> -->
                                <li><strong><?php echo __("Account Number","gibbs");?>:</strong> <?php echo esc_html($bank_account_number); ?></li>
                                <li><strong><?php echo __("Bank Account Currency","gibbs");?>:</strong> <?php echo esc_html($bank_account_currency); ?></li>
                                <li><strong><?php echo __("Account Number Type","gibbs");?>:</strong> <?php echo esc_html($bank_account_number_type); ?></li>
                                <li><strong><?php echo __("Account Country Code","gibbs");?>:</strong> <?php echo esc_html($bank_account_country_code); ?></li>
                            </ul>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" id="change_bank_details" class="btn btn-primary"><?php echo __("Change Bank Details","gibbs");?></button>
                        <button type="button" class="btn btn-secondary dintero-close"><?php echo __("Cancel","gibbs");?></button>
                    </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<script>
    jQuery("#usergroup_addnew_st").click(function(){
  
        jQuery("#usergroupModal").show();
        jQuery(".chaange_group_title").html("<?php  echo __("Opprett konto","Gibbs");;?>")
        //jQuery(".group_admin_email_div").hide();
        setTimeout(function(){
            jQuery(".users_group_id").val("");
            jQuery(".users_group_name").val("");
            jQuery(".group_admin_email").val("");
        },100);

    });
    jQuery(document).on("click","#group_sidebar",function(){
        setTimeout(function(){
            jQuery("#menudrpcontent").addClass("show");
            jQuery(".gr_divv").addClass("focus_div")
        },200)
    })
    
    // Dintero payment checkbox functionality
    jQuery("#dintero_payment_checkbox").change(function(){
        if(jQuery(this).is(":checked")){
            jQuery("#extra_section_content").show();
            jQuery("#open_dintero_settings").show();
        } else {
            jQuery("#extra_section_content").hide();
            jQuery("#open_dintero_settings").hide();
        }
    });

    // Change Bank Details confirmation functionality
    jQuery("#change_bank_details").click(function(){
        if(confirm("<?php echo __("Are you sure you want to change bank details? If you change the bank details, then new details will create a new seller account. Do you want to proceed?","gibbs"); ?>")){
            // User clicked OK/Proceed
            // Show the dintero settings modal
            jQuery('#dintero_seller_modal').hide();
            jQuery("#dintero_settings_modal").show();
            console.log("User confirmed bank details change - opening modal");
        } else {
            // User clicked Cancel
            console.log("User cancelled bank details change");
        }
    });
</script>
<style>
    .settingsg .user_group_modal:before {
        height: 20%;
    }
    

    
    /* Toggle Switch Styling */
    .switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }
    
    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 34px;
    }
    
    .slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    
    /* input:checked + .slider {
        background-color: #2196F3;
    } */
    
    input:checked + .slider:before {
        transform: translateX(26px);
    }
    
    /* Dintero Section Styling */
    .dintero-section {
        margin-bottom: 30px;
        padding: 20px;
        background-color: #f0f8ff;
        border-radius: 8px;
        border: 1px solid #cce5ff;
    }
    
    .dintero-section h4 {
        margin-top: 0;
        margin-bottom: 20px;
        color: #0066cc;
        font-size: 18px;
    }
    
    .extra-fields-section {
        margin-top: 30px;
        padding: 20px;
        background-color: #f9f9f9;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }
    
    .extra-fields-section h4 {
        margin-top: 0;
        margin-bottom: 20px;
        color: #333;
        font-size: 18px;
    }
    
    .alert {
        padding: 12px 16px;
        margin-bottom: 20px;
        border: 1px solid transparent;
        border-radius: 4px;
    }
    
    .alert-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }
    
    .alert i {
        margin-right: 8px;
    }
    
    .btn {
        display: inline-block;
        padding: 8px 16px;
        margin: 5px;
        font-size: 14px;
        font-weight: 500;
        text-align: center;
        text-decoration: none;
        border: 1px solid transparent;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-primary {
        color: #fff;
        background-color: #007cba;
        border-color: #007cba;
    }
    
    .btn-primary:hover {
        background-color: #005a87;
        border-color: #005a87;
    }
    
    .btn-secondary {
        color: #fff;
        background-color: #6c757d;
        border-color: #6c757d;
    }
    
    .btn-secondary:hover {
        background-color: #545b62;
        border-color: #545b62;
    }
    
    /* Form Elements Styling */
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
    }
    
    .form-group input[type="text"],
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }
    
    .form-group textarea {
        min-height: 80px;
        resize: vertical;
    }
    
    .form-group select {
        background-color: white;
    }
    
    /* Form Row for Side-by-Side Fields */
    .form-row {
        display: flex;
        gap: 20px;
        padding: 0 7px;
    }
    
    .form-group-half {
        flex: 1;
        margin-bottom: 0;
    }
    
    @media (max-width: 768px) {
        .form-row {
            flex-direction: column;
            gap: 0;
        }
        
        .form-group-half {
            margin-bottom: 20px;
        }
    }
</style>