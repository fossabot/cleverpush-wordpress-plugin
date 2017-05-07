<?php
/*
Plugin Name: CleverPush
Plugin URI: https://cleverpush.com
Description: Send push notifications to your users right trough your website. Visit <a href="https://cleverpush.com">CleverPush</a> for more details.
Author: CleverPush
Version: 0.5
Author URI: https://cleverpush.com

This relies on the actions being present in the themes header.php and footer.php
* header.php code before the closing </head> tag
*   wp_head();
*
*/

if (!defined('ABSPATH')) {
    exit;
}

include_once 'cleverpush-api.php';

if ( ! class_exists( 'CleverPush' ) ) :
class CleverPush
{
    /**
     * Construct the plugin.
     */
    public function __construct()
    {
        add_action('plugins_loaded', array($this, 'init'));
        add_action('wp_head', array($this, 'javascript'), 20);
        add_action('admin_menu', array($this, 'plugin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_notices', array($this, 'warn_nosettings'));
        add_action('add_meta_boxes', array($this, 'create_metabox'));
        add_action('save_post', array($this, 'save_post'), 10, 2);
        add_action('admin_notices', array($this, 'notices'));
        add_action('publish_post', array($this, 'send_notification'), 10, 1);

        add_action('wp_ajax_cleverpush_subscription_id', array($this, 'set_subscription_id'));
        add_action('wp_ajax_nopriv_cleverpush_subscription_id', array($this, 'set_subscription_id'));

        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_add_settings_link'));


        load_plugin_textdomain(
            'cleverpush',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }

    /**
     * Initialize the plugin.
     */
    public function init()
    {
        if (class_exists('WC_Integration') && get_option('cleverpush_woocommerce_enabled') == 1) {
            include_once 'cleverpush-woocommerce.php';
            add_filter('woocommerce_integrations', array($this, 'add_woocommerce_integration'));
        }
    }

    public function add_woocommerce_integration($integrations)
    {
        $integrations[] = 'WC_Integration_CleverPush';
        return $integrations;
    }

    public function warn_nosettings()
    {
        if (!is_admin()) {
            return;
        }

        if (empty(get_option('cleverpush_channel_id')) || empty(get_option('cleverpush_channel_subdomain'))) {
            echo '<div class="updated fade"><p><strong>' . __('CleverPush is almost ready.', 'cleverpush') . '</strong> ' . sprintf(__('You have to select a channel in the %s to get started.', 'cleverpush'), '<a href="options-general.php?page=cleverpush_options">' . __('settings', 'cleverpush') . '</a>') . '</p></div>';
        }
    }

    public function set_subscription_id()
    {
        WC()->session->set('cleverpush_subscription_id', $_POST['subscriptionId']);
        wp_die();
    }

    public function plugin_add_settings_link($links)
    {
        $settings_link = '<a href="options-general.php?page=cleverpush_options">' . __( 'Settings' ) . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }


    //------------------------------------------------------------------------//
    //---Metabox--------------------------------------------------------------//
    //------------------------------------------------------------------------//
    public function create_metabox()
    {
        add_meta_box('cleverpush-metabox', 'CleverPush', array($this, 'metabox'), 'post', 'side', 'high');
    }

    public function metabox($post)
    {
        ?>
        <input type="hidden" name="cleverpush_metabox_form_data_available" value="1">
        <label><input name="cleverpush_send_notification" type="checkbox"
                      value="1" <?php if (get_post_meta($post->ID, 'cleverpush_send_notification', true)) echo 'checked'; ?>> <?php _e('Send push notification', 'cleverpush'); ?>
        </label>
        <?php
    }

    public function publish_post($post_id) {
        if ('inline-save' == $_POST['action'])
        {
            return;
        }

        if (!isset($_POST ['cleverpush_metabox_form_data_available']) ? isset($_POST['cleverpush_send_notification']) : get_post_meta($post_id, 'cleverpush_send_notification', true))
        {
            return;
        }

        $title = html_entity_decode(get_bloginfo('name'));
        $body = html_entity_decode(get_the_title($post_id));
        $url = get_permalink($post_id);

        try {
            CleverPush_Api::send_notification($title, $body, $url);
            update_option('cleverpush_notification_result', array('status' => 'success'));
            delete_post_meta($post_id, 'cleverpush_send_notification');

        } catch (Exception $ex) {
            update_option('cleverpush_notification_result', array('status' => 'error', 'message' => $ex->getMessage() ));
        }
    }

    public function save_post($post_id)
    {
        if ('inline-save' == $_POST['action'] || !current_user_can('edit_post', $post_id))
            return;

        $should_send = get_post_status($post_id) != 'publish' ? isset ($_POST['cleverpush_send_notification']) : false;
        update_post_meta($post_id, 'cleverpush_send_notification', $should_send);
    }

    public function notices()
    {
        $result = get_option( 'cleverpush_notification_result', null );
        if ($result)
        {
            if ($result['status'] === 'success')
            {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('The push notification for this post has been successfully sent.', 'cleverpush') . '</p></div>';
            }
            else if ($result['status'] === 'error')
            {
                echo '<div class="error is-dismissible"><p>CleverPush API Error:<br>' .  $result['message'] . '</p></div>';
            }
        }
        update_option('cleverpush_notification_result', null);
    }


    public function plugin_menu()
    {
        add_options_page('CleverPush', 'CleverPush', 'create_users', 'cleverpush_options', array($this, 'plugin_options'));
    }

    public function register_settings()
    {
        register_setting('cleverpush_options', 'cleverpush_channel');
        register_setting('cleverpush_options', 'cleverpush_channel_id');
        register_setting('cleverpush_options', 'cleverpush_channel_subdomain');
        register_setting('cleverpush_options', 'cleverpush_apikey_private');
        register_setting('cleverpush_options', 'cleverpush_apikey_public');
        register_setting('cleverpush_options', 'cleverpush_woocommerce_enabled');
        register_setting('cleverpush_options', 'cleverpush_woocommerce_notification_minutes');
        register_setting('cleverpush_options', 'cleverpush_woocommerce_notification_text');
    }

    public function javascript()
    {
        $woocommerce_available = in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
        $woocommerce_enabled = false;

        $cleverpush_id = get_option('cleverpush_channel_id');
        if (!empty($cleverpush_id)) {
            echo '<script src="//cdnjs.cloudflare.com/ajax/libs/fetch/2.0.3/fetch.min.js"></script>';
            echo '<script src="//static.cleverpush.com/sdk/cleverpush.js" async></script>';
            echo '<script>';
            echo 'var cleverpushWordpressConfig = ' . json_encode(['channelId' => $cleverpush_id, 'ajaxUrl' => admin_url('admin-ajax.php'), 'woocommerceEnabled' => $woocommerce_available && $woocommerce_enabled]) . ';';
            echo file_get_contents(plugin_dir_path( __FILE__ ) . '/assets/cleverpush.js');
            echo '</script>';
        }
    }

    public function plugin_options()
    {
        $channels = array();
        $selected_channel_id = get_option('cleverpush_channel_id');

        $api_key_private = get_option('cleverpush_apikey_private');
        if (!empty($api_key_private)) {
            $response = wp_remote_get( CLEVERPUSH_API_ENDPOINT . '/channels', array(
                    'timeout' => 10,
                    'headers' => array(
                        'authorization' => $api_key_private
                    )
                )
            );

            if ( is_wp_error( $response ) ) {
                ?>
                <div class="error notice">
                    <p><?php echo $response->get_error_message(); ?></p>
                </div>
                <?php
            } else {
                $body = wp_remote_retrieve_body( $response );
                $data = json_decode( $body );
                if (isset($data->channels)) {
                    $channels = $data->channels;
                }
            }
        }

        $woocommerce_available = in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );

        ?>

        <div class="wrap">
            <h2>CleverPush</h2>
            <p><?php echo sprintf(__('You need to have a %s account with an already set up channel to use this plugin. Please then enter your channel identifier (subdomain) below.', 'cleverpush'), '<a target="_blank" href="https://cleverpush.com/">CleverPush</a>'); ?></p>
            <p><?php echo sprintf(__('The API keys can be found in the %s.', 'cleverpush'), '<a href="https://cleverpush.com/app/settings/api" target="_blank">' . __('API settings', 'cleverpush') . '</a>'); ?></p>

            <form method="post" action="options.php">
                <input type="hidden" name="cleverpush_channel_subdomain" value="<?php echo get_option('cleverpush_channel_subdomain'); ?>">
                <?php settings_fields('cleverpush_options'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php _e('Select Channel', 'cleverpush'); ?></th>
                        <td>
                            <?php if (!empty($api_key_private)) {
                                if (!empty($channels) && count($channels) > 0) {
                                    ?>
                                    <select name="cleverpush_channel_id">
                                        <option disabled value="" <?php echo empty($selected_channel_id) ? 'selected' : ''; ?>>Kanal auswählen...</option>
                                        <?php
                                        foreach ($channels as $channel) {
                                            ?>
                                            <option value="<?php echo $channel->_id; ?>" <?php echo $selected_channel_id == $channel->_id ? 'selected' : ''; ?> data-subdomain="<?php echo $channel->identifier; ?>"><?php echo $channel->name; ?></option>
                                            <?php
                                        }
                                        ?>
                                    </select>
                                    <?php
                                } else {
                                    ?>
                                    <?php _e('No channels available', 'cleverpush'); ?>
                                    <?php
                                }
                            } else { ?>
                                <?php _e('Please enter your API keys first', 'cleverpush'); ?>
                            <?php } ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e('Public API-Key', 'cleverpush'); ?></th>
                        <td><input type="text" name="cleverpush_apikey_public"
                                   value="<?php echo get_option('cleverpush_apikey_public'); ?>" style="width: 320px;"/></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e('Private API-Key', 'cleverpush'); ?></th>
                        <td><input type="text" name="cleverpush_apikey_private"
                                   value="<?php echo get_option('cleverpush_apikey_private'); ?>" style="width: 320px;"/></td>
                    </tr>

                    <?php if ($woocommerce_available): ?>

                        <tr valign="top">
                            <th scope="row"><?php _e('WooCommerce Integration', 'cleverpush'); ?></th>
                            <td>
                                <label><input type="checkbox" name="cleverpush_woocommerce_enabled"
                                              value="1" <?php checked('1', get_option('cleverpush_woocommerce_enabled')); ?>/> aktiviert</label>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e('WooCommerce Benachrichtigung', 'cleverpush'); ?></th>
                            <td>
                                Nach <input type="number" name="cleverpush_woocommerce_notification_minutes" style="width: 70px;"
                                              value="<?php echo get_option('cleverpush_woocommerce_notification_minutes', 30); ?>"/> Minuten senden, falls Produkt noch im Warenkorb
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e('WooCommerce Benachrichtigungs-Text', 'cleverpush'); ?></th>
                            <td>
                                <input type="text" name="cleverpush_woocommerce_notification_text"
                                       value="<?php echo get_option('cleverpush_woocommerce_notification_text', 'Wir haben noch etwas in deinem Warenkorb gefunden.'); ?>" style="width: 420px;"/>
                            </td>
                        </tr>

                    <?php endif; ?>
                </table>

                <p class="submit"><input type="submit" class="button-primary"
                                         value="<?php _e('Save Changes', 'cleverpush') ?>"/></p>
            </form>
        </div>

        <script>
            var subdomain_input = document.querySelector('input[name="cleverpush_channel_subdomain"]');
            document.querySelector('select[name="cleverpush_channel_id').addEventListener('change', function() {
                subdomain_input.value = this.querySelector(':checked').getAttribute('data-subdomain');
            });
        </script>

        <?php
    }
}

$cleverPush = new CleverPush( __FILE__ );

endif;
