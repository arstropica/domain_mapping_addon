<?php
/*
Plugin Name: WordPress MU Domain Mapping htaccess Addon
Plugin URI: http://digitalsherpa.com
Description: Wildcard htaccess Redirect Addon for WordPress MU Domain Mapping. Required mod_proxy to be active.
Version: 1.0
Author: Akin Williams
Author URI: http://nci.com
*/

/*ini_set('display_errors', '1');
error_reporting(E_ALL);*/
// Definitions
define('NPP_PLUGIN_FILE', __FILE__);
define('WDMHA_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WDMHA_PLUGIN_PATH', trailingslashit(dirname(__FILE__)));
define('WDMHA_PLUGIN_DIR', trailingslashit(WP_PLUGIN_URL) . str_replace(basename(__FILE__), "", plugin_basename(__FILE__)));
define('WDMHA_LOCAL_PATH', str_replace(get_bloginfo('url'), '', WDMHA_PLUGIN_DIR));

// Default Path to Root Site Path
$wdmha_default_path = "/home/root_account/path/to/web/root/";

$user_root_path = get_site_option('wdmha_root', false, false);
if ($user_root_path === false){
    define('WDMHA_ROOT_SITE_PATH', $wdmha_default_path);
    add_site_option('wdmha_root', $wdmha_default_path);
} elseif (! file_exists($user_root_path)){
    define('WDMHA_ROOT_SITE_PATH', $wdmha_default_path);
    add_action('network_admin_notices', 'wdmha_badpath_msg');
} else { 
    define('WDMHA_ROOT_SITE_PATH', get_site_option('wdmha_root', false, false));
}
if ( !defined( 'WP_PLUGIN_DIR' ) )
    define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );

register_activation_hook( __FILE__, 'wdmha_dependentplugin_activate' );
add_action( 'admin_init', 'init_wdmha' );
add_action( 'init', 'wdmha_override_dm');
add_action( 'network_admin_menu', 'wdmha_add_admin_page' );

function wdmha_override_dm(){
    remove_action( 'template_redirect', 'redirect_to_mapped_domain' );
    add_action( 'template_redirect', 'wdmha_redirect_to_mapped_domain' );
}

function wdmha_redirect_to_mapped_domain() {
    global $current_blog, $wpdb;

    // don't redirect post previews
    if ( isset( $_GET['preview'] ) && $_GET['preview'] == 'true' )
        return;

    if ( !isset( $_SERVER[ 'HTTPS' ] ) )
        $_SERVER[ 'HTTPS' ] = 'off';
    $protocol = ( 'on' == strtolower( $_SERVER['HTTPS'] ) ) ? 'https://' : 'http://';
    $url = domain_mapping_siteurl( false );
    /*var_dump($current_blog);
    echo "<br><br>";
    var_dump($url);
    echo "<br><br>";
    var_dump( get_blog_details( array( 'domain' => 'test1.showcase1.devsherpa.com' ), false ));
    echo "<br><br>";
    var_dump($_SERVER['HTTP_HOST']);
    echo "<br><br>";
    var_dump($_SERVER['REQUEST_URI']);
    echo "<br><br>";
    var_dump($_SERVER['HTTP_X_FORWARDED_FOR']);
    echo "<br><br>";
    var_dump($_SERVER['HTTP_X_FORWARDED_HOST']);
    echo "<br><br>";
    var_dump($_SERVER['HTTP_X_FORWARDED_SERVER']);
    die();*/
    if ( ($url && $url != untrailingslashit( $protocol . $current_blog->domain . $current_blog->path )) && ($url != (untrailingslashit( $protocol . $_SERVER['HTTP_X_FORWARDED_HOST'])))) {
        $redirect = get_site_option( 'dm_301_redirect' ) ? '301' : '302';
        if ( ( defined( 'VHOST' ) && constant( "VHOST" ) != 'yes' ) || ( defined( 'SUBDOMAIN_INSTALL' ) && constant( 'SUBDOMAIN_INSTALL' ) == false ) ) {
            $_SERVER[ 'REQUEST_URI' ] = str_replace( $current_blog->path, '/', $_SERVER[ 'REQUEST_URI' ] );
        }
        header( "Location: {$url}{$_SERVER[ 'REQUEST_URI' ]}", true, $redirect );
        exit;
    }
}

function wdmha_dependentplugin_activate() {
    $wpmu_plugin_dir = WP_CONTENT_DIR . '/mu-plugins';
    require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
    if ( ! is_multisite() ) {
        deactivate_plugins( __FILE__);
        exit ('WordPress MU Domain Mapping htaccess Addon requires a WordPress Network to function.');
    }
    if ( file_exists( $wpmu_plugin_dir . '/domain_mapping.php' ) || file_exists( $wpmu_plugin_dir . '/wordpress-mu-domain-mapping/domain_mapping.php' ) ) {
        // Silence is golden!
    } else {
        // deactivate dependent plugin
        deactivate_plugins( __FILE__);
        //   throw new Exception('Requires another plugin!');
        //  exit();
        exit (' WordPress MU Domain Mapping htaccess Addon requires the "WordPress MU Domain Mapping" plugin to be installed as an MU Plugin.');
    }
}

function wdmha_check_htaccess(){
    $htstate = false;
    $htaccess = WDMHA_ROOT_SITE_PATH . '.htaccess';
    #$htaccess = ABSPATH . 'test_htaccess.txt';
    $htstate = wdmha_parse_htaccess_state($htaccess);
    $orig = get_site_option('wdmha_orig_status', false, false);
    if ($orig === false){
        add_site_option('wdmha_orig_status', $htstate);
    }
    $saved = get_site_option('wdmha_status', false, false);
    if ($saved === false){
        add_site_option('wdmha_status', $htstate);
    } else {
        update_site_option('wdmha_status', $htstate);
    }
    wdmha_process_htaccess();
    return $htstate;
}

function wdmha_save_backup($htaccess=''){
    if (empty($htaccess)) $htaccess = WDMHA_ROOT_SITE_PATH . '.htaccess';
    $backup = get_site_option('wdmha_orig_backup', false, false);
    $status = get_site_option('wdmha_orig_backup_status', false, false);
    $orig_content = file_get_contents($htaccess);
    $serialized = serialize($orig_content);
    if ($status === false){
        if ($backup === false) add_site_option('wdmha_orig_backup', $serialized);
        else update_site_option('wdmha_orig_backup', $serialized);
        add_site_option('wdmha_orig_backup_status', date("m-d-Y"));
    } else {
        update_site_option('wdmha_orig_backup_status', date("m-d-Y"));
        update_site_option('wdmha_orig_backup', $serialized);
    }
    wdmha_backupsuccess_msg();
    return empty($orig_content) ? false : true;
}

function wdmha_restore_backup($htaccess=''){
    if (empty($htaccess)) $htaccess = WDMHA_ROOT_SITE_PATH . '.htaccess';
    $backup = get_site_option('wdmha_orig_backup', false, false);
    if ($backup !== false){
        $orig_content = unserialize($backup);
        if($orig_content){
            $htcreate = wdmha_openfile($htaccess, "WRITE", (str_replace("\n\n", "\n", $orig_content)));
            if ($htcreate !== true) {
                wdmha_locked_msg();
                return false;
            } else {
                wdmha_backuprestore_msg();
                return true;
            }                   
        } else {
            wdmha_backupnotfound_msg();
            return false;
        }
    } else {
        wdmha_backupnotfound_msg();
        return false;
    }
}

function wdmha_parse_htaccess($htaccess = NULL){
    if (empty($htaccess)){
        $htaccess = WDMHA_ROOT_SITE_PATH . '.htaccess';
    }
    $wdmha_content_arry = false;
    $begin = "# BEGIN - WORDPRESS MU DOMAIN MAPPING HTACCESS\n##############################################\nOptions +FollowSymLinks\nRewriteEngine on\nRewriteBase /";
    $end = "##############################################\n# END - WORDPRESS MU DOMAIN MAPPING HTACCESS";
    $wdmha_pattern = "/((" . preg_quote($begin, '/') . ")|($end))/s";
    // Check if New HTACCESS
    if (! file_exists($htaccess)){
        return array('before_plugin' => "", 'plugin_begin' => $begin, 'plugin' => "", 'plugin_end' => $end, 'after_plugin' => "");
    }
    // Check if WDMHA written
    $orig_content = file_get_contents($htaccess);
    if (! preg_match($wdmha_pattern, $orig_content, $wdmha_exists)){
        $wdmha_content_arry = array('before_plugin' => "", 'plugin_begin' => $begin, 'plugin' => "", 'plugin_end' => $end, 'after_plugin' => $orig_content);
    } else {
        // Check if damaged HTACCESS
        $before_wdmha_content_arry = explode($begin, $orig_content);
        if (count($before_wdmha_content_arry) > 1){
            $after_wdmha_content_arry = explode($end, $before_wdmha_content_arry[1]);
            // If WDMHA is already written 
            if (count($after_wdmha_content_arry) > 1) {
                $wdmha_content_arry = array('before_plugin' => $before_wdmha_content_arry[0], 'plugin_begin' => $begin, 'plugin' => $after_wdmha_content_arry[0], 'plugin_end' => $end, 'after_plugin' => $after_wdmha_content_arry[1]);
            } 
        } 
    }
    return $wdmha_content_arry;
}

function wdmha_parse_htaccess_state($htaccess=NULL){
    if (empty($htaccess)) $htaccess = WDMHA_ROOT_SITE_PATH . '.htaccess';
    $mudomain = strtoupper(wdmha_remove_http(network_site_url()));
    $begin = "# BEGIN - WORDPRESS MU DOMAIN MAPPING HTACCESS\n##############################################\nOptions +FollowSymLinks\nRewriteEngine on\nRewriteBase /";
    $end = "##############################################\n# END - WORDPRESS MU DOMAIN MAPPING HTACCESS";
    $begin_mu = "# BEGINMU - " . $mudomain . "\n";
    $end_mu = "# ENDMU - " . $mudomain . "\n";
    $wdmha_content_arry = wdmha_parse_htaccess($htaccess);
    // Writable ?
    if (! file_exists($htaccess)){
            $htstate = "new";
    } else {
        // New ?
        if (! is_writable($htaccess)){
            $htstate = "locked";
        } elseif ((strpos($wdmha_content_arry['plugin_begin'], $begin) === false) && (strpos($wdmha_content_arry['plugin_end'], $end) === false)){
            $htstate = "unedited";   
        } elseif((strpos($wdmha_content_arry['plugin_begin'], $begin) !== false) xor (strpos($wdmha_content_arry['plugin_end'], $end)  !== false)){
            $htstate = "damaged";
        } elseif ((strpos($wdmha_content_arry['plugin'], $begin_mu) === false) && (strpos($wdmha_content_arry['plugin'], $end_mu) === false)){
            $htstate = "newmu";   
        } elseif (((strpos($wdmha_content_arry['plugin'], $begin_mu) === false) xor (strpos($wdmha_content_arry['plugin'], $end_mu) === false)) || (preg_match("/(?:#\s(?:(?<!BEGINMU).)*?\n)([^#]*?(?=\n# (?!ENDMU)))/s", $wdmha_content_arry['plugin']))) {
            $htstate = "damagedmu";
        } else {
            $htstate = "edited";
        }      
    }
    return $htstate;
}

function wdmha_process_htaccess(){
    global $wpdb;
    $status = get_site_option('wdmha_status', false, false);
    $orig_status = get_site_option('wdmha_orig_status', false, false);
    $htaccess = WDMHA_ROOT_SITE_PATH . '.htaccess';
    $rows = $wpdb->get_results( "SELECT * FROM {$wpdb->dmtable} ORDER BY id DESC LIMIT 0,20" );
    $domains = array();
    if ($states === false){
        add_site_option('wdmha_domain_states', serialize(array()));
    }
    $states_serialized = get_site_option('wdmha_domain_states', serialize(array()), false);
    $states = unserialize($states_serialized);
    foreach($rows as $row){ 
        $domains[$row->blog_id] = $row->domain;
        $states[$row->blog_id] = (empty($states[$row->blog_id])) ? 0 : $states[$row->blog_id];
    }
    #$htaccess = ABSPATH . 'test_htaccess.txt';
    $begin = "# BEGIN - WORDPRESS MU DOMAIN MAPPING HTACCESS\n##############################################\nOptions +FollowSymLinks\nRewriteEngine on\nRewriteBase /";
    $end = "##############################################\n# END - WORDPRESS MU DOMAIN MAPPING HTACCESS";
    $wdmha_content_arry = wdmha_parse_htaccess($htaccess);
    if (! is_array($wdmha_content_arry)){
        add_action('network_admin_notices', 'wdmha_damaged_msg');
        return false;
    }
    // Backup
    if ($orig_status == 'unedited'){
        wdmha_save_backup();
        update_site_option('wdmha_orig_status', 'backup');
    }
    switch($status){
        case 'damaged':
            $pattern = "/(#\s(?:(?<!BEGINMU).)*?\n)([^#]*?(?=\n# (?!ENDMU)))/s";
            if (preg_match($pattern, $wdmha_content_arry['plugin'], $matches)){
                $fixed = preg_replace($pattern, "", $wdmha_content_arry['plugin']);
                $wdmha_content_arry['plugin'] = $fixed;
            } else {
                add_action('network_admin_notices', 'wdmha_damaged_msg');
            }
            break;
        case 'new':
            add_action('network_admin_notices', 'wdmha_new_msg');
            $create = wdmha_openfile($htaccess, "WRITE", $begin . "\n" . $end);
            if ($create === true) $status = 'edited';
            else $status = 'locked';
            break;
        case 'locked':
            add_action('network_admin_notices', 'wdmha_locked_msg');
            break;
        case 'edited':
            wdmha_update_htaccess($domains, $states, true);
            #add_action('network_admin_notices', 'wdmha_edited_msg');
            break;
        case false:
            $status = wdmha_check_htaccess();
            return;
            break;
    }
    $saved = get_site_option('wdmha_status', false, false);
    if ($saved === false){
        add_site_option('wdmha_status', $status);
    } else {
        update_site_option('wdmha_status', $status);
    }
}

function wdmha_fix_htaccess_orphan($damaged_content, $newrules){
    $output = false;
    $mudomain = strtoupper(wdmha_remove_http(network_site_url()));
    $begin_mu = "# BEGINMU - " . $mudomain . "\n";
    $end_mu = "# ENDMU - " . $mudomain . "\n";
    // Orphan Rules
    $pattern = "/(?:#\s(?:(?<!BEGINMU).)*?\n)([^#]*?(?=\n# (?!ENDMU)))/s";
    if (preg_match($pattern, $damaged_content, $matches)){
        $fragment = $matches[1];
        if ($fragment) {
            $damaged_content_arry = preg_split($pattern, $damaged_content, NULL, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
            $damaged_rules_index = array_search($fragment, $damaged_content_arry);
        }
    }
    if ($damaged_rules_index) {
        $damaged_content_arry[$damaged_rules_index] = $begin_mu . $newrules . "\n" . $end_mu; 
    } else {
        add_action('network_admin_notices', 'wdmha_damaged_orphan_msg');
        return false;
    }
    $output = str_replace("\n\n", "\n", implode("\n", $damaged_content_arry));
    return $output;
}

function wdmha_fix_htaccess_mu($damaged_content, $newrules){
    $output = false;
    $mudomain = strtoupper(wdmha_remove_http(network_site_url()));
    $begin_mu = "# BEGINMU - " . $mudomain . "\n";
    $end_mu = "# ENDMU - " . $mudomain . "\n";
    if ((strpos($damaged_content, $begin_mu) !== false)) {
        $pattern = "/(?:$begin_mu((?:(?!#\sENDMU.*#\sBEGINMU)).*?)(?:#))/s";
        if (preg_match($pattern,$damaged_content, $matches)){
            $fragment = $matches[1];
            if ($fragment) {
                $damaged_content_arry = preg_split($pattern, $damaged_content, NULL, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
                $damaged_rules_index = array_search($fragment, $damaged_content_arry);
            }
        } else {
            add_action('network_admin_notices', 'wdmha_damaged_msg');
            return false;
        }
    } elseif(strpos($damaged_content, $end_mu) !== false) {
        $pattern = "/(?:\n([^#]*?)(?:$end_mu))/s";
        if (preg_match($pattern,$damaged_content, $matches)){
            $fragment = @$matches[1];
            if ($fragment) {
                $damaged_content_arry = preg_split($pattern, $damaged_content, NULL, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
                $damaged_rules_index = array_search($fragment, $damaged_content_arry);
            }
        } else {
            add_action('network_admin_notices', 'wdmha_damaged_msg');
            return false;
        }
    } else {
        add_action('network_admin_notices', 'wdmha_damaged_msg');
        return false;
    }
    if ($damaged_rules_index) {
        $damaged_content_arry[$damaged_rules_index] = $begin_mu . $newrules . "\n" . $end_mu; 
    } else {
        add_action('network_admin_notices', 'wdmha_damaged_msg');
        return false;
    }
    $output = str_replace("\n\n", "\n", implode("\n", $damaged_content_arry));
    return $output;
}

function wdmha_replace_rules_mu($mublock, $newrules){
    $mudomain = strtoupper(wdmha_remove_http(network_site_url()));
    $begin_mu = "# BEGINMU - " . $mudomain . "\n";
    $end_mu = "# ENDMU - " . $mudomain . "\n";
    $edited_content = $mublock;
    $fragment1 = explode($begin_mu, $edited_content);
    $fragment2 = preg_split("/".$end_mu."/s", $fragment1[1], NULL, PREG_SPLIT_NO_EMPTY);
    if (strcmp(@$fragment2[0], $fragment1[1]) !== 0){
        $before_mu = $fragment1[0];
        unset($fragment2[0]);
        return $before_mu . $begin_mu . $newrules . "\n" . $end_mu . implode("\n", $fragment2);
    } else {
        return false;
    }
}

function wdmha_update_htaccess($domains, $states, $write=true){
    if ( ! is_array($domains) || ! is_array($states) ) return false;
    $htaccess = WDMHA_ROOT_SITE_PATH . '.htaccess';
    #$htaccess = ABSPATH . 'test_htaccess.txt';
    $orig_content = file_get_contents($htaccess);
    $mudomain = strtoupper(wdmha_remove_http(network_site_url()));
    $htstate = false;
    $begin = "# BEGIN - WORDPRESS MU DOMAIN MAPPING HTACCESS\n##############################################\nOptions +FollowSymLinks\nRewriteEngine on\nRewriteBase /";
    $end = "##############################################\n# END - WORDPRESS MU DOMAIN MAPPING HTACCESS";
    $begin_mu = "# BEGINMU - " . $mudomain . "\n";
    $end_mu = "# ENDMU - " . $mudomain . "\n";
    $newrules = wmdha_generate_rules($domains, $states);
    $wdmha_content_arry = wdmha_parse_htaccess($htaccess);
    $htstate = wdmha_parse_htaccess_state($htaccess);
    switch ($htstate){
        case 'locked' :
            break;
        case 'new':
            // Add New MU Rules
            $wdmha_content_arry['plugin'] .= $begin_mu . $newrules . $end_mu;
            break;
        case 'newmu':
        case 'unedited':
            $wdmha_content_arry['plugin'] .= $begin_mu . $newrules . "\n" . $end_mu;
            break;
        case 'damaged':
            add_action('network_admin_notices', 'wdmha_damaged_msg');
            break;
        case 'damagedmu':
            $fixed = wdmha_fix_htaccess_mu($wdmha_content_arry['plugin'], $newrules);
            if ($fixed){
                $edited = wdmha_replace_rules_mu($fixed, $newrules);
                $wdmha_content_arry['plugin'] = $edited;
            }
            // Orphan Rules
            if (preg_match("/(?:#\s(?:(?<!BEGINMU).)*?\n)([^#]*?(?=\n# (?!ENDMU)))/s", $wdmha_content_arry['plugin'])){
                $fixed = wdmha_fix_htaccess_orphan($wdmha_content_arry['plugin'], $newrules);
                if ($fixed){
                    $edited = wdmha_replace_rules_mu($fixed, $newrules);
                    $wdmha_content_arry['plugin'] = $edited;
                }
            }
            #return $wdmha_content_arry['plugin'];
            break;
        case 'edited':
            $edited = wdmha_replace_rules_mu($wdmha_content_arry['plugin'], $newrules);
            $wdmha_content_arry['plugin'] = ($edited) ? $edited : $wdmha_content_arry['plugin'];
            break;
    }
    // Write File
    if ($write === true){
        $htcreate = wdmha_openfile($htaccess, "WRITE", (str_replace("\n\n", "\n", implode("\n", $wdmha_content_arry))));
        if ($htcreate !== true) {
            wdmha_locked_msg();
        } else {
            wdmha_edited_msg();
        }                   
    }
    return (str_replace("\n\n", "\n", implode("\n", $wdmha_content_arry)));
}    

function wmdha_generate_rules($domains, $states){
    if ( ! is_array($domains) || ! is_array($states) || count($domains) == 0 || count($states) == 0) return false;
    $enabled = get_site_option('wdmha_enabled', 0, false);
    if ($enabled == "0") return false;
    $rewrite_conds = array();
    $rules = false;
    foreach($domains as $blogid => $domain){
        $blog_details = get_blog_details($blogid, 'domain');
        if ($states[$blogid] == 1) {
            $siteurl = "http://" . untrailingslashit($blog_details->domain);
            #$siteurl = untrailingslashit(network_site_url());
            #$rewrite_conds[$blogid] = "RewriteCond %{HTTP_HOST} ^" . preg_quote($domain) . "$ [NC,OR]";
            $rewrite_conds[$blogid] = "RewriteCond %{HTTP_HOST} ^" . preg_quote($domain) . "$ [NC]";
            #$rewrite_conds[$blogid] = "RewriteCond %{HTTP:X-Forwarded-Host} ^!" . preg_quote($domain) . "$ [NC]";
            #$rewrite_conds[$blogid] .= "\nRewriteCond %{HTTP_REFERER} ^!" . preg_quote($domain) . "$ [NC]\n";
            $rewrite_conds[$blogid] .= "\nRewriteRule ^(.*)$ " . $siteurl . "/$1 [L,P]\n";
        }
    }
    if (count($rewrite_conds) > 0){
        #reset($rewrite_conds);
        #end($rewrite_conds);
        #$lastidx = key($rewrite_conds);
        #$rewrite_conds[$lastidx] = "RewriteCond %{HTTP_HOST} ^" . preg_quote($domains[$lastidx]) . "$ [NC]";
        #$muurl = untrailingslashit(network_site_url());
        #$rewrite_rule = "\nRewriteRule ^(.*)$ " . $muurl . "/$1 [R=302,L]";
        #$rules = implode("\n", $rewrite_conds) . $rewrite_rule;
        $rules = implode("\n", $rewrite_conds);
    }
    return $rules;
}

function wdmha_openfile($file, $mode, $input) {
    if ($mode == "READ") {
        if (file_exists($file)) {
            $handle = fopen($file, "r"); 
            $output = fread($handle, filesize($file));
            return $output; // output file text
        } else {
            return false; // failed.
        }
    } elseif ($mode == "WRITE") {
        $handle = @fopen($file, "w");
        if (!@fwrite($handle, $input)) {
            return false; // failed.
        } else {
            return true; //success.
        }
    } elseif ($mode == "READ/WRITE") {        
        if (file_exists($file) && isset($input)) {
            $handle = fopen($file, "r+");
            $read = fread($handle, filesize($file));
            $data = $read.$input;
            if (!fwrite($handle, $data)) {
                return false; // failed.
            } else {
                return true; // success.
            }
        } else {
            return false; // failed.
        }
    } else {
        return false; // failed.
    }
    fclose($handle); 
}

function init_wdmha(){
    global $wpdb;
    if (empty($wpdb->dmtable)) $wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';
    if (is_network_admin()){
        if (wdmha_site_admin('manage_options')) {
            $status = wdmha_check_htaccess();
        }
    }
}
function wdmha_admin_message($message, $errormsg = false)
{
    if ($errormsg) {
        echo '<div id="message" class="error">';
    }
    else {
        echo '<div id="message" class="updated fade">';
    }

    echo "<p><strong>$message</strong></p></div>";
}
function wdmha_damaged_msg()
{
    // Only show to admins
    if (current_user_can('manage_options')) {
        wdmha_admin_message("The Domain Mapping htaccess file appears to be unwriteable, damaged or incomplete.", true);
    }
}

function wdmha_damaged_orphan_msg()
{
    // Only show to admins
    if (current_user_can('manage_options')) {
        wdmha_admin_message("The Domain Mapping htaccess file appears to have contained orphan rules outside the control of the plugin. The problem could not be fixed.", true);
    }
}

function wdmha_new_msg()
{
    // Only show to admins
    if (current_user_can('manage_options')) {
        wdmha_admin_message("Congrats, your have created a Domain Mapping htaccess file.", false);
    }
}

function wdmha_backupexists_msg()
{
    // Only show to admins
    if (current_user_can('manage_options')) {
        wdmha_admin_message("An htaccess backup exists.", false);
    }
}

function wdmha_backuprestore_msg()
{
    // Only show to admins
    if (current_user_can('manage_options')) {
        wdmha_admin_message("The backup htaccess has been restored.", false);
    }
}

function wdmha_locked_msg()
{
    // Only show to admins
    if (current_user_can('manage_options')) {
        wdmha_admin_message("Sorry, your Domain Mapping htaccess file appears to be unwritable.", true);
    }
}

function wdmha_edited_msg()
{
    // Only show to admins
    if (current_user_can('manage_options')) {
        wdmha_admin_message("The Domain Mapping htaccess has been edited.", false);
    }
}

function wdmha_badpath_msg()
{
    // Only show to admins
    if (current_user_can('manage_options')) {
        wdmha_admin_message("The WordPress MU Domain Mapping HTACCESS root path is invalid. Using Default: " . WDMHA_ROOT_SITE_PATH, true);
    }
}

function wdmha_backupnotfound_msg(){
    // Only show to admins
    if (current_user_can('manage_options')) {
        wdmha_admin_message("Sorry, a backup of the htaccess file could not be found.", true);
    }
}

function wdmha_backupsuccess_msg(){
    // Only show to admins
    if (current_user_can('manage_options')) {
        wdmha_admin_message("A backup has been made of the htaccess file.", false);
    }
}

function wdmha_add_admin_page(){
    global $current_site, $wpdb, $wp_db_version, $wp_version;
    if ( wdmha_site_admin()) {
        add_submenu_page('settings.php', __( 'Domain Mapping Addon', 'wordpress-mu-domain-mapping-addon' ), 'Domain Mapping Addon', 'manage_options', 'wdmha_admin_page', 'wdmha_admin_page');
    }
}

function wdmha_admin_page() {
    global $wpdb, $current_site;
    $htaccess = WDMHA_ROOT_SITE_PATH . '.htaccess';
    #$htaccess = ABSPATH . '/test_htaccess.txt';
    $output = false;
    $rows = $wpdb->get_results( "SELECT * FROM {$wpdb->dmtable} ORDER BY id DESC LIMIT 0,20" );
    $domains = array();
    $states = get_site_option('wdmha_domain_states', false, false);
    if ($states === false){
        add_site_option('wdmha_domain_states', serialize(array()));
    }
    $states_serialized = get_site_option('wdmha_domain_states', serialize(array()), false);
    $states = unserialize($states_serialized);
    foreach($rows as $row){ 
        $domains[$row->blog_id] = $row->domain;
        $states[$row->blog_id] = (empty($states[$row->blog_id])) ? 0 : $states[$row->blog_id];
    }
    if ( false == dm_site_admin() ) { // paranoid? moi?
        return false;
    }

    if ( $current_site->path != "/" ) {
        wp_die( sprintf( __( "<strong>Warning!</strong> This plugin will only work if WordPress is installed in the root directory of your webserver. It is currently installed in &#8217;%s&#8217;.", "wordpress-mu-domain-mapping" ), $current_site->path ) );
    }
    
    $enabled = get_site_option('wdmha_enabled', 0, false);
    if ($enabled === 0){
        add_site_option('wdmha_enabled', 0);
    }

    if (! empty($_POST['action'])){
        check_admin_referer( 'domain_mapping_addon' );
        switch( $_POST[ 'action' ] ) {
            case "switch":
                if (isset($_POST['wdmha_enabled'])){
                    if ($enabled == 0) {
                        update_site_option('wdmha_enabled', 1);
                        $enabled = 1;
                    }
                    else {
                        update_site_option('wdmha_enabled', 0);
                        $enabled = 0;
                    }
                } 
                $output = wdmha_update_htaccess($domains, $states);    
            break;
            case "update":
                $wdmha_root = $_POST['wdmha_root'];
                if (! empty($_POST['wdmha_root'])){
                    if (get_site_option('wdmha_root', false, false) === false){
                        add_site_option('wdmha_root', $_POST['wdmha_root']);
                    } else {
                        update_site_option('wdmha_root', $_POST['wdmha_root']);
                    }
                }
                $domains = $_POST['domain'];
                $states = @$_POST['wdmha_enable'];
                if (!empty($domains)){
                    foreach($domains as $blogid => $domain){
                        if (empty($states[$blogid])) $states[$blogid] = 0;
                    }
                    update_site_option('wdmha_domain_states', serialize($states));
                }
                $output = wdmha_update_htaccess($domains, $states);    
            break;
            case 'backup':
                $br_action = $_POST['backup'];
                switch ($br_action){
                    case 'Backup':
                        wdmha_save_backup($htaccess);
                        break;
                    case 'Restore':
                        wdmha_restore_backup($htaccess);
                        break;
                }
                $output = wdmha_update_htaccess($domains, $states, false);    
            break;
        }
    } else {
        $output = wdmha_update_htaccess($domains, $states, false);    
    }
    
    echo '<h2>' . __( 'Domain Mapping Addon Admin', 'wordpress-mu-domain-mapping-addon' ) . '</h2>';
    echo '<form method="POST">';
    echo '<input type="hidden" name="action" value="switch" />';
    wp_nonce_field( 'domain_mapping_addon' );
    echo '<p>';
    echo "<input type=\"submit\" value=\"" . (($enabled == 0) ? "Enable Plugin" : "Disable Plugin") . "\" name=\"wdmha_enabled\" class=\"action button-" . (($enabled == 0) ? "primary" : "secondary") . "\" />\n";
    echo '</p>';
    echo "</form><hr />";
    $backup = get_site_option('wdmha_orig_backup', false, false);
    $backup_status = get_site_option('wdmha_orig_backup_status', false, false);
    $preview = "<p><textarea disabled='disabled' style='background-color: #EEE; border: 1px solid #CCC' cols='100'  rows='10'>" . (file_get_contents($htaccess)) . "</textarea></p>\n";
    echo "<div style='width: 100%'>\n";
    echo "<form method='POST' style='float:left; display: inline-block; padding: 10px; margin: 10px 0; border: 1px solid #BBB;'>\n";
    wp_nonce_field( 'domain_mapping_addon' );
    if ($backup !== false){
        echo "<div id=\"message\" class=\"updated fade\"><p>A backup of your root htaccess file" . (($backup_status) ? " dated " . $backup_status : "") . " exists. Do you want to restore it?</p></div>\n";
        echo $preview;
        echo "<input name=\"backup\" type=\"submit\" class=\"action button-primary\" value=\"Backup\" />\n";
        echo "<input name=\"backup\" type=\"submit\" class=\"action button-secondary\" value=\"Restore\" />\n";
        echo "<input type=\"hidden\" name=\"action\" value=\"backup\" />\n";
    } else {
        echo "<div id=\"message\" class=\"updated fade\" style=\"background-color: #FAFAFA; border: 1px solid #DDDDDD;\"><p>A backup of your root htaccess file does not exist. Take a backup now?</p></div>\n";
        echo $preview;
        echo "<input name=\"backup\" type=\"submit\" class=\"action button-primary\" value=\"Backup\" />\n";
        echo "<input type=\"hidden\" name=\"action\" value=\"backup\" />\n";
    } 
    echo "</form>\n";

    /*echo "<form method='POST' style='float: left; display: inline-block; padding: 10px; margin: 10px 0; border: 1px solid #BBB;'>\n";
    echo "<p><textarea disabled='disabled' style='background-color: #EEE; border: 1px solid #CCC' cols='100'  rows='10'>" . $output . "</textarea></p>\n";
    echo "</form>\n";*/
    echo "</div><div style='clear: both; width: 100%; height: 0px;'></div>\n";

    wdmha_domain_listing( $rows );
}
function wdmha_site_admin() {
    if ( function_exists( 'is_super_admin' ) ) {
        return is_super_admin();
    } elseif ( function_exists( 'is_site_admin' ) ) {
        return is_site_admin();
    } else {
        return true;
    }
}

function wdmha_domain_listing( $rows, $heading = '' ) {
    $wdmha_root = (get_site_option('wdmha_root', false, false) === false) ? WDMHA_ROOT_SITE_PATH : get_site_option('wdmha_root', false, false);
    $wdmha_domain_states_str = get_site_option('wdmha_domain_states', serialize(array()), false);
    $wdmha_domain_states = unserialize($wdmha_domain_states_str);
    echo "<form method='POST'><input type='hidden' name='action' value='update' />\n";
    wp_nonce_field( 'domain_mapping_addon' );
    echo "<p>Enter path to root account public folder (containing .htaccess): <input style='width: 50%;' type='text' name='wdmha_root' value='" . $wdmha_root . "' /> .htaccess</p>\n";
    if ( $rows ) {
        if ( file_exists( ABSPATH . 'wp-admin/network/site-info.php' ) ) {
            $edit_url = network_admin_url( 'site-info.php' );
        } elseif ( file_exists( ABSPATH . 'wp-admin/ms-sites.php' ) ) {
            $edit_url = admin_url( 'ms-sites.php' );
        } else {
            $edit_url = admin_url( 'wpmu-blogs.php' );
        }
        if ( $heading != '' )
            echo "<h3>$heading</h3>";
        #echo wdmha_remove_http(network_site_url()) . "<br>";
        echo "<p style='text-align: right; margin-right: 50px;'>Enable All: <input name='wdmha_enableall' id='wdmha_enableall' value type='checkbox' /></p>";
        echo "<script type='text/javascript'>jQuery(document).ready(function(){ jQuery('#wdmha_enableall').click(function(){ if (jQuery(this).is(':checked')){ jQuery('.wdmha_enable:checkbox').attr('checked', 'checked');} else{ jQuery('.wdmha_enable:checkbox').removeAttr('checked'); } })});</script>\n";        echo '<table id="wdmha_table" class="widefat" cellspacing="0"><thead><tr><th>'.__( 'Site ID', 'wordpress-mu-domain-mapping-addon' ).'</th><th>'.__( 'Domain', 'wordpress-mu-domain-mapping-addon' ).'</th><th>'.__( 'Redirect Active?', 'wordpress-mu-domain-mapping-addon' ).'</th><th>'.__( 'Enable', 'wordpress-mu-domain-mapping-addon' ).'</th></tr></thead><tbody>';
        foreach( $rows as $row ) {
            echo "<tr><td>{$row->blog_id}</td><td><a href='http://{$row->domain}/'>{$row->domain}</a></td><td>";
            echo wdmha_detect_proxy('http://' . $row->domain) === true ? __( 'Yes',  'wordpress-mu-domain-mapping-addon' ) : __( 'No',  'wordpress-mu-domain-mapping-addon' );
            echo "</td><td><input type='hidden' name='domain[" . $row->blog_id . "]' value='{$row->domain}' />";
            echo "<input type='checkbox' value='1' class='wdmha_enable' name='wdmha_enable[" . $row->blog_id . "]' " . ((!empty($wdmha_domain_states)) ? checked("1", $wdmha_domain_states[$row->blog_id], false) : "") . " /></form></td>";
            echo "</tr>";
        }
        echo '</table>';
    }
    echo '<br /><input type="submit" value="Save" class="action button-primary" />';
    if ( get_site_option( 'dm_no_primary_domain' ) == 1 ) {
        echo "<p>" . __( '<strong>Warning!</strong> Primary domains are currently disabled.', 'wordpress-mu-domain-mapping-addon' ) . "</p>";
    }
    echo "</form>\n";
}

// Detect redirect
function wdmha_is_redirect($url) {
 
  # 1. Prevent redirects
  $opts = array('http' =>
    array('max_redirects'=>1, 'ignore_errors'=>1)
  );
  stream_context_get_default($opts);
 
  # 2. Get headers (does not take context argument like file_get_contents)
  $headers = get_headers($url,true);
 
  # 3. Restore stream settings
  $opts = array('http' =>
    array('max_redirects'=>20, 'ignore_errors'=>0)
  );
  stream_context_get_default($opts);
 
  # 4. Extract http request status code
  $status = $headers[0];
  list($protocol,$code,$message) = explode(' ', $status,3);
 
  # 5. Detect redirect
  return ($code>=300 && $code<400);
  #return ($code);
}

function wdmha_detect_proxy($url){
    ini_set('default_socket_timeout', 1);
     $opts = array('http' =>
      array('max_redirects'=>20,'ignore_errors'=>0)
     );
     stream_context_get_default($opts);
     $headers = @get_headers($url,true);
     $isproxy = isset($headers["Via"]) ? true : false;
     return $isproxy;
}

// Find location of redirect
function wdmha_loc_redirect($url) {
 
 # 1. Prevent redirects
 $opts = array('http' =>
  array('max_redirects'=>1,'ignore_errors'=>1)
 );
 stream_context_get_default($opts);
 
 # 2. Get headers (does not take context argument like file_get_contents)
 $headers = get_headers($url,true);
 
 # 3. Restore stream settings
 $opts = array('http' =>
  array('max_redirects'=>20,'ignore_errors'=>0)
 );
 stream_context_get_default($opts);
 
 # 4. Extract http request status code
 $status = $headers[0];
 list($protocol,$code,$message) = split(' ',$status,3);
 
 # 5. Find redirect
 return ($code>=300 && $code<400) ? $headers['Location'] : FALSE;
}

function wdmha_remove_http($url = '')
{
$new = (str_replace(array('http://','https://'), '', $url));
return untrailingslashit($new);
}
?>
