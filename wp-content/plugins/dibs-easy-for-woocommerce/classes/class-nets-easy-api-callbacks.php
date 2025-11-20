<?php
/**
 * API Callback class
 *
 * @package DIBS_Easy/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Nets_Easy_Api_Callbacks class.
 *
 * @since 1.4.0
 *
 * Class that handles DIBS API callbacks.
 */
class Nets_Easy_Api_Callbacks {

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;
	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return self::$instance The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * DIBS_Api_Callbacks constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_dibs_api_callbacks', array( $this, 'payment_created_scheduler' ) );
		add_action( 'dibs_payment_created_callback', array( $this, 'execute_dibs_payment_created_callback' ), 10, 3 );
	}

	/**
	 * Handle scheduling of payment completed webhook.
	 */
	public function payment_created_scheduler() {
		

		

		// echo '<pre>';
		// print_r('payment_created_scheduler');
		// echo '</pre>';
		// die();
		$dibs_payment_created_callback = filter_input( INPUT_GET, 'dibs-payment-created-callback', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! empty( $dibs_payment_created_callback ) && '1' === $dibs_payment_created_callback ) {

			$post_body = file_get_contents( 'php://input' );
			// $post_body = '{"id":"ecee3fa19e8e41fea6a88fa53c7c4a79","merchantId":100021639,"timestamp":"2025-10-14T07:08:23.2469+00:00","event":"payment.charge.created","data":{"chargeId":"ecee3fa19e8e41fea6a88fa53c7c4a79","orderItems":[{"grossTotalAmount":80000,"name":"Dropin - Sarpsborg badstua","netTotalAmount":80000,"quantity":1.0,"reference":"37887","taxRate":0,"taxAmount":0,"unit":"pcs","unitPrice":80000},{"grossTotalAmount":20000,"name":"Total mva","netTotalAmount":20000,"quantity":1.0,"reference":"fee|total-mva","taxRate":0,"taxAmount":0,"unit":"pcs","unitPrice":20000}],"reservationId":"4f5a1c9bccd04864a50cbfad581213f4","reconciliationReference":"RGFdDaWAB4AR5uVcXmEoI4fLm","amount":{"amount":100000,"currency":"NOK"},"surchargeAmount":0,"paymentId":"42e1c143b76f4406b04357a54774748c"}}';
			$data      = json_decode( $post_body, true );

			// Log webhook data to file for debugging
			$log_data = array(
				'timestamp' => date('Y-m-d H:i:s')
			);
			
			$log_file = WP_CONTENT_DIR . '/nets-old-dibs-webhook-logs.txt';
			$log_entry = "[" . $log_data['timestamp'] . "] DIBS Webhook Data:\n";
			$log_entry .= "Raw Data: " . $post_body . "\n";
			$log_entry .= str_repeat('-', 80) . "\n\n";
			
			file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

			if(isset($data['event']) && $data['event'] == 'payment.charge.created'){

				if(isset($data['data']['chargeId']) && isset($data['data']['paymentId'])){
					Nets_Easy_Logger::log( 'Payment charges webhook listener hit ' . wp_json_encode( $data ) );
					$this->handle_payment_completed($data['data']['paymentId'],$data['data']['chargeId']);
				}
				exit;
			}
			die;
			

			// $amount       = $data['data']['order']['amount']['amount'];
			// $payment_id   = $data['data']['paymentId'];
			// $order_number = $data['data']['order']['reference'];

			// Nets_Easy_Logger::log( 'Payment created webhook listener hit ' . wp_json_encode( $data ) );

			// as_schedule_single_action( time() + 120, 'dibs_payment_created_callback', array( $payment_id, $order_number, $amount ) );
			// header( 'HTTP/1.1 200 OK' );
			// die();
		}
	}

	private function handle_payment_completed($payment_id,$charge_id) {

        

        global $wpdb;
        $order_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                '_dibs_payment_id',
                $payment_id
            )
        );

		if (!$order_id) {

			$order_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_id FROM $wpdb->postmeta WHERE meta_key LIKE %s AND meta_value = %s LIMIT 1",
					'_dibs_payment_id_data%',
					$payment_id
				)
			);

		}

        

        if (!$order_id) {
            error_log('DIBS Payment: Order not found for payment ID: ' . $payment_id);
            $this->return_error_response(404, 'Order not found');
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('DIBS Payment: Order not found: ' . $order_id);
            $this->return_error_response(404, 'Order not found');
            return;
        }

        $order->update_meta_data('_dibs_charge_id', $charge_id);
        
        $order->save();
        
        // Update order status
        $order->payment_complete();
        $order->update_status('completed', 'Payment completed via DIBS Nets Easy Payment by webhook');
        
        // Update booking status if exists
        $booking_table = $wpdb->prefix . "bookings_calendar";
        $sql = "UPDATE {$booking_table} SET status = 'paid' WHERE order_id = $order_id";
        $wpdb->query($sql);

        error_log('DIBS Payment: Order ' . $order_id . ' payment completed');
        return true;
        // $this->return_success_response('Payment completed successfully', [
        //     'order_id' => $order_id,
        //     'status' => 'processing'
        // ]);
    }

	/**
	 * Handle execution of payment created cronjob.
	 *
	 * @param string $payment_id Nets payment id.
	 * @param string $order_number WC order number.
	 * @param string $amount Nets order amount.
	 */
	public function execute_dibs_payment_created_callback( $payment_id, $order_number, $amount ) {

		Nets_Easy_Logger::log( 'Execute Payment created API callback. Payment ID:' . $payment_id . '. Order number: ' . $order_number . '. Amount: ' . $amount );

		$order = nets_easy_get_order_by_purchase_id( $payment_id );

		if ( empty( $order ) ) {
			Nets_Easy_Logger::log( 'No corresponding order ID was found for Payment ID ' . $payment_id );
			return;
		}

		// Maybe abort the callback (if the order already has been processed in Woo).
		if ( ! empty( $order->get_date_paid() ) ) {
			Nets_Easy_Logger::log( 'Aborting Payment created API callback. Order ' . $order->get_order_number() . '(order ID ' . $order->get_id() . ') already processed.' );
		} else {
			Nets_Easy_Logger::log( 'Order status not set correctly for order ' . $order->get_order_number() . ' during checkout process. Setting order status to Processing/Completed in API callback.' );
			wc_dibs_confirm_dibs_order( $order->get_id() );
			$this->check_order_totals( $order, $amount );
		}
	}

	/**
	 * Check order totals.
	 *
	 * @param object $order WC order.
	 * @param string $dibs_order_total Order total amount from Nets.
	 */
	public function check_order_totals( $order, $dibs_order_total ) {

		$order_totals_match = true;

		// Check order total and compare it with Woo.
		$woo_order_total = intval( round( $order->get_total() * 100 ) );

		if ( $woo_order_total > $dibs_order_total && ( $woo_order_total - $dibs_order_total ) > 30 ) {
			/* Translators: Nets order total. */
			$order->update_status( 'on-hold', sprintf( __( 'Order needs manual review. WooCommerce order total and Nexi order total do not match. Nexi order total: %s.', 'dibs-easy-for-woocommerce' ), $dibs_order_total ) );
			Nets_Easy_Logger::log( 'Order total mismatch in order:' . $order->get_order_number() . '. Woo order total: ' . $woo_order_total . '. Nexi order total: ' . $dibs_order_total );
			$order_totals_match = false;
		} elseif ( $dibs_order_total > $woo_order_total && ( $dibs_order_total - $woo_order_total ) > 30 ) {
			/* Translators: Nets order total. */
			$order->update_status( 'on-hold', sprintf( __( 'Order needs manual review. WooCommerce order total and Nexi order total do not match. Nexi order total: %s.', 'dibs-easy-for-woocommerce' ), $dibs_order_total ) );
			Nets_Easy_Logger::log( 'Order total mismatch in order:' . $order->get_order_number() . '. Woo order total: ' . $woo_order_total . '. Nexi order total: ' . $dibs_order_total );
			$order_totals_match = false;
		}

		return $order_totals_match;
	}
}
Nets_Easy_Api_Callbacks::get_instance();
