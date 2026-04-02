<?php
/**
 * load-news.php — Публичный API новостей
 * Читает .md файлы из /news/data/ и отдаёт JSON
 * 
 * GET параметры:
 *   ?lang=ka|en|ru    — язык (default: ka)
 *   ?category=...     — фильтр по категории
 *   ?limit=12         — кол-во на страницу
 *   ?offset=0         — смещение для пагинации
 *   ?status=published — только опубликованные (default)
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60'); // кэш 1 минута

$lang     = in_array($_GET['lang'] ?? '', ['ka','en','ru']) ? $_GET['lang'] : 'ka';
$category = trim($_GET['category'] ?? '');
$limit    = min(max(intval($_GET['limit'] ?? 12), 1), 50);
$offset   = max(intval($_GET['offset'] ?? 0), 0);
$status   = ($_GET['status'] ?? 'published') === 'all' ? 'all' : 'published';

$dir = __DIR__ . '/news/data/';

// Совместимость со старым форматом (файлы прямо в /news/*.md)
$files = array_merge(
    glob($dir . '*.md') ?: [],
    glob(__DIR__ . '/news/*.md') ?: []
);

// Сортировка: новые сверху
usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

$news  = [];
$total = 0;

foreach ($files as $file) {
    $content = file_get_contents($file);
    if ($content === false) continue;

    // Парсим frontmatter
    preg_match('/^---\s*([\s\S]*?)\s*---/s', $content, $fm);
    $frontmatter = $fm[1] ?? '';

    $get = function(string $key) use ($frontmatter): string {
        preg_match('/' . preg_quote($key) . ':\s*"?([^"\n]+)"?/i', $frontmatter, $m);
        return trim($m[1] ?? '');
    };

    // Фильтр по статусу
    $st = $get('status') ?: 'published';
    if ($status === 'published' && $st !== 'published') continue;

    // Фильтр по категории
    $cat = $get('category');
    if ($category && $cat !== $category) continue;

    // Мультиязычные поля с фолбэком на ka
    $title   = $get("title_{$lang}")   ?: $get('title_ka')   ?: $get('title')   ?: basename($file);
    $excerpt = $get("excerpt_{$lang}") ?: $get('excerpt_ka') ?: $get('excerpt') ?: '';
    $date    = $get('date')   ?: date('Y-m-d', filemtime($file));
    $author  = $get('author') ?: '';
    $image   = $get('image')  ?: '';

    // Тело статьи
    $body_raw = trim(preg_replace('/^---[\s\S]*?---\s*/s', '', $content));

    // Разбить по языкам
    $sep_en = "/\n===EN===\n/u";
    $sep_ru = "/\n===RU===\n/u";

    if ($lang === 'ru' && preg_match($sep_ru, $body_raw)) {
        $parts = preg_split($sep_ru, $body_raw);
        $body  = trim($parts[1] ?? $parts[0]);
    } elseif ($lang === 'en' && preg_match($sep_en, $body_raw)) {
        $parts = preg_split($sep_en, $body_raw);
        $tmp   = trim($parts[1] ?? $parts[0]);
        // обрезать RU если есть
        $body  = trim(preg_split($sep_ru, $tmp)[0]);
    } else {
        $body = trim(preg_split($sep_en, $body_raw)[0]);
    }

    // Авто-excerpt из тела если не задан
    if (!$excerpt && $body) {
        $excerpt = mb_substr(strip_tags($body), 0, 160) . '…';
    }

    $total++;
    $news[] = [
        'filename' => basename($file),
        'title'    => $title,
        'excerpt'  => $excerpt,
        'body'     => $body,
        'date'     => $date,
        'date_fmt' => format_date($date, $lang),
        'category' => $cat ?: 'announce',
        'author'   => $author,
        'image'    => $image,
        'status'   => $st,
    ];
}

// Сортировка по дате
usort($news, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));

$total_count = count($news);
$page_items  = array_slice($news, $offset, $limit);

echo json_encode([
    'ok'     => true,
    'lang'   => $lang,
    'total'  => $total_count,
    'limit'  => $limit,
    'offset' => $offset,
    'items'  => $page_items,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// ── Форматирование даты по языку ──────────────────────────
function format_date(string $date, string $lang): string {
    $ts = strtotime($date);
    if (!$ts) return $date;

    $months_ka = ['','იანვ','თებ','მარ','აპრ','მაი','ივნ','ივლ','აგვ','სექ','ოქტ','ნოე','დეკ'];
    $months_ru = ['','янв','фев','мар','апр','май','июн','июл','авг','сен','окт','ноя','дек'];
    $months_en = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    $d = intval(date('j', $ts));
    $m = intval(date('n', $ts));
    $y = date('Y', $ts);

    return match($lang) {
        'ru'    => "{$d} {$months_ru[$m]} {$y}",
        'en'    => "{$months_en[$m]} {$d}, {$y}",
        default => "{$d} {$months_ka[$m]} {$y}",
    };
}
