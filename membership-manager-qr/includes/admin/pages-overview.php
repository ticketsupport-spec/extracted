<?php
if (!defined('ABSPATH')) exit;

$pages = array(
    'mmgr_page_registration' => 'Member Registration',
    'mmgr_page_checkin' => 'Member Check-In',
    'mmgr_page_coc' => 'Code of Conduct',
    'mmgr_page_member_coc' => 'Member Code of Conduct',
    'mmgr_page_setup' => 'Member Setup',
    'mmgr_page_login' => 'Member Login',
    'mmgr_page_dashboard' => 'Member Dashboard',
    'mmgr_page_activity' => 'Member Activity',
    'mmgr_page_profile' => 'Member Profile',
    'mmgr_page_community' => 'Member Community'
);

?>
<div class="wrap">
    <h1>Plugin Pages</h1>
    <p>These pages were automatically created by the plugin. You can view or edit them below.</p>
    
    <table class="widefat">
        <thead>
            <tr>
                <th>Page Name</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pages as $option => $name): 
                $page_id = get_option($option);
                $exists = $page_id && get_post($page_id);
                $url = $exists ? get_permalink($page_id) : '';
            ?>
                <tr>
                    <td><strong><?php echo esc_html($name); ?></strong></td>
                    <td>
                        <?php if ($exists): ?>
                            <span style="color:#00a32a;">✓ Created</span>
                        <?php else: ?>
                            <span style="color:#d63638;">✗ Missing</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($exists): ?>
                            <a href="<?php echo esc_url($url); ?>" class="button button-small" target="_blank">View</a>
                            <a href="<?php echo admin_url('post.php?post=' . $page_id . '&action=edit'); ?>" class="button button-small">Edit</a>
                        <?php else: ?>
                            <em>Page not found</em>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>