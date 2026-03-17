<?php
if (!defined('ABSPATH')) exit;

add_action('wp_head', function() {
    ?>
    <style>
    /* ============================================
       GLOBAL & UTILITY STYLES
       ============================================ */
    
    /* Hide theme navigation */
    body > header,
    body #masthead,
    #site-navigation,
    .site-header,
    .main-navigation {
        display: none !important;
    }

    /* ============================================
       PORTAL THEME COLORS & VARIABLES
       ============================================ */
    :root {
        --portal-primary: #9b51e0;
        --portal-primary-dark: #7d3cb8;
        --portal-secondary: #ce00ff;
        --portal-accent: #FF2197;
        --portal-accent-light: #FF1177;
        --portal-blue: #0073aa;
        --portal-blue-dark: #005a87;
        --portal-success: #28a745;
        --portal-error: #dc3545;
        --portal-border: #e0e0e0;
        --portal-light-bg: #f9f9f9;
    }

    /* ============================================
       PORTAL NAVIGATION
       ============================================ */
    .mmgr-portal-nav-wrapper {
        background: linear-gradient(135deg, var(--portal-primary), var(--portal-secondary));
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .mmgr-nav-toggle-btn {
        display: none;
        padding: 15px 20px;
        background: rgba(0,0,0,0.2);
        color: white;
        border: none;
        width: 100%;
        text-align: left;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
    }
    
    .mmgr-nav-items-container {
        display: flex;
        flex-wrap: wrap;
        gap: 0;
        justify-content: center;
    }
    
    .mmgr-nav-items-container a {
        padding: 11px 10px;
        text-align: center;
        color: white;
        text-decoration: none;
        font-weight: bold;
        font-size: 14px;
        transition: all 0.3s;
        border-bottom: 3px solid transparent;
        background: rgba(0,0,0,0.1);
        white-space: nowrap;
    }
    
    .mmgr-nav-items-container a:hover {
        background: rgba(0,0,0,0.3);
        border-bottom-color: white;
    }
    
    .mmgr-nav-items-container a.active {
        background: rgba(255,255,255,0.2);
        border-bottom-color: white;
    }
    
    .mmgr-nav-items-container a.logout {
        background: rgba(214,54,56,0.8);
    }
    
    .mmgr-nav-items-container a.logout:hover {
        background: rgba(214,54,56,1);
    }
    
    @media (max-width: 600px) {
        .mmgr-nav-toggle-btn {
            display: flex !important;
            justify-content: space-between;
            align-items: center;
        }
        
        .mmgr-nav-items-container {
            display: none;
            flex-direction: column;
            justify-content: flex-start;
        }
        
        .mmgr-nav-items-container.active {
            display: flex !important;
        }
        
        .mmgr-nav-items-container a {
            width: 100%;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            font-size: 16px;
            padding: 15px 20px;
        }
    }

    /* ============================================
       NAV STATS BAR (desktop: above menu bar)
       ============================================ */
    .mmgr-nav-stats-bar {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 6px 16px;
        background: rgba(0,0,0,0.35);
        flex-wrap: wrap;
    }

    .mmgr-nav-stat-item {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: #fff;
        font-size: 13px;
        font-weight: 600;
        white-space: nowrap;
    }

    .mmgr-nav-stat-icon {
        font-size: 16px;
        line-height: 1;
    }

    .mmgr-nav-stat-count {
        background: rgba(255,255,255,0.15);
        border-radius: 10px;
        padding: 1px 7px;
        font-size: 12px;
        font-weight: bold;
        min-width: 20px;
        text-align: center;
        line-height: 1.6;
    }

    .mmgr-nav-stat-awards {
        display: inline-flex;
        align-items: center;
        gap: 2px;
        margin-left: 4px;
    }

    /* Help icon in the stats bar */
    .mmgr-nav-help-icon {
        margin-left: auto;
        color: rgba(255,255,255,0.85);
        text-decoration: none;
        font-size: 18px;
        line-height: 1;
        padding: 2px 6px;
        border-radius: 50%;
        transition: background 0.2s, color 0.2s;
        flex-shrink: 0;
    }
    .mmgr-nav-help-icon:hover,
    .mmgr-nav-help-icon.active {
        color: #fff;
        background: rgba(255,255,255,0.25);
    }

    /* On mobile the stats bar is hidden; stats appear in the toggle button row instead */
    .mmgr-nav-toggle-stats {
        display: none;
    }

    @media (max-width: 600px) {
        .mmgr-nav-stats-bar {
            display: none !important;
        }

        .mmgr-nav-toggle-stats {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Make count chips slightly smaller in the cramped button bar */
        .mmgr-nav-toggle-stats .mmgr-nav-stat-count {
            font-size: 11px;
            padding: 1px 5px;
        }

        .mmgr-nav-toggle-label {
            flex-shrink: 0;
        }
    }

    /* ============================================
       UNREAD MESSAGE BADGE
       ============================================ */
    .mmgr-nav-unread-badge {
        display: inline-block;
        background: #FF2197;
        color: #fff;
        border-radius: 10px;
        padding: 1px 7px;
        font-size: 12px;
        font-weight: bold;
        min-width: 20px;
        text-align: center;
        margin-left: 4px;
        vertical-align: middle;
        line-height: 1.6;
    }



    /* ============================================
       PORTAL TITLES CHRIS
       ============================================ */
    .mmgr-portal-titlecc {
		background: linear-gradient(44deg, rgb(85 0 251 / 30%), rgb(229 39 179 / 30%));
		padding: 10px 10px;
		border-radius: 12px;
		margin-bottom: 10px;
		border-left: 6px solid #9b51e0;		
    }
    
    .mmgr-portal-titlecc h1 {
        color: var(--portal-primary);
        font-size: 32px;
        font-weight: bold;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .mmgr-portal-titlecc p {
        color: var(--portal-primary);
        font-size: 15px;
        opacity: 0.8;
        margin: 0;
    }
	



    /* ============================================
       PORTAL WELCOME/TITLES
       ============================================ */
    .mmgr-portal-welcome {
        margin-bottom: 30px;
    }
    
    .mmgr-portal-welcome h1 {
        color: var(--portal-primary);
        font-size: 32px;
        font-weight: bold;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .mmgr-portal-welcome p {
        color: var(--portal-primary);
        font-size: 15px;
        opacity: 0.8;
        margin: 0;
    }

    /* ============================================
       REGISTRATION FORM
       ============================================ */
    .mmgr-registration-form {
        max-width: 600px;
        margin: 20px auto;
        background: #000;
        border-radius: 12px;
        padding: 0;
        box-shadow: 0 4px 20px rgba(255,33,151,0.3);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        overflow: hidden;
        border: 2px solid #FF2197;
    }

    .mmgr-registration-form > .mmgr-registration-logo,
    .mmgr-registration-form > .mmgr-registration-blurb,
    .mmgr-registration-form > h2,
    .mmgr-registration-form > form {
        padding-left: 30px;
        padding-right: 30px;
    }

    .mmgr-registration-form > .mmgr-registration-logo {
        padding-top: 30px;
    }

    .mmgr-registration-form > form {
        padding-bottom: 30px;
    }

    .mmgr-registration-form h2 {
        margin-top: 0;
        padding-top: 24px;
        color: #FF2197;
        border-bottom: 2px solid #FF2197;
        padding-bottom: 12px;
        margin-bottom: 24px;
        font-size: 22px;
    }

    .mmgr-registration-form h3 {
        color: #FF2197;
        font-size: 16px;
        margin-bottom: 15px;
    }

    .mmgr-registration-form .mmgr-field label {
        color: #fff;
    }

    .mmgr-registration-form .mmgr-field input[type="text"],
    .mmgr-registration-form .mmgr-field input[type="email"],
    .mmgr-registration-form .mmgr-field input[type="tel"],
    .mmgr-registration-form .mmgr-field input[type="date"],
    .mmgr-registration-form .mmgr-field select,
    .mmgr-registration-form .mmgr-field textarea {
        background: #1a1a1a;
        color: #fff;
        border-color: #FF2197;
    }

    .mmgr-registration-form .mmgr-field input:focus,
    .mmgr-registration-form .mmgr-field select:focus,
    .mmgr-registration-form .mmgr-field textarea:focus {
        border-color: #FF2197;
        box-shadow: 0 0 0 3px rgba(255, 33, 151, 0.25);
    }

    .mmgr-registration-form .mmgr-field select option {
        background: #1a1a1a;
        color: #fff;
    }

    .mmgr-registration-form #partner_fields {
        border-top-color: #FF2197;
    }

    .mmgr-registration-form .mmgr-submit {
        background: linear-gradient(135deg, #FF2197 0%, #ce00ff 100%);
        box-shadow: 0 2px 8px rgba(255, 33, 151, 0.4);
        color: #fff;
        max-width: none;
        width: 100%;
        padding: 14px;
        font-size: 16px;
        border-radius: 6px;
    }

    .mmgr-registration-form .mmgr-submit:hover {
        box-shadow: 0 4px 14px rgba(255, 33, 151, 0.6);
    }

    .mmgr-registration-form .mmgr-coc ul li {
        color: #fff;
    }

    .mmgr-registration-blurb {
        color: #ccc;
    }

    /* ============================================
       FORM FIELDS
       ============================================ */
    .mmgr-field {
        margin-bottom: 20px;
    }
    
    .mmgr-field label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: #333;
        font-size: 14px;
    }
    
    .mmgr-field input[type="text"],
    .mmgr-field input[type="email"],
    .mmgr-field input[type="tel"],
    .mmgr-field input[type="date"],
    .mmgr-field select,
    .mmgr-field textarea {
        width: 100%;
        padding: 10px 12px;
        border: 2px solid var(--portal-border);
        border-radius: 6px;
        font-size: 14px;
        transition: all 0.3s;
        box-sizing: border-box;
    }
    
    .mmgr-field input:focus,
    .mmgr-field select:focus,
    .mmgr-field textarea:focus {
        border-color: var(--portal-blue);
        outline: none;
        box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.1);
    }

    /* ============================================
       BUTTONS
       ============================================ */
    .mmgr-submit,
    .mmgr-button,
    .mmgr-btn-primary {
        background: linear-gradient(135deg, var(--portal-blue) 0%, var(--portal-blue-dark) 100%);
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 2px 8px rgba(0, 115, 170, 0.3);
        display: inline-block;
        text-decoration: none;
        text-align: center;
        min-width: auto;
        max-width: 200px;
    }
    
    .mmgr-submit:hover,
    .mmgr-button:hover,
    .mmgr-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 115, 170, 0.4);
    }
    
    .mmgr-submit:active,
    .mmgr-button:active {
        transform: translateY(0);
    }

    /* ============================================
       MESSAGES & ALERTS
       ============================================ */
    .mmgr-success {
        background: #d4edda;
        border-left: 4px solid var(--portal-success);
        color: #155724;
        padding: 12px 15px;
        border-radius: 6px;
        margin: 15px 0;
        font-weight: 600;
        font-size: 14px;
    }
    
    .mmgr-error {
        background: #f8d7da;
        border-left: 4px solid var(--portal-error);
        color: #721c24;
        padding: 12px 15px;
        border-radius: 6px;
        margin: 15px 0;
        font-weight: 600;
        font-size: 14px;
    }

    .mmgr-load-more-btn {
        text-align: center;
        padding: 15px;
        background: var(--portal-light-bg);
        border: 1px solid var(--portal-border);
        border-radius: 6px;
        cursor: pointer;
        margin-bottom: 15px;
        transition: all 0.3s;
    }

    .mmgr-load-more-btn:hover {
        background: #e0e0e0;
    }

    /* ============================================
       MEMBERS DIRECTORY TABLE
       ============================================ */
    .mmgr-directory-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border: 2px solid var(--portal-primary);
    }
    
    .mmgr-directory-table thead {
        background: linear-gradient(135deg, var(--portal-primary), var(--portal-secondary));
        color: white;
    }
    
    .mmgr-directory-table thead th {
        padding: 18px 20px;
        text-align: left;
        font-weight: bold;
        font-size: 16px;
        letter-spacing: 0.5px;
        border-right: 1px solid rgba(255,255,255,0.2);
    }
    
    .mmgr-directory-table thead th:last-child {
        border-right: none;
    }
    
    .mmgr-directory-table tbody tr {
        border-bottom: 1px solid var(--portal-border);
        transition: all 0.3s;
    }
    
    .mmgr-directory-table tbody tr:last-child {
        border-bottom: none;
    }
    
    .mmgr-directory-table tbody tr:hover {
        background: #f9f4ff;
    }
    
    .mmgr-directory-table tbody td {
        padding: 20px;
        vertical-align: middle;
    }
    
    .mmgr-directory-photo {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid var(--portal-primary);
        cursor: pointer;
        transition: transform 0.3s, box-shadow 0.3s;
    }
    
    .mmgr-directory-photo:hover {
        transform: scale(1.1);
        box-shadow: 0 4px 12px rgba(155, 81, 224, 0.4);
    }
    
    .mmgr-directory-alias {
        font-size: 18px;
        font-weight: bold;
        color: var(--portal-primary);
        cursor: pointer;
        transition: color 0.3s;
    }
    
    .mmgr-directory-alias:hover {
        color: var(--portal-secondary);
    }
    
    .mmgr-directory-actions {
        display: flex;
        gap: 8px;
        margin-top: 8px;
        flex-wrap: wrap;
    }
    
    .mmgr-directory-btn {
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        font-size: 14px;
        transition: all 0.3s;
        white-space: nowrap;
    }
    
    .mmgr-directory-btn-message {
        background: linear-gradient(135deg, var(--portal-accent), var(--portal-accent-light));
        color: white;
        box-shadow: 0 2px 8px rgba(255, 33, 151, 0.3);
    }
    
    .mmgr-directory-btn-message:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255, 33, 151, 0.5);
    }
    
    .mmgr-directory-btn-like {
        border: 2px solid var(--portal-accent);
        color: var(--portal-accent);
        background: white;
        box-shadow: 0 1px 4px rgba(255, 33, 151, 0.2);
    }
    
    .mmgr-directory-btn-like:hover {
        transform: translateY(-2px);
        box-shadow: 0 2px 8px rgba(255, 33, 151, 0.3);
    }
    
    .mmgr-directory-btn-like.liked {
        background: var(--portal-accent);
        color: white;
        box-shadow: 0 2px 8px rgba(255, 33, 151, 0.4);
    }

    .mmgr-directory-btn-friend {
        border: 2px solid #0073aa;
        color: #0073aa;
        background: white;
        box-shadow: 0 1px 4px rgba(0, 115, 170, 0.2);
    }

    .mmgr-directory-btn-friend:hover {
        transform: translateY(-2px);
        box-shadow: 0 2px 8px rgba(0, 115, 170, 0.3);
    }

    .mmgr-directory-btn-friend.accepted {
        background: #28a745;
        border-color: #28a745;
        color: white;
    }

    .mmgr-directory-btn-friend.pending {
        background: #aaa;
        border-color: #aaa;
        color: white;
    }

    .mmgr-directory-btn-friend.incoming {
        background: #00a32a;
        border-color: #00a32a;
        color: white;
    }
    
    .mmgr-directory-count {
        text-align: center;
        color: #666;
        font-size: 13px;
        margin-top: 20px;
        font-weight: 500;
    }

    /* ============================================
       MESSAGES PAGE
       ============================================ */
    .mmgr-messages-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .mmgr-messages-grid {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 20px;
        height: calc(100vh - 200px);
        min-height: 600px;
    }
    
    .mmgr-sidebar,
    .mmgr-chat-area {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border: 2px solid var(--portal-primary);
    }
    
    .mmgr-sidebar {
        overflow-y: auto;
    }
    
    .mmgr-chat-area {
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    
    .mmgr-conversation-item,
    .mmgr-admin-conv-item {
        padding: 15px;
        border-bottom: 1px solid var(--portal-border);
        cursor: pointer;
        transition: background 0.2s;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .mmgr-conversation-item:hover,
    .mmgr-admin-conv-item:hover {
        background: var(--portal-light-bg);
    }
    
    .mmgr-conversation-item.active,
    .mmgr-admin-conv-item.active {
        background: #f0e6ff;
        border-left: 4px solid var(--portal-primary);
    }
    
    .mmgr-conversation-avatar,
    .mmgr-admin-avatar,
    .mmgr-avatar-placeholder,
    .mmgr-admin-avatar-placeholder {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }
    
    .mmgr-conversation-avatar,
    .mmgr-admin-avatar {
        border: 2px solid var(--portal-primary);
    }
    
    .mmgr-avatar-placeholder,
    .mmgr-admin-avatar-placeholder {
        background: var(--portal-light-bg);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }
    
    .mmgr-unread-badge,
    .mmgr-admin-unread-badge {
        background: var(--portal-accent);
        color: white;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: bold;
        margin-left: auto;
    }
    
    .mmgr-chat-header,
    .mmgr-admin-chat-header {
        padding: 20px;
        color: white;
        border-bottom: 2px solid #7c3aed;
        display: flex;
        align-items: center;
        gap: 15px;
        flex-shrink: 0;
    }
    
    .mmgr-chat-header {
        background: linear-gradient(135deg, var(--portal-primary), var(--portal-secondary));
    }

    .mmgr-admin-chat-header {
        background: #2271b1;
        border-bottom: 1px solid #135e96;
    }
    
    .mmgr-chat-messages,
    .mmgr-admin-messages-list {
        flex: 1;
        max-height: 350px;
        overflow-y: auto;
        padding: 20px;
        background: var(--portal-light-bg);
    }
    
    .mmgr-message-bubble,
    .mmgr-admin-message-bubble {
        margin-bottom: 15px;
        display: flex;
        gap: 10px;
    }
    
    .mmgr-message-bubble.sent,
    .mmgr-admin-message-bubble.sent {
        flex-direction: row-reverse;
    }
    
    .mmgr-message-content,
    .mmgr-admin-message-content {
        max-width: 60%;
        background: white;
        padding: 12px 16px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .mmgr-message-bubble.sent .mmgr-message-content,
    .mmgr-admin-message-bubble.sent .mmgr-admin-message-content {
        background: var(--portal-primary);
        color: white;
    }
    
    .mmgr-admin-message-bubble.sent .mmgr-admin-message-content {
        background: #2271b1;
    }
    
    .mmgr-message-time,
    .mmgr-admin-message-time {
        font-size: 11px;
        color: #666;
        margin-top: 5px;
    }
    
    .mmgr-message-bubble.sent .mmgr-message-time,
    .mmgr-admin-message-bubble.sent .mmgr-admin-message-time {
        color: rgba(255,255,255,0.7);
    }
    
    .mmgr-message-image,
    .mmgr-admin-message-image {
        max-width: 100%;
        border-radius: 8px;
        margin-top: 8px;
        cursor: pointer;
    }
    
    .mmgr-chat-input,
    .mmgr-admin-input-area {
        padding: 20px;
        background: white;
        border-top: 2px solid var(--portal-border);
        flex-shrink: 0;
    }
    
    .mmgr-input-wrapper,
    .mmgr-admin-input-wrapper {
        display: flex;
        gap: 10px;
        align-items: flex-end;
    }
    
    .mmgr-message-textarea,
    .mmgr-admin-textarea {
        flex: 1;
        padding: 12px;
        border: 2px solid var(--portal-border);
        border-radius: 8px;
        resize: none;
        font-family: inherit;
        font-size: 15px;
    }
    
    .mmgr-admin-textarea {
        border: 1px solid #8c8f94;
        border-radius: 4px;
        font-size: 14px;
    }
    
    .mmgr-send-btn {
        background: var(--portal-primary);
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: bold;
        transition: all 0.3s;
    }
    
    .mmgr-send-btn:hover {
        background: var(--portal-secondary);
        transform: translateY(-2px);
    }
    
    .mmgr-tabs,
    .mmgr-admin-tabs {
        display: flex;
        background: var(--portal-light-bg);
        border-bottom: 2px solid var(--portal-border);
    }
    
    .mmgr-tab,
    .mmgr-admin-tab {
        flex: 1;
        padding: 15px;
        text-align: center;
        cursor: pointer;
        font-weight: bold;
        border: none;
        background: none;
        transition: all 0.3s;
    }
    
    .mmgr-tab.active,
    .mmgr-admin-tab.active {
        background: white;
        color: var(--portal-primary);
        border-bottom: 3px solid var(--portal-primary);
    }
    
    .mmgr-empty-state,
    .mmgr-admin-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #999;
        padding: 40px;
        text-align: center;
    }

    /* ============================================
       RESPONSIVE DESIGN
       ============================================ */
    @media (max-width: 768px) {
        .mmgr-messages-grid {
            grid-template-columns: 1fr;
            height: auto;
        }
        
        .mmgr-sidebar {
            max-height: 300px;
        }
        
        .mmgr-chat-area,
        .mmgr-admin-chat-area {
            min-height: 500px;
        }
    }

    
    /* ============================================
       LOGIN PAGE
       ============================================ */
    .mmgr-login-wrapper {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #000 0%, #1a1a1a 50%, #000 100%);
        padding: 20px;
        margin: -20px -20px -20px -20px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    
    .mmgr-login-container {
        width: 100%;
        max-width: 450px;
        background: white;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(255, 33, 151, 0.3);
        overflow: hidden;
        border: 3px solid var(--portal-accent);
    }
    
    .mmgr-login-header {
        background: linear-gradient(135deg, #000 0%, var(--portal-accent) 100%);
        padding: 40px 30px;
        text-align: center;
        color: white;
    }
    
    .mmgr-login-header h1 {
        margin: 0;
        font-size: 32px;
        font-weight: bold;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    }
    
    .mmgr-login-header p {
        margin: 10px 0 0 0;
        opacity: 0.9;
        font-size: 16px;
    }
    
    .mmgr-login-body {
        padding: 40px 30px;
    }
    
    .mmgr-login-field {
        margin-bottom: 25px;
    }
    
    .mmgr-login-field label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: #000;
        font-size: 15px;
    }
    
    .mmgr-login-field input {
        width: 100%;
        padding: 14px 16px;
        border: 2px solid var(--portal-border);
        border-radius: 8px;
        font-size: 16px;
        transition: all 0.3s;
        box-sizing: border-box;
        background: var(--portal-light-bg);
    }
    
    .mmgr-login-field input:focus {
        border-color: var(--portal-accent);
        outline: none;
        background: white;
        box-shadow: 0 0 0 3px rgba(255, 33, 151, 0.1);
    }
    
    .mmgr-login-btn {
        width: 100%;
        padding: 16px;
        background: linear-gradient(135deg, var(--portal-accent) 0%, var(--portal-accent-light) 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 18px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s;
        text-transform: uppercase;
        letter-spacing: 1px;
        box-shadow: 0 4px 15px rgba(255, 33, 151, 0.4);
    }
    
    .mmgr-login-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(255, 33, 151, 0.6);
    }
    
    .mmgr-login-btn:active {
        transform: translateY(0);
    }
    
    .mmgr-login-error {
        background: #ffe2e2;
        border-left: 4px solid #d00;
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 20px;
        color: #d00;
        font-weight: 500;
    }
    
    .mmgr-login-help {
        text-align: center;
        margin-top: 20px;
        font-size: 13px;
        color: #999;
    }
    
    .mmgr-login-signup {
        text-align: center;
        padding: 25px 30px;
        background: linear-gradient(135deg, var(--portal-light-bg) 0%, #ffffff 100%);
        border-top: 2px solid var(--portal-border);
    }
    
    .mmgr-login-signup p {
        margin: 0 0 15px 0;
        color: #666;
        font-size: 15px;
    }
    
    .mmgr-signup-btn {
        display: inline-block;
        padding: 12px 30px;
        background: #000;
        color: var(--portal-accent);
        text-decoration: none;
        border-radius: 8px;
        font-weight: bold;
        border: 2px solid var(--portal-accent);
        transition: all 0.3s;
        font-size: 16px;
        cursor: pointer;
    }
    
    .mmgr-signup-btn:hover {
        background: var(--portal-accent);
        color: #000;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255, 33, 151, 0.3);
    }
    
    @media (max-width: 768px) {
        .mmgr-login-wrapper {
            padding: 10px;
        }
        
        .mmgr-login-header {
            padding: 30px 20px;
        }
        
        .mmgr-login-header h1 {
            font-size: 26px;
        }
        
        .mmgr-login-body {
            padding: 30px 20px;
        }
    }	

    /* ============================================
       PORTAL LAYOUT
       ============================================ */
    .mmgr-portal-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    .mmgr-portal-card {
        background: white;
        border-radius: 8px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }

    .mmgr-portal-card h2 {
        margin-top: 0;
        color: #0073aa;
        border-bottom: 2px solid #e0e0e0;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    .mmgr-portal-card h3 {
        margin-top: 0;
        color: #333;
        font-size: 18px;
        margin-bottom: 15px;
    }

    .mmgr-portal-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }

    @media (max-width: 768px) {
        .mmgr-portal-grid {
            grid-template-columns: 1fr;
        }
    }

    /* ============================================
       BUTTONS
       ============================================ */
    .mmgr-btn-secondary {
        padding: 12px 24px;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
        background: #f0f0f0;
        color: #333;
    }

    .mmgr-btn-secondary:hover {
        background: #e0e0e0;
    }

    /* ============================================
       INFO TABLE
       ============================================ */
    .mmgr-info-table {
        width: 100%;
        border-collapse: collapse;
    }

    .mmgr-info-table tr {
        border-bottom: 1px solid #f0f0f0;
    }

    .mmgr-info-table td {
        padding: 12px 8px;
    }

    .mmgr-info-table td:first-child {
        width: 40%;
        color: #666;
    }

    .mmgr-info-table td:last-child {
        font-weight: 500;
    }

    /* ============================================
       STATUS BADGE
       ============================================ */
    .mmgr-status-badge {
        display: inline-block;
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 14px;
        margin-bottom: 15px;
    }

    .status-pending {
        background: #fff3cd;
        color: #856404;
    }

    .status-ready {
        background: #d4edda;
        color: #155724;
    }

    .status-completed {
        background: #e7f3ff;
        color: #0073aa;
    }

    /* ============================================
       MISC COMPONENTS
       ============================================ */
    .mmgr-portal-footer {
        background: #f9f9f9;
        padding: 30px;
        margin-top: 40px;
        border-top: 3px solid #ce00ff;
    }

    .mmgr-activity-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }

    @media (max-width: 768px) {
        .mmgr-activity-grid {
            grid-template-columns: 1fr;
        }
    }

    .mmgr-events-widget {
        margin-top: 20px;
    }

    .mmgr-post-like-btn {
        background: white;
        color: #FF2197;
        border: 2px solid #FF2197;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        font-size: 14px;
        transition: all 0.3s;
    }

    .mmgr-post-like-btn.liked {
        background: #FF2197;
        color: white;
    }

    .mmgr-tab-content {
        display: block;
    }
	
    <?php if (!get_option('mmgr_show_portal_titles', 1)): ?>
    .mmgr-portal-titlecc {
        display: none !important;
    }
    <?php endif; ?>
	
    </style>	
    <?php
});

add_action('admin_head', function() {
    ?>
    <style>
    .mmgr-admin-messages-wrap {
        margin: 20px 20px 20px 0;
    }
    
    .mmgr-admin-messages-grid {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 20px;
        height: calc(100vh - 200px);
        min-height: 600px;
    }
    
    .mmgr-admin-sidebar {
        background: white;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        overflow-y: auto;
    }
    
    .mmgr-admin-chat-area {
        background: white;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        display: flex;
        flex-direction: column;
        height: auto;
        max-height: 800px;
    }
    
    .mmgr-admin-conv-item {
        padding: 15px;
        border-bottom: 1px solid #f0f0f0;
        cursor: pointer;
        transition: background 0.2s;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .mmgr-admin-conv-item:hover {
        background: #f6f7f7;
    }
    
    .mmgr-admin-conv-item.active {
        background: #f0f6fc;
        border-left: 4px solid #2271b1;
    }
    
    .mmgr-admin-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }
    
    .mmgr-admin-avatar-placeholder {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: #f0f0f0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        flex-shrink: 0;
    }
    
    .mmgr-admin-unread-badge {
        background: #d63638;
        color: white;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: bold;
        margin-left: auto;
    }
    
    .mmgr-admin-chat-header {
        padding: 20px;
        background: #2271b1;
        color: white;
        border-bottom: 1px solid #135e96;
        display: flex;
        align-items: center;
        gap: 15px;
        flex-shrink: 0;
    }
    
    .mmgr-admin-messages-list {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        background: #f6f7f7;
        max-height: 300px;
        min-height: 300px;
    }
    
    .mmgr-admin-message-bubble {
        margin-bottom: 15px;
        display: flex;
        gap: 10px;
    }
    
    .mmgr-admin-message-bubble.sent {
        flex-direction: row-reverse;
    }
    
    .mmgr-admin-message-content {
        max-width: 60%;
        background: white;
        padding: 12px 16px;
        border-radius: 12px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }
    
    .mmgr-admin-message-bubble.sent .mmgr-admin-message-content {
        background: #2271b1;
        color: white;
    }
    
    .mmgr-admin-message-time {
        font-size: 11px;
        color: #666;
        margin-top: 5px;
    }
    
    .mmgr-admin-message-bubble.sent .mmgr-admin-message-time {
        color: rgba(255,255,255,0.7);
    }
    
    .mmgr-admin-message-image {
        max-width: 100%;
        border-radius: 8px;
        margin-top: 8px;
        cursor: pointer;
    }
    
    .mmgr-admin-input-area {
        padding: 20px;
        background: white;
        border-top: 1px solid #ccd0d4;
        flex-shrink: 0;
    }
    
    .mmgr-admin-input-wrapper {
        display: flex;
        gap: 10px;
        align-items: flex-end;
    }
    
    .mmgr-admin-textarea {
        flex: 1;
        padding: 10px;
        border: 1px solid #8c8f94;
        border-radius: 4px;
        resize: none;
        font-family: inherit;
        font-size: 14px;
    }
    
    .mmgr-admin-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #646970;
        padding: 40px;
        text-align: center;
    }
    
    .mmgr-member-info-box {
        background: #f0f6fc;
        border: 1px solid #c3e0f7;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .mmgr-member-info-box h4 {
        margin: 0 0 10px 0;
        color: #135e96;
    }
    
    .mmgr-member-info-row {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .mmgr-member-info-row:last-child {
        border-bottom: none;
    }

    /* ============================================
       HELP CENTER PAGE
       ============================================ */
    .mmgr-help-wrap {
        max-width: 820px;
        margin: 0 auto;
        background: #fff;
        border-radius: 8px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .mmgr-help-search-wrap {
        margin-bottom: 24px;
    }

    .mmgr-help-search {
        width: 100%;
        padding: 12px 16px;
        font-size: 16px;
        background: #fff;
        color: #333;
        border: 2px solid var(--portal-border);
        border-radius: 30px;
        outline: none;
        transition: border-color 0.2s;
        box-sizing: border-box;
    }
    .mmgr-help-search:focus {
        border-color: var(--portal-primary);
    }

    .mmgr-help-results-count {
        font-size: 13px;
        color: #666;
        margin-bottom: 16px;
    }

    .mmgr-help-category {
        margin-bottom: 28px;
    }

    .mmgr-help-category-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--portal-primary);
        margin: 0 0 10px;
        padding: 6px 14px;
        background: linear-gradient(44deg, rgba(155,81,224,0.10), rgba(206,0,255,0.08));
        border-left: 4px solid var(--portal-primary);
        border-radius: 4px;
    }

    .mmgr-help-accordion {
        border: 1px solid var(--portal-border);
        border-radius: 8px;
        overflow: hidden;
    }

    .mmgr-help-item + .mmgr-help-item {
        border-top: 1px solid var(--portal-border);
    }

    .mmgr-help-question {
        width: 100%;
        background: #fff;
        border: none;
        text-align: left;
        padding: 14px 18px;
        font-size: 15px;
        font-weight: 600;
        color: #222;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        transition: background 0.15s;
    }
    .mmgr-help-question:hover {
        background: var(--portal-light-bg);
    }
    .mmgr-help-question[aria-expanded="true"] {
        background: linear-gradient(44deg, rgba(155,81,224,0.08), rgba(206,0,255,0.05));
        color: var(--portal-primary);
    }

    .mmgr-help-q-text {
        flex: 1;
    }

    .mmgr-help-chevron {
        font-size: 12px;
        flex-shrink: 0;
        color: var(--portal-primary);
        transition: transform 0.2s;
    }

    .mmgr-help-answer {
        background: var(--portal-light-bg);
        border-top: 1px solid var(--portal-border);
    }

    .mmgr-help-answer-inner {
        padding: 16px 18px;
        font-size: 15px;
        line-height: 1.7;
        color: #333;
    }

    .mmgr-help-answer-inner a {
        color: var(--portal-primary);
    }

    .mmgr-help-answer-inner ul,
    .mmgr-help-answer-inner ol {
        margin: 8px 0 8px 20px;
    }

    .mmgr-help-no-results {
        text-align: center;
        color: #888;
        padding: 40px 0;
        font-size: 16px;
    }

    /* ============================================
       CODE OF CONDUCT PAGE
       ============================================ */
    .mmgr-coc {
        max-width: 800px;
        margin: 20px auto;
        background: #000;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 4px 20px rgba(255,33,151,0.3);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        overflow: hidden;
        border: 2px solid #FF2197;
        color: #fff;
    }

    .mmgr-coc h3 {
        color: #6b21a8;
        font-size: 1.1rem;
        font-weight: 700;
        margin: 32px 0 10px;
        padding: 8px 14px;
        background: linear-gradient(44deg, rgba(107,33,168,0.12), rgba(85,0,251,0.10));
        border-left: 4px solid #6b21a8;
        border-radius: 4px;
        display: block;
    }

    .mmgr-coc ul {
        list-style: none;
        padding: 0;
        margin: 0 0 12px 0;
    }

    .mmgr-coc ul li {
        padding: 6px 10px 6px 28px;
        position: relative;
        border-bottom: 1px solid rgba(255,33,151,0.2);
        line-height: 1.6;
        color: #fff;
    }

    .mmgr-coc ul li::before {
        content: '●';
        color: #FF2197;
        position: absolute;
        left: 8px;
        font-size: 0.6em;
        top: 11px;
    }

    .mmgr-coc p {
        margin: 10px 0;
        line-height: 1.6;
        color: #ccc;
    }

    /* ============================================
       CHECK-IN PAGE
       ============================================ */
    .mmgr-checkin-container {
        max-width: 700px;
        margin: 0 auto;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    .mmgr-checkin-container > h2 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--portal-primary);
        margin-bottom: 20px;
        text-align: center;
    }

    .mmgr-mode-switch {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .mmgr-mode-switch button {
        flex: 1;
        padding: 10px 16px;
        border: 2px solid var(--portal-blue);
        border-radius: 6px;
        background: #fff;
        color: var(--portal-blue);
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .mmgr-mode-switch button.active {
        background: var(--portal-blue);
        color: #fff;
    }

    .mmgr-mode-switch button:hover:not(.active) {
        background: #f0f4ff;
    }

    .mmgr-scan-input {
        width: 100%;
        padding: 15px;
        font-size: 18px;
        border: 2px solid var(--portal-blue);
        border-radius: 6px;
        box-sizing: border-box;
    }

    .mmgr-scan-hint {
        color: #666;
        margin-top: 10px;
        font-size: 14px;
    }

    .mmgr-camera-view {
        max-width: 500px;
        margin: 0 auto 12px;
    }

    .mmgr-manual-actions {
        margin-top: 10px;
    }

    .mmgr-checkin-result {
        margin-top: 30px;
    }

    /* Member result card */
    .mmgr-member-card {
        background: #fff;
        border: 3px solid #00a32a;
        border-radius: 12px;
        padding: 20px;
        margin: 20px 0;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .mmgr-member-card-header {
        display: flex;
        gap: 20px;
        align-items: flex-start;
        margin-bottom: 20px;
    }

    .mmgr-member-photo {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 50%;
        border: 3px solid #00a32a;
        flex-shrink: 0;
    }

    .mmgr-member-avatar {
        width: 100px;
        height: 100px;
        background: #f0f0f0;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 50px;
        border: 3px solid #ccc;
        flex-shrink: 0;
    }

    .mmgr-member-info {
        flex: 1;
    }

    .mmgr-member-name {
        margin: 0 0 5px 0;
        color: #00a32a;
        font-size: 1.25rem;
    }

    .mmgr-member-info p {
        margin: 5px 0;
        font-size: 14px;
    }

    .mmgr-expired-notice {
        margin: 10px 0;
        padding: 10px;
        background: #fff3cd;
        border-left: 4px solid #f0c33c;
        border-radius: 4px;
    }

    /* Payment section */
    .mmgr-payment-section {
        background: #f0f8ff;
        padding: 15px;
        border-radius: 6px;
        margin-top: 15px;
    }

    .mmgr-fee-label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }

    .mmgr-fee-group {
        margin-bottom: 15px;
    }

    .mmgr-fee-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
    }

    .mmgr-fee-amount {
        font-size: 24px;
        font-weight: bold;
    }

    .mmgr-fee-input {
        width: 120px;
        padding: 10px;
        font-size: 18px;
        border: 2px solid var(--portal-blue);
        border-radius: 6px;
        font-weight: bold;
    }

    .mmgr-discount-btn {
        background: #f0c33c;
        color: #1d2327;
        border: none;
        padding: 8px 15px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: bold;
        cursor: pointer;
    }

    .mmgr-discount-btn:hover {
        background: #dbb02e;
    }

    .mmgr-fee-hint {
        margin: 5px 0 0 0;
        font-size: 12px;
        color: #666;
    }

    .mmgr-payment-options {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 10px;
    }

    .mmgr-payment-options label {
        display: flex;
        align-items: center;
        gap: 5px;
        cursor: pointer;
    }

    .mmgr-notes-input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        margin-bottom: 10px;
        box-sizing: border-box;
    }

    .mmgr-confirm-btn {
        background: #00a32a;
        color: #fff;
        border: none;
        padding: 12px 24px;
        border-radius: 6px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        width: 100%;
    }

    .mmgr-confirm-btn:hover {
        background: #008a22;
    }

    @media (max-width: 600px) {
        .mmgr-member-card-header {
            flex-direction: column;
            align-items: center;
        }
    }
    </style>
    <?php
});

// AJAX polling script for unread message count in portal navigation
add_action('wp_footer', function() {
    // Only output on pages that include the portal navigation
    if (!function_exists('mmgr_get_current_member') || !mmgr_get_current_member()) {
        return;
    }
    ?>
    <script>
    (function() {
        // IDs present on every portal page that has the nav
        var navBadge    = document.getElementById('mmgr-nav-unread-badge');
        var msgBadge    = document.getElementById('mmgr-messages-unread-badge');

        // Stats bar elements (desktop)
        var statMsg     = document.getElementById('mmgr-stat-messages');
        var statLikes   = document.getElementById('mmgr-stat-likes');
        var statEvents  = document.getElementById('mmgr-stat-events');
        var statAwards  = document.getElementById('mmgr-stat-awards');

        // Mobile toggle button stats
        var statMsgM    = document.getElementById('mmgr-stat-messages-mobile');
        var statLikesM  = document.getElementById('mmgr-stat-likes-mobile');
        var statEventsM = document.getElementById('mmgr-stat-events-mobile');
        var statAwardsM = document.getElementById('mmgr-stat-awards-mobile');

        // Only run on pages that include the portal navigation stats bar
        if (!statMsg && !statMsgM && !navBadge && !msgBadge) return;

        var ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';

        function mmgrUpdateUnreadBadges(count) {
            var label = count > 0 ? '(' + count + ')' : '';
            var show  = count > 0;

            if (navBadge) {
                navBadge.textContent = label;
                navBadge.style.display = show ? 'inline-block' : 'none';
            }
            if (msgBadge) {
                msgBadge.textContent = label;
                msgBadge.style.display = show ? 'inline-block' : 'none';
            }
        }

        function mmgrSetText(el, val) {
            if (el) el.textContent = val;
        }

        function mmgrSetHTML(el, html) {
            if (el) el.innerHTML = html;
        }

        function mmgrFetchNavStats() {
            var formData = new FormData();
            formData.append('action', 'mmgr_get_nav_stats');

            fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data || !data.success) return;
                    var d = data.data;

                    // Update unread message badges (legacy + stats bar)
                    var msgs = parseInt(d.messages, 10) || 0;
                    mmgrUpdateUnreadBadges(msgs);
                    mmgrSetText(statMsg,  msgs);
                    mmgrSetText(statMsgM, msgs);

                    // Likes
                    var likes = parseInt(d.likes, 10) || 0;
                    mmgrSetText(statLikes,  likes);
                    mmgrSetText(statLikesM, likes);

                    // Events
                    var events = parseInt(d.events, 10) || 0;
                    mmgrSetText(statEvents,  events);
                    mmgrSetText(statEventsM, events);

                    // Award badges (HTML)
                    mmgrSetHTML(statAwards,  d.awards || '');
                    mmgrSetHTML(statAwardsM, d.awards || '');
                })
                .catch(function() { /* Network error – will retry on next interval. */ });
        }

        // Initial fetch and then every 60 seconds
        mmgrFetchNavStats();
        setInterval(mmgrFetchNavStats, 60000);
    })();
    </script>
    <?php
});
