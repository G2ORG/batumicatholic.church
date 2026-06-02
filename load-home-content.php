<?php
/** Public homepage content API. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
header('X-Content-Type-Options: nosniff');

$file = __DIR__ . '/data/home-content.json';
if (!is_file($file) || !is_readable($file)) {
    echo json_encode(['ok' => true, 'updated' => null, 'fields' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode(file_get_contents($file), true);
if (!is_array($data)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Homepage content is invalid'], JSON_UNESCAPED_UNICODE);
    exit;
}

$fields = [];
foreach (($data['fields'] ?? []) as $key => $value) {
    if (!preg_match('/^[a-z0-9_.-]+$/i', (string)$key) || !is_array($value)) continue;
    $fields[$key] = [
        'ka' => trim(strip_tags((string)($value['ka'] ?? ''))),
        'en' => trim(strip_tags((string)($value['en'] ?? ''))),
        'ru' => trim(strip_tags((string)($value['ru'] ?? ''))),
    ];
}

echo json_encode([
    'ok' => true,
    'updated' => $data['updated'] ?? null,
    'fields' => $fields,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
