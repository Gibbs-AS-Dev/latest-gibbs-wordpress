<?php
get_header();
// single-giftcard.php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
// Get current post data
global $post;
$giftcard_id = $post->ID;
$woocommerce_product_id = get_post_meta($giftcard_id, 'woocommerce_product_id', true);
if(!$woocommerce_product_id){
    wp_redirect(home_url());
    exit;
}
$title = get_the_title($giftcard_id);
$description = apply_filters('the_content', get_the_content($giftcard_id));
$image_url = get_the_post_thumbnail_url($giftcard_id, 'large'); // Get featured image URL

$Class_Gibbs_Giftcard = new Class_Gibbs_Giftcard;
$check_giftcard_page_id = $Class_Gibbs_Giftcard->get_page_id_by_shortcode("check_giftcard");
$group_name = $Class_Gibbs_Giftcard->getGroupName($post->post_author);
$min_amount = get_post_meta($giftcard_id, 'min_amount', true) ?: "";
?>
<style>
    .get_your_trial{
        display: none;
    }
    /* Spinner styles */
    .spinner-border {
        display: inline-block;
        width: 2rem;
        height: 2rem;
        vertical-align: -0.125em;
        border: 0.25em solid currentColor;
        border-right-color: transparent;
        border-radius: 50%;
        -webkit-animation: .75s linear infinite spinner-border;
        animation: .75s linear infinite spinner-border;
    }
    .spinner-border-sm {
        width: 1rem;
        height: 1rem;
        border-width: 0.2em;
    }
    @-webkit-keyframes spinner-border {
        to {
            -webkit-transform: rotate(360deg);
            transform: rotate(360deg);
        }
    }
    @keyframes spinner-border {
        to {
            -webkit-transform: rotate(360deg);
            transform: rotate(360deg);
        }
    }
</style>

<?php 
$iframe = "";
if(isset($_GET["iframe"]) && $_GET["iframe"] == "true"){ 

    $iframe = "?iframe=true";
    
    ?>
<style>
    .giftcard_single {
        flex-direction: column;
        padding: 0 !important;
        margin: 0 !important;
    }
    .giftcard_single .giftcard-details {
        display:none;
    }
    .giftcard-image-section {
        margin-left: 0px;
        margin-bottom: 20px;
    }
    .giftcard-image-section {
        display: none;
    }
    header {
        display: none;
    }
    .giftcard_single .purchase-card {
        min-width: 100px;
        margin-left: 0px;
    }
</style>

<?php }
?>

<div class="giftcard_single">
    <div class="giftcard-container">
        <!-- Gift Card Image and Details Section -->
        <div class="giftcard-image-section">
            <?php if ($image_url): ?>
                <img src="<?php echo esc_url($image_url); ?>" alt="Gift Card Image" class="giftcard-image">
            <?php else: ?>
                <div class="giftcard-image">
                    <div class="gift-card">
                        <div class="icon"></div>
                        <div class="title">Gavekort</div>
                        <div class="button">🥳 Gjør noen veldig glad 🥳</div>
                        <div class="footer">Utsendes av <?php echo $group_name;?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Gift Card Details Section -->
        <div class="giftcard-details">
            <h2><?php echo esc_html($title); ?></h2>
            <p> <?php echo $description; ?></p>
        </div>
    </div>

    <!-- Buy Gift Card Form Section -->
    <div class="giftcard-purchase-section">
        <div class="purchase-card">
            <h4>Kjøp et gavekort</h4>
            <form action="<?php echo home_url();?>/gift-booking" method="POST" id="giftcardPurchaseForm">
                <?php if(isset($_GET["iframe"]) && $_GET["iframe"] == true){ ?>
                    <input type="hidden" name="iframe" value="true">
                <?php } ?>
                <input type="hidden" name="gift_booking" value="1">
                <input type="hidden" name="giftcard_id" value="<?php echo $giftcard_id;?>">
                <label for="giftcardAmount">Skriv inn gavebeløp</label>
                <input type="number" id="giftcardAmount" name="giftcard_amount" placeholder="eks.. 500kr " <?php if($min_amount != ""){ ?> min="<?php echo $min_amount;?>" <?php }else{ ?> min="100" <?php } ?>required>
                <?php if($check_giftcard_page_id){ ?><a href="<?php echo get_permalink($check_giftcard_page_id).$iframe;?>" class="check-saldo">Sjekk saldo?</a><?php } ?>
                <button type="submit" class="purchase-btn">Gå videre</button>
            </form>
        </div>
    </div>
</div>
<?php
get_footer();
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('giftcardPurchaseForm');
        const submitButton = form.querySelector('.purchase-btn');

        form.addEventListener('submit', function() {
            // Disable the button and show a loading indicator
            submitButton.disabled = true;
            submitButton.innerHTML = 'Laster inn... <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        });
    });
</script>

  