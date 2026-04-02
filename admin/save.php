<?php
/**
 * save.php — API бэкенд Admin-панели
 * Приход Святого Духа, Батуми
 * 
 * Поддерживает: news, event, sermon, schedule, delete, upload_image
 * Формат хранения: .md файлы с YAML frontmatter (ka/en/ru)
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// ── Базовые пути ──────────────────────────────────────────
define('BASE',    dirname(__DIR__) . '/');
define('UPLOADS', BASE . 'uploads/');
define('NEWS',    BASE . 'news/data/');
define('EVENTS',  BASE . 'events/');
define('SERMONS', BASE . 'sermons/data/');
define('SCHED',   BASE . 'data/schedule.json');

// ── Создать папки если нет ────────────────────────────────
foreach ([UPLOADS, NEWS, EVENTS, SERMONS, BASE.'data/'] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

// ── Вспомогательные функции ───────────────────────────────

function respond($ok, $message, $data = []) {
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $data));
    exit;
}

function clean($str) {
    return htmlspecialchars(strip_tags(trim($str ?? '')), ENT_QUOTES, 'UTF-8');
}

function slug($str) {
    $str = mb_strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9\-_]/u', '-', $str);
    $str = preg_replace('/-+/', '-', $str);
    return trim($str, '-') ?: 'item';
}

/**
 * Загрузка изображения с ресайзом через GD
 * Возвращает URL относительно корня сайта
 */
function upload_image($file) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return '';

    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowed)) return '';
    if ($file['size'] > 8 * 1024 * 1024) return ''; // 8MB max

    $ext  = match($mime) {
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => 'jpg'
    };
    $name = date('Y-m-d-His') . '-' . substr(md5_file($file['tmp_name']), 0, 8) . '.' . $ext;
    $dest = UPLOADS . $name;

    // Ресайз до 1200px по ширине (сохраняем пропорции)
    if (function_exists('imagecreatefromstring')) {
        $src = imagecreatefromstring(file_get_contents($file['tmp_name']));
        if ($src) {
            $ow = imagesx($src);
            $oh = imagesy($src);
            $maxW = 1200;
            if ($ow > $maxW) {
                $nw = $maxW;
                $nh = intval($oh * $maxW / $ow);
                $dst = imagecreatetruecolor($nw, $nh);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $ow, $oh);
                imagejpeg($dst, $dest, 85);
                imagedestroy($src);
                imagedestroy($dst);
            } else {
                move_uploaded_file($file['tmp_name'], $dest);
            }
        } else {
            move_uploaded_file($file['tmp_name'], $dest);
        }
    } else {
        move_uploaded_file($file['tmp_name'], $dest);
    }

    return 'uploads/' . $name;
}

/**
 * Запись .md файла с YAML frontmatter
 */
function write_md($folder, $filename, array $front, string $body_ka, string $body_en = '', string $body_ru = '') {
    $yaml = "---\n";
    foreach ($front as $k => $v) {
        $v = str_replace('"', '\\"', $v);
        $yaml .= "{$k}: \"{$v}\"\n";
    }
    $yaml .= "---\n\n";

    $body  = $body_ka;
    if ($body_en) $body .= "\n\n===EN===\n\n" . $body_en;
    if ($body_ru) $body .= "\n\n===RU===\n\n" . $body_ru;

    if (!is_dir($folder)) mkdir($folder, 0755, true);
    return file_put_contents($folder . $filename, $yaml . $body) !== false;
}

/**
 * Удаление файла по имени и типу
 */
function delete_file($type, $filename) {
    $dirs = ['news' => NEWS, 'event' => EVENTS, 'sermon' => SERMONS];
    if (!isset($dirs[$type])) return false;
    $path = $dirs[$type] . basename($filename); // basename — защита от path traversal
    if (file_exists($path)) return unlink($path);
    return false;
}

// ─────────────────────────────────────────────────────────
// МАРШРУТИЗАЦИЯ
// ─────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Загрузка изображения (multipart) ──
if ($method === 'POST' && $action === 'upload_image') {
    if (empty($_FILES['image'])) respond(false, 'Файл не получен');
    $url = upload_image($_FILES['image']);
    if (!$url) respond(false, 'Ошибка загрузки. Проверьте формат и размер (до 8МБ).');
    respond(true, 'Фото загружено', ['url' => $url]);
}

// ── Сохранение новости ──
if ($method === 'POST' && $action === 'save_news') {
    $id       = clean($_POST['id'] ?? '');          // если редактирование
    $title_ka = clean($_POST['title_ka'] ?? '');
    $title_en = clean($_POST['title_en'] ?? '');
    $title_ru = clean($_POST['title_ru'] ?? '');
    $exc_ka   = clean($_POST['excerpt_ka'] ?? '');
    $exc_en   = clean($_POST['excerpt_en'] ?? '');
    $exc_ru   = clean($_POST['excerpt_ru'] ?? '');
    $body_ka  = trim($_POST['body_ka'] ?? '');
    $body_en  = trim($_POST['body_en'] ?? '');
    $body_ru  = trim($_POST['body_ru'] ?? '');
    $date     = clean($_POST['date'] ?? date('Y-m-d'));
    $category = clean($_POST['category'] ?? 'announce');
    $author   = clean($_POST['author'] ?? 'Fr. Gabriele Bragantini');
    $status   = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $image    = clean($_POST['image'] ?? '');

    if (!$title_ka) respond(false, 'Заголовок (KA) обязателен');

    // Загрузить новое фото если приложено
    if (!empty($_FILES['image']['tmp_name'])) {
        $uploaded = upload_image($_FILES['image']);
        if ($uploaded) $image = $uploaded;
    }

    // Имя файла
    if ($id) {
        $filename = basename($id);   // редактирование — тот же файл
    } else {
        $filename = $date . '-' . slug($title_ka) . '.md';
    }

    $front = [
        'title_ka'  => $title_ka,
        'title_en'  => $title_en,
        'title_ru'  => $title_ru,
        'excerpt_ka'=> $exc_ka,
        'excerpt_en'=> $exc_en,
        'excerpt_ru'=> $exc_ru,
        'date'      => $date,
        'category'  => $category,
        'author'    => $author,
        'status'    => $status,
        'image'     => $image,
    ];

    if (write_md(NEWS, $filename, $front, $body_ka, $body_en, $body_ru)) {
        respond(true, $status === 'published' ? 'Новость опубликована!' : 'Черновик сохранён', ['filename' => $filename]);
    } else {
        respond(false, 'Ошибка записи файла. Проверьте права на папку /news/data/');
    }
}

// ── Сохранение события ──
if ($method === 'POST' && $action === 'save_event') {
    $title_ka = clean($_POST['title_ka'] ?? '');
    $title_en = clean($_POST['title_en'] ?? '');
    $title_ru = clean($_POST['title_ru'] ?? '');
    $desc_ka  = trim($_POST['desc_ka']  ?? '');
    $desc_en  = trim($_POST['desc_en']  ?? '');
    $desc_ru  = trim($_POST['desc_ru']  ?? '');
    $date     = clean($_POST['date']     ?? '');
    $time     = clean($_POST['time']     ?? '');
    $type     = clean($_POST['type']     ?? 'other');
    $location = clean($_POST['location'] ?? 'სულიწმიდის ეკლესია, ბათუმი');

    if (!$title_ka || !$date) respond(false, 'Название (KA) и дата обязательны');

    $filename = $date . '-' . slug($title_ka) . '.md';
    $front = [
        'title_ka'  => $title_ka,
        'title_en'  => $title_en,
        'title_ru'  => $title_ru,
        'date'      => $date,
        'time'      => $time,
        'type'      => $type,
        'location'  => $location,
        'status'    => 'published',
    ];

    if (write_md(EVENTS, $filename, $front, $desc_ka, $desc_en, $desc_ru)) {
        respond(true, 'Событие добавлено!', ['filename' => $filename]);
    } else {
        respond(false, 'Ошибка записи файла');
    }
}

// ── Сохранение проповеди ──
if ($method === 'POST' && $action === 'save_sermon') {
    $title_ka = clean($_POST['title_ka'] ?? '');
    $title_en = clean($_POST['title_en'] ?? '');
    $title_ru = clean($_POST['title_ru'] ?? '');
    $body_ka  = trim($_POST['body_ka']  ?? '');
    $date     = clean($_POST['date']     ?? date('Y-m-d'));
    $lang     = clean($_POST['lang']     ?? 'ka');
    $preacher = clean($_POST['preacher'] ?? 'Fr. Gabriele Bragantini');
    $context  = clean($_POST['context']  ?? '');
    $audio    = clean($_POST['audio']    ?? '');
    $youtube  = clean($_POST['youtube']  ?? '');

    // Загрузка аудиофайла
    if (!empty($_FILES['audio_file']['tmp_name']) && is_uploaded_file($_FILES['audio_file']['tmp_name'])) {
        $allowed_audio = ['audio/mpeg','audio/mp4','audio/ogg','audio/x-m4a'];
        $mime = mime_content_type($_FILES['audio_file']['tmp_name']);
        if (in_array($mime, $allowed_audio) && $_FILES['audio_file']['size'] < 50*1024*1024) {
            $aname = $date . '-' . slug($title_ka) . '.mp3';
            $adest = BASE . 'sermons/audio/' . $aname;
            if (!is_dir(BASE.'sermons/audio/')) mkdir(BASE.'sermons/audio/', 0755, true);
            if (move_uploaded_file($_FILES['audio_file']['tmp_name'], $adest)) {
                $audio = 'sermons/audio/' . $aname;
            }
        }
    }

    if (!$title_ka) respond(false, 'Название проповеди (KA) обязательно');

    $filename = $date . '-' . slug($title_ka) . '.md';
    $front = [
        'title_ka' => $title_ka,
        'title_en' => $title_en,
        'title_ru' => $title_ru,
        'date'     => $date,
        'lang'     => $lang,
        'preacher' => $preacher,
        'context'  => $context,
        'audio'    => $audio,
        'youtube'  => $youtube,
        'status'   => 'published',
    ];

    if (write_md(SERMONS, $filename, $front, $body_ka)) {
        respond(true, 'Проповедь сохранена!', ['filename' => $filename, 'audio' => $audio]);
    } else {
        respond(false, 'Ошибка записи файла');
    }
}

// ── Сохранение расписания ──
if ($method === 'POST' && $action === 'save_schedule') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) respond(false, 'Неверный формат данных');

    // Базовая валидация
    $clean = [];
    foreach ($data as $row) {
        if (!isset($row['day'], $row['time'])) continue;
        $clean[] = [
            'day'    => clean($row['day']),
            'time'   => clean($row['time']),
            'type'   => clean($row['type'] ?? 'Месса'),
            'langs'  => array_map('clean', (array)($row['langs'] ?? [])),
            'note'   => clean($row['note'] ?? ''),
        ];
    }

    if (file_put_contents(SCHED, json_encode(['updated' => date('c'), 'rows' => $clean], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT))) {
        respond(true, 'Расписание сохранено!');
    } else {
        respond(false, 'Ошибка записи расписания');
    }
}

// ── Удаление ──
if ($method === 'POST' && $action === 'delete') {
    $type     = clean($_POST['type'] ?? '');
    $filename = clean($_POST['filename'] ?? '');
    if (!$type || !$filename) respond(false, 'Не указан тип или имя файла');
    if (delete_file($type, $filename)) {
        respond(true, 'Удалено');
    } else {
        respond(false, 'Файл не найден или нет прав');
    }
}

// ── Получить список новостей (для таблицы в админке) ──
if ($method === 'GET' && $action === 'list_news') {
    $files = glob(NEWS . '*.md') ?: [];
    rsort($files);
    $items = [];
    foreach (array_slice($files, 0, 50) as $f) {
        $c = file_get_contents($f);
        preg_match('/title_ka:\s*"?([^"\n]+)"?/i', $c, $tka);
        preg_match('/date:\s*"?([^"\n]+)"?/i',     $c, $dt);
        preg_match('/category:\s*"?([^"\n]+)"?/i', $c, $cat);
        preg_match('/status:\s*"?([^"\n]+)"?/i',   $c, $st);
        preg_match('/image:\s*"?([^"\n]+)"?/i',    $c, $img);
        $items[] = [
            'filename' => basename($f),
            'title'    => trim($tka[1] ?? basename($f)),
            'date'     => trim($dt[1]  ?? ''),
            'category' => trim($cat[1] ?? ''),
            'status'   => trim($st[1]  ?? 'draft'),
            'image'    => trim($img[1] ?? ''),
        ];
    }
    echo json_encode(['ok'=>true,'items'=>$items]);
    exit;
}

// ── Получить одну новость для редактирования ──
if ($method === 'GET' && $action === 'get_news') {
    $filename = basename($_GET['filename'] ?? '');
    $path = NEWS . $filename;
    if (!$filename || !file_exists($path)) respond(false, 'Файл не найден');

    $c = file_get_contents($path);
    preg_match('/title_ka:\s*"?([^"\n]+)"?/i',   $c, $tka);
    preg_match('/title_en:\s*"?([^"\n]+)"?/i',   $c, $ten);
    preg_match('/title_ru:\s*"?([^"\n]+)"?/i',   $c, $tru);
    preg_match('/excerpt_ka:\s*"?([^"\n]+)"?/i', $c, $eka);
    preg_match('/excerpt_en:\s*"?([^"\n]+)"?/i', $c, $een);
    preg_match('/excerpt_ru:\s*"?([^"\n]+)"?/i', $c, $eru);
    preg_match('/date:\s*"?([^"\n]+)"?/i',        $c, $dt);
    preg_match('/category:\s*"?([^"\n]+)"?/i',    $c, $cat);
    preg_match('/author:\s*"?([^"\n]+)"?/i',      $c, $aut);
    preg_match('/status:\s*"?([^"\n]+)"?/i',      $c, $st);
    preg_match('/image:\s*"?([^"\n]+)"?/i',       $c, $img);

    // Тело
    $body_raw = trim(preg_replace('/^---[\s\S]*?---\s*/s', '', $c));
    $parts = preg_split('/\n===(?:EN|RU)===\n/u', $body_raw);

    respond(true, 'ok', [
        'filename'   => $filename,
        'title_ka'   => trim($tka[1] ?? ''), 'title_en' => trim($ten[1] ?? ''), 'title_ru' => trim($tru[1] ?? ''),
        'excerpt_ka' => trim($eka[1] ?? ''), 'excerpt_en'=> trim($een[1] ?? ''), 'excerpt_ru'=> trim($eru[1] ?? ''),
        'body_ka'    => trim($parts[0] ?? ''),
        'body_en'    => trim($parts[1] ?? ''),
        'body_ru'    => trim($parts[2] ?? ''),
        'date'       => trim($dt[1]  ?? ''),
        'category'   => trim($cat[1] ?? ''),
        'author'     => trim($aut[1] ?? ''),
        'status'     => trim($st[1]  ?? 'draft'),
        'image'      => trim($img[1] ?? ''),
    ]);
}

// ── Список проповедей для таблицы ──
if ($method === 'GET' && $action === 'list_sermons') {
    $files = glob(SERMONS . '*.md') ?: [];
    rsort($files);
    $items = [];
    foreach (array_slice($files, 0, 30) as $f) {
        $c = file_get_contents($f);
        preg_match('/title_ka:\s*"?([^"\n]+)"?/i',  $c, $tka);
        preg_match('/date:\s*"?([^"\n]+)"?/i',       $c, $dt);
        preg_match('/lang:\s*"?([^"\n]+)"?/i',       $c, $lg);
        preg_match('/audio:\s*"?([^"\n]+)"?/i',      $c, $au);
        preg_match('/preacher:\s*"?([^"\n]+)"?/i',   $c, $pr);
        $items[] = [
            'filename' => basename($f),
            'title'    => trim($tka[1] ?? basename($f)),
            'date'     => trim($dt[1]  ?? ''),
            'lang'     => trim($lg[1]  ?? 'ka'),
            'audio'    => trim($au[1]  ?? ''),
            'preacher' => trim($pr[1]  ?? ''),
        ];
    }
    echo json_encode(['ok'=>true,'items'=>$items]);
    exit;
}

// ── Список событий ──
if ($method === 'GET' && $action === 'list_events') {
    $files = glob(EVENTS . '*.md') ?: [];
    sort($files);
    $items = [];
    foreach ($files as $f) {
        $c = file_get_contents($f);
        preg_match('/title_ka:\s*"?([^"\n]+)"?/i', $c, $tka);
        preg_match('/date:\s*"?([^"\n]+)"?/i',      $c, $dt);
        preg_match('/time:\s*"?([^"\n]+)"?/i',      $c, $tm);
        if (!empty($dt[1]) && strtotime($dt[1]) >= strtotime(date('Y-m-d'))) {
            $items[] = [
                'filename' => basename($f),
                'title'    => trim($tka[1] ?? ''),
                'date'     => trim($dt[1]  ?? ''),
                'time'     => trim($tm[1]  ?? ''),
            ];
        }
    }
    echo json_encode(['ok'=>true,'items'=>$items]);
    exit;
}

// ── Загрузить расписание ──
if ($method === 'GET' && $action === 'get_schedule') {
    if (file_exists(SCHED)) {
        echo file_get_contents(SCHED);
    } else {
        echo json_encode(['rows' => []]);
    }
    exit;
}

respond(false, 'Неизвестное действие: ' . $action);
