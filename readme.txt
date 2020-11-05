=== Payment Gateway for Mopé on WooCommerce ===
Contributors: vokality
Tags: woocommerce, mope, webshop
Requires at least: 5.1
Tested up to: 5.5.3
Requires PHP: 7.2
Stable tag: 1.0.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A Mopé Gateway Plugin for WooCommerce

== Description ==

# Payment Gateway for Mopé on WooCommerce
Seamlessly accept payments from any Mopé wallet on your web shop.

## Requirements
- WordPress
- WooCommerce
- Mopé API Keys


## Getting started
- Go to `WooCommerce` > `Settings` > `Payments`, then enable `Mopé Payment Gateway`
- Click on `manage` to customize the gateway and provide your API keys


## Bugs & Feature requests
If you've found a bug or have a feature request, you can create an [issue](https://github.com/Vokality/mope-php/issues)


== Installation ==
## Manual installation

1. Upload `mope.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > Settings > Payments and enable Mopé Payment Gateway
4. Then, click on `manage` to add customize the gateway and provide your API keys


== Screenshots ==
1. Update your gateway settings
2. Shoppers can select Mopé as a payment option during checkout


== Changelog ==
= 1.0.4 =
* use change WC_API callback structure
* use $order->get_checkout_order_received_url() instead of hardcoded URL

= 1.0.3 =
* This fixes a regression caused by 1.0.2 which constantly caused requests to Mopé to error when issuing a payment request.

= 1.0.2 =
* Prevents a bug from showing up when the response from Mopé is non-200 when creating a payment request.

= 1.0.1 =
* Improve readme.txt file
* Add screenshots and plugin icon

= 1.0.0 =
* initial release