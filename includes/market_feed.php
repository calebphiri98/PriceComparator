<?php

/**
 * Fetch an HTTPS page from a fixed source host.
 * Only redirects to the same trusted provider are followed; TLS stays enabled.
 */
function agri_remote_get(string $url, string $host, int $maxBytes = 2097152, ?string &$error = null): ?string
{
    $error = null;
    $allowedHosts = [$host];
    if (in_array($host, ['www.aceafricatrust.org', 'aceafricatrust.org'], true)) {
        $allowedHosts = ['www.aceafricatrust.org', 'aceafricatrust.org'];
    }

    for ($hop = 0; $hop < 3; $hop++) {
        $urlParts = parse_url($url);
        $requestHost = strtolower((string) ($urlParts['host'] ?? ''));
        if (($urlParts['scheme'] ?? '') !== 'https' || !in_array($requestHost, $allowedHosts, true)
            || isset($urlParts['user']) || isset($urlParts['pass']) || isset($urlParts['port'])) {
            $error = 'Unexpected or unsafe external URL (HTTPS provider-host restriction).';
            return null;
        }

        $body = false;
        $status = 0;
        $redirect = '';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                $error = 'Unable to initialize PHP cURL.';
                return null;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_MAXFILESIZE => $maxBytes,
                CURLOPT_USERAGENT => 'AgriPriceMalawi/2.1 (market news and published-price reader)',
                CURLOPT_HTTPHEADER => ['Accept: text/html, application/rss+xml, application/xml;q=0.9', 'Cache-Control: no-cache'],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_ENCODING => '',
                CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$redirect): int {
                    if (preg_match('/^location:\s*(.+)$/i', trim($line), $match)) {
                        $redirect = trim($match[1]);
                    }
                    return strlen($line);
                },
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $curlError = curl_error($ch);
            $curlCode = curl_errno($ch);
            // Do not call curl_close(): it was deprecated in PHP 8.5.
            if ($body === false) {
                $error = 'cURL ' . $curlCode . ': ' . ($curlError ?: 'request failed');
                return null;
            }
        } else {
            if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
                $error = 'PHP cURL is missing and allow_url_fopen is disabled.';
                return null;
            }
            $context = stream_context_create([
                'http' => [
                    'timeout' => 20,
                    'follow_location' => 0,
                    'ignore_errors' => true,
                    'user_agent' => 'AgriPriceMalawi/2.1',
                    'header' => "Accept: text/html, application/rss+xml, application/xml;q=0.9\r\n",
                ],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $body = @file_get_contents($url, false, $context, 0, $maxBytes + 1);
            $headersName = 'http_response_header';
            $headers = function_exists('http_get_last_response_headers')
                ? (http_get_last_response_headers() ?? [])
                : ($$headersName ?? []);
            foreach ($headers as $line) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $match)) {
                    $status = (int) $match[1];
                } elseif (preg_match('/^location:\s*(.+)$/i', $line, $match)) {
                    $redirect = trim($match[1]);
                }
            }
            if ($body === false) {
                $error = 'PHP HTTPS stream request failed. Check CA certificates, DNS and outbound HTTPS.';
                return null;
            }
        }

        if ($status >= 300 && $status < 400) {
            if ($redirect === '') {
                $error = 'HTTP ' . $status . ' redirect without a Location header.';
                return null;
            }
            if (str_starts_with($redirect, '/') && !str_starts_with($redirect, '//')) {
                $url = 'https://' . $requestHost . $redirect;
            } elseif (str_starts_with($redirect, 'https://')) {
                $url = $redirect;
            } else {
                $error = 'Provider redirected to an unsupported or insecure URL.';
                return null;
            }
            continue;
        }
        if ($status !== 200) {
            $error = 'HTTP ' . $status . ($status === 403 ? ' (provider denied the request)' : ($status === 429 ? ' (provider rate limit)' : ''));
            return null;
        }
        if (!is_string($body) || strlen($body) > $maxBytes) {
            $error = 'Empty or oversized provider response.';
            return null;
        }
        return $body;
    }

    $error = 'Too many redirects from the external provider.';
    return null;
}

function agri_record_sync(PDO $db, string $feed, bool $success, string $message): void
{
    $stmt = $db->prepare("INSERT INTO agri_sync_status(feed, last_attempt, last_success, detail) VALUES (?, CURRENT_TIMESTAMP, CASE WHEN ? THEN CURRENT_TIMESTAMP ELSE NULL END, ?)
        ON CONFLICT(feed) DO UPDATE SET last_attempt=CURRENT_TIMESTAMP, last_success=CASE WHEN ? THEN CURRENT_TIMESTAMP ELSE last_success END, detail=excluded.detail");
    $stmt->execute([$feed, (int) $success, substr($message, 0, 500), (int) $success]);
}

function agri_sync_ace(PDO $db): array
{
    $url = 'https://www.aceafricatrust.org/resources/market-information';
    $fetchError = null;
    $body = agri_remote_get($url, 'www.aceafricatrust.org', 2097152, $fetchError);
    if ($body === null) {
        $detail = 'ACE request failed: ' . ($fetchError ?: 'unknown transport error');
        agri_record_sync($db, 'ace', false, $detail);
        return [false, $detail . '. Cached prices were preserved.'];
    }
    if (!class_exists('DOMDocument')) {
        agri_record_sync($db, 'ace', false, 'PHP DOM extension required.');
        return [false, 'PHP DOM extension is required for the ACE importer.'];
    }
    $document = new DOMDocument();
    $old = libxml_use_internal_errors(true);
    $ok = $document->loadHTML($body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($old);
    if (!$ok) {
        agri_record_sync($db, 'ace', false, 'HTML could not be parsed.');
        return [false, 'ACE returned an unreadable page.'];
    }
    $xpath = new DOMXPath($document);
    $rows = $xpath->query('//table//tr');
    $insert = $db->prepare('INSERT OR IGNORE INTO agri_external_quotes (crop, price_per_kg, location, source_name, source_url, observed_at) VALUES (?, ?, ?, ?, ?, ?)');
    $count = 0;
    $found = 0;
    foreach ($rows as $row) {
        $values = [];
        foreach ($xpath->query('./th|./td', $row) as $cell) {
            $values[] = trim((string) preg_replace('/\s+/u', ' ', $cell->textContent));
        }
        if (count($values) < 4) continue;
        $timestamp = strtotime($values[0]);
        $price = (float) preg_replace('/[^0-9.]/', '', $values[3]);
        $crop = trim($values[1]);
        $market = trim($values[2]);
        if (!$timestamp || $timestamp < strtotime('2016-01-01') || $timestamp > time() + 86400 ||
            !$crop || strlen($crop) > 80 || !$market || strlen($market) > 80 || $price <= 0 || $price > 10000000 ||
            (isset($values[4]) && !preg_match('/\bkg\b/i', $values[4]))) continue;
        $found++;
        $insert->execute([$crop, $price, $market, 'ACE Africa Trust', $url, date('Y-m-d', $timestamp)]);
        $count += $insert->rowCount();
    }
    if (!$found) {
        agri_record_sync($db, 'ace', false, 'No valid dated MWK/kg table rows found. Source markup may have changed.');
        return [false, 'ACE page contained no importable rows. No prices were invented or overwritten.'];
    }
    agri_record_sync($db, 'ace', true, "Read $found dated prices, inserted $count new records.");
    return [true, "ACE: read $found dated quotes, added $count new observations."];
}

function agri_sync_news(PDO $db): array
{
    if (!function_exists('simplexml_load_string')) {
        agri_record_sync($db, 'news', false, 'PHP SimpleXML extension required.');
        return [false, 'PHP SimpleXML extension is required for RSS.'];
    }
    $queries = [
        'national' => 'Malawi agriculture commodity prices markets when:30d',
        'southern' => 'Malawi Blantyre Southern Region farmers agriculture when:30d',
        'central' => 'Malawi Lilongwe Central Region agriculture when:30d',
        'northern' => 'Malawi Mzuzu Northern Region agriculture when:30d',
    ];
    $stmt = $db->prepare('INSERT OR IGNORE INTO agri_news(region, title, publisher, article_url, published_at) VALUES (?, ?, ?, ?, ?)');
    $new = 0;
    $read = 0;
    $connected = 0;
    $failureReasons = [];
    foreach ($queries as $region => $query) {
        $url = 'https://news.google.com/rss/search?q=' . rawurlencode($query) . '&hl=en&gl=MW&ceid=MW:en';
        $fetchError = null;
        $body = agri_remote_get($url, 'news.google.com', 2097152, $fetchError);
        if ($body === null) {
            $fallbackUrl = 'https://news.google.com/rss/search?q=' . rawurlencode($query) . '&hl=en-US&gl=US&ceid=US:en';
            $body = agri_remote_get($fallbackUrl, 'news.google.com', 2097152, $fetchError);
        }
        if ($body === null) {
            $failureReasons[] = $fetchError ?: 'unknown request failure';
            continue;
        }
        $old = libxml_use_internal_errors(true);
        $rss = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($old);
        if ($rss === false || !isset($rss->channel->item)) {
            $failureReasons[] = 'response was not a usable RSS feed';
            continue;
        }
        $connected++;
        $seen = 0;
        foreach ($rss->channel->item as $item) {
            if ($seen++ >= 30) break;
            $title = trim((string) $item->title);
            $link = trim((string) $item->link);
            if (strlen($title) < 8 || strlen($title) > 350 || !str_starts_with($link, 'https://news.google.com/')) continue;
            $publisher = trim((string) ($item->source ?? 'News source'));
            if ($publisher === '') $publisher = 'News source';
            $date = strtotime((string) $item->pubDate);
            if (!$date || $date > time() + 86400 || $date < strtotime('-180 days')) continue;
            $read++;
            $stmt->execute([$region, $title, substr($publisher, 0, 100), $link, date('Y-m-d H:i:s', $date)]);
            $new += $stmt->rowCount();
        }
    }
    if (!$connected) {
        $reason = implode('; ', array_slice(array_unique($failureReasons), 0, 2));
        $detail = 'News RSS failed: ' . ($reason ?: 'no usable response');
        agri_record_sync($db, 'news', false, $detail);
        return [false, $detail . '. Cached articles were preserved.'];
    }
    $db->exec("DELETE FROM agri_news WHERE published_at < datetime('now','-180 days')");
    agri_record_sync($db, 'news', true, "Read $read articles, inserted $new.");
    return [true, "News: read $read articles and added $new new links."];
}
