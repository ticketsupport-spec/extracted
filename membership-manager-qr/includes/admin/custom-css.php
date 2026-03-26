<?php
if (!defined('ABSPATH')) exit;

// Handle clear
if (isset($_GET['clear']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'mmgr_clear_custom_css')) {
    delete_option('mmgr_custom_css');
    wp_redirect(admin_url('admin.php?page=membership_custom_css&cleared=1'));
    exit;
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mmgr_save_custom_css'])) {
    if (!isset($_POST['mmgr_custom_css_nonce']) || !wp_verify_nonce($_POST['mmgr_custom_css_nonce'], 'mmgr_save_custom_css')) {
        echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
    } else {
        $global_css = isset($_POST['mmgr_global_css']) ? wp_strip_all_tags($_POST['mmgr_global_css']) : '';
        update_option('mmgr_custom_css', $global_css);
        echo '<div class="notice notice-success"><p>Custom CSS saved successfully!</p></div>';
    }
}

$global_css = get_option('mmgr_custom_css', '');

?>
<div class="wrap">
    <h1 class="wp-heading-inline">🎨 Custom CSS</h1>
    <hr class="wp-header-end">

    <?php if (isset($_GET['cleared'])): ?>
        <div class="notice notice-success is-dismissible"><p>Custom CSS cleared.</p></div>
    <?php endif; ?>

    <p style="margin-bottom:20px;color:#555;">
        Add custom CSS to override or extend the default styles used across all plugin pages. Your CSS is loaded <strong>after</strong>
        the plugin's built-in styles, so any rules you add here will take priority.
    </p>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:30px;margin-top:20px;">

        <!-- CSS Editor -->
        <div>
            <div class="card" style="padding:20px;">
                <h2 style="margin-top:0;">Global Custom CSS</h2>
                <p style="color:#555;margin-bottom:15px;">
                    CSS entered here is applied to <strong>every plugin page</strong> on the front end.
                    Use it to change colors, fonts, layout, or hide/show any element.
                </p>

                <form method="POST">
                    <?php wp_nonce_field('mmgr_save_custom_css', 'mmgr_custom_css_nonce'); ?>

                    <textarea
                        name="mmgr_global_css"
                        id="mmgr-global-css"
                        rows="25"
                        style="width:100%;font-family:monospace;font-size:13px;line-height:1.6;background:#1e1e1e;color:#d4d4d4;border:1px solid #555;border-radius:4px;padding:12px;resize:vertical;"
                        placeholder="/* Add your custom CSS here */
/* Example: change primary color */
:root {
    --portal-primary: #e74c3c;
    --portal-secondary: #c0392b;
}

/* Example: hide the portal navigation */
/*.mmgr-portal-nav-wrapper { display: none; }*/"
                    ><?php echo esc_textarea($global_css); ?></textarea>

                    <p class="submit" style="margin-top:15px;">
                        <button type="submit" name="mmgr_save_custom_css" class="button button-primary button-large">
                            💾 Save Custom CSS
                        </button>
                        <?php if (!empty($global_css)): ?>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=membership_custom_css&clear=1'), 'mmgr_clear_custom_css')); ?>"
                               class="button button-link-delete"
                               style="color:#d63638;margin-left:10px;"
                               onclick="return confirm('Are you sure you want to clear all custom CSS?');">
                                🗑️ Clear All CSS
                            </a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
        </div>

        <!-- Help & Tips -->
        <div>
            <div style="padding:15px;background:#f0f6fc;border-left:4px solid #0073aa;border-radius:4px;margin-bottom:20px;">
                <h3 style="margin-top:0;">ℹ️ How It Works</h3>
                <ul style="list-style:disc;margin-left:20px;line-height:1.8;color:#333;">
                    <li>Your CSS is injected into <code>&lt;style&gt;</code> tags on every plugin page, <strong>after</strong> the built-in styles.</li>
                    <li>Use standard CSS. Any valid rule is accepted.</li>
                    <li>Changes are applied immediately after saving — no cache to clear.</li>
                    <li>This CSS is only output on the <strong>front end</strong> (not in the WordPress admin).</li>
                </ul>
            </div>

            <div style="padding:15px;background:#fff8e1;border-left:4px solid #f0b429;border-radius:4px;margin-bottom:20px;">
                <h3 style="margin-top:0;">🎨 CSS Variables</h3>
                <p style="color:#555;margin-bottom:10px;">Override these <code>:root</code> variables to retheme the entire portal at once:</p>
                <table style="width:100%;font-size:12px;font-family:monospace;border-collapse:collapse;">
                    <?php
                    $vars = [
                        '--portal-primary'       => '#9b51e0',
                        '--portal-primary-dark'  => '#7d3cb8',
                        '--portal-secondary'     => '#ce00ff',
                        '--portal-accent'        => '#FF2197',
                        '--portal-blue'          => '#0073aa',
                        '--portal-success'       => '#28a745',
                        '--portal-error'         => '#dc3545',
                        '--portal-border'        => '#e0e0e0',
                        '--portal-light-bg'      => '#f9f9f9',
                    ];
                    foreach ($vars as $var => $default):
                    ?>
                    <tr>
                        <td style="padding:3px 6px;color:#555;"><?php echo esc_html($var); ?></td>
                        <td style="padding:3px 6px;">
                            <span style="display:inline-block;width:12px;height:12px;background:<?php echo esc_attr($default); ?>;border-radius:2px;vertical-align:middle;margin-right:4px;border:1px solid #ccc;"></span>
                            <code style="font-size:11px;"><?php echo esc_html($default); ?></code>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div style="padding:15px;background:#f0fff4;border-left:4px solid #28a745;border-radius:4px;">
                <h3 style="margin-top:0;">💡 Common Overrides</h3>
                <ul style="list-style:disc;margin-left:20px;line-height:1.8;color:#333;font-size:13px;">
                    <li>Change brand colors via <code>:root</code> variables</li>
                    <li>Hide the portal navigation bar</li>
                    <li>Adjust card or table padding/spacing</li>
                    <li>Override button styles</li>
                    <li>Add custom fonts via <code>@import</code></li>
                    <li>Tweak responsive breakpoints</li>
                </ul>
            </div>
        </div>

    </div>
</div>
