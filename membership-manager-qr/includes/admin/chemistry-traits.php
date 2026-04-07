<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$traits_tbl    = $wpdb->prefix . 'membership_chemistry_traits';
$questions_tbl = $wpdb->prefix . 'membership_chemistry_questions';
$answers_tbl   = $wpdb->prefix . 'membership_chemistry_answers';

// Ensure tables exist
if ($wpdb->get_var("SHOW TABLES LIKE '$traits_tbl'") !== $traits_tbl) {
    mmgr_migrate_chemistry_tables();
}

$success = $error = '';
$view = isset($_GET['view']) ? sanitize_key($_GET['view']) : 'traits';
$page_base = admin_url('admin.php?page=membership_chemistry');

// ── Save trait ─────────────────────────────────────────────────────────────
if (isset($_POST['save_trait']) && wp_verify_nonce($_POST['trait_nonce'], 'mmgr_save_trait')) {
    $trait_id    = intval($_POST['trait_id']);
    $trait_name  = sanitize_text_field($_POST['trait_name']);
    $description = sanitize_text_field($_POST['description'] ?? '');
    $sort_order  = intval($_POST['sort_order']);
    $active      = isset($_POST['active']) ? 1 : 0;

    if (empty($trait_name)) {
        $error = 'Trait name is required.';
    } elseif ($trait_id > 0) {
        $wpdb->update($traits_tbl,
            array('trait_name' => $trait_name, 'description' => $description, 'sort_order' => $sort_order, 'active' => $active),
            array('id' => $trait_id)
        );
        $success = 'Trait updated.';
    } else {
        $wpdb->insert($traits_tbl,
            array('trait_name' => $trait_name, 'description' => $description, 'sort_order' => $sort_order, 'active' => $active)
        );
        $success = 'Trait added.';
    }
}

// ── Delete trait ───────────────────────────────────────────────────────────
if (isset($_GET['delete_trait']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_trait_' . intval($_GET['delete_trait']))) {
    $del_tid = intval($_GET['delete_trait']);
    // Delete answers for questions belonging to this trait first
    $q_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM `$questions_tbl` WHERE trait_id = %d", $del_tid));
    if (!empty($q_ids)) {
        $placeholders = implode(',', array_fill(0, count($q_ids), '%d'));
        $wpdb->query($wpdb->prepare("DELETE FROM `$answers_tbl` WHERE question_id IN ($placeholders)", ...$q_ids));
    }
    $wpdb->delete($questions_tbl, array('trait_id' => $del_tid));
    $wpdb->delete($traits_tbl, array('id' => $del_tid));
    $success = 'Trait and its questions deleted.';
}

// ── Save question ──────────────────────────────────────────────────────────
if (isset($_POST['save_question']) && wp_verify_nonce($_POST['question_nonce'], 'mmgr_save_question')) {
    $q_id         = intval($_POST['question_id']);
    $q_trait_id   = intval($_POST['q_trait_id']);
    $question_text = sanitize_text_field($_POST['question_text']);
    $q_sort       = intval($_POST['q_sort_order']);
    $q_active     = isset($_POST['q_active']) ? 1 : 0;

    if (empty($question_text) || !$q_trait_id) {
        $error = 'Question text and trait are required.';
    } elseif ($q_id > 0) {
        $wpdb->update($questions_tbl,
            array('question_text' => $question_text, 'sort_order' => $q_sort, 'active' => $q_active),
            array('id' => $q_id)
        );
        $success = 'Question updated.';
    } else {
        $wpdb->insert($questions_tbl,
            array('trait_id' => $q_trait_id, 'question_text' => $question_text, 'sort_order' => $q_sort, 'active' => $q_active)
        );
        $success = 'Question added.';
    }
    $view = 'questions';
}

// ── Delete question ────────────────────────────────────────────────────────
if (isset($_GET['delete_question']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_question_' . intval($_GET['delete_question']))) {
    $del_qid = intval($_GET['delete_question']);
    $wpdb->delete($answers_tbl, array('question_id' => $del_qid));
    $wpdb->delete($questions_tbl, array('id' => $del_qid));
    $success = 'Question deleted.';
    $view = 'questions';
}

// ── Fetch data ─────────────────────────────────────────────────────────────
$traits      = $wpdb->get_results("SELECT * FROM `$traits_tbl` ORDER BY sort_order ASC, id ASC", ARRAY_A);
$editing_trait = null;
if (isset($_GET['edit_trait'])) {
    $editing_trait = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$traits_tbl` WHERE id = %d", intval($_GET['edit_trait'])), ARRAY_A);
}
$sort_orders = array_column($traits, 'sort_order');
$next_sort   = $sort_orders ? max($sort_orders) + 10 : 10;

// For questions view
$filter_trait_id = isset($_GET['trait_id']) ? intval($_GET['trait_id']) : 0;
$editing_question = null;
if (isset($_GET['edit_question'])) {
    $editing_question = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$questions_tbl` WHERE id = %d", intval($_GET['edit_question'])), ARRAY_A);
    if ($editing_question) $filter_trait_id = intval($editing_question['trait_id']);
    $view = 'questions';
}
$questions = array();
if ($view === 'questions' && $filter_trait_id) {
    $questions = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM `$questions_tbl` WHERE trait_id = %d ORDER BY sort_order ASC, id ASC", $filter_trait_id),
        ARRAY_A
    );
}
$q_sort_orders = array_column($questions, 'sort_order');
$q_next_sort   = $q_sort_orders ? max($q_sort_orders) + 10 : 10;
?>
<div class="wrap">
    <h1>🧪 Chemistry Profile Traits</h1>
    <p class="description" style="font-size:14px;margin-bottom:20px;">
        Manage the personality traits and questions used in the chemistry analysis poll. Members answer questions via 0–100% sliders on their profile. Results are displayed as a bar chart on community profiles.
    </p>

    <!-- Tab navigation -->
    <nav class="nav-tab-wrapper" style="margin-bottom:20px;">
        <a href="<?php echo esc_url($page_base . '&view=traits'); ?>"
           class="nav-tab<?php echo $view !== 'questions' && $view !== 'results' ? ' nav-tab-active' : ''; ?>">
            🏷️ Traits
        </a>
        <a href="<?php echo esc_url($page_base . '&view=results'); ?>"
           class="nav-tab<?php echo $view === 'results' ? ' nav-tab-active' : ''; ?>">
            📊 Community Results
        </a>
    </nav>

    <?php if ($success): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($success); ?></p></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
    <?php endif; ?>

    <?php if ($view === 'results'): ?>
    <!-- ── Community Results ─────────────────────────────────────────── -->
    <?php
    $member_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}memberships` WHERE active = 1");
    $result_rows = $wpdb->get_results(
        "SELECT t.trait_name, t.description,
                ROUND(AVG(a.answer_value)) AS avg_score,
                COUNT(DISTINCT a.member_id) AS respondent_count
         FROM `$traits_tbl` t
         JOIN `$questions_tbl` q ON q.trait_id = t.id AND q.active = 1
         JOIN `$answers_tbl` a ON a.question_id = q.id
         WHERE t.active = 1
         GROUP BY t.id, t.trait_name, t.description
         ORDER BY avg_score DESC",
        ARRAY_A
    );
    ?>
    <div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px;">
        <h2 style="margin-top:0;">Community Average Scores</h2>
        <p style="color:#666;font-size:13px;margin-top:0;">Total active members: <strong><?php echo $member_count; ?></strong></p>
        <?php if (empty($result_rows)): ?>
            <p style="color:#666;">No chemistry answers submitted yet.</p>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:12px;margin-top:15px;">
            <?php foreach ($result_rows as $row):
                $score = (int) $row['avg_score'];
                if ($score >= 70)      { $color = '#00a32a'; }
                elseif ($score >= 40)  { $color = '#dba617'; }
                else                   { $color = '#d63638'; }
            ?>
            <div style="display:flex;align-items:center;gap:12px;">
                <span style="min-width:42px;text-align:right;font-weight:bold;color:<?php echo $color; ?>;font-size:14px;"><?php echo $score; ?>%</span>
                <div style="flex:1;background:#f0f0f0;border-radius:4px;height:12px;overflow:hidden;max-width:400px;">
                    <div style="width:<?php echo $score; ?>%;background:<?php echo $color; ?>;height:100%;border-radius:4px;"></div>
                </div>
                <span style="min-width:160px;font-size:14px;color:#333;font-weight:bold;"><?php echo esc_html($row['trait_name']); ?></span>
                <span style="font-size:12px;color:#888;"><?php echo intval($row['respondent_count']); ?> member<?php echo $row['respondent_count'] != 1 ? 's' : ''; ?> responded</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php elseif ($view === 'questions' && $filter_trait_id): ?>
    <!-- ── Questions for a trait ─────────────────────────────────────── -->
    <?php
    $current_trait = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$traits_tbl` WHERE id = %d", $filter_trait_id), ARRAY_A);
    ?>
    <p>
        <a href="<?php echo esc_url($page_base); ?>" class="button">← Back to Traits</a>
    </p>
    <h2>Questions for: <em><?php echo esc_html($current_trait ? $current_trait['trait_name'] : ''); ?></em></h2>

    <div style="display:grid;grid-template-columns:400px 1fr;gap:30px;align-items:start;">
        <!-- Form -->
        <div style="background:#fff;border:1px solid #ddd;padding:20px;border-radius:6px;">
            <h3 style="margin-top:0;"><?php echo $editing_question ? 'Edit Question' : 'Add Question'; ?></h3>
            <form method="POST">
                <?php wp_nonce_field('mmgr_save_question', 'question_nonce'); ?>
                <input type="hidden" name="question_id" value="<?php echo $editing_question ? intval($editing_question['id']) : 0; ?>">
                <input type="hidden" name="q_trait_id" value="<?php echo $filter_trait_id; ?>">
                <input type="hidden" name="view" value="questions">

                <div style="margin-bottom:15px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;">Question Text *</label>
                    <textarea name="question_text" rows="3"
                              style="width:100%;padding:8px;border:1px solid #8c8f94;border-radius:4px;font-size:14px;"
                              required><?php echo $editing_question ? esc_textarea($editing_question['question_text']) : ''; ?></textarea>
                    <p class="description">Members will answer this on a 0–100% slider.</p>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:15px;">
                    <div>
                        <label style="display:block;font-weight:bold;margin-bottom:6px;">Sort Order</label>
                        <input type="number" name="q_sort_order"
                               value="<?php echo $editing_question ? intval($editing_question['sort_order']) : $q_next_sort; ?>"
                               style="width:100%;padding:8px;border:1px solid #8c8f94;border-radius:4px;">
                    </div>
                    <div style="padding-top:26px;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:bold;">
                            <input type="checkbox" name="q_active" value="1"
                                   <?php checked($editing_question ? intval($editing_question['active']) : 1, 1); ?>>
                            Active
                        </label>
                    </div>
                </div>

                <div style="display:flex;gap:8px;">
                    <button type="submit" name="save_question" class="button button-primary">
                        <?php echo $editing_question ? '💾 Update Question' : '➕ Add Question'; ?>
                    </button>
                    <?php if ($editing_question): ?>
                        <a href="<?php echo esc_url($page_base . '&view=questions&trait_id=' . $filter_trait_id); ?>" class="button">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- List -->
        <div>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Question</th>
                        <th style="width:60px;text-align:center;">Order</th>
                        <th style="width:80px;text-align:center;">Status</th>
                        <th style="width:130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($questions)): ?>
                        <tr><td colspan="4" style="text-align:center;padding:30px;color:#666;">No questions yet — add one using the form.</td></tr>
                    <?php else: ?>
                        <?php foreach ($questions as $q): ?>
                        <tr>
                            <td style="padding:10px 8px;"><?php echo esc_html($q['question_text']); ?></td>
                            <td style="text-align:center;"><?php echo intval($q['sort_order']); ?></td>
                            <td style="text-align:center;">
                                <?php if ($q['active']): ?>
                                    <span style="background:#00a32a;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold;">Active</span>
                                <?php else: ?>
                                    <span style="background:#999;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold;">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:8px;">
                                <a href="<?php echo esc_url($page_base . '&edit_question=' . intval($q['id']) . '&trait_id=' . $filter_trait_id); ?>"
                                   class="button button-small">Edit</a>
                                <a href="<?php echo esc_url(wp_nonce_url($page_base . '&delete_question=' . intval($q['id']) . '&trait_id=' . $filter_trait_id, 'delete_question_' . intval($q['id']))); ?>"
                                   class="button button-small"
                                   style="color:#d63638;border-color:#d63638;"
                                   onclick="return confirm('Delete this question? All member answers to it will be removed.');">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php else: ?>
    <!-- ── Traits list ───────────────────────────────────────────────── -->
    <div style="display:grid;grid-template-columns:400px 1fr;gap:30px;align-items:start;">
        <!-- Form -->
        <div style="background:#fff;border:1px solid #ddd;padding:20px;border-radius:6px;">
            <h2 style="margin-top:0;"><?php echo $editing_trait ? 'Edit Trait' : 'Add New Trait'; ?></h2>
            <form method="POST">
                <?php wp_nonce_field('mmgr_save_trait', 'trait_nonce'); ?>
                <input type="hidden" name="trait_id" value="<?php echo $editing_trait ? intval($editing_trait['id']) : 0; ?>">

                <div style="margin-bottom:15px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;">Trait Name *</label>
                    <input type="text" name="trait_name"
                           value="<?php echo $editing_trait ? esc_attr($editing_trait['trait_name']) : ''; ?>"
                           style="width:100%;padding:8px;border:1px solid #8c8f94;border-radius:4px;font-size:14px;"
                           required placeholder="e.g. Dominant, Voyeur, Switch">
                </div>

                <div style="margin-bottom:15px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;">Short Description</label>
                    <input type="text" name="description"
                           value="<?php echo $editing_trait ? esc_attr($editing_trait['description']) : ''; ?>"
                           style="width:100%;padding:8px;border:1px solid #8c8f94;border-radius:4px;font-size:14px;"
                           placeholder="Brief description shown to members">
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:15px;">
                    <div>
                        <label style="display:block;font-weight:bold;margin-bottom:6px;">Sort Order</label>
                        <input type="number" name="sort_order"
                               value="<?php echo $editing_trait ? intval($editing_trait['sort_order']) : $next_sort; ?>"
                               style="width:100%;padding:8px;border:1px solid #8c8f94;border-radius:4px;">
                        <p class="description">Lower numbers appear first.</p>
                    </div>
                    <div style="padding-top:26px;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:bold;">
                            <input type="checkbox" name="active" value="1"
                                   <?php checked($editing_trait ? intval($editing_trait['active']) : 1, 1); ?>>
                            Active
                        </label>
                        <p class="description">Inactive traits are hidden.</p>
                    </div>
                </div>

                <div style="display:flex;gap:8px;">
                    <button type="submit" name="save_trait" class="button button-primary">
                        <?php echo $editing_trait ? '💾 Update Trait' : '➕ Add Trait'; ?>
                    </button>
                    <?php if ($editing_trait): ?>
                        <a href="<?php echo esc_url($page_base); ?>" class="button">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Traits list -->
        <div>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Trait Name</th>
                        <th>Description</th>
                        <th style="width:60px;text-align:center;">Order</th>
                        <th style="width:60px;text-align:center;">Questions</th>
                        <th style="width:80px;text-align:center;">Status</th>
                        <th style="width:160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($traits)): ?>
                        <tr><td colspan="6" style="text-align:center;padding:30px;color:#666;">No traits yet — add one using the form.</td></tr>
                    <?php else: ?>
                        <?php foreach ($traits as $t):
                            $q_count = (int) $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM `$questions_tbl` WHERE trait_id = %d AND active = 1", intval($t['id'])
                            ));
                        ?>
                        <tr>
                            <td style="padding:10px 8px;font-weight:bold;"><?php echo esc_html($t['trait_name']); ?></td>
                            <td style="padding:10px 8px;color:#666;font-size:13px;"><?php echo esc_html($t['description']); ?></td>
                            <td style="text-align:center;"><?php echo intval($t['sort_order']); ?></td>
                            <td style="text-align:center;">
                                <a href="<?php echo esc_url($page_base . '&view=questions&trait_id=' . intval($t['id'])); ?>"
                                   title="Manage questions for this trait">
                                    <?php echo $q_count; ?> ✏️
                                </a>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($t['active']): ?>
                                    <span style="background:#00a32a;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold;">Active</span>
                                <?php else: ?>
                                    <span style="background:#999;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold;">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:8px;">
                                <a href="<?php echo esc_url($page_base . '&edit_trait=' . intval($t['id'])); ?>"
                                   class="button button-small">Edit</a>
                                <a href="<?php echo esc_url($page_base . '&view=questions&trait_id=' . intval($t['id'])); ?>"
                                   class="button button-small">Questions</a>
                                <a href="<?php echo esc_url(wp_nonce_url($page_base . '&delete_trait=' . intval($t['id']), 'delete_trait_' . intval($t['id']))); ?>"
                                   class="button button-small"
                                   style="color:#d63638;border-color:#d63638;"
                                   onclick="return confirm('Delete trait &quot;<?php echo esc_js($t['trait_name']); ?>&quot; and ALL its questions and answers?\n\nThis cannot be undone.');">
                                    Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div style="margin-top:15px;padding:14px 16px;background:#f0f8ff;border-left:4px solid #0073aa;border-radius:4px;font-size:13px;line-height:1.6;">
                <strong>ℹ️ How it works:</strong>
                <ul style="margin:6px 0 0 18px;padding:0;">
                    <li>Each trait can have multiple questions. A trait's score is the average of all its questions' answers.</li>
                    <li>Members answer each question via a 0–100% slider on their Profile page.</li>
                    <li>Results are displayed as a colour-coded bar chart on community profile pages.</li>
                    <li>Members control whether their results are visible to others via a privacy setting on their profile.</li>
                    <li>Click the number in the <strong>Questions</strong> column to manage questions for a trait.</li>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
