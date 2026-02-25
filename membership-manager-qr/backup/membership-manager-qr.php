<?php
/*
Plugin Name: Membership Manager with QR Code
Description: Membership platform for in-person/online membership, public signups, QR code cards, admin check-in, Code of Conduct, photos, visit log, BAN/unban member.
Version: 3.7.0
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

define('MMGR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MMGR_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once MMGR_PLUGIN_DIR . 'includes/database.php';
require_once MMGR_PLUGIN_DIR . 'includes/admin-menu.php';
require_once MMGR_PLUGIN_DIR . 'includes/ajax-handlers.php';
require_once MMGR_PLUGIN_DIR . 'includes/shortcodes.php';

register_activation_hook(__FILE__, 'mmgr_ensure_tables_exist');

add_action('admin_enqueue_scripts', function($hook){
    if(strpos($hook,'membership_manager')!==false) wp_enqueue_media();
});

add_action('wp_enqueue_scripts', function(){
    wp_enqueue_media();
});

function mmgr_generate_member_code($name) { 
    return substr(md5($name.time().rand(1000,9999)),0,12); 
}
?>