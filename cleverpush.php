<?php
/*
Plugin Name: CleverPush
Plugin URI: https://cleverpush.com
Description: Send push notifications to your users right through your website. Visit <a href="https://cleverpush.com">CleverPush</a> for more details.
Author: CleverPush
Version: 1.0.10
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
			add_action('init', array($this, 'register_post_types'));
			add_action('admin_notices', array($this, 'warn_nosettings'));
			add_action('add_meta_boxes', array($this, 'create_metabox'));
			add_action('save_post', array($this, 'save_post'), 10, 2);
			add_action('admin_notices', array($this, 'notices'));
			add_action('publish_post', array($this, 'publish_post'), 10, 1);
			add_action('admin_enqueue_scripts', array($this, 'load_admin_style') );

			add_action('wp_ajax_cleverpush_send_options', array($this, 'ajax_load_options'));

			add_action('wp_ajax_cleverpush_subscription_id', array($this, 'set_subscription_id'));
			add_action('wp_ajax_nopriv_cleverpush_subscription_id', array($this, 'set_subscription_id'));

			add_action('single_template', array($this, 'cleverpush_story_template' ), 20, 1 );
			add_action('frontpage_template', array($this, 'cleverpush_story_template' ), 11 );

			add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_add_settings_link'));


			load_plugin_textdomain(
				'cleverpush',
				false,
				dirname(plugin_basename(__FILE__)) . '/languages/'
			);

			register_activation_hook( __FILE__, array($this, 'cleverpush_activate') );
			register_deactivation_hook( __FILE__, array($this, 'cleverpush_deactivate') );
		}

		function cleverpush_activate() {
			if ( ! get_option( 'cleverpush_flush_rewrite_rules_flag' ) ) {
				add_option( 'cleverpush_flush_rewrite_rules_flag', true );
			}
		}

		function cleverpush_deactivate() {
			flush_rewrite_rules();
		}

		function load_admin_style() {
			wp_enqueue_style( 'admin_css', plugin_dir_url( __FILE__ ) . 'assets/cleverpush-admin.css', false, '1.0.0' );
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

			if (empty(get_option('cleverpush_channel_id'))) {
				echo '<div class="updated fade"><p><strong>' . __('CleverPush is almost ready.', 'cleverpush') . '</strong> ' . sprintf(__('You have to select a channel in the %s to get started.', 'cleverpush'), '<a href="options-general.php?page=cleverpush_options">' . __('settings', 'cleverpush') . '</a>') . '</p></div>';
			}
		}

		public function register_post_types() {
			$labels = array(
				'menu_name' => _x('CP Stories', 'post type general name', 'cleverpush'),
				'name' => _x('CleverPush Stories', 'post type general name', 'cleverpush'),
				'singular_name' => _x('Story', 'post type singular name', 'cleverpush'),
				'add_new' => _x('Neue Story', 'portfolio item', 'cleverpush'),
				'add_new_item' => __('Neue Story hinzufügen', 'cleverpush'),
				'edit_item' => __('Story bearbeiten', 'cleverpush'),
				'new_item' => __('Neue Story', 'cleverpush'),
				'view_item' => __('Story ansehen', 'cleverpush'),
				'search_items' => __('Stories suchen', 'cleverpush'),
				'not_found' =>  __('Nichts gefunden', 'cleverpush'),
				'not_found_in_trash' => __('Nichts gefunden', 'cleverpush'),
				'parent_item_colon' => '',
				'all_items' =>  __('Stories', 'cleverpush'),
			);

			$args = array(
				'labels' => $labels,
				'public' => true,
				'show_ui' => true,
				'capability_type' => 'post',
				'hierarchical' => false,
				'menu_position' => null,
				'supports' => false,
				'rewrite' => array('slug' => 'cleverpush-stories','with_front' => false),
			);

			register_post_type( 'cleverpush_story' , $args );

			if ( get_option( 'cleverpush_flush_rewrite_rules_flag' ) ) {
				flush_rewrite_rules();
				delete_option( 'cleverpush_flush_rewrite_rules_flag' );
			}
		}

		public function cleverpush_story_id_meta() {
			?>

			<div class="wrap">
				<table class="form-table">

					<?php
					global $post;
					$custom = get_post_custom($post->ID);
					$apiKey = get_option('cleverpush_apikey_private');
					$channelId = get_option('cleverpush_channel_id');
					$cleverpushStoryId = $custom['cleverpush_story_id'][0];
					$fetchTime = get_transient('cleverpush_story_' . $cleverpushStoryId . '_time');

					if (!empty($apiKey))
					{
						$response = wp_remote_get( 'https://api.cleverpush.com/channel/' . $channelId . '/stories', array( 'headers' => array( 'Authorization' => $apiKey ) ) );
						if (is_wp_error($response)) {
							?>
							<div class="error notice">
								<p><?php echo $response->get_error_message(); ?></p>
							</div>
							<?php
						}
						else if ($response['response']['code'] == 200 && isset($response['body']))
						{
							$stories = json_decode($response['body'])->stories;
							if ($stories && count($stories) > 0)
							{
								?>

								<tr valign="top">
									<th scope="row">Story auswählen</th>
									<td>
										<select name="cleverpush_story_id">
											<?php
											echo '<option value="" disabled' . (empty($cleverpushStoryId) ? ' selected' : '') . '>Bitte Story auswählen…</option>';
											foreach ( $stories as $story ) {
												echo '<option value="' . $story->_id . '"' . ($cleverpushStoryId == $story->_id ? ' selected' : '') . '>' . $story->title . '</option>';
											}
											?>
										</select>
									</td>
								</tr>

								<?php
							}
							else
							{
								echo '<div class="error notice"><p>Es wurden keine CleverPush Stories gefunden.</p></div>';
							}
						}
						else if (!empty($response['response'])) {
							echo '<div class="error notice"><p>API Error: ' . $response['response']['message'] . '</p></div>';
						}
					}

					?>

					<tr valign="top">
						<th scope="row">Story Path</th>
						<td>
							<input type="text" name="post_name" value="<?php echo $post->post_name; ?>" class="regular-text" />
						</td>
					</tr>

					<tr valign="top">
						<th scope="row">Zwischenspeicher</th>
						<td>
							<p class="text-muted">Die Inhalte deiner Stories werden alle 30 Minuten neu von den CleverPush Servern geladen. Hier kannst du den Zwischenspeicher dafür leeren:</p>
							<!--
						<?php if (!empty($cleverpushId) && !empty($fetchTime)) { ?>
							<p>Zuletzt geladen: <strong><?php echo date('d.m.Y H:i', $fetchTime); ?></strong></p>
						<?php } ?>
						-->
							<br />
							<p><?php submit_button( 'Zwischenspeicher leeren', 'primary', 'clear_cache', false ); ?></p>
						</td>
					</tr>

				</table>
			</div>

			<?php
		}

		public function plugin_add_settings_link($links)
		{
			$settings_link = '<a href="options-general.php?page=cleverpush_options">' . __( 'Settings' ) . '</a>';
			array_unshift($links, $settings_link);
			return $links;
		}

		function ajax_load_options() {
			$selected_channel_id = get_option('cleverpush_channel_id');
			$api_key_private = get_option('cleverpush_apikey_private');
			$cleverpush_topics_required = false;
			$cleverpush_segments_required = false;

			if (!empty($api_key_private) && !empty($selected_channel_id)) {
				$cleverpush_segments = array();

				$response = wp_remote_get(CLEVERPUSH_API_ENDPOINT . '/channel/' . $selected_channel_id . '/segments', array(
						'timeout' => 10,
						'headers' => array(
							'authorization' => $api_key_private
						)
					)
				);

				if (is_wp_error($response)) {
					?>
					<div class="error notice">
						<p><?php echo $response->get_error_message(); ?></p>
					</div>
					<?php
				} else {
					$body = wp_remote_retrieve_body($response);
					$data = json_decode($body);
					if (isset($data->segments)) {
						$cleverpush_segments = $data->segments;
					}
					if (isset($data->segmentsRequiredField) && $data->segmentsRequiredField) {
						$cleverpush_segments_required = true;
					}
				}

				$cleverpush_topics = array();

				$response = wp_remote_get(CLEVERPUSH_API_ENDPOINT . '/channel/' . $selected_channel_id . '/topics', array(
						'timeout' => 10,
						'headers' => array(
							'authorization' => $api_key_private
						)
					)
				);

				if (is_wp_error($response)) {
					?>
					<div class="error notice">
						<p><?php echo $response->get_error_message(); ?></p>
					</div>
					<?php
				} else {
					$body = wp_remote_retrieve_body($response);
					$data = json_decode($body);
					if (isset($data->topics)) {
						$cleverpush_topics = $data->topics;
					}
					if (isset($data->topicsRequiredField) && $data->topicsRequiredField) {
						$cleverpush_topics_required = true;
					}
				}

				?>

				<?php
				if (!empty($cleverpush_topics) && count($cleverpush_topics) > 0) {
					?>
					<div class="components-base-control__field">
						<label class="components-base-control__label"><?php _e('Topics', 'cleverpush'); ?>:</label>
						<div>
							<div>
								<label><input name="cleverpush_use_topics" type="radio" value="0"
											  checked> <?php _e('All subscriptions', 'cleverpush'); ?></label>
							</div>
							<div>
								<label><input name="cleverpush_use_topics" type="radio"
											  value="1"> <?php _e('Select topics', 'cleverpush'); ?></label>
							</div>
						</div>
					</div>
					<div class="components-base-control__field cleverpush-topics"
						 style="display: none; margin-left: 30px;" data-required="<?php echo $cleverpush_topics_required ? 'true' : 'false'; ?>">
						<?php
						foreach ($cleverpush_topics as $topic) {
							?>
							<div>
								<label>
									<input type="checkbox" name="cleverpush_topics[]"
										   value="<?php echo $topic->_id; ?>"><?php echo $topic->name; ?></input>
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
								<label><input name="cleverpush_use_segments" type="radio" value="0"
											  checked> <?php _e('All subscriptions', 'cleverpush'); ?></label>
							</div>
							<div>
								<label><input name="cleverpush_use_segments" type="radio"
											  value="1"> <?php _e('Select segments', 'cleverpush'); ?></label>
							</div>
						</div>
					</div>
					<div class="components-base-control__field cleverpush-segments"
						 style="display: none; margin-left: 30px;" data-required="<?php echo $cleverpush_segments_required ? 'true' : 'false'; ?>">
						<?php
						foreach ($cleverpush_segments as $segment) {
							?>
							<div>
								<label>
									<input type="checkbox" name="cleverpush_segments[]"
										   value="<?php echo $segment->_id; ?>"><?php echo $segment->name; ?></input>
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

			} else {

				?>

				<div><?php _e('Please enter your API keys first', 'cleverpush'); ?></div>

				<?php

			}

			wp_die();
		}


		//------------------------------------------------------------------------//
		//---Metabox--------------------------------------------------------------//
		//------------------------------------------------------------------------//
		public function create_metabox()
		{
			add_meta_box('cleverpush-metabox', 'CleverPush', array($this, 'metabox'), 'post', 'side', 'high');
			add_meta_box('cleverpush_story_id_meta', 'CleverPush Story', array(&$this, 'cleverpush_story_id_meta'), 'cleverpush_story', 'normal', 'default');
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

				?>

				<input type="hidden" name="cleverpush_metabox_form_data_available" value="1">
				<label><input name="cleverpush_send_notification" type="checkbox"
							  value="1" <?php if (get_post_meta($post->ID, 'cleverpush_send_notification', true)) echo 'checked'; ?>> <?php _e('Send push notification', 'cleverpush'); ?>
				</label>

				<div class="cleverpush-content components-base-control" style="display: none; margin-top: 15px;">
					<div class="components-base-control__field">
						<label class="components-base-control__label"
							   for="cleverpush_title"><?php _e('Custom headline', 'cleverpush'); ?>:</label>
						<div><input type="text" name="cleverpush_title" id="cleverpush_title"
									value="<?php echo(!empty(get_post_meta($post->ID, 'cleverpush_title', true)) ? get_post_meta($post->ID, 'cleverpush_title', true) : ''); ?>"
									style="width: 100%"></div>
					</div>

					<div class="components-base-control__field">
						<label class="components-base-control__label"
							   for="cleverpush_text"><?php _e('Custom text', 'cleverpush'); ?>:</label>
						<div><input type="text" name="cleverpush_text" id="cleverpush_text"
									value="<?php echo(!empty(get_post_meta($post->ID, 'cleverpush_text', true)) ? get_post_meta($post->ID, 'cleverpush_text', true) : ''); ?>"
									style="width: 100%"></div>
					</div>

					<div class="cleverpush-loading-container">
						<div class="cleverpush-loading"></div>
					</div>
				</div>

				<script>
					try {
						var cpCheckbox = document.querySelector('input[name="cleverpush_send_notification"]');
						var cpContent = document.querySelector('.cleverpush-content');
						var cpLoading = document.querySelector('.cleverpush-loading-container');
						if (cpCheckbox && cpContent) {
							cpContent.style.display = cpCheckbox.checked ? 'block' : 'none';
							cpCheckbox.addEventListener('change', function (e) {
								cpContent.style.display = e.target.checked ? 'block' : 'none';
							});

							var initCleverPush = function () {
								if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe && wp.data.select) {
									var hasNotice = false;

									var coreEditor = wp.data.select('core/editor');

									if (coreEditor) {
										var wasSavingPost = coreEditor.isSavingPost();
										var wasAutosavingPost = coreEditor.isAutosavingPost();
										var wasPreviewingPost = coreEditor.isPreviewingPost();
										// determine whether to show notice
										wp.data.subscribe(function () {
											if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe && wp.data.select) {
												if (!coreEditor) {
													coreEditor = wp.data.select('core/editor');
												}
												if (coreEditor) {
													var isSavingPost = coreEditor.isSavingPost();
													var isAutosavingPost = coreEditor.isAutosavingPost();
													var isPreviewingPost = coreEditor.isPreviewingPost();
													var postStatus = coreEditor.getEditedPostAttribute('status');

													// Save metaboxes on save completion, except for autosaves that are not a post preview.
													var shouldTriggerTemplateNotice = (
														(wasSavingPost && !isSavingPost && !wasAutosavingPost) ||
														(wasAutosavingPost && wasPreviewingPost && !isPreviewingPost)
													);

													// Save current state for next inspection.
													wasSavingPost = isSavingPost;
													wasAutosavingPost = isAutosavingPost;
													wasPreviewingPost = isPreviewingPost;

													if (shouldTriggerTemplateNotice && postStatus === 'publish') {
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
															var coreNotices = wp.data.dispatch('core/notices');
															if (coreNotices) {
																coreNotices.removeNotice('cleverpush-notification-status');
															}
														}
													}
												}
											}
										});
									}
								}

								var request = new XMLHttpRequest();
								request.onreadystatechange = function() {
									if (request.readyState === XMLHttpRequest.DONE) {
										if (cpContent) {
											var ajaxContent = document.createElement('div');
											ajaxContent.innerHTML = request.responseText;
											cpContent.appendChild(ajaxContent);

											if (cpLoading) {
												cpLoading.style.display = 'none';
											}

											var cpTopicsRadios = document.querySelectorAll('input[name="cleverpush_use_topics"]');
											var cpTopics = document.querySelector('.cleverpush-topics');
											var topicsRequired = false;
											if (cpTopicsRadios && cpTopics) {
												topicsRequired = cpTopics.dataset.required === 'true';
												for (var cpTopicsRadioIndex = 0; cpTopicsRadioIndex < cpTopicsRadios.length; cpTopicsRadioIndex++) {
													cpTopicsRadios[cpTopicsRadioIndex].addEventListener('change', function (e) {
														cpTopics.style.display = e.currentTarget.value === '1' ? 'block' : 'none';
													});
												}
											}

											var cpSegmentsRadios = document.querySelectorAll('input[name="cleverpush_use_segments"]');
											var cpSegments = document.querySelector('.cleverpush-segments');
											var segmentsRequired = false;
											if (cpSegmentsRadios && cpSegments) {
												segmentsRequired = cpSegments.dataset.required === 'true';
												for (var cpSegmentRadioIndex = 0; cpSegmentRadioIndex < cpSegmentsRadios.length; cpSegmentRadioIndex++) {
													cpSegmentsRadios[cpSegmentRadioIndex].addEventListener('change', function (e) {
														cpSegments.style.display = e.currentTarget.value === '1' ? 'block' : 'none';
													});
												}
											}

											if (topicsRequired || segmentsRequired) {
												if (typeof wp !== 'undefined' && wp.plugins && wp.plugins.registerPlugin && wp.editPost && wp.editPost.PluginPrePublishPanel) {
													var topicsLocked = false;
													var segmentsLocked = false;

													var registerPlugin = wp.plugins.registerPlugin;
													var PluginPrePublishPanel = wp.editPost.PluginPrePublishPanel;

													var PrePublishCleverPush = function() {
														if ( cpCheckbox && cpCheckbox.checked ) {
															var topicsChecked = false;
															if (topicsRequired) {
																var topics = cpTopics.querySelectorAll('input[type="checkbox"]');
																for (var i = 0; i < topics.length; i++) {
																	if (topics[i].checked) {
																		topicsChecked = true;
																	}
																}
																if (!topicsChecked && !topicsLocked) {
																	topicsLocked = true;
																	wp.data.dispatch( 'core/editor' ).lockPostSaving( 'cleverpushTopics' );
																} else if (topicsChecked && topicsLocked) {
																	topicsLocked = false;
																	wp.data.dispatch( 'core/editor' ).unlockPostSaving( 'cleverpushTopics' );
																}
															}

															var segmentsChecked = false;
															if (segmentsRequired) {
																var segments = cpSegments.querySelectorAll('input[type="checkbox"]');
																for (var i = 0; i < segments.length; i++) {
																	if (segments[i].checked) {
																		segmentsChecked = true;
																	}
																}
																if (!segmentsChecked && !segmentsLocked) {
																	segmentsLocked = true;
																	wp.data.dispatch( 'core/editor' ).lockPostSaving( 'cleverpushSegments' );
																} else if (segmentsChecked && segmentsLocked) {
																	segmentsLocked = false;
																	wp.data.dispatch( 'core/editor' ).unlockPostSaving( 'cleverpushSegments' );
																}
															}
														}

														return React.createElement(PluginPrePublishPanel, {
															title: 'CleverPush'
														}, topicsRequired && !topicsChecked ? React.createElement("p", null, "Bitte Themenbereiche ausw\xE4hlen") : null, segmentsRequired && !segmentsChecked ? React.createElement("p", null, "Bitte Segmente ausw\xE4hlen") : null);
													};

													registerPlugin( 'pre-publish-checklist', { render: PrePublishCleverPush } );
												} else {
													var publish = document.getElementById('publish');
													if (publish) {
														publish.addEventListener('click', function(e) {
															if ( cpCheckbox && cpCheckbox.checked ) {
																var topicsChecked = false;
																if (topicsRequired) {
																	var topics = cpTopics.querySelectorAll('input[type="checkbox"]');
																	for (var i = 0; i < topics.length; i++) {
																		if (topics[i].checked) {
																			topicsChecked = true;
																		}
																	}
																	if (!topicsChecked) {
																		e.preventDefault();
																		alert('CleverPush: Bitte Themenbereiche auswählen');
																		return;
																	}
																}

																var segmentsChecked = false;
																if (segmentsRequired) {
																	var segments = cpSegments.querySelectorAll('input[type="checkbox"]');
																	for (var i = 0; i < segments.length; i++) {
																		if (segments[i].checked) {
																			segmentsChecked = true;
																		}
																	}
																	if (!segmentsChecked) {
																		e.preventDefault();
																		alert('CleverPush: Bitte Segmente auswählen');
																		return;
																	}
																}
															}
														});
													}
												}
											}
										}
									}
								};
								request.open('POST', ajaxurl, true);
								request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
								request.send('action=cleverpush_send_options');
							};

							if (document.readyState === 'complete') {
								initCleverPush();
							} else {
								window.addEventListener('load', initCleverPush);
							}
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

		public function save_post($post_id, $post)
		{
			if (!current_user_can('edit_post', $post_id))
				return;

			$should_send = get_post_status($post_id) != 'publish' ? isset ($_POST['cleverpush_send_notification']) : false;
			update_post_meta($post_id, 'cleverpush_send_notification', $should_send);

			if (isset($_POST['cleverpush_title'])) {
                update_post_meta($post_id, 'cleverpush_title', $_POST['cleverpush_title']);
            }
			if (isset($_POST['cleverpush_text'])) {
                update_post_meta($post_id, 'cleverpush_text', $_POST['cleverpush_text']);
            }

			if (!empty($_POST['cleverpush_story_id'])) {
				if (isset($_POST['clear_cache']) && !empty($_POST['cleverpush_story_id'])) {
					delete_transient('cleverpush_story_' . sanitize_text_field($_POST['cleverpush_story_id']) . '_content');
					delete_transient('cleverpush_story_' . sanitize_text_field($_POST['cleverpush_story_id']) . '_time');
				}

				$meta = array(
					'cleverpush_story_id' => sanitize_text_field($_POST['cleverpush_story_id']),
				);

				foreach ($meta as $key => $value) { // Cycle through the $events_meta array!
					if ( $post->post_type == 'revision' ) return; // Don't store custom data twice
					$value = implode(',', (array)$value); // If $value is an array, make it a CSV (unlikely)
					if (get_post_meta($post->ID, $key, FALSE)) { // If the custom field already has a value
						update_post_meta($post->ID, $key, $value);
					} else { // If the custom field doesn't have a value
						add_post_meta($post->ID, $key, $value);
					}
					if (!$value) delete_post_meta($post->ID, $key); // Delete if blank
				}
				remove_action('save_post', array( $this, 'save_post' ), 1 );
			}
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

				if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['cleverpush_action'] == 'synchronize_stories') {
					$response = wp_remote_get('https://api.cleverpush.com/channel/' . $selected_channel_id . '/stories', array('headers' => array('Authorization' => $api_key_private)));
					if ( is_wp_error( $response ) ) {
						?>
						<div class="error notice">
							<p><?php echo $response->get_error_message(); ?></p>
						</div>
						<?php
					} else if ($response['response']['code'] == 200 && isset($response['body'])) {
						$stories = json_decode($response['body'])->stories;
						if ($stories && count($stories) > 0) {
							foreach ($stories as $story) {
								$args = array(
									'meta_query' => array(
										array(
											'key' => 'cleverpush_story_id',
											'value' => $story->_id
										)
									),
									'post_type' => 'cleverpush_story'
								);
								$existing_posts = get_posts($args);

								if (count($existing_posts) < 1) {
									$post_id = wp_insert_post(array(
										'post_title' => $story->title,
										'post_name' => $story->title,
										'post_type' => 'cleverpush_story',
										'post_status' => 'publish'
									));
									if ($post_id) {
										add_post_meta($post_id, 'cleverpush_story_id', $story->_id);
									}
								} else {
									foreach ( $existing_posts as $post ) {
										wp_update_post( array(
											'ID'           => $post->ID,
											'post_title' => $story->title,
											'post_name' => $story->title,
										) );
									}
								}

								delete_transient('cleverpush_story_' . $story->_id . '_content');
								delete_transient('cleverpush_story_' . $story->_id . '_time');
							}

							?>

							<div class="notice updated"><p>Die Stories wurden erfolgreich synchronisiert.</p></div>

							<?php
						} else {
							echo '<div class="error notice"><p>Es wurden keine CleverPush Stories gefunden.</p></div>';
						}
					} else if (!empty($response['response'])) {
						echo '<div class="error notice"><p>API Error: ' . $response['response']['message'] . '</p></div>';
					}
				}
			}

			?>

			<div class="wrap">
				<h2>CleverPush</h2>
				<p><?php echo sprintf(__('You need to have a %s account with an already set up channel to use this plugin. Please then select your channel below.', 'cleverpush'), '<a target="_blank" href="https://cleverpush.com/">CleverPush</a>'); ?></p>
				<p><?php echo sprintf(__('The API key can be found in the %s.', 'cleverpush'), '<a href="https://cleverpush.com/app/settings/api" target="_blank">' . __('API settings', 'cleverpush') . '</a>'); ?></p>

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
							<th scope="row"><?php _e('Private API-Key', 'cleverpush'); ?></th>
							<td><input type="text" name="cleverpush_apikey_private"
									   value="<?php echo get_option('cleverpush_apikey_private'); ?>" style="width: 320px;"/></td>
						</tr>

					</table>

					<p class="submit"><input type="submit" class="button-primary"
											 value="<?php _e('Save Changes', 'cleverpush') ?>"/></p>
				</form>

				<?php if (!empty($api_key_private)): ?>
					<hr />
					<br />

					<form method="post" action="">
						<input type="hidden" name="cleverpush_action" value="synchronize_stories">
						<p class="submit"><input type="submit" class="button-secondary" value="CleverPush Stories synchronisieren" /></p>
					</form>
				<?php endif; ?>
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

		public function cleverpush_story_template($single) {
			global $post;

			if ($post->post_type == 'cleverpush_story') {
				remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
				remove_action( 'wp_print_styles', 'print_emoji_styles' );
				remove_action( 'wp_head', 'rest_output_link_wp_head' );
				remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
				remove_action( 'template_redirect', 'rest_output_link_header', 11, 0 );
				remove_action( 'wp_head', 'wp_generator' );
				remove_action( 'wp_head', 'rsd_link' );
				remove_action( 'wp_head', 'wlwmanifest_link');
				remove_theme_support( 'automatic-feed-links' );
				add_theme_support( 'title-tag' );
				add_filter('show_admin_bar', '__return_false');

				$cleverpushId = get_post_meta($post->ID, 'cleverpush_story_id', true);
				$cleverpushContent = get_transient( 'cleverpush_story_' . $cleverpushId . '_content' );
				$cleverpushTime = get_transient( 'cleverpush_story_' . $cleverpushId . '_time' );
				$apiKey = get_option('cleverpush_apikey_private');
				$channelId = get_option('cleverpush_channel_id');

				if ( false === $cleverpushContent || ($cleverpushTime < (time() - (60 * 30))) ) {
					$response = wp_remote_get( 'https://api.cleverpush.com/channel/' . $channelId . '/story/' . $cleverpushId, array( 'headers' => array( 'Authorization' => $apiKey )) );
					if ($response['response']['code'] == 200 && isset($response['body'])) {
						$story = json_decode($response['body']);
						$cleverpushTime = time();
						$cleverpushContent = $story->code . "\n<!-- cache time: " . date('Y-m-d H:m:s', $cleverpushTime) . " -->";
						set_transient( 'cleverpush_story_' . $cleverpushId . '_content', $cleverpushContent, 60 * 60 * 24 * 3 );
						set_transient( 'cleverpush_story_' . $cleverpushId . '_time', $cleverpushTime, 60 * 30 );
					}
				}

				add_action('wp_head', function() use ($cleverpushContent) {
					echo preg_replace("#</?(head)[^>]*>#i", "", $cleverpushContent);
				});

				$path = plugin_dir_path( __FILE__ ) . 'includes/story-template.php';
				if (file_exists($path)) {
					return $path;
				}
			}
			return $single;
		}
	}

	$cleverPush = new CleverPush( __FILE__ );

endif;
