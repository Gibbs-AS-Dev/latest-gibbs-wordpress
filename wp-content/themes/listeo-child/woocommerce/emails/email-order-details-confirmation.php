<?php
/**
 * Order details confirmation table shown in emails.
 *
 * This template displays order confirmation details for booking confirmations.
 *
 * @package WooCommerce\Templates\Emails
 */



defined( 'ABSPATH' ) || exit;

$text_align = is_rtl() ? 'right' : 'left';

$comment_message = "";

if(isset($booking_confirmation_data['customer_data']) && !empty($booking_confirmation_data['customer_data'])){
	if(isset($booking_confirmation_data['customer_data']['message']) && !empty($booking_confirmation_data['customer_data']['message'])){
		$comment_message = $booking_confirmation_data['customer_data']['message'];
	}
}

if($comment_message != ""){
?>
	<div style="margin-bottom: 20px;">
		<h3><?php esc_html_e( 'Booking kommentar', 'woocommerce' ); ?></h3>
		<p><?php echo wp_kses_post( nl2br( wptexturize( $comment_message ) ) ); ?></p>
	</div>
<?php
}
?>
<h2>
    <?php
        if ( $sent_to_admin ) {
            $before = '<a class="link" href="' . esc_url( $order->get_edit_order_url() ) . '">';
            $after  = '</a>';
        } else {
            $before = '';
            $after  = '';
        }
        echo wp_kses_post( $before . sprintf( __( 'Kvittering for ordre #%s', 'woocommerce' ) . '  ' . $after, $order->get_order_number() ) );
    ?>
</h2>
<?php
$startDate = date_i18n(get_option( 'date_format' ), strtotime($booking_confirmation_data['price_data']['start_date']));
$startTime = date_i18n(get_option( 'time_format' ), strtotime($booking_confirmation_data['price_data']['start_time']));

$endDate = date_i18n(get_option( 'date_format' ), strtotime($booking_confirmation_data['price_data']['end_date']));
$endTime = date_i18n(get_option( 'time_format' ), strtotime($booking_confirmation_data['price_data']['end_time']));

$booking_for_date = $startDate . " " . $startTime . " - ". $endDate . " " . $endTime;
?>
<p>Dato: <?php echo wp_kses_post( $booking_for_date); ?></p>

<div style="margin-bottom: 40px;">
	
	

	<!-- Order Items -->
	<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, Roboto;" border="1">
		<thead>
			<tr>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>; background-color: #f8f8f8;border: 1px solid #e0e0e0;padding: 10px 20px;font-size: 14px;font-weight: 600;color: #636363;"><?php esc_html_e( 'Produkt', 'woocommerce' ); ?></th>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>; background-color: #f8f8f8;border: 1px solid #e0e0e0;padding: 10px 20px;font-size: 14px;font-weight: 600;color: #636363;"><?php esc_html_e( 'Pris', 'woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
        <?php
            $product_tax_prcentage=get_post_meta($booking_confirmation_data['price_data']['listing_id'],'_tax',true);
            // echo wc_get_email_order_items( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            //     $order,
            //     array(
            //         'show_sku'      => $sent_to_admin,
            //         'show_image'    => false,
            //         'image_size'    => array( 32, 32 ),
            //         'plain_text'    => $plain_text,
            //         'sent_to_admin' => $sent_to_admin,
            //         'av_days' => "",
            //     )
            // );
            do_action( 'woocommerce_order_details_after_order_table_items', $order );
        ?>
    </tbody>
		<tfoot>
			<?php
			$item_totals = $order->get_order_item_totals();

			//echo "<pre>"; print_r($item_totals); die;
			
			if ( $item_totals ) :
				foreach ( $item_totals as $key_label => $total ) :

					if($key_label == "payment_method"){
						continue;
					}
					?>
					<tr>
						<th class="td" scope="row" style="text-align:<?php echo esc_attr( $text_align ); ?>;border: 1px solid #e0e0e0;padding: 10px 20px;font-size: 14px;font-weight: 500;color: #636363;">
							<?php echo wp_kses_post( $total['label'] ); ?>
						</th>
						<td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>;border: 1px solid #e0e0e0;padding: 10px 20px;font-size: 14px;font-weight: 500;color: #636363;">
							<?php echo wp_kses_post( $total['value'] ); ?>
						</td>
					</tr>
					<?php
				endforeach;
			endif;
			?>
		</tfoot>
	</table>
</div>

<?php


// Customer note
if ( $order->get_customer_note() ) :
	?>
	<div style="margin-top: 20px;">
		<h3><?php esc_html_e( 'Kunde Notat', 'woocommerce' ); ?></h3>
		<p><?php echo wp_kses_post( nl2br( wptexturize( $order->get_customer_note() ) ) ); ?></p>
	</div>
	<?php
endif;



//echo "<pre>"; print_r($author); die;

?>
