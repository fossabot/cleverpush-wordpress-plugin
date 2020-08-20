<?php

$cleverpush_id = null;

if (!empty($_GET['channel']) && ctype_alnum($_GET['channel'])) {
    $cleverpush_id = $_GET['channel'];

} else {

    // No need for the template engine
    define( 'WP_USE_THEMES', false );

    // Assuming we're in a subdir: "~/wp-content/plugins/cleverpush"
    $wpConfigPath = '../../../wp-load.php';
    
    // maybe the user uses bedrock
    if (!file_exists( $wpConfigPath )) {
        $wpConfigPath = '../../../wp/wp-load.php';
    }

    if (file_exists( $wpConfigPath ) {
        require_once( $wpConfigPath );
    
        $cleverpush_id = get_option('cleverpush_channel_id');
    }
}

header("Service-Worker-Allowed: /");
header("Content-Type: application/javascript");
header("X-Robots-Tag: none");

if (!empty($cleverpush_id)) {
    echo "importScripts('https://static.cleverpush.com/channel/worker/" . $cleverpush_id . ".js');\n";

} else {
    echo "// error: no cleverpush channel id found\n";
}
