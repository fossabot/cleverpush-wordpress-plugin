=== CleverPush ===
Contributors: CleverPush
Plugin Name: CleverPush
Plugin URI: https://cleverpush.com
Tags: push notifications, web push, browser notifications, woocommerce
Requires at least: 2.7
Tested up to: 4.7
Stable tag: 0.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

CleverPush lets you send browser push notifications to your users in the simplest way possible.

== Description ==
Installing the CleverPush plugin will automatically insert the CleverPush code into your WordPress site.

What is CleverPush?

CleverPush lets you send browser push notifications to your users in the simplest way possible.

== Installation ==
Extract the zip file and just drop the contents in the wp-content/plugins/ directory of your WordPress installation and then activate the Plugin from Plugins page.

== Frequently Asked Questions ==
= I can't see any code added to my header or footer when I view my page source =
Your theme needs to have the header and footer actions in place before the `</head>` and before the `</body>`

= If I use this plugin, do I need to enter any other code on my website? =
No, this plugin is sufficient by itself

== ChangeLog ==

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

Enter your CleverPush channel identifier in the given field.

== Adding it to your template ==

header code:
`<?php wp_head();?>`

footer code: 
`<?php wp_footer();?>`
