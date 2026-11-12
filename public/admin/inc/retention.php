<?php
// Срок хранения заявок (152-ФЗ: персональные данные не храним дольше, чем нужно).
// Заявки — 1 год, отсеянный спам — 30 дней. Вызывается из form-handler.php и раздела «Заявки».
declare(strict_types=1);

const LEADS_KEEP_DAYS = 365;
const SPAM_KEEP_DAYS = 30;

function prune_jsonl(string $file, int $days): void
{
    if (!is_file($file)) return;
    $edge = time() - $days * 86400;
    $fh = @fopen($file, 'c+');
    if (!$fh || !flock($fh, LOCK_EX)) return;
    $keep = [];
    $dropped = 0;
    while (($line = fgets($fh)) !== false) {
        $r = json_decode($line, true);
        $at = is_array($r) ? (strtotime((string)($r['at'] ?? '')) ?: 0) : 0;
        if ($at >= $edge) $keep[] = rtrim($line, "\r\n");
        else $dropped++;
    }
    if ($dropped > 0) {
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, $keep ? implode("\n", $keep) . "\n" : '');
    }
    flock($fh, LOCK_UN);
    fclose($fh);
    if (is_file($file . '.old')) @unlink($file . '.old'); // старая копия спама после ротации
}

function prune_leads(string $dir): void
{
    prune_jsonl($dir . '/leads.jsonl', LEADS_KEEP_DAYS);
    prune_jsonl($dir . '/spam.jsonl', SPAM_KEEP_DAYS);
}
