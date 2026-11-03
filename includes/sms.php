<?php

function sms_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $cfg = [
        'api_key'       => (string) getenv('TEXTBEE_API_KEY'),
        'device_id'     => (string) getenv('TEXTBEE_DEVICE_ID'),
        'base_url'      => 'https://api.textbee.dev/api/v1',
        'max_broadcast' => (int) (getenv('SMS_MAX_BROADCAST') ?: 100),
        'country_code'  => '265',
    ];

    $local = __DIR__ . '/sms-config.local.php';
    if (is_file($local)) {
        $override = include $local;
        if (is_array($override)) {
            foreach ($override as $key => $value) {
                if ($value !== '' && $value !== null) {
                    $cfg[$key] = $value;
                }
            }
        }
    }

    return $cfg;
}

function sms_is_configured(): bool
{
    $cfg = sms_config();
    return $cfg['api_key'] !== '' && $cfg['device_id'] !== '';
}

function sms_normalize_phone(string $phone): ?string
{
    $cc = (string) sms_config()['country_code'];
    $digits = preg_replace('/\D/', '', $phone);

    if ($digits === '' || $digits === null) {
        return null;
    }
    if (strpos($digits, $cc) === 0 && strlen($digits) === strlen($cc) + 9) {
        return '+' . $digits;
    }
    if (strlen($digits) === 10 && $digits[0] === '0') {
        return '+' . $cc . substr($digits, 1);
    }
    if (strlen($digits) === 9) {
        return '+' . $cc . $digits;
    }
    return null;
}

function sms_log(?PDO $db, ?int $user_id, string $phone, string $message, string $status, string $response = ''): void
{
    if (!$db) {
        return;
    }
    try {
        $stmt = $db->prepare("INSERT INTO sms_log (user_id, phone, message, status, response) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $phone, $message, $status, substr($response, 0, 500)]);
    } catch (Throwable $e) {
    }
}

function sms_fetch_modern(string $url, $context): array
{
    $body = @file_get_contents($url, false, $context);
    $headers = http_get_last_response_headers();
    return [$body, is_array($headers) ? $headers : []];
}

function sms_fetch_legacy(string $url, $context): array
{
    $body = @file_get_contents($url, false, $context);
    $headerVariable = 'http_response_header';
    $headers = $$headerVariable ?? [];
    $headers = is_array($headers) ? $headers : [];
    return [$body, $headers];
}

function sms_http_post(string $url, string $json, string $api_key): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $api_key,
            ],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        return [$status, is_string($body) ? $body : '', $err];
    }

    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nx-api-key: " . $api_key . "\r\n",
            'content'       => $json,
            'timeout'       => 20,
            'ignore_errors' => true,
        ],
    ]);

    if (function_exists('http_get_last_response_headers')) {
        [$body, $headers] = sms_fetch_modern($url, $context);
    } else {
        [$body, $headers] = sms_fetch_legacy($url, $context);
    }

    $status = 0;
    if (isset($headers[0]) && preg_match('#HTTP/\S+\s+(\d{3})#', $headers[0], $m)) {
        $status = (int) $m[1];
    }
    return [$status, is_string($body) ? $body : '', $body === false ? 'Request failed' : ''];
}

function sms_send_many(?PDO $db, array $recipients, string $message): array
{
    $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'detail' => ''];
    $numbers = [];

    foreach ($recipients as $r) {
        $raw = (string) ($r['phone'] ?? '');
        $uid = isset($r['user_id']) ? (int) $r['user_id'] : null;
        $e164 = sms_normalize_phone($raw);
        if ($e164 === null) {
            sms_log($db, $uid, $raw, $message, 'skipped', 'Invalid phone number');
            $result['skipped']++;
            continue;
        }
        $numbers[$e164] = $uid;
    }

    if (!$numbers) {
        return $result;
    }

    if (!sms_is_configured()) {
        foreach ($numbers as $to => $uid) {
            sms_log($db, $uid, (string) $to, $message, 'skipped', 'TextBee is not configured (set TEXTBEE_API_KEY and TEXTBEE_DEVICE_ID)');
            $result['skipped']++;
        }
        $result['detail'] = 'TextBee is not configured.';
        return $result;
    }

    $cfg = sms_config();
    $url = rtrim($cfg['base_url'], '/') . '/gateway/devices/' . rawurlencode($cfg['device_id']) . '/send-sms';

    foreach (array_chunk($numbers, 50, true) as $chunk) {
        $list = array_map('strval', array_keys($chunk));
        $ok = false;
        $response = '';

        try {
            $json = json_encode(
                ['recipients' => $list, 'message' => $message],
                JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE
            );
            [$status, $body, $err] = sms_http_post($url, (string) $json, $cfg['api_key']);
            $ok = $status >= 200 && $status < 300;
            $response = $ok ? 'HTTP ' . $status : trim('HTTP ' . $status . ' ' . $err . ' ' . $body);
        } catch (Throwable $e) {
            $response = 'Exception: ' . $e->getMessage();
        }

        foreach ($chunk as $to => $uid) {
            sms_log($db, $uid, (string) $to, $message, $ok ? 'sent' : 'failed', $response);
        }

        if ($ok) {
            $result['sent'] += count($chunk);
        } else {
            $result['failed'] += count($chunk);
            $result['detail'] = substr($response, 0, 200);
        }
    }

    return $result;
}

function sms_add(array $total, array $r): array
{
    foreach (['sent', 'failed', 'skipped'] as $key) {
        $total[$key] = ($total[$key] ?? 0) + ($r[$key] ?? 0);
    }
    if (!empty($r['detail'])) {
        $total['detail'] = $r['detail'];
    }
    return $total;
}

function sms_summary(array $r): string
{
    $parts = [];
    if (!empty($r['sent']))    { $parts[] = $r['sent'] . ' sent'; }
    if (!empty($r['failed']))  { $parts[] = $r['failed'] . ' failed'; }
    if (!empty($r['skipped'])) { $parts[] = $r['skipped'] . ' skipped'; }

    if (!$parts) {
        return 'SMS: none needed.';
    }

    $text = 'SMS: ' . implode(', ', $parts) . '.';
    if ((!empty($r['failed']) || !empty($r['skipped'])) && !empty($r['detail'])) {
        $text .= ' (' . $r['detail'] . ')';
    }
    return $text;
}