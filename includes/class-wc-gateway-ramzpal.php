<?php
/**
 * RamzPal WooCommerce payment gateway.
 *
 * @package RamzPal_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Payment_Gateway' ) && ! class_exists( 'WC_Gateway_Ramzpal' ) ) {

	/**
	 * Redirect gateway for USDT payments through RamzPal.
	 */
	final class WC_Gateway_Ramzpal extends WC_Payment_Gateway {

		const META_PAYMENT_ID       = '_ramzpal_payment_id';
		const META_PAYMENT_AMOUNT   = '_ramzpal_payment_amount';
		const META_REDIRECT_URL     = '_ramzpal_redirect_url';
		const META_REQUESTED_AT     = '_ramzpal_requested_at';
		const META_EXTERNAL_ORDER   = '_ramzpal_order_id';
		const META_TRANSACTION_IDS  = '_ramzpal_tx_ids';

		/** @var string */
		private $api_key;

		/** @var string */
		private $pricing_mode;

		/** @var string */
		private $exchange_rate;

		/** @var int */
		private $amount_precision;

		/** @var string */
		private $payment_language;

		/** @var bool */
		private $debug_enabled;

		/** @var string */
		private $success_message;

		/** @var string */
		private $failure_message;

		/**
		 * Set up gateway fields and lifecycle hooks.
		 */
		public function __construct() {
			$this->id                 = 'ramzpal';
			$this->method_title       = __( 'رمزپال', 'ramzpal-payment-gateway-for-woocommerce' );
			$this->method_description = __( 'با رمزپال، پرداخت با تتر را به فروشگاه ووکامرسی خود اضافه کنید.', 'ramzpal-payment-gateway-for-woocommerce' );
			$this->icon               = apply_filters( 'ramzpal_wc_gateway_icon', RAMZPAL_WC_URL . 'assets/images/ramzpal-logo.webp' );
			$this->has_fields         = false;
			$this->supports           = array( 'products' );

			$this->init_form_fields();
			$this->init_settings();

			$this->title             = $this->get_option( 'title', __( 'پرداخت با تتر (رمزپال)', 'ramzpal-payment-gateway-for-woocommerce' ) );
			$this->description       = $this->get_option( 'description', __( 'مبلغ سفارش را با تتر و از طریق درگاه رمزپال پرداخت کنید.', 'ramzpal-payment-gateway-for-woocommerce' ) );
			$this->enabled           = $this->get_option( 'enabled', 'no' );
			$this->api_key           = $this->get_option( 'api_key', '' );
			$this->pricing_mode      = $this->get_option( 'pricing_mode', 'native' );
			$this->exchange_rate     = $this->get_option( 'exchange_rate', '' );
			$this->amount_precision  = max( 2, min( 6, absint( $this->get_option( 'amount_precision', '2' ) ) ) );
			$this->payment_language  = $this->get_option( 'payment_language', 'auto' );
			$this->debug_enabled     = 'yes' === $this->get_option( 'debug', 'no' );
			$this->success_message   = $this->get_option( 'success_message', __( 'پرداخت شما با موفقیت تأیید شد. شناسه پرداخت: {payment_id}', 'ramzpal-payment-gateway-for-woocommerce' ) );
			$this->failure_message   = $this->get_option( 'failure_message', __( 'پرداخت تأیید نشد: {fault} لطفاً دوباره تلاش کنید یا با پشتیبانی فروشگاه تماس بگیرید.', 'ramzpal-payment-gateway-for-woocommerce' ) );

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_api_wc_gateway_ramzpal', array( $this, 'handle_callback' ) );
			add_action( 'admin_notices', array( $this, 'configuration_notice' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}

		/**
		 * Gateway settings shown in WooCommerce.
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'            => array(
					'title'   => __( 'فعال‌سازی', 'ramzpal-payment-gateway-for-woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'درگاه پرداخت رمزپال فعال باشد', 'ramzpal-payment-gateway-for-woocommerce' ),
					'default' => 'no',
				),
				'title'              => array(
					'title'       => __( 'عنوان درگاه', 'ramzpal-payment-gateway-for-woocommerce' ),
					'type'        => 'text',
					'description' => __( 'عنوانی که مشتری هنگام انتخاب روش پرداخت می‌بیند.', 'ramzpal-payment-gateway-for-woocommerce' ),
					'default'     => __( 'پرداخت با تتر (رمزپال)', 'ramzpal-payment-gateway-for-woocommerce' ),
					'desc_tip'    => true,
				),
				'description'        => array(
					'title'       => __( 'توضیح کوتاه', 'ramzpal-payment-gateway-for-woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'در صفحه تسویه‌حساب زیر نام درگاه نمایش داده می‌شود.', 'ramzpal-payment-gateway-for-woocommerce' ),
					'default'     => __( 'مبلغ سفارش را با تتر و از طریق درگاه رمزپال پرداخت کنید.', 'ramzpal-payment-gateway-for-woocommerce' ),
					'desc_tip'    => true,
				),
				'api_key'            => array(
					'title'       => __( 'کلید API', 'ramzpal-payment-gateway-for-woocommerce' ),
					'type'        => 'password',
					'description' => sprintf(
						/* translators: 1: login link, 2: merchant creation link. */
						__( 'ابتدا <a href="%1$s" target="_blank" rel="noopener noreferrer">وارد پنل رمزپال</a> شوید. اگر هنوز پذیرنده ندارید، <a href="%2$s" target="_blank" rel="noopener noreferrer">یک پذیرنده بسازید</a>. سپس از بخش مدیریت پذیرنده‌ها، کلید API همان پذیرنده را کپی و اینجا وارد کنید.', 'ramzpal-payment-gateway-for-woocommerce' ),
						esc_url( 'https://ramzpal.com/login' ),
						esc_url( 'https://ramzpal.com/profile/merchant/create' )
					),
					'default'     => '',
				),
				'pricing_mode'       => array(
					'title'       => __( 'محاسبه مبلغ USDT', 'ramzpal-payment-gateway-for-woocommerce' ),
					'type'        => 'select',
					'description' => __( 'اگر قیمت محصولات با تتر یا دلار است، حالت یک‌به‌یک را انتخاب کنید. برای تومان، ریال یا واحدهای دیگر از نرخ تبدیل استفاده کنید.', 'ramzpal-payment-gateway-for-woocommerce' ),
					'default'     => 'native',
					'options'     => array(
						'native' => __( 'یک‌به‌یک (فقط USDT یا USD)', 'ramzpal-payment-gateway-for-woocommerce' ),
						'manual' => __( 'تبدیل با نرخ ثابت', 'ramzpal-payment-gateway-for-woocommerce' ),
					),
				),
				'exchange_rate'      => array(
					'title'             => __( 'قیمت هر ۱ USDT', 'ramzpal-payment-gateway-for-woocommerce' ),
					'type'              => 'decimal',
					'description'       => __( 'قیمت یک تتر بر اساس واحد پول فروشگاه را وارد کنید. برای نمونه، اگر هر تتر ۱۰۰٬۰۰۰ تومان است، عدد 100000 را بنویسید. هر زمان نرخ تغییر کرد، این عدد را هم به‌روز کنید.', 'ramzpal-payment-gateway-for-woocommerce' ),
					'default'           => '',
					'custom_attributes' => array(
						'min'  => '0.000001',
						'step' => 'any',
					),
				),
				'amount_precision'   => array(
					'title'       => __( 'دقت مبلغ USDT', 'ramzpal-payment-gateway-for-woocommerce' ),
					'type'        => 'select',
					'description' => __( 'تعداد رقم اعشار هنگام تبدیل مبلغ. عدد بیشتر خطای گرد کردن را کمتر می‌کند.', 'ramzpal-payment-gateway-for-woocommerce' ),
					'default'     => '2',
					'options'     => array(
						'2' => __( '۲ رقم اعشار', 'ramzpal-payment-gateway-for-woocommerce' ),
						'4' => __( '۴ رقم اعشار', 'ramzpal-payment-gateway-for-woocommerce' ),
						'6' => __( '۶ رقم اعشار', 'ramzpal-payment-gateway-for-woocommerce' ),
					),
				),
				'payment_language'   => array(
					'title'       => __( 'زبان صفحه پرداخت', 'ramzpal-payment-gateway-for-woocommerce' ),
					'type'        => 'select',
					'description' => __( 'در حالت خودکار، زبان از زبان سفارش ووکامرس تشخیص داده می‌شود.', 'ramzpal-payment-gateway-for-woocommerce' ),
					'default'     => 'auto',
					'options'     => array(
						'auto' => __( 'خودکار', 'ramzpal-payment-gateway-for-woocommerce' ),
						'fa'   => __( 'فارسی', 'ramzpal-payment-gateway-for-woocommerce' ),
						'en'   => __( 'English', 'ramzpal-payment-gateway-for-woocommerce' ),
						'ar'   => __( 'العربية', 'ramzpal-payment-gateway-for-woocommerce' ),
					),
				),
				'success_message'    => array(
					'title'       => __( 'پیام پرداخت موفق', 'ramzpal-payment-gateway-for-woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'جای‌نگهدارهای مجاز: {payment_id} و {tx_ids}', 'ramzpal-payment-gateway-for-woocommerce' ),
					'default'     => __( 'پرداخت شما با موفقیت تأیید شد. شناسه پرداخت: {payment_id}', 'ramzpal-payment-gateway-for-woocommerce' ),
				),
				'failure_message'    => array(
					'title'       => __( 'پیام پرداخت ناموفق', 'ramzpal-payment-gateway-for-woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'برای نمایش دلیل قابل فهم خطا از {fault} استفاده کنید.', 'ramzpal-payment-gateway-for-woocommerce' ),
					'default'     => __( 'پرداخت تأیید نشد: {fault} لطفاً دوباره تلاش کنید یا با پشتیبانی فروشگاه تماس بگیرید.', 'ramzpal-payment-gateway-for-woocommerce' ),
				),
				'debug'              => array(
					'title'       => __( 'گزارش فنی', 'ramzpal-payment-gateway-for-woocommerce' ),
					'type'        => 'checkbox',
					'label'       => __( 'ثبت رویدادهای فنی در گزارش‌های ووکامرس', 'ramzpal-payment-gateway-for-woocommerce' ),
					'description' => __( 'فقط هنگام عیب‌یابی فعال کنید. کلید API، آدرس بازگشت و اطلاعات شخصی مشتری ثبت نمی‌شوند.', 'ramzpal-payment-gateway-for-woocommerce' ),
					'default'     => 'no',
				),
			);
		}

		/**
		 * A polished introduction above the native settings table.
		 */
		public function admin_options() {
			?>
			<div class="ramzpal-settings-hero" dir="rtl">
				<img src="<?php echo esc_url( RAMZPAL_WC_URL . 'assets/images/ramzpal-logo.webp' ); ?>" alt="<?php esc_attr_e( 'رمزپال', 'ramzpal-payment-gateway-for-woocommerce' ); ?>">
				<div>
					<h2><?php esc_html_e( 'درگاه رمزپال را در چند دقیقه راه‌اندازی کنید', 'ramzpal-payment-gateway-for-woocommerce' ); ?></h2>
					<p><?php esc_html_e( 'برای شروع، کلید API را وارد و روش محاسبه مبلغ را انتخاب کنید. بقیه تنظیمات آماده است.', 'ramzpal-payment-gateway-for-woocommerce' ); ?></p>
					<ol class="ramzpal-settings-steps">
						<li><?php esc_html_e( 'در پنل رمزپال یک پذیرنده ایجاد کنید.', 'ramzpal-payment-gateway-for-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'API Key پذیرنده را در فیلد «کلید API» قرار دهید.', 'ramzpal-payment-gateway-for-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'محاسبه مبلغ را تنظیم و یک پرداخت کم‌مبلغ آزمایش کنید.', 'ramzpal-payment-gateway-for-woocommerce' ); ?></li>
					</ol>
					<p class="ramzpal-settings-links">
						<a href="https://ramzpal.com/profile/merchant/create" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'ساخت پذیرنده و دریافت API Key', 'ramzpal-payment-gateway-for-woocommerce' ); ?></a>
						<a href="https://ramzpal.com/developers/docs" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'مستندات API', 'ramzpal-payment-gateway-for-woocommerce' ); ?></a>
						<a href="https://t.me/ramzpal_support" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'پشتیبانی رمزپال', 'ramzpal-payment-gateway-for-woocommerce' ); ?></a>
					</p>
				</div>
			</div>
			<?php
			parent::admin_options();
		}

		/**
		 * Preserve every valid character in the opaque API key.
		 */
		public function validate_api_key_field( $key, $value ) {
			unset( $key );
			return trim( (string) wp_unslash( $value ) );
		}

		/**
		 * Only display the gateway when it can actually start a payment.
		 */
		public function is_available() {
			if ( ! parent::is_available() || '' === trim( $this->api_key ) ) {
				return false;
			}

			$currency = get_woocommerce_currency();
			if ( 'native' === $this->pricing_mode && ! in_array( $currency, array( 'USDT', 'USD' ), true ) ) {
				return false;
			}
			if ( 'manual' === $this->pricing_mode && (float) $this->exchange_rate <= 0 ) {
				return false;
			}

			return true;
		}

		/**
		 * Create a RamzPal payment and hand the redirect URL back to WooCommerce.
		 *
		 * @param int $order_id WooCommerce order ID.
		 * @return array
		 */
		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				wc_add_notice( __( 'سفارش برای شروع پرداخت پیدا نشد.', 'ramzpal-payment-gateway-for-woocommerce' ), 'error' );
				return array( 'result' => 'failure' );
			}

			$amount = $this->calculate_payment_amount( $order );
			if ( is_wp_error( $amount ) ) {
				wc_add_notice( $amount->get_error_message(), 'error' );
				$order->add_order_note(
					sprintf(
						/* translators: %s: readable validation error. */
						__( 'رمزپال: %s', 'ramzpal-payment-gateway-for-woocommerce' ),
						$amount->get_error_message()
					)
				);
				return array( 'result' => 'failure' );
			}

			$reusable_url = $this->get_reusable_redirect_url( $order, $amount );
			if ( $reusable_url ) {
				return array( 'result' => 'success', 'redirect' => $reusable_url );
			}

			$recovered = $this->recover_existing_payment( $order, $amount );
			if ( is_array( $recovered ) ) {
				wc_add_notice( __( 'پرداخت قبلی این سفارش در رمزپال تأیید شد.', 'ramzpal-payment-gateway-for-woocommerce' ), 'success' );
				return array( 'result' => 'success', 'redirect' => $this->get_return_url( $order ) );
			}
			if ( is_wp_error( $recovered ) ) {
				wc_add_notice( $recovered->get_error_message(), 'error' );
				$order->add_order_note(
					sprintf(
						/* translators: %s: readable verification error. */
						__( 'رمزپال: بررسی پرداخت قبلی ناموفق بود — %s', 'ramzpal-payment-gateway-for-woocommerce' ),
						$recovered->get_error_message()
					)
				);
				return array( 'result' => 'failure' );
			}

			$external_order_id = $this->get_external_order_id( $order );
			$callback_url      = add_query_arg(
				array(
					'wc_order' => $order->get_id(),
					'key'      => $order->get_order_key(),
				),
				WC()->api_request_url( 'wc_gateway_ramzpal' )
			);
			$callback_url      = (string) apply_filters( 'ramzpal_wc_callback_url', $callback_url, $order );

			$payload = array(
				'amount'       => (float) $amount,
				'callback_url' => $callback_url,
				'order_id'     => $external_order_id,
				'language'     => $this->resolve_language(),
			);
			$payload = (array) apply_filters( 'ramzpal_wc_payment_payload', $payload, $order );

			$response = $this->api()->create_payment( $payload );
			if ( is_wp_error( $response ) ) {
				$message = $response->get_error_message();
				wc_add_notice( $message, 'error' );
				$order->add_order_note(
					sprintf(
						/* translators: %s: readable API error. */
						__( 'رمزپال: ایجاد پرداخت ناموفق بود — %s', 'ramzpal-payment-gateway-for-woocommerce' ),
						$message
					)
				);
				do_action( 'ramzpal_wc_payment_request_failed', $order, $response );
				return array( 'result' => 'failure' );
			}

			$payment_id  = isset( $response['payment_id'] ) ? sanitize_text_field( $response['payment_id'] ) : '';
			$redirect_url = isset( $response['redirect_url'] ) ? esc_url_raw( $response['redirect_url'] ) : '';

			if ( empty( $response['success'] ) || '' === $payment_id || ! $this->is_valid_payment_url( $redirect_url ) ) {
				$message = __( 'رمزپال لینک معتبر پرداخت را برنگرداند. لطفاً دوباره تلاش کنید.', 'ramzpal-payment-gateway-for-woocommerce' );
				wc_add_notice( $message, 'error' );
				$order->add_order_note(
					sprintf(
						/* translators: %s: readable validation error. */
						__( 'رمزپال: %s', 'ramzpal-payment-gateway-for-woocommerce' ),
						$message
					)
				);
				return array( 'result' => 'failure' );
			}

			$order->update_meta_data( self::META_PAYMENT_ID, $payment_id );
			$order->update_meta_data( self::META_PAYMENT_AMOUNT, $amount );
			$order->update_meta_data( self::META_REDIRECT_URL, $redirect_url );
			$order->update_meta_data( self::META_REQUESTED_AT, time() );
			$order->update_meta_data( self::META_EXTERNAL_ORDER, $external_order_id );
			$order->save();
			$order->add_order_note(
				sprintf(
					/* translators: 1: payment ID, 2: USDT amount. */
					__( 'درخواست پرداخت رمزپال ساخته شد. شناسه: %1$s — مبلغ: %2$s USDT', 'ramzpal-payment-gateway-for-woocommerce' ),
					$payment_id,
					$amount
				)
			);

			do_action( 'ramzpal_wc_payment_created', $order, $payment_id, $amount, $response );

			return array( 'result' => 'success', 'redirect' => $redirect_url );
		}

		/**
		 * Verify callback data against the API before completing an order.
		 */
		public function handle_callback() {
			$external_order_id = isset( $_GET['order_id'] ) ? sanitize_text_field( wp_unslash( $_GET['order_id'] ) ) : '';
			$order_id          = isset( $_GET['wc_order'] ) ? absint( wp_unslash( $_GET['wc_order'] ) ) : 0;
			$order_id          = $order_id ? $order_id : $this->order_id_from_external_id( $external_order_id );
			$order             = $order_id ? wc_get_order( $order_id ) : false;
			$payment_id        = isset( $_GET['payment_id'] ) ? sanitize_text_field( wp_unslash( $_GET['payment_id'] ) ) : '';
			$provided_key      = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
			$callback_success  = isset( $_GET['success'] ) ? strtolower( wc_clean( wp_unslash( $_GET['success'] ) ) ) : '';

			if ( ! $order ) {
				$this->callback_error( __( 'سفارش مرتبط با این پرداخت پیدا نشد.', 'ramzpal-payment-gateway-for-woocommerce' ), 400 );
			}

			$callback_validation = $this->validate_callback_context( $order, $payment_id, $external_order_id, $provided_key, $callback_success );
			if ( is_wp_error( $callback_validation ) ) {
				$order->add_order_note( 'رمزپال: ' . $callback_validation->get_error_message() );
				if ( 'ramzpal_callback_invalid_key' === $callback_validation->get_error_code() ) {
					$this->callback_error( $callback_validation->get_error_message(), 403 );
				}
				$this->redirect_failure( $order, $callback_validation->get_error_message() );
			}

			$expected_payment_id = (string) $order->get_meta( self::META_PAYMENT_ID, true );

			if ( $order->is_paid() ) {
				$this->redirect_success( $order, $expected_payment_id, $this->get_transaction_ids( $order ) );
			}

			$amount = (string) $order->get_meta( self::META_PAYMENT_AMOUNT, true );
			if ( '' === $amount ) {
				$this->redirect_failure( $order, __( 'مبلغ اولیه پرداخت در سفارش ثبت نشده است.', 'ramzpal-payment-gateway-for-woocommerce' ) );
			}

			$verification = $this->verify_and_complete_order( $order, $expected_payment_id, $amount );
			if ( is_wp_error( $verification ) ) {
				$order->add_order_note(
					sprintf(
						/* translators: %s: readable verification error. */
						__( 'رمزپال: تأیید پرداخت ناموفق بود — %s', 'ramzpal-payment-gateway-for-woocommerce' ),
						$verification->get_error_message()
					)
				);
				do_action( 'ramzpal_wc_payment_verification_failed', $order, $verification );
				$this->redirect_failure( $order, $verification->get_error_message() );
			}

			$this->redirect_success( $order, $expected_payment_id, $verification['transaction_ids'] );
		}

		/**
		 * Verify a previously-created payment whose redirect link is no longer reusable.
		 *
		 * This closes the gap where the provider callback did not reach the store even
		 * though the customer completed the payment.
		 *
		 * @param WC_Order $order  Order object.
		 * @param string   $amount Current calculated amount.
		 * @return array|WP_Error|false
		 */
		private function recover_existing_payment( $order, $amount ) {
			$payment_id  = (string) $order->get_meta( self::META_PAYMENT_ID, true );
			$saved_amount = (string) $order->get_meta( self::META_PAYMENT_AMOUNT, true );

			if ( '' === $payment_id || $saved_amount !== (string) $amount || $order->is_paid() ) {
				return false;
			}

			$result = $this->verify_and_complete_order( $order, $payment_id, $saved_amount );
			if ( ! is_wp_error( $result ) ) {
				return $result;
			}

			$data        = $result->get_error_data();
			$api_message = is_array( $data ) && isset( $data['message'] ) ? $data['message'] : '';
			if ( 'payment not completed' === $api_message || 'ramzpal_http_404' === $result->get_error_code() ) {
				return false;
			}

			return $result;
		}

		/**
		 * Validate every callback value that was fixed when the payment was created.
		 *
		 * Verify remains authoritative for the final payment state, but malformed or
		 * unsuccessful callbacks must never reach it.
		 *
		 * @param WC_Order $order             Order object.
		 * @param string   $payment_id        Provider payment ID from callback.
		 * @param string   $external_order_id Merchant order ID from callback.
		 * @param string   $provided_key      WooCommerce order key from callback URL.
		 * @param string   $callback_success  Provider success flag.
		 * @return true|WP_Error
		 */
		private function validate_callback_context( $order, $payment_id, $external_order_id, $provided_key, $callback_success ) {
			if ( '' === $provided_key || ! hash_equals( (string) $order->get_order_key(), (string) $provided_key ) ) {
				return new WP_Error( 'ramzpal_callback_invalid_key', __( 'نشانی بازگشت این سفارش معتبر نیست.', 'ramzpal-payment-gateway-for-woocommerce' ) );
			}

			$expected_payment_id = (string) $order->get_meta( self::META_PAYMENT_ID, true );
			if ( '' === $payment_id || '' === $expected_payment_id || ! hash_equals( $expected_payment_id, (string) $payment_id ) ) {
				return new WP_Error( 'ramzpal_callback_payment_mismatch', __( 'شناسه پرداخت بازگشتی معتبر نیست.', 'ramzpal-payment-gateway-for-woocommerce' ) );
			}

			$expected_order_id = (string) $order->get_meta( self::META_EXTERNAL_ORDER, true );
			if ( '' === $external_order_id || '' === $expected_order_id || ! hash_equals( $expected_order_id, (string) $external_order_id ) ) {
				return new WP_Error( 'ramzpal_callback_order_mismatch', __( 'شناسه سفارش بازگشتی معتبر نیست.', 'ramzpal-payment-gateway-for-woocommerce' ) );
			}

			if ( ! in_array( $callback_success, array( 'true', '1' ), true ) ) {
				return new WP_Error( 'ramzpal_callback_unsuccessful', __( 'رمزپال پرداخت را موفق اعلام نکرده است.', 'ramzpal-payment-gateway-for-woocommerce' ) );
			}

			return true;
		}

		/**
		 * Verify all immutable payment fields and complete the WooCommerce order.
		 *
		 * @param WC_Order $order      Order object.
		 * @param string   $payment_id Expected provider payment ID.
		 * @param string   $amount     Stored USDT amount.
		 * @return array|WP_Error
		 */
		private function verify_and_complete_order( $order, $payment_id, $amount ) {
			$response = $this->api()->verify_payment(
				array(
					'payment_id' => $payment_id,
					'amount'     => (float) $amount,
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$verified_payment_id = isset( $response['payment_id'] ) ? sanitize_text_field( $response['payment_id'] ) : '';
			$verified_order_id   = isset( $response['order_id'] ) ? sanitize_text_field( $response['order_id'] ) : '';
			$verified_status     = isset( $response['status'] ) ? strtoupper( sanitize_text_field( $response['status'] ) ) : '';
			$verified_amount     = isset( $response['amount'] ) ? (float) $response['amount'] : -1;
			$expected_order_id   = (string) $order->get_meta( self::META_EXTERNAL_ORDER, true );
			$tolerance           = pow( 10, -1 * $this->amount_precision ) / 2;

			$is_valid = ! empty( $response['success'] )
				&& 'VERIFIED' === $verified_status
				&& hash_equals( (string) $payment_id, $verified_payment_id )
				&& hash_equals( $expected_order_id, $verified_order_id )
				&& abs( (float) $amount - $verified_amount ) <= $tolerance;

			if ( ! $is_valid ) {
				return new WP_Error(
					'ramzpal_verification_mismatch',
					__( 'اطلاعات تأیید پرداخت با سفارش یکسان نیست.', 'ramzpal-payment-gateway-for-woocommerce' )
				);
			}

			$transaction_ids = $this->sanitize_transaction_ids( isset( $response['tx_ids'] ) ? $response['tx_ids'] : array() );
			$order->update_meta_data( self::META_TRANSACTION_IDS, $transaction_ids );
			$order->save();
			$order->payment_complete( $payment_id );
			$order->add_order_note(
				sprintf(
					/* translators: 1: payment ID, 2: blockchain transaction IDs. */
					__( 'پرداخت رمزپال با موفقیت تأیید شد. شناسه پرداخت: %1$s%2$s', 'ramzpal-payment-gateway-for-woocommerce' ),
					$payment_id,
					empty( $transaction_ids )
						? ''
						: sprintf(
							/* translators: %s: comma-separated blockchain transaction IDs. */
							__( ' — شناسه شبکه: %s', 'ramzpal-payment-gateway-for-woocommerce' ),
							implode( '، ', $transaction_ids )
						)
				)
			);

			do_action( 'ramzpal_wc_payment_verified', $order, $payment_id, $transaction_ids, $response );

			return array(
				'transaction_ids' => $transaction_ids,
				'response'        => $response,
			);
		}

		/**
		 * Convert an order total to an API-compatible USDT amount.
		 *
		 * @param WC_Order $order Order object.
		 * @return string|WP_Error
		 */
		public function calculate_payment_amount( $order ) {
			$total    = (float) $order->get_total( 'edit' );
			$currency = strtoupper( (string) $order->get_currency() );

			if ( 'native' === $this->pricing_mode ) {
				if ( ! in_array( $currency, array( 'USDT', 'USD' ), true ) ) {
					return new WP_Error( 'ramzpal_unsupported_currency', __( 'برای پرداخت یک‌به‌یک، واحد پول فروشگاه باید USDT یا USD باشد.', 'ramzpal-payment-gateway-for-woocommerce' ) );
				}
				$amount = $total;
			} else {
				$rate = (float) wc_format_decimal( $this->exchange_rate );
				if ( $rate <= 0 ) {
					return new WP_Error( 'ramzpal_invalid_rate', __( 'نرخ تبدیل USDT در تنظیمات رمزپال معتبر نیست.', 'ramzpal-payment-gateway-for-woocommerce' ) );
				}
				$amount = $total / $rate;
			}

			$amount = (float) apply_filters( 'ramzpal_wc_payment_amount', $amount, $order, $this->pricing_mode, $this->exchange_rate );
			$amount = wc_format_decimal( round( $amount, $this->amount_precision ), $this->amount_precision, false );

			if ( ! is_numeric( $amount ) || (float) $amount < 1 || (float) $amount > 5000 ) {
				return new WP_Error( 'ramzpal_amount_out_of_range', __( 'مبلغ قابل پرداخت باید بین ۱ تا ۵۰۰۰ تتر باشد.', 'ramzpal-payment-gateway-for-woocommerce' ) );
			}

			return $amount;
		}

		/**
		 * Show actionable configuration issues to store managers.
		 */
		public function configuration_notice() {
			if ( 'yes' !== $this->enabled || ! current_user_can( 'manage_woocommerce' ) ) {
				return;
			}

			$message = '';
			if ( '' === trim( $this->api_key ) ) {
				$message = __( 'درگاه رمزپال فعال است، اما کلید API وارد نشده است.', 'ramzpal-payment-gateway-for-woocommerce' );
			} elseif ( 'native' === $this->pricing_mode && ! in_array( get_woocommerce_currency(), array( 'USDT', 'USD' ), true ) ) {
				$message = __( 'درگاه رمزپال روی حالت یک‌به‌یک است، اما واحد پول فروشگاه USDT یا USD نیست.', 'ramzpal-payment-gateway-for-woocommerce' );
			} elseif ( 'manual' === $this->pricing_mode && (float) $this->exchange_rate <= 0 ) {
				$message = __( 'درگاه رمزپال روی تبدیل دستی است، اما قیمت هر USDT وارد نشده است.', 'ramzpal-payment-gateway-for-woocommerce' );
			}

			if ( '' === $message ) {
				return;
			}

			$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ramzpal' );
			echo '<div class="notice notice-warning"><p>';
			echo esc_html( $message ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'اصلاح تنظیمات', 'ramzpal-payment-gateway-for-woocommerce' ) . '</a>';
			echo '</p></div>';
		}

		/**
		 * Load settings-page styling only on this gateway's screen.
		 */
		public function enqueue_admin_assets() {
			$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
			$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
			if ( 'wc-settings' !== $page || 'ramzpal' !== $section ) {
				return;
			}

			wp_enqueue_style( 'ramzpal-wc-admin', RAMZPAL_WC_URL . 'assets/css/admin.css', array(), RAMZPAL_WC_VERSION );
		}

		/**
		 * Build an API client from the current settings.
		 */
		private function api() {
			return new Ramzpal_API_Client( $this->api_key, $this->debug_enabled );
		}

		/**
		 * Use an unexpired payment link when checkout is submitted twice.
		 */
		private function get_reusable_redirect_url( $order, $amount ) {
			$requested_at = absint( $order->get_meta( self::META_REQUESTED_AT, true ) );
			$saved_amount = (string) $order->get_meta( self::META_PAYMENT_AMOUNT, true );
			$url          = (string) $order->get_meta( self::META_REDIRECT_URL, true );

			if ( $requested_at < time() - ( 14 * MINUTE_IN_SECONDS ) || $saved_amount !== (string) $amount ) {
				return false;
			}

			return $this->is_valid_payment_url( $url ) ? $url : false;
		}

		/**
		 * Validate an external HTTPS payment URL.
		 */
		private function is_valid_payment_url( $url ) {
			if ( ! is_string( $url ) || 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) {
				return false;
			}

			return (bool) wp_http_validate_url( $url );
		}

		/**
		 * Generate a merchant-scoped, non-PII order identifier.
		 */
		private function get_external_order_id( $order ) {
			return 'wc-' . get_current_blog_id() . '-' . $order->get_id();
		}

		/**
		 * Recover the order when a provider does not preserve callback query args.
		 */
		private function order_id_from_external_id( $external_id ) {
			$external_id = sanitize_text_field( $external_id );
			if ( ! preg_match( '/^wc-(\d+)-(\d+)$/', $external_id, $matches ) ) {
				return 0;
			}
			if ( (int) $matches[1] !== (int) get_current_blog_id() ) {
				return 0;
			}
			return absint( $matches[2] );
		}

		/**
		 * Resolve supported checkout language from gateway settings/site locale.
		 */
		private function resolve_language() {
			if ( in_array( $this->payment_language, array( 'fa', 'en', 'ar' ), true ) ) {
				return $this->payment_language;
			}

			$locale   = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
			$language = strtolower( substr( (string) $locale, 0, 2 ) );
			return in_array( $language, array( 'fa', 'en', 'ar' ), true ) ? $language : 'en';
		}

		/**
		 * Sanitize blockchain transaction IDs before storing or displaying them.
		 */
		private function sanitize_transaction_ids( $ids ) {
			if ( ! is_array( $ids ) ) {
				return array();
			}
			$ids = array_map( 'sanitize_text_field', $ids );
			$ids = array_filter( array_unique( $ids ) );
			return array_values( $ids );
		}

		/**
		 * Read stored transaction IDs consistently.
		 */
		private function get_transaction_ids( $order ) {
			return $this->sanitize_transaction_ids( $order->get_meta( self::META_TRANSACTION_IDS, true ) );
		}

		/**
		 * Complete browser callback with a success notice and redirect.
		 */
		private function redirect_success( $order, $payment_id, array $transaction_ids ) {
			if ( WC()->cart ) {
				WC()->cart->empty_cart();
			}
			$message = strtr(
				$this->success_message,
				array(
					'{payment_id}' => $payment_id,
					'{tx_ids}'     => implode( '، ', $transaction_ids ),
				)
			);
			if ( WC()->session ) {
				wc_add_notice( wp_kses_post( $message ), 'success' );
			}

			wp_safe_redirect( $this->get_return_url( $order ) );
			exit;
		}

		/**
		 * Keep the order retryable and return the shopper to its payment page.
		 */
		private function redirect_failure( $order, $fault ) {
			$message = str_replace( '{fault}', $fault, $this->failure_message );
			if ( WC()->session ) {
				wc_add_notice( wp_kses_post( $message ), 'error' );
			}

			wp_safe_redirect( $order->get_checkout_payment_url( true ) );
			exit;
		}

		/**
		 * Stop malformed callbacks without exposing internals.
		 */
		private function callback_error( $message, $status ) {
			wp_die(
				esc_html( $message ),
				esc_html__( 'بازگشت نامعتبر رمزپال', 'ramzpal-payment-gateway-for-woocommerce' ),
				array( 'response' => absint( $status ) )
			);
		}
	}
}
