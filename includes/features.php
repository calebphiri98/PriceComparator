<?php
/**
 * includes/features.php
 * Expected prices (farmers), price offers (buyers), admin approval workflow
 * and SMS notifications. Loaded from config.php once $db is ready.
 */
require_once __DIR__ . '/sms.php';

$db->exec("
    CREATE TABLE IF NOT EXISTS farmer_expected_prices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        farmer_id INTEGER NOT NULL,
        crop TEXT NOT NULL,
        expected_price REAL NOT NULL,
        quantity INTEGER,
        location TEXT NOT NULL,
        notes TEXT,
        status TEXT NOT NULL DEFAULT 'pending',
        rejection_reason TEXT,
        reviewed_by INTEGER,
        reviewed_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS buyer_price_offers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        buyer_id INTEGER NOT NULL,
        buyer_name TEXT NOT NULL,
        crop TEXT NOT NULL,
        price_per_kg REAL NOT NULL,
        quantity INTEGER NOT NULL DEFAULT 1000,
        location TEXT NOT NULL,
        contact TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending',
        rejection_reason TEXT,
        reviewed_by INTEGER,
        reviewed_at DATETIME,
        published_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS sms_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        phone TEXT NOT NULL,
        message TEXT NOT NULL,
        status TEXT NOT NULL,
        response TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE INDEX IF NOT EXISTS idx_fep_status ON farmer_expected_prices (status);
    CREATE INDEX IF NOT EXISTS idx_bpo_status ON buyer_price_offers (status);
");

/* ---------- small helpers ---------- */

function esc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function mk($amount): string
{
    return 'MK ' . number_format((float) $amount, 0);
}

function short_text(string $text, int $max): string
{
    $text = trim((string) preg_replace('/\s+/', ' ', $text));
    return strlen($text) > $max ? substr($text, 0, $max - 3) . '...' : $text;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . esc(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $token = $_POST['csrf'] ?? '';
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function status_pill(string $status): string
{
    $labels = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'withdrawn' => 'Withdrawn'];
    $key = isset($labels[$status]) ? $status : 'pending';
    return '<span class="pill pill-' . $key . '">' . $labels[$key] . '</span>';
}

function pill_styles(): string
{
    return '<style>
.pill{display:inline-block;padding:3px 11px;border-radius:999px;font-size:.8rem;font-weight:600;white-space:nowrap}
.pill-pending{background:#fff8e1;color:#8a6d00;border:1px solid #ffe082}
.pill-approved{background:#e8f5e9;color:#1b5e20;border:1px solid #a5d6a7}
.pill-rejected{background:#ffebee;color:#b71c1c;border:1px solid #ef9a9a}
.pill-withdrawn{background:#eceff1;color:#455a64;border:1px solid #cfd8dc}
.source-farmer{color:#6a1b9a;font-weight:600}
.btn-danger{background:#c62828 !important}
.review-card{background:#fff;border-radius:10px;padding:16px;margin-bottom:28px;box-shadow:0 2px 10px rgba(46,125,50,.08)}
</style>';
}

/* ---------- notifications ---------- */

function notify_user(PDO $db, int $user_id, string $message): array
{
    $stmt = $db->prepare("SELECT phone FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $phone = $stmt->fetchColumn();

    if (!$phone) {
        return ['sent' => 0, 'failed' => 0, 'skipped' => 1, 'detail' => 'User has no phone number'];
    }
    return sms_send_many($db, [['phone' => $phone, 'user_id' => $user_id]], $message);
}

function notify_all_farmers(PDO $db, string $message): array
{
    $limit = max(1, (int) sms_config()['max_broadcast']);
    $rows = $db->query("SELECT id, phone FROM users WHERE role = 'farmer' ORDER BY id LIMIT " . $limit)
               ->fetchAll(PDO::FETCH_ASSOC);

    $recipients = [];
    foreach ($rows as $row) {
        $recipients[] = ['phone' => $row['phone'], 'user_id' => (int) $row['id']];
    }
    return sms_send_many($db, $recipients, $message);
}

/* ---------- admin review: buyer price offers ---------- */

/** Returns [bool ok, string message]. $decision is 'approve' or 'reject'. */
function review_buyer_offer(PDO $db, int $offer_id, int $admin_id, string $decision, string $reason = '', bool $notify_poster = true): array
{
    $stmt = $db->prepare("SELECT * FROM buyer_price_offers WHERE id = ?");
    $stmt->execute([$offer_id]);
    $o = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$o) {
        return [false, 'Offer not found.'];
    }
    if ($o['status'] !== 'pending') {
        return [false, 'This offer has already been reviewed.'];
    }

    $price = mk($o['price_per_kg']);

    if ($decision === 'approve') {
        $db->beginTransaction();
        try {
            $qty = max(1, (int) $o['quantity']);
            $amount = (float) $o['price_per_kg'] * $qty;

            $ins = $db->prepare("INSERT INTO outside_markets (company, crop, quantity, amount_mk, location, contact, created_at)
                                 VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
            $ins->execute([$o['buyer_name'], $o['crop'], $qty, $amount, $o['location'], $o['contact']]);
            $published_id = (int) $db->lastInsertId();

            $db->prepare('UPDATE outside_markets SET photo_path = ? WHERE id = ?')->execute([$o['photo_path'] ?? null, $published_id]);

            $mp = $db->prepare("INSERT INTO market_prices (crop, price_per_kg, location, source) VALUES (?, ?, ?, 'Buyer Offer')");
            $mp->execute([$o['crop'], $o['price_per_kg'], $o['location']]);

            $upd = $db->prepare("UPDATE buyer_price_offers
                                 SET status = 'approved', rejection_reason = NULL, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, published_id = ?
                                 WHERE id = ? AND status = 'pending'");
            $upd->execute([$admin_id, $published_id, $offer_id]);

            if ($upd->rowCount() !== 1) {
                $db->rollBack();
                return [false, 'This offer has already been reviewed.'];
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return [false, 'Could not approve the offer: ' . $e->getMessage()];
        }

        $text = 'Offer approved and published.';
        if ($notify_poster) {
            $rb = notify_user($db, (int) $o['buyer_id'],
                'AgriPrice: Your offer of ' . $price . '/kg for ' . $o['crop'] . ' was APPROVED and is now live for farmers.');
            $text .= ' Buyer ' . sms_summary($rb);
        }
        $rf = notify_all_farmers($db,
            'AgriPrice: ' . $o['buyer_name'] . ' is buying ' . $o['crop'] . ' at ' . $price . '/kg in ' . $o['location'] . '. Call ' . $o['contact'] . '.');
        $text .= ' Farmers ' . sms_summary($rf);

        return [true, $text];
    }

    $reason = short_text($reason, 80);
    $upd = $db->prepare("UPDATE buyer_price_offers
                         SET status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP
                         WHERE id = ? AND status = 'pending'");
    $upd->execute([$reason, $admin_id, $offer_id]);

    if ($upd->rowCount() !== 1) {
        return [false, 'This offer has already been reviewed.'];
    }

    $text = 'Offer rejected.';
    if ($notify_poster) {
        $msg = 'AgriPrice: Your offer of ' . $price . '/kg for ' . $o['crop'] . ' was REJECTED.';
        if ($reason !== '') {
            $msg .= ' Reason: ' . $reason . '.';
        }
        $msg .= ' You can post a new offer.';
        $text .= ' Buyer ' . sms_summary(notify_user($db, (int) $o['buyer_id'], $msg));
    }
    return [true, $text];
}

/* ---------- admin review: farmer expected prices ---------- */

function review_farmer_price(PDO $db, int $id, int $admin_id, string $decision, string $reason = ''): array
{
    $stmt = $db->prepare("SELECT * FROM farmer_expected_prices WHERE id = ?");
    $stmt->execute([$id]);
    $f = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$f) {
        return [false, 'Submission not found.'];
    }
    if ($f['status'] !== 'pending') {
        return [false, 'This submission has already been reviewed.'];
    }

    $price = mk($f['expected_price']);

    if ($decision === 'approve') {
        $upd = $db->prepare("UPDATE farmer_expected_prices
                             SET status = 'approved', rejection_reason = NULL, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP
                             WHERE id = ? AND status = 'pending'");
        $upd->execute([$admin_id, $id]);
        if ($upd->rowCount() !== 1) {
            return [false, 'This submission has already been reviewed.'];
        }

        $r = notify_user($db, (int) $f['farmer_id'],
            'AgriPrice: Your expected price of ' . $price . '/kg for ' . $f['crop'] . ' was APPROVED and is now visible to buyers.');
        return [true, 'Farmer price approved and published. Farmer ' . sms_summary($r)];
    }

    $reason = short_text($reason, 80);
    $upd = $db->prepare("UPDATE farmer_expected_prices
                         SET status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP
                         WHERE id = ? AND status = 'pending'");
    $upd->execute([$reason, $admin_id, $id]);
    if ($upd->rowCount() !== 1) {
        return [false, 'This submission has already been reviewed.'];
    }

    $msg = 'AgriPrice: Your expected price of ' . $price . '/kg for ' . $f['crop'] . ' was REJECTED.';
    if ($reason !== '') {
        $msg .= ' Reason: ' . $reason . '.';
    }
    $msg .= ' You can post a new price.';
    return [true, 'Farmer price rejected. Farmer ' . sms_summary(notify_user($db, (int) $f['farmer_id'], $msg))];
}
require_once __DIR__ . '/market_plus.php';
