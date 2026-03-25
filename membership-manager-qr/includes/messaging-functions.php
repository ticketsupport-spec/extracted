<?php
if (!defined('ABSPATH')) exit;

/**
 * Return a member's display name, preferring their community alias when set.
 */
function mmgr_get_display_name(array $member): string {
    return !empty($member['community_alias']) ? $member['community_alias'] : $member['name'];
}

/**
 * Send a message
 */
function mmgr_send_message($from_member_id, $to_member_id, $message, $image_url = null) {
    global $wpdb;
    $messages_table = $wpdb->prefix . 'membership_messages';
    $blocks_table = $wpdb->prefix . 'membership_blocks';
    $archive_table = $wpdb->prefix . 'membership_conversation_archive';
    
    // Check if sender is blocked by receiver (skip check if sending to admin)
    if ($to_member_id != 0) {
        $is_blocked = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $blocks_table WHERE member_id = %d AND blocked_member_id = %d",
            $to_member_id,
            $from_member_id
        ));
        
        if ($is_blocked > 0) {
            return array('success' => false, 'message' => 'You cannot send messages to this member.');
        }
    }
    
    // Insert message
    $result = $wpdb->insert($messages_table, array(
        'from_member_id' => $from_member_id,
        'to_member_id' => $to_member_id,
        'message' => $message,
        'image_url' => $image_url,
        'sent_at' => current_time('mysql')
    ));
    
    if ($result) {
        // Unarchive the conversation for both parties when a new message is sent
        if ($wpdb->get_var("SHOW TABLES LIKE '$archive_table'") === $archive_table) {
            // Unarchive for the recipient (their view of the sender's conversation)
            $wpdb->delete($archive_table, array(
                'member_id'       => $to_member_id,
                'other_member_id' => $from_member_id,
            ));
            // Unarchive for the sender (their own view) in case they had archived it
            $wpdb->delete($archive_table, array(
                'member_id'       => $from_member_id,
                'other_member_id' => $to_member_id,
            ));
        }

        // Send push notification to the recipient (skip admin id = 0)
        if ($to_member_id != 0 && function_exists('mmgr_pwa_send_push_to_member')) {
            if ($from_member_id == 0) {
                $sender_name = 'Admin / Support';
            } else {
                $sender = $wpdb->get_row($wpdb->prepare(
                    "SELECT name, community_alias FROM {$wpdb->prefix}memberships WHERE id = %d",
                    $from_member_id
                ), ARRAY_A);
                $sender_name = $sender ? mmgr_get_display_name($sender) : 'Member';
            }
            $body = !empty($message) ? wp_trim_words($message, 10) : '[Image attached]';
            mmgr_pwa_send_push_to_member(
                intval($to_member_id),
                'New message from ' . $sender_name,
                $body,
                home_url('/member-messages/?chat=' . intval($from_member_id))
            );
        }
        return array('success' => true, 'message' => 'Message sent!');
    }

    return array('success' => false, 'message' => 'Failed to send message.');
}

/**
 * Get conversation between two members with pagination
 */
function mmgr_get_conversation($member1_id, $member2_id, $limit = 50, $offset = 0) {
    global $wpdb;
    $messages_table = $wpdb->prefix . 'membership_messages';
    
    $messages = $wpdb->get_results($wpdb->prepare(
        "SELECT m.*
         FROM $messages_table m
         WHERE ((m.from_member_id = %d AND m.to_member_id = %d AND m.deleted_by_sender = 0)
            OR (m.from_member_id = %d AND m.to_member_id = %d AND m.deleted_by_receiver = 0))
         ORDER BY m.sent_at DESC
         LIMIT %d OFFSET %d",
        $member1_id, $member2_id,
        $member2_id, $member1_id,
        $limit,
        $offset
    ), ARRAY_A);
    
    // Add member names manually (to handle admin with id = 0)
    foreach ($messages as &$msg) {
        if ($msg['from_member_id'] == 0) {
            $msg['from_name'] = 'Admin / Support';
            $msg['from_photo'] = null;
        } else {
            $from = $wpdb->get_row($wpdb->prepare(
                "SELECT name, community_alias, photo_url FROM {$wpdb->prefix}memberships WHERE id = %d",
                $msg['from_member_id']
            ), ARRAY_A);
            $msg['from_name'] = $from ? mmgr_get_display_name($from) : 'Unknown';
            $msg['from_photo'] = $from ? $from['photo_url'] : null;
        }
        
        if ($msg['to_member_id'] == 0) {
            $msg['to_name'] = 'Admin / Support';
            $msg['to_photo'] = null;
        } else {
            $to = $wpdb->get_row($wpdb->prepare(
                "SELECT name, community_alias, photo_url FROM {$wpdb->prefix}memberships WHERE id = %d",
                $msg['to_member_id']
            ), ARRAY_A);
            $msg['to_name'] = $to ? mmgr_get_display_name($to) : 'Unknown';
            $msg['to_photo'] = $to ? $to['photo_url'] : null;
        }
    }
    
    return array_reverse($messages); // Return in chronological order
}

/**
 * Get total message count for a conversation
 */
function mmgr_get_conversation_count($member1_id, $member2_id) {
    global $wpdb;
    $messages_table = $wpdb->prefix . 'membership_messages';
    
    return $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $messages_table m
         WHERE ((m.from_member_id = %d AND m.to_member_id = %d AND m.deleted_by_sender = 0)
            OR (m.from_member_id = %d AND m.to_member_id = %d AND m.deleted_by_receiver = 0))",
        $member1_id, $member2_id,
        $member2_id, $member1_id
    ));
}

/**
 * Get member's conversations list
 */
function mmgr_get_conversations_list($member_id, $archived = false) {
    global $wpdb;
    $messages_table = $wpdb->prefix . 'membership_messages';
    $members_table = $wpdb->prefix . 'memberships';
    $archive_table = $wpdb->prefix . 'membership_conversation_archive';

    $archive_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$archive_table'") === $archive_table;

    // Pre-fetch all archived conversation partner IDs in a single query
    $archived_ids = array();
    if ($archive_table_exists) {
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT other_member_id FROM $archive_table WHERE member_id = %d",
            $member_id
        ));
        $archived_ids = array_map('intval', $rows);
    }

    // If asking for archived conversations but the table doesn't exist, return empty
    if ($archived && !$archive_table_exists) {
        return array();
    }

    // Get unique conversations with latest message
    $conversations = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            CASE 
                WHEN m.from_member_id = %d THEN m.to_member_id 
                ELSE m.from_member_id 
            END as other_member_id,
            MAX(m.sent_at) as last_message_time,
            SUM(CASE WHEN m.to_member_id = %d AND m.read_at IS NULL THEN 1 ELSE 0 END) as unread_count
         FROM $messages_table m
         WHERE (m.from_member_id = %d OR m.to_member_id = %d)
           AND ((m.from_member_id = %d AND m.deleted_by_sender = 0) OR (m.to_member_id = %d AND m.deleted_by_receiver = 0))
         GROUP BY other_member_id
         ORDER BY last_message_time DESC",
        $member_id, $member_id,
        $member_id, $member_id,
        $member_id, $member_id
    ), ARRAY_A);
    
    // Get member details for each conversation
    $result = array();
    foreach ($conversations as $conv) {
        $other_id = intval($conv['other_member_id']);

        // Filter by archive status using the pre-fetched set (no extra query per row)
        $is_archived = in_array($other_id, $archived_ids, true);
        if ($archived && !$is_archived) {
            continue; // Only want archived – skip non-archived
        }
        if (!$archived && $is_archived) {
            continue; // Only want active – skip archived
        }

        // Handle admin conversation (member_id = 0)
        if ($other_id == 0) {
            $other_member = array(
                'id' => 0,
                'name' => 'Admin / Support',
                'photo_url' => null
            );
        } else {
            $other_member = $wpdb->get_row($wpdb->prepare(
                "SELECT id, name, community_alias, photo_url FROM $members_table WHERE id = %d",
                $other_id
            ), ARRAY_A);

            // If the member no longer exists, use a placeholder so unread
            // messages from that conversation can still be marked as read.
            if (!$other_member) {
                $other_member = array(
                    'id'             => $other_id,
                    'name'           => 'Deleted Member',
                    'community_alias' => '',
                    'photo_url'      => null
                );
            }
        }
        
        $result[] = array(
            'member' => $other_member,
            'last_message_time' => $conv['last_message_time'],
            'unread_count' => $conv['unread_count']
        );
    }
    
    return $result;
}

/**
 * Mark messages as read
 */
function mmgr_mark_messages_read($from_member_id, $to_member_id) {
    global $wpdb;
    $messages_table = $wpdb->prefix . 'membership_messages';
    
    $wpdb->query($wpdb->prepare(
        "UPDATE $messages_table SET read_at = %s
         WHERE from_member_id = %d AND to_member_id = %d AND read_at IS NULL",
        current_time('mysql'),
        $from_member_id,
        $to_member_id
    ));
}

/**
 * Delete image from message (soft delete)
 */
function mmgr_delete_message_image($message_id, $member_id) {
    global $wpdb;
    $messages_table = $wpdb->prefix . 'membership_messages';
    
    // Verify member owns the message
    $message = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $messages_table WHERE id = %d AND from_member_id = %d",
        $message_id,
        $member_id
    ), ARRAY_A);
    
    if ($message && !empty($message['image_url'])) {
        // Soft delete - mark as deleted but keep file
        $wpdb->update(
            $messages_table,
            array('image_deleted' => 1),
            array('id' => $message_id)
        );
        
        return array('success' => true, 'message' => 'Image hidden from conversation.');
    }
    
    return array('success' => false, 'message' => 'Image not found or you don\'t have permission.');
}

/**
 * Delete conversation (soft delete)
 */
function mmgr_delete_conversation($member_id, $other_member_id) {
    global $wpdb;
    $messages_table = $wpdb->prefix . 'membership_messages';
    
    // Mark as deleted for this member only
    $wpdb->query($wpdb->prepare(
        "UPDATE $messages_table 
         SET deleted_by_sender = CASE WHEN from_member_id = %d THEN 1 ELSE deleted_by_sender END,
             deleted_by_receiver = CASE WHEN to_member_id = %d THEN 1 ELSE deleted_by_receiver END
         WHERE (from_member_id = %d AND to_member_id = %d) 
            OR (from_member_id = %d AND to_member_id = %d)",
        $member_id, $member_id,
        $member_id, $other_member_id,
        $other_member_id, $member_id
    ));
    
    return array('success' => true, 'message' => 'Conversation deleted.');
}

/**
 * Add contact
 */
function mmgr_add_contact($member_id, $contact_member_id) {
    global $wpdb;
    $contacts_table = $wpdb->prefix . 'membership_contacts';
    
    $result = $wpdb->insert($contacts_table, array(
        'member_id' => $member_id,
        'contact_member_id' => $contact_member_id,
        'added_at' => current_time('mysql')
    ));
    
    if ($result) {
        return array('success' => true, 'message' => 'Contact added!');
    }
    
    return array('success' => false, 'message' => 'Failed to add contact.');
}

/**
 * Remove contact
 */
function mmgr_remove_contact($member_id, $contact_member_id) {
    global $wpdb;
    $contacts_table = $wpdb->prefix . 'membership_contacts';
    
    $wpdb->delete($contacts_table, array(
        'member_id' => $member_id,
        'contact_member_id' => $contact_member_id
    ));
    
    return array('success' => true, 'message' => 'Contact removed.');
}

/**
 * Get member's contacts
 */
function mmgr_get_contacts($member_id) {
    global $wpdb;
    $contacts_table = $wpdb->prefix . 'membership_contacts';
    $members_table = $wpdb->prefix . 'memberships';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT m.id, m.name, m.community_alias, m.photo_url, c.added_at
         FROM $contacts_table c
         LEFT JOIN $members_table m ON c.contact_member_id = m.id
         WHERE c.member_id = %d
         ORDER BY m.name ASC",
        $member_id
    ), ARRAY_A);
}

/**
 * Block member
 */
function mmgr_block_member($member_id, $blocked_member_id) {
    global $wpdb;
    $blocks_table = $wpdb->prefix . 'membership_blocks';
    
    // Don't allow blocking admin (member_id = 0 or special admin ID)
    if ($blocked_member_id == 0) {
        return array('success' => false, 'message' => 'You cannot block the admin.');
    }
    
    $result = $wpdb->insert($blocks_table, array(
        'member_id' => $member_id,
        'blocked_member_id' => $blocked_member_id,
        'blocked_at' => current_time('mysql')
    ));
    
    if ($result) {
        return array('success' => true, 'message' => 'Member blocked.');
    }
    
    return array('success' => false, 'message' => 'Failed to block member.');
}

/**
 * Unblock member
 */
function mmgr_unblock_member($member_id, $blocked_member_id) {
    global $wpdb;
    $blocks_table = $wpdb->prefix . 'membership_blocks';
    
    $wpdb->delete($blocks_table, array(
        'member_id' => $member_id,
        'blocked_member_id' => $blocked_member_id
    ));
    
    return array('success' => true, 'message' => 'Member unblocked.');
}

/**
 * Get blocked members
 */
function mmgr_get_blocked_members($member_id) {
    global $wpdb;
    $blocks_table = $wpdb->prefix . 'membership_blocks';
    $members_table = $wpdb->prefix . 'memberships';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT m.id, m.name, m.photo_url, b.blocked_at
         FROM $blocks_table b
         LEFT JOIN $members_table m ON b.blocked_member_id = m.id
         WHERE b.member_id = %d
         ORDER BY b.blocked_at DESC",
        $member_id
    ), ARRAY_A);
}

/**
 * Archive a conversation for a member
 */
function mmgr_archive_conversation($member_id, $other_member_id) {
    global $wpdb;
    $archive_table = $wpdb->prefix . 'membership_conversation_archive';

    $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO $archive_table (member_id, other_member_id, archived_at) VALUES (%d, %d, %s)",
        $member_id,
        $other_member_id,
        current_time('mysql')
    ));

    return array('success' => true, 'message' => 'Conversation archived.');
}

/**
 * Unarchive a conversation for a member
 */
function mmgr_unarchive_conversation($member_id, $other_member_id) {
    global $wpdb;
    $archive_table = $wpdb->prefix . 'membership_conversation_archive';

    $wpdb->delete($archive_table, array(
        'member_id'       => $member_id,
        'other_member_id' => $other_member_id,
    ));

    return array('success' => true, 'message' => 'Conversation unarchived.');
}

/**
 * Check if a conversation is archived by a member
 */
function mmgr_is_conversation_archived($member_id, $other_member_id) {
    global $wpdb;
    $archive_table = $wpdb->prefix . 'membership_conversation_archive';

    if ($wpdb->get_var("SHOW TABLES LIKE '$archive_table'") !== $archive_table) {
        return false;
    }

    return (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $archive_table WHERE member_id = %d AND other_member_id = %d",
        $member_id,
        $other_member_id
    ));
}

/**
 * Report message
 */
function mmgr_report_message($message_id, $reason) {
    global $wpdb;
    $reports_table = $wpdb->prefix . 'membership_message_reports';
    
    $member = mmgr_get_current_member();
    if (!$member) {
        return array('success' => false, 'message' => 'Not logged in.');
    }
    
    $result = $wpdb->insert($reports_table, array(
        'message_id' => $message_id,
        'reported_by' => $member['id'],
        'reason' => $reason,
        'reported_at' => current_time('mysql'),
        'status' => 'pending'
    ));
    
    if ($result) {
        return array('success' => true, 'message' => 'Message reported to admin.');
    }
    
    return array('success' => false, 'message' => 'Failed to report message.');
}

/**
 * Check if member is blocked
 */
function mmgr_is_blocked($member_id, $other_member_id) {
    global $wpdb;
    $blocks_table = $wpdb->prefix . 'membership_blocks';
    
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $blocks_table 
         WHERE (member_id = %d AND blocked_member_id = %d)
            OR (member_id = %d AND blocked_member_id = %d)",
        $member_id, $other_member_id,
        $other_member_id, $member_id
    ));
    
    return $count > 0;
}

/**
 * Send welcome private message to new member
 */
function mmgr_send_welcome_pm($member_id) {
    // Check if welcome PM is enabled
    if (!get_option('mmgr_welcome_pm_enabled', 1)) {
        return false;
    }
    
    global $wpdb;
    $members_table = $wpdb->prefix . 'memberships';
    $messages_table = $wpdb->prefix . 'membership_messages';
    
    // Get member details
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $members_table WHERE id = %d",
        $member_id
    ), ARRAY_A);
    
    if (!$member) {
        return false;
    }
    
    // Get welcome message template
    $message_template = get_option('mmgr_welcome_pm_message', mmgr_get_default_welcome_pm());
    
    // Replace placeholders
    $message = str_replace(
        array('{member_name}', '{first_name}', '{last_name}', '{membership_type}', '{site_name}'),
        array(
            $member['name'],
            $member['first_name'],
            $member['last_name'],
            $member['level'],
            get_bloginfo('name')
        ),
        $message_template
    );
    
    // Send message from admin (member_id = 0)
    $result = $wpdb->insert($messages_table, array(
        'from_member_id' => 0, // Admin
        'to_member_id' => $member_id,
        'message' => $message,
        'sent_at' => current_time('mysql')
    ));
    
    return $result !== false;
}