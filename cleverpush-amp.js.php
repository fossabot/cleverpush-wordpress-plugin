<?php

$cleverpush_id = null;
$cleverpush_amp_cache_time = 60 * 60 * 12;

// No need for the template engine
define('WP_USE_THEMES', false);

$wpLoaded = false;

// Assuming we're in a subdir: "~/wp-content/plugins/cleverpush"
$wpConfigPath = '../../../wp-load.php';

// maybe the user uses bedrock
if (!file_exists($wpConfigPath)) {
    $wpConfigPath = '../../../wp/wp-load.php';
}

if (file_exists($wpConfigPath)) {
    include_once $wpConfigPath;
    $wpLoaded = true;
}

if ($wpLoaded) {
    $cleverpush_id = get_option('cleverpush_channel_id');
} else if (!empty($_GET['channel']) && ctype_alnum($_GET['channel'])) { // phpcs:ignore
    // We can't use sanitize_text_field here as the function is not defined here as wp-load is not included, yet. Input is sanitized via ctype_alnum.
    $cleverpush_id = $_GET['channel']; // phpcs:ignore
}

header("Content-Type: application/javascript");
header("X-Robots-Tag: none");

if (!empty($cleverpush_id) && $wpLoaded) {
    $cached_script = get_transient('cleverpush_amp_script_' . $cleverpush_id);
    if (!empty($cached_script)) {
        echo $cached_script; // phpcs:ignore
        die();
    }

    $response = wp_remote_get(
        'https://static.cleverpush.com/channel/amp/' . $cleverpush_id . '.js', [
        'timeout' => 10, // phpcs:ignore
        ]
    );
    if ($response['response']['code'] == 200 && isset($response['body'])) {
        echo $response['body']; // phpcs:ignore

        set_transient('cleverpush_amp_script_' . $cleverpush_id, $response['body'], $cleverpush_amp_cache_time);
    }

} else {
  echo "// error: no cleverpush channel id found\n"; // phpcs:ignore
}
