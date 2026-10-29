<?php
// База данных админки: сотрудники и права, журнал, контент страниц (с версиями), статистика посещений.
// По умолчанию SQLite (файл admin/_data/trio.sqlite, настройка не нужна).
// MySQL: создать admin/_data/db.json {"driver":"mysql","host":"localhost","name":"...","user":"...","pass":"..."}
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

const ROLES = [
    'owner' => 'Владелец (все права)',
    'staff' => 'Сотрудник (права по списку)',
];

// Права, которые владелец выдаёт сотрудникам. Владелец имеет все права всегда.
const PERMS = [
    'analytics.view' => 'Аналитика сайта',
    'page.home' => 'Страница «Главная»',
    'page.alyuminievye' => 'Страница «Алюминиевые окна»',
    'page.pvh' => 'Страница «Окна ПВХ»',
    'page.works' => 'Страница «Наши работы»',
    'page.tech' => 'Страница «Техническая информация»',
    'page.contacts' => 'Страница «Контакты»',
];

function db_driver(): string
{
    $c = jread(A_DATA . '/db.json', []);
    return ($c['driver'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $opt = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
    if (db_driver() === 'mysql') {
        $c = jread(A_DATA . '/db.json', []);
        $dsn = 'mysql:host=' . ($c['host'] ?? 'localhost') . ';dbname=' . ($c['name'] ?? '') . ';charset=utf8mb4';
        $pdo = new PDO($dsn, (string)($c['user'] ?? ''), (string)($c['pass'] ?? ''), $opt);
    } else {
        if (!is_dir(A_DATA)) @mkdir(A_DATA, 0755, true);
        $pdo = new PDO('sqlite:' . A_DATA . '/trio.sqlite', null, null, $opt);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
    }
    db_migrate($pdo);
    return $pdo;
}

function db_migrate(PDO $pdo): void
{
    $my = db_driver() === 'mysql';
    $pk = $my ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $long = $my ? 'MEDIUMTEXT' : 'TEXT';
    $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
    $t = [
        "CREATE TABLE IF NOT EXISTS users (
            id $pk, email VARCHAR(190) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL DEFAULT '',
            role VARCHAR(20) NOT NULL DEFAULT 'staff', perms TEXT NULL, password_hash VARCHAR(255) NULL,
            active TINYINT NOT NULL DEFAULT 1, totp_secret VARCHAR(64) NULL, totp_on TINYINT NOT NULL DEFAULT 0,
            created_at VARCHAR(19) NOT NULL, last_login VARCHAR(19) NULL)$tail",
        "CREATE TABLE IF NOT EXISTS content (
            k VARCHAR(64) NOT NULL PRIMARY KEY, value $long NOT NULL, updated_at VARCHAR(19) NOT NULL, updated_by VARCHAR(190) NULL)$tail",
        "CREATE TABLE IF NOT EXISTS content_versions (
            id $pk, k VARCHAR(64) NOT NULL, value $long NOT NULL, created_at VARCHAR(19) NOT NULL, user_email VARCHAR(190) NULL)$tail",
        "CREATE TABLE IF NOT EXISTS audit (
            id $pk, at VARCHAR(19) NOT NULL, user_email VARCHAR(190) NULL, action VARCHAR(60) NOT NULL, detail TEXT NULL, ip VARCHAR(45) NULL)$tail",
        "CREATE TABLE IF NOT EXISTS attempts (
            id $pk, ip_hash VARCHAR(64) NOT NULL, at INT NOT NULL)$tail",
        "CREATE TABLE IF NOT EXISTS hits (
            id $pk, day VARCHAR(10) NOT NULL, at VARCHAR(19) NOT NULL, path VARCHAR(250) NOT NULL, vid VARCHAR(16) NOT NULL,
            ref VARCHAR(120) NOT NULL DEFAULT '', device VARCHAR(10) NOT NULL DEFAULT 'desktop', ev VARCHAR(10) NOT NULL DEFAULT 'view')$tail",
    ];
    foreach ($t as $sql) $pdo->exec($sql);
    try {
        $pdo->exec($my ? 'CREATE INDEX idx_hits_day ON hits (day)' : 'CREATE INDEX IF NOT EXISTS idx_hits_day ON hits (day)');
    } catch (Throwable $e) {
        // индекс уже есть
    }
}

// ---------- мелкие помощники ----------
function dbq(string $sql, array $p = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st;
}

function dbrow(string $sql, array $p = []): ?array
{
    $r = dbq($sql, $p)->fetch();
    return $r === false ? null : $r;
}

function dbrows(string $sql, array $p = []): array
{
    return dbq($sql, $p)->fetchAll();
}

function dbid(): int
{
    return (int)db()->lastInsertId();
}

// ---------- права ----------
function user_perms(?array $user): array
{
    if (!$user) return [];
    $p = json_decode((string)($user['perms'] ?? ''), true);
    return is_array($p) ? array_values(array_intersect($p, array_keys(PERMS))) : [];
}

function user_can(?array $user, string $perm): bool
{
    if (!$user) return false;
    if (($user['role'] ?? '') === 'owner') return true;
    if ($perm === 'owner') return false;
    return in_array($perm, user_perms($user), true);
}

// ---------- журнал ----------
function audit(string $action, string $detail = '', ?array $user = null): void
{
    try {
        dbq('INSERT INTO audit (at, user_email, action, detail, ip) VALUES (?,?,?,?,?)',
            [now(), $user['email'] ?? null, $action, mb_substr($detail, 0, 1000), client_ip()]);
    } catch (Throwable $e) {
        // журнал не должен ломать работу
    }
}

// ---------- контент (с версиями и публикацией в /data/*.json) ----------
// Ключи: site (контакты) и page_<имя> (страницы). Страницы публикуются одним файлом /data/pages.json.
function content_get(string $k, $default = null)
{
    $row = dbrow('SELECT value FROM content WHERE k = ?', [$k]);
    if ($row) {
        $d = json_decode($row['value'], true);
        return $d === null ? $default : $d;
    }
    // первое обращение: подхватываем уже опубликованный файл (стартовые значения из проекта)
    $d = null;
    if (strpos($k, 'page_') === 0) {
        $all = jread(PUB_DATA . '/pages.json', []);
        $d = $all[substr($k, 5)] ?? null;
    } else {
        $d = jread(PUB_DATA . '/' . $k . '.json', null);
    }
    if ($d !== null) {
        dbq('INSERT INTO content (k, value, updated_at, updated_by) VALUES (?,?,?,?)', [$k, json_encode($d, JSON_UNESCAPED_UNICODE), now(), 'import']);
        return $d;
    }
    return $default;
}

function publish_pages(): bool
{
    $all = jread(PUB_DATA . '/pages.json', []);
    foreach (dbrows("SELECT k, value FROM content WHERE k LIKE 'page!_%' ESCAPE '!'") as $r) {
        $d = json_decode($r['value'], true);
        if ($d !== null) $all[substr($r['k'], 5)] = $d;
    }
    return jwrite(PUB_DATA . '/pages.json', $all);
}

function content_set(string $k, $data, ?array $user): bool
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    $old = dbrow('SELECT value FROM content WHERE k = ?', [$k]);
    if ($old) {
        dbq('INSERT INTO content_versions (k, value, created_at, user_email) VALUES (?,?,?,?)', [$k, $old['value'], now(), $user['email'] ?? null]);
        $ids = array_column(dbrows('SELECT id FROM content_versions WHERE k = ? ORDER BY id DESC', [$k]), 'id');
        foreach (array_slice($ids, 20) as $id) dbq('DELETE FROM content_versions WHERE id = ?', [$id]);
    }
    dbq('REPLACE INTO content (k, value, updated_at, updated_by) VALUES (?,?,?,?)', [$k, $json, now(), $user['email'] ?? null]);
    return strpos($k, 'page_') === 0 ? publish_pages() : jwrite(PUB_DATA . '/' . $k . '.json', $data);
}

function content_versions(string $k): array
{
    return dbrows('SELECT id, created_at, user_email FROM content_versions WHERE k = ? ORDER BY id DESC LIMIT 20', [$k]);
}

function content_restore(string $k, int $versionId, ?array $user): bool
{
    $v = dbrow('SELECT value FROM content_versions WHERE id = ? AND k = ?', [$versionId, $k]);
    if (!$v) return false;
    $d = json_decode($v['value'], true);
    return $d === null ? false : content_set($k, $d, $user);
}

// ---------- сотрудники ----------
function user_by_id(int $id): ?array
{
    return dbrow('SELECT * FROM users WHERE id = ?', [$id]);
}

function user_by_email(string $email): ?array
{
    return dbrow('SELECT * FROM users WHERE LOWER(email) = ?', [mb_strtolower($email)]);
}

function users_count(): int
{
    return (int)dbq('SELECT COUNT(*) FROM users')->fetchColumn();
}

function owners_active_count(): int
{
    return (int)dbq("SELECT COUNT(*) FROM users WHERE role = 'owner' AND active = 1")->fetchColumn();
}

// ---------- попытки входа ----------
function login_throttled(): bool
{
    dbq('DELETE FROM attempts WHERE at < ?', [time() - FAIL_WINDOW]);
    $n = (int)dbq('SELECT COUNT(*) FROM attempts WHERE ip_hash = ? AND at >= ?', [hash('sha256', client_ip()), time() - FAIL_WINDOW])->fetchColumn();
    return $n >= MAX_FAILS;
}

function login_fail(): void
{
    dbq('INSERT INTO attempts (ip_hash, at) VALUES (?,?)', [hash('sha256', client_ip()), time()]);
}

function login_clear(): void
{
    dbq('DELETE FROM attempts WHERE ip_hash = ?', [hash('sha256', client_ip())]);
}
