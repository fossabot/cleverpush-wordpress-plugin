=== CleverPush ===
Contributors: CleverPush
Plugin Name: CleverPush
Plugin URI: https://cleverpush.com
Tags: push notifications, web push, browser notifications, woocommerce
Requires at least: 2.7
Tested up to: 5.1
Stable tag: 0.7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

CleverPush lets you send browser push notifications to your users in the simplest way possible.

== Description ==
Installing the CleverPush plugin will automatically insert the CleverPush code into your WordPress site. It will also give you the possibility to create a notification when publishing a new post.
Another great thing is completely automated re-targeting for WooCommerce.

What is CleverPush?

CleverPush lets you send browser push notifications to your users in the simplest way possible.

== Installation ==
Extract the zip file and just drop the contents in the wp-content/plugins/ directory of your WordPress installation and then activate the Plugin from the Plugins page.

== Frequently Asked Questions ==
= I have activated the plugin but the confirmation prompt is now shown =
Please enter your API keys in the plugin's settings and select a channel. If no channel is available, you need to create one at cleverpush.com
If you are using any cache plugin, also be sure, to empty your cache.

== ChangeLog ==

= 0.7.4 =
* better error logs

= 0.7.3 =
* change default headline + text
* allow one notification per post per minute

= 0.7.2 =
* minor fix

= 0.7.1 =
* added post thumbnails to notifications
* allow for every post to send a notification only once
* multi topic & segment selection

= 0.7.0 =
* added ability to set custom headline and text when sending new notifications

= 0.6.4 =
* minor fix

= 0.6.3 =
* added assets/cleverpush-worker.js.php for usage on HTTPS sites

= 0.6.2 =
* fixed bug where notification was sent every time post was saved

= 0.6.1 =
* fix publish_post handler

= 0.6.0 =
* breaking: no direct WooCommerce integration anymore (should be used with follow-up campaigns now)
* fixed: don't triggerOptIn directly after first page view. Considering opt-in channel settings now.

= 0.5.1 =
* Bug fixes

= 0.5 =
* Added new JavaScript SDK code

= 0.4.1 =
* Hotfix for WooCommerce integration

= 0.4 =
* WooCommerce integration with automatic retargeting notifications.

= 0.3 =
* Ability to notifications directly in WordPress

= 0.2 =
* Multi language
* Simplify settings

= 0.1 =
* Initial release

== Configuration ==

Enter your CleverPush API keys in the given fields. You can find your API keys in the CleverPush backoffice under Settings > API.

