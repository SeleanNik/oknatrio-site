<?php
// Собственная аналитика сайта: без cookie и без персональных данных.
// Посетитель = обезличенный хеш (IP + браузер + дата + соль), который меняется каждый день,
// поэтому идентифицировать человека или отследить его между днями нельзя.
declare(strict_types=1);

function stat_salt(): string
{
    $f = A_DATA . '/salt.txt';
    $s = is_file($f) ? trim((string)file_get_contents($f)) : '';
    if (strlen($s) < 16) {
        $s = bin2hex(random_bytes(16));
        @file_put_contents($f, $s, LOCK_EX);
    }
    return $s;
}

function ref_host(string $ref, string $ownHost): string
{
    if ($ref === '') return '';
    $h = strtolower((string)parse_url($ref, PHP_URL_HOST));
    $h = preg_replace('/^www\./', '', $h) ?? '';
    $own = preg_replace('/^www\./', '', strtolower(preg_replace('/:\d+$/', '', $ownHost) ?? '')) ?? '';
    return ($h === '' || $h === $own) ? '' : mb_substr($h, 0, 120);
}

// Записывает просмотр страницы или событие «заявка отправлена». Любые ошибки молча игнорируются.
function record_hit(string $path, string $ev, string $ref, string $ua): void
{
    $path = (string)parse_url($path, PHP_URL_PATH);
    if ($path === '' || $path[0] !== '/' || strlen($path) > 250 || strpos($path, '/admin') === 0) return;
    $ev = $ev === 'lead' ? 'lead' : 'view';
    $device = preg_match('/iPad|Tablet/i', $ua) ? 'tablet' : (preg_match('/Mobile|Android|iPhone/i', $ua) ? 'mobile' : 'desktop');
    $vid = substr(hash('sha256', client_ip() . '|' . $ua . '|' . date('Y-m-d') . '|' . stat_salt()), 0, 16);
    // защита от накрутки/флуда: не больше 40 записей в минуту с одного посетителя
    $n = (int)dbq('SELECT COUNT(*) FROM hits WHERE vid = ? AND at >= ?', [$vid, date('Y-m-d H:i:s', time() - 60)])->fetchColumn();
    if ($n >= 40) return;
    dbq('INSERT INTO hits (day, at, path, vid, ref, device, ev) VALUES (?,?,?,?,?,?,?)',
        [date('Y-m-d'), now(), $path, $vid, ref_host($ref, (string)($_SERVER['HTTP_HOST'] ?? '')), $device, $ev]);
    if (random_int(1, 200) === 1) dbq('DELETE FROM hits WHERE day < ?', [date('Y-m-d', time() - 400 * 86400)]);
}

function ref_group(string $host): string
{
    if ($host === '') return 'Прямые заходы';
    if (preg_match('/(^|\.)(yandex|ya)\.|google\.|bing\.com|duckduckgo|mail\.ru|rambler|yahoo|sputnik/i', $host)) return 'Поисковые системы';
    if (preg_match('/vk\.com|t\.me|telegram|instagram|facebook|ok\.ru|youtube|dzen|tiktok|whatsapp/i', $host)) return 'Соцсети и мессенджеры';
    return 'Другие сайты';
}

function stats_summary(int $days): array
{
    $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $prevFrom = date('Y-m-d', strtotime('-' . (2 * $days - 1) . ' days'));
    $q = fn(string $sql, array $p) => dbrows($sql, $p);

    $byDay = [];
    foreach ($q("SELECT day, COUNT(DISTINCT vid) v, COUNT(*) pv FROM hits WHERE ev = 'view' AND day >= ? GROUP BY day", [$from]) as $r) $byDay[$r['day']] = [(int)$r['v'], (int)$r['pv']];
    $leadsByDay = [];
    foreach ($q("SELECT day, COUNT(*) c FROM hits WHERE ev = 'lead' AND day >= ? GROUP BY day", [$from]) as $r) $leadsByDay[$r['day']] = (int)$r['c'];

    $series = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $series[] = ['day' => $d, 'visits' => $byDay[$d][0] ?? 0, 'views' => $byDay[$d][1] ?? 0, 'leads' => $leadsByDay[$d] ?? 0];
    }
    $visits = array_sum(array_column($series, 'visits'));
    $views = array_sum(array_column($series, 'views'));
    $leads = array_sum(array_column($series, 'leads'));

    $prev = dbrow("SELECT COUNT(*) pv FROM hits WHERE ev = 'view' AND day >= ? AND day < ?", [$prevFrom, $from]);
    $prevVisits = 0;
    foreach ($q("SELECT day, COUNT(DISTINCT vid) v FROM hits WHERE ev = 'view' AND day >= ? AND day < ? GROUP BY day", [$prevFrom, $from]) as $r) $prevVisits += (int)$r['v'];

    $top = $q("SELECT path, COUNT(*) c FROM hits WHERE ev = 'view' AND day >= ? GROUP BY path ORDER BY c DESC LIMIT 8", [$from]);

    $sources = [];
    foreach ($q("SELECT ref, COUNT(*) c FROM hits WHERE ev = 'view' AND day >= ? GROUP BY ref", [$from]) as $r) {
        $g = ref_group((string)$r['ref']);
        $sources[$g] = ($sources[$g] ?? 0) + (int)$r['c'];
    }
    arsort($sources);
    $sites = $q("SELECT ref, COUNT(*) c FROM hits WHERE ev = 'view' AND ref <> '' AND day >= ? GROUP BY ref ORDER BY c DESC LIMIT 6", [$from]);

    $devices = [];
    foreach ($q("SELECT device, COUNT(*) c FROM hits WHERE ev = 'view' AND day >= ? GROUP BY device", [$from]) as $r) $devices[$r['device']] = (int)$r['c'];

    return [
        'series' => $series, 'visits' => $visits, 'views' => $views, 'leads' => $leads,
        'perVisit' => $visits > 0 ? round($views / $visits, 1) : 0,
        'prevVisits' => $prevVisits, 'top' => $top, 'sources' => $sources, 'sites' => $sites, 'devices' => $devices,
        'conversion' => $visits > 0 ? round($leads * 100 / $visits, 1) : 0,
    ];
}

function delta_badge(int $now, int $prev): string
{
    if ($prev <= 0) return $now > 0 ? '<span class="delta delta--up">новое</span>' : '';
    $p = round(($now - $prev) * 100 / $prev);
    if ($p === 0.0) return '<span class="delta">без изменений</span>';
    return '<span class="delta ' . ($p > 0 ? 'delta--up' : 'delta--down') . '">' . ($p > 0 ? '▲ +' : '▼ ') . (int)$p . '% к прошлому периоду</span>';
}

// SVG-график посещений: площадь + линия, подсказки по наведению (<title>)
function chart_svg(array $series): string
{
    $w = 760; $h = 240; $padL = 34; $padB = 26; $padT = 12; $padR = 8;
    $n = count($series);
    $max = max(1, max(array_column($series, 'views')));
    $nice = (int)(ceil($max / 4) * 4) ?: 4;
    $x = fn(int $i) => $padL + ($n > 1 ? ($w - $padL - $padR) * $i / ($n - 1) : 0);
    $y = fn(int $v) => $padT + ($h - $padT - $padB) * (1 - $v / $nice);

    $line = function (string $key) use ($series, $x, $y): string {
        $pts = [];
        foreach ($series as $i => $s) $pts[] = round($x($i), 1) . ',' . round($y($s[$key]), 1);
        return implode(' ', $pts);
    };
    $area = function (string $key) use ($series, $x, $y, $n, $h, $padB, $padL): string {
        $pts = $series ? round($x(0), 1) . ',' . ($h - $padB) . ' ' : '';
        foreach ($series as $i => $s) $pts .= round($x($i), 1) . ',' . round($y($s[$key]), 1) . ' ';
        return $pts . round($x($n - 1), 1) . ',' . ($h - $padB);
    };

    $o = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="Посещения по дням"><defs>'
        . '<linearGradient id="gv" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3672ff" stop-opacity=".28"/><stop offset="1" stop-color="#3672ff" stop-opacity="0"/></linearGradient>'
        . '<linearGradient id="gp" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#22a45d" stop-opacity=".22"/><stop offset="1" stop-color="#22a45d" stop-opacity="0"/></linearGradient></defs>';
    for ($g = 0; $g <= 4; $g++) {
        $val = (int)($nice * $g / 4);
        $yy = round($y($val), 1);
        $o .= '<line x1="' . $padL . '" x2="' . ($w - $padR) . '" y1="' . $yy . '" y2="' . $yy . '" class="chart__grid"/><text x="' . ($padL - 6) . '" y="' . ($yy + 4) . '" text-anchor="end" class="chart__t">' . $val . '</text>';
    }
    $o .= '<polygon points="' . $area('views') . '" fill="url(#gp)"/><polyline points="' . $line('views') . '" class="chart__line chart__line--g"/>';
    $o .= '<polygon points="' . $area('visits') . '" fill="url(#gv)"/><polyline points="' . $line('visits') . '" class="chart__line"/>';
    $step = max(1, (int)ceil($n / 7));
    foreach ($series as $i => $s) {
        $d = substr($s['day'], 8, 2) . '.' . substr($s['day'], 5, 2);
        if ($i % $step === 0 || $i === $n - 1) $o .= '<text x="' . round($x($i), 1) . '" y="' . ($h - 6) . '" text-anchor="middle" class="chart__t">' . $d . '</text>';
        $o .= '<circle cx="' . round($x($i), 1) . '" cy="' . round($y($s['visits']), 1) . '" r="4" class="chart__dot"><title>' . $d . ': визитов ' . $s['visits'] . ', просмотров ' . $s['views'] . ', заявок ' . $s['leads'] . '</title></circle>';
    }
    return $o . '</svg>';
}

function wave(string $color): string
{
    return '<svg class="stat__wave" viewBox="0 0 160 70" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id="w' . substr($color, 1) . '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' . $color . '" stop-opacity=".35"/><stop offset="1" stop-color="' . $color . '" stop-opacity=".05"/></linearGradient></defs>'
        . '<path d="M0 70 L0 52 C18 50 26 32 44 34 C64 36 70 58 92 46 C112 35 118 14 138 10 C148 8 154 12 160 14 L160 70 Z" fill="url(#w' . substr($color, 1) . ')"/></svg>';
}
