<?php
/*
Plugin Name: CleverPush
Plugin URI: https://cleverpush.com
Description: Send push notifications to your users right through your website. Visit <a href="https://cleverpush.com">CleverPush</a> for more details.
Author: CleverPush
Version: 0.7.8
Author URI: https://cleverpush.com
Text Domain: cleverpush
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit;
}

include_once 'cleverpush-api.php';

if ( ! class_exists( 'CleverPush' ) ) :
    class CleverPush
    {
        /**
         * varruct the plugin.
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
            add_action('publish_post', array($this, 'publish_post'), 10, 1);

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
            $notification_sent = get_post_meta(get_the_ID(), 'cleverpush_notification_sent', true);

            if ($notification_sent) {
                $notification_sent_at = get_post_meta(get_the_ID(), 'cleverpush_notification_sent_at', true);
                if (!empty($notification_sent_at) && (time() - $notification_sent_at) < 60) {
                    ?>
                    ✅ <?php _e('A notification as been sent for this post', 'cleverpush'); ?>
                    <?php
                    return;
                }
            }

            $selected_channel_id = get_option('cleverpush_channel_id');
            $api_key_private = get_option('cleverpush_apikey_private');

            if (!empty($api_key_private) && !empty($selected_channel_id)) {
                $cleverpush_segments = array();

                $response = wp_remote_get( CLEVERPUSH_API_ENDPOINT . '/channel/' . $selected_channel_id . '/segments', array(
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
                    if (isset($data->segments)) {
                        $cleverpush_segments = $data->segments;
                    }
                }

                $cleverpush_topics = array();

                $response = wp_remote_get( CLEVERPUSH_API_ENDPOINT . '/channel/' . $selected_channel_id . '/topics', array(
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
                    if (isset($data->topics)) {
                        $cleverpush_topics = $data->topics;
                    }
                }

                ?>

                <input type="hidden" name="cleverpush_metabox_form_data_available" value="1">
                <label><input name="cleverpush_send_notification" type="checkbox"
                              value="1" <?php if (get_post_meta($post->ID, 'cleverpush_send_notification', true)) echo 'checked'; ?>> <?php _e('Send push notification', 'cleverpush'); ?>
                </label>

                <div class="cleverpush-content components-base-control" style="display: none; margin-top: 15px;">
                    <div class="components-base-control__field">
                        <label class="components-base-control__label" for="cleverpush_title"><?php _e('Custom headline', 'cleverpush'); ?>:</label>
                        <div><input type="text" name="cleverpush_title" id="cleverpush_title" style="width: 100%"></div>
                    </div>

                    <div class="components-base-control__field">
                        <label class="components-base-control__label" for="cleverpush_text"><?php _e('Custom text', 'cleverpush'); ?>:</label>
                        <div><input type="text" name="cleverpush_text" id="cleverpush_text" style="width: 100%"></div>
                    </div>

                    <?php
                    if (!empty($cleverpush_topics) && count($cleverpush_topics) > 0) {
                        ?>
                        <div class="components-base-control__field">
                            <label class="components-base-control__label"><?php _e('Topics', 'cleverpush'); ?>:</label>
                            <div>
                                <div>
                                    <label><input name="cleverpush_use_topics" type="radio" value="0" checked> <?php _e('All subscriptions', 'cleverpush'); ?></label>
                                </div>
                                <div>
                                    <label><input name="cleverpush_use_topics" type="radio" value="1"> <?php _e('Select topics', 'cleverpush'); ?></label>
                                </div>
                            </div>
                        </div>
                        <div class="components-base-control__field cleverpush-topics" style="display: none; margin-left: 30px;">
                            <?php
                            foreach ($cleverpush_topics as $topic) {
                                ?>
                                <div>
                                    <label>
                                        <input type="checkbox" name="cleverpush_topics[]" value="<?php echo $topic->_id; ?>"><?php echo $topic->name; ?></input>
                                    </label>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                        <?php
                    }
                    ?>

                    <?php
                    if (!empty($cleverpush_segments) && count($cleverpush_segments) > 0) {
                        ?>
                        <div class="components-base-control__field">
                            <label class="components-base-control__label"><?php _e('Segments', 'cleverpush'); ?>:</label>
                            <div>
                                <div>
                                    <label><input name="cleverpush_use_segments" type="radio" value="0" checked> <?php _e('All subscriptions', 'cleverpush'); ?></label>
                                </div>
                                <div>
                                    <label><input name="cleverpush_use_segments" type="radio" value="1"> <?php _e('Select segments', 'cleverpush'); ?></label>
                                </div>
                            </div>
                        </div>
                        <div class="components-base-control__field cleverpush-segments" style="display: none; margin-left: 30px;">
                                <?php
                                foreach ($cleverpush_segments as $segment) {
                                    ?>
                                    <div>
                                        <label>
                                            <input type="checkbox" name="cleverpush_segments[]" value="<?php echo $segment->_id; ?>"><?php echo $segment->name; ?></input>
                                        </label>
                                    </div>
                                    <?php
                                }
                                ?>
                        </div>
                    <?php
                    }
                    ?>
                </div>

                <script>
                    try {
                        var cpCheckbox = document.querySelector('input[name="cleverpush_send_notification"]');
                        var cpContent = document.querySelector('.cleverpush-content');
                        if (cpCheckbox && cpContent) {
                            cpCheckbox.addEventListener('change', function (e) {
                                cpContent.style.display = e.target.checked ? 'block' : 'none';
                            });

                            var cpTopicsRadios = document.querySelectorAll('input[name="cleverpush_use_topics"]');
                            var cpTopics = document.querySelector('.cleverpush-topics');
                            if (cpTopicsRadios && cpTopics) {
                                for (var cpTopicsRadioIndex = 0; cpTopicsRadioIndex < cpTopicsRadios.length; cpTopicsRadioIndex++) {
                                    cpTopicsRadios[cpTopicsRadioIndex].addEventListener('change', function (e) {
                                        cpTopics.style.display = e.currentTarget.value === '1' ? 'block' : 'none';
                                    });
                                }
                            }

                            var cpSegmentsRadios = document.querySelectorAll('input[name="cleverpush_use_segments"]');
                            var cpSegments = document.querySelector('.cleverpush-segments');
                            if (cpSegmentsRadios && cpSegments) {
                                for (var cpSegmentRadioIndex = 0; cpSegmentRadioIndex < cpSegmentsRadios.length; cpSegmentRadioIndex++) {
                                    cpSegmentsRadios[cpSegmentRadioIndex].addEventListener('change', function (e) {
                                        cpSegments.style.display = e.currentTarget.value === '1' ? 'block' : 'none';
                                    });
                                }
                            }

                            // credits: https://rsvpmaker.com/blog/2019/03/31/new-rsvpmaker-form-builder-based-on-gutenberg/
                            window.addEventListener('load', function() {
                                if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe) {
                                    var hasNotice = false;

                                    var wasSavingPost = wp.data.select( 'core/editor' ).isSavingPost();
                                    var wasAutosavingPost = wp.data.select( 'core/editor' ).isAutosavingPost();
                                    var wasPreviewingPost = wp.data.select( 'core/editor' ).isPreviewingPost();
                                    // determine whether to show notice
                                    wp.data.subscribe(function() {
                                        var isSavingPost = wp.data.select( 'core/editor' ).isSavingPost();
                                        var isAutosavingPost = wp.data.select( 'core/editor' ).isAutosavingPost();
                                        var isPreviewingPost = wp.data.select( 'core/editor' ).isPreviewingPost();
                                        var hasActiveMetaBoxes = wp.data.select( 'core/edit-post' ).hasMetaBoxes();

                                        var postStatus = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'status' );

                                        // Save metaboxes on save completion, except for autosaves that are not a post preview.
                                        var shouldTriggerTemplateNotice = (
                                            ( wasSavingPost && ! isSavingPost && ! wasAutosavingPost ) ||
                                            ( wasAutosavingPost && wasPreviewingPost && ! isPreviewingPost )
                                        );

                                        // Save current state for next inspection.
                                        wasSavingPost = isSavingPost;
                                        wasAutosavingPost = isAutosavingPost;
                                        wasPreviewingPost = isPreviewingPost;

                                        if ( shouldTriggerTemplateNotice && postStatus === 'publish' ) {
                                            if (cpCheckbox && cpCheckbox.checked) {
                                                setTimeout(function () {
                                                    cpCheckbox.checked = false;
                                                }, 30 * 1000);

                                                hasNotice = true;

                                                wp.data.dispatch('core/notices').createNotice(
                                                    'info', // Can be one of: success, info, warning, error.
                                                    '<?php echo __('The push notification for this post has been successfully sent.', 'cleverpush'); ?>', // Text string to display.
                                                    {
                                                        id: 'cleverpush-notification-status', //assigning an ID prevents the notice from being added repeatedly
                                                        isDismissible: true, // Whether the user can dismiss the notice.
                                                        // Any actions the user can perform.
                                                        actions: []
                                                    }
                                                );
                                            } else if (hasNotice) {
                                                wp.data.dispatch('core/notices').removeNotice('cleverpush-notification-status');
                                            }
                                        }
                                    });

                                }
                            });
                        }
                    } catch (err) {
                        console.error(err);
                    }
                </script>

            <?php

            } else {

            ?>

                <div><?php _e('Please enter your API keys first', 'cleverpush'); ?></div>

            <?php

            }
        }

        public function publish_post($post_id) {
            if ('inline-save' == $_POST['action'])
            {
                return;
            }

            if (isset($_POST['cleverpush_metabox_form_data_available']) ? !isset($_POST['cleverpush_send_notification']) : !get_post_meta($post_id, 'cleverpush_send_notification', true))
            {
                return;
            }

            if (get_post_meta($post_id, 'cleverpush_notification_sent', true)) {
                $notification_sent_at = get_post_meta(get_the_ID(), 'cleverpush_notification_sent_at', true);
                if (!empty($notification_sent_at) && (time() - $notification_sent_at) < 60) {
                    return;
                }
            }

            $title = html_entity_decode(get_the_title($post_id));
            $text = !empty(get_the_excerpt()) ? html_entity_decode(get_the_excerpt()) : '';
            $url = get_permalink($post_id);

            if (!empty($_POST['cleverpush_title'])) {
                $title = stripslashes($_POST['cleverpush_title']);
                $text = '';
            }
            if (!empty($_POST['cleverpush_text'])) {
                $text = stripslashes($_POST['cleverpush_text']);
            }

            $options = array();
            if ($_POST['cleverpush_use_segments'] == '1' && !empty($_POST['cleverpush_segments'])) {
                $options['segments'] = $_POST['cleverpush_segments'];
            }
            if ($_POST['cleverpush_use_topics'] == '1' && !empty($_POST['cleverpush_topics'])) {
                $options['topics'] = $_POST['cleverpush_topics'];
            }
            $thumbnail_url = get_the_post_thumbnail_url();
            if (!empty($thumbnail_url)) {
                $options['mediaUrl'] = $thumbnail_url;
            }

            delete_post_meta($post_id, 'cleverpush_send_notification');

            try {
                CleverPush_Api::send_notification($title, $text, $url, $options);
                update_option('cleverpush_notification_result', array('status' => 'success'));
                update_option('cleverpush_notification_error', null);
                update_post_meta($post_id, 'cleverpush_notification_sent', true);
                update_post_meta($post_id, 'cleverpush_notification_sent_at', time());

            } catch (Exception $ex) {
                update_option('cleverpush_notification_result', array('status' => 'error', 'message' => $ex->getMessage() ));
                update_option('cleverpush_notification_error', $ex->getMessage());
            }
        }

        public function save_post($post_id)
        {
            if (!current_user_can('edit_post', $post_id))
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
        }

        public function javascript()
        {
            $cleverpush_id = get_option('cleverpush_channel_id');
            if (!empty($cleverpush_id)) {
                // echo "<script>window.cleverPushConfig = { plugin: 'wordpress', serviceWorkerFile: '/wp-content/plugins/" . plugin_basename(plugin_dir_path( __FILE__ ) . '/assets/cleverpush-worker.js.php') . "' };</script>\n";
                echo "<script src=\"//static.cleverpush.com/channel/loader/" . $cleverpush_id . ".js\" async></script>\n";
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

            ?>

            <div class="wrap">
                <h2>CleverPush</h2>
                <p><?php echo sprintf(__('You need to have a %s account with an already set up channel to use this plugin. Please then select your channel below.', 'cleverpush'), '<a target="_blank" href="https://cleverpush.com/">CleverPush</a>'); ?></p>
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
            $last_error = get_option('cleverpush_notification_error');
            update_option('cleverpush_notification_error', null);

            if (!empty($last_error)) {
                ?>

                <div class="error notice">
                    <?php
                    echo $last_error;
                    ?>
                </div>

                <?php
            }
        }
    }

    $cleverPush = new CleverPush( __FILE__ );

endif;
