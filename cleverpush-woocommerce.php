<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Integration_CleverPush extends WC_Integration
{

    public function __construct()
    {
        add_action('woocommerce_add_to_cart', array($this, 'add_to_cart'), 10, 6);

        add_action('cleverpush_check_if_product_bought', array($this, 'check_if_product_bought'), 10, 4);
    }

    public function add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
    {
        $sessionHandler = new WC_Session_Handler();
        list($customer_id) = $sessionHandler->get_session_cookie(); // customer_id = session_key
        if (!empty($customer_id)) {
            wp_schedule_single_event( time() + 60 * intval(get_option('cleverpush_woocommerce_notification_minutes', 30)), 'cleverpush_check_if_product_bought', array($customer_id, $product_id, $quantity, $variation_id) ); // check again in 5min
        }
    }

    public function check_if_product_bought($customer_id, $product_id, $quantity, $variation_id)
    {
        $cache_key = 'woocommerce_customer_' . $customer_id;
        if (wp_cache_get($cache_key, 'cleverpush') == 'sent') {
            return;
        }

        $sessionHandler = new WC_Session_Handler();
        $session = $sessionHandler->get_session($customer_id);
        if (!empty($session) && !empty($session['cart']) && !empty($session['cleverpush_subscription_id'])) { // still items in cart
            $product = wc_get_product($product_id);
            if ($product) {
                $title = $product->get_title();
                $emoji = json_decode('"\ud83d\uded2"');
                $body = $emoji. ' ' . get_option('cleverpush_woocommerce_notification_text', 'Wir haben noch etwas in deinem Warenkorb gefunden.');
                $url = WC_Cart::get_cart_url();
                $attachment_image = wp_get_attachment_image_src(get_post_thumbnail_id($product_id), 'single-post-thumbnail' );
                $iconUrl = $attachment_image[0];
                $subscriptionId = $session['cleverpush_subscription_id'];

                $cart = unserialize($session['cart']);
                if (count($cart) > 1) {
                    $title = get_bloginfo('name');
                    $iconUrl = null;
                }

                CleverPush_Api::send_notification($title, $body, $url, $iconUrl, $subscriptionId);

                wp_cache_set($cache_key, 'cleverpush', 60 * intval(get_option('cleverpush_woocommerce_notification_minutes', 30)));
            }
        }
    }
}
