# Membership Manager with QR Codes

A WordPress plugin for complete membership management with QR code check-ins.

## Paid event tickets

In **Memberships → Edit Member**, use **Paid Event Tickets** to check events
after receiving payment, then save the member. Uncheck an event to remove its
paid ticket. Upcoming active events with ticket sales enabled are available;
already-assigned tickets remain editable even if ticket sales are turned off.
Past and inactive event records are retained.

Members see **Your upcoming events** below the **Download** button in **Your
Digital Membership Card**, with rounded cards showing each prepaid event's
title, date, time, and **PAID** badge. Ticket payment is independent of membership
payment. Marking an event ticket paid automatically adds the member to its
**Going** list without duplicating an existing RSVP. Existing paid tickets for
upcoming active events are also added when upgrading. Paid ticket holders see
**YOU ARE GOING** instead of **BUY TICKETS**, even after ticket sales close.
Removing a paid ticket does not remove the member's RSVP.

### Admin sales and complimentary access

**Memberships → Add / Edit Member** records newly paid memberships (including
new accounts) and newly assigned paid event tickets in a separate account
transaction ledger. Paid tickets use the event's configured price for the
account's membership level; a couple price is charged once, not per person.
No historical sales are guessed or backfilled.

For a free membership, choose **COMP membership — 1 month free** or **12 months
free**, then save. This sets paid access, method `COMP`, amount `$0`, and dates
from today's WordPress-local date through the selected calendar term, clamped
to the last day of the destination month when needed. It replaces current dates
rather than extending an existing term. Ordinary saves retain COMP status.
For an event, check its ticket and select **COMP — free ticket** before saving.
It grants the same admission and Going RSVP as a paid ticket, without charging.
Existing tickets retain their paid/COMP status; revoke and reassign deliberately
if a different grant is required.

The member's **Account transaction log** and **Accounting**
(`admin.php?page=membership_logs`) clearly show **COMP'D TICKET** /
**COMP'D MEMBERSHIP**, dates, administrator ID, and zero amount. The logs page
includes account transaction pagination and a separate full-history CSV with
amounts, methods, event/member IDs and audit descriptions. Paid sales contribute
to today's/month's revenue; COMPs do not. Neither sales nor COMPs create visits
or inflate attendance. Deleting visits leaves account audit history intact.

Normal resaves do not create another sale. Correcting paid membership dates or
amounts does not record another payment or change the payment method. Use
**Record a new paid membership / renewal** to explicitly record another payment
(including converting a COMP to paid), using **Amount Paid** and the entered
dates. An unpaid-to-paid transition is recorded automatically. Membership and
ticket changes are committed together with their audit records or rolled back
on failure. Removing a ticket logs revocation, not a refund, and preserves RSVPs
and historical account transactions. These actions require administrator
permission and a valid form nonce.

Atomic grants require InnoDB storage. New ticket/RSVP tables explicitly use
InnoDB; older ticket/RSVP tables using another engine are converted before the
transaction starts. If engine verification or conversion fails, the account
save is refused rather than granting access without a durable audit record.
This ledger records the Add / Edit Member workflow; other pre-existing payment
collection handlers retain their existing logging behavior.

## Couples account upgrades

Single, male, and female members can use **Upgrade to a Couples Account** on
their portal **Profile** page. The partner's first name, last name, and date of
birth are required; partners must be at least 18. A configured **Couple** or
**Couples** membership level is required.

Paid accounts receive a visible admin note stating that **$20.00 is owed at the
next visit**, shown to administrators during QR check-in. Unpaid accounts receive
no upgrade debt or payment change. Existing payment records, membership dates,
and QR codes are preserved. Staff can hide the reminder in **Edit Member** after
collecting payment.

## Bio update tracking

Open **Memberships → Bio Updates**, directly below **BIO Fields**, to review
member changes to community aliases, bios, profile photos, custom BIO fields
(including images), bio gallery photos and orientation selections. Each saved
change keeps dated before/after values. Unchanged saves do not create updates.
Tracking starts on installation; existing profiles are not backfilled.

The badge counts unviewed updates, shared across all administrators. Opening
the page marks only its displayed updates viewed; other pages and changes made
afterward remain unread. The badge disappears at zero and returns after a new
change, on the next admin page load. History remains available after viewing.
Profile changes and their history are saved together; a tracking failure is
reported rather than silently saving an untracked change.
This requires InnoDB membership storage; legacy BIO value, gallery and selection
tables are converted to InnoDB before saving.

Removing a gallery photo removes it from the member's profile but retains its
uploaded file for the administrator's before/after history. This history is
restricted to administrators with `manage_options`; uploaded images retain the
site's existing upload URL access rules. Passwords, private notes and chemistry
answers are not copied into this content-moderation history.

## Forum administration

Open **Memberships → Forum Posts**, directly below **Forum Topics**, to review
discussion posts, including hidden posts and photos. The menu badge counts posts
not yet viewed by an administrator. Opening the page marks only its displayed
posts as read for all administrators; posts on other pages or arriving afterward
remain unread. The badge disappears when no unread posts remain. Counts update
on the next admin page load. Existing posts initially count as unread.

Use **Edit** to update a message while preserving its edit history, **Delete** to
permanently remove a post and its associated comments, likes, photo references,
reports and edit history, or **Ban from forum** to prevent its author from
posting and commenting. A forum ban does not disable membership or private
messaging, and can be reversed with **Unban from forum**. These actions require
administrator permission and a valid form nonce.

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
