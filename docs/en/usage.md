---
title: Usage
locale: en
status: stable
---

# Usage

## A board of a record

Add `HasBoard` to a model, and its screens can show the record's board:

```php
use Dskripchenko\LaravelAdminBoard\Concerns\HasBoard;

class Project extends Model
{
    use HasBoard;

    // Optional: the title a new board gets.
    public function boardTitle(): string
    {
        return 'Project board: '.$this->name;
    }
}

// a screen's layout()
public function layout(): array
{
    return [$this->project->boardLayout()];
}
```

The board is created on first access. A record has one board (`board()` is a
`morphOne`; a unique index keeps two first visits from making two).

## A board anywhere

`BoardLayout::for()` builds the layout for any board:

```php
use Dskripchenko\LaravelAdminBoard\Support\BoardLayout;

return [BoardLayout::for($board, title: 'Retro', height: 480)];
```

It uses the core's `Layout::board()` when the core has it, and otherwise a
`board` layout with the same props — the core draws either. `BoardLayout::props()`
returns just the props:

| Prop | What |
|---|---|
| `endpoint` | the board's API path relative to the panel's API client, `/boards/42` |
| `readonly` | true without the right to edit (the server checks it anyway) |
| `user` | `{ id, name, color }` — the colour is derived from the id, stable per user |
| `realtime` | `{ ticketUrl, channel }`, or null without realtime |
| `pollInterval` | ms between polls without realtime |
| `title`, `height` | as in other layouts |

## On a dashboard

Set the board and the widget appears among the dashboard's widgets:

```php
// config/admin-board.php
'widget' => ['board' => 42, 'title' => 'Team board'],
```

To show another board — per team, say — extend `BoardWidget`, override
`board()`, and register your class with `$admin->widgets([...])`.

## Access

The permission comes first: `admin.boards.view` to see, plus
`admin.boards.edit` to draw. A host narrows it per board, for example in a
service provider:

```php
use Dskripchenko\LaravelAdminBoard\Access\BoardAccess;
use Dskripchenko\LaravelAdminBoard\Models\Board;
use Illuminate\Support\Facades\Gate;

BoardAccess::authorizeUsing(function (object $user, Board $board, string $ability): ?bool {
    $owner = $board->owner;              // the record of a HasBoard board

    return $owner === null
        ? null                           // a board of its own: the permission decides
        : Gate::forUser($user)->allows($ability === 'edit' ? 'update' : 'view', $owner);
});

// The board list: only the boards of one's own records, say.
BoardAccess::scopeUsing(fn ($boards, object $user) => $boards->whereNull('owner_type'));
```

The callback returns false to refuse; true or null keep the permission's
answer. It also guards the realtime channel, which runs outside any request,
so it must use the `$user` it is given rather than `auth()`.

## The API

The core's board layout talks to these endpoints through the panel's API
client — POST JSON, the panel's envelope `{ success, payload }`:

| Request | Body | Payload |
|---|---|---|
| `{endpoint}/snapshot` | — | `{ board, seq }` |
| `{endpoint}/batches` | `{ client, cseq, ops }` | `{ seq }`; a resend gets the same `seq`; 403 without the right to edit; 422 over the limits or malformed |
| `{endpoint}/poll` | `{ after }` | `{ batches }` with `seq > after`; 410 when `after` is older than the log (the client reloads the snapshot) |
| `{endpoint}/presence` | `{ client, cursor?, selection? }` | `{ presence }` — everyone seen within 30 s |

With the default prefix the URLs are `/api/admin/boards/{id}/{action}`; for
another panel, `/api/{panel}/boards/{id}/{action}`. They are routes of their
own next to laravel-api's (its `{controller}/{action}` pattern has no room for
an id), and they run the same stack as the panel's own API: laravel-api's
group with the panel middleware (session, CSRF, locale, authentication on the
panel's guard), then the global middleware of the panel's API class
(throttle, demo guard, the panel's additions such as a tenant layer).

Who a presence or a batch comes from is taken from the session; a `user` in
the body is ignored.

### Semantics

As the reference `BoardServer` of `@dskripchenko/board`: the server sets the
order; `set` is the last write in that order; `delete` wins; a locked board
(`meta.locked`) keeps only `meta` operations (so it can be unlocked), dropping
the rest for every client alike; at most 500 operations per batch and
10 000 objects per board. Each batch is received in a transaction that holds
the board's row lock, so the numbers are consecutive whatever the concurrency.

The one difference from the reference: a client's last batch number (for
spotting resends) is remembered as long as the log holds one of that client's
batches — 10 000 batches by default.

## Realtime

With `dskripchenko/laravel-realtime` installed, after a new batch the server
publishes the event `batch` (the batch with its `seq`) and after a presence
report the event `presence` (the whole list) on the private channel
`admin-board.{id}`. The channel is authorized by the same view check as the
API. The layout gets `realtime: { ticketUrl, channel }`, and the browser
subscribes with `RealtimeClient` of `@dskripchenko/realtime`.

The ticket is issued for the user of `realtime.ticket.guard`. Panel users
sign in on the panel's guard (`admin` by default), so point the ticket there:

```dotenv
REALTIME_GUARD=admin
```

and give the ticket route the panel's session (`realtime.routes.middleware`,
e.g. `['web', 'auth:admin']`). `admin-board.realtime.ticket_url` overrides the
URL; `admin-board.realtime.enabled = false` turns realtime off. A broker
outage does not fail a change: it is stored, and clients catch up by polling.

## Limits and load

Without realtime each open board polls every `poll_interval` ms and reports
presence. The board routes have a request limit of their own,
`admin-board.throttle` (1800 a minute per user by default), counted apart
from the panel API's `admin.api.throttle` — cursor reports, up to ten a
second, would spend the panel's limit and leave the rest of the panel
answering 429. The poll is a single indexed
query; long polling is not used, since it would hold PHP workers.
