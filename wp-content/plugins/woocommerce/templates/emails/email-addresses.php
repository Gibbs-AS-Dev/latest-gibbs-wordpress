<?php
/**
 * Email Addresses
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/email-addresses.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 5.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$text_align = is_rtl() ? 'right' : 'left';
$address    = $order->get_formatted_billing_address();
$shipping   = $order->get_formatted_shipping_address();

global $wpdb;
$result  = $wpdb -> get_row( "SELECT * FROM `" . $wpdb->prefix . "bookings_calendar` WHERE order_id=".$order->get_id());

?><table id="addresses" cellspacing="0" cellpadding="0" style="width: 100%; vertical-align: top; margin-bottom: 40px; padding:0;" border="0">
	<tr>
		<td style="text-align:<?php echo esc_attr( $text_align ); ?>; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; border:0; padding:0;" valign="top" width="50%">
			<h2><?php esc_html_e( 'Billing address', 'woocommerce' ); ?></h2>

			<address class="address">
				<?php echo wp_kses_post( $address ? $address : esc_html__( 'N/A', 'woocommerce' ) ); ?>
				<?php if ( $order->get_billing_phone() ) : ?>
					<br/><?php echo wc_make_phone_clickable( $order->get_billing_phone() ); ?>
				<?php endif; ?>
				<?php if ( $order->get_billing_email() ) : ?>
					<br/><?php echo esc_html( $order->get_billing_email() ); ?>
				<?php endif; ?>
			</address>
		</td>
		<?php if ( ! wc_ship_to_billing_address_only() && $order->needs_shipping_address() && $shipping ) : ?>
			<td style="text-align:<?php echo esc_attr( $text_align ); ?>; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; padding:0;" valign="top" width="50%">
				<h2><?php esc_html_e( 'Shipping address', 'woocommerce' ); ?></h2>

				<address class="address">
					<?php echo wp_kses_post( $shipping ); ?>
					<?php if ( $order->get_shipping_phone() ) : ?>
						<br /><?php echo wc_make_phone_clickable( $order->get_shipping_phone() ); ?>
					<?php endif; ?>
				</address>
			</td>
		<?php endif; ?>
	</tr>
</table>
<?php
	if(isset($result->listing_id)){

		$refund_policy_type = get_post_meta($result->listing_id, 'refund_policy', true);
		if($refund_policy_type == "flexible_refund" || $refund_policy_type == "standard_refund"){
	?>
		<a href="<?php echo home_url();?>/kunde-kansellering/?order=<?php echo base64_encode($order->get_id());?>" style="margin-top: -17px !important;display: block;padding-bottom: 27px;"><br> Passer ikke tidspunktet? Kanseller bestillingen din her</a>
		<?php
		}

		$author_id = get_post_field('post_author', $result->listing_id);
        $author = get_user_by('ID', $author_id);
		
		if($author && isset($author->user_email) && !empty($author->user_email)){
			echo '<p style="margin-top:10px;">'.esc_html__("If you have any questions, send an email to", "gibbs").' <a href="mailto:' . esc_attr($author->user_email) . '">' . esc_html($author->user_email) . '</a></p>';
		}
	}
?>
