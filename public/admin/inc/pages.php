<?php
// Страницы админки (каждая функция возвращает HTML).
declare(strict_types=1);

function ago(?string $dt): string
{
    if (!$dt) return '';
    $t = strtotime($dt);
    if (!$t) return $dt;
    $d = time() - $t;
    if ($d < 90) return 'только что';
    if ($d < 3600) return (int)($d / 60) . ' мин назад';
    if (date('Y-m-d', $t) === date('Y-m-d')) return 'сегодня ' . date('H:i', $t);
    if (date('Y-m-d', $t) === date('Y-m-d', time() - 86400)) return 'вчера ' . date('H:i', $t);
    return fmt_dt($dt);
}

// Тип события: категория, уровень (ok/info/warn/err) и подпись для журнала
const AUDIT_CATS = ['login' => 'Входы', 'security' => 'Безопасность', 'content' => 'Контент', 'accounts' => 'Аккаунты', 'error' => 'Ошибки'];
const AUDIT_LEVELS = ['ok' => 'Успешно', 'info' => 'Действие', 'warn' => 'Внимание', 'err' => 'Ошибка'];
const AUDIT_META = [
    'login' => ['login', 'ok', 'Вход'],
    'logout' => ['login', 'info', 'Выход'],
    'setup' => ['accounts', 'ok', 'Первая настройка'],
    'login_fail' => ['security', 'warn', 'Неудачный вход'],
    'login_blocked' => ['security', 'err', 'Вход заблокирован'],
    '2fa_fail' => ['security', 'warn', 'Неверный код 2FA'],
    'csrf_fail' => ['security', 'warn', 'Просроченная форма'],
    'denied' => ['security', 'warn', 'Нет доступа'],
    '2fa_on' => ['security', 'info', '2FA включена'],
    '2fa_off' => ['security', 'info', '2FA отключена'],
    'page_save' => ['content', 'info', 'Правка страницы'],
    'page_restore' => ['content', 'info', 'Возврат версии'],
    'user_create' => ['accounts', 'ok', 'Новый аккаунт'],
    'user_save' => ['accounts', 'info', 'Правка аккаунта'],
    'user_delete' => ['accounts', 'warn', 'Аккаунт удалён'],
    'user_disable' => ['accounts', 'warn', 'Вход отключён'],
    'user_enable' => ['accounts', 'info', 'Вход включён'],
    'user_reset_2fa' => ['accounts', 'info', 'Сброс 2FA'],
    'save_warn' => ['error', 'warn', 'Замечание при сохранении'],
    'error' => ['error', 'err', 'Ошибка'],
];

function audit_meta(string $action): array
{
    return AUDIT_META[$action] ?? ['content', 'info', $action];
}

// Человеческое описание действия из журнала
function audit_text(array $r): string
{
    $labels = array_map(fn($s) => $s['label'], page_schemas()) + ['contacts' => 'Контакты'];
    $d = (string)$r['detail'];
    switch ($r['action']) {
        case 'page_save': return 'изменил(а) страницу «' . ($labels[$d] ?? $d) . '»';
        case 'page_restore': return 'вернул(а) прежнюю версию: ' . ($labels[preg_replace('/^page_/', '', $d)] ?? $d);
        case 'user_create': return 'создал(а) аккаунт ' . $d;
        case 'user_save': return 'изменил(а) аккаунт ' . $d;
        case 'user_delete': return 'удалил(а) аккаунт ' . $d;
        case 'user_disable': return 'отключил(а) вход: ' . $d;
        case 'user_enable': return 'включил(а) вход: ' . $d;
        case 'user_reset_2fa': return 'сбросил(а) 2FA: ' . $d;
        case 'login': return 'вошёл(а) в админку';
        case 'logout': return 'вышел(а) из админки';
        case 'login_fail': return 'неверный пароль' . ($d !== '' ? ' для ' . $d : '');
        case 'login_blocked': return 'слишком много попыток' . ($d !== '' ? ' (' . $d . ')' : '') . ': вход закрыт на 15 минут';
        case '2fa_fail': return 'неверный код из приложения-аутентификатора';
        case 'csrf_fail': return 'форма устарела или отправлена не с сайта: ' . $d;
        case 'denied': return 'попытка открыть раздел без прав: ' . (PERMS[$d] ?? $d);
        case '2fa_on': return 'включил(а) двухфакторную защиту';
        case '2fa_off': return 'отключил(а) двухфакторную защиту';
        case 'setup': return 'создан аккаунт владельца';
        case 'save_warn': return 'сохранение с замечаниями: ' . $d;
        case 'error': return $d;
        default: return $r['action'] . ($d !== '' ? ': ' . $d : '');
    }
}

function page_dashboard(array $user): string
{
    $d = (int)($_GET['days'] ?? 30);
    $days = in_array($d, [7, 30, 90], true) ? $d : 30;
    $st = stats_summary($days);
    $total = max(1, array_sum($st['devices']));
    $devLabel = ['desktop' => 'Компьютеры', 'mobile' => 'Телефоны', 'tablet' => 'Планшеты'];
    $topMax = max(1, (int)($st['top'][0]['c'] ?? 1));
    $srcTotal = max(1, array_sum($st['sources']));
    $recent = dbrows("SELECT * FROM audit WHERE action IN ('page_save','page_restore','user_create','user_save','user_delete','user_disable','user_enable','login','login_fail','error') ORDER BY id DESC LIMIT 7");
    ob_start(); ?>
    <div class="toolbar">
      <form method="get" class="inline"><select name="days" onchange="this.form.submit()" aria-label="Период">
        <?php foreach ([7 => 'Последние 7 дней', 30 => 'Последние 30 дней', 90 => 'Последние 90 дней'] as $dd => $l): ?>
          <option value="<?= $dd ?>" <?= $dd === $days ? 'selected' : '' ?>><?= h($l) ?></option>
        <?php endforeach; ?></select></form>
      <span class="muted">Статистика без cookie и персональных данных: посетитель обезличен и меняется каждый день.</span>
    </div>

    <div class="stats">
      <div class="stat stat--blue"><?= wave('#3672ff') ?><span class="stat__ico"><?= icon('users') ?></span><span class="stat__lbl">Визиты</span><strong><?= number_format($st['visits'], 0, '', ' ') ?></strong><?= delta_badge($st['visits'], $st['prevVisits']) ?></div>
      <div class="stat stat--orange"><?= wave('#f59e0b') ?><span class="stat__ico"><?= icon('eye') ?></span><span class="stat__lbl">Просмотры страниц</span><strong><?= number_format($st['views'], 0, '', ' ') ?></strong><span class="delta"><?= h((string)$st['perVisit']) ?> стр. за визит</span></div>
      <div class="stat stat--red"><?= wave('#ef476f') ?><span class="stat__ico"><?= icon('form') ?></span><span class="stat__lbl">Отправленные заявки</span><strong><?= number_format($st['leads'], 0, '', ' ') ?></strong><span class="delta">конверсия <?= h((string)$st['conversion']) ?>%</span></div>
      <div class="stat stat--green"><?= wave('#22a45d') ?><span class="stat__ico"><?= icon('pages') ?></span><span class="stat__lbl">Популярная страница</span><strong class="stat__small"><?= h($st['top'][0]['path'] ?? '—') ?></strong><span class="delta"><?= (int)($st['top'][0]['c'] ?? 0) ?> просмотров</span></div>
    </div>

    <div class="dash">
      <section class="panel dash__chart">
        <div class="panel__head"><h2>Посещения по дням</h2><div class="legend"><span><i class="dot dot--b"></i> Визиты</span><span><i class="dot dot--g"></i> Просмотры</span></div></div>
        <?= chart_svg($st['series']) ?>
      </section>
      <section class="panel dash__top">
        <div class="panel__head"><h2>Популярные страницы</h2></div>
        <ul class="bars">
          <?php foreach ($st['top'] as $r): ?>
            <li><span class="bars__name"><?= h($r['path']) ?></span><span class="bars__v"><?= (int)$r['c'] ?></span><i style="width:<?= max(4, (int)round($r['c'] * 100 / $topMax)) ?>%"></i></li>
          <?php endforeach; ?>
          <?php if (!$st['top']): ?><li class="muted">Данных пока нет. Статистика начнёт копиться после первых посещений сайта.</li><?php endif; ?>
        </ul>
      </section>
      <section class="panel dash__third">
        <div class="panel__head"><h2>Откуда приходят</h2></div>
        <ul class="bars">
          <?php foreach ($st['sources'] as $name => $c): ?>
            <li><span class="bars__name"><?= h($name) ?></span><span class="bars__v"><?= round($c * 100 / $srcTotal) ?>%</span><i style="width:<?= max(4, (int)round($c * 100 / $srcTotal)) ?>%"></i></li>
          <?php endforeach; ?>
          <?php if (!$st['sources']): ?><li class="muted">Данных пока нет.</li><?php endif; ?>
        </ul>
        <?php if ($st['sites']): ?><h3>Сайты-источники</h3><ul class="plain-list"><?php foreach ($st['sites'] as $r): ?><li><?= h($r['ref']) ?> <b><?= (int)$r['c'] ?></b></li><?php endforeach; ?></ul><?php endif; ?>
      </section>
      <section class="panel dash__third">
        <div class="panel__head"><h2>Устройства</h2></div>
        <ul class="bars">
          <?php foreach ($st['devices'] as $dv => $c): ?>
            <li><span class="bars__name"><?= h($devLabel[$dv] ?? $dv) ?></span><span class="bars__v"><?= round($c * 100 / $total) ?>%</span><i style="width:<?= max(4, (int)round($c * 100 / $total)) ?>%"></i></li>
          <?php endforeach; ?>
          <?php if (!$st['devices']): ?><li class="muted">Данных пока нет.</li><?php endif; ?>
        </ul>
      </section>
      <section class="panel dash__third">
        <div class="panel__head"><h2>Последние действия</h2><?php if (user_can($user, 'owner')): ?><a href="./?s=audit" class="muted">весь журнал →</a><?php endif; ?></div>
        <ul class="feed">
          <?php foreach ($recent as $r): ?>
            <li><i class="feed__ava"><?= h(mb_strtoupper(mb_substr((string)($r['user_email'] ?: '?'), 0, 1))) ?></i><span><b><?= h($r['user_email'] ?: 'Система') ?></b> <?= h(audit_text($r)) ?><small><?= h(ago($r['at'])) ?></small></span></li>
          <?php endforeach; ?>
          <?php if (!$recent): ?><li class="muted">Здесь появятся изменения страниц и действия сотрудников.</li><?php endif; ?>
        </ul>
      </section>
    </div>
    <?php return (string)ob_get_clean();
}

// Универсальный редактор страницы по описанию из schema.php
function page_editor(string $key): string
{
    $schemas = page_schemas();
    $schema = $schemas[$key];
    $data = content_get('page_' . $key, []);
    ob_start(); ?>
    <p class="lead">Изменения видны на сайте сразу после сохранения. Страница на сайте: <a href="<?= h(PAGE_URLS[$key]) ?>" target="_blank" rel="noopener"><?= h(PAGE_URLS[$key]) ?></a></p>
    <form method="post" enctype="multipart/form-data" class="pageform" id="page-form">
      <?= csrf_field() ?><input type="hidden" name="page" value="<?= h($key) ?>">
      <?= render_page_form($schema, $data) ?>
      <div class="savebar"><span class="savebar__msg" data-savemsg>Фото — jpg, png, webp до 4 МБ. PDF — до 100 МБ (не больше лимита хостинга).</span><button class="btn" name="action" value="save_page">Сохранить страницу</button></div>
    </form>
    <?= versions_box('page_' . $key) ?>
    <?php return (string)ob_get_clean();
}

function page_contacts(): string
{
    $d = content_get('site', []);
    $a = $d['address'] ?? [];
    ob_start(); ?>
    <p class="lead">Телефон, адрес и график показываются в шапке, футере, блоках с формой и на странице «Контакты».</p>
    <form method="post" class="pageform" autocomplete="off">
      <?= csrf_field() ?>
      <section class="panel"><h2>Связь</h2><div class="panel__body">
        <div class="grid2">
          <label>Телефон<input name="phone" value="<?= h($d['phone']['display'] ?? '') ?>" placeholder="+7 (495) 225-44-03" required></label>
          <label>E-mail (публичный, на сайте)<input type="email" name="email" value="<?= h($d['email'] ?? '') ?>" required></label>
        </div>
        <label>График работы<input name="hours" value="<?= h($d['hours'] ?? '') ?>" placeholder="Пн–Вс 09:00–20:00"></label>
        <div class="grid2">
          <label>Ссылка WhatsApp<input type="url" name="whatsapp" value="<?= h($d['whatsapp'] ?? '') ?>" placeholder="https://wa.me/74952254403"></label>
          <label>Ссылка Telegram<input type="url" name="telegram" value="<?= h($d['telegram'] ?? '') ?>" placeholder="https://t.me/имя"></label>
        </div>
      </div></section>
      <section class="panel"><h2>Адрес</h2><div class="panel__body">
        <label>Адрес (так показывается на сайте)<input name="address_full" value="<?= h($a['full'] ?? '') ?>" required></label>
        <details class="adv"><summary>Адрес для поисковиков (микроразметка)</summary>
          <div class="grid3">
            <label>Индекс<input name="postal" value="<?= h($a['postalCode'] ?? '') ?>"></label>
            <label>Город<input name="city" value="<?= h($a['city'] ?? '') ?>"></label>
            <label>Улица, дом<input name="street" value="<?= h($a['street'] ?? '') ?>"></label>
          </div></details>
      </div></section>
      <div class="savebar"><span class="savebar__msg" data-savemsg></span><button class="btn" name="action" value="save_contacts">Сохранить контакты</button></div>
    </form>
    <?= versions_box('site') ?>
    <?php return (string)ob_get_clean();
}

function page_users(array $me): string
{
    $id = (int)($_GET['id'] ?? 0);
    ob_start();

    // --- создание / редактирование ---
    if ($id > 0 || isset($_GET['new'])) {
        $u = $id > 0 ? user_by_id($id) : null;
        if ($id > 0 && !$u) { echo '<p>Сотрудник не найден. <a href="./?s=users">К списку</a></p>'; return (string)ob_get_clean(); }
        $self = $u && (int)$u['id'] === (int)$me['id'];
        $perms = $u ? user_perms($u) : [];
        $role = $u['role'] ?? 'staff';
        ?>
        <a class="back" href="./?s=users">← Все сотрудники</a>
        <div class="account-head">
          <i class="person__ava person__ava--lg"><?= h($u ? initials($u) : '+') ?></i>
          <div><h2><?= $u ? h($u['name'] ?: $u['email']) : 'Новый аккаунт' ?></h2>
            <p class="muted"><?= $u ? 'Измените данные, пароль или права. Новые права действуют сразу после сохранения.' : 'Заполните три шага: кто это, роль и что ему разрешено.' ?></p></div>
        </div>
        <form method="post" class="pageform" autocomplete="off">
          <?= csrf_field() ?><?php if ($u): ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><?php endif; ?>
          <div class="account">
            <div class="account__main">
              <section class="panel step"><span class="step__n">1</span><h2>Кто это</h2><div class="panel__body">
                <div class="grid2">
                  <label>Имя<input name="name" value="<?= h($u['name'] ?? '') ?>" maxlength="120" placeholder="Например, Анна" required></label>
                  <label>E-mail (будет логином)<input type="email" name="email" value="<?= h($u['email'] ?? '') ?>" maxlength="190" placeholder="anna@company.ru" autocomplete="off" required></label>
                </div>
                <label><?= $u ? 'Новый пароль' : 'Пароль' ?> <span class="muted">(не короче 10 символов<?= $u ? ', оставьте пустым, если менять не нужно' : '' ?>)</span>
                  <span class="pw"><input type="text" name="password" id="pw" minlength="10" autocomplete="new-password" <?= $u ? '' : 'required' ?>><button type="button" class="btn btn--ghost" data-genpw>Сгенерировать</button></span></label>
                <p class="hint">Пароль хранится в зашифрованном виде, увидеть его потом нельзя. Передайте его сотруднику лично.</p>
              </div></section>
              <section class="panel step"><span class="step__n">2</span><h2>Роль</h2><div class="panel__body">
                <?= role_cards($role) ?>
              </div></section>
            </div>
            <section class="panel step account__perms" data-permsection><span class="step__n">3</span><h2>Что разрешено</h2>
              <p class="hint" data-ownernote hidden>Владельцу доступно всё, отдельные права не нужны.</p>
              <div class="panel__body" data-permbody><?= perms_switches($perms) ?></div>
            </section>
          </div>
          <div class="savebar"><span class="savebar__msg"><?= $u ? '' : 'Аккаунт создаётся сразу. Логин и пароль передайте сотруднику.' ?></span><button class="btn" name="action" value="<?= $u ? 'user_save' : 'user_create' ?>"><?= $u ? 'Сохранить изменения' : 'Создать аккаунт' ?></button></div>
        </form>
        <?php if ($u): ?>
        <section class="panel panel--quiet">
          <div><strong>Управление аккаунтом</strong><p class="hint">Отключённый аккаунт не может войти, но сохраняется. Удаление необратимо.</p></div>
          <div class="row">
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <?php if (!$self): ?><button class="btn btn--ghost" name="action" value="user_toggle"><?= $u['active'] ? 'Отключить вход' : 'Включить вход' ?></button><?php endif; ?>
              <?php if ($u['totp_on']): ?><button class="btn btn--ghost" name="action" value="user_reset_2fa">Сбросить 2FA</button><?php endif; ?>
            </form>
            <?php if (!$self): ?>
            <form method="post" class="inline" onsubmit="return confirm('Удалить аккаунт «<?= h(addslashes($u['name'] ?: $u['email'])) ?>» навсегда? Действие необратимо.')">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <button class="btn btn--danger" name="action" value="user_delete">Удалить аккаунт</button>
            </form>
            <?php endif; ?>
          </div>
        </section>
        <?php endif;
        return (string)ob_get_clean();
    }

    // --- список карточками ---
    $users = dbrows('SELECT * FROM users ORDER BY id');
    ?>
    <div class="toolbar toolbar--between"><p class="lead" style="margin:0">Аккаунты создаёт, меняет и удаляет только владелец. Он же выдаёт права.</p><a class="btn" href="./?s=users&amp;new=1">+ Создать аккаунт</a></div>
    <div class="people">
      <?php foreach ($users as $u): $p = user_perms($u); ?>
        <a class="person<?= $u['active'] ? '' : ' is-off' ?>" href="./?s=users&amp;id=<?= (int)$u['id'] ?>">
          <i class="person__ava"><?= h(initials($u)) ?></i>
          <span class="person__who"><strong><?= h($u['name'] ?: '—') ?></strong><small><?= h($u['email']) ?></small></span>
          <span class="person__role"><?= $u['role'] === 'owner' ? '<span class="badge badge--new">Владелец</span>' : '<span class="badge">Сотрудник</span>' ?><?= $u['active'] ? '' : ' <span class="badge badge--spam">отключён</span>' ?><?= $u['totp_on'] ? ' <span class="badge badge--done">2FA</span>' : '' ?></span>
          <span class="person__perms">
            <?php if ($u['role'] === 'owner'): ?><span class="pchip pchip--all">Полный доступ</span>
            <?php elseif (!$p): ?><span class="pchip pchip--none">Нет прав</span>
            <?php else: foreach (array_slice($p, 0, 4) as $k): ?><span class="pchip"><?= h(PERM_LABEL_SHORT[$k] ?? $k) ?></span><?php endforeach; if (count($p) > 4): ?><span class="pchip">+<?= count($p) - 4 ?></span><?php endif; endif; ?>
          </span>
          <span class="person__foot"><?= h($u['last_login'] ? 'вход: ' . ago($u['last_login']) : 'ещё не входил') ?> <b>Изменить →</b></span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php return (string)ob_get_clean();
}

function audit_url(array $over = []): string
{
    $q = array_merge(['s' => 'audit'], array_filter(array_intersect_key($_GET, array_flip(['q', 'cat', 'lvl', 'per', 'who'])), fn($v) => $v !== ''), $over);
    return './?' . http_build_query(array_filter($q, fn($v) => $v !== '' && $v !== null));
}

function page_audit(): string
{
    $q = trim((string)($_GET['q'] ?? ''));
    $cat = (string)($_GET['cat'] ?? '');
    $lvl = (string)($_GET['lvl'] ?? '');
    $per = (string)($_GET['per'] ?? '0');
    $who = trim((string)($_GET['who'] ?? ''));
    $page = max(1, (int)($_GET['p'] ?? 1));
    $size = 40;
    $perDays = ['0' => 'Всё время', '1' => 'Сегодня', '7' => '7 дней', '30' => '30 дней'];
    if (!isset($perDays[$per])) $per = '0';

    $byCat = fn(string $c) => array_keys(array_filter(AUDIT_META, fn($m) => $m[0] === $c));
    $byLvl = fn(string $l) => array_keys(array_filter(AUDIT_META, fn($m) => $m[1] === $l));
    $in = fn(array $a) => "'" . implode("','", $a) . "'";

    $where = ['1=1']; $args = [];
    if ($per !== '0') { $where[] = 'at >= ?'; $args[] = date('Y-m-d 00:00:00', time() - ((int)$per - 1) * 86400); }
    if (isset(AUDIT_CATS[$cat])) $where[] = 'action IN (' . $in($byCat($cat)) . ')'; else $cat = '';
    if (isset(AUDIT_LEVELS[$lvl])) $where[] = 'action IN (' . $in($byLvl($lvl)) . ')'; else $lvl = '';
    if ($who !== '') { $where[] = 'user_email = ?'; $args[] = $who; }
    if ($q !== '') {
        $like = '%' . str_replace(['%', '_'], ['', ''], $q) . '%';
        $where[] = '(user_email LIKE ? OR detail LIKE ? OR ip LIKE ? OR action LIKE ?)';
        array_push($args, $like, $like, $like, $like);
    }
    $W = implode(' AND ', $where);
    $total = (int)dbq("SELECT COUNT(*) FROM audit WHERE $W", $args)->fetchColumn();
    $pages = max(1, (int)ceil($total / $size));
    $page = min($page, $pages);
    $rows = dbrows("SELECT * FROM audit WHERE $W ORDER BY id DESC LIMIT $size OFFSET " . (($page - 1) * $size), $args);

    $users = array_column(dbrows('SELECT DISTINCT user_email FROM audit WHERE user_email IS NOT NULL ORDER BY user_email'), 'user_email');
    $ico = ['login' => 'user', 'security' => 'lock', 'content' => 'pages', 'accounts' => 'users', 'error' => 'clock'];
    $filtered = $q !== '' || $cat !== '' || $lvl !== '' || $who !== '' || $per !== '0';

    ob_start(); ?>
    <p class="lead">Все входы в админку, изменения на сайте, неудачные попытки и ошибки. Записи хранятся без ограничения срока.</p>

    <form method="get" class="logbar">
      <input type="hidden" name="s" value="audit">
      <label class="logbar__search"><?= icon('eye') ?><input type="search" name="q" value="<?= h($q) ?>" placeholder="Поиск: e-mail, IP, подробности…" autocomplete="off"></label>
      <select name="cat" aria-label="Категория"><option value="">Все категории</option><?php foreach (AUDIT_CATS as $k => $v): ?><option value="<?= h($k) ?>"<?= $cat === $k ? ' selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select>
      <select name="lvl" aria-label="Уровень"><option value="">Любой уровень</option><?php foreach (AUDIT_LEVELS as $k => $v): ?><option value="<?= h($k) ?>"<?= $lvl === $k ? ' selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select>
      <select name="who" aria-label="Кто"><option value="">Все аккаунты</option><?php foreach ($users as $u): ?><option value="<?= h($u) ?>"<?= $who === $u ? ' selected' : '' ?>><?= h($u) ?></option><?php endforeach; ?></select>
      <select name="per" aria-label="Период"><?php foreach ($perDays as $k => $v): ?><option value="<?= h((string)$k) ?>"<?= $per === (string)$k ? ' selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select>
      <button class="btn">Найти</button>
      <?php if ($filtered): ?><a class="btn btn--ghost" href="./?s=audit">Сбросить</a><?php endif; ?>
    </form>

    <div class="logchips">
      <?php foreach (['' => 'Все'] + AUDIT_CATS as $k => $v): ?>
        <a class="logchip<?= $cat === (string)$k ? ' is-on' : '' ?>" href="<?= h(audit_url(['cat' => (string)$k, 'p' => null])) ?>"><?= h($v) ?></a>
      <?php endforeach; ?>
      <span class="muted logchips__n">Найдено: <?= $total ?></span>
    </div>

    <?php if (!$rows): ?>
      <div class="logempty"><?= icon('clock') ?><b>Ничего не найдено</b><span>Измените запрос или период.</span></div>
    <?php else: ?>
    <ul class="log">
      <?php $prevDay = ''; foreach ($rows as $r):
          [$c, $l, $title] = audit_meta((string)$r['action']);
          $day = substr((string)$r['at'], 0, 10);
          if ($day !== $prevDay): $prevDay = $day;
              $lab = $day === date('Y-m-d') ? 'Сегодня' : ($day === date('Y-m-d', time() - 86400) ? 'Вчера' : date('d.m.Y', strtotime($day))); ?>
        <li class="log__day"><?= h($lab) ?></li>
      <?php endif; ?>
        <li class="logrow logrow--<?= h($l) ?>">
          <i class="logrow__ico"><?= icon($ico[$c] ?? 'clock') ?></i>
          <div class="logrow__main">
            <div class="logrow__top"><b><?= h($title) ?></b><span class="lvl lvl--<?= h($l) ?>"><?= h(AUDIT_LEVELS[$l]) ?></span><span class="cat"><?= h(AUDIT_CATS[$c]) ?></span></div>
            <p><?php if ($r['user_email']): ?><a href="<?= h(audit_url(['who' => $r['user_email'], 'p' => null])) ?>" class="logrow__who"><?= h($r['user_email']) ?></a> <?php endif; ?><?= h(audit_text($r)) ?></p>
          </div>
          <div class="logrow__meta"><time><?= h(substr((string)$r['at'], 11, 5)) ?></time><?php if ($r['ip']): ?><a href="<?= h(audit_url(['q' => $r['ip'], 'p' => null])) ?>" class="ip" title="Показать всё с этого IP"><?= h($r['ip']) ?></a><?php endif; ?></div>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($pages > 1): ?>
      <nav class="logpager" aria-label="Страницы">
        <?php if ($page > 1): ?><a class="btn btn--ghost" href="<?= h(audit_url(['p' => $page - 1])) ?>">← Новее</a><?php endif; ?>
        <span class="muted">Страница <?= $page ?> из <?= $pages ?></span>
        <?php if ($page < $pages): ?><a class="btn btn--ghost" href="<?= h(audit_url(['p' => $page + 1])) ?>">Старее →</a><?php endif; ?>
      </nav>
    <?php endif; endif; ?>
    <?php return (string)ob_get_clean();
}

function page_profile(array $user): string
{
    $new = $_SESSION['totp_new'] ?? null;
    $perms = user_perms($user);
    ob_start(); ?>
    <p class="lead">Данные аккаунта и пароль меняет только владелец.</p>
    <section class="panel"><h2>Аккаунт</h2><div class="panel__body">
      <dl class="detail"><dt>Имя</dt><dd><?= h($user['name']) ?></dd><dt>E-mail</dt><dd><?= h($user['email']) ?></dd>
        <dt>Роль</dt><dd><?= h($user['role'] === 'owner' ? 'Владелец' : 'Сотрудник') ?></dd>
        <dt>Доступ</dt><dd><?php
          if ($user['role'] === 'owner') echo 'Все разделы и управление сотрудниками';
          else echo $perms ? h(implode(', ', array_map(fn($p) => PERMS[$p], $perms))) : 'Нет выданных прав: обратитесь к владельцу';
        ?></dd></dl>
    </div></section>
    <section class="panel"><h2>Вход в два шага (2FA)</h2><div class="panel__body">
    <?php if ($user['totp_on']): ?>
      <form method="post" class="form form--narrow"><?= csrf_field() ?>
        <p>Двухфакторная защита <strong>включена</strong>: при входе нужен код из приложения-аутентификатора.</p>
        <label>Пароль для отключения<input type="password" name="current" autocomplete="current-password" required></label>
        <div class="actions"><button class="btn btn--ghost" name="action" value="totp_disable">Отключить 2FA</button></div>
      </form>
    <?php elseif ($new): ?>
      <form method="post" class="form form--narrow"><?= csrf_field() ?>
        <p>1. В приложении-аутентификаторе (Google Authenticator, Яндекс Ключ, Aegis и т.п.) добавьте аккаунт вручную и введите этот ключ:</p>
        <input readonly value="<?= h($new) ?>" onclick="this.select()">
        <label>2. Введите 6-значный код из приложения<input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required></label>
        <div class="actions"><button class="btn" name="action" value="totp_confirm">Включить</button></div>
      </form>
    <?php else: ?>
      <form method="post" class="form form--narrow"><?= csrf_field() ?>
        <p>Дополнительная защита аккаунта: после пароля потребуется код из телефона.</p>
        <div class="actions"><button class="btn btn--ghost" name="action" value="totp_start">Настроить 2FA</button></div>
      </form>
    <?php endif; ?>
    </div></section>
    <?php return (string)ob_get_clean();
}
