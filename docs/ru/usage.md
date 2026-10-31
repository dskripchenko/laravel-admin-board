---
title: Использование
locale: ru
status: stable
---

# Использование

## Доска записи

Добавьте модели `HasBoard`, и её экраны смогут показывать доску записи:

```php
use Dskripchenko\LaravelAdminBoard\Concerns\HasBoard;

class Project extends Model
{
    use HasBoard;

    // Необязательно: название новой доски.
    public function boardTitle(): string
    {
        return 'Доска проекта: '.$this->name;
    }
}

// layout() экрана
public function layout(): array
{
    return [$this->project->boardLayout()];
}
```

Доска создаётся при первом обращении. У записи одна доска (`board()` —
`morphOne`; уникальный индекс не даёт двум первым визитам создать две).

## Доска где угодно

`BoardLayout::for()` строит раскладку для любой доски:

```php
use Dskripchenko\LaravelAdminBoard\Support\BoardLayout;

return [BoardLayout::for($board, title: 'Ретро', height: 480)];
```

Если в ядре есть `Layout::board()`, используется он, иначе — раскладка типа
`board` с теми же свойствами; ядро рисует обе. `BoardLayout::props()`
возвращает только свойства:

| Свойство | Что |
|---|---|
| `endpoint` | путь API доски относительно API-клиента панели, `/boards/42` |
| `readonly` | true без права правки (сервер всё равно проверяет) |
| `user` | `{ id, name, color }` — цвет выводится из id и постоянен для пользователя |
| `realtime` | `{ ticketUrl, channel }` или null без realtime |
| `pollInterval` | мс между опросами без realtime |
| `title`, `height` | как у остальных раскладок |

## На дашборде

Укажите доску, и виджет появится среди виджетов дашборда:

```php
// config/admin-board.php
'widget' => ['board' => 42, 'title' => 'Доска команды'],
```

Чтобы показывать другую доску — например, у каждой команды свою, —
унаследуйте `BoardWidget`, переопределите `board()` и зарегистрируйте свой
класс через `$admin->widgets([...])`.

## Доступ

Сначала право: `admin.boards.view`, чтобы видеть, и ещё `admin.boards.edit`,
чтобы рисовать. Хост сужает его для отдельной доски, например в
сервис-провайдере:

```php
use Dskripchenko\LaravelAdminBoard\Access\BoardAccess;
use Dskripchenko\LaravelAdminBoard\Models\Board;
use Illuminate\Support\Facades\Gate;

BoardAccess::authorizeUsing(function (object $user, Board $board, string $ability): ?bool {
    $owner = $board->owner;              // запись доски из HasBoard

    return $owner === null
        ? null                           // отдельная доска: решает право
        : Gate::forUser($user)->allows($ability === 'edit' ? 'update' : 'view', $owner);
});

// Список досок: например, только отдельные доски.
BoardAccess::scopeUsing(fn ($boards, object $user) => $boards->whereNull('owner_type'));
```

Колбэк возвращает false, чтобы отказать; true или null оставляют ответ права.
Он же охраняет канал realtime, который работает вне запроса, поэтому
пользоваться нужно переданным `$user`, а не `auth()`.

## API

Раскладка доски ядра обращается к этим адресам через API-клиент панели —
POST JSON, конверт панели `{ success, payload }`:

| Запрос | Тело | Ответ |
|---|---|---|
| `{endpoint}/snapshot` | — | `{ board, seq }` |
| `{endpoint}/batches` | `{ client, cseq, ops }` | `{ seq }`; повтор получает тот же `seq`; 403 без права правки; 422 сверх лимитов или при ошибке формата |
| `{endpoint}/poll` | `{ after }` | `{ batches }` с `seq > after`; 410, если `after` старше журнала (клиент перезагружает снимок) |
| `{endpoint}/presence` | `{ client, cursor?, selection? }` | `{ presence }` — все, кого сервер видел за 30 с |

С префиксом по умолчанию адреса — `/api/admin/boards/{id}/{action}`; у другой
панели — `/api/{panel}/boards/{id}/{action}`. Это отдельные маршруты рядом с
маршрутами laravel-api (в его шаблоне `{controller}/{action}` нет места для
id), и они проходят тот же стек, что и собственный API панели: группу
laravel-api с middleware панели (сессия, CSRF, локаль, аутентификация на guard
панели), затем глобальные middleware API-класса панели (throttle, демо-режим,
добавки панели вроде слоя тенанта).

От кого пришли присутствие или пакет, берётся из сессии; `user` в теле
игнорируется.

### Семантика

Как у эталонного `BoardServer` из `@dskripchenko/board`: порядок задаёт
сервер; `set` — последняя запись в этом порядке; `delete` побеждает;
заблокированная доска (`meta.locked`) принимает только операции `meta` (чтобы
её можно было разблокировать), остальные отбрасываются для всех клиентов
одинаково; не больше 500 операций в пакете и 10 000 объектов на доске. Каждый
пакет принимается в транзакции, держащей блокировку строки доски, поэтому
номера идут подряд при любой конкуренции.

Единственное отличие от эталона: последний номер пакета клиента (чтобы
узнавать повторы) помнится, пока в журнале есть хоть один пакет этого
клиента — по умолчанию это 10 000 пакетов.

## Realtime

Если установлен `dskripchenko/laravel-realtime`, после нового пакета сервер
публикует событие `batch` (пакет с его `seq`), а после отчёта о присутствии —
событие `presence` (весь список) в приватный канал `admin-board.{id}`. Канал
авторизуется той же проверкой просмотра, что и API. Раскладка получает
`realtime: { ticketUrl, channel }`, и браузер подписывается через
`RealtimeClient` из `@dskripchenko/realtime`.

Билет выдаётся пользователю guard'а `realtime.ticket.guard`. Пользователи
панели входят на guard панели (по умолчанию `admin`), поэтому направьте билет
туда:

```dotenv
REALTIME_GUARD=admin
```

и дайте маршруту билета сессию панели (`realtime.routes.middleware`,
например `['web', 'auth:admin']`). `admin-board.realtime.ticket_url`
переопределяет адрес; `admin-board.realtime.enabled = false` выключает
realtime. Сбой брокера не роняет изменение: оно сохранено, и клиенты догоняют
опросом.

## Лимиты и нагрузка

Без realtime каждая открытая доска опрашивает сервер раз в `poll_interval` мс и
сообщает о присутствии. У маршрутов доски свой лимит запросов,
`admin-board.throttle` (по умолчанию 1800 в минуту на пользователя), он
считается отдельно от `admin.api.throttle` API панели: отчёты о курсоре, до
десяти в секунду, израсходовали бы лимит панели, и остальная панель отвечала
бы 429. Опрос — один запрос по
индексу; long polling не используется, потому что держит воркеры PHP.
