<?php
// Run with: php tests/test-bio-updates.php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
$passed = 0;
$actions = $shortcodes = array();
$can_manage = $logged_in = $valid_nonce = true;
function expect($condition, $message) {
    global $passed;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $passed++;
    echo "PASS: $message\n";
}
class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
class BioResponse extends RuntimeException {
    public $success;
    public $data;
    public function __construct($success, $data) { $this->success = $success; $this->data = $data; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function add_action($hook, $callback, ...$args) { global $actions; $actions[$hook][] = $callback; }
function add_shortcode($tag, $callback) { global $shortcodes; $shortcodes[$tag] = $callback; }
function do_action($hook) { global $actions; foreach ($actions[$hook] ?? array() as $callback) $callback(); }
function current_user_can($cap) { global $can_manage; return $can_manage && $cap === 'manage_options'; }
function current_time($format) { return '2026-10-27 18:00:00'; }
function absint($value) { return abs((int) $value); }
function wp_json_encode($value) { return json_encode($value); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_url($value) { return esc_html($value); }
function admin_url($path) { return 'https://example.com/wp-admin/' . $path; }
function add_query_arg($key, $value, $url = 'https://example.com/member-profile/') { return $url . '&' . $key . '=' . $value; }
function wp_die($message) { throw new RuntimeException($message); }
function mmgr_get_current_member() { global $wpdb, $logged_in; return $logged_in ? array_merge($wpdb->member, array('id' => 7)) : null; }
function mmgr_enforce_usercod($member) {}
function nocache_headers() {}
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_textarea_field($value) { return sanitize_text_field($value); }
function sanitize_email($value) { return $value; }
function sanitize_key($value) { return $value; }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
function wp_kses_post($value) { return $value; }
function esc_url_raw($value) { return $value; }
function wp_verify_nonce($nonce, $action) { global $valid_nonce; return $valid_nonce; }
function check_ajax_referer($action, $key, ...$args) { global $valid_nonce; if (!$valid_nonce) throw new BioResponse(false, array('message' => 'Invalid nonce')); return true; }
function wp_send_json_error($data) { throw new BioResponse(false, $data); }
function wp_send_json_success($data = null) { throw new BioResponse(true, $data); }
function wp_redirect($url) { throw new BioResponse(true, $url); }
function wp_check_filetype($name) { return array('type' => 'image/png'); }
function wp_check_filetype_and_ext($path, $name, $allowed) { return array('type' => 'image/png'); }
function wp_handle_upload($file, $options) { return array('url' => 'https://example.com/uploads/' . $file['name']); }

class BioDatabase {
    public $prefix = 'wp_';
    public $last_error = '';
    public $insert_id = 0;
    public $member = array('name' => '<b>Member</b>', 'community_alias' => 'Original', 'community_bio' => 'Old bio',
        'community_photo_url' => '', 'phone' => '123', 'email' => 'member@example.com');
    public $values = array();
    public $fields = array(1 => array('id' => 1, 'field_name' => 'Custom text', 'field_type' => 'textarea'),
        2 => array('id' => 2, 'field_name' => 'Custom image', 'field_type' => 'image'));
    public $photos = array();
    public $orientations = array();
    public $updates = array();
    public $writes = array();
    public $fail = '';
    public $engines = array();
    public $arriving_update = null;
    private $transaction;
    public function get_charset_collate() { return ''; }
    public function esc_like($value) { return addcslashes($value, '_%\\'); }
    public function prepare($sql, ...$args) {
        if (count($args) === 1 && is_array($args[0])) $args = $args[0];
        foreach ($args as $arg) {
            $sql = preg_replace_callback('/%[ds]/', function($m) use ($arg) {
                return $m[0] === '%d' ? (string) (int) $arg : "'" . addslashes($arg) . "'";
            }, $sql, 1);
        }
        return $sql;
    }
    public function get_row($sql, $format) {
        $this->last_error = '';
        if (strpos($sql, 'SHOW TABLE STATUS') === 0) {
            preg_match("/Name = '([^']+)'/", $sql, $m);
            return array('Engine' => $this->engines[$m[1]] ?? 'InnoDB');
        }
        if (strpos($sql, 'SELECT name,') === 0) return $this->member;
        if (strpos($sql, 'SELECT id, photo_url') === 0) {
            preg_match('/id = (\d+) AND member_id = (\d+)/', $sql, $m);
            return (int) $m[2] === 7 ? ($this->photos[$m[1]] ?? null) : null;
        }
        throw new RuntimeException('Unexpected row query: ' . $sql);
    }
    public function get_var($sql) {
        $this->last_error = '';
        if (strpos($sql, 'SHOW TABLES LIKE') === 0) {
            preg_match("/LIKE '(.*)'/", $sql, $m);
            return stripslashes(stripslashes($m[1]));
        }
        if (strpos($sql, 'FOR UPDATE') !== false) return 7;
        if (strpos($sql, 'membership_bio_photos') !== false) return count($this->photos);
        if (strpos($sql, 'IS NULL') !== false) return count(array_filter($this->updates, function($r) { return $r['admin_read_at'] === null; }));
        return count($this->updates);
    }
    public function get_col($sql) { return array(1, 2); }
    public function get_results($sql, $format) {
        $this->last_error = '';
        if ($this->fail === 'snapshot' && strpos($sql, 'SELECT v.field_id') === 0) {
            $this->last_error = 'Read failed';
            return array();
        }
        if (strpos($sql, 'SELECT v.field_id') === 0) {
            $rows = array();
            foreach ($this->values as $id => $value) {
                $rows[] = array('id' => $id, 'label' => $this->fields[$id]['field_name'], 'type' => $this->fields[$id]['field_type'], 'value' => $value);
            }
            return $rows;
        }
        if (strpos($sql, 'SELECT id, photo_url AS value') === 0) {
            return array_map(function($p) { return array('id' => $p['id'], 'value' => $p['photo_url']); }, array_values($this->photos));
        }
        if (strpos($sql, 'SELECT o.id') === 0) {
            return array_map(function($id) { return array('id' => $id, 'label' => 'Option ' . $id); }, array_values($this->orientations));
        }
        if (strpos($sql, 'SELECT id, field_type') === 0) return array_values($this->fields);
        if (strpos($sql, 'SELECT * FROM `wp_membership_bio_updates`') === 0) {
            $rows = $this->updates;
            krsort($rows);
            preg_match('/LIMIT (\d+) OFFSET (\d+)/', $sql, $m);
            $rows = array_slice(array_values($rows), (int) $m[2], (int) $m[1]);
            if ($this->arriving_update) {
                $this->updates[$this->arriving_update['id']] = $this->arriving_update;
                $this->arriving_update = null;
            }
            return $rows;
        }
        throw new RuntimeException('Unexpected results query: ' . $sql);
    }
    public function query($sql) {
        $this->writes[] = $sql;
        if ($this->fail === 'create' && strpos($sql, 'CREATE TABLE') === 0) return false;
        if ($this->fail === 'commit' && $sql === 'COMMIT') return false;
        if ($sql === 'START TRANSACTION') $this->transaction = array($this->member, $this->values, $this->photos, $this->orientations, $this->updates);
        if ($sql === 'ROLLBACK' && $this->transaction) {
            list($this->member, $this->values, $this->photos, $this->orientations, $this->updates) = $this->transaction;
        }
        if ($sql === 'COMMIT' || $sql === 'ROLLBACK') $this->transaction = null;
        if (strpos($sql, 'ALTER TABLE') === 0) {
            preg_match('/`([^`]+)`/', $sql, $m);
            $this->engines[$m[1]] = 'InnoDB';
        }
        if (strpos($sql, 'UPDATE `wp_membership_bio_updates`') === 0) {
            if ($this->fail === 'mark') return false;
            preg_match('/id IN \(([\d,]+)\)/', $sql, $m);
            foreach (explode(',', $m[1]) as $id) {
                if ($this->updates[$id]['admin_read_at'] === null) $this->updates[$id]['admin_read_at'] = current_time('mysql');
            }
        }
        return 1;
    }
    public function insert($table, $data) {
        $this->writes[] = array($table, $data);
        if ($table === 'wp_membership_bio_updates') {
            if ($this->fail === 'history') return false;
            $id = $this->updates ? max(array_keys($this->updates)) + 1 : 1;
            $this->updates[$id] = array_merge($data, array('id' => $id, 'admin_read_at' => null));
        } else {
            if ($this->fail === 'photo') return false;
            $id = $this->photos ? max(array_keys($this->photos)) + 1 : 1;
            $this->photos[$id] = array_merge($data, array('id' => $id));
        }
        $this->insert_id = $id;
        return 1;
    }
    public function update($table, $data, $where) { $this->member = array_merge($this->member, $data); return 1; }
    public function replace($table, $data) {
        if ($this->fail === 'replace') return false;
        if ($table === 'wp_membership_bio_field_values') $this->values[$data['field_id']] = $data['field_value'];
        else $this->orientations[$data['orientation_id']] = $data['orientation_id'];
        return 1;
    }
    public function delete($table, $where) {
        if ($this->fail === 'delete') return false;
        if ($table === 'wp_membership_bio_photos') unset($this->photos[$where['id']]);
        else $this->orientations = array();
        return 1;
    }
}
$wpdb = new BioDatabase();
require __DIR__ . '/../membership-manager-qr/includes/bio-updates.php';
require __DIR__ . '/../membership-manager-qr/includes/member-portal-shortcodes.php';
require __DIR__ . '/../membership-manager-qr/includes/ajax-handlers.php';
function ajax($hook) {
    try { do_action($hook); } catch (BioResponse $response) { return $response; }
    throw new RuntimeException('Missing AJAX response');
}
function save_profile() {
    global $shortcodes;
    try { $shortcodes['mmgr_member_profile'](); } catch (BioResponse $response) { return $response; }
    throw new RuntimeException('Missing redirect');
}
expect(mmgr_bio_updates_menu_label() === 'Bio Updates', 'Empty history has no badge');
$before = mmgr_begin_bio_update(7);
expect(!is_wp_error($before) && mmgr_finish_bio_update(7, $before) === true && !$wpdb->updates, 'Unchanged saves do not create history');
$before = mmgr_begin_bio_update(7);
$wpdb->member['community_bio'] = '<script>bad</script>';
$wpdb->values[2] = 'https://example.com/custom.png';
expect(mmgr_finish_bio_update(7, $before) === true, 'Profile changes commit with history');
$changes = json_decode($wpdb->updates[1]['changes'], true);
expect(count($changes) === 2 && $changes[0]['before'] === 'Old bio' && $changes[0]['after'] === '<script>bad</script>', 'History preserves before/after text');
expect($changes[1]['type'] === 'image' && $changes[1]['after'] === 'https://example.com/custom.png', 'Custom images are tracked as photos');
expect(strpos(mmgr_bio_updates_menu_label(), '>1</span>') !== false, 'New changes restore the unread badge');
$wpdb->fail = 'history';
$before = mmgr_begin_bio_update(7);
$wpdb->member['community_alias'] = 'Untracked';
expect(is_wp_error(mmgr_finish_bio_update(7, $before)) && $wpdb->member['community_alias'] === 'Original' && count($wpdb->updates) === 1, 'History failure rolls back the content change');
$wpdb->fail = 'commit';
$before = mmgr_begin_bio_update(7);
$wpdb->member['community_alias'] = 'Uncommitted';
expect(is_wp_error(mmgr_finish_bio_update(7, $before)) && $wpdb->member['community_alias'] === 'Original' && count($wpdb->updates) === 1, 'Commit failure is explicit and rolls back history and content');
$wpdb->fail = 'snapshot';
expect(is_wp_error(mmgr_begin_bio_update(7)) && end($wpdb->writes) === 'ROLLBACK', 'Snapshot read failures stop tracking rather than inventing empty values');
$wpdb->fail = 'create';
expect(is_wp_error(mmgr_begin_bio_update(7)), 'Schema creation failure prevents untracked saves');
$wpdb->fail = '';
$wpdb->engines['wp_membership_bio_photos'] = 'MyISAM';
expect(!is_wp_error(mmgr_begin_bio_update(7)) && $wpdb->engines['wp_membership_bio_photos'] === 'InnoDB', 'Legacy photo storage is converted before starting a transaction');
mmgr_cancel_bio_update();
$wpdb->engines['wp_memberships'] = 'MyISAM';
expect(is_wp_error(mmgr_begin_bio_update(7)), 'Nontransactional membership storage fails explicitly');
$wpdb->engines['wp_memberships'] = 'InnoDB';

$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET = $_FILES = array();
$_POST = array('update_profile' => 1, 'profile_nonce' => 'valid', 'phone' => '456', 'email' => 'new@example.com',
    'community_alias' => 'New alias', 'community_bio' => 'New bio', 'bio_field_1' => 'New custom text', 'sexual_orientation' => array(2));
$count = count($wpdb->updates);
expect(save_profile()->success && count($wpdb->updates) === $count + 1, 'Actual profile form saves are wired to history before redirecting');
$changes = json_decode(end($wpdb->updates)['changes'], true);
expect(in_array('Community alias', array_column($changes, 'label')) && in_array('Custom text', array_column($changes, 'label')) && in_array('Orientation', array_column($changes, 'label')), 'Profile form records text, custom fields and selections together');
expect(!in_array('email', array_column($changes, 'label')) && strpos(end($wpdb->updates)['changes'], 'new@example.com') === false, 'Contact details are not copied into moderation history');
$count = count($wpdb->updates);
expect(save_profile()->success && count($wpdb->updates) === $count, 'Repeated form submissions with unchanged values do not create unread updates');
$_FILES = array(
    'community_photo' => array('name' => 'profile.png'),
    'bio_field_img_2' => array('name' => 'custom.png', 'tmp_name' => 'unused'),
);
$count = count($wpdb->updates);
expect(save_profile()->success && count($wpdb->updates) === $count + 1, 'Profile form tracks profile and custom image uploads');
$changes = json_decode(end($wpdb->updates)['changes'], true);
expect(count($changes) === 2 && $changes[0]['type'] === 'image' && $changes[1]['type'] === 'image', 'Both image changes retain image previews in history');
$_FILES = array();
$_POST['remove_community_photo'] = '1';
expect(save_profile()->success && $wpdb->member['community_photo_url'] === '', 'Profile photo removal is tracked');
$change = json_decode(end($wpdb->updates)['changes'], true)[0];
expect($change['label'] === 'Profile photo' && $change['before'] !== '' && $change['after'] === '', 'Removed profile photo remains available in the before snapshot');
$count = count($wpdb->updates);
$_POST = array('orientation_ids' => array(1));
expect(ajax('wp_ajax_nopriv_mmgr_save_orientations')->success && count($wpdb->updates) === $count + 1, 'Separate portal-authenticated orientation AJAX saves also create history');

$file = tmpfile();
fwrite($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1sAAAAASUVORK5CYII='));
$_FILES = array('photo' => array('name' => 'photo.png', 'tmp_name' => stream_get_meta_data($file)['uri']));
$count = count($wpdb->updates);
$response = ajax('wp_ajax_nopriv_mmgr_upload_bio_photo');
expect($response->success && count($wpdb->updates) === $count + 1 && count($wpdb->photos) === 1, 'Portal-authenticated photo upload records the photo addition');
$photo_id = $response->data['id'];
$_POST = array('photo_id' => $photo_id);
$response = ajax('wp_ajax_nopriv_mmgr_delete_bio_photo');
$change = json_decode(end($wpdb->updates)['changes'], true)[0];
expect($response->success && !$wpdb->photos && $change['type'] === 'image' && $change['before'] !== '' && $change['after'] === '', 'Photo deletion records the removed image for review');
$count = count($wpdb->updates);
expect(!ajax('wp_ajax_mmgr_delete_bio_photo')->success && count($wpdb->updates) === $count, 'Missing or foreign photos cannot create deletion history');
$wpdb->fail = 'history';
expect(!ajax('wp_ajax_mmgr_upload_bio_photo')->success && !$wpdb->photos && count($wpdb->updates) === $count, 'Photo upload history failure is not reported as success and rolls back the photo row');
$wpdb->fail = '';
$valid_nonce = false;
$writes = count($wpdb->writes);
expect(!ajax('wp_ajax_mmgr_upload_bio_photo')->success && count($wpdb->writes) === $writes, 'Invalid nonce cannot change photos or history');
$valid_nonce = true;
$logged_in = false;
expect(!ajax('wp_ajax_nopriv_mmgr_delete_bio_photo')->success && count($wpdb->writes) === $writes, 'Unauthenticated portal request cannot change history');
$logged_in = true;
fclose($file);

$base = $wpdb->updates[1];
$wpdb->updates = array();
for ($id = 1; $id <= 25; $id++) $wpdb->updates[$id] = array_replace($base, array('id' => $id));
$submenu = array('membership_manager' => array(array(mmgr_bio_updates_menu_label(), 'manage_options', 'membership_bio_updates')));
$_GET = array('page' => 'membership_bio_fields');
$writes = count($wpdb->writes);
mmgr_admin_bio_updates_load();
expect(count($wpdb->writes) === $writes, 'Opening BIO Fields does not mark updates viewed');
$_GET = array('page' => 'membership_bio_updates');
$wpdb->arriving_update = array_replace($base, array('id' => 26));
mmgr_admin_bio_updates_load();
expect(count($mmgr_bio_updates['rows']) === 20 && $wpdb->updates[6]['admin_read_at'] !== null && $wpdb->updates[5]['admin_read_at'] === null, 'Only the 20 displayed updates are marked viewed');
expect($wpdb->updates[26]['admin_read_at'] === null && strpos($submenu['membership_manager'][0][0], '>6</span>') !== false, 'Concurrent changes remain unread and the registered badge refreshes immediately');
ob_start();
mmgr_admin_bio_updates_page();
$html = ob_get_clean();
expect(strpos($html, '<script>') === false && strpos($html, '&lt;script&gt;bad') !== false, 'Changed text is escaped in the admin history');
expect(strpos($html, '&lt;b&gt;Member&lt;/b&gt;') !== false && strpos($html, 'custom.png') !== false, 'Member names are escaped and image history is displayed');
expect(strpos($html, 'New</span>') !== false && strpos($html, '>Next</a>') !== false, 'New indicators and pagination render from the loaded snapshot');
$_GET['paged'] = 2;
mmgr_admin_bio_updates_load();
$_GET['paged'] = 1;
mmgr_admin_bio_updates_load();
expect(mmgr_bio_updates_menu_label() === 'Bio Updates' && $submenu['membership_manager'][0][0] === 'Bio Updates', 'Badge disappears once all updates have been viewed');
$first_read = $wpdb->updates[1]['admin_read_at'];
mmgr_admin_bio_updates_load();
expect($wpdb->updates[1]['admin_read_at'] === $first_read, 'Revisiting preserves first-read timestamps and history');
$wpdb->updates[27] = array_replace($base, array('id' => 27));
expect(strpos(mmgr_bio_updates_menu_label(), '>1</span>') !== false, 'Future changes bring back the unread count');
$wpdb->fail = 'mark';
mmgr_admin_bio_updates_load();
expect($mmgr_bio_updates['notice'] !== '' && $wpdb->updates[27]['admin_read_at'] === null, 'Failed read marking displays an error and leaves the badge');
$wpdb->fail = '';
$wpdb->updates[28] = array_replace($base, array('id' => 28, 'changes' => '[1]'));
mmgr_admin_bio_updates_load();
expect($mmgr_bio_updates['notice'] !== '' && $wpdb->updates[28]['admin_read_at'] === null, 'Malformed history is not marked viewed');
$can_manage = false;
$writes = count($wpdb->writes);
try { mmgr_admin_bio_updates_load(); } catch (RuntimeException $error) { expect($error->getMessage() === 'Permission denied.', 'Non-admin load is denied'); }
try { mmgr_admin_bio_updates_page(); } catch (RuntimeException $error) { expect($error->getMessage() === 'Permission denied.', 'Non-admin rendering is denied'); }
expect(count($wpdb->writes) === $writes, 'Non-admin access cannot change unread state');
$menu = file_get_contents(__DIR__ . '/../membership-manager-qr/includes/admin-menu.php');
expect(strpos($menu, "'membership_bio_fields'") < strpos($menu, "'membership_bio_updates'") && strpos($menu, "'membership_bio_updates'") < strpos($menu, "'membership_help_topics'"), 'Bio Updates is directly below BIO Fields');
expect(strpos($menu, "add_action('load-' . \$bio_updates_hook, 'mmgr_admin_bio_updates_load')") !== false, 'Read state changes only on the real admin page load hook');
echo "Results: $passed passed\n";
