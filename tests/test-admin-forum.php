<?php
// Run with: php tests/test-admin-forum.php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

$passed = 0;
$can_manage = true;
$valid_nonce = true;
$actions = array();
function expect($condition, $description) {
    global $passed;
    if (!$condition) throw new RuntimeException('FAIL: ' . $description);
    $passed++;
    echo "PASS: $description\n";
}
class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function add_action($hook, $callback, $priority = 10) { global $actions; $actions[$hook] = array($callback, $priority); }
function current_user_can($capability) { global $can_manage; return $can_manage && $capability === 'manage_options'; }
function wp_verify_nonce($nonce, $action) { global $valid_nonce; return $valid_nonce && $nonce === $action; }
function current_time($format) { return '2026-10-27 16:00:00'; }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z_]/', '', $value); }
function wp_unslash($value) { return stripslashes($value); }
function sanitize_textarea_field($value) { return trim(strip_tags($value)); }
function wp_die($message) { throw new RuntimeException($message); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_textarea($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function admin_url($path) { return 'https://example.com/wp-admin/' . $path; }
function add_query_arg($key, $value, $url) { return $url . '&' . $key . '=' . $value; }
function wp_nonce_field($action, $name) {}

class ForumDatabase {
    public $prefix = 'wp_';
    public $posts = array();
    public $writes = array();
    public $history = array();
    public $members = array(7 => array('forum_banned' => 0));
    public $fail = '';
    public $column_exists = true;
    public $arriving_post = null;
    public function prepare($sql, ...$args) {
        if (count($args) === 1 && is_array($args[0])) $args = $args[0];
        foreach ($args as $arg) {
            $sql = preg_replace_callback('/%[ds]/', function($match) use ($arg) {
                return $match[0] === '%d' ? (string) (int) $arg : "'" . addslashes($arg) . "'";
            }, $sql, 1);
        }
        return $sql;
    }
    public function get_var($sql) {
        if (strpos($sql, 'SHOW COLUMNS') === 0) return $this->column_exists ? 'admin_read_at' : null;
        if (strpos($sql, 'IS NULL') !== false) {
            return count(array_filter($this->posts, function($p) { return $p['admin_read_at'] === null; }));
        }
        return count($this->posts);
    }
    public function get_row($sql, $format) {
        preg_match('/id = (\d+)/', $sql, $matches);
        return $this->posts[(int) $matches[1]] ?? null;
    }
    public function get_results($sql, $format) {
        if (strpos($sql, 'SELECT p.*') === 0) {
            if (preg_match('/WHERE p.id = (\d+)/', $sql, $matches)) {
                return isset($this->posts[$matches[1]]) ? array($this->posts[$matches[1]]) : array();
            }
            preg_match('/LIMIT (\d+) OFFSET (\d+)/', $sql, $matches);
            $posts = $this->posts;
            krsort($posts);
            return array_slice(array_values($posts), (int) $matches[2], (int) $matches[1]);
        }
        if ($this->arriving_post) {
            $this->posts[$this->arriving_post['id']] = $this->arriving_post;
            $this->arriving_post = null;
        }
        return array(array('post_id' => 25, 'photo_url' => 'https://example.com/photo.jpg'));
    }
    public function query($sql) {
        $this->writes[] = $sql;
        if ($this->fail === 'query') return false;
        if (strpos($sql, 'UPDATE') === 0) {
            preg_match('/id IN \(([\d,]+)\)/', $sql, $matches);
            foreach (explode(',', $matches[1]) as $id) {
                if ($this->posts[$id]['admin_read_at'] === null) {
                    $this->posts[$id]['admin_read_at'] = current_time('mysql');
                }
            }
        }
        return 1;
    }
    public function insert($table, $data) {
        $this->writes[] = array($table, $data);
        if ($this->fail === 'history') return false;
        $this->history[] = $data;
        return 1;
    }
    public function update($table, $data, $where) {
        $this->writes[] = array($table, $data, $where);
        if ($this->fail === 'update') return false;
        if ($table === 'wp_memberships') {
            $this->members[$where['id']] = array_merge($this->members[$where['id']], $data);
        } else {
            $this->posts[$where['id']] = array_merge($this->posts[$where['id']], $data);
        }
        return 1;
    }
    public function delete($table, $where) {
        $this->writes[] = array($table, $where);
        if ($this->fail === 'delete') return false;
        if ($table === 'wp_membership_forum_posts') unset($this->posts[$where['id']]);
        return 1;
    }
}
$wpdb = new ForumDatabase();
require __DIR__ . '/../membership-manager-qr/includes/admin-forum.php';
$base = array('id' => 1, 'member_id' => 7, 'topic_id' => 3, 'message' => '<script>alert(1)</script>',
    'member_name' => '<b>Member</b>', 'community_alias' => 'Alias', 'forum_banned' => 0,
    'posted_at' => '2026-10-27 12:00:00', 'edited_at' => null, 'admin_read_at' => null,
    'photo_url' => 'https://example.com/legacy.jpg', 'hidden' => 1, 'topic_name' => null);
for ($id = 1; $id <= 25; $id++) $wpdb->posts[$id] = array_replace($base, array('id' => $id));
expect(strpos(mmgr_forum_posts_menu_label(), '>25</span>') !== false, 'Menu counts all unread posts including hidden and orphaned posts');
$submenu = array('membership_manager' => array(array(mmgr_forum_posts_menu_label(), 'manage_options', 'membership_forum_posts')));
$wpdb->column_exists = false;
expect(mmgr_forum_posts_menu_label() === 'Forum Posts', 'Menu tolerates a not-yet-migrated installation');
$wpdb->column_exists = true;
$_GET = array('page' => 'membership_forum_topics');
$_SERVER['REQUEST_METHOD'] = 'GET';
mmgr_admin_forum_load();
expect(!$wpdb->writes, 'Opening Forum Topics does not mark forum posts read');
$_GET = array('page' => 'membership_forum_posts');
$wpdb->arriving_post = array_replace($base, array('id' => 26));
mmgr_admin_forum_load();
expect(count($mmgr_admin_forum['rows']) === 20, 'Management page is paginated');
expect($wpdb->posts[6]['admin_read_at'] !== null && $wpdb->posts[5]['admin_read_at'] === null, 'Only displayed posts are marked read');
expect($wpdb->posts[26]['admin_read_at'] === null, 'A post arriving during page load stays unread');
expect(strpos($submenu['membership_manager'][0][0], '>6</span>') !== false, 'Already-registered menu immediately reflects viewed posts');
expect($mmgr_admin_forum['rows'][0]['admin_read_at'] === null, 'New labels use the original unread snapshot');
ob_start();
mmgr_admin_forum_page();
$html = ob_get_clean();
expect(strpos($html, '<script>') === false && strpos($html, '&lt;script&gt;') !== false, 'Post text is escaped');
expect(strpos($html, '&lt;b&gt;Member&lt;/b&gt;') !== false, 'Member names are escaped');
expect(strpos($html, 'Deleted / unknown topic') !== false && strpos($html, 'Hidden from members') !== false, 'Orphaned and hidden posts remain manageable');
expect(strpos($html, 'legacy.jpg') !== false && strpos($html, '/photo.jpg') !== false, 'Both legacy and multi-photo attachments are visible');
$wpdb->posts[25]['admin_read_at'] = '2026-10-26 10:00:00';
mmgr_admin_forum_mark_read(array(25));
expect($wpdb->posts[25]['admin_read_at'] === '2026-10-26 10:00:00', 'Repeated reads preserve the first admin-read timestamp');
$_GET['paged'] = 2;
mmgr_admin_forum_load();
$_GET = array('page' => 'membership_forum_posts', 'edit' => 26);
mmgr_admin_forum_load();
expect(count($mmgr_admin_forum['rows']) === 1 && mmgr_forum_posts_menu_label() === 'Forum Posts', 'Opening a single post clears only its unread flag; badge disappears at zero');
expect($submenu['membership_manager'][0][0] === 'Forum Posts', 'Already-registered menu hides the badge at zero');
expect($wpdb->posts[25]['admin_read_at'] === '2026-10-26 10:00:00', 'Read state persists across management views');

$input = array('forum_nonce' => 'mmgr_admin_forum_25', 'message' => "Updated \\'post\\' <b>text</b>", 'member_id' => 999, 'reason' => 'Spam');
$before = count($wpdb->writes);
$can_manage = false;
expect(is_wp_error(mmgr_admin_forum_moderate('delete', 25, $input)), 'Non-admin moderation is denied');
mmgr_admin_forum_mark_read(array(1));
try {
    mmgr_admin_forum_load();
    throw new RuntimeException('Expected permission rejection');
} catch (RuntimeException $error) {
    expect($error->getMessage() === 'Permission denied.', 'Non-admin page access is denied');
}
expect(count($wpdb->writes) === $before, 'Unauthorized requests cannot change read state or content');
$can_manage = true;
$valid_nonce = false;
expect(is_wp_error(mmgr_admin_forum_moderate('delete', 25, $input)), 'Invalid nonce cannot moderate posts');
$valid_nonce = true;
expect(is_wp_error(mmgr_admin_forum_moderate('other', 25, $input)), 'Unknown actions are rejected');
expect(is_wp_error(mmgr_admin_forum_moderate('edit', 999, array('forum_nonce' => 'mmgr_admin_forum_999'))), 'Missing posts cannot be edited');
expect(is_wp_error(mmgr_admin_forum_moderate('edit', 25, array_replace($input, array('message' => '  ')))), 'Empty edits are rejected');
expect(count($wpdb->writes) === $before, 'Invalid moderation requests perform no writes');
$wpdb->fail = 'history';
expect(is_wp_error(mmgr_admin_forum_moderate('edit', 25, $input)) && $wpdb->posts[25]['message'] === $base['message'], 'History failure prevents an edit');
$wpdb->fail = '';
expect(!is_wp_error(mmgr_admin_forum_moderate('edit', 25, $input)), 'Admin can edit a post');
expect(end($wpdb->history)['old_message'] === $base['message'], 'Edits preserve the previous message in forum history');
expect($wpdb->posts[25]['message'] === "Updated 'post' text" && $wpdb->posts[25]['edited_at'] === current_time('mysql'), 'Edits are unslashed, sanitized and timestamped');
$wpdb->fail = 'update';
expect(is_wp_error(mmgr_admin_forum_moderate('ban', 25, $input)), 'Database failure is not reported as success');
$wpdb->fail = '';
mmgr_admin_forum_moderate('ban', 25, $input);
expect($wpdb->members[7] === array('forum_banned' => 1, 'forum_banned_reason' => 'Spam'), 'Ban targets the stored post author and changes only forum ban fields');
mmgr_admin_forum_moderate('unban', 25, $input);
expect($wpdb->members[7] === array('forum_banned' => 0, 'forum_banned_reason' => null), 'Admin can reverse a forum ban');
$wpdb->fail = 'delete';
expect(is_wp_error(mmgr_admin_forum_moderate('delete', 25, $input)) && isset($wpdb->posts[25]), 'Failed dependent cleanup retains the post for retry');
$wpdb->fail = '';
$wpdb->writes = array();
expect(!is_wp_error(mmgr_admin_forum_moderate('delete', 25, $input)) && !isset($wpdb->posts[25]), 'Admin can delete a forum post');
foreach (array('comments', 'likes', 'photos', 'history', 'reports') as $suffix) {
    expect(in_array('wp_membership_forum_post_' . $suffix, array_column(array_filter($wpdb->writes, 'is_array'), 0)), 'Deletion removes post ' . $suffix);
}
expect(strpos($wpdb->writes[0], 'membership_forum_comment_likes') !== false, 'Deletion removes likes on deleted comments');
$schema = file_get_contents(__DIR__ . '/../membership-manager-qr/includes/database.php');
$migration = file_get_contents(__DIR__ . '/../membership-manager-qr/includes/member-portal-functions.php');
expect(strpos($schema, 'admin_read_at DATETIME NULL DEFAULT NULL') !== false, 'Fresh installations default posts to unread');
expect(strpos($migration, "LIKE 'admin_read_at'") !== false && strpos($migration, 'ADD COLUMN `admin_read_at` DATETIME NULL DEFAULT NULL') !== false, 'Existing installations receive an idempotent unread-column migration');
$menu = file_get_contents(__DIR__ . '/../membership-manager-qr/includes/admin-menu.php');
expect(strpos($menu, "'membership_forum_topics'") < strpos($menu, "'membership_forum_posts'") && strpos($menu, "'membership_forum_posts'") < strpos($menu, "'membership_card_requests'"), 'Forum Posts is registered directly below Forum Topics');
expect(strpos($menu, "add_action('load-' . \$forum_posts_hook, 'mmgr_admin_forum_load')") !== false && !isset($actions['admin_init']), 'Post loading is scoped to the real page load hook, not AJAX or other admin requests');
echo "Results: $passed passed\n";
