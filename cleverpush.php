<?php
/*
Plugin Name: CleverPush
Plugin URI: https://cleverpush.com
Description: Send push notifications to your users right trough your website. Visit <a href="https://cleverpush.com">CleverPush</a> for more details.
Author: CleverPush
Version: 0.3
Author URI: https://cleverpush.com

This relies on the actions being present in the themes header.php and footer.php
* header.php code before the closing </head> tag
*   wp_head();
*
*/

//------------------------------------------------------------------------//
//---Config---------------------------------------------------------------//
//------------------------------------------------------------------------//

$cleverpush_api_endpoint = 'https://api.cleverpush.com';
$cleverpush_header_script = '
<script>
(function(c,l,v,r,p,s,h){c[\'CleverPushObject\']=p;c[p]=c[p]||function(){(c[p].q=c[p].q||[]).push(arguments)},c[p].l=1*new Date();s=l.createElement(v),h=l.getElementsByTagName(v)[0];s.async=1;s.src=r;h.parentNode.insertBefore(s,h)})(window,document,\'script\',\'//CLEVERPUSH_IDENTIFIER.cleverpush.com/loader.js\',\'cleverpush\');
cleverpush(\'triggerOptIn\');
cleverpush(\'checkNotificationClick\');
</script>
';

//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//
add_action('wp_head', 'cleverpush_headercode', 1);
add_action('admin_menu', 'cleverpush_plugin_menu');
add_action('admin_init', 'cleverpush_register_settings');
add_action('admin_notices', 'cleverpush_warn_nosettings');
add_action('add_meta_boxes', 'cleverpush_create_metabox');
add_action('save_post', 'cleverpush_save_post', 10, 2);
add_action('admin_notices', 'cleverpush_notices');
add_action('publish_post', 'cleverpush_send_notification', 10, 1);

load_plugin_textdomain(
    'cleverpush',
    false,
    dirname(plugin_basename(__FILE__)) . '/languages/'
);


//------------------------------------------------------------------------//
//---Metabox--------------------------------------------------------------//
//------------------------------------------------------------------------//
function cleverpush_create_metabox()
{
    add_meta_box('cleverpush-metabox', 'CleverPush', 'cleverpush_metabox', 'post', 'side', 'high');
}

function cleverpush_metabox($post)
{
    ?>
    <input type="hidden" name="cleverpush_metabox_form_data_available" value="1">
    <label><input name="cleverpush_send_notification" type="checkbox"
                  value="1" <?php if (get_post_meta($post->ID, 'cleverpush_send_notification', true)) echo 'checked'; ?>> <?php _e('Send push notification', 'cleverpush'); ?>
    </label>
    <?php
}

function cleverpush_save_post($post_id)
{
    if ('inline-save' == $_POST['action'] || !current_user_can('edit_post', $post_id))
        return;

    $should_send = get_post_status($post_id) != 'publish' ? isset ($_POST['cleverpush_send_notification']) : false;
    update_post_meta($post_id, 'cleverpush_send_notification', $should_send);
}

function cleverpush_send_notification($post_id)
{
    global $cleverpush_api_endpoint;

    if ('inline-save' == $_POST['action'])
    {
        return;
    }

    if (!isset($_POST ['cleverpush_metabox_form_data_available']) ? isset($_POST['cleverpush_send_notification']) : get_post_meta($post_id, 'cleverpush_send_notification', true))
    {
        return;
    }

    $channel_id = get_option('cleverpush_channel_id');
    $api_key_private = get_option('cleverpush_apikey_private');

    if (empty($channel_id) || empty($api_key_private))
    {
        return;
    }

    $title = html_entity_decode(get_bloginfo('name'));
    $body = html_entity_decode(get_the_title($post_id));
    $url = get_permalink($post_id);

    $response = wp_remote_post( $cleverpush_api_endpoint . '/notification/send', array(
            'timeout' => 10,
            'headers' => array(
                'authorization' => $api_key_private,
                'content-type' => 'application/json'
            ),
            'body' => json_encode( array(
                'channel' => $channel_id,
                'title' => $title,
                'text' => $body,
                'url' => $url
            ) )
        )
    );

    $error_message = null;
    if (is_wp_error ( $response ))
    {
        $error_message = $response->get_error_message();
    } elseif ( !in_array( wp_remote_retrieve_response_code( $response ), array(200, 201) ) )
    {
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode($body);
        if ($data && !empty($data->error))
        {
            $error_message = $data->error;
        }
        else
        {
            $error_message = 'HTTP ' . wp_remote_retrieve_response_code( $response );
        }
    }

    if (!empty($error_message))
    {
        update_option('cleverpush_notification_result', array('status' => 'error', 'message' => $error_message ));
    }
    else
    {
        update_option('cleverpush_notification_result', array('status' => 'success'));
        delete_post_meta($post_id, 'cleverpush_send_notification');
    }
}

function cleverpush_notices()
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


//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//
// options page link
function cleverpush_plugin_menu()
{
    add_options_page('CleverPush', 'CleverPush', 'create_users', 'cleverpush_options', 'cleverpush_plugin_options');
}

// register settings
function cleverpush_register_settings()
{
    register_setting('cleverpush_options', 'cleverpush_channel');
    register_setting('cleverpush_options', 'cleverpush_channel_id');
    register_setting('cleverpush_options', 'cleverpush_channel_subdomain');
    register_setting('cleverpush_options', 'cleverpush_apikey_private');
    register_setting('cleverpush_options', 'cleverpush_apikey_public');
}

//------------------------------------------------------------------------//
//---Output Functions-----------------------------------------------------//
//------------------------------------------------------------------------//
function cleverpush_headercode()
{
    global $cleverpush_header_script;

    $cleverpush_identifier = get_option('cleverpush_channel_subdomain');
    if (!empty($cleverpush_identifier)) {
        echo str_replace('CLEVERPUSH_IDENTIFIER', $cleverpush_identifier, $cleverpush_header_script);
    }
}

//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//

function cleverpush_plugin_options()
{
    global $cleverpush_api_endpoint;

    $channels = array();
    $selected_channel_id = get_option('cleverpush_channel_id');

    $api_key_private = get_option('cleverpush_apikey_private');
    if (!empty($api_key_private)) {
        $response = wp_remote_get( $cleverpush_api_endpoint . '/channels', array(
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
                               value="<?php echo get_option('cleverpush_apikey_public'); ?>"/></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Private API-Key', 'cleverpush'); ?></th>
                    <td><input type="text" name="cleverpush_apikey_private"
                               value="<?php echo get_option('cleverpush_apikey_private'); ?>"/></td>
                </tr>
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

function cleverpush_warn_nosettings()
{
    if (!is_admin())
    {
        return;
    }

    if (empty(get_option('cleverpush_channel_id')) || empty(get_option('cleverpush_channel_subdomain')))
    {
        echo '<div class="updated fade"><p><strong>' . __('CleverPush is almost ready.', 'cleverpush') . '</strong> ' . sprintf(__('You have to select a channel in the %s to get started.', 'cleverpush'), '<a href="options-general.php?page=cleverpush_options">' . __('settings', 'cleverpush') . '</a>') . '</p></div>';
    }
}

?>
