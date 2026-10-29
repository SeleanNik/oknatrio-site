<?php
// Оболочка админки (боковая панель, шапка, иконки) и мелкие HTML-помощники.
declare(strict_types=1);

// key => [подпись, право, иконка, группа]
const SECTIONS = [
    'dashboard' => ['Обзор', 'analytics.view', 'chart', ''],
    'home' => ['Главная', 'page.home', 'home', 'pages'],
    'alyuminievye' => ['Алюминиевые окна', 'page.alyuminievye', 'window', 'pages'],
    'pvh' => ['Окна ПВХ', 'page.pvh', 'layers', 'pages'],
    'works' => ['Наши работы', 'page.works', 'image', 'pages'],
    'tech' => ['Тех. информация', 'page.tech', 'doc', 'pages'],
    'contacts' => ['Контакты', 'page.contacts', 'phone', 'pages'],
    'users' => ['Сотрудники', 'owner', 'users', 'admin'],
    'audit' => ['Журнал', 'owner', 'clock', 'admin'],
    'profile' => ['Мой профиль', null, 'user', 'me'],
];

const GROUPS = ['pages' => 'Страницы сайта', 'admin' => 'Администратор'];

function icon(string $name): string
{
    $p = [
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'home' => '<path d="M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'window' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M12 3v18M4 12h16"/>',
        'layers' => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-8 8"/>',
        'doc' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
        'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'lock' => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'out' => '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 8l-4 4 4 4M6 12h10"/>',
        'ext' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'form' => '<path d="M4 4h16v13H8l-4 4z"/><path d="M8 9h8M8 13h5"/>',
        'pages' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3"/>',
    ][$name] ?? '';
    return '<svg class="ico" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'] = [$type, $msg];
}

function go(string $section = 'dashboard', string $extra = ''): void
{
    header('Location: ./' . ($section === 'dashboard' && $extra === '' ? '' : '?s=' . $section . $extra));
    exit;
}

function post_str(string $k, int $max = 300): string
{
    $v = trim((string)($_POST[$k] ?? ''));
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v) ?? '';
    return mb_substr($v, 0, $max);
}

function fmt_dt(?string $dt): string
{
    if (!$dt || !preg_match('/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})/', $dt, $m)) return (string)$dt;
    return "$m[3].$m[2].$m[1] $m[4]:$m[5]";
}

function initials(array $u): string
{
    $n = trim((string)($u['name'] ?: $u['email']));
    return mb_strtoupper(mb_substr($n, 0, 1));
}

function first_section(?array $user): string
{
    foreach (SECTIONS as $k => [, $perm]) {
        if ($perm === null || user_can($user, $perm)) return $k;
    }
    return 'profile';
}

function shell(string $title, string $body, string $active = '', ?array $user = null, string $actions = ''): void
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    ?><!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h($title) ?> — Админка «Трио»</title>
<link rel="icon" href="/favicon.svg">
<link rel="stylesheet" href="assets/admin.css">
</head>
<body class="<?= $user ? 'app' : 'plain' ?>">
<?php if ($user): ?>
<div class="frame">
<aside class="side">
  <a class="side__brand" href="./">Tri<span>o</span></a>
  <nav class="side__nav" aria-label="Разделы">
    <?php
    $lastGroup = null;
    foreach (SECTIONS as $k => [$label, $perm, $ic, $grp]) {
        if ($grp === 'me') continue;
        if ($perm !== null && !user_can($user, $perm)) continue;
        if ($grp !== $lastGroup && $grp !== '') echo '<div class="side__label">' . h(GROUPS[$grp] ?? '') . '</div>';
        $lastGroup = $grp;
        $href = './' . ($k === 'dashboard' ? '' : '?s=' . $k);
        echo '<a href="' . h($href) . '" class="' . ($active === $k ? 'is-on' : '') . '">' . icon($ic) . '<span>' . h($label) . '</span></a>';
    }
    ?>
  </nav>
  <div class="side__foot">
    <a href="./?s=profile" class="<?= $active === 'profile' ? 'is-on' : '' ?>"><?= icon('user') ?><span>Мой профиль</span></a>
    <form method="post" action="./"><?= csrf_field() ?><button name="action" value="logout" class="side__out"><?= icon('out') ?><span>Выйти</span></button></form>
    <small>Админка сайта · v2</small>
  </div>
</aside>
<div class="content">
  <header class="topbar">
    <h1 class="topbar__title"><?= h($title) ?></h1>
    <div class="topbar__right">
      <?= $actions ?>
      <a class="btn btn--sm" href="/" target="_blank" rel="noopener"><?= icon('ext') ?> Открыть сайт</a>
      <span class="chip-user"><i><?= h(initials($user)) ?></i><span><?= h($user['name'] ?: $user['email']) ?></span></span>
    </div>
  </header>
  <main class="main">
    <?php if ($flash): ?><div class="flash flash--<?= h($flash[0]) ?>" role="status"><?= h($flash[1]) ?></div><?php endif; ?>
    <?= $body ?>
  </main>
</div>
</div>
<?php else: ?>
<main class="main">
  <?php if ($flash): ?><div class="flash flash--<?= h($flash[0]) ?>" role="status"><?= h($flash[1]) ?></div><?php endif; ?>
  <?= $body ?>
</main>
<?php endif; ?>
<script src="assets/admin.js" defer></script>
</body>
</html>
<?php
    exit;
}

function auth_box(string $title, string $inner, string $err = ''): void
{
    ob_start(); ?>
    <div class="auth">
      <div class="auth__brand">Tri<span>o</span></div>
      <h1><?= h($title) ?></h1>
      <?php if ($err): ?><div class="flash flash--err"><?= h($err) ?></div><?php endif; ?>
      <?= $inner ?>
    </div>
    <?php shell($title, (string)ob_get_clean());
}

function versions_box(string $k): string
{
    $list = content_versions($k);
    if (!$list) return '';
    $o = '';
    foreach ($list as $v) {
        $o .= '<option value="' . (int)$v['id'] . '">' . h(fmt_dt($v['created_at']) . ($v['user_email'] ? ' · ' . $v['user_email'] : '')) . '</option>';
    }
    return '<form method="post" class="panel panel--quiet backups" onsubmit="return confirm(\'Вернуть выбранную версию? Текущая будет сохранена в истории.\')">'
        . csrf_field() . '<input type="hidden" name="key" value="' . h($k) . '">'
        . '<div><strong>История версий</strong><p class="hint">Перед каждым сохранением прежняя версия запоминается (последние 20).</p></div>'
        . '<div class="row"><select name="version">' . $o . '</select><button name="action" value="restore" class="btn btn--ghost">Вернуть версию</button></div></form>';
}

const PERM_LABEL_SHORT = ['analytics.view' => 'Аналитика', 'page.home' => 'Главная', 'page.alyuminievye' => 'Алюминий', 'page.pvh' => 'ПВХ', 'page.works' => 'Работы', 'page.tech' => 'Тех. инфо', 'page.contacts' => 'Контакты'];

const PERM_INFO = [
    'Аналитика' => [
        'analytics.view' => ['Аналитика сайта', 'Видит посещаемость: визиты, популярные страницы, источники', 'chart'],
    ],
    'Страницы сайта' => [
        'page.home' => ['Главная', 'Меняет заголовок, блоки и фото на главной странице', 'home'],
        'page.alyuminievye' => ['Алюминиевые окна', 'Меняет виды Alutech и Schuco, характеристики и вопросы', 'window'],
        'page.pvh' => ['Окна ПВХ', 'Меняет виды окон VEKA, характеристики и вопросы', 'layers'],
        'page.works' => ['Наши работы', 'Добавляет и меняет объекты и их фото', 'image'],
        'page.tech' => ['Тех. информация', 'Загружает PDF-каталоги, меняет карточки профилей', 'doc'],
        'page.contacts' => ['Контакты', 'Меняет телефон, адрес, график и ссылки на мессенджеры', 'phone'],
    ],
];

function perms_switches(array $selected): string
{
    $o = '<div class="presets"><span class="muted">Быстро:</span>'
        . '<button type="button" class="chip" data-preset="all">Все права</button>'
        . '<button type="button" class="chip" data-preset="pages">Только страницы</button>'
        . '<button type="button" class="chip" data-preset="analytics">Только аналитика</button>'
        . '<button type="button" class="chip" data-preset="none">Снять все</button></div>';
    foreach (PERM_INFO as $group => $items) {
        $o .= '<div class="permgroup"><div class="permgroup__t">' . h($group) . '</div>';
        foreach ($items as $k => [$label, $desc, $ic]) {
            $o .= '<label class="perm"><span class="perm__ico">' . icon($ic) . '</span><span class="perm__txt"><strong>' . h($label) . '</strong><small>' . h($desc) . '</small></span>'
                . '<input type="checkbox" class="switch" name="perms[]" value="' . h($k) . '" data-perm="' . h($k) . '"' . (in_array($k, $selected, true) ? ' checked' : '') . '><span class="switch__ui"></span></label>';
        }
        $o .= '</div>';
    }
    return $o;
}

function role_cards(string $current): string
{
    $opts = [
        'staff' => ['Сотрудник', 'Видит только те разделы, которые вы отметите справа. Не может управлять аккаунтами.', 'user'],
        'owner' => ['Владелец', 'Полный доступ ко всему сайту и к аккаунтам. Выдавайте осторожно.', 'users'],
    ];
    $o = '<div class="rolecards">';
    foreach ($opts as $k => [$t, $d, $ic]) {
        $o .= '<label class="rolecard"><input type="radio" name="role" value="' . h($k) . '" data-role' . ($current === $k ? ' checked' : '') . '><span class="rolecard__in"><span class="rolecard__ico">' . icon($ic) . '</span><strong>' . h($t) . '</strong><small>' . h($d) . '</small></span></label>';
    }
    return $o . '</div>';
}
