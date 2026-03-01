# Membership Manager with QR Codes

A WordPress plugin for complete membership management with QR code check-ins.

## Plugin Files

### Plugin Root
- `membership-manager-qr/membership-manager-qr.php` – Main plugin file; defines constants, loads core includes, and registers activation hooks.
- `membership-manager-qr/phpqrcode.php` – Third-party QR code generation library.

### Includes (`membership-manager-qr/includes/`)
- `admin-menu.php` – Registers and renders the admin menu pages.
- `admin-messages.php` – Handles admin notice messages.
- `ajax-handlers.php` – AJAX action handlers for front-end and admin requests.
- `auth-functions.php` – Authentication and permission helper functions.
- `database.php` – Database table creation and upgrade logic.
- `email-functions.php` – Email notification functions.
- `member-portal-functions.php` – Core functions for the member portal.
- `member-portal-shortcodes.php` – Shortcodes used in the member-facing portal.
- `member-portal-styles.php` – Inline styles for the member portal.
- `messaging-functions.php` – Internal messaging system functions.
- `qr-generator.php` – Wrapper functions for generating member QR codes.
- `shortcodes.php` – General-purpose shortcodes.
- `styles.php` – Admin and front-end stylesheet enqueuing.
- `code_of_conduct.txt` – Code of conduct text displayed to members.

### Includes – Admin Pages (`membership-manager-qr/includes/admin/`)
- `add-edit-member.php` – Add and edit member form logic.
- `events-page.php` – Events management admin page.
- `forum-topics.php` – Forum topics admin page.
- `levels-page.php` – Membership levels admin page.
- `logs-page.php` – Activity logs admin page.
- `members-page.php` – Members list and management admin page.
- `messages-admin.php` – Messaging admin page.
- `pages-admin.php` – Plugin pages admin configuration.
- `pages-overview.php` – Overview of plugin-created pages.
- `settings-page.php` – Plugin settings admin page.
- `special-fees-page.php` – Special fees management admin page.
- `special-fees.php` – Special fees calculation functions.
- `visit-logs.php` – Visit/check-in logs admin page.
