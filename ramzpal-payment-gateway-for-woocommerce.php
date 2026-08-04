<?php
/**
 * Plugin Name: درگاه پرداخت رمزپال برای ووکامرس
 * Plugin URI: https://ramzpal.com/developers/docs
 * Description: درگاه پرداخت تتر رمزپال برای ووکامرس، با نصب ساده و تنظیمات فارسی.
 * Version: 1.0.5
 * Author: RamzPal
 * Author URI: https://ramzpal.com
 * Text Domain: ramzpal-payment-gateway-for-woocommerce
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 7.6
 * WC tested up to: 10.9
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package RamzPal_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'RAMZPAL_WC_VERSION', '1.0.5' );
define( 'RAMZPAL_WC_FILE', __FILE__ );
define( 'RAMZPAL_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'RAMZPAL_WC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare compatibility before WooCommerce initializes its feature system.
 */
function ramzpal_wc_declare_compatibility() {
	if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'ramzpal_wc_declare_compatibility' );

/**
 * Load and register the gateway after WooCommerce is available.
 */
function ramzpal_wc_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once RAMZPAL_WC_PATH . 'includes/class-ramzpal-api-client.php';
	require_once RAMZPAL_WC_PATH . 'includes/class-wc-gateway-ramzpal.php';

	add_filter(
		'woocommerce_payment_gateways',
		static function ( $gateways ) {
			$gateways[] = 'WC_Gateway_Ramzpal';
			return $gateways;
		}
	);
}
add_action( 'plugins_loaded', 'ramzpal_wc_init', 11 );

/**
 * Register the Checkout Block integration.
 */
function ramzpal_wc_register_blocks_support() {
	if ( ! class_exists( '\\Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType' ) ) {
		return;
	}

	require_once RAMZPAL_WC_PATH . 'includes/class-wc-gateway-ramzpal-blocks.php';

	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		static function ( $registry ) {
			$registry->register( new Ramzpal_Gateway_Blocks_Support() );
		}
	);
}
add_action( 'woocommerce_blocks_loaded', 'ramzpal_wc_register_blocks_support' );

/**
 * Add USDT to WooCommerce's currency list.
 *
 * Stores that use another currency can enable the manual conversion setting.
 */
function ramzpal_wc_add_usdt_currency( $currencies ) {
	$currencies['USDT'] = __( 'تتر (USDT)', 'ramzpal-payment-gateway-for-woocommerce' );
	return $currencies;
}
add_filter( 'woocommerce_currencies', 'ramzpal_wc_add_usdt_currency' );

/**
 * Display a compact USDT symbol.
 */
function ramzpal_wc_usdt_symbol( $symbol, $currency ) {
	return 'USDT' === $currency ? '₮' : $symbol;
}
add_filter( 'woocommerce_currency_symbol', 'ramzpal_wc_usdt_symbol', 10, 2 );

/**
 * Show a dependency notice on older WordPress installations.
 */
function ramzpal_wc_missing_woocommerce_notice() {
	if ( class_exists( 'WooCommerce' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'برای استفاده از درگاه رمزپال، ابتدا افزونه ووکامرس را نصب و فعال کنید.', 'ramzpal-payment-gateway-for-woocommerce' );
	echo '</p></div>';
}
add_action( 'admin_notices', 'ramzpal_wc_missing_woocommerce_notice' );

/**
 * Add a direct settings link on the Plugins page.
 */
function ramzpal_wc_plugin_action_links( $links ) {
	$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ramzpal' );
	array_unshift(
		$links,
		'<a href="' . esc_url( $url ) . '">' . esc_html__( 'تنظیمات', 'ramzpal-payment-gateway-for-woocommerce' ) . '</a>'
	);
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ramzpal_wc_plugin_action_links' );
