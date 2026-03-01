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
        padding: 15px 20px;
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
        text-align: center;
        display: flex;
        gap: 12px;
        justify-content: center;
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
	
	
    </style>	
    <?php
});