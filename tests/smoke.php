<?php
/**
 * Dependency-free smoke tests for amount conversion and API normalization.
 */

define( 'ABSPATH', __DIR__ );
define( 'RAMZPAL_WC_VERSION', '1.0.0-test' );
define( 'RAMZPAL_WC_URL', 'https://merchant.example/wp-content/plugins/ramzpal/' );
define( 'MINUTE_IN_SECONDS', 60 );

final class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code, $message, $data = array() ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}

	public function get_error_data() {
		return $this->data;
	}
}

class WC_Payment_Gateway {
	public $id;
	public $method_title;
	public $method_description;
	public $icon;
	public $has_fields;
	public $supports = array();
	public $title;
	public $description;
	public $enabled;
	public $form_fields = array();
	public $settings = array();

	public function init_settings() {
		global $ramzpal_test_settings;
		$this->settings = $ramzpal_test_settings;
	}

	public function get_option( $key, $default = '' ) {
		return array_key_exists( $key, $this->settings ) ? $this->settings[ $key ] : $default;
	}

	public function is_available() {
		return 'yes' === $this->enabled;
	}

	public function supports( $feature ) {
		return in_array( $feature, $this->supports, true );
	}

	public function admin_options() {}
	public function process_admin_options() {}
}

function __( $text ) { return $text; }
function add_action() {}
function apply_filters( $hook, $value ) { return $value; }
function do_action() {}
function esc_url( $url ) { return $url; }
function esc_url_raw( $url ) { return $url; }
function wp_unslash( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function get_woocommerce_currency() { global $ramzpal_test_currency; return $ramzpal_test_currency; }
function wc_format_decimal( $number, $dp = false ) {
	$number = (float) str_replace( ',', '.', (string) $number );
	return false === $dp ? (string) $number : number_format( $number, (int) $dp, '.', '' );
}
function home_url() { return 'https://merchant.example/'; }
function untrailingslashit( $value ) { return rtrim( $value, '/' ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_generate_uuid4() { return '12345678-1234-4000-8000-123456789abc'; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code( $response ) { return $response['status']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function wc_get_logger() { return null; }

function wp_safe_remote_post( $url, $args ) {
	global $ramzpal_test_http_response, $ramzpal_test_http_request;
	$ramzpal_test_http_request = array( 'url' => $url, 'args' => $args );
	return $ramzpal_test_http_response;
}

final class Ramzpal_Test_Order {
	private $total;
	private $currency;
	private $meta;
	public $paid = false;
	public $notes = array();

	public function __construct( $total, $currency, $meta = array() ) {
		$this->total    = $total;
		$this->currency = $currency;
		$this->meta     = $meta;
	}

	public function get_total() { return $this->total; }
	public function get_currency() { return $this->currency; }
	public function get_meta( $key ) { return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : ''; }
	public function get_order_key() { return 'wc_order_key_test'; }
	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
	public function save() {}
	public function payment_complete() { $this->paid = true; }
	public function add_order_note( $note ) { $this->notes[] = $note; }
	public function is_paid() { return $this->paid; }
}

$ramzpal_test_settings = array(
	'enabled'          => 'yes',
	'api_key'          => 'secret-key',
	'pricing_mode'     => 'native',
	'amount_precision' => '2',
);
$ramzpal_test_currency = 'USDT';

require_once dirname( __DIR__ ) . '/includes/class-ramzpal-api-client.php';
require_once dirname( __DIR__ ) . '/includes/class-wc-gateway-ramzpal.php';

$failures = array();
$check    = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$gateway = new WC_Gateway_Ramzpal();
$check( '12.35' === $gateway->calculate_payment_amount( new Ramzpal_Test_Order( '12.345', 'USDT' ) ), 'USDT rounding failed.' );
$check( is_wp_error( $gateway->calculate_payment_amount( new Ramzpal_Test_Order( '12', 'IRR' ) ) ), 'Native mode accepted IRR.' );
$check( 'opaque%key' === $gateway->validate_api_key_field( 'api_key', ' opaque%key ' ), 'Opaque API key was altered.' );

$ramzpal_test_settings['pricing_mode']     = 'manual';
$ramzpal_test_settings['exchange_rate']    = '100000';
$ramzpal_test_settings['amount_precision'] = '4';
$ramzpal_test_currency                     = 'IRT';
$gateway = new WC_Gateway_Ramzpal();
$check( '100.0000' === $gateway->calculate_payment_amount( new Ramzpal_Test_Order( '10000000', 'IRT' ) ), 'Manual conversion failed.' );
$check( is_wp_error( $gateway->calculate_payment_amount( new Ramzpal_Test_Order( '600000000', 'IRT' ) ) ), 'Maximum amount was not enforced.' );

$client = new Ramzpal_API_Client( 'opaque%key' );
$ramzpal_test_http_response = array(
	'status' => 200,
	'body'   => '{"data":{"success":true,"payment_id":"pay_test","redirect_url":"https://ramzpal.com/pay/test"}}',
);
$result = $client->create_payment( array( 'amount' => 10 ) );
$check( ! is_wp_error( $result ) && 'pay_test' === $result['payment_id'], 'Successful API response was not parsed.' );
$check( 'https://ramzpal.com/pay/test' === $result['redirect_url'], 'Nested API response was not normalized.' );
$check( 'https://ramzpal.com/api/v1/payment/request' === $ramzpal_test_http_request['url'], 'Request endpoint is incorrect.' );
$check( 'Bearer opaque%key' === $ramzpal_test_http_request['args']['headers']['Authorization'], 'Bearer header is incorrect.' );
$check( 0 === strpos( $ramzpal_test_http_request['args']['headers']['Content-Type'], 'multipart/form-data; boundary=' ), 'Request is not multipart/form-data.' );
$check( false !== strpos( $ramzpal_test_http_request['args']['body'], 'name="amount"' . "\r\n\r\n" . '10' ), 'Multipart amount field is missing.' );
$check( false !== strpos( $ramzpal_test_http_request['args']['body'], '--' . substr( $ramzpal_test_http_request['args']['headers']['Content-Type'], strlen( 'multipart/form-data; boundary=' ) ) . '--' ), 'Multipart body is not closed.' );
$check( true === $ramzpal_test_http_request['args']['sslverify'], 'TLS verification is not enabled.' );

$ramzpal_test_http_response = array( 'status' => 401, 'body' => '{"message":"Unauthorized"}' );
$result = $client->verify_payment( array( 'payment_id' => 'pay_test', 'amount' => 10 ) );
$check( is_wp_error( $result ) && 'ramzpal_http_401' === $result->get_error_code(), 'HTTP 401 was not normalized.' );

$ramzpal_test_http_response = array( 'status' => 200, 'body' => '<html>bad response</html>' );
$result = $client->create_payment( array( 'amount' => 10 ) );
$check( is_wp_error( $result ) && 'ramzpal_invalid_response' === $result->get_error_code(), 'Invalid JSON was not rejected.' );

$missing_key_result = ( new Ramzpal_API_Client( '' ) )->create_payment( array() );
$check( is_wp_error( $missing_key_result ) && 'ramzpal_missing_api_key' === $missing_key_result->get_error_code(), 'Missing API key was not rejected.' );

$verification_order = new Ramzpal_Test_Order(
	'10000000',
	'IRT',
	array(
		WC_Gateway_Ramzpal::META_EXTERNAL_ORDER => 'wc-1-42',
	)
);
$ramzpal_test_http_response = array(
	'status' => 200,
	'body'   => '{"success":true,"status":"VERIFIED","payment_id":"pay_verified","order_id":"wc-1-42","amount":100,"tx_ids":["0xabc","0xabc"]}',
);
$verify_method = new ReflectionMethod( WC_Gateway_Ramzpal::class, 'verify_and_complete_order' );
$verify_method->setAccessible( true );
$verified = $verify_method->invoke( $gateway, $verification_order, 'pay_verified', '100.0000' );
$check( ! is_wp_error( $verified ) && $verification_order->paid, 'Valid verification did not complete the order.' );
$check( array( '0xabc' ) === $verification_order->get_meta( WC_Gateway_Ramzpal::META_TRANSACTION_IDS ), 'Transaction IDs were not normalized.' );

$callback_order = new Ramzpal_Test_Order(
	'10000000',
	'IRT',
	array(
		WC_Gateway_Ramzpal::META_PAYMENT_ID    => 'pay_verified',
		WC_Gateway_Ramzpal::META_EXTERNAL_ORDER => 'wc-1-42',
	)
);
$callback_method = new ReflectionMethod( WC_Gateway_Ramzpal::class, 'validate_callback_context' );
$callback_method->setAccessible( true );
$valid_callback = $callback_method->invoke( $gateway, $callback_order, 'pay_verified', 'wc-1-42', 'wc_order_key_test', 'true' );
$check( true === $valid_callback, 'Valid callback context was rejected.' );
$missing_key_callback = $callback_method->invoke( $gateway, $callback_order, 'pay_verified', 'wc-1-42', '', 'true' );
$check( is_wp_error( $missing_key_callback ) && 'ramzpal_callback_invalid_key' === $missing_key_callback->get_error_code(), 'Callback without an order key was accepted.' );
$wrong_order_callback = $callback_method->invoke( $gateway, $callback_order, 'pay_verified', 'wc-1-99', 'wc_order_key_test', 'true' );
$check( is_wp_error( $wrong_order_callback ) && 'ramzpal_callback_order_mismatch' === $wrong_order_callback->get_error_code(), 'Callback with a mismatched order ID was accepted.' );
$failed_callback = $callback_method->invoke( $gateway, $callback_order, 'pay_verified', 'wc-1-42', 'wc_order_key_test', 'false' );
$check( is_wp_error( $failed_callback ) && 'ramzpal_callback_unsuccessful' === $failed_callback->get_error_code(), 'Unsuccessful callback was accepted.' );

$ramzpal_test_http_response['body'] = '{"success":true,"status":"VERIFIED","payment_id":"pay_wrong","order_id":"wc-1-42","amount":100,"tx_ids":[]}';
$mismatch_order = new Ramzpal_Test_Order( '10000000', 'IRT', array( WC_Gateway_Ramzpal::META_EXTERNAL_ORDER => 'wc-1-42' ) );
$mismatch = $verify_method->invoke( $gateway, $mismatch_order, 'pay_verified', '100.0000' );
$check( is_wp_error( $mismatch ) && ! $mismatch_order->paid, 'Mismatched payment ID completed the order.' );

$ramzpal_test_http_response = array( 'status' => 422, 'body' => '{"message":"this payment exists."}' );
$duplicate = $client->create_payment( array( 'amount' => 10 ) );
$check( is_wp_error( $duplicate ) && 'برای این سفارش قبلاً یک پرداخت فعال ساخته شده است.' === $duplicate->get_error_message(), 'Punctuated duplicate-payment error was not normalized.' );

if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo "RamzPal smoke tests passed." . PHP_EOL;
