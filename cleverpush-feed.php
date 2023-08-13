<?php

$posts_cache_key = 'cleverpush_feed_posts';
$posts_cache_expiration = 60 * 60; // 1 hour

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

if (!$wpLoaded) {
    die('error: wp-load.php not found');
}

$feed_title = 'CleverPush Feed';
if (function_exists('wp_date') ) {
    $diff_time = wp_date('O');
} else {
    $diff_time = date_i18n('O');
}

$update_period = 'hourly';
$update_frequency = '1';

header('Content-Type: text/xml; charset=UTF-8');

$feed_link = site_url() . '/' . trim(parse_url(plugin_dir_url(__FILE__), PHP_URL_PATH), '/');

$article_count = get_option('cleverpush_feed_maximum_articles');
if (empty($article_count)) {
    $article_count = 100;
}

$posts_query = [
  'numberposts' => $article_count,
  'orderby' => 'date',
  'order' => 'DESC',
  'post_status' => 'publish',
];

$article_maximum_days = get_option('cleverpush_feed_maximum_days');
if (!empty($article_maximum_days)) {
    $posts_query['date_query'] = [
        'after' => date('Y-m-d', strtotime('-' . $article_maximum_days . ' days')),
    ];
}

$posts = get_transient($posts_cache_key);
if (empty($posts)) {
    $posts = get_posts($posts_query);
    set_transient($posts_cache_key, $posts, $posts_cache_expiration);
}

?><?php echo '<?'; ?>xml version="1.0" encoding="UTF-8"<?php echo '?>'; ?>

<rss version="2.0"
xmlns:content="http://purl.org/rss/1.0/modules/content/"
xmlns:wfw="http://wellformedweb.org/CommentAPI/"
xmlns:dc="http://purl.org/dc/elements/1.1/"
xmlns:atom="http://www.w3.org/2005/Atom"
xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
>
<channel>
<title><?php echo esc_html($feed_title . ' &#187; ' . get_bloginfo('name')); ?></title>
<atom:link href="<?php echo esc_url($feed_link); ?>" rel="self" type="application/rss+xml" />
<link><?php echo esc_url($feed_link); ?></link>
<description><?php echo esc_html($feed_title); ?></description>
<lastBuildDate><?php echo esc_html(mysql2date('D, d M Y H:i:s ', $posts[0]->post_date, false) . $diff_time); ?></lastBuildDate>
<language><?php echo esc_html(get_bloginfo('language')); ?></language>
<sy:updatePeriod><?php echo esc_html($update_period); ?></sy:updatePeriod>
<sy:updateFrequency><?php echo esc_html($update_frequency); ?></sy:updateFrequency>
<?php
foreach ( $posts as $post ) {
    $pid = $post->ID;
    $link_url = get_permalink($pid);
  
    $attachment_url = get_the_post_thumbnail_url($pid);

    if (function_exists('wp_date') ) {
        $diff_time  = wp_date('O');
    } else {
        $diff_time  = date_i18n('O');
    }

    $categories = get_the_category($pid);
  
    ?>
  <item>
  <title><?php echo esc_html($post->post_title); ?></title>
  <guid isPermaLink="false"><?php echo site_url() . '/?p=' . esc_html($post->ID); ?></guid>
  <link><?php echo esc_url($link_url); ?></link>
  <pubDate><?php echo esc_html(mysql2date('D, d M Y H:i:s ', $post->post_date, false) . $diff_time); ?></pubDate>
  <description><![CDATA[<?php echo esc_html(get_the_excerpt($post->ID)); ?>]]></description>
    <?php
    if (! empty($attachment_url) ) {
        ?><enclosure url="<?php echo esc_url($attachment_url); ?>" />
        <?php
    }

    foreach ($categories as $category) {
        ?>
       <category><![CDATA[<?php echo esc_html($category->cat_name); ?>]]></category>
        <?php
    }
    ?>
  </item>
    <?php

}
?>
</channel>
</rss>
