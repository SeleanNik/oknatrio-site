<?php
// Ядро админки: пути, JSON, сессия, CSRF, почта, TOTP (двухфакторный вход), загрузка картинок.
declare(strict_types=1);

if (!defined('A_ROOT')) {
    define('A_ROOT', dirname(__DIR__));            // .../admin
    define('A_DATA', A_ROOT . '/_data');           // закрытые данные (база SQLite, настройки, файлы заявок)
    define('SITE_ROOT', dirname(A_ROOT));          // корень сайта
    define('PUB_DATA', SITE_ROOT . '/data');       // публичные JSON, из которых сайт берёт контент
    define('UPLOADS', SITE_ROOT . '/uploads');     // загруженные картинки
}

const SESSION_TTL = 8 * 3600;                      // автоматический выход через 8 часов
const MAX_FAILS = 5;                               // неудачных входов с одного IP
const FAIL_WINDOW = 900;                           // за 15 минут

// ---------- общие ----------
function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0';
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function base_url(): string
{
    $host = preg_replace('/[^a-z0-9.:-]/i', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return (is_https() ? 'https' : 'http') . '://' . $host . '/admin/';
}

// ---------- JSON-файлы (публикация контента и закрытые настройки) ----------
function jread(string $file, $default = null)
{
    if (!is_file($file)) return $default;
    $d = json_decode((string)file_get_contents($file), true);
    return $d === null ? $default : $d;
}

// Атомарная запись
function jwrite(string $file, $data): bool
{
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return false;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    $tmp = $file . '.tmp' . bin2hex(random_bytes(3));
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    if (!@rename($tmp, $file)) {
        $ok = file_put_contents($file, $json, LOCK_EX) !== false;
        @unlink($tmp);
        return $ok;
    }
    return true;
}

// ---------- сессия и CSRF ----------
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('trio_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/admin/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function csrf(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    $t = (string)($_POST['_csrf'] ?? '');
    if (!hash_equals(csrf(), $t)) {
        if (function_exists('audit')) audit('csrf_fail', (string)($_SERVER['REQUEST_URI'] ?? ''));
        http_response_code(419);
        exit('Сессия устарела. Обновите страницу и повторите.');
    }
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf()) . '">';
}

// ---------- почта ----------
function mail_send(string $to, string $subject, string $body): bool
{
    $host = preg_replace('/[^a-z0-9.-]/i', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $host = preg_replace('/:\d+$/', '', $host) ?: 'localhost';
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=utf-8\r\nFrom: no-reply@$host";
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

// ---------- TOTP (RFC 6238) для входа в два шага ----------
const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function b32_encode(string $bin): string
{
    $bits = '';
    foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 5) as $chunk) $out .= B32[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}

function b32_decode(string $s): string
{
    $bits = '';
    foreach (str_split(strtoupper($s)) as $c) {
        $p = strpos(B32, $c);
        if ($p !== false) $bits .= str_pad(decbin($p), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) $out .= chr(bindec($byte));
    }
    return $out;
}

function totp_secret(): string
{
    return b32_encode(random_bytes(20));
}

function totp_code(string $secret, ?int $time = null): string
{
    $counter = intdiv($time ?? time(), 30);
    $hash = hash_hmac('sha1', pack('N*', 0, $counter), b32_decode($secret), true);
    $off = ord($hash[19]) & 0xF;
    $n = ((ord($hash[$off]) & 0x7F) << 24) | (ord($hash[$off + 1]) << 16) | (ord($hash[$off + 2]) << 8) | ord($hash[$off + 3]);
    return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}

function totp_check(string $secret, string $code): bool
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if (strlen($code) !== 6) return false;
    $t = time();
    foreach ([-30, 0, 30] as $d) {
        if (hash_equals(totp_code($secret, $t + $d), $code)) return true;
    }
    return false;
}

// ---------- загрузка картинок ----------
// $f — элемент $_FILES (name, tmp_name, size, error). Возвращает путь вида /uploads/xxx.jpg или null.
function save_image(array $f, string $prefix = 'img'): ?string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if (($f['size'] ?? 0) <= 0 || $f['size'] > 4 * 1024 * 1024) return null;
    if (!is_uploaded_file($f['tmp_name'])) return null;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if ($ext === null || @getimagesize($f['tmp_name']) === false) return null;
    if (!is_dir(UPLOADS) && !@mkdir(UPLOADS, 0755, true) && !is_dir(UPLOADS)) return null;
    $name = preg_replace('/[^a-z0-9-]/', '', strtolower($prefix)) . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
    return move_uploaded_file($f['tmp_name'], UPLOADS . '/' . $name) ? '/uploads/' . $name : null;
}

// PDF-каталоги: до 100 МБ, проверяется содержимое. Возвращает путь вида /docs/files/xxx.pdf или null.
function save_pdf(array $f, string $prefix = 'catalog'): ?string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if (($f['size'] ?? 0) <= 0 || $f['size'] > 100 * 1024 * 1024) return null;
    if (!is_uploaded_file($f['tmp_name'])) return null;
    if ((new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) !== 'application/pdf') return null;
    $dir = SITE_ROOT . '/docs/files';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return null;
    $name = preg_replace('/[^a-z0-9-]/', '', strtolower($prefix)) . '-' . bin2hex(random_bytes(4)) . '.pdf';
    return move_uploaded_file($f['tmp_name'], $dir . '/' . $name) ? '/docs/files/' . $name : null;
}

// Удаляем только то, что загружено через админку (файлы, поставляемые вместе с сайтом, не трогаем)
function delete_managed_file(?string $path): void
{
    if (!$path) return;
    if (preg_match('#^/uploads/[a-z0-9-]+\.(jpg|png|webp)$#', $path) || preg_match('#^/docs/files/[a-z0-9-]+\.pdf$#', $path)) {
        @unlink(SITE_ROOT . $path);
    }
}
