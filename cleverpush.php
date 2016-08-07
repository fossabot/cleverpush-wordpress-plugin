<?php
/*
Plugin Name: CleverPush
Plugin URI: https://cleverpush.com
Description: Send push notifications to your users right trough your website. Visit <a href="https://cleverpush.com">CleverPush</a> for more details.
Author: CleverPush
Version: 1.0
Author URI: https://cleverpush.com

This relies on the actions being present in the themes header.php and footer.php
* header.php code before the closing </head> tag
*   wp_head();
*
*/

//------------------------------------------------------------------------//
//---Config---------------------------------------------------------------//
//------------------------------------------------------------------------//

$clhf_header_cleverpush_script = '
<script>
(function(c,l,v,r,p,s,h){c[\'CleverPushObject\']=p;c[p]=c[p]||function(){(c[p].q=c[p].q||[]).push(arguments)},c[p].l=1*new Date();s=l.createElement(v),h=l.getElementsByTagName(v)[0];s.async=1;s.src=r;h.parentNode.insertBefore(s,h)})(window,document,\'script\',\'//CLEVERPUSH_IDENTIFIER.cleverpush.com/loader.js\',\'cleverpush\');
cleverpush(\'triggerOptIn\');
cleverpush(\'checkNotificationClick\');
</script>
';

//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//
add_action ( 'wp_head', 'clhf_headercode',1 );
add_action( 'admin_menu', 'clhf_plugin_menu' );
add_action( 'admin_init', 'clhf_register_mysettings' );
add_action( 'admin_notices','clhf_warn_nosettings');


//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//
// options page link
function clhf_plugin_menu() {
  add_options_page('CleverPush', 'CleverPush', 'create_users', 'clhf_cleverpush_options', 'clhf_plugin_options');
}

// whitelist settings
function clhf_register_mysettings() {
  register_setting('clhf_cleverpush_options', 'cleverpush_identifier');
}

//------------------------------------------------------------------------//
//---Output Functions-----------------------------------------------------//
//------------------------------------------------------------------------//
function clhf_headercode() {
  // runs in the header
  global $clhf_header_cleverpush_script;
  $cleverpush_identifier = get_option('cleverpush_identifier');

  if ($cleverpush_identifier){
      echo str_replace('CLEVERPUSH_IDENTIFIER', $cleverpush_identifier, $clhf_header_cleverpush_script); // only output if options were saved
  }
}
//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//
// options page
function clhf_plugin_options() {
?>

<div class="wrap">
  <h2>CleverPush</h2>
  <p>You need to have a <a target="_blank" href="https://cleverpush.com/">CleverPush</a> account with an already set up channel to use this plugin. Please then enter your channel identifier (subdomain) below.</p>
  <form method="post" action="options.php">
  <?php settings_fields( 'clhf_cleverpush_options' ); ?>
  <table class="form-table">
        <tr valign="top">
            <th scope="row">Your CleverPush channel identifier (subdomain):</th>
            <td><input type="text" name="cleverpush_identifier" value="<?php echo get_option('cleverpush_identifier'); ?>" /></td>
        </tr>
  </table>

  <p class="submit"><input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" /></p>

<?php
}

function clhf_warn_nosettings() {
  if (!is_admin())
      return;

  $clhf_option = get_option("cleverpush_identifier");
  if (!$clhf_option){
    echo "<div class='updated fade'><p><strong>CleverPush is almost ready.</strong> You have to enter your website identifier to make it work.</p></div>";
  }
}

?>
