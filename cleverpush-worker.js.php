<?php

// No need for the template engine
define( 'WP_USE_THEMES', false );

// Assuming we're in a subdir: "~/wp-content/plugins/cleverpush"
require_once( '../../../wp-load.php' );

header("Service-Worker-Allowed: /");
header("Content-Type: application/javascript");
header("X-Robots-Tag: none");

$cleverpush_id = get_option('cleverpush_channel_id');
if (!empty($cleverpush_id)) {
    echo "importScripts('https://static.cleverpush.com/channel/worker/" . $cleverpush_id . ".js');\n";
}
