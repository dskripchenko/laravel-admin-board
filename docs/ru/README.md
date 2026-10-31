# dskripchenko/laravel-admin-board

> 🌐 [English](../../README.md) · **Русский** · [Deutsch](../de/README.md) · [中文](../zh/README.md)

Совместные доски для админ-панели: отдельная доска или доска у каждой
записи, которую правят несколько человек одновременно. Порядок всех
изменений задаёт сервер, поэтому у всех получается одна и та же доска;
присутствие показывает, кто сейчас на доске и где его курсор. Если установлен
[`dskripchenko/laravel-realtime`](https://github.com/dskripchenko/laravel-realtime),
изменения приходят по WebSocket; без него доска опрашивает сервер.

Sister-pack для [`dskripchenko/laravel-admin`](https://github.com/dskripchenko/laravel-admin).
Сам редактор — `BoardEditor` из
[`@dskripchenko/board`](https://www.npmjs.com/package/@dskripchenko/board), его рисует
раскладка ядра `board`; этот пакет хранит доски и обслуживает их API.

[![Packagist](https://img.shields.io/packagist/v/dskripchenko/laravel-admin-board)](https://packagist.org/packages/dskripchenko/laravel-admin-board)
[![License](https://img.shields.io/packagist/l/dskripchenko/laravel-admin-board)](../../LICENSE)

## Установка

```bash
composer require dskripchenko/laravel-admin-board
php artisan migrate
```

Провайдер регистрируется сам. Выдайте права ролям, которым они нужны.

## Что вы получаете

| Где | Что |
|---|---|
| **Доски** | список досок: название, число изменений, заблокирована ли; создание по названию, переименование, удаление. Название открывает доску. |
| **Страница доски** (`/screens/board?id=…`) | доска; без `admin.boards.edit` — только просмотр. |
| **Доска записи** | `HasBoard` на любой модели: `$project->boardLayout()` на её экранах, доска создаётся при первом обращении. |
| **Дашборд** | `BoardWidget` показывает доску из `admin-board.widget.board`. |

## Права

| Ключ | Что разрешает |
|---|---|
| `admin.boards.view` | открывать доски и список, видеть, кто на доске |
| `admin.boards.edit` | рисовать на досках (без него сервер отклоняет изменения) |
| `admin.boards.create` / `.update` / `.delete` | вести список: новые доски, переименование, удаление |

Хост сужает доступ к отдельной доске через `BoardAccess::authorizeUsing()` —
например, доска записи видна только тем, кто может смотреть саму запись.

## Документация

- [Начало работы](getting-started.md)
- [Использование](usage.md): записи, доступ, API, realtime, лимиты

## Требования

PHP 8.2–8.5, Laravel 11 / 12 / 13, `dskripchenko/laravel-admin` ^1.68.
Необязательно: `dskripchenko/laravel-realtime`.

## Лицензия

MIT
