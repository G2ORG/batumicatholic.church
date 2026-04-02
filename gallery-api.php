<?php
/**
 * gallery-api.php — API для фотогалереи
 * Сканирует /uploads/ и возвращает JSON с фото и альбомами
 *
 * GET ?album=all|liturgy|caritas|events|church
 * GET ?album=church&limit=12&offset=0
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=120');

define('UPLOADS_DIR', __DIR__ . '/uploads/');
define('UPLOADS_URL', '/uploads/');

// Разрешённые расширения
$allowed_ext = ['jpg','jpeg','png','webp','gif'];

$album  = trim($_GET['album']  ?? 'all');
$limit  = min(max(intval($_GET['limit']  ?? 48), 1), 100);
$offset = max(intval($_GET['offset'] ?? 0), 0);

if (!is_dir(UPLOADS_DIR)) {
    echo json_encode(['ok'=>true,'total'=>0,'items'=>[],'albums'=>[]]);
    exit;
}

// ── Читаем все файлы ──────────────────────────────────────
$all_files = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(UPLOADS_DIR, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile()) continue;

    $ext = strtolower($file->getExtension());
    if (!in_array($ext, $allowed_ext)) continue;

    $real_path  = $file->getRealPath();
    $rel_path   = str_replace(UPLOADS_DIR, '', $real_path);
    $rel_path   = str_replace('\\', '/', $rel_path); // Windows safe

    // Определяем альбом: первая папка в пути или 'church' по умолчанию
    $parts      = explode('/', $rel_path);
    $file_album = count($parts) > 1 ? $parts[0] : 'church';

    // Нормализуем альбом к известным значениям
    $known = ['liturgy','caritas','events','church'];
    if (!in_array($file_album, $known)) $file_album = 'church';

    // Мета из имени файла (формат: YYYY-MM-DD-title.jpg)
    $basename = pathinfo($rel_path, PATHINFO_FILENAME);
    $title    = preg_replace('/^\d{4}-\d{2}-\d{2}-?/', '', $basename);
    $title    = str_replace(['-','_'], ' ', $title);
    $title    = ucfirst(trim($title)) ?: $basename;

    $all_files[] = [
        'src'     => UPLOADS_URL . $rel_path,
        'title'   => $title,
        'album'   => $file_album,
        'size'    => $file->getSize(),
        'mtime'   => $file->getMTime(),
        'filename'=> basename($rel_path),
    ];
}

// Сортировка: новые сверху
usort($all_files, fn($a,$b) => $b['mtime'] - $a['mtime']);

// ── Список альбомов (для фильтра) ─────────────────────────
$album_counts = [];
foreach ($all_files as $f) {
    $album_counts[$f['album']] = ($album_counts[$f['album']] ?? 0) + 1;
}

// ── Фильтр ────────────────────────────────────────────────
$filtered = $album === 'all'
    ? $all_files
    : array_values(array_filter($all_files, fn($f) => $f['album'] === $album));

$total      = count($filtered);
$page_items = array_slice($filtered, $offset, $limit);

// Убираем mtime из ответа (не нужен клиенту)
$page_items = array_map(function($f) {
    unset($f['mtime'], $f['size']);
    return $f;
}, $page_items);

echo json_encode([
    'ok'     => true,
    'total'  => $total,
    'limit'  => $limit,
    'offset' => $offset,
    'album'  => $album,
    'albums' => $album_counts,
    'items'  => $page_items,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
