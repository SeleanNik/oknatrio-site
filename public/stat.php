<?php
// Приём событий статистики с сайта (просмотр страницы, отправка заявки). Без cookie, без персональных данных.
// Всегда отвечает 204 и ничего не ломает на сайте, даже если база недоступна.
declare(strict_types=1);

header('Cache-Control: no-store');
http_response_code(204);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') exit;

$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
if ($ua === '' || preg_match('/bot|crawl|spider|slurp|facebookexternalhit|headless|lighthouse|monitor|uptime|curl|wget|python|httpclient|preview/i', $ua)) exit;

$j = json_decode((string)file_get_contents('php://input', false, null, 0, 2048), true);
if (!is_array($j) || !isset($j['p']) || !is_string($j['p'])) exit;

try {
    require __DIR__ . '/admin/inc/db.php';
    require __DIR__ . '/admin/inc/stats.php';
    record_hit($j['p'], (string)($j['e'] ?? 'view'), is_string($j['r'] ?? null) ? $j['r'] : '', $ua);
} catch (Throwable $e) {
    // статистика не должна влиять на сайт
}
