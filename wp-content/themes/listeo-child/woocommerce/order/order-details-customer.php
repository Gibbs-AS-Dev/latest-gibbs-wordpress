<?php
/**
 * Order Customer Details
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/order-details-customer.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 5.6.0
 */

defined( 'ABSPATH' ) || exit;

$show_shipping = ! wc_ship_to_billing_address_only() && $order->needs_shipping_address();

global $wpdb;
$result  = $wpdb -> get_row( "SELECT * FROM `" . $wpdb->prefix . "bookings_calendar` WHERE order_id=".$order->get_id());
?>
<section class="woocommerce-customer-details">

	<?php if ( $show_shipping ) : ?>

	<section class="woocommerce-columns woocommerce-columns--2 woocommerce-columns--addresses col2-set addresses">
		<div class="woocommerce-column woocommerce-column--1 woocommerce-column--billing-address col-1">

	<?php endif; ?>

	<h2 class="woocommerce-column__title"><?php esc_html_e( 'Billing address', 'woocommerce' ); ?></h2>

	<address>
		<?php echo wp_kses_post( $order->get_formatted_billing_address( esc_html__( 'N/A', 'woocommerce' ) ) ); ?>

		<?php if ( $order->get_billing_phone() ) : ?>
			<p class="woocommerce-customer-details--phone"><?php echo esc_html( $order->get_billing_phone() ); ?></p>
		<?php endif; ?>

		<?php if ( $order->get_billing_email() ) : ?>
			<p class="woocommerce-customer-details--email"><?php echo esc_html( $order->get_billing_email() ); ?></p>
		<?php endif; ?>
	</address>

	<?php if ( $show_shipping ) : ?>

		</div><!-- /.col-1 -->

		<div class="woocommerce-column woocommerce-column--2 woocommerce-column--shipping-address col-2">
			<h2 class="woocommerce-column__title"><?php esc_html_e( 'Shipping address', 'woocommerce' ); ?></h2>
			<address>
				<?php echo wp_kses_post( $order->get_formatted_shipping_address( esc_html__( 'N/A', 'woocommerce' ) ) ); ?>

				<?php if ( $order->get_shipping_phone() ) : ?>
					<p class="woocommerce-customer-details--phone"><?php echo esc_html( $order->get_shipping_phone() ); ?></p>
				<?php endif; ?>
			</address>
		</div><!-- /.col-2 -->

	</section><!-- /.col2-set -->

	<?php endif; ?>

	<?php do_action( 'woocommerce_order_details_after_customer_details', $order ); ?>

	<?php
	if(isset($result->listing_id)){

		$refund_policy_type = get_post_meta($result->listing_id, 'refund_policy', true);
		if($refund_policy_type == "flexible_refund" || $refund_policy_type == "standard_refund"){
	?>
		<a href="<?php echo home_url();?>/kunde-kansellering/?order=<?php echo base64_encode($order->get_id());?>" style="margin-top: -17px !important;display: block;padding-bottom: 27px;"><br> <?php echo esc_html__("Passer ikke tidspunktet? Kanseller bestillingen din her", "gibbs"); ?></a>
		<?php
		}

		$author_id = get_post_field('post_author', $result->listing_id);
        $author = get_user_by('ID', $author_id);
		
		if($author && isset($author->user_email) && !empty($author->user_email)){
			echo '<p style="margin-top:10px;">'.esc_html__("If you have any questions, send an email to", "gibbs").' <a href="mailto:' . esc_attr($author->user_email) . '">' . esc_html($author->user_email) . '</a></p>';
		}
	}
	?>

</section>
