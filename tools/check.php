<?php
// Диагностика хостинга. Залить в корень сайта, открыть https://домен/check.php, прочитать и УДАЛИТЬ.
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
$root = __DIR__;
$rows = [];
$add = function (string $name, bool $ok, string $note = '') use (&$rows) { $rows[] = [$name, $ok, $note]; };

$add('PHP работает', true, 'версия ' . PHP_VERSION);
$add('PHP 7.4 или новее (лучше 8.x)', version_compare(PHP_VERSION, '7.4.0', '>='));
foreach (['pdo', 'pdo_sqlite', 'pdo_mysql', 'mbstring', 'fileinfo', 'json', 'openssl'] as $ext) {
    $need = in_array($ext, ['pdo_mysql'], true) ? ' (нужно только если выберете MySQL)' : '';
    $add('Расширение ' . $ext, extension_loaded($ext), $need);
}
foreach (['data', 'uploads', 'docs/files', 'admin/_data'] as $dir) {
    $p = $root . '/' . $dir;
    if (!is_dir($p)) { @mkdir($p, 0755, true); }
    $add('Папка ' . $dir . ': есть и доступна для записи', is_dir($p) && is_writable($p), is_dir($p) ? 'права ' . substr(sprintf('%o', fileperms($p)), -4) : 'не найдена');
}
$add('Файл admin/index.php на месте', is_file($root . '/admin/index.php'));
$add('Файл admin/inc/db.php на месте', is_file($root . '/admin/inc/db.php'));
$add('Файл data/pages.json на месте', is_file($root . '/data/pages.json'));
$add('Отправка почты mail() доступна', function_exists('mail'));
$add('Лимит загрузки файла', true, ini_get('upload_max_filesize') . ', POST до ' . ini_get('post_max_size'));
?><!doctype html><meta charset="utf-8"><title>Проверка хостинга</title>
<style>body{font:16px/1.5 system-ui,sans-serif;max-width:760px;margin:2rem auto;padding:0 1rem}td{padding:.35rem .6rem;border-bottom:1px solid #ddd}.ok{color:#1c7c4a}.bad{color:#c0392b;font-weight:700}</style>
<h1>Проверка хостинга</h1>
<table>
<?php foreach ($rows as [$n, $ok, $note]): ?>
<tr><td><?= htmlspecialchars($n) ?></td><td class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? 'OK' : 'НЕТ' ?></td><td><?= htmlspecialchars($note) ?></td></tr>
<?php endforeach; ?>
</table>
<p><b>После проверки удалите этот файл с сервера.</b></p>
