<?php
/**
 * load-events.php — Публичный API событий
 * GET ?lang=ka|en|ru&upcoming=1 (только будущие, по умолчанию)
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

$lang     = in_array($_GET['lang'] ?? '', ['ka','en','ru']) ? $_GET['lang'] : 'ka';
$upcoming = ($_GET['upcoming'] ?? '1') !== '0';

$dir   = __DIR__ . '/events/';
$files = glob($dir . '*.md') ?: [];

$events = [];

foreach ($files as $file) {
    $content = file_get_contents($file);
    if (!$content) continue;

    preg_match('/^---\s*([\s\S]*?)\s*---/s', $content, $fm);
    $front = $fm[1] ?? '';

    $get = function(string $key) use ($front): string {
        preg_match('/' . preg_quote($key) . ':\s*"?([^"\n]+)"?/i', $front, $m);
        return trim($m[1] ?? '');
    };

    $date = $get('date');
    if ($upcoming && $date && strtotime($date) < strtotime(date('Y-m-d'))) continue;

    $title = $get("title_{$lang}") ?: $get('title_ka') ?: basename($file);
    $body  = trim(preg_replace('/^---[\s\S]*?---\s*/s', '', $content));

    // Описание по языку
    $sep_en = "/\n===EN===\n/u";
    $sep_ru = "/\n===RU===\n/u";
    if ($lang === 'ru' && preg_match($sep_ru, $body)) {
        $desc = trim(preg_split($sep_ru, $body)[1] ?? '');
    } elseif ($lang === 'en' && preg_match($sep_en, $body)) {
        $desc = trim(preg_split($sep_en, preg_split($sep_ru, $body)[0])[1] ?? '');
    } else {
        $desc = trim(preg_split($sep_en, $body)[0]);
    }

    $events[] = [
        'filename' => basename($file),
        'title'    => $title,
        'date'     => $date,
        'time'     => $get('time'),
        'type'     => $get('type') ?: 'other',
        'location' => $get('location'),
        'description' => mb_substr(strip_tags($desc), 0, 200),
    ];
}

usort($events, fn($a, $b) => strtotime($a['date']) - strtotime($b['date']));

echo json_encode(['ok' => true, 'lang' => $lang, 'items' => $events], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
