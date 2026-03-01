<?php
if (!defined('ABSPATH')) exit;

/**
 * Add member portal styles to frontend
 */
add_action('wp_head', function() {
    // Only load on portal pages
    if (!is_page(array('member-dashboard', 'member-login', 'member-setup', 'member-activity', 'member-profile', 'member-community'))) {
        return;
    }
    ?>
    <style>
    /* Portal Container */
    .mmgr-portal-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    
    /* Portal Navigation - Hamburger Menu */
    .mmgr-portal-nav {
        background: linear-gradient(135deg, #000 0%, #FF2197 100%);
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 4px 15px rgba(255, 33, 151, 0.3);
        position: relative;
    }

    .mmgr-nav-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        color: white;
        font-weight: bold;
        font-size: 18px;
        user-select: none;
    }

    .mmgr-hamburger {
        display: flex;
        flex-direction: column;
        gap: 4px;
        width: 30px;
    }

    .mmgr-hamburger span {
        display: block;
        height: 3px;
        background: white;
        border-radius: 2px;
        transition: all 0.3s;
    }

    .mmgr-nav-toggle.active .mmgr-hamburger span:nth-child(1) {
        transform: rotate(45deg) translate(7px, 7px);
    }

    .mmgr-nav-toggle.active .mmgr-hamburger span:nth-child(2) {
        opacity: 0;
    }

    .mmgr-nav-toggle.active .mmgr-hamburger span:nth-child(3) {
        transform: rotate(-45deg) translate(7px, -7px);
    }

    .mmgr-nav-items {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
        margin-top: 0;
    }

    .mmgr-nav-items.active {
        max-height: 500px;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 2px solid rgba(255, 255, 255, 0.2);
    }

    .mmgr-portal-nav a {
        display: block;
        color: white;
        text-decoration: none;
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 8px;
        transition: all 0.3s;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.1);
    }

    .mmgr-portal-nav a:hover {
        background: rgba(255, 255, 255, 0.25);
        transform: translateX(5px);
    }

    .mmgr-portal-nav a.active {
        background: white;
        color: #FF2197;
        font-weight: bold;
    }

    .mmgr-portal-nav a.logout {
        background: rgba(214, 54, 56, 0.3);
        border: 2px solid rgba(214, 54, 56, 0.5);
        margin-top: 10px;
    }

    .mmgr-portal-nav a.logout:hover {
        background: #d63638;
        border-color: #d63638;
        color: white;
    }
    
    /* Welcome Header */
    .mmgr-portal-welcome {
        background: linear-gradient(135deg, #f0f8ff 0%, #e7f3ff 100%);
        border-left: 4px solid #0073aa;
        padding: 20px 30px;
        border-radius: 8px;
        margin-bottom: 30px;
    }
    
    .mmgr-portal-welcome h1 {
        margin: 0;
        font-size: 28px;
        color: #0073aa;
    }
    
    /* Portal Card */
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
    
    /* Grid Layout */
    .mmgr-portal-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }
    
    /* Form Fields */
    .mmgr-field {
        margin-bottom: 20px;
    }
    
    .mmgr-field label {
        display: block;
        font-weight: 600;
        margin-bottom: 5px;
        color: #333;
    }
    
    .mmgr-field input[type="text"],
    .mmgr-field input[type="email"],
    .mmgr-field input[type="password"],
    .mmgr-field input[type="tel"],
    .mmgr-field textarea,
    .mmgr-field select {
        width: 100%;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 6px;
        font-size: 16px;
        transition: border-color 0.3s;
        box-sizing: border-box;
    }
    
    .mmgr-field input:focus,
    .mmgr-field textarea:focus,
    .mmgr-field select:focus {
        border-color: #0073aa;
        outline: none;
    }
    
    /* Buttons */
    .mmgr-btn-primary,
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
    }
    
    .mmgr-btn-primary {
        background: linear-gradient(135deg, #0073aa 0%, #005a87 100%);
        color: white;
    }
    
    .mmgr-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,115,170,0.3);
    }
    
    .mmgr-btn-secondary {
        background: #f0f0f0;
        color: #333;
    }
    
    .mmgr-btn-secondary:hover {
        background: #e0e0e0;
    }
    
    /* Info Table */
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
    
    /* Status Badges */
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
    
    /* Error/Success Messages */
    .mmgr-error {
        background: #ffe2e2;
        border-left: 4px solid #d00;
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 20px;
        color: #d00;
    }
    
    .mmgr-success {
        background: #d4edda;
        border-left: 4px solid #159742;
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 20px;
        color: #155724;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .mmgr-portal-nav {
            padding: 12px 15px;
        }
        
        .mmgr-nav-toggle {
            font-size: 16px;
        }
        
        .mmgr-portal-grid {
            grid-template-columns: 1fr;
        }
        
        .mmgr-portal-welcome h1 {
            font-size: 22px;
        }
    }
    
    /* Activity Timeline */
    .mmgr-timeline {
        position: relative;
        padding-left: 30px;
    }
    
    .mmgr-timeline::before {
        content: '';
        position: absolute;
        left: 10px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e0e0e0;
    }
    
    .mmgr-timeline-item {
        position: relative;
        padding: 15px 0;
        margin-bottom: 10px;
    }
    
    .mmgr-timeline-item::before {
        content: '•';
        position: absolute;
        left: -24px;
        top: 15px;
        width: 12px;
        height: 12px;
        background: #0073aa;
        border-radius: 50%;
        font-size: 20px;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .mmgr-timeline-item.special-event::before {
        background: #ff9800;
        content: '★';
    }
    
    /* Forum Styles */
    .mmgr-forum-post {
        background: #f9f9f9;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        display: flex;
        gap: 15px;
    }
    
    .mmgr-forum-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }
    
    .mmgr-forum-avatar-placeholder {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: #0073aa;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        font-weight: bold;
        flex-shrink: 0;
    }
    
    .mmgr-forum-content {
        flex: 1;
    }
    
    .mmgr-forum-meta {
        font-size: 14px;
        color: #666;
        margin-bottom: 8px;
    }
    
    .mmgr-forum-message {
        color: #333;
        line-height: 1.6;
    }
    
    .mmgr-forum-compose {
        background: #f0f8ff;
        border: 2px solid #0073aa;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 30px;
    }
    
    .mmgr-forum-compose textarea {
        width: 100%;
        min-height: 100px;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 6px;
        font-size: 16px;
        font-family: inherit;
        resize: vertical;
        box-sizing: border-box;
    }
    
    /* Photo Upload Preview */
    .mmgr-photo-preview {
        margin: 15px 0;
        text-align: center;
    }
    
    .mmgr-photo-preview img {
        max-width: 200px;
        border-radius: 8px;
        border: 2px solid #e0e0e0;
    }
    
    /* Empty State */
    .mmgr-empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #999;
    }
    
    .mmgr-empty-state-icon {
        font-size: 64px;
        margin-bottom: 20px;
    }
    </style>
    <?php
});