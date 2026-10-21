<?php
if (!defined('ABSPATH')) exit;

/**
 * Financial activity is separate from attendance. Never backfill historical sales.
 * Create the InnoDB ledger before starting a grant transaction (DDL commits in MySQL).
 */
function mmgr_ensure_account_transactions_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'membership_account_transactions';
    return $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        event_id INT NULL,
        activity VARCHAR(40) NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        payment_method VARCHAR(50) NOT NULL,
        description TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        created_by BIGINT UNSIGNED NOT NULL,
        operation_key VARCHAR(100) NOT NULL,
        UNIQUE KEY operation_key (operation_key),
        KEY member_activity (member_id, id),
        KEY created_at (created_at)
    ) ENGINE=InnoDB " . $wpdb->get_charset_collate()) !== false;
}

function mmgr_record_account_transaction($member_id, $activity, $amount, $method, $description, $key, $event_id = null) {
    global $wpdb;
    return $wpdb->insert($wpdb->prefix . 'membership_account_transactions', array(
        'member_id' => $member_id,
        'event_id' => $event_id,
        'activity' => $activity,
        'amount' => $amount,
        'payment_method' => $method,
        'description' => $description,
        'created_at' => current_time('mysql'),
        'created_by' => get_current_user_id(),
        'operation_key' => $key,
    )) !== false;
}

function mmgr_ensure_account_grant_storage($with_tickets) {
    global $wpdb;
    $tables = array('memberships', 'membership_account_transactions');
    if ($with_tickets) {
        $tables = array_merge($tables, array('membership_member_event_tickets', 'membership_event_rsvps'));
    }
    foreach ($tables as $suffix) {
        $table = $wpdb->prefix . $suffix;
        $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table), ARRAY_A);
        if (!$status || empty($status['Engine'])) {
            return false;
        }
        if (strcasecmp($status['Engine'], 'InnoDB') === 0) {
            continue;
        }
        // Older portal tables inherited the server default, which could be MyISAM.
        // Convert before START TRANSACTION: ALTER TABLE implicitly commits.
        if (!in_array($suffix, array('membership_member_event_tickets', 'membership_event_rsvps'), true) ||
            $wpdb->query("ALTER TABLE `$table` ENGINE=InnoDB") === false) {
            return false;
        }
        $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table), ARRAY_A);
        if (!$status || strcasecmp($status['Engine'] ?? '', 'InnoDB') !== 0) {
            return false;
        }
    }
    return true;
}

function mmgr_comp_expiry($start, $months) {
    $date = new DateTimeImmutable($start);
    $target = $date->modify('first day of this month')->modify('+' . (int) $months . ' months');
    return $target->setDate((int) $target->format('Y'), (int) $target->format('m'),
        min((int) $date->format('d'), (int) $target->format('t')))->format('Y-m-d');
}

/**
 * Save the admin account form and grants as one unit. Lock the account before
 * comparing payment/ticket state, so concurrent saves cannot record the same grant.
 * Returns the saved ID, or false. Notes/passwords are handled after this commit.
 */
function mmgr_save_admin_account($member_id, $data, $request) {
    global $wpdb;
    if (!current_user_can('manage_options') ||
        !isset($request['member_nonce']) || !wp_verify_nonce($request['member_nonce'], 'mmgr_save_member')) {
        return false;
    }
    $operation = $request['membership_operation'] ?? '';
    $key = $request['account_operation_key'] ?? '';
    if (!is_string($operation) || !in_array($operation, array('', 'paid', 'comp_1', 'comp_12'), true) ||
        !is_string($key) || !preg_match('/^[a-f0-9]{32}$/', $key) ||
        (isset($request['amount_paid']) && !is_numeric($request['amount_paid'])) ||
        !is_finite((float) $data['payment_amount']) || $data['payment_amount'] < 0 || $data['payment_amount'] > 99999999.99 ||
        !mmgr_ensure_account_transactions_table() ||
        !mmgr_ensure_account_grant_storage(isset($request['mmgr_paid_tickets_present'])) ||
        $wpdb->query('START TRANSACTION') === false) {
        return false;
    }
    $members = $wpdb->prefix . 'memberships';
    $ledger = $wpdb->prefix . 'membership_account_transactions';
    try {
        $old = $member_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $members WHERE id = %d FOR UPDATE", $member_id), ARRAY_A) : array();
        if ($member_id && !$old) {
            throw new RuntimeException('Missing member');
        }
        $replayed = $member_id && $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $ledger WHERE member_id = %d AND operation_key = %s",
            $member_id, $key . ':membership'
        ));
        $comp = in_array($operation, array('comp_1', 'comp_12'), true);
        if ($operation === 'paid') {
            $data['paid'] = 1;
        }
        if ($replayed) {
            foreach (array('paid', 'payment_amount', 'payment_method', 'payment_date', 'start_date', 'expire_date') as $field) {
                $data[$field] = $old[$field] ?? null;
            }
        } elseif ($comp) {
            $data['paid'] = 1;
            $data['payment_amount'] = 0;
            $data['payment_method'] = 'COMP';
            $data['payment_date'] = current_time('mysql');
            $data['start_date'] = substr(current_time('mysql'), 0, 10);
            $data['expire_date'] = mmgr_comp_expiry($data['start_date'], $operation === 'comp_1' ? 1 : 12);
        } elseif (($old['payment_method'] ?? '') === 'COMP' && $operation === '' && !empty($data['paid'])) {
            // Ordinary resaves must not silently turn a complimentary grant into a sale.
            $data['payment_amount'] = 0;
            $data['payment_method'] = 'COMP';
        }
        $sale = !$replayed && !$comp && !empty($data['paid']) &&
            ($operation === 'paid' || empty($old['paid']));
        if ($sale) {
            $data['payment_method'] = 'Admin';
            $data['payment_date'] = current_time('mysql');
        }
        if ($member_id) {
            $saved = $wpdb->update($members, $data, array('id' => $member_id));
        } else {
            $data['member_code'] = mmgr_generate_member_code($data['name']);
            $saved = $wpdb->insert($members, $data);
            $member_id = (int) $wpdb->insert_id;
        }
        if ($saved === false || !$member_id) {
            throw new RuntimeException('Account save failed');
        }
        if (!$replayed && ($comp || $sale)) {
            $label = $comp ? "COMP'D MEMBERSHIP" : 'PAID MEMBERSHIP';
            $description = $label . ' — ' . $data['level'] . ' — ' . $data['start_date'] . ' to ' . ($data['expire_date'] ?? 'no expiry');
            if ($comp) {
                $description .= $operation === 'comp_1' ? ' (1 month)' : ' (12 months)';
            }
            if (!mmgr_record_account_transaction($member_id, $comp ? 'membership_comp' : 'membership_sale',
                $data['payment_amount'], $data['payment_method'], $description, $key . ':membership')) {
                throw new RuntimeException('Membership log failed');
            }
        }
        if (isset($request['mmgr_paid_tickets_present'])) {
            $ids = $request['paid_event_ids'] ?? array();
            $methods = $request['ticket_methods'] ?? array();
            if (!is_array($ids) || !is_array($methods)) {
                throw new RuntimeException('Invalid ticket selection');
            }
            $normalized_ids = array();
            foreach ($ids as $id) {
                if (!is_scalar($id) || !ctype_digit((string) $id) || (int) $id <= 0) {
                    throw new RuntimeException('Invalid ticket ID');
                }
                $normalized_ids[] = (int) $id;
            }
            $ids = array_values(array_unique($normalized_ids));
            $before = mmgr_get_member_ticket_options($member_id);
            if ($before === null || !mmgr_save_member_paid_tickets($member_id, $ids)) {
                throw new RuntimeException('Ticket save failed');
            }
            foreach ($before as $event) {
                $id = (int) $event['id'];
                $selected = in_array($id, $ids, true);
                if ($selected === !empty($event['paid_ticket_id'])) {
                    continue;
                }
                $method = $methods[$id] ?? 'paid';
                if (!in_array($method, array('paid', 'comp'), true)) {
                    throw new RuntimeException('Invalid ticket method');
                }
                $amount = 0;
                $activity = 'ticket_removed';
                $label = 'TICKET ACCESS REMOVED (not a refund)';
                if ($selected) {
                    $prices = mmgr_get_event_ticket_prices($event);
                    if ($method === 'paid' && !isset($prices[$data['level']])) {
                        throw new RuntimeException('No ticket price for membership level');
                    }
                    $amount = $method === 'comp' ? 0 : $prices[$data['level']];
                    $activity = $method === 'comp' ? 'ticket_comp' : 'ticket_sale';
                    $label = $method === 'comp' ? "COMP'D TICKET" : 'PAID TICKET';
                }
                if (!mmgr_record_account_transaction($member_id, $activity, $amount,
                    $selected ? ($method === 'comp' ? 'COMP' : 'Admin') : 'Admin',
                    $label . ' — ' . $event['event_name'] . ' (' . $event['event_date'] . ') — ' . $data['level'],
                    $key . ':ticket:' . $id, $id)) {
                    throw new RuntimeException('Ticket log failed');
                }
            }
        }
        if ($wpdb->query('COMMIT') === false) {
            throw new RuntimeException('Commit failed');
        }
        return $member_id;
    } catch (Throwable $error) {
        $wpdb->query('ROLLBACK');
        return false;
    }
}

function mmgr_get_account_transactions($member_id = 0, $limit = 50, $offset = 0) {
    global $wpdb;
    $where = $member_id ? $wpdb->prepare(' WHERE a.member_id = %d', $member_id) : '';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT a.*, m.name, m.member_code FROM {$wpdb->prefix}membership_account_transactions a
         LEFT JOIN {$wpdb->prefix}memberships m ON m.id = a.member_id
         $where ORDER BY a.id DESC LIMIT %d OFFSET %d", $limit, $offset
    ), ARRAY_A);
}

function mmgr_account_revenue($date, $monthly = false) {
    global $wpdb;
    $start = $date . ($monthly ? '-01' : '');
    $end = (new DateTimeImmutable($start))->modify($monthly ? '+1 month' : '+1 day')->format('Y-m-d');
    return (float) $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(amount) FROM {$wpdb->prefix}membership_account_transactions
         WHERE created_at >= %s AND created_at < %s AND activity IN ('membership_sale', 'ticket_sale')",
        $start . ' 00:00:00', $end . ' 00:00:00'
    ));
}

function mmgr_account_csv_row($row) {
    $cells = array($row['id'], $row['created_at'], $row['member_id'], $row['name'] ?? '',
        $row['member_code'] ?? '', $row['activity'], number_format((float) $row['amount'], 2, '.', ''),
        $row['payment_method'], $row['event_id'] ?? '', $row['created_by'], $row['description']);
    return array_map(function($cell) {
        $cell = (string) $cell;
        // CSV quoting alone does not stop spreadsheet formula execution.
        return preg_match('/^[\x00-\x20]*[=+\-@]|^[\x00-\x1f]/', $cell) ? "'" . $cell : $cell;
    }, $cells);
}

function mmgr_render_account_transactions($rows) {
    echo '<table class="widefat striped"><thead><tr><th>Date / administrator ID</th><th>Account</th><th>Activity</th><th>Amount</th></tr></thead><tbody>';
    foreach ((array) $rows as $row) {
        echo '<tr><td>' . esc_html($row['created_at']) . ' / ' . (int) $row['created_by'] . '</td><td><a href="' .
            esc_url(admin_url('admin.php?page=membership_add&id=' . (int) $row['member_id'])) . '">' .
            esc_html(($row['name'] ?? '') . ' ' . ($row['member_code'] ?? '')) . '</a></td><td>' .
            esc_html($row['description']) . '</td><td>$' . esc_html(number_format((float) $row['amount'], 2)) . '</td></tr>';
    }
    if (!$rows) {
        echo '<tr><td colspan="4">No account transactions recorded.</td></tr>';
    }
    echo '</tbody></table>';
}
