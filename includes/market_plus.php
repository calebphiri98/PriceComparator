<?php

function agri_column_exists(PDO $db, string $table, string $column): bool
{
    $allowed = ['farmer_expected_prices', 'buyer_price_offers', 'outside_markets', 'contracts'];
    if (!in_array($table, $allowed, true)) {
        return false;
    }
    $cols = $db->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        if ($col['name'] === $column) {
            return true;
        }
    }
    return false;
}

function agri_upgrade(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $db->exec("CREATE TABLE IF NOT EXISTS price_comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        price_type TEXT NOT NULL CHECK (price_type IN ('local','buyer','farmer','external')),
        price_id INTEGER NOT NULL,
        author_id INTEGER NOT NULL,
        author_name TEXT NOT NULL,
        crop TEXT NOT NULL,
        location TEXT NOT NULL,
        reason TEXT NOT NULL,
        body TEXT NOT NULL,
        suggested_price REAL,
        status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected')),
        reviewed_by INTEGER,
        reviewed_at DATETIME,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    CREATE INDEX IF NOT EXISTS idx_price_comments_listing ON price_comments (price_type, price_id, status);
    CREATE INDEX IF NOT EXISTS idx_price_comments_review ON price_comments (status, created_at);
    CREATE TABLE IF NOT EXISTS agri_news (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        region TEXT NOT NULL,
        title TEXT NOT NULL,
        publisher TEXT NOT NULL,
        article_url TEXT NOT NULL UNIQUE,
        published_at DATETIME,
        fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    CREATE INDEX IF NOT EXISTS idx_agri_news_region ON agri_news (region, published_at);
    CREATE TABLE IF NOT EXISTS agri_external_quotes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        crop TEXT NOT NULL,
        price_per_kg REAL NOT NULL,
        location TEXT NOT NULL,
        source_name TEXT NOT NULL,
        source_url TEXT NOT NULL,
        observed_at TEXT NOT NULL,
        fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(crop, location, source_name, observed_at)
    );
    CREATE INDEX IF NOT EXISTS idx_external_region ON agri_external_quotes (location, observed_at);
    CREATE TABLE IF NOT EXISTS agri_sync_status (
        feed TEXT PRIMARY KEY,
        last_attempt DATETIME,
        last_success DATETIME,
        detail TEXT
    );");
    foreach (['farmer_expected_prices', 'buyer_price_offers', 'outside_markets', 'contracts'] as $table) {
        if (!agri_column_exists($db, $table, 'photo_path')) {
            try {
                $db->exec('ALTER TABLE ' . $table . ' ADD COLUMN photo_path TEXT');
            } catch (PDOException $e) {
                if (!agri_column_exists($db, $table, 'photo_path')) {
                    throw $e;
                }
            }
        }
    }
    // AgriPrice Render schema compatibility
    $marketColumns = $db->query("PRAGMA table_info(market_prices)")
        ->fetchAll(PDO::FETCH_ASSOC);

    if (!$marketColumns) {
        throw new RuntimeException("market_prices table is missing");
    }

    $columnNames = array_column($marketColumns, 'name');

    if (!in_array('source', $columnNames, true)) {
        $db->exec("ALTER TABLE market_prices ADD COLUMN source TEXT NOT NULL DEFAULT 'Local market'");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS online_market_prices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            crop TEXT NOT NULL,
            price_per_kg REAL NOT NULL,
            location TEXT NOT NULL,
            source_name TEXT NOT NULL,
            source_url TEXT,
            currency TEXT DEFAULT 'MWK',
            unit TEXT DEFAULT 'kg',
            observed_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $done = true;
}

function agri_catalog(): array
{
    return [
        'Maize', 'Rice', 'Beans', 'Soybeans', 'Groundnuts', 'Pigeon Peas', 'Cowpeas',
        'Sunflower', 'Cassava', 'Sweet Potatoes', 'Irish Potatoes', 'Tomatoes',
        'Onions', 'Cabbage', 'Bananas', 'Tobacco', 'Cotton', 'Sorghum', 'Millet',
        'Chillies', 'Paprika', 'Sugarcane', 'Wheat', 'Peas', 'Pumpkins', 'Mangoes',
        'Avocados', 'Oranges', 'Sesame', 'Okra', 'Eggplant'
    ];
}

function agri_crop_slug(string $crop): string
{
    $name = strtolower(trim($crop));
    $name = in_array($name, ['soya beans', 'soya', 'soybean'], true) ? 'soybeans' : $name;
    $aliases = ['tomato' => 'tomatoes', 'onion' => 'onions', 'banana' => 'bananas',
        'groundnut' => 'groundnuts', 'soy bean' => 'soybeans', 'sweet potato' => 'sweet-potatoes',
        'potato' => 'irish-potatoes', 'pea' => 'peas', 'pumpkin' => 'pumpkins', 'chilli' => 'chillies',
        'chili' => 'chillies', 'mango' => 'mangoes', 'orange' => 'oranges', 'avocado' => 'avocados',
        'pigeon pea' => 'pigeon-peas', 'cowpea' => 'cowpeas'];
    $name = $aliases[$name] ?? $name;
    $name = str_replace(['white maize', 'yellow maize'], 'maize', $name);
    $name = str_replace(['sugar beans', 'kidney beans'], 'beans', $name);
    $name = preg_replace('/\s+/', '-', $name);
    $known = ['maize', 'rice', 'beans', 'soybeans', 'groundnuts', 'pigeon-peas', 'cowpeas',
        'sunflower', 'cassava', 'sweet-potatoes', 'irish-potatoes', 'tomatoes', 'onions',
        'cabbage', 'bananas', 'tobacco', 'cotton', 'sorghum', 'millet', 'chillies',
        'paprika', 'sugarcane', 'wheat', 'peas', 'pumpkins', 'mangoes', 'avocados',
        'oranges', 'sesame', 'okra', 'eggplant'];
    foreach ($known as $item) {
        if ($name === $item || str_starts_with($name, $item . '-')) {
            return $item;
        }
    }
    return 'other';
}

function agri_crop_image(string $crop, ?string $photo = null): string
{
    if ($photo && preg_match('~^uploads/produce/[a-f0-9]{32}\.(jpg|png|webp)$~', $photo)) {
        return $photo;
    }
    return 'assets/crops/' . agri_crop_slug($crop) . '.svg';
}

function agri_upload_photo(array $file): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($error !== UPLOAD_ERR_OK || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return [null, 'Photo upload failed. Please choose a JPG, PNG or WEBP image.'];
    }
    if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > 4 * 1024 * 1024) {
        return [null, 'Photo must be 4 MB or smaller.'];
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || $info[0] > 6000 || $info[1] > 6000 || $info[0] < 80 || $info[1] < 80) {
        return [null, 'Please upload a valid image between 80 and 6000 pixels wide and high.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime]) || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return [null, 'Only JPG, PNG and WEBP photographs are accepted.'];
    }
    $path = dirname(__DIR__) . '/uploads/produce';
    if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
        return [null, 'The image storage folder is unavailable.'];
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $path . '/' . $filename)) {
        return [null, 'Could not save the photo. Check server folder permissions.'];
    }
    @chmod($path . '/' . $filename, 0644);
    return ['uploads/produce/' . $filename, null];
}

function agri_allowed_area($area): string
{
    $allowed = ['lunzu', 'blantyre', 'southern', 'central', 'northern', 'all'];
    return is_string($area) && in_array($area, $allowed, true) ? $area : 'lunzu';
}

function agri_area_label(string $area): string
{
    return ['lunzu' => 'Lunzu & nearby', 'blantyre' => 'Blantyre district',
        'southern' => 'Southern Region', 'central' => 'Central Region',
        'northern' => 'Northern Region', 'all' => 'All Malawi'][$area] ?? 'Lunzu & nearby';
}

function agri_location_match(string $location, string $area, string $place = ''): bool
{
    $location = strtolower(trim($location));
    if ($place !== '') {
        return stripos($location, $place) !== false;
    }
    if ($area === 'all') {
        return true;
    }
    $southern = ['blantyre','lunzu','limbe','machinjiri','chilomoni','soche','nancholi','bangwe',
        'chigumula','bvumbwe','thyolo','zomba','chiradzulu','mulanje','phalombe','machinga',
        'mangochi','balaka','nsanje','chikwawa','mwanza','neno'];
    $central = ['lilongwe','dedza','dowa','kasungu','mchinji','salima','nkhotakota','ntchisi','ntcheu'];
    $northern = ['mzuzu','karonga','chitipa','rumphi','m zimba','mzimba','nkhata bay','likoma'];
    $blantyre = ['blantyre','lunzu','limbe','machinjiri','chilomoni','soche','nancholi','bangwe','chigumula'];
    $lunzu = ['lunzu','blantyre','limbe','chigumula','bvumbwe','machinjiri'];
    $dictionary = ['lunzu' => $lunzu, 'blantyre' => $blantyre,
        'southern' => $southern, 'central' => $central, 'northern' => $northern];
    foreach ($dictionary[$area] ?? $lunzu as $term) {
        if (str_contains($location, $term)) {
            return true;
        }
    }
    return false;
}

function agri_listing(PDO $db, string $type, int $id): ?array
{
    if ($id < 1) {
        return null;
    }
    $queries = [
        'local' => "SELECT id, crop, price_per_kg AS price, location, 'Local price' AS source FROM market_prices WHERE id = ? AND source <> 'Buyer Offer'",
        'buyer' => "SELECT id, crop, ROUND(amount_mk / NULLIF(quantity, 0), 2) AS price, location, company AS source FROM outside_markets WHERE id = ? AND quantity > 0",
        'farmer' => "SELECT id, crop, expected_price AS price, location, 'Farmer expected price' AS source FROM farmer_expected_prices WHERE id = ? AND status = 'approved'",
        'external' => "SELECT id, crop, price_per_kg AS price, location, source_name AS source FROM agri_external_quotes WHERE id = ?"
    ];
    if (!isset($queries[$type])) {
        return null;
    }
    $stmt = $db->prepare($queries[$type]);
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function agri_query_prices(PDO $db): array
{
    $result = [];
    foreach ($db->query("SELECT id, crop, price_per_kg, location, source, updated_at AS observed_at, NULL AS photo_path, NULL AS source_url, 'local' AS listing_type FROM market_prices WHERE source <> 'Buyer Offer'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $result[] = $r;
    }
    foreach ($db->query("SELECT id, crop, amount_mk / NULLIF(quantity, 0) AS price_per_kg, location, 'Buyer offer' AS source, created_at AS observed_at, photo_path, NULL AS source_url, 'buyer' AS listing_type FROM outside_markets WHERE quantity > 0")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $result[] = $r;
    }
    foreach ($db->query("SELECT id, crop, expected_price AS price_per_kg, location, 'Farmer expected' AS source, COALESCE(reviewed_at, created_at) AS observed_at, photo_path, NULL AS source_url, 'farmer' AS listing_type FROM farmer_expected_prices WHERE status = 'approved'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $result[] = $r;
    }
    foreach ($db->query("SELECT id, crop, price_per_kg, location, source_name AS source, observed_at, NULL AS photo_path, source_url, 'external' AS listing_type FROM agri_external_quotes")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $result[] = $r;
    }
    $legacyTableExists = $db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'online_market_prices'")->fetchColumn();

    if ($legacyTableExists) {
    foreach ($db->query("SELECT 0 AS id, crop, price_per_kg, location, source_name AS source, observed_at, NULL AS photo_path, source_url, 'legacy' AS listing_type FROM online_market_prices WHERE currency = 'MWK' AND unit = 'kg'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $result[] = $r;
    }
    }
    return $result;
}

function agri_currency($value): string
{
    return 'MK ' . number_format((float) $value, 0);
}

function agri_datetime(?string $date): string
{
    if (!$date || !strtotime($date)) {
        return 'Date unavailable';
    }
    return date('d M Y', strtotime($date));
}

agri_upgrade($db);
