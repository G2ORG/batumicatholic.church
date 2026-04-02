# batumicatholic.church — Контекст проекта для Claude Code

## Что это
Сайт католического прихода Святого Духа, Батуми, Грузия.
Замена WordPress на Bootstrap 5 + минимальный PHP (без БД, без Node.js).
**Типовой шаблон OPC** — потом тиражируется для других приходов.

## Хостинг
- Домен: batumicatholic.church
- Путь на сервере: `/home/enterpr2/batumicatholic.church/`
- PHP 8.1, Apache, shared hosting
- Admin BasicAuth: `/admin/.htpasswd` (НЕ коммитить в git!)
- Email прихода: batumicatholic@gmail.com

## Стек
- Bootstrap 5.3 + Vanilla JS (без сборщиков)
- PHP 8.1 (только для форм и API)
- Контент: JSON + Markdown файлы (без БД)
- Языки: **KA** (основной), EN, RU — i18n через `data-i18n` + `translations` объект в JS

## Структура
```
index.html          — главная (одностраничник с якорями)
about/              — история прихода
news/               — новости (index.html + load-news.php читает news/data/*.md)
gallery/            — галерея (gallery-api.php читает uploads/)
sermons/            — проповеди (серmons/data/*.md + аудио)
admin/              — CMS панель (BasicAuth защита)
data/schedule.json  — расписание (мультиязычный формат {ka,en,ru})
data/caritas/       — запросы каритас (НЕ публично!)
uploads/            — фото (liturgy/, events/, caritas/)
caritas-form.php    — обработка формы помощи
load-news.php       — JSON API новостей
load-events.php     — JSON API событий
gallery-api.php     — JSON API галереи
```

## Что сделано
- [x] Главная страница полностью (все секции)
- [x] i18n KA/EN/RU на главной и в /admin/
- [x] caritas-form.php (rate limit, email, сохранение)
- [x] .htaccess (HTTPS redirect, gzip, cache, security)
- [x] robots.txt + sitemap.xml
- [x] data/.htaccess (Apache 2.4/2.2 совместимый)
- [x] Vatican News виджет (RSS2JSON)
- [x] Admin дашборд i18n (плитки, таблица, кнопки)
- [x] schedule.json мультиязычный

## Что ещё нужно
- [ ] Stripe Payment Link → вставить в index.html (~строка 443)
- [ ] IBAN TBC Bank → вставить в index.html (~строка 433)
- [ ] Телефон прихода → секция #contact
- [ ] Расписание уточнить у о. Габриэле → data/schedule.json
- [ ] Реальные новости через /admin/
- [ ] Фото церкви в uploads/liturgy/
- [ ] Telegram канал embed (сейчас placeholder)
- [ ] Google Search Console → добавить sitemap

## Важные соглашения
- `data-i18n="ключ"` для всего переводимого текста
- Переводы в JS: объект `translations` в index.html, `ADMIN_LANG` в admin/index.html
- Новости в MD-формате с frontmatter: title_ka, title_en, title_ru, date, category, status
- Фото через admin → загружаются в /uploads/{album}/
- НЕ коммитить: admin/.htpasswd, uploads/, data/caritas/
