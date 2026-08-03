<?php
/**
 * RamzPal support for WooCommerce Cart and Checkout Blocks.
 *
 * @package RamzPal_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Expose the classic gateway settings to the Blocks checkout registry.
 */
final class Ramzpal_Gateway_Blocks_Support extends AbstractPaymentMethodType {

	/** @var string */
	protected $name = 'ramzpal';

	/** @var WC_Gateway_Ramzpal */
	private $gateway;

	/**
	 * Load settings and gateway availability.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_ramzpal_settings', array() );
		$this->gateway  = new WC_Gateway_Ramzpal();
	}

	/**
	 * Only expose the block payment method when the gateway is configured.
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}

	/**
	 * Register the dependency-free frontend integration.
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'ramzpal-wc-checkout-blocks',
			RAMZPAL_WC_URL . 'assets/js/checkout-blocks.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
			RAMZPAL_WC_VERSION,
			true
		);

		return array( 'ramzpal-wc-checkout-blocks' );
	}

	/**
	 * Data consumed by checkout-blocks.js.
	 */
	public function get_payment_method_data() {
		return array(
			'title'       => $this->gateway->title,
			'description' => $this->gateway->description,
			'icon'        => RAMZPAL_WC_URL . 'assets/images/ramzpal-logo.webp',
			'supports'    => array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ),
		);
	}
}
