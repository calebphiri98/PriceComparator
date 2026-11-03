<?php
require 'config.php';
require_once __DIR__ . '/includes/features.php';
require_once __DIR__ . '/includes/market_plus.php';

$type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$listing = agri_listing($db, $type, (int) $id);
if (!$listing) {
    http_response_code(404);
    $page_title = 'Price not found';
    require 'includes/header.php';
    echo '<section class="agri-empty"><h1>Price listing not found</h1><p>This listing may have been removed.</p><a class="btn" href="market-prices.php">Browse prices</a></section>';
    require 'includes/footer.php';
    exit;
}
$role = $_SESSION['role'] ?? '';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$canPost = $userId > 0 && $role === 'farmer';
$error = '';
$notice = '';
$reasons = ['inputs' => 'High cost of farm inputs', 'transport' => 'Transport and logistics',
    'weather' => 'Weather or crop losses', 'demand' => 'Demand and negotiation', 'quality' => 'Produce quality',
    'other' => 'Other market concern'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canPost) {
        http_response_code(403);
        $error = 'Only signed-in farmers can contribute to price discussions.';
    } elseif (!csrf_valid()) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        $body = trim((string) ($_POST['body'] ?? ''));
        $reason = (string) ($_POST['reason'] ?? '');
        $suggested = trim((string) ($_POST['suggested_price'] ?? ''));
        $suggestedValue = $suggested === '' ? null : filter_var($suggested, FILTER_VALIDATE_FLOAT);
        if (strlen($body) < 15 || strlen($body) > 800) {
            $error = 'Write a comment between 15 and 800 characters.';
        } elseif (!isset($reasons[$reason])) {
            $error = 'Select why you are commenting on this price.';
        } elseif ($suggested !== '' && ($suggestedValue === false || $suggestedValue <= 0 || $suggestedValue > 1000000)) {
            $error = 'Enter a valid suggested price, or leave it empty.';
        } else {
            $limit = $db->prepare("SELECT COUNT(*) FROM price_comments WHERE author_id = ? AND created_at >= datetime('now', '-1 day')");
            $limit->execute([$userId]);
            if ((int) $limit->fetchColumn() >= 8) {
                $error = 'Daily limit reached (8 comments). Try again tomorrow.';
            } else {
                $insert = $db->prepare('INSERT INTO price_comments (price_type, price_id, author_id, author_name, crop, location, reason, body, suggested_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $insert->execute([$type, $id, $userId, (string) ($_SESSION['fullname'] ?? 'Farmer'),
                    $listing['crop'], $listing['location'], $reason, $body, $suggestedValue]);
                $notice = 'Comment submitted for moderation. It will appear publicly when approved.';
                $_POST = [];
            }
        }
    }
}
$stmt = $db->prepare("SELECT author_name, location, reason, body, suggested_price, created_at FROM price_comments WHERE price_type = ? AND price_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 40");
$stmt->execute([$type, $id]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
$page_title = 'Price discussion: ' . $listing['crop'];
$hide_main_nav = isset($_SESSION['user_id']);
require 'includes/header.php';
?>
<div class="agri-shell">
    <div class="agri-content">
        <p class="agri-eyebrow"><a href="market-prices.php">Market prices</a> / Farmer voices</p>
        <div class="agri-hero small">
            <div><span class="agri-kicker">COMMUNITY PRICE INTELLIGENCE</span>
                <h1><?= esc($listing['crop']) ?> · Price discussion</h1>
                <p>Share the real costs behind a price. Contributions are opinions from farmers, not verified market quotations.</p>
                <div class="agri-stats"><span><?= esc(agri_currency($listing['price'])) ?> / kg</span><span><?= esc($listing['location']) ?></span><span><?= esc($listing['source']) ?></span></div>
            </div>
            <img class="agri-hero-image" src="<?= esc(agri_crop_image($listing['crop'])) ?>" alt="Illustration of <?= esc($listing['crop']) ?>">
        </div>
        <?php if ($error): ?><div class="alert alert-error" role="alert"><?= esc($error) ?></div><?php endif; ?>
        <?php if ($notice): ?><div class="alert alert-success" role="status"><?= esc($notice) ?></div><?php endif; ?>
        <div class="agri-discussion-grid">
            <section class="agri-panel">
                <div class="agri-section-head"><div><span class="agri-kicker">LOCAL PERSPECTIVES</span><h2>What farmers are saying</h2></div><span class="agri-count"><?= count($comments) ?> published</span></div>
                <?php if (!$comments): ?><div class="agri-empty"><h3>Start the conversation</h3><p>No approved comments yet. Farmers can share input costs, transport challenges or a price suggestion.</p></div><?php endif; ?>
                <?php foreach ($comments as $comment): ?>
                    <article class="agri-comment">
                        <div class="agri-comment-top"><strong><?= esc($comment['author_name']) ?></strong><span><?= esc(agri_datetime($comment['created_at'])) ?></span></div>
                        <p class="agri-muted"><?= esc($reasons[$comment['reason']] ?? 'Market perspective') ?> · <?= esc($comment['location']) ?></p>
                        <p class="agri-body-copy"><?= nl2br(esc($comment['body'])) ?></p>
                        <?php if ($comment['suggested_price'] !== null): ?><p class="agri-suggested">Farmer suggested: <?= esc(agri_currency($comment['suggested_price'])) ?> / kg</p><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
            <section class="agri-panel agri-sticky">
                <span class="agri-kicker">ADD YOUR VOICE</span><h2>Comment on this price</h2>
                <?php if ($canPost): ?>
                <p class="agri-muted">Comments are public after admin approval. Do not share private contact details.</p>
                <form method="POST" class="agri-form">
                    <?= csrf_field() ?>
                    <label for="reason">Main reason</label>
                    <select name="reason" id="reason" required>
                        <option value="">Choose a reason</option>
                        <?php foreach ($reasons as $key => $value): ?><option value="<?= esc($key) ?>" <?= ($_POST['reason'] ?? '') === $key ? 'selected' : '' ?>><?= esc($value) ?></option><?php endforeach; ?>
                    </select>
                    <label for="body">Your experience</label>
                    <textarea name="body" id="body" required minlength="15" maxlength="800" rows="6" placeholder="Fertiliser and transport costs have increased, so this price may not cover my expenses..."><?= esc($_POST['body'] ?? '') ?></textarea>
                    <label for="suggested_price">Suggested fair price (MK/kg, optional)</label>
                    <input type="number" name="suggested_price" id="suggested_price" min="1" max="1000000" step="any" value="<?= esc($_POST['suggested_price'] ?? '') ?>">
                    <button class="btn agri-full" type="submit">Submit comment for review</button>
                </form>
                <?php else: ?>
                <p class="agri-muted">Sign in as a farmer to contribute. Everyone can read approved comments.</p>
                <a class="btn agri-full" href="login.php">Farmer login</a>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
<?php require 'includes/footer.php'; ?>
