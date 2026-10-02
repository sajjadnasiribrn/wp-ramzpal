=== Ramzpal Payment Gateway for WooCommerce ===
Contributors: ramzpal
Tags: woocommerce, payment gateway, usdt, tether, crypto payments
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.7
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Accept USDT payments through Ramzpal in WooCommerce, with Persian settings, payment verification and support for classic and block checkout.

== Description ==

Accept USDT payments on BNB Smart Chain (BEP20) in your WooCommerce store with Ramzpal. Customers pay on the hosted payment page, and the plugin verifies the payment with Ramzpal before marking the order as paid.

The plugin is free and open source. A Ramzpal merchant account and payment credits are required to use the payment service. The service has a fixed charge per successful payment; see the current pricing before enabling the gateway.

* [Official website](https://ramzpal.com/)
* [Plugin download and setup](https://ramzpal.com/developers/wordpress-plugin)
* [API documentation](https://ramzpal.com/developers/docs)
* [Source code and issue tracker](https://github.com/sajjadnasiribrn/wp-ramzpal)
* [Pricing and settlement](https://ramzpal.com/pricing)

= Features =

* Accept USDT on BNB Smart Chain (BEP20).
* Support classic checkout, Checkout Blocks and HPOS.
* Convert store prices to USDT using a merchant-defined fixed rate.
* Choose Persian, English or Arabic for the hosted payment page.
* Use Persian gateway settings and optional technical logs.

The final payment amount must be between 1 and 5,000 USDT. For stores priced in toman, rial or another currency, enter the price of one USDT in the gateway settings and keep it up to date.

Persian setup instructions are available on the [official setup page](https://ramzpal.com/woocommerce-usdt).

== External services ==

This plugin connects to the Ramzpal payment service at https://ramzpal.com. The connection is needed to create and verify a USDT payment. It is used when a customer selects this gateway at checkout, when the payment callback is processed, and when an unpaid order is rechecked on its order-received page. Enabling the plugin alone does not create a payment.

To create a payment, the plugin sends the USDT amount, the store callback URL and the payment-page language to /api/v1/payment/request. The callback URL includes the WooCommerce order ID and order key so the payment can be matched to the order. To verify it, the plugin sends the payment ID and expected amount to /api/v1/payment/verify. Requests authenticate with the merchant API key and include the store URL in the HTTP User-Agent. The plugin does not send customer names, email addresses, billing addresses or wallet private keys in its default payment payload. Custom code that uses the plugin's filters may change that payload.

The customer leaves the store to complete payment on Ramzpal's hosted payment page. Ramzpal receives payment and network data as described in its privacy notice. Blockchain transfers are public and cannot normally be reversed.

* [Service terms](https://ramzpal.com/terms)
* [Privacy notice](https://ramzpal.com/privacy)
* [Security and asset custody](https://ramzpal.com/security)

== Installation ==

1. Install and activate WooCommerce, then upload and activate this plugin.
2. Go to WooCommerce > Settings > Payments > Ramzpal.
3. Sign in to Ramzpal, create a merchant and copy its API key from merchant management.
4. Paste only the API key into the API key field.
5. Choose the amount calculation mode, enter a conversion rate if needed and enable the gateway.
6. Test a small payment from checkout through payment confirmation before accepting customer payments.

== Frequently Asked Questions ==

= Where do I get an API key? =

After signing in to Ramzpal, each merchant's key is available in merchant management. If you do not have a merchant yet, create one at https://ramzpal.com/profile/merchant/create.

= Do I need to register a callback URL? =

No. The plugin builds the callback URL and sends it with the payment request.

= Can I use a store priced in toman or rial? =

Yes. Select the fixed-rate conversion mode and enter the price of one USDT in your store's currency. Update this value whenever your chosen rate changes.

= Why is the gateway missing at checkout? =

Check that the gateway is enabled, an API key is present and the currency or conversion rate is configured correctly. The final amount must be between 1 and 5,000 USDT.

= Where can I find logs? =

Enable technical logging in the gateway settings, then open WooCommerce > Status > Logs and select the ramzpal source.

== Changelog ==

= 1.0.7 =

* Provide the directory readme in English, with a link to Persian setup instructions.
* Test plugin registration and settings on WordPress 7.1 and WooCommerce 11.1.

= 1.0.6 =

* Clarify the external payment service, transmitted fields, privacy and pricing.
* Add official website, setup, documentation and source links.
* Use an English directory name and five focused tags; the gateway settings remain in Persian.


= 1.0.5 =

* Remove the optional order_id field from new payment requests.
* Support callbacks and verification without order_id while preserving older orders.
* Fix guest payment retries after the payment link expires.

= 1.0.4 =

* Validate callback order keys, payment IDs and order IDs.
* Handle the duplicate-order response from the Ramzpal API.

= 1.0.3 =

* Read the payment redirect URL from the API response's data object.

= 1.0.2 =

* Send payment requests and verification as multipart/form-data, as required by the API.

= 1.0.1 =

* Simplify the setup instructions, gateway settings and checkout copy.

= 1.0.0 =

* Initial release with Request and Verify API integration.
* Support classic checkout, Checkout Blocks and HPOS.
* Add amount conversion, payment-page languages, configurable messages and safe logging.
