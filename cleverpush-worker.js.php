<?php

$cleverpush_id = null;
$static_subdomain_suffix = '';

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

if ($wpLoaded) {
    $channel = get_option('cleverpush_channel_config');
    if (!empty($channel) && !empty($channel->hostingLocation)) {
        $static_subdomain_suffix = '-' . $channel->hostingLocation;
    }
}

header("Service-Worker-Allowed: /");
header("Content-Type: application/javascript");
header("X-Robots-Tag: none");

if (!empty($cleverpush_id)) {
    echo "importScripts('https://static" . $static_subdomain_suffix . ".cleverpush.com/channel/worker/" . $cleverpush_id . ".js');\n"; // phpcs:ignore

} else if ($wpLoaded) {
    echo "// error: no cleverpush channel id found\n"; // phpcs:ignore
}
