<?php
if (!defined('ABSPATH')) exit;

function mmgr_pages_admin() {
    $pages = array(
        'Registration' => get_option('mmgr_page_registration'),
        'Check-In' => get_option('mmgr_page_checkin'),
        'Code of Conduct' => get_option('mmgr_page_coc'),
        'Member Setup' => get_option('mmgr_page_setup'),
        'Member Login' => get_option('mmgr_page_login'),
        'Member Dashboard' => get_option('mmgr_page_dashboard'),
        'Member Activity' => get_option('mmgr_page_activity'),
        'Member Profile' => get_option('mmgr_page_profile'),
        'Member Community' => get_option('mmgr_page_community')
    );
    
    echo '<div class="wrap">';
    echo '<h1>Plugin Pages</h1>';
    echo '<p>These pages were automatically created by the plugin. You can edit them, but don\'t delete the shortcodes!</p>';
    echo '<table class="widefat"><thead><tr><th>Page Name</th><th>Status</th><th>URL</th><th>Actions</th></tr></thead><tbody>';
    
    foreach ($pages as $name => $page_id) {
        $page = get_post($page_id);
        if ($page) {
            $status = $page->post_status === 'publish' ? '<span style="color:#159742;">✓ Published</span>' : '<span style="color:#d00;">✕ Draft</span>';
            echo '<tr>';
            echo '<td><strong>'.esc_html($name).'</strong></td>';
            echo '<td>'.$status.'</td>';
            echo '<td><a href="'.get_permalink($page_id).'" target="_blank">'.get_permalink($page_id).'</a></td>';
            echo '<td>';
            echo '<a href="'.admin_url('post.php?post='.$page_id.'&action=edit').'" class="button">Edit</a> ';
            echo '<a href="'.get_permalink($page_id).'" target="_blank" class="button">View</a>';
            echo '</td>';
            echo '</tr>';
        } else {
            echo '<tr>';
            echo '<td><strong>'.esc_html($name).'</strong></td>';
            echo '<td><span style="color:#d00;">✕ Missing</span></td>';
            echo '<td>—</td>';
            echo '<td><button class="button" onclick="alert(\'Page missing! Reactivate plugin to recreate.\')">Recreate</button></td>';
            echo '</tr>';
        }
    }
    
    echo '</tbody></table>';
    echo '</div>';
}