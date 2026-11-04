<?php
require 'config.php';
require_once __DIR__ . '/includes/features.php';
require_once __DIR__ . '/includes/market_plus.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

$admin_id = (int) $_SESSION['user_id'];
$message = '';
$error = '';
$section = $_GET['section'] ?? 'prices';

// Message saved before a redirect (approve / reject / test SMS)
if (!empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    if (($flash['type'] ?? '') === 'error') {
        $error = (string) ($flash['text'] ?? '');
    } else {
        $message = (string) ($flash['text'] ?? '');
    }
}

if (isset($_POST['update_price']) && csrf_valid()) {
    $id = (int)$_POST['id'];
    $price = (float)$_POST['price'];

    if ($id > 0 && $price > 0) {
        $cropStmt = $db->prepare("SELECT crop, location FROM market_prices WHERE id = ?");
        $cropStmt->execute([$id]);
        $cropRow = $cropStmt->fetch(PDO::FETCH_ASSOC);

        if ($cropRow) {
            $cropName = $cropRow['crop'];
            $location = $cropRow['location'] ?? 'National (Govt)';

            $stmt = $db->prepare("UPDATE market_prices 
                                SET price_per_kg = ?, updated_at = CURRENT_TIMESTAMP 
                                WHERE id = ?");
            $stmt->execute([$price, $id]);

            $historyStmt = $db->prepare("INSERT INTO market_price_history (crop, price_per_kg, location, updated_at)
                                        VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
            $historyStmt->execute([$cropName, $price, $location]);

            header("Location: admin.php?section=prices&ok=1&crop=" . urlencode($cropName) . "&price=" . $price);
            exit;
        }
    }
}

if (isset($_POST['add_admin']) && csrf_valid()) {
    $fullname = trim($_POST['fullname'] ?? '');
    $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($fullname === '' || strlen($phone) !== 10 || $password === '') {
        $message = "Error: All fields are required and phone must be exactly 10 digits.";
        $section = 'admins';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        try {
            $check = $db->prepare("SELECT id FROM users WHERE phone = ?");
            $check->execute([$phone]);
            $existing = $check->fetch();

            if ($existing) {
                $stmt = $db->prepare("UPDATE users SET fullname = ?, password = ?, role = 'admin' WHERE phone = ?");
                $stmt->execute([$fullname, $hashed, $phone]);
            } else {
                $stmt = $db->prepare("INSERT INTO users (fullname, phone, password, role) VALUES (?, ?, ?, 'admin')");
                $stmt->execute([$fullname, $phone, $hashed]);
            }

            header("Location: admin.php?section=admins&added=1");
            exit;
        } catch (PDOException $e) {
            $message = "Error: Database issue creating admin account.";
            $section = 'admins';
        }
    }
}

// ---------- Moderate farmer price discussions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['moderate_comment'])) {
    $decision = (string) ($_POST['decision'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $ok = false;
    if (csrf_valid() && $id > 0 && in_array($decision, ['approve', 'reject'], true)) {
        $stmt = $db->prepare("UPDATE price_comments SET status = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'pending'");
        $stmt->execute([$decision === 'approve' ? 'approved' : 'rejected', $admin_id, $id]);
        $ok = $stmt->rowCount() === 1;
    }
    $_SESSION['flash'] = ['type' => $ok ? 'success' : 'error', 'text' => $ok ? 'Price comment reviewed.' : 'Comment could not be reviewed. Please refresh.'];
    header('Location: admin.php?section=comments');
    exit;
}

// ---------- Review submissions + test SMS ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['review_buyer']) || isset($_POST['review_farmer']) || isset($_POST['send_test_sms']))) {

    $target = 'review';
    $flash_type = 'success';
    $flash_text = '';

    if (!csrf_valid()) {
        $flash_type = 'error';
        $flash_text = 'Your session expired. Please try again.';
    } elseif (isset($_POST['send_test_sms'])) {
        $target = 'sms';
        $test_phone = preg_replace('/\D/', '', (string) ($_POST['test_phone'] ?? ''));
        if (strlen($test_phone) !== 10) {
            $flash_type = 'error';
            $flash_text = 'Enter a 10-digit phone number for the test SMS.';
        } else {
            $r = sms_send_many($db, [['phone' => $test_phone, 'user_id' => null]], 'AgriPrice test: SMS notifications are working.');
            $flash_type = $r['sent'] > 0 ? 'success' : 'error';
            $flash_text = 'Test SMS - ' . sms_summary($r);
        }
    } else {
        $decision = (($_POST['decision'] ?? '') === 'approve') ? 'approve' : 'reject';
        $reason = (string) ($_POST['reason'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);

        if (isset($_POST['review_buyer'])) {
            [$ok, $text] = review_buyer_offer($db, $id, $admin_id, $decision, $reason);
        } else {
            [$ok, $text] = review_farmer_price($db, $id, $admin_id, $decision, $reason);
        }
        $flash_type = $ok ? 'success' : 'error';
        $flash_text = $text;
    }

    $_SESSION['flash'] = ['type' => $flash_type, 'text' => $flash_text];
    header("Location: admin.php?section=" . $target);
    exit;
}

if (isset($_GET['ok']) && $_GET['ok'] == 1) {
    $crop = htmlspecialchars($_GET['crop'] ?? 'Crop');
    $price = number_format((float)($_GET['price'] ?? 0), 0);
    $message = "$crop has been updated to MK $price. It is now visible on Current Market Prices.";
    $section = 'prices';
}

if (isset($_GET['added']) && $_GET['added'] == 1) {
    $message = "Admin account saved successfully and ready for login.";
    $section = 'admins';
}

$prices = $db->query("SELECT * FROM market_prices ORDER BY crop")->fetchAll(PDO::FETCH_ASSOC);
$contracts = $db->query("SELECT * FROM contracts ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$admin_users = $db->query("SELECT id, fullname, phone, role, created_at FROM users WHERE role = 'admin' ORDER BY fullname")->fetchAll(PDO::FETCH_ASSOC);

$pending_buyer_count = (int) $db->query("SELECT COUNT(*) FROM buyer_price_offers WHERE status = 'pending'")->fetchColumn();
$pending_farmer_count = (int) $db->query("SELECT COUNT(*) FROM farmer_expected_prices WHERE status = 'pending'")->fetchColumn();
$pending_total = $pending_buyer_count + $pending_farmer_count;

$pending_buyer = [];
$pending_farmer = [];
$recent_reviews = [];
$sms_logs = [];
$pending_comments = [];
$sync_status = [];

if ($section == 'review') {
    $pending_buyer = $db->query("SELECT * FROM buyer_price_offers WHERE status = 'pending' ORDER BY created_at")->fetchAll(PDO::FETCH_ASSOC);
    $pending_farmer = $db->query("SELECT f.*, u.fullname, u.phone
                                  FROM farmer_expected_prices f
                                  JOIN users u ON u.id = f.farmer_id
                                  WHERE f.status = 'pending'
                                  ORDER BY f.created_at")->fetchAll(PDO::FETCH_ASSOC);
    $recent_reviews = $db->query("
        SELECT 'Buyer price' AS kind, buyer_name AS who, crop, price_per_kg AS price, status, reviewed_at
        FROM buyer_price_offers WHERE status IN ('approved', 'rejected')
        UNION ALL
        SELECT 'Farmer price', (SELECT fullname FROM users WHERE id = farmer_id), crop, expected_price, status, reviewed_at
        FROM farmer_expected_prices WHERE status IN ('approved', 'rejected')
        ORDER BY reviewed_at DESC LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
}

if ($section == 'comments') {
    $pending_comments = $db->query("SELECT * FROM price_comments WHERE status = 'pending' ORDER BY created_at ASC LIMIT 150")->fetchAll(PDO::FETCH_ASSOC);
}
if ($section == 'feeds') {
    $sync_status = $db->query('SELECT * FROM agri_sync_status ORDER BY feed')->fetchAll(PDO::FETCH_ASSOC);
}

if ($section == 'sms') {
    $sms_logs = $db->query("SELECT * FROM sms_log ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = "Admin Panel";
$hide_main_nav = true;
require 'includes/header.php';
echo pill_styles();
?>

<div class="page-layout">

    <div class="side-menu">
        <div class="side-header">Quick Menu</div>
        <a href="index.php">Admin Dashboard</a>
        <a href="admin.php?section=review" class="<?= $section == 'review' ? 'active' : '' ?>">Review Submissions<?php if ($pending_total > 0): ?> <span class="pill pill-pending"><?= $pending_total ?></span><?php endif; ?></a>
        <a href="admin.php?section=prices" class="<?= $section == 'prices' ? 'active' : '' ?>">Manage Prices</a>
        <a href="admin.php?section=comments" class="<?= $section == 'comments' ? 'active' : '' ?>">Moderate Farmer Comments</a>
        <a href="admin.php?section=feeds" class="<?= $section == 'feeds' ? 'active' : '' ?>">External Prices & News</a>
        <a href="price-reports.php">Price Reports</a>
        <a href="admin.php?section=contracts" class="<?= $section == 'contracts' ? 'active' : '' ?>">View All Contracts</a>
        <a href="admin.php?section=admins" class="<?= $section == 'admins' ? 'active' : '' ?>">Manage Admins</a>
        <a href="market-performance.php">Crop Performance</a>
        <a href="admin.php?section=sms" class="<?= $section == 'sms' ? 'active' : '' ?>">SMS Log</a>
        <a href="buyer-add-price.php">Post Price Offer</a>
        <a href="outside-markets.php">View Buyer Offers</a>
        <a href="farmer-prices.php">Farmer Prices</a>
        <a href="market-prices.php">Current Market Prices</a>
        <a href="contracts.php">Browse Contracts</a>
        <a href="trending.php">Trending Crops</a>
        <a href="logout.php" style="color:#c62828;">Logout</a>
    </div>

    <div class="main-content">

        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($section == 'prices'): ?>
            <h2>Manage Prices</h2>
            <table>
                <thead>
                    <tr>
                        <th>Crop</th>
                        <th>Price (MK/kg)</th>
                        <th>Location</th>
                        <th>Last Updated</th>
                        <th>Update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prices as $p): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($p['crop']) ?></strong></td>
                        <td class="price">MK <?= number_format($p['price_per_kg'], 0) ?></td>
                        <td><?= htmlspecialchars($p['location'] ?? 'National (Govt)') ?></td>
                        <td><?= !empty($p['updated_at']) ? date('d M Y, H:i', strtotime($p['updated_at'])) : 'N/A' ?></td>
                        <td>
                            <form method="POST" style="display:flex; gap:8px; align-items:center;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <input type="number" name="price" value="<?= $p['price_per_kg'] ?>" 
                                       style="width:90px; padding:6px;" required min="1">
                                <button type="submit" name="update_price" class="btn" style="padding:6px 12px;">Update</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php elseif ($section == 'review'): ?>
            <h2>Review Submissions</h2>

            <div class="review-card">
                <h3>Buyer price offers waiting for approval (<?= count($pending_buyer) ?>)</h3>
                <p style="color:#555; font-size:.9rem;">Approving publishes the offer, texts the buyer, and texts all farmers. Rejecting texts the buyer with your reason.</p>
                <table>
                    <thead>
                        <tr>
                            <th>Buyer</th>
                            <th>Crop</th>
                            <th>Price (MK/kg)</th>
                            <th>Quantity</th>
                            <th>Location</th>
                            <th>Contact</th>
                            <th>Posted</th>
                            <th>Decision</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_buyer)): ?>
                            <tr><td colspan="8">No buyer offers are waiting.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pending_buyer as $o): ?>
                            <tr>
                                <td><strong><?= esc($o['buyer_name']) ?></strong></td>
                                <td><?= esc($o['crop']) ?><br><img class="agri-review-thumb" loading="lazy" src="<?= esc(agri_crop_image($o['crop'], $o['photo_path'] ?? null)) ?>" alt="<?= esc($o['crop']) ?>"></td>
                                <td class="price"><?= esc(mk($o['price_per_kg'])) ?></td>
                                <td><?= esc(number_format($o['quantity'])) ?> kg</td>
                                <td><?= esc($o['location']) ?></td>
                                <td><?= esc($o['contact']) ?></td>
                                <td><?= esc(date('d M Y H:i', strtotime($o['created_at']))) ?></td>
                                <td>
                                    <form method="POST" style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="review_buyer" value="1">
                                        <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                        <input type="text" name="reason" placeholder="Reason (if rejecting)" maxlength="80" style="width:150px; padding:6px;">
                                        <button type="submit" name="decision" value="approve" class="btn" style="padding:6px 12px;">Approve</button>
                                        <button type="submit" name="decision" value="reject" class="btn btn-danger" style="padding:6px 12px;">Reject</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="review-card">
                <h3>Farmer expected prices waiting for approval (<?= count($pending_farmer) ?>)</h3>
                <p style="color:#555; font-size:.9rem;">Approving shows the price on Market Prices and Farmer Prices and texts the farmer. Rejecting texts the farmer with your reason.</p>
                <table>
                    <thead>
                        <tr>
                            <th>Farmer</th>
                            <th>Phone</th>
                            <th>Crop</th>
                            <th>Expected price (MK/kg)</th>
                            <th>Quantity</th>
                            <th>Location</th>
                            <th>Posted</th>
                            <th>Decision</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_farmer)): ?>
                            <tr><td colspan="8">No farmer prices are waiting.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pending_farmer as $f): ?>
                            <tr>
                                <td><strong><?= esc($f['fullname']) ?></strong></td>
                                <td><?= esc($f['phone']) ?></td>
                                <td><?= esc($f['crop']) ?><br><img class="agri-review-thumb" loading="lazy" src="<?= esc(agri_crop_image($f['crop'], $f['photo_path'] ?? null)) ?>" alt="<?= esc($f['crop']) ?>"></td>
                                <td class="price"><?= esc(mk($f['expected_price'])) ?></td>
                                <td><?= $f['quantity'] ? esc(number_format($f['quantity'])) . ' kg' : '-' ?></td>
                                <td><?= esc($f['location']) ?></td>
                                <td><?= esc(date('d M Y H:i', strtotime($f['created_at']))) ?></td>
                                <td>
                                    <form method="POST" style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="review_farmer" value="1">
                                        <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                                        <input type="text" name="reason" placeholder="Reason (if rejecting)" maxlength="80" style="width:150px; padding:6px;">
                                        <button type="submit" name="decision" value="approve" class="btn" style="padding:6px 12px;">Approve</button>
                                        <button type="submit" name="decision" value="reject" class="btn btn-danger" style="padding:6px 12px;">Reject</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="review-card">
                <h3>Recently reviewed</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>From</th>
                            <th>Crop</th>
                            <th>Price (MK/kg)</th>
                            <th>Result</th>
                            <th>When</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_reviews)): ?>
                            <tr><td colspan="6">Nothing reviewed yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent_reviews as $r): ?>
                            <tr>
                                <td><?= esc($r['kind']) ?></td>
                                <td><?= esc($r['who']) ?></td>
                                <td><?= esc($r['crop']) ?></td>
                                <td><?= esc(mk($r['price'])) ?></td>
                                <td><?= status_pill($r['status']) ?></td>
                                <td><?= $r['reviewed_at'] ? esc(date('d M Y H:i', strtotime($r['reviewed_at']))) : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($section == 'comments'): ?>
            <h2>Moderate Farmer Price Comments</h2>
            <p class="agri-muted">Comments are not visible to the public until you approve them. Review claims, personal details and abusive language before publication.</p>
            <?php if (!$pending_comments): ?><div class="agri-empty"><h3>All caught up</h3><p>No comments awaiting review.</p></div><?php endif; ?>
            <?php foreach ($pending_comments as $comment): ?>
            <div class="agri-admin-review">
                <strong><?= esc($comment['crop']) ?> · <?= esc($comment['author_name']) ?></strong>
                <p><?= esc($comment['reason']) ?> · <?= esc($comment['location']) ?> · <?= esc(agri_datetime($comment['created_at'])) ?></p>
                <p class="agri-body-copy"><?= nl2br(esc($comment['body'])) ?></p>
                <?php if ($comment['suggested_price']): ?><p>Suggested price: <?= esc(agri_currency($comment['suggested_price'])) ?> per kg</p><?php endif; ?>
                <a href="price-comments.php?type=<?= esc($comment['price_type']) ?>&amp;id=<?= (int)$comment['price_id'] ?>">View price listing</a>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="moderate_comment" value="1"><input type="hidden" name="id" value="<?= (int)$comment['id'] ?>">
                    <button type="submit" name="decision" value="approve">Approve comment</button>
                    <button type="submit" name="decision" value="reject">Reject comment</button>
                </form>
            </div>
            <?php endforeach; ?>

        <?php elseif ($section == 'feeds'): ?>
            <h2>External Prices & News</h2>
            <p class="agri-muted">Import dated reference quotations from ACE Africa Trust and agricultural headlines from Google News RSS. Imports retain existing records and require the site's server to have internet access. External prices are not live exchange quotes.</p>
            <form method="POST" action="sync-market.php" class="agri-admin-review">
                <?= csrf_field() ?>
                <button class="btn" type="submit">Refresh market prices and news now</button>
                <p class="agri-muted">Admin refresh is rate-limited to 15 minutes. For automatic updates, schedule <code>php sync-market.php</code> in a trusted server cron job, for example every 6 hours.</p>
            </form>
            <?php if (!$sync_status): ?><div class="agri-empty"><h3>Not synced yet</h3><p>Press Refresh to attempt the first import. Source availability is not guaranteed.</p></div><?php endif; ?>
            <?php foreach ($sync_status as $status): ?>
            <div class="agri-admin-review"><strong><?= esc(strtoupper($status['feed'])) ?> source</strong><p>Last attempt: <?= esc(agri_datetime($status['last_attempt'])) ?> · Last success: <?= esc(agri_datetime($status['last_success'])) ?></p><p><?= esc($status['detail']) ?></p></div>
            <?php endforeach; ?>
            <p><a href="market-news.php">View news feed</a> · <a href="market-prices.php?area=all&amp;source=external">Browse external prices</a> · <a href="https://www.aceafricatrust.org/resources/market-information" target="_blank" rel="noopener noreferrer">ACE original site</a></p>

        <?php elseif ($section == 'sms'): ?>
            <h2>SMS Notifications (TextBee)</h2>

            <div class="review-card">
                <p>
                    Status:
                    <?php if (sms_is_configured()): ?>
                        <span class="pill pill-approved">TextBee configured</span>
                    <?php else: ?>
                        <span class="pill pill-rejected">Not configured</span>
                        Set <code>TEXTBEE_API_KEY</code> (optionally <code>TEXTBEE_DEVICE_ID</code>), then restart the server.
                    <?php endif; ?>
                </p>
                <form method="POST" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="send_test_sms" value="1">
                    <input type="text" name="test_phone" required maxlength="10" pattern="[0-9]{10}" placeholder="0888123456" style="width:160px; padding:8px;">
                    <button type="submit" class="btn" style="padding:8px 14px;">Send test SMS</button>
                </form>
            </div>

            <h3>Last 50 messages</h3>
            <table>
                <thead>
                    <tr>
                        <th>When</th>
                        <th>To</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Response</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sms_logs)): ?>
                        <tr><td colspan="5">No SMS messages yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($sms_logs as $l): ?>
                        <tr>
                            <td><?= esc(date('d M H:i', strtotime($l['created_at']))) ?></td>
                            <td><?= esc($l['phone']) ?></td>
                            <td><?= esc($l['message']) ?></td>
                            <td>
                                <?php if ($l['status'] === 'sent'): ?>
                                    <span class="pill pill-approved">API accepted (not confirmed delivered)</span>
                                <?php elseif ($l['status'] === 'failed'): ?>
                                    <span class="pill pill-rejected">Failed</span>
                                <?php else: ?>
                                    <span class="pill pill-pending">Skipped</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:.8rem;"><?= esc($l['response']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

        <?php elseif ($section == 'contracts'): ?>
            <h2>View All Contracts</h2>
            <table>
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Crop</th>
                        <th>Quantity</th>
                        <th>Amount (MK)</th>
                        <th>Deadline</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contracts)): ?>
                        <tr><td colspan="6">No contracts found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($contracts as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['company']) ?></td>
                            <td><?= htmlspecialchars($c['crop']) ?></td>
                            <td><?= number_format($c['quantity']) ?> kg</td>
                            <td>MK <?= number_format($c['amount_mk'], 0) ?></td>
                            <td><?= htmlspecialchars($c['deadline']) ?></td>
                            <td><?= ucfirst($c['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

        <?php elseif ($section == 'admins'): ?>
            <h2>Manage Admins</h2>
            
            <div class="form-box" style="margin-bottom:30px; background:#fff; padding:20px; border-radius:8px;">
                <h3>Add or Update Admin Account</h3>
                <form method="POST">
                    <?= csrf_field() ?>
                    <label>Full Name</label>
                    <input type="text" name="fullname" required style="width:100%; padding:8px; margin-bottom:10px;">

                    <label>Phone Number (10 digits)</label>
                    <input type="text" name="phone" required maxlength="10" pattern="[0-9]{10}" placeholder="0888123456" style="width:100%; padding:8px; margin-bottom:10px;">

                    <label>Password</label>
                    <input type="password" name="password" required style="width:100%; padding:8px; margin-bottom:15px;">

                    <button type="submit" name="add_admin" class="btn">Save Admin Account</button>
                </form>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admin_users as $adm): ?>
                    <tr>
                        <td><?= htmlspecialchars($adm['fullname']) ?></td>
                        <td><?= htmlspecialchars($adm['phone']) ?></td>
                        <td><?= ucfirst(htmlspecialchars($adm['role'])) ?></td>
                        <td><?= htmlspecialchars($adm['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>
</div>

<?php require 'includes/footer.php'; ?>

