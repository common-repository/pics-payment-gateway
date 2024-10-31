=== Pics Payment Gateway ===
Contributors: pics
Donate link: https://pos.pics.lk
Tags: pics, online, payments, sri lanka
Requires at least: 3.0.1
Tested up to: 5.5
Requires PHP: 5.4.2
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Pics Payment Gateway Plugin for WooCommerce


== Description ==

Pics is a Sri Lankan Payment Gateway Service that enables you to accept payments online from your customers via Visa, MasterCard, Amex, eZcash, mCash & Internet Banking services. You can install this plugin to list Pics as a payment method in your WooCommerce store.


== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/plugin-name` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the WooCommerce->Settings->Checkout->Pics screen to configure the plugin with your Pics Merchant Account
4. Make sure you tick the Sandbox Mode checkbox if you want to test the plugin with your Pics Sandbox account


== Changelog ==

= 1.0.0 =
Initial public release


== Frequently Asked Questions ==

= How to sign up for a Pics Merchant Account? =

Go to Pics website & register for a Merchant Account.
https://payme.pics.lk/

= How to set callback URL? =
1. Login to your Merchant portal (https://payme.pics.lk/)
2. Browse to the domains section.
3. select your domain.
4. Click the ‘edit’ button next to the field ‘Callback URL’.
5. Fill up the popup form (https://{your-domain}/wc-api/wc_gateway_pics) and click on the ‘Update’ button.
Note : please make sure {your-domain} replace with your actual domain.
