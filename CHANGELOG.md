# 1.0.7

- English directory readme with a link to the Persian setup guide.
- Compatibility smoke check on WordPress 7.1 / WooCommerce 11.1.
- No changes to payment behavior.

# 1.0.6

- Prepared directory metadata and external-service disclosures for WordPress.org.
- Added official setup, privacy, pricing and source links.
- Payment behavior is unchanged.

# تغییرات

## 1.0.5 — 2026-08-04

- حذف پارامتر اختیاری `order_id` از درخواست ساخت پرداخت
- شناسایی امن سفارش از `wc_order` و کلید سفارش در Callback
- پشتیبانی از پاسخ Verify بدون `order_id`
- حفظ سازگاری Callback و Verify برای پرداخت‌های قدیمی دارای `order_id`
- امکان تلاش مجدد پس از پایان عمر لینک پرداخت، از جمله برای کاربران مهمان

## 1.0.4 — 2026-08-04

- اعتبارسنجی کامل Callback شامل کلید سفارش، `payment_id`، `order_id` و نتیجه موفق
- جلوگیری از Verify برای Callback نامعتبر یا ناموفق
- پشتیبانی از پیام نقطه‌دار `this payment exists.` در API واقعی
- افزودن تست‌های رگرسیون امنیتی برای Callback

## 1.0.3 — 2026-08-04

- اصلاح پردازش پاسخ واقعی API که اطلاعات پرداخت را داخل فیلد `data` برمی‌گرداند
- افزودن تست رگرسیون برای پاسخ‌های پوشش‌دار و حفظ سازگاری با پاسخ‌های قدیمی

## 1.0.2 — 2026-08-04

- اصلاح فرمت بدنه درخواست‌های ساخت و تأیید پرداخت به `multipart/form-data`
- افزودن تست رگرسیون برای فرمت درخواست ارسالی به API رمزپال

## 1.0.1 — 2026-08-03

- بازنویسی متن معرفی، راهنمای نصب و تنظیمات با لحن ساده‌تر
- ساده‌تر شدن توضیحات فنی برای مدیران فروشگاه

## 1.0.0 — 2026-08-02

- ایجاد درگاه پرداخت USDT رمزپال برای ووکامرس
- اتصال امن به endpointهای Request و Verify با WordPress HTTP API
- بررسی مبلغ، سفارش، شناسه پرداخت و وضعیت VERIFIED پس از بازگشت مشتری
- پشتیبانی از Checkout کلاسیک، Checkout Blocks و HPOS
- پشتیبانی از USDT و تبدیل نرخ ثابت برای سایر واحدهای پول
- رابط مدیریت فارسی، پیام‌های قابل تنظیم، لوگوی رسمی و گزارش فنی امن
- بسته‌ساز انتشار و کنترل کیفیت خودکار
