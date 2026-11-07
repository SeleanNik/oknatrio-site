<?php
// Админка сайта «Трио»: страницы сайта, аналитика, сотрудники и права. Контент публикуется в /data/*.json.
declare(strict_types=1);

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/ui.php';
require __DIR__ . '/inc/stats.php';
require __DIR__ . '/inc/schema.php';
require __DIR__ . '/inc/pages.php';

ini_set('display_errors', '0'); // ошибки пишутся в журнал сервера, а не на экран
header('X-Frame-Options: DENY');
header("Content-Security-Policy: frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'");
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-origin');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');

start_session();

// непойманная ошибка попадает в журнал, а не только на экран
set_exception_handler(function (Throwable $e): void {
    audit('error', get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
    http_response_code(500);
    exit('Ошибка на сервере. Сообщение записано в журнал.');
});

try {
    db();
} catch (Throwable $e) {
    http_response_code(500);
    error_log('admin db: ' . $e->getMessage());
    auth_box('База данных недоступна', '<p>Не удалось открыть базу. Проверьте, что у папки <code>admin/_data</code> есть права на запись (или настройки MySQL в <code>db.json</code>).</p><p class="hint">Подробности записаны в журнал ошибок сервера.</p>');
}

$action = (string)($_POST['action'] ?? '');

function finish_login(array $u): void
{
    session_regenerate_id(true);
    $_SESSION = ['uid' => (int)$u['id'], 't' => time(), 'ua' => hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''), 'csrf' => bin2hex(random_bytes(16))];
    dbq('UPDATE users SET last_login = ? WHERE id = ?', [now(), $u['id']]);
    audit('login', '', $u);
}

function valid_password(string $p): string
{
    return mb_strlen($p) < 10 ? 'Пароль должен быть не короче 10 символов.' : '';
}

$user = null;
if (!empty($_SESSION['uid'])) {
    $u = user_by_id((int)$_SESSION['uid']);
    $fresh = time() - (int)($_SESSION['t'] ?? 0) <= SESSION_TTL;
    $sameUa = ($_SESSION['ua'] ?? '') === hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($u && (int)$u['active'] === 1 && $fresh && $sameUa) {
        $user = $u;
        $_SESSION['t'] = time();
    } else {
        $_SESSION = [];
    }
}

if (!$user) {
    // --- самый первый запуск: создаём владельца ---
    if (users_count() === 0) {
        $err = '';
        if ($action === 'setup') {
            csrf_check();
            $email = mb_strtolower(post_str('email', 190));
            $name = post_str('name', 120);
            $pw = (string)($_POST['password'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Укажите корректный e-mail.';
            elseif ($name === '') $err = 'Укажите имя.';
            elseif ($pw !== (string)($_POST['password2'] ?? '')) $err = 'Пароли не совпадают.';
            else $err = valid_password($pw);
            if ($err === '') {
                dbq('INSERT INTO users (email, name, role, perms, password_hash, active, created_at) VALUES (?,?,?,?,?,1,?)', [$email, $name, 'owner', '[]', password_hash($pw, PASSWORD_DEFAULT), now()]);
                $u = user_by_id(dbid());
                finish_login($u);
                audit('setup', 'создан владелец', $u);
                flash('ok', 'Аккаунт владельца создан. Добро пожаловать!');
                go();
            }
        }
        auth_box('Первый запуск', '<p>Создайте аккаунт владельца: он управляет аккаунтами сотрудников и их правами. Сделайте это сразу после загрузки сайта на хостинг.</p>
            <form method="post" class="form">' . csrf_field() . '
            <label>Имя<input name="name" value="' . h(post_str('name', 120)) . '" required autofocus></label>
            <label>E-mail (логин)<input type="email" name="email" value="' . h(post_str('email', 190)) . '" autocomplete="username" required></label>
            <label>Пароль (не короче 10 символов)<input type="password" name="password" minlength="10" autocomplete="new-password" required></label>
            <label>Повторите пароль<input type="password" name="password2" minlength="10" autocomplete="new-password" required></label>
            <button class="btn" name="action" value="setup">Создать и войти</button></form>', $err);
    }

    // --- вход по e-mail и паролю ---
    $err = '';
    if ($action === 'login') {
        csrf_check();
        if (login_throttled(post_str('email', 190))) {
            audit('login_blocked', post_str('email', 190));
            $err = 'Слишком много попыток. Подождите 15 минут.';
        } else {
            $lu = user_by_email(post_str('email', 190));
            // для несуществующего аккаунта тоже проверяем пароль: по времени ответа нельзя понять, есть ли такой e-mail
            $hash = ($lu && $lu['password_hash']) ? $lu['password_hash'] : '$2y$10$usesomesillystringforsaltuT3dQ7y7m0m8m1m2m3m4m5m6m7m8m9m';
            $pwOk = password_verify((string)($_POST['password'] ?? ''), $hash);
            $ok = $lu && (int)$lu['active'] === 1 && $lu['password_hash'] && $pwOk;
            if (!$ok) {
                login_fail(post_str('email', 190));
                usleep(400000);
                audit('login_fail', post_str('email', 190));
                $err = 'Неверный e-mail или пароль.';
            } else {
                login_clear(post_str('email', 190));
                finish_login($lu);
                go();
            }
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        audit_guest('login_visit', (string)($_SERVER['REQUEST_URI'] ?? '') . ' · ' . mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120));
    }
    auth_box('Вход в админку', '<form method="post" class="form">' . csrf_field() . '
        <label>E-mail<input type="email" name="email" value="' . h(post_str('email', 190)) . '" autocomplete="username" required autofocus></label>
        <label>Пароль<input type="password" name="password" autocomplete="current-password" required></label>
        <button class="btn" name="action" value="login">Войти</button>
        <p class="hint">Доступ выдаёт владелец сайта. Если забыли пароль, обратитесь к нему.</p></form>', $err);
}

// ---------- ниже — только для вошедших ----------
function need(string $perm): void
{
    global $user;
    if (!user_can($user, $perm)) {
        audit('denied', $perm, $user);
        http_response_code(403);
        shell('Нет доступа', '<p class="lead">Для этого раздела у вашей учётной записи нет прав. Обратитесь к владельцу сайта.</p><p><a href="./">На главную</a></p>', '', $user);
    }
}

// ---------- действия ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $schemas = page_schemas();

    switch ($action) {
        case 'logout':
            audit('logout', '', $user);
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();
            header('Location: ./');
            exit;

        // --- страницы сайта ---
        case 'save_page':
            $key = (string)($_POST['page'] ?? '');
            if (!isset($schemas[$key])) go();
            need('page.' . $key);
            $old = content_get('page_' . $key, []);
            $errors = [];
            $new = clean_page($schemas[$key], is_array($_POST['d'] ?? null) ? $_POST['d'] : [], $old, files_flat(), $errors);
            $ok = content_set('page_' . $key, $new, $user);
            // удаляем загруженные ранее файлы, которые больше нигде не используются
            foreach (array_diff(managed_files($old), managed_files($new)) as $gone) delete_managed_file($gone);
            audit('page_save', changes_detail($key, page_changes($schemas[$key], $old, $new)), $user);
            if (!$ok) audit('error', 'Не удалось записать файл сайта (data): нет прав на запись. Страница: ' . $key, $user);
            elseif ($errors) audit('save_warn', $key . ': ' . implode(' ', array_unique($errors)), $user);
            if (!$ok) flash('err', 'Сохранено в базе, но не удалось обновить файл сайта: нет прав на запись в папку data.');
            elseif ($errors) flash('err', 'Страница сохранена, но есть замечания: ' . implode(' ', array_unique($errors)));
            else flash('ok', 'Страница сохранена. На сайте изменения уже видны.');
            go($key);

        case 'save_contacts':
            need('page.contacts');
            $old = content_get('site', []);
            $display = post_str('phone', 30);
            $digits = preg_replace('/\D/', '', $display) ?? '';
            if (strlen($digits) === 11 && $digits[0] === '8') $digits = '7' . substr($digits, 1);
            $email = post_str('email', 120);
            $full = post_str('address_full', 200);
            $errs = [];
            if (strlen($digits) < 10 || strlen($digits) > 15) $errs[] = 'Телефон: укажите номер полностью, например +7 (495) 225-44-03.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errs[] = 'E-mail указан неверно.';
            if ($full === '') $errs[] = 'Адрес не может быть пустым.';
            if ($errs) {
                audit('save_warn', 'contacts: ' . implode(' ', $errs), $user);
                flash('err', implode(' ', $errs));
                go('contacts');
            }
            $data = array_merge($old, [
                'phone' => ['display' => $display, 'href' => '+' . $digits],
                'email' => $email,
                'address' => ['full' => $full, 'city' => post_str('city', 80), 'street' => post_str('street', 120), 'postalCode' => post_str('postal', 10)],
                'hours' => post_str('hours', 100),
            ]);
            unset($data['whatsapp'], $data['telegram']);
            $ok = content_set('site', $data, $user);
            $chg = [];
            foreach ([['phone.display', 'Телефон'], ['email', 'E-mail'], ['hours', 'График работы'], ['address.full', 'Адрес'], ['address.city', 'Город'], ['address.street', 'Улица'], ['address.postalCode', 'Индекс']] as [$p, $lb]) {
                $a = path_get($old, $p); $b = path_get($data, $p);
                if ((string)$a !== (string)$b) $chg[] = [$lb, chg_short($a), chg_short($b)];
            }
            audit('page_save', changes_detail('contacts', $chg), $user);
            if (!$ok) audit('error', 'Не удалось записать файл сайта (data): нет прав на запись. Страница: contacts', $user);
            flash($ok ? 'ok' : 'err', $ok ? 'Контакты сохранены. На сайте изменения уже видны.' : 'Сохранено в базе, но не удалось обновить файл сайта: нет прав на запись в папку data.');
            go('contacts');

        case 'restore':
            $k = (string)($_POST['key'] ?? '');
            $pageKey = strpos($k, 'page_') === 0 ? substr($k, 5) : '';
            if ($k !== 'site' && !isset($schemas[$pageKey])) go();
            need($k === 'site' ? 'page.contacts' : 'page.' . $pageKey);
            $ok = content_restore($k, (int)($_POST['version'] ?? 0), $user);
            audit('page_restore', $k, $user);
            flash($ok ? 'ok' : 'err', $ok ? 'Версия возвращена.' : 'Не удалось вернуть версию.');
            go($k === 'site' ? 'contacts' : $pageKey);

        // --- сотрудники (только владелец) ---
        case 'user_create':
        case 'user_save':
            need('owner');
            $isNew = $action === 'user_create';
            $t = $isNew ? null : user_by_id((int)($_POST['id'] ?? 0));
            if (!$isNew && !$t) go('users');
            $email = mb_strtolower(post_str('email', 190));
            $name = post_str('name', 120);
            $role = (string)($_POST['role'] ?? 'staff');
            $pw = (string)($_POST['password'] ?? '');
            $perms = array_values(array_intersect(array_map('strval', (array)($_POST['perms'] ?? [])), array_keys(PERMS)));
            $back = $isNew ? '&new=1' : '&id=' . (int)$t['id'];
            $err = '';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Укажите корректный e-mail.';
            elseif ($name === '') $err = 'Укажите имя.';
            elseif (!isset(ROLES[$role])) $err = 'Выберите роль.';
            elseif (($ex = user_by_email($email)) && (!$t || (int)$ex['id'] !== (int)$t['id'])) $err = 'Аккаунт с таким e-mail уже есть.';
            elseif ($isNew || $pw !== '') $err = valid_password($pw);
            if (!$err && $t && (int)$t['id'] === (int)$user['id'] && $role !== 'owner') $err = 'Нельзя снять с себя роль владельца.';
            if (!$err && $t && $t['role'] === 'owner' && $role !== 'owner' && owners_active_count() <= 1) $err = 'Нельзя разжаловать последнего владельца.';
            if ($err) { flash('err', $err); go('users', $back); }
            $permJson = json_encode($role === 'owner' ? [] : $perms);
            if ($isNew) {
                dbq('INSERT INTO users (email, name, role, perms, password_hash, active, created_at) VALUES (?,?,?,?,?,1,?)', [$email, $name, $role, $permJson, password_hash($pw, PASSWORD_DEFAULT), now()]);
                audit('user_create', "$email ($role)", $user);
                flash('ok', 'Аккаунт создан. Передайте сотруднику логин и пароль.');
            } else {
                dbq('UPDATE users SET email = ?, name = ?, role = ?, perms = ? WHERE id = ?', [$email, $name, $role, $permJson, $t['id']]);
                if ($pw !== '') dbq('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $t['id']]);
                audit('user_save', $email . ($pw !== '' ? ' (новый пароль)' : ''), $user);
                flash('ok', 'Изменения сохранены.');
            }
            go('users');

        case 'user_toggle':
        case 'user_delete':
            need('owner');
            $t = user_by_id((int)($_POST['id'] ?? 0));
            if (!$t) go('users');
            if ($action === 'user_toggle') {
                if ((int)$t['id'] === (int)$user['id']) { flash('err', 'Себя отключить нельзя.'); go('users'); }
                $to = (int)$t['active'] === 1 ? 0 : 1;
                if ($to === 0 && $t['role'] === 'owner' && owners_active_count() <= 1) { flash('err', 'Нельзя отключить последнего владельца.'); go('users'); }
                dbq('UPDATE users SET active = ? WHERE id = ?', [$to, $t['id']]);
                audit($to ? 'user_enable' : 'user_disable', $t['email'], $user);
                flash('ok', $to ? 'Вход включён.' : 'Вход отключён: сотрудник больше не сможет войти.');
            } else {
                if ((int)$t['id'] === (int)$user['id']) { flash('err', 'Себя удалить нельзя.'); go('users'); }
                if ($t['role'] === 'owner' && (int)$t['active'] === 1 && owners_active_count() <= 1) { flash('err', 'Нельзя удалить последнего владельца.'); go('users'); }
                dbq('DELETE FROM users WHERE id = ?', [$t['id']]);
                audit('user_delete', $t['email'], $user);
                flash('ok', 'Аккаунт удалён.');
            }
            go('users');
    }
    go();
}

// ---------- страницы ----------
$s = (string)($_GET['s'] ?? '');
if ($s === '') {
    $s = first_section($user);
    if ($s !== 'dashboard') {
        header('Location: ./?s=' . $s);
        exit;
    }
}
if (!isset(SECTIONS[$s])) $s = first_section($user);
$perm = SECTIONS[$s][1];
if ($perm !== null) need($perm);

$schemas = page_schemas();
$actions = '';
if (isset($schemas[$s]) || $s === 'contacts') {
    $actions = '<a class="roundbtn" href="' . h(PAGE_URLS[$s] ?? '/') . '" target="_blank" rel="noopener" title="Открыть страницу на сайте" aria-label="Открыть страницу на сайте">' . icon('ext') . '</a>';
}
if (isset($schemas[$s])) {
    $body = page_editor($s);
} else {
    switch ($s) {
        case 'contacts': $body = page_contacts(); break;
        case 'users': $body = page_users($user); break;
        case 'audit': $body = page_audit(); break;
        case 'profile': $body = page_profile($user); break;
        default: $body = page_dashboard($user);
    }
}

shell(SECTIONS[$s][0], $body, $s, $user, $actions);
