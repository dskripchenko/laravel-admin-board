---
title: Начало работы
locale: ru
status: stable
---

# Начало работы

## 1. Установка

```bash
composer require dskripchenko/laravel-admin-board
php artisan migrate
```

Сервис-провайдер подключается автоматически и регистрирует плагин в
`config('admin.plugins')`. Миграции создают `admin_boards` и
`admin_board_batches`; чтобы скопировать их в приложение:

```bash
php artisan vendor:publish --tag=admin-board-migrations
```

## 2. Выдайте права

Ключи появляются в редакторе ролей панели в группе **Доски**:

| Ключ | Для кого |
|---|---|
| `admin.boards.view` | все, кто смотрит доски |
| `admin.boards.edit` | все, кто на них рисует |
| `admin.boards.create`, `admin.boards.update`, `admin.boards.delete` | тот, кто ведёт список |

У суперадминистратора (`*`) есть все.

## 3. Откройте панель

**Доски** — список досок. **Создать** спрашивает название; клик по названию
открывает доску. Рисовать могут несколько человек сразу, каждый видит курсоры
остальных.

## 4. Необязательно: конфиг

```bash
php artisan vendor:publish --tag=admin-board-config
```

| Ключ | По умолчанию | Что |
|---|---|---|
| `limits.max_ops` | 500 | операций в пакете; больше — отказ 422 |
| `limits.max_objects` | 10 000 | объектов на доске |
| `keep` | 10 000 | сколько пакетов хранится на доску для вернувшихся клиентов |
| `poll_limit` | 1 000 | пакетов в одном ответе на опрос |
| `presence.ttl` | 30 | секунд, сколько клиент остаётся в списке присутствия |
| `presence.store` | null | хранилище кэша для присутствия (null — по умолчанию) |
| `poll_interval` | 1500 | мс между опросами без realtime |
| `height` | 640 | высота доски на странице, px |
| `realtime.enabled` | null | null — включён, если установлен laravel-realtime; false — выключен |
| `realtime.ticket_url` | null | где браузер берёт билет realtime; null — маршрут `realtime.ticket` |
| `widget.board` | null | доска виджета дашборда; null — виджета нет |
| `widget.title` | Доска | заголовок виджета |

## 5. Необязательно: realtime

Установите и запустите [`dskripchenko/laravel-realtime`](https://github.com/dskripchenko/laravel-realtime) —
доски начнут пользоваться им без дальнейшей настройки: изменения и присутствие
приходят по WebSocket, а опрос нужен только чтобы догнать пропущенное после
переподключения. Какой guard нужен билету — в
[Использование → Realtime](usage.md#realtime).
