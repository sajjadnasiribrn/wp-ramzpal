<?php
/**
 * HTTP client for the RamzPal API.
 *
 * @package RamzPal_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Small, testable wrapper around the WordPress HTTP API.
 */
final class Ramzpal_API_Client {

	/** @var string */
	private $api_key;

	/** @var bool */
	private $debug;

	/**
	 * @param string $api_key Merchant API key.
	 * @param bool   $debug   Whether safe diagnostic logging is enabled.
	 */
	public function __construct( $api_key, $debug = false ) {
		$this->api_key = trim( (string) $api_key );
		$this->debug   = (bool) $debug;
	}

	/**
	 * Create a payment.
	 *
	 * @param array $payload Request payload.
	 * @return array|WP_Error
	 */
	public function create_payment( array $payload ) {
		return $this->post( '/api/v1/payment/request', $payload );
	}

	/**
	 * Verify a payment.
	 *
	 * @param array $payload Request payload.
	 * @return array|WP_Error
	 */
	public function verify_payment( array $payload ) {
		return $this->post( '/api/v1/payment/verify', $payload );
	}

	/**
	 * Send a JSON request and normalize all failures to WP_Error.
	 *
	 * @param string $path    API path.
	 * @param array  $payload Request payload.
	 * @return array|WP_Error
	 */
	private function post( $path, array $payload ) {
		if ( '' === $this->api_key ) {
			return new WP_Error( 'ramzpal_missing_api_key', __( 'کلید API رمزپال وارد نشده است.', 'ramzpal-payment-gateway-for-woocommerce' ) );
		}

		$base_url = (string) apply_filters( 'ramzpal_wc_api_base_url', 'https://ramzpal.com' );
		$url      = untrailingslashit( $base_url ) . $path;
		$boundary = '----RamzPalWooCommerce' . str_replace( '-', '', wp_generate_uuid4() );
		$args     = array(
			'method'      => 'POST',
			'timeout'     => 20,
			'redirection' => 0,
			'sslverify'   => true,
			'headers'     => array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
				'User-Agent'    => 'RamzPal-WooCommerce/' . RAMZPAL_WC_VERSION . '; ' . home_url( '/' ),
			),
			'body'        => $this->build_multipart_body( $payload, $boundary ),
		);

		$this->log( 'ارسال درخواست API', array( 'path' => $path, 'payload' => $payload ) );
		$response = wp_safe_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log( 'خطای ارتباط با API', array( 'path' => $path, 'error' => $response->get_error_message() ), 'error' );
			return new WP_Error(
				'ramzpal_connection_error',
				__( 'ارتباط امن با رمزپال برقرار نشد. لطفاً دوباره تلاش کنید.', 'ramzpal-payment-gateway-for-woocommerce' ),
				array( 'original_error' => $response->get_error_code() )
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = (string) wp_remote_retrieve_body( $response );
		$data   = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			$this->log( 'پاسخ نامعتبر API', array( 'path' => $path, 'status' => $status ), 'error' );
			return new WP_Error(
				'ramzpal_invalid_response',
				__( 'پاسخ دریافتی از رمزپال قابل پردازش نبود.', 'ramzpal-payment-gateway-for-woocommerce' ),
				array( 'status' => $status )
			);
		}

		$data = $this->normalize_response_data( $data );

		$this->log(
			'دریافت پاسخ API',
			array(
				'path'       => $path,
				'status'     => $status,
				'success'    => isset( $data['success'] ) ? (bool) $data['success'] : null,
				'payment_id' => isset( $data['payment_id'] ) ? sanitize_text_field( $data['payment_id'] ) : '',
				'api_status' => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : '',
			)
		);

		if ( $status < 200 || $status >= 300 ) {
			$message = $this->friendly_error_message( $status, isset( $data['message'] ) ? $data['message'] : '' );
			return new WP_Error(
				'ramzpal_http_' . $status,
				$message,
				array(
					'status'  => $status,
					'message' => isset( $data['message'] ) ? sanitize_text_field( $data['message'] ) : '',
				)
			);
		}

		return $data;
	}

	/**
	 * Unwrap the data envelope returned by the live RamzPal API.
	 *
	 * The public examples historically showed response fields at the root, so
	 * both shapes remain supported for backwards compatibility.
	 *
	 * @param array $data Decoded API response.
	 * @return array
	 */
	private function normalize_response_data( array $data ) {
		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$nested = $data['data'];
			unset( $data['data'] );
			$data = array_replace( $data, $nested );
		}

		return $data;
	}

	/**
	 * Encode request fields exactly as the RamzPal payment API expects them.
	 *
	 * @param array  $payload  Request payload.
	 * @param string $boundary Multipart boundary.
	 * @return string
	 */
	private function build_multipart_body( array $payload, $boundary ) {
		$body = '';

		foreach ( $payload as $name => $value ) {
			$name = preg_replace( '/[^A-Za-z0-9_.\[\]-]/', '', (string) $name );
			if ( '' === $name ) {
				continue;
			}

			if ( is_bool( $value ) ) {
				$value = $value ? '1' : '0';
			} elseif ( is_array( $value ) || is_object( $value ) ) {
				$value = wp_json_encode( $value );
			} elseif ( null === $value ) {
				$value = '';
			}

			$body .= '--' . $boundary . "\r\n";
			$body .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
			$body .= (string) $value . "\r\n";
		}

		return $body . '--' . $boundary . "--\r\n";
	}

	/**
	 * Convert public API failures to useful Persian messages.
	 *
	 * @param int    $status  HTTP status.
	 * @param string $message API message.
	 * @return string
	 */
	private function friendly_error_message( $status, $message ) {
		$message = rtrim( strtolower( trim( (string) $message ) ), '.' );

		if ( 401 === $status ) {
			return __( 'کلید API رمزپال معتبر نیست. تنظیمات درگاه را بررسی کنید.', 'ramzpal-payment-gateway-for-woocommerce' );
		}
		if ( 404 === $status || 'payment not found' === $message ) {
			return __( 'پرداخت موردنظر در رمزپال پیدا نشد.', 'ramzpal-payment-gateway-for-woocommerce' );
		}
		if ( 'this payment exists' === $message ) {
			return __( 'برای این سفارش قبلاً یک پرداخت فعال ساخته شده است.', 'ramzpal-payment-gateway-for-woocommerce' );
		}
		if ( 'amount mismatch' === $message ) {
			return __( 'مبلغ پرداخت با مبلغ سفارش یکسان نیست.', 'ramzpal-payment-gateway-for-woocommerce' );
		}
		if ( 'payment not completed' === $message ) {
			return __( 'پرداخت هنوز در شبکه کامل نشده است.', 'ramzpal-payment-gateway-for-woocommerce' );
		}

		return __( 'رمزپال درخواست را نپذیرفت. لطفاً اطلاعات سفارش و تنظیمات درگاه را بررسی کنید.', 'ramzpal-payment-gateway-for-woocommerce' );
	}

	/**
	 * Write redacted diagnostics to the WooCommerce logger.
	 *
	 * @param string $message Log message.
	 * @param array  $context Safe context without secrets or customer PII.
	 * @param string $level   Log level.
	 */
	private function log( $message, array $context = array(), $level = 'info' ) {
		if ( ! $this->debug || ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		if ( isset( $context['payload']['callback_url'] ) ) {
			$context['payload']['callback_url'] = '[redacted]';
		}

		$context['source'] = 'ramzpal';
		wc_get_logger()->log( $level, $message . ' ' . wp_json_encode( $context, JSON_UNESCAPED_UNICODE ), $context );
	}
}
