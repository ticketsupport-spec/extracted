<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$bio_fields_tbl = $wpdb->prefix . 'membership_bio_fields';

// Ensure table exists (in case migration hasn't run yet)
if ($wpdb->get_var("SHOW TABLES LIKE '$bio_fields_tbl'") !== $bio_fields_tbl) {
    mmgr_create_portal_tables();
}

$allowed_types = array('link', 'text', 'textarea', 'html', 'image');
$type_labels = array(
    'link'     => '🔗 Link',
    'text'     => '📝 Single Line',
    'textarea' => '📄 Multi Line',
    'html'     => '🖥️ HTML Div Box',
    'image'    => '🖼️ Image',
);

// Handle add/edit field
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_bio_field'])) {
    if (!isset($_POST['bio_field_nonce']) || !wp_verify_nonce($_POST['bio_field_nonce'], 'mmgr_save_bio_field')) {
        echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
    } else {
        $field_id    = isset($_POST['field_id']) ? intval($_POST['field_id']) : 0;
        $field_name  = sanitize_text_field($_POST['field_name']);
        $field_type  = sanitize_text_field($_POST['field_type']);
        $placeholder = sanitize_text_field($_POST['placeholder'] ?? '');
        $sort_order  = intval($_POST['sort_order']);
        $active      = isset($_POST['active']) ? 1 : 0;

        if (!in_array($field_type, $allowed_types, true)) {
            $field_type = 'text';
        }

        $data = array(
            'field_name'  => $field_name,
            'field_type'  => $field_type,
            'placeholder' => $placeholder,
            'sort_order'  => $sort_order,
            'active'      => $active,
        );

        if ($field_id > 0) {
            $result = $wpdb->update($bio_fields_tbl, $data, array('id' => $field_id));
            if ($result !== false) {
                echo '<div class="notice notice-success"><p>BIO field updated successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Failed to update BIO field.</p></div>';
            }
        } else {
            $result = $wpdb->insert($bio_fields_tbl, $data);
            if ($result !== false) {
                echo '<div class="notice notice-success"><p>BIO field created successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Failed to create BIO field.</p></div>';
            }
        }
    }
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_bio_field_' . intval($_GET['delete']))) {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    $del_id = intval($_GET['delete']);
    $deleted = $wpdb->delete($bio_fields_tbl, array('id' => $del_id));
    if ($deleted !== false) {
        // Also remove all stored values for this field
        $bio_field_values_tbl = $wpdb->prefix . 'membership_bio_field_values';
        if ($wpdb->get_var("SHOW TABLES LIKE '$bio_field_values_tbl'") === $bio_field_values_tbl) {
            $wpdb->delete($bio_field_values_tbl, array('field_id' => $del_id));
        }
        echo '<div class="notice notice-success"><p>BIO field deleted.</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>Could not delete field. It may not exist.</p></div>';
    }
}

// Get all fields
$fields = $wpdb->get_results("SELECT * FROM $bio_fields_tbl ORDER BY sort_order, id", ARRAY_A);

// Edit mode
$edit_field = null;
if (isset($_GET['edit'])) {
    $edit_field = $wpdb->get_row($wpdb->prepare("SELECT * FROM $bio_fields_tbl WHERE id = %d", intval($_GET['edit'])), ARRAY_A);
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline">📋 Custom BIO Fields</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=membership_bio_fields')); ?>" class="page-title-action">+ Add New Field</a>
    <hr class="wp-header-end">

    <p style="margin-bottom:20px;color:#555;">
        Custom BIO fields appear in the <strong>Online Profile</strong> section for members to fill in, and are displayed publicly on their community profile page.
    </p>

    <div style="display:grid;grid-template-columns:1fr 2fr;gap:30px;margin-top:20px;">
        <!-- Add/Edit Form -->
        <div>
            <div class="card" style="padding:20px;">
                <h2><?php echo $edit_field ? 'Edit BIO Field' : 'Add New BIO Field'; ?></h2>

                <form method="POST">
                    <?php wp_nonce_field('mmgr_save_bio_field', 'bio_field_nonce'); ?>
                    <?php if ($edit_field): ?>
                        <input type="hidden" name="field_id" value="<?php echo intval($edit_field['id']); ?>">
                    <?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th><label for="field_name">Field Name *</label></th>
                            <td>
                                <input type="text" id="field_name" name="field_name"
                                       value="<?php echo $edit_field ? esc_attr($edit_field['field_name']) : ''; ?>"
                                       required class="regular-text" placeholder="e.g. Facebook Link">
                                <p class="description">Label shown to members on their profile page.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="field_type">Field Type *</label></th>
                            <td>
                                <select id="field_type" name="field_type">
                                    <?php foreach ($type_labels as $val => $label): ?>
                                        <option value="<?php echo esc_attr($val); ?>"
                                            <?php selected($edit_field ? $edit_field['field_type'] : 'text', $val); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">How the field is displayed and edited.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="placeholder">Placeholder</label></th>
                            <td>
                                <input type="text" id="placeholder" name="placeholder"
                                       value="<?php echo $edit_field ? esc_attr($edit_field['placeholder']) : ''; ?>"
                                       class="regular-text" placeholder="e.g. https://facebook.com/yourpage">
                                <p class="description">Hint text shown inside the empty field.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sort_order">Sort Order</label></th>
                            <td>
                                <input type="number" id="sort_order" name="sort_order"
                                       value="<?php echo $edit_field ? intval($edit_field['sort_order']) : 0; ?>"
                                       class="small-text">
                                <p class="description">Lower numbers appear first in the profile form.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Active</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="active" value="1"
                                           <?php checked(!$edit_field || $edit_field['active']); ?>>
                                    Show this field to members
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" name="save_bio_field" class="button button-primary">
                            <?php echo $edit_field ? 'Update Field' : 'Create Field'; ?>
                        </button>
                        <?php if ($edit_field): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=membership_bio_fields')); ?>" class="button">Cancel</a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
        </div>

        <!-- Fields List -->
        <div>
            <h2>Existing BIO Fields</h2>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Field Name</th>
                        <th style="width:140px;">Type</th>
                        <th>Placeholder</th>
                        <th style="width:60px;">Sort</th>
                        <th style="width:80px;">Status</th>
                        <th style="width:130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($fields)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;padding:40px;color:#666;">
                                No custom BIO fields defined yet. Create your first field!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($fields as $field): ?>
                            <tr>
                                <td><strong><?php echo esc_html($field['field_name']); ?></strong></td>
                                <td><?php echo esc_html($type_labels[$field['field_type']] ?? $field['field_type']); ?></td>
                                <td style="color:#888;font-style:italic;"><?php echo esc_html($field['placeholder'] ?: '—'); ?></td>
                                <td style="text-align:center;"><?php echo intval($field['sort_order']); ?></td>
                                <td>
                                    <?php if ($field['active']): ?>
                                        <span style="color:#00a32a;font-weight:bold;">● Active</span>
                                    <?php else: ?>
                                        <span style="color:#999;">○ Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=membership_bio_fields&edit=' . intval($field['id']))); ?>"
                                       class="button button-small">Edit</a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=membership_bio_fields&delete=' . intval($field['id'])), 'delete_bio_field_' . intval($field['id']))); ?>"
                                       class="button button-small button-link-delete"
                                       style="color:#d63638;"
                                       onclick="return confirm('Delete this field? All member data for this field will also be removed.');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div style="margin-top:30px;padding:15px;background:#f0f6fc;border-left:4px solid #0073aa;border-radius:4px;">
                <h3 style="margin-top:0;">ℹ️ Field Types</h3>
                <ul style="list-style:disc;margin-left:20px;line-height:1.8;">
                    <li><strong>🔗 Link</strong> – A URL input; displayed as a clickable link on the profile.</li>
                    <li><strong>📝 Single Line</strong> – A short plain-text input.</li>
                    <li><strong>📄 Multi Line</strong> – A multi-line textarea for longer text.</li>
                    <li><strong>🖥️ HTML Div Box</strong> – A textarea that accepts and renders HTML content.</li>
                    <li><strong>🖼️ Image</strong> – An image upload; displayed as an image on the profile.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
