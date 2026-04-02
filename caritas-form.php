<?php
/**
 * caritas-form.php — Обработка формы запроса помощи Каритас
 * POST: name, contact, type, lang, description
 */

header('Content-Type: application/json; charset=utf-8');

// Только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Rate limiting: не более 5 запросов с одного IP в час
$ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rl_file   = sys_get_temp_dir() . '/caritas_rl_' . md5($ip) . '.json';
$rl_data   = file_exists($rl_file) ? json_decode(file_get_contents($rl_file), true) : [];
$now       = time();
$rl_data   = array_filter($rl_data, fn($t) => $now - $t < 3600);
if (count($rl_data) >= 5) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many requests']);
    exit;
}
$rl_data[] = $now;
file_put_contents($rl_file, json_encode(array_values($rl_data)));

// Валидация и очистка
$name        = trim(htmlspecialchars(strip_tags($_POST['name']        ?? ''), ENT_QUOTES, 'UTF-8'));
$contact     = trim(htmlspecialchars(strip_tags($_POST['contact']     ?? ''), ENT_QUOTES, 'UTF-8'));
$type        = trim(htmlspecialchars(strip_tags($_POST['type']        ?? ''), ENT_QUOTES, 'UTF-8'));
$lang        = in_array($_POST['lang'] ?? '', ['ka','en','ru']) ? $_POST['lang'] : 'ka';
$description = trim(htmlspecialchars(strip_tags($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8'));

if (!$name || !$contact) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Name and contact are required']);
    exit;
}

if (mb_strlen($name) > 100 || mb_strlen($contact) > 200 || mb_strlen($description) > 2000) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Input too long']);
    exit;
}

// Сохранение в /data/caritas/
$data_dir = __DIR__ . '/data/caritas/';
if (!is_dir($data_dir)) {
    mkdir($data_dir, 0755, true);
}

$record = [
    'id'          => date('YmdHis') . '-' . substr(md5($ip . $now), 0, 6),
    'date'        => date('c'),
    'name'        => $name,
    'contact'     => $contact,
    'type'        => $type,
    'lang'        => $lang,
    'description' => $description,
    'ip'          => md5($ip),   // хэшируем IP для конфиденциальности
    'status'      => 'new',
];

$filename = $data_dir . date('Ymd-His') . '-' . substr(md5($name . $now), 0, 8) . '.json';
file_put_contents($filename, json_encode($record, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// Отправка email священнику
$to      = 'batumicatholic@gmail.com';
$subject = '=?UTF-8?B?' . base64_encode('[Caritas Batumi] Новый запрос помощи') . '?=';

$type_labels = [
    'საკვები'   => 'Продукты / Food',
    'სამედიცინო' => 'Медицина / Medical',
    'სასწავლო'  => 'Образование / Education',
    'საბინაო'   => 'Жильё / Housing',
    'სხვა'      => 'Другое / Other',
    'Продукты'  => 'Продукты / Food',
    'Медицина'  => 'Медицина / Medical',
    'Food'      => 'Food',
    'Medical'   => 'Medical',
];
$type_display = $type_labels[$type] ?? $type;

$body = "Новый запрос помощи Каритас\n";
$body .= str_repeat('─', 40) . "\n";
$body .= "Имя: {$name}\n";
$body .= "Контакт: {$contact}\n";
$body .= "Вид помощи: {$type_display}\n";
$body .= "Язык: {$lang}\n";
$body .= "Описание:\n{$description}\n";
$body .= str_repeat('─', 40) . "\n";
$body .= "Дата: " . date('d.m.Y H:i') . "\n";
$body .= "ID: {$record['id']}\n\n";
$body .= "Ответьте на этот запрос через: {$contact}\n";

$headers  = "From: noreply@batumicatholic.church\r\n";
$headers .= "Reply-To: {$to}\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: 8bit\r\n";

@mail($to, $subject, $body, $headers);

echo json_encode(['ok' => true, 'id' => $record['id']], JSON_UNESCAPED_UNICODE);
