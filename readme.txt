=== Ramzpal Payment Gateway for WooCommerce ===
Contributors: ramzpal
Tags: woocommerce, payment gateway, usdt, tether, crypto payments
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.6
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

درگاه پرداخت تتر رمزپال برای ووکامرس؛ با نصب ساده، تنظیمات فارسی و پشتیبانی از نسخه‌های جدید ووکامرس.

== Description ==

Accept USDT payments on BNB Smart Chain (BEP20) in your WooCommerce store with Ramzpal. Customers pay on the hosted payment page, and the plugin verifies the payment with Ramzpal before marking the order as paid.

The plugin is free and open source. A Ramzpal merchant account and payment credits are required to use the payment service. The service has a fixed charge per successful payment; see the current pricing before enabling the gateway.

* [Official website](https://ramzpal.com/)
* [Plugin download and setup](https://ramzpal.com/developers/wordpress-plugin)
* [API documentation](https://ramzpal.com/developers/docs)
* [Source code and issue tracker](https://github.com/sajjadnasiribrn/wp-ramzpal)
* [Pricing and settlement](https://ramzpal.com/pricing)

= راهنمای فارسی =

با این افزونه مشتری می‌تواند مبلغ سفارش را با تتر پرداخت کند. پس از ثبت سفارش، مشتری به صفحه رمزپال می‌رود و بعد از پرداخت به فروشگاه برمی‌گردد. افزونه نتیجه را بررسی می‌کند و وضعیت سفارش را خودکار به‌روز نگه می‌دارد.

امکانات اصلی:

* پرداخت USDT روی شبکه BEP20
* پشتیبانی از صفحه پرداخت کلاسیک و Checkout Blocks
* سازگاری با HPOS
* تبدیل مبلغ با نرخ ثابت برای واحدهای غیر USDT
* رابط و راهنمای فارسی
* زبان پرداخت فارسی، انگلیسی یا عربی
* گزارش فنی اختیاری برای پیدا کردن خطاها

مبلغ نهایی پرداخت باید بین ۱ تا ۵۰۰۰ تتر باشد. اگر قیمت محصولات با تومان، ریال یا واحد دیگری ثبت شده، کافی است قیمت یک تتر را در تنظیمات درگاه وارد کنید.

== External services ==

This plugin connects to the Ramzpal payment service at https://ramzpal.com. The connection is needed to create and verify a USDT payment. It is used when a customer selects this gateway at checkout, when the payment callback is processed, and when an unpaid order is rechecked on its order-received page. Enabling the plugin alone does not create a payment.

To create a payment, the plugin sends the USDT amount, the store callback URL and the payment-page language to /api/v1/payment/request. The callback URL includes the WooCommerce order ID and order key so the payment can be matched to the order. To verify it, the plugin sends the payment ID and expected amount to /api/v1/payment/verify. Requests authenticate with the merchant API key and include the store URL in the HTTP User-Agent. The plugin does not send customer names, email addresses, billing addresses or wallet private keys in its default payment payload. Custom code that uses the plugin's filters may change that payload.

The customer leaves the store to complete payment on Ramzpal's hosted payment page. Ramzpal receives payment and network data as described in its privacy notice. Blockchain transfers are public and cannot normally be reversed.

* [Service terms](https://ramzpal.com/terms)
* [Privacy notice](https://ramzpal.com/privacy)
* [Security and asset custody](https://ramzpal.com/security)

== Installation ==

1. فایل ZIP افزونه را از پیشخوان وردپرس بارگذاری و فعال کنید.
2. به «ووکامرس ← پیکربندی ← پرداخت‌ها ← رمزپال» بروید.
3. وارد پنل رمزپال شوید، یک پذیرنده بسازید و API Key آن را از بخش مدیریت پذیرنده‌ها کپی کنید.
4. فقط خود API Key را در فیلد «کلید API» وارد کنید.
5. روش محاسبه مبلغ را انتخاب و درگاه را فعال کنید.
6. یک خرید کم‌مبلغ را از ابتدا تا صفحه سفارش موفق آزمایش کنید.

== Frequently Asked Questions ==

= کلید API را از کجا دریافت کنم؟ =

پس از ورود به پنل رمزپال، کلید هر پذیرنده در بخش مدیریت پذیرنده‌ها در دسترس است.

فقط خود API Key را در تنظیمات وارد کنید. اگر هنوز پذیرنده ندارید، ابتدا از نشانی https://ramzpal.com/profile/merchant/create یک پذیرنده بسازید.

= لازم است آدرس بازگشت را جایی ثبت کنم؟ =

خیر. افزونه آدرس بازگشت را خودکار می‌سازد و همراه درخواست پرداخت می‌فرستد.

= فروشگاه من تومان یا ریال است؛ آیا افزونه کار می‌کند؟ =

بله. حالت «تبدیل با نرخ ثابت» را انتخاب کنید و قیمت یک تتر را بر اساس واحد فروشگاه وارد کنید. هر زمان نرخ تغییر کرد، این عدد را هم به‌روز کنید.

= چرا درگاه در تسویه‌حساب دیده نمی‌شود؟ =

فعال بودن درگاه، وجود کلید API و تنظیم درست واحد پول/نرخ تبدیل را بررسی کنید. مبلغ نهایی نیز باید بین ۱ تا ۵۰۰۰ USDT باشد.

= لاگ‌ها کجا هستند؟ =

پس از فعال کردن گزارش فنی، در «ووکامرس ← وضعیت ← گزارش‌ها» منبع ramzpal را انتخاب کنید.

== Changelog ==

= 1.0.6 =

* Clarify the external payment service, transmitted fields, privacy and pricing.
* Add official website, setup, documentation and source links.
* Use an English directory name and five focused tags; the gateway settings remain in Persian.


= 1.0.5 =

* حذف پارامتر اختیاری order_id از درخواست ساخت پرداخت
* پشتیبانی از Callback و Verify بدون order_id همراه با حفظ سازگاری سفارش‌های قدیمی
* اصلاح تلاش مجدد کاربران مهمان پس از منقضی شدن لینک پرداخت

= 1.0.4 =

* سخت‌گیری امنیتی در اعتبارسنجی Callback و تطبیق کلید، شناسه پرداخت و شناسه سفارش
* اصلاح پردازش پیام سفارش تکراری API رمزپال

= 1.0.3 =

* اصلاح خواندن لینک پرداخت از پوشش data در پاسخ واقعی API رمزپال

= 1.0.2 =

* اصلاح ارسال درخواست ساخت و تأیید پرداخت با فرمت multipart/form-data مورد انتظار API رمزپال

= 1.0.1 =

* بازنویسی توضیحات و راهنمای نصب با لحن ساده‌تر
* ساده‌تر شدن متن تنظیمات درگاه و صفحه پرداخت

= 1.0.0 =

* انتشار نخست
* اتصال Request و Verify به API رمزپال
* Checkout کلاسیک، Checkout Blocks و HPOS
* تبدیل مبلغ، زبان پرداخت، پیام‌های قابل تنظیم و لاگ امن
