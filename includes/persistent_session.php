<?php
if (session_status() === PHP_SESSION_ACTIVE) {
    return;
}

$url = getenv('DATABASE_URL');
if (!$url) {
    throw new RuntimeException('DATABASE_URL is required for persistent sessions.');
}
$parts = parse_url($url);
if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['postgres', 'postgresql'], true)) {
    throw new RuntimeException('Persistent sessions require a PostgreSQL connection URL.');
}
$host = $parts['host'] ?? '';
$name = ltrim($parts['path'] ?? '', '/');
$user = rawurldecode($parts['user'] ?? '');
$password = rawurldecode($parts['pass'] ?? '');
if (!$host || !$name || !$user || !$password) {
    throw new RuntimeException('Incomplete PostgreSQL connection URL.');
}
$port = (int) ($parts['port'] ?? 5432);
$sessionDb = new PDO(
    'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';sslmode=require',
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$sessionDb->exec('CREATE TABLE IF NOT EXISTS app_sessions (session_id VARCHAR(128) PRIMARY KEY, payload TEXT NOT NULL, expires_at BIGINT NOT NULL)');
$ttl = 86400 * 7;
ini_set('session.gc_maxlifetime', (string) $ttl);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
session_set_cookie_params([
    'lifetime' => $ttl,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);
$handler = new class($sessionDb, $ttl) implements SessionHandlerInterface {
    private PDO $db;
    private int $ttl;
    public function __construct(PDO $db, int $ttl) {
        $this->db = $db;
        $this->ttl = $ttl;
    }
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }
    public function read(string $id): string|false {
        $s = $this->db->prepare('SELECT payload FROM app_sessions WHERE session_id = ? AND expires_at > ?');
        $s->execute([$id, time()]);
        $value = $s->fetchColumn();
        return $value === false ? '' : (string) $value;
    }
    public function write(string $id, string $data): bool {
        $s = $this->db->prepare('INSERT INTO app_sessions (session_id, payload, expires_at) VALUES (?, ?, ?) ON CONFLICT (session_id) DO UPDATE SET payload = EXCLUDED.payload, expires_at = EXCLUDED.expires_at');
        return $s->execute([$id, $data, time() + $this->ttl]);
    }
    public function destroy(string $id): bool {
        $s = $this->db->prepare('DELETE FROM app_sessions WHERE session_id = ?');
        return $s->execute([$id]);
    }
    public function gc(int $max_lifetime): int|false {
        $s = $this->db->prepare('DELETE FROM app_sessions WHERE expires_at <= ?');
        $s->execute([time()]);
        return $s->rowCount();
    }
};
session_set_save_handler($handler, true);
session_start();
