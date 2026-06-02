<?php
/**
 * load-schedule.php — публичный API расписания богослужений.
 * Читает приватный data/schedule.json и отдаёт только валидированные поля.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

$lang = in_array($_GET['lang'] ?? 'all', ['ka', 'en', 'ru', 'all'], true) ? $_GET['lang'] : 'all';
$file = __DIR__ . '/data/schedule.json';
$exceptionsFile = __DIR__ . '/data/schedule-exceptions.json';

if (!is_file($file) || !is_readable($file)) {
    echo json_encode(['ok' => true, 'updated' => null, 'rows' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode(file_get_contents($file), true);
if (!is_array($payload)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Schedule data is invalid'], JSON_UNESCAPED_UNICODE);
    exit;
}

function schedule_text($value, string $lang) {
    if (is_array($value)) {
        $clean = [
            'ka' => trim(strip_tags((string)($value['ka'] ?? ''))),
            'en' => trim(strip_tags((string)($value['en'] ?? ''))),
            'ru' => trim(strip_tags((string)($value['ru'] ?? ''))),
        ];
        if ($lang === 'all') return $clean;
        return $clean[$lang] ?: $clean['ka'] ?: $clean['en'] ?: $clean['ru'] ?: '—';
    }
    return trim(strip_tags((string)$value));
}

function schedule_row(array $row, string $lang) {
    $time = trim(strip_tags((string)($row['time'] ?? '')));
    if ($time === '') return null;

    $langs = array_values(array_intersect(
        array_map(fn($v) => strtoupper(trim(strip_tags((string)$v))), (array)($row['langs'] ?? [])),
        ['KA', 'EN', 'RU', 'PL', 'LA', 'IT']
    ));

    return [
        'day' => schedule_text($row['day'] ?? '', $lang),
        'time' => $time,
        'type' => schedule_text($row['type'] ?? '', $lang),
        'langs' => $langs,
        'note' => schedule_text($row['note'] ?? '', $lang),
    ];
}

$rows = [];
foreach (($payload['rows'] ?? []) as $row) {
    if (!is_array($row)) continue;
    $cleanRow = schedule_row($row, $lang);
    if ($cleanRow) $rows[] = $cleanRow;
}

$exceptions = [];
if (is_file($exceptionsFile) && is_readable($exceptionsFile)) {
    $exceptionPayload = json_decode(file_get_contents($exceptionsFile), true);
    if (is_array($exceptionPayload)) {
        $today = date('Y-m-d');
        foreach (($exceptionPayload['items'] ?? []) as $item) {
            if (!is_array($item)) continue;
            $date = trim(strip_tags((string)($item['date'] ?? '')));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < $today) continue;
            $cleanItem = schedule_row($item, $lang);
            if (!$cleanItem) continue;
            $cleanItem['date'] = $date;
            $cleanItem['title'] = schedule_text($item['title'] ?? '', $lang);
            $cleanItem['status'] = in_array(($item['status'] ?? 'changed'), ['added', 'changed', 'cancelled'], true) ? $item['status'] : 'changed';
            $exceptions[] = $cleanItem;
        }
    }
}
usort($exceptions, fn($a, $b) => strcmp($a['date'], $b['date']));

echo json_encode([
    'ok' => true,
    'updated' => $payload['updated'] ?? null,
    'lang' => $lang,
    'rows' => $rows,
    'exceptions' => $exceptions,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
