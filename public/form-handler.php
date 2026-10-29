<?php
// Обработчик заявок: проверка, honeypot, отправка на e-mail. Ответ — JSON.
// Адрес получателя и настройки: form-config.php (создать из form-config.example.php).

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function respond(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, ['ok' => false, 'error' => 'method']);

$configFile = __DIR__ . '/form-config.php';
if (!is_file($configFile)) respond(500, ['ok' => false, 'error' => 'config']);
$cfg = require $configFile;


// Honeypot: боты заполняют скрытое поле. Отвечаем «успехом», чтобы не подсказывать.
if (!empty($_POST['website'])) respond(200, ['ok' => true]);

// Слишком быстрая отправка (< 2 с после загрузки страницы) — вероятно бот
$ts = (int)($_POST['ts'] ?? 0);
if ($ts > 0 && (time() - $ts) < 2) respond(200, ['ok' => true]);

// Простое ограничение частоты: не чаще 1 заявки в 20 с с одного IP
$ip = $_SERVER['REMOTE_ADDR'] ?? '0';
$lockFile = sys_get_temp_dir() . '/okno_lead_' . md5($ip);
if (is_file($lockFile) && (time() - filemtime($lockFile)) < 20) respond(429, ['ok' => false, 'error' => 'rate']);

function clean(string $key, int $max = 500): string {
    $v = trim((string)($_POST[$key] ?? ''));
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v);
    return mb_substr($v, 0, $max);
}

$name    = clean('name', 100);
$phone   = clean('phone', 30);
$email   = clean('email', 120);
$date    = clean('date', 100);
$comment = clean('comment', 1000);
$page    = clean('page', 200);
$type    = clean('form_type', 20);
$product = clean('product', 100);
$service = clean('service', 60);
$calc    = clean('calc_summary', 600);
$consent = !empty($_POST['consent']);

$emailOk = ($type !== 'measure') || filter_var($email, FILTER_VALIDATE_EMAIL);
if (!$emailOk || $name === '' || strlen(preg_replace('/\D/', '', $phone)) < 10 || !$consent) {
    respond(422, ['ok' => false, 'error' => 'validation']);
}

$types = ['callback' => 'Заказ звонка', 'measure' => 'Заявка с сайта (замер)', 'calc' => 'Заявка на расчёт'];
$title = $types[$type] ?? 'Заявка с сайта';

$lines = [
    "Тип: $title",
    "Имя: $name",
    "Телефон: $phone",
];
if ($service !== '') $lines[] = "Интересует: $service";
if ($email !== '')   $lines[] = "Почта: $email";
if ($date !== '')    $lines[] = "Дата/время: $date";
if ($comment !== '') $lines[] = "Комментарий: $comment";
if ($product !== '') $lines[] = "Товар/страница: $product";
if ($calc !== '')    $lines[] = "Расчёт калькулятора: $calc";
$lines[] = "Страница: $page";
$lines[] = 'Согласие на обработку ПДн (152-ФЗ): да, ' . date('d.m.Y H:i:s');
$lines[] = "IP: $ip";

// Вложения: до 3 файлов, по 10 МБ, только безопасные типы (по расширению)
$allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'heic' => 'image/heic',
            'pdf' => 'application/pdf', 'dwg' => 'application/octet-stream', 'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
$attachments = [];
if (!empty($_FILES['files']['name']) && is_array($_FILES['files']['name'])) {
    $count = min(3, count($_FILES['files']['name']));
    for ($i = 0; $i < $count; $i++) {
        if (($_FILES['files']['error'][$i] ?? 1) !== UPLOAD_ERR_OK) continue;
        $tmp  = $_FILES['files']['tmp_name'][$i];
        $size = (int)$_FILES['files']['size'][$i];
        $ext  = strtolower(pathinfo($_FILES['files']['name'][$i], PATHINFO_EXTENSION));
        if ($size <= 0 || $size > 10 * 1024 * 1024 || !isset($allowed[$ext]) || !is_uploaded_file($tmp)) continue;
        $attachments[] = ['name' => 'file' . ($i + 1) . '.' . $ext, 'type' => $allowed[$ext], 'data' => chunk_split(base64_encode(file_get_contents($tmp)))];
    }
}
if ($attachments) $lines[] = 'Файлов приложено: ' . count($attachments);

$subject = '=?UTF-8?B?' . base64_encode("[{$cfg['site_name']}] $title") . '?=';
$body = implode("\n", $lines);
if ($attachments) {
    $bd = 'b' . md5(uniqid('', true));
    $headers = ['MIME-Version: 1.0', 'From: ' . $cfg['from'], "Content-Type: multipart/mixed; boundary=\"$bd\""];
    $body = "--$bd\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . $body . "\r\n";
    foreach ($attachments as $f) {
        $body .= "--$bd\r\nContent-Type: {$f['type']}; name=\"{$f['name']}\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"{$f['name']}\"\r\n\r\n{$f['data']}\r\n";
    }
    $body .= "--$bd--";
} else {
    $headers = ['MIME-Version: 1.0', 'Content-Type: text/plain; charset=utf-8', 'From: ' . $cfg['from']];
}

$sent = !empty($cfg['to']) && @mail($cfg['to'], $subject, $body, implode("
", $headers));
if (!$sent) respond(500, ['ok' => false, 'error' => 'mail']);

@touch($lockFile);
respond(200, ['ok' => true]);
