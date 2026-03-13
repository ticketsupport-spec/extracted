<?php

if (!defined('ABSPATH')) exit;

global $wpdb;
$events_table = $wpdb->prefix . 'membership_events';

// DEBUG: Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$events_table'");
if (!$table_exists) {
    echo '<div class="notice notice-error"><p>❌ Events table does not exist! Creating it now...</p></div>';
    
    // Create the table
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS `$events_table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_name VARCHAR(255) NOT NULL,
        event_date DATE NOT NULL,
        description TEXT,
        image_url VARCHAR(500),
        start_time TIME,
        end_time TIME,
        location VARCHAR(255),
        active TINYINT(1) DEFAULT 1,
        sort_order INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_event_date (event_date),
        INDEX idx_active (active),
        INDEX idx_sort (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    echo '<div class="notice notice-success"><p>✓ Events table created successfully!</p></div>';
}
if (!defined('ABSPATH')) exit;

global $wpdb;
$events_table = $wpdb->prefix . 'membership_events';

// Handle delete
if (isset($_GET['delete']) && isset($_GET['_wpnonce'])) {
    if (wp_verify_nonce($_GET['_wpnonce'], 'delete_event_' . $_GET['delete'])) {
        $event_id = intval($_GET['delete']);
        $event = $wpdb->get_row($wpdb->prepare("SELECT image_url FROM $events_table WHERE id = %d", $event_id), ARRAY_A);
        
        // Delete image if exists
        if ($event && !empty($event['image_url'])) {
            $upload_dir = wp_upload_dir();
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $event['image_url']);
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        $wpdb->delete($events_table, array('id' => $event_id));
        echo '<div class="notice notice-success"><p>✓ Event deleted successfully!</p></div>';
    }
}

// Handle form submission
if (isset($_POST['save_event']) && isset($_POST['event_nonce'])) {
    if (!wp_verify_nonce($_POST['event_nonce'], 'save_event')) {
        echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
    } else {
        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : null;
        $event_name = sanitize_text_field($_POST['event_name']);
        $event_date = sanitize_text_field($_POST['event_date']);
        $start_time = sanitize_text_field($_POST['start_time'] ?? '');
        $end_time = sanitize_text_field($_POST['end_time'] ?? '');
        $location = sanitize_text_field($_POST['location'] ?? '');
        $description = wp_kses_post($_POST['description']);
        $active = isset($_POST['active']) ? 1 : 0;
        $sort_order = intval($_POST['sort_order'] ?? 0);
        
        // Handle image upload
        $image_url = null;
        if (!empty($_FILES['event_image']['name'])) {
            $upload = wp_upload_bits(
                'event-' . time() . '.jpg',
                null,
                file_get_contents($_FILES['event_image']['tmp_name'])
            );
            
            if (!$upload['error']) {
                $image_url = $upload['url'];
            } else {
                echo '<div class="notice notice-error"><p>Image upload failed: ' . $upload['error'] . '</p></div>';
            }
        }
        
        if ($event_id) {
            // Update event
            $update_data = array(
                'event_name' => $event_name,
                'event_date' => $event_date,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'location' => $location,
                'description' => $description,
                'active' => $active,
                'sort_order' => $sort_order
            );
            
            if ($image_url) {
                $update_data['image_url'] = $image_url;
            }
            
            $wpdb->update($events_table, $update_data, array('id' => $event_id));
            echo '<div class="notice notice-success"><p>✓ Event updated successfully!</p></div>';
        } else {
            // Create new event
            $wpdb->insert($events_table, array(
                'event_name' => $event_name,
                'event_date' => $event_date,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'location' => $location,
                'description' => $description,
                'image_url' => $image_url,
                'active' => $active,
                'sort_order' => $sort_order
            ));
            echo '<div class="notice notice-success"><p>✓ Event created successfully!</p></div>';
        }
    }
}

$editing_event = null;
if (isset($_GET['edit'])) {
    $editing_event = $wpdb->get_row($wpdb->prepare("SELECT * FROM $events_table WHERE id = %d", intval($_GET['edit'])), ARRAY_A);
}

?>
<div class="wrap">
    <h1>📅 Upcoming Events & Advertising</h1>
    
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
        <!-- Event Form -->
        <div style="background:white;padding:20px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
            <h2><?php echo $editing_event ? 'Edit Event' : 'Add New Event'; ?></h2>
            
            <form method="POST" enctype="multipart/form-data">
                <?php wp_nonce_field('save_event', 'event_nonce'); ?>
                <?php if ($editing_event): ?>
                    <input type="hidden" name="event_id" value="<?php echo $editing_event['id']; ?>">
                <?php endif; ?>
                
                <table class="form-table" style="width:100%;">
                    <tr>
                        <th><label for="event_name">Event Name *</label></th>
                        <td><input type="text" name="event_name" id="event_name" class="regular-text" value="<?php echo esc_attr($editing_event['event_name'] ?? ''); ?>" required></td>
                    </tr>
                    
                    <tr>
                        <th><label for="event_date">Event Date *</label></th>
                        <td><input type="date" name="event_date" id="event_date" value="<?php echo esc_attr($editing_event['event_date'] ?? ''); ?>" required></td>
                    </tr>
                    
                    <tr>
                        <th><label for="start_time">Start Time</label></th>
                        <td><input type="time" name="start_time" id="start_time" value="<?php echo esc_attr($editing_event['start_time'] ?? ''); ?>"></td>
                    </tr>
                    
                    <tr>
                        <th><label for="end_time">End Time</label></th>
                        <td><input type="time" name="end_time" id="end_time" value="<?php echo esc_attr($editing_event['end_time'] ?? ''); ?>"></td>
                    </tr>
                    
                    <tr>
                        <th><label for="location">Location</label></th>
                        <td><input type="text" name="location" id="location" class="regular-text" value="<?php echo esc_attr($editing_event['location'] ?? ''); ?>"></td>
                    </tr>
                    
                    <tr>
                        <th><label for="description">Description</label></th>
                        <td>
                            <?php
                            wp_editor(
                                $editing_event['description'] ?? '',
                                'description',
                                array(
                                    'textarea_name' => 'description',
                                    'textarea_rows' => 8,
                                    'media_buttons' => false,
                                    'teeny'         => false,
                                    'quicktags'     => true,
                                )
                            );
                            ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="event_image">Advertising Image</label></th>
                        <td>
                            <input type="file" name="event_image" id="event_image" accept="image/*">
                            <p class="description">JPG, PNG (recommended 800x400px)</p>
                            <?php if ($editing_event && !empty($editing_event['image_url'])): ?>
                                <p><img src="<?php echo esc_url($editing_event['image_url']); ?>" style="max-width:200px;margin-top:10px;border-radius:4px;"></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="sort_order">Display Order</label></th>
                        <td><input type="number" name="sort_order" id="sort_order" value="<?php echo esc_attr($editing_event['sort_order'] ?? 0); ?>"></td>
                    </tr>
                    
                    <tr>
                        <th><label for="active">Status</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="active" id="active" value="1" <?php checked($editing_event['active'] ?? 1, 1); ?>>
                                Active (visible to members)
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p>
                    <button type="submit" name="save_event" class="button button-primary">💾 Save Event</button>
                    <?php if ($editing_event): ?>
                        <a href="<?php echo admin_url('admin.php?page=membership_events'); ?>" class="button">Cancel</a>
                    <?php endif; ?>
                </p>
            </form>
        </div>
        
        <!-- Events List -->
        <div style="background:white;padding:20px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
            <h2>All Events</h2>
            
            <?php
            $events = $wpdb->get_results("SELECT * FROM $events_table ORDER BY event_date DESC, sort_order ASC", ARRAY_A);
            
            if (empty($events)):
                echo '<p style="color:#666;">No events created yet.</p>';
            else:
                echo '<table class="widefat" style="margin-top:10px;">';
                echo '<thead><tr><th>Event Name</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
                
                foreach ($events as $event):
                    $status = $event['active'] ? '✓ Active' : '⊘ Inactive';
                    $is_past = strtotime($event['event_date']) < strtotime('today') ? ' (Past)' : '';
                    echo '<tr>';
                    echo '<td><strong>' . esc_html($event['event_name']) . '</strong>' . $is_past . '</td>';
                    echo '<td>' . esc_html(date('M d, Y', strtotime($event['event_date']))) . '</td>';
                    echo '<td>' . $status . '</td>';
                    echo '<td>';
                    echo '<a href="' . admin_url('admin.php?page=membership_events&edit=' . $event['id']) . '" class="button button-small">✏️ Edit</a> ';
                    echo '<a href="' . wp_nonce_url(admin_url('admin.php?page=membership_events&delete=' . $event['id']), 'delete_event_' . $event['id']) . '" class="button button-small" style="color:#d63638;" onclick="return confirm(\'Delete this event?\');">🗑️ Delete</a>';
                    echo '</td>';
                    echo '</tr>';
                endforeach;
                
                echo '</tbody></table>';
            endif;
            ?>
        </div>
    </div>
</div>