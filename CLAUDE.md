# batumicatholic.church — Сайт католического прихода, Батуми

## Контекст
Сайт для прихода Святого Духа (Roman Catholic Parish), Батуми, Грузия.
Замена WordPress на Bootstrap 5 + минимальный PHP — без БД, без Node.js.
**Типовой шаблон OPC** — потом тиражируется для других приходов мира.

**Notion:** https://www.notion.so/33370d84f3158168a501fa470b8e484e
**GitHub:** https://github.com/G2ORG/batumicatholic.church
**Хостинг:** batumicatholic.church → `/home/enterpr2/batumicatholic.church/`

## Стек
- Bootstrap 5.3 + Vanilla JS (без сборщиков, без npm)
- PHP 8.1 на shared hosting (Apache, cPanel)
- Контент: JSON + Markdown файлы (без БД)
- Языки: **KA** (основной), EN, RU — i18n через `data-i18n` + объект `translations`

## Быстрый старт
```
# Открыть папку проекта
cd "C:\Users\user\Documents\Knights Errant Life\church Null\new Cloud WP\02042026"

# Деплой — вручную через FTP/хостинг-панель
# Git push:
git add -A && git commit -m "..." && git push
```

## Соглашения
- `data-i18n="ключ"` на всех переводимых элементах
- Переводы: объект `translations` в `index.html`, `ADMIN_LANG` в `admin/index.html`
- Новости в MD с frontmatter: `title_ka`, `title_en`, `title_ru`, `date`, `category`, `status`
- НЕ коммитить: `admin/.htpasswd`, `uploads/`, `data/caritas/`
- Контакт прихода: batumicatholic@gmail.com

## Структура ключевых файлов
```
index.html          — главная (Hero, расписание, новости, каритас, донаты, контакты)
about/index.html    — история прихода
news/index.html     — новости (читает news/data/*.md через load-news.php)
gallery/index.html  — галерея (читает uploads/ через gallery-api.php)
sermons/index.html  — проповеди (читает sermons/data/*.md)
admin/index.html    — CMS панель (BasicAuth, Quill WYSIWYG)
data/schedule.json  — расписание ({ka,en,ru} мультиязычный формат)
caritas-form.php    — форма запроса помощи (rate limit + email + JSON)
.htaccess           — HTTPS, gzip, cache, security headers
```

## currentStatus
*Обновлено: 2026-04-03*

- ✅ Главная страница — все секции (Hero, расписание, новости, каритас, донаты, контакты)
- ✅ i18n KA/EN/RU — главная + admin дашборд полностью
- ✅ caritas-form.php — rate limiting, email, сохранение в /data/caritas/
- ✅ .htaccess — HTTPS redirect, gzip, cache, security headers (Apache 2.4/2.2)
- ✅ robots.txt + sitemap.xml
- ✅ Vatican News виджет — RSS2JSON (заменён нерабочий iframe)
- ✅ data/schedule.json — мультиязычный формат, правильное расписание
- ✅ Admin дашборд i18n — плитки, таблица, кнопки, подсказки
- ✅ favicon.ico в корне, подключён во всех страницах
- ✅ GitHub: github.com/G2ORG/batumicatholic.church (3 коммита)

## nextSteps
1. **Загрузить на хостинг** изменённые файлы (список в памяти Claude Code)
2. **IBAN TBC Bank** — вставить реквизиты в `index.html` (~строка 433)
3. **Телефон прихода** — добавить в секцию `#contact` в `index.html`
4. Расписание уточнить у о. Габриэле → обновить `data/schedule.json`
5. Добавить первые реальные новости через `/admin/`

## Блокеры
- Нет реальных банковских реквизитов (IBAN) — вставить placeholder
- Расписание в JSON — тестовое, нужно подтверждение от священника
