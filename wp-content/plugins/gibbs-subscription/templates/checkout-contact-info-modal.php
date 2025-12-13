<div id="checkout-contact-modal" class="checkout-modal" style="display: none;">
    <div class="checkout-modal-content">
        <div class="checkout-modal-header">
            <h2><?php echo __("Company Information","gibbs");?></h2>
            <span class="checkout-modal-close">&times;</span>
        </div>
        <div class="checkout-modal-body">
            <form id="checkout-contact-form">
                <?php

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
                                
                $company_company_name = get_user_meta($info_user_id, 'company_company_name', true);
                $company_email = get_user_meta($info_user_id, 'company_email', true);
                $company_industry = get_user_meta($info_user_id, 'company_industry', true);
                $company_street_address = get_user_meta($info_user_id, 'company_street_address', true);
                $company_zip_code = get_user_meta($info_user_id, 'company_zip_code', true);
                $company_city = get_user_meta($info_user_id, 'company_city', true);
                $company_organization_number = get_user_meta($info_user_id, 'company_organization_number', true);
                
                $company_country_code = get_user_meta($info_user_id, 'company_country_code', true);
                $company_phone = get_user_meta($info_user_id, 'company_phone', true);
                $company_country = get_user_meta($info_user_id, 'company_country', true);

                $countries = get_countries();
                $industries = get_industries();

                if($company_country == ""){
                    $company_country = "NO";
                }
                if($company_country_code == ""){
                    $company_country_code = "+47";
                }

                ?>
                <div class="checkout-form-row">
                    <div class="checkout-form-group checkout-form-group-half">
                        <label for="company_company_name"><?php echo __("Company Name","gibbs");?> *</label>
                        <input type="text" id="company_company_name" name="company_company_name" value="<?php echo esc_attr($company_company_name);?>" required placeholder="<?php echo __("Company Name Inc.","gibbs");?>" />
                    </div>
                    <div class="checkout-form-group checkout-form-group-half">
                        <label for="company_email"><?php echo __("Company Email","gibbs");?> *</label>
                        <input type="email" id="company_email" name="company_email" value="<?php echo esc_attr($company_email);?>" required placeholder="<?php echo __("contact@company.com","gibbs");?>" />
                    </div>
                </div>
                <div class="checkout-form-row">
                    <div class="checkout-form-group checkout-form-group-half">
                        <label for="company_industry"><?php echo __("Organization type","gibbs");?></label>
                        <select id="company_industry" name="company_industry">
                            <option value=""><?php echo __("Select Organization type","gibbs");?></option>
                            <?php foreach($industries as $industry){ ?>
                                <option value="<?php echo $industry;?>" <?php if($company_industry == $industry){?>selected<?php }?>><?php echo __($industry,"gibbs");?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="checkout-form-group checkout-form-group-half">
                        <label for="company_country"><?php echo __("Country","gibbs");?> *</label>
                        <select id="company_country" name="company_country" onchange="updateCountryCode(jQuery(this).find('option:selected').data('phone-code'))" required>
                            <?php foreach($countries as $country){ ?>
                                <option value="<?php echo $country['code'];?>" <?php if($company_country == $country['code']){?>selected<?php }?> data-phone-code="<?php echo $country['phone'];?>"><?php echo $country['name'];?></option>
                            <?php } ?>
                        </select>   
                    </div>
                </div>
                <div class="checkout-form-row">
                    <div class="checkout-form-group checkout-form-group-half">
                        <label for="company_country_code"><?php echo __("Country Code","gibbs");?> *</label>
                        <input type="text" id="company_country_code" name="company_country_code" value="<?php echo esc_attr($company_country_code);?>" required placeholder="<?php echo __("+47","gibbs");?>" readonly />
                    </div>
                    <div class="checkout-form-group checkout-form-group-half">
                        <label for="company_phone"><?php echo __("Phone Number","gibbs");?> *</label>
                        <input type="text" id="company_phone" name="company_phone" value="<?php echo esc_attr($company_phone);?>" required placeholder="<?php echo __("000 00 000","gibbs");?>" />
                    </div>
                    <div class="checkout-form-group checkout-form-group-half">
                        <label for="company_organization_number"><?php echo __("Organization Number","gibbs");?></label>
                        <input type="text" id="company_organization_number" name="company_organization_number" value="<?php echo esc_attr($company_organization_number);?>" placeholder="<?php echo __("999 999 999","gibbs");?>" />
                    </div>
                </div>
                <div class="checkout-form-group">
                    <label for="company_street_address"><?php echo __("Street Address","gibbs");?></label>
                    <input type="text" id="company_street_address" name="company_street_address" value="<?php echo esc_attr($company_street_address);?>" placeholder="<?php echo __("Street Address","gibbs");?>" />
                </div>
                <div class="checkout-form-row">
                    <div class="checkout-form-group checkout-form-group-half">
                        <label for="company_zip_code"><?php echo __("Zip Code","gibbs");?></label>
                        <input type="text" id="company_zip_code" name="company_zip_code" value="<?php echo esc_attr($company_zip_code);?>" placeholder="<?php echo __("0000","gibbs");?>" />
                    </div>
                    <div class="checkout-form-group checkout-form-group-half">
                        <label for="company_city"><?php echo __("City","gibbs");?></label>
                        <input type="text" id="company_city" name="company_city" value="<?php echo esc_attr($company_city);?>" placeholder="<?php echo __("City","gibbs");?>" />
                    </div>
                </div>
            </form>
        </div>
        <div class="checkout-modal-footer">
            <button type="button" class="checkout-btn-close"><?php echo __("Close","gibbs");?></button>
            <button type="button" class="checkout-btn-save"><?php echo __("Save","gibbs");?></button>
        </div>
    </div>
</div>
<script>
    if(!jQuery.fn.updateCountryCode){
        function updateCountryCode(phone_code){
            jQuery("#company_country_code").val("+"+phone_code);
            jQuery("#company_country_code").trigger("change");
        }
    }
</script>