<?php
$super_admin = $this->get_super_admin();

if(!$super_admin){
    wp_redirect(home_url());
}
$args_packages = [
    'post_type' => 'stripe-packages',
    'posts_per_page' => -1, 
    'orderby' => 'date', 
    'order' => 'ASC' 
];
$query = new WP_Query($args_packages);


$packages = $query->posts;


$user_id = $super_admin;

$mode = get_option('stripe_mode');
if($mode == "test"){
    $stripe_customer_id = get_user_meta($user_id, 'stripe_test_customer_id', true);
}else{
    $stripe_customer_id = get_user_meta($user_id, 'stripe_customer_id', true);
}
//$stripe_customer_id = get_user_meta($user_id, 'stripe_customer_id', true);

$active_subscription_price_id = null;
$active_sub = [];
$has_payment_method = false;
$is_trial = false;

if ($stripe_customer_id) {
    try {
        // Check for payment methods
        $payment_methods = $this->stripe->paymentMethods->all([
            'customer' => $stripe_customer_id,
            'type' => 'card'
        ]);
        $has_payment_method = count($payment_methods->data) > 0;

        $subscriptions = $this->stripe->subscriptions->all(['customer' => $stripe_customer_id]);
        if (count($subscriptions->data) > 0) {
            $active_subscription = $subscriptions->data[0]; // Get the first active subscription
            $active_subscription_price_id = $active_subscription->items->data[0]->price->id; // Get the price ID of the active subscription
            $price_id = $active_subscription->items->data[0]->price->id; // Assuming single price
            $price = $this->stripe->prices->retrieve($price_id);

            $active_subscription->pricer = $price;
            $active_sub = $active_subscription;
            
            // Check if subscription is in trial
            $is_trial = !empty($active_subscription->trial_end) && $active_subscription->trial_end > time();
        }
    } catch (Exception $e) {
        error_log('Error fetching subscriptions: ' . $e->getMessage());
    }
}
$disable_stripe_dashboard = false;
if(isset($active_sub->pricer->product) && $active_sub->pricer->product == $this->stripe_custom_plan_product_id){
    $disable_stripe_dashboard = true;
}
$trail = get_user_meta($super_admin, 'stripe_trail', true);

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
//echo "<pre>"; print_r($active_sub); die;
?>
<div class="package-view">
    <?php if ($stripe_customer_id && !$disable_stripe_dashboard && !$has_payment_method && $trail == "true"  && !$is_trial): ?>
    <div class="payment-method-banner" style="background-color: #fff3cd; color: #856404; padding: 15px; margin-bottom: 20px; border: 1px solid #ffeeba; border-radius: 4px;">
        <strong><?php echo __("Viktig melding", "gibbs"); ?>:</strong> 
        <?php echo __("Du har ikke lagt til noe betalingskort ennå. Vennligst legg til et betalingskort for å aktivere abonnementet ditt.", "gibbs"); ?>
    </div>
    <?php endif; ?>

    <?php foreach ($packages as $package): 

        $package = (array) $package;
        $stripe_product_id = get_post_meta($package["ID"], 'stripe_product_id', true);
        $start_price_id = get_post_meta($package["ID"],"start_price_id",true);
        $lock_price = get_post_meta($package["ID"],"lock_price",true);
        $shally_price = get_post_meta($package["ID"],"shally_price",true);

        $user_id = $this->get_super_admin(); 

        $locks = $this->getLocks($user_id);
        $shelly = $this->getShally($user_id);

        if($lock_price != ""){
            $lock_price = $lock_price * $locks;
            $start_price_id = $start_price_id + $lock_price;
        }
    
        if($shally_price != ""){
            $shally_price = $shally_price * $shelly;
            $start_price_id = $start_price_id + $shally_price;
        }

        $first_listing_price = $start_price_id;
        
        $locks = $this->getLocks($super_admin);

        $disable_btn = false;

        if($locks > 0 && $lock_price == ""){
            $disable_btn = true;
        }
        
        $shelly = $this->getShally($super_admin);

        if($shelly > 0 && $shally_price == ""){
            $disable_btn = true;
        }
        


        $data_sub = array();
        if(isset($active_sub->pricer->product) && $active_sub->pricer->product == $stripe_product_id){
            $data_sub = $active_sub;
        }
        $Class_Gibbs_Subscription = new Class_Gibbs_Subscription;
		$get_listing_count  = $Class_Gibbs_Subscription->get_listing_count($super_admin);
        ?>
        <div class="package">
            <div class="top-area">
                <div class="top-right">
                    <h3><?php echo esc_html($package['post_title']); ?> <?php if(isset($data_sub->status)){?> <span class="badge badge-status bd-<?php echo $data_sub->status;?>"><?php esc_html_e($data_sub->status, 'listeo_core'); ?></span><?php } ?></h3>
                    <div class="in-div">
                        <?php if($start_price_id != 0){ ?>
                            <?php if(isset($data_sub->id)){?>
                            
                                    
                                <p  style="display:none">
                                <span >  <strong> </strong>  Antall publiserte utleieobjekter. <span class="listing-count"><?php echo $get_listing_count; ?> stk </span> 
                                </p>
                                <?php if ($lock_price != "") { ?>
                                <p>
                                <span>  <strong> </strong> Antall aktive smartlås. </strong>  </span> <span class="listing-count"><?php if ($locks !== "") { ?><?php echo isset($locks) ? $locks : 0; ?><?php } ?> stk </span>   </p>
                                </p>
                                <?php } ?>
                                <?php if ($shally_price != "") { ?>
                                <p>
                                <span>  <strong> </strong> <?php echo __("Antall aktive strømstyringsenheter","gibbs");?>. </span>  <span class="listing-count"><?php if ($shelly !== "") { ?> <?php echo isset($shelly) ? $shelly : 0; ?><?php } ?> stk </span>   </p>
                                </p>
                                <?php } ?>
                            
                                <p><?php echo __("Gyldig til","gibbs");?>:  <span class="listing-count"><?php echo esc_html(date('Y-m-d', $data_sub->current_period_end));?> </span>  
                                </p>
                                <p><?php echo __("Total pris per mnd","gibbs");?>:<span class="listing-count"><?php echo esc_html(number_format($price->unit_amount / 100, 2))?>kr (<?php echo __("eks mva","gibbs");?>)</span>
                                <p>  
                                
                                </p>
                            <?php }else{ ?>
                            <p>From <?php echo esc_html($first_listing_price); ?>kr/mo (<?php echo __("eks mva","gibbs");?>)</p>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </div>
                <div class="top-right">
                    <?php if($start_price_id != 0){ ?>
                        
                        <?php if(isset($data_sub->id)){?>
                            <span class="load-div">
                                <button class="cancel-subscription" data-subscription-id="<?php echo $data_sub->id; ?>" data-price-id="<?php echo esc_attr($start_price_id); ?>" data-package-id="<?php echo $package["ID"]; ?>">
                                    <?php echo __("Avslutt","gibbs");?>
                                </button>
                                <span class="loading spinner" style="display: none;width:20px;height:20px;"></span>
                            </span>
                        <?php }else{ ?>

                            <?php

                            $company_company_name = get_user_meta($info_user_id, 'company_company_name', true);
                            $company_email = get_user_meta($info_user_id, 'company_email', true);
                            
                            $existCompanyData = true;
                            
                            if($company_company_name == "" || $company_email == ""){
                                $existCompanyData = false;
                            } ?>
                            <span class="load-div">
                                <button class="<?php if($existCompanyData == true){ echo 'checkout-button';}else{ echo 'checkout-popup';}?> <?php if($disable_btn == true){ echo 'btn-primary.disabled';}?>" data-price-id="<?php echo esc_attr($start_price_id); ?>" data-package-id="<?php echo $package["ID"]; ?>" <?php if($disable_btn == true){ echo "disabled";}?> <?php if(isset($active_sub->pricer->product) && $active_sub->pricer->product == $this->stripe_custom_plan_product_id){  echo "disabled";}?>>
                                     <?php echo __("Aktiver","gibbs");?>
                                </button>
                                <span class="loading spinner" style="display: none;width:20px;height:20px;"></span>
                            </span>
                        <?php } ?>
                    <?php } ?>
                    
                </div>
            </div>
            <div class="bottom-area">
                <p><?php echo wp_trim_words(strip_tags($package['post_content']), 10,"...."); ?></p>
                <p class="gibbs_popup-<?php echo $package['ID']; ?>"> 
                    <strong>
                        <span class="load-div">
                            <a href="javascript:void(0)">Les mer</a>
                            <span class="loading spinner" style="display: none;width:20px;height:20px;"></span>
                        </span>    
                    </strong>
                </p>
            </div>
        </div>
    <?php endforeach; ?>
    <?php

    $group_admin = get_group_admin();

   

    $total_sms_count = $this->get_sms_count($group_admin);

    ?>

    <div class="package">
        <div class="top-area">
            <div class="top-right">
                <h3>SMS (<?php echo $total_sms_count; ?> stk)</h3>
                <div class="in-div">
                    <?php if($total_sms_count > 0){ ?>
                        <p>Pris: <?php echo round($total_sms_count * 1,2); ?>kr</p>
                    <?php }else{ ?>
                        <p></p>
                    <?php } ?>
                </div>
            </div>
            <div class="top-right">
                                        
            <?php if($total_sms_count > 0){ ?>
                <span class="load-div">
                    <button class="sms-payment-button btn-primary.disabled">Pay</button>
                    <span class="loading spinner" style="display: none;width:20px;height:20px;"></span>
                </span>
            <?php } ?>           
                                                            
            </div>
        </div>
        <div class="bottom-area">
            <p>1 SMS koster 1kr. 1 SMS er 160 tegn.</p>
            <p class="gibbs_popup-70102"> 
                <strong>
                    <!-- <span class="load-div">
                        <a href="javascript:void(0)">Les mer</a>
                        <span class="loading spinner" style="display: none;width:20px;height:20px;"></span>
                    </span>     -->
                </strong>
            </p>
        </div>
    </div>

    <!-- <div class="package">
        <div class="top-area">
            <div class="top-left">
                <h3>Skreddersydd pakke</h3>
                <div class="contt">
                    
                    <p></p>
                </div>
            </div>
            <div class="top-right">
                    <button onclick="location.href='mailto:kontakt@gibbs.no'">
                        Kontakt
                    </button>
            </div>
        </div>
        <div class="bottom-area">
           <p>

            - Tilpasses deres spesifikke behov.
            - Kvanterabatt for store aktører.
            - Volumrabatt ved stort bruk volum.
            - Ofte brukt av kommuner og store organisasjoner
            - Funksjonalitet for nye tilpasninger – Fleksibilitet til å legge til spesialfunksjoner.
            Sesongbooking – Enkel håndtering av sesongbaserte bestillinger.
                        
           
           
           Contact us at kontakt@gibbs.no</p>
        </div>
    </div> -->
    <?php if($stripe_customer_id && !$disable_stripe_dashboard){ ?>
        <div class="stripe-dash-main">
            <div class="top-area">
                <div class="top-left">
                    <h3> <?php echo __("Kort og betalingshistorikk","gibbs");?></h3>
                </div>
                <form id="stripe-form" method="post" action="<?php echo get_admin_url(); ?>admin-ajax.php" target="_blank">
                        <input type="hidden" name="action" value="stripe_dashboard">
                        <button type="submit" style="cursor: pointer;" class="btn btn-primary">Åpne <i class="fa fa-external-link"></i>  </button>
                    </form>
            </div>
           
        </div>
    <?php } ?>
    
</div>

<!-- Checkout Contact Info Modal -->

<?php //echo do_shortcode('[checkout_contact_info_modal]'); ?>
<script>
    jQuery(".load-div").find("a").click(function(){
        jQuery(this).parent().find(".loading").show();
        var _that = this;
        setTimeout(function(){
            jQuery(_that).parent().find(".loading").hide();
        },3000)
    })
</script>
<?php 

add_action('wp_footer', function(){
	do_action('wp_footer_custom');
});

?>