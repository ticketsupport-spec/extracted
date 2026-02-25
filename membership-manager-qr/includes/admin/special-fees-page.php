<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$fees_tbl = $wpdb->prefix . 'membership_fees';
$levels_tbl = $wpdb->prefix . 'membership_levels';

// Handle add/edit event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_event'])) {
    if (!isset($_POST['event_nonce']) || !wp_verify_nonce($_POST['event_nonce'], 'save_event')) {
        echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
    } else {
        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        $fee_date = sanitize_text_field($_POST['fee_date']);
        $fee_amount = floatval($_POST['fee_amount']);
        $event_name = sanitize_text_field($_POST['event_name']);
        $description = sanitize_textarea_field($_POST['description']);
        $apply_to_levels = isset($_POST['apply_to_levels']) ? json_encode($_POST['apply_to_levels']) : json_encode([]);
        
        $data = array(
            'fee_date' => $fee_date,
            'fee_amount' => $fee_amount,
            'event_name' => $event_name,
            'description' => $description,
            'apply_to_levels' => $apply_to_levels
        );
        
        if ($event_id > 0) {
            $wpdb->update($fees_tbl, $data, array('id' => $event_id));
            echo '<div class="notice notice-success"><p>Event updated successfully!</p></div>';
        } else {
            $wpdb->insert($fees_tbl, $data);
            echo '<div class="notice notice-success"><p>Event added successfully!</p></div>';
        }
    }
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_event_' . $_GET['delete'])) {
    $wpdb->delete($fees_tbl, array('id' => intval($_GET['delete'])));
    echo '<div class="notice notice-success"><p>Event deleted successfully!</p></div>';
}

// Get event for editing
$editing_event = null;
if (isset($_GET['edit'])) {
    $editing_event = $wpdb->get_row($wpdb->prepare("SELECT * FROM $fees_tbl WHERE id = %d", intval($_GET['edit'])), ARRAY_A);
}

// Get all events
$events = $wpdb->get_results("SELECT * FROM $fees_tbl ORDER BY fee_date DESC", ARRAY_A);

// Get all membership levels
$levels = $wpdb->get_results("SELECT level_name FROM $levels_tbl ORDER BY level_name", ARRAY_A);

?>

<div class="wrap">
    <h1 class="wp-heading-inline">Special Events</h1>
    <a href="<?php echo admin_url('admin.php?page=membership_special_fees'); ?>" class="page-title-action">Add New Event</a>
    <hr class="wp-header-end">
    
    <p style="color:#666;margin-bottom:20px;">
        Create special events with custom entry fees. When a member checks in on an event date, they will be charged the event fee instead of their regular daily fee.
    </p>
    
    <!-- Add/Edit Event Form -->
    <div style="background:white;padding:20px;border:1px solid #ccc;border-radius:6px;margin-bottom:30px;">
        <h2><?php echo $editing_event ? 'Edit Event' : 'Add New Event'; ?></h2>
        
        <form method="POST">
            <?php wp_nonce_field('save_event', 'event_nonce'); ?>
            <?php if ($editing_event): ?>
                <input type="hidden" name="event_id" value="<?php echo $editing_event['id']; ?>">
            <?php endif; ?>
            
            <table class="form-table">
                <tr>
                    <th><label for="event_name">Event Name *</label></th>
                    <td>
                        <input type="text" 
                               id="event_name" 
                               name="event_name" 
                               class="regular-text" 
                               value="<?php echo $editing_event ? esc_attr($editing_event['event_name']) : ''; ?>" 
                               placeholder="e.g., Valentine's Day Party, New Year's Eve Bash" 
                               required>
                        <p class="description">Give your event a memorable name</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="fee_date">Event Date *</label></th>
                    <td>
                        <input type="date" 
                               id="fee_date" 
                               name="fee_date" 
                               value="<?php echo $editing_event ? esc_attr($editing_event['fee_date']) : ''; ?>" 
                               required>
                        <p class="description">On this date, the special event fee will apply</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="fee_amount">Entry Fee *</label></th>
                    <td>
                        <input type="number" 
                               id="fee_amount" 
                               name="fee_amount" 
                               step="0.01" 
                               min="0" 
                               value="<?php echo $editing_event ? esc_attr($editing_event['fee_amount']) : ''; ?>" 
                               placeholder="0.00" 
                               required 
                               style="width:150px;">
                        <p class="description">Entry fee for this special event (e.g., 25.00)</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="description">Description</label></th>
                    <td>
                        <textarea id="description" 
                                  name="description" 
                                  rows="4" 
                                  class="large-text" 
                                  placeholder="Describe what makes this event special..."><?php echo $editing_event ? esc_textarea($editing_event['description']) : ''; ?></textarea>
                        <p class="description">Optional: Add event details, theme, dress code, etc.</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label>Apply to Membership Levels</label></th>
                    <td>
                        <?php 
                        $selected_levels = $editing_event && !empty($editing_event['apply_to_levels']) 
                            ? json_decode($editing_event['apply_to_levels'], true) 
                            : [];
                        
                        if (empty($levels)): ?>
                            <p style="color:#d63638;">⚠️ No membership levels found. <a href="<?php echo admin_url('admin.php?page=membership_levels'); ?>">Create membership levels first</a>.</p>
                        <?php else: ?>
                            <fieldset>
                                <label style="display:block;margin-bottom:8px;">
                                    <input type="checkbox" 
                                           id="select_all_levels" 
                                           onclick="document.querySelectorAll('.level-checkbox').forEach(cb => cb.checked = this.checked);">
                                    <strong>Select All</strong>
                                </label>
                                <?php foreach ($levels as $level): ?>
                                    <label style="display:block;margin-bottom:5px;">
                                        <input type="checkbox" 
                                               name="apply_to_levels[]" 
                                               value="<?php echo esc_attr($level['level_name']); ?>" 
                                               class="level-checkbox"
                                               <?php echo in_array($level['level_name'], $selected_levels) ? 'checked' : ''; ?>>
                                        <?php echo esc_html($level['level_name']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description">Leave unchecked to apply to all levels</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" name="save_event" class="button button-primary">
                    <?php echo $editing_event ? '💾 Update Event' : '✨ Create Event'; ?>
                </button>
                <?php if ($editing_event): ?>
                    <a href="<?php echo admin_url('admin.php?page=membership_special_fees'); ?>" class="button">Cancel</a>
                <?php endif; ?>
            </p>
        </form>
    </div>
    
    <!-- Events List -->
    <h2>Scheduled Events</h2>
    
    <?php if (empty($events)): ?>
        <div style="background:#f0f0f0;padding:40px;text-align:center;border-radius:6px;color:#666;">
            <p style="font-size:18px;margin:0;">🎉 No special events scheduled yet</p>
            <p style="margin:10px 0 0 0;">Create your first event using the form above!</p>
        </div>
    <?php else: ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:120px;">Event Date</th>
                    <th>Event Name</th>
                    <th>Description</th>
                    <th style="width:100px;">Entry Fee</th>
                    <th>Applies To</th>
                    <th style="width:150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): 
                    $event_date = strtotime($event['fee_date']);
                    $is_past = $event_date < strtotime('today');
                    $is_today = date('Y-m-d', $event_date) === date('Y-m-d');
                    $is_upcoming = $event_date > strtotime('today');
                    
                    $selected_levels = !empty($event['apply_to_levels']) ? json_decode($event['apply_to_levels'], true) : [];
                ?>
                    <tr style="<?php echo $is_today ? 'background:#d4edda;' : ($is_past ? 'opacity:0.6;' : ''); ?>">
                        <td>
                            <strong><?php echo date('M d, Y', $event_date); ?></strong><br>
                            <?php if ($is_today): ?>
                                <span style="background:#28a745;color:white;padding:2px 6px;border-radius:10px;font-size:10px;font-weight:bold;">TODAY</span>
                            <?php elseif ($is_upcoming): ?>
                                <span style="background:#0073aa;color:white;padding:2px 6px;border-radius:10px;font-size:10px;font-weight:bold;">UPCOMING</span>
                            <?php else: ?>
                                <span style="background:#666;color:white;padding:2px 6px;border-radius:10px;font-size:10px;font-weight:bold;">PAST</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong style="font-size:14px;">🎉 <?php echo esc_html($event['event_name']); ?></strong>
                        </td>
                        <td>
                            <?php echo !empty($event['description']) ? esc_html($event['description']) : '<em style="color:#999;">No description</em>'; ?>
                        </td>
                        <td>
                            <strong style="font-size:16px;color:#d63638;">$<?php echo number_format($event['fee_amount'], 2); ?></strong>
                        </td>
                        <td>
                            <?php if (empty($selected_levels)): ?>
                                <span style="color:#666;font-style:italic;">All Levels</span>
                            <?php else: ?>
                                <?php foreach ($selected_levels as $level): ?>
                                    <span style="background:#0073aa;color:white;padding:2px 6px;border-radius:10px;font-size:11px;display:inline-block;margin:2px;">
                                        <?php echo esc_html($level); ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=membership_special_fees&edit=' . $event['id']); ?>" class="button button-small">Edit</a>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=membership_special_fees&delete=' . $event['id']), 'delete_event_' . $event['id']); ?>" 
                               class="button button-small button-link-delete" 
                               style="color:#d63638;"
                               onclick="return confirm('Delete this event?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.level-checkbox {
    margin-right: 5px;
}
</style>