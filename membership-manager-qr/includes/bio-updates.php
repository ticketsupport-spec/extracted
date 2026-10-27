<?php
if (!defined('ABSPATH')) exit;

function mmgr_ensure_bio_updates_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'membership_bio_updates';
    return $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        member_name VARCHAR(255) NOT NULL,
        changes LONGTEXT NOT NULL,
        changed_at DATETIME NOT NULL,
        admin_read_at DATETIME NULL DEFAULT NULL,
        INDEX idx_member_id (member_id),
        INDEX idx_admin_read (admin_read_at, id)
    ) ENGINE=InnoDB " . $wpdb->get_charset_collate()) !== false;
}

add_action('init', 'mmgr_ensure_bio_updates_table');

function mmgr_bio_profile_snapshot($member_id) {
    global $wpdb;
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT name, community_alias, community_bio, community_photo_url FROM {$wpdb->prefix}memberships WHERE id = %d",
        $member_id
    ), ARRAY_A);
    if (!$member) {
        return new WP_Error('profile', 'Could not load the profile for update tracking.');
    }
    $fields = array();
    foreach (array('community_alias' => 'Community alias', 'community_bio' => 'Community bio', 'community_photo_url' => 'Profile photo') as $key => $label) {
        $fields[$key] = array('label' => $label, 'type' => $key === 'community_photo_url' ? 'image' : 'text', 'value' => (string) ($member[$key] ?? ''));
    }
    $queries = array(
        'field' => "SELECT v.field_id AS id, f.field_name AS label, f.field_type AS type, v.field_value AS value
            FROM {$wpdb->prefix}membership_bio_field_values v
            INNER JOIN {$wpdb->prefix}membership_bio_fields f ON f.id = v.field_id
            WHERE v.member_id = %d ORDER BY v.field_id",
        'photo' => "SELECT id, photo_url AS value FROM {$wpdb->prefix}membership_bio_photos WHERE member_id = %d ORDER BY id",
        'orientation' => "SELECT o.id, o.label FROM {$wpdb->prefix}membership_member_orientations s
            INNER JOIN {$wpdb->prefix}membership_sexual_orientations o ON o.id = s.orientation_id
            WHERE s.member_id = %d ORDER BY o.id",
    );
    foreach ($queries as $prefix => $query) {
        $rows = $wpdb->get_results($wpdb->prepare($query, $member_id), ARRAY_A);
        if ($wpdb->last_error) {
            return new WP_Error('profile', 'Could not load the profile for update tracking.');
        }
        foreach ($rows as $row) {
            $fields[$prefix . '_' . $row['id']] = array(
                'label' => $prefix === 'photo' ? 'Bio photo #' . $row['id'] : ($prefix === 'orientation' ? 'Orientation' : $row['label']),
                'type' => $prefix === 'photo' ? 'image' : ($row['type'] ?? 'text'),
                'value' => (string) ($prefix === 'orientation' ? $row['label'] : $row['value']),
            );
        }
    }
    return array('name' => $member['name'], 'fields' => $fields);
}

function mmgr_begin_bio_update($member_id) {
    global $wpdb;
    if (!mmgr_ensure_bio_updates_table()) {
        return new WP_Error('tracking', 'Could not prepare profile update tracking. Please try again.');
    }
    foreach (array('memberships', 'membership_bio_updates', 'membership_bio_field_values', 'membership_bio_photos', 'membership_member_orientations') as $suffix) {
        $table = $wpdb->prefix . $suffix;
        $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table), ARRAY_A);
        if (!$status || empty($status['Engine'])) {
            return new WP_Error('storage', 'Profile update storage is unavailable. Please contact an administrator.');
        }
        if (strcasecmp($status['Engine'], 'InnoDB') !== 0) {
            // Conversion must happen before the transaction because ALTER TABLE commits.
            if ($suffix === 'memberships' || $wpdb->query("ALTER TABLE `$table` ENGINE=InnoDB") === false) {
                return new WP_Error('storage', 'Profile update tracking requires InnoDB storage. Please contact an administrator.');
            }
            $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table), ARRAY_A);
            if (!$status || strcasecmp($status['Engine'] ?? '', 'InnoDB') !== 0) {
                return new WP_Error('storage', 'Could not verify profile update storage. Please contact an administrator.');
            }
        }
    }
    if ($wpdb->query('START TRANSACTION') === false) {
        return new WP_Error('tracking', 'Could not start the profile update. Please try again.');
    }
    // Serialize all profile/photo saves for this member before taking the snapshot.
    if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}memberships WHERE id = %d FOR UPDATE", $member_id))) {
        return mmgr_cancel_bio_update();
    }
    $before = mmgr_bio_profile_snapshot($member_id);
    if (is_wp_error($before)) {
        $wpdb->query('ROLLBACK');
    }
    return $before;
}

function mmgr_cancel_bio_update() {
    global $wpdb;
    $wpdb->query('ROLLBACK');
    return new WP_Error('tracking', 'Could not save the profile change and its update history. Please try again.');
}

function mmgr_finish_bio_update($member_id, $before) {
    global $wpdb;
    $after = mmgr_bio_profile_snapshot($member_id);
    if (is_wp_error($after)) {
        return mmgr_cancel_bio_update();
    }
    $changes = array();
    foreach (array_unique(array_merge(array_keys($before['fields']), array_keys($after['fields']))) as $key) {
        $old = $before['fields'][$key]['value'] ?? '';
        $new = $after['fields'][$key]['value'] ?? '';
        if ($old !== $new) {
            $field = $after['fields'][$key] ?? $before['fields'][$key];
            $changes[] = array('label' => $field['label'], 'type' => $field['type'], 'before' => $old, 'after' => $new);
        }
    }
    if ($changes) {
        $json = wp_json_encode($changes);
        if ($json === false || $wpdb->insert($wpdb->prefix . 'membership_bio_updates', array(
            'member_id' => $member_id,
            'member_name' => $before['name'],
            'changes' => $json,
            'changed_at' => current_time('mysql'),
        )) === false) {
            return mmgr_cancel_bio_update();
        }
    }
    if ($wpdb->query('COMMIT') === false) {
        return mmgr_cancel_bio_update();
    }
    return true;
}

function mmgr_bio_updates_menu_label() {
    global $wpdb;
    $table = $wpdb->prefix . 'membership_bio_updates';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
        return 'Bio Updates';
    }
    $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE admin_read_at IS NULL");
    return 'Bio Updates' . ($count > 0 ? ' <span class="awaiting-mod">' . $count . '</span>' : '');
}

function mmgr_admin_bio_updates_load() {
    if (($_GET['page'] ?? '') !== 'membership_bio_updates') return;
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    global $wpdb, $mmgr_bio_updates, $submenu;
    $page = max(1, absint($_GET['paged'] ?? 1));
    $table = $wpdb->prefix . 'membership_bio_updates';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `$table` ORDER BY id DESC LIMIT %d OFFSET %d", 20, ($page - 1) * 20
    ), ARRAY_A);
    $notice = $wpdb->last_error ? 'Could not load bio updates. Please reload to try again.' : '';
    $ids = array();
    $rows = $rows ?: array();
    foreach ($rows as &$row) {
        $row['decoded_changes'] = json_decode($row['changes'], true);
        $valid = is_array($row['decoded_changes']) && !empty($row['decoded_changes']);
        if ($valid) {
            foreach ($row['decoded_changes'] as $change) {
                if (!is_array($change) || !isset($change['label'], $change['type'], $change['before'], $change['after']) ||
                    count(array_filter(array($change['label'], $change['type'], $change['before'], $change['after']), 'is_string')) !== 4) {
                    $valid = false;
                    break;
                }
            }
        }
        if (!$valid) {
            $row['decoded_changes'] = null;
            $notice = 'Some update history could not be loaded and has not been marked viewed. Please contact support.';
            continue;
        }
        $ids[] = (int) $row['id'];
    }
    unset($row);
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        if ($wpdb->query($wpdb->prepare(
            "UPDATE `$table` SET admin_read_at = %s WHERE admin_read_at IS NULL AND id IN ($placeholders)",
            array_merge(array(current_time('mysql')), $ids)
        )) === false) {
            $notice = 'Could not mark these updates viewed. Please reload to try again.';
        }
    }
    $mmgr_bio_updates = array('rows' => $rows ?: array(), 'page' => $page, 'notice' => $notice,
        'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table`"));
    if (isset($submenu['membership_manager'])) {
        foreach ($submenu['membership_manager'] as &$item) {
            if ($item[2] === 'membership_bio_updates') $item[0] = mmgr_bio_updates_menu_label();
        }
        unset($item);
    }
}

function mmgr_render_bio_update_value($value, $type) {
    if ($value === '') {
        echo '<em>Empty / removed</em>';
    } elseif ($type === 'image') {
        echo '<a href="' . esc_url($value) . '" target="_blank" rel="noopener noreferrer"><img src="' . esc_url($value) . '" alt="Profile photo change" style="max-width:220px;max-height:220px;"></a>';
    } else {
        echo '<div style="white-space:pre-wrap;overflow-wrap:anywhere;">' . esc_html($value) . '</div>';
    }
}

function mmgr_admin_bio_updates_page() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    global $mmgr_bio_updates;
    $data = $mmgr_bio_updates;
    $url = admin_url('admin.php?page=membership_bio_updates');
    ?>
    <div class="wrap">
        <h1>Bio Updates</h1>
        <p>Profile changes shown here are automatically marked viewed for all administrators. Updates on other pages remain unread. History starts when update tracking is installed.</p>
        <?php if ($data['notice']): ?><div class="notice notice-error"><p><?php echo esc_html($data['notice']); ?></p></div><?php endif; ?>
        <?php if (!$data['rows']): ?><p>No bio updates yet.</p><?php endif; ?>
        <?php foreach ($data['rows'] as $row): ?>
            <div class="card" style="max-width:none;">
                <h2><?php echo esc_html($row['member_name']); ?> (Member #<?php echo (int) $row['member_id']; ?>)
                    <?php if (empty($row['admin_read_at'])): ?><span class="awaiting-mod">New</span><?php endif; ?></h2>
                <p><?php echo esc_html($row['changed_at']); ?> |
                    <a href="<?php echo esc_url(add_query_arg('id', (int) $row['member_id'], admin_url('admin.php?page=membership_add'))); ?>">Member record</a></p>
                <?php if (is_array($row['decoded_changes'])): ?>
                    <table class="widefat striped" style="table-layout:fixed;">
                        <thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead>
                        <tbody><?php foreach ($row['decoded_changes'] as $change): ?>
                            <tr><th><?php echo esc_html($change['label']); ?></th>
                                <td><?php mmgr_render_bio_update_value($change['before'], $change['type']); ?></td>
                                <td><?php mmgr_render_bio_update_value($change['after'], $change['type']); ?></td></tr>
                        <?php endforeach; ?></tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <p><?php if ($data['page'] > 1): ?><a class="button" href="<?php echo esc_url(add_query_arg('paged', $data['page'] - 1, $url)); ?>">Previous</a><?php endif; ?>
        <?php if ($data['page'] * 20 < $data['total']): ?><a class="button" href="<?php echo esc_url(add_query_arg('paged', $data['page'] + 1, $url)); ?>">Next</a><?php endif; ?></p>
    </div>
    <?php
}
