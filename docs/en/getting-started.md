---
title: Getting started
locale: en
status: stable
---

# Getting started

## 1. Install

```bash
composer require dskripchenko/laravel-admin-board
php artisan migrate
```

The service provider is discovered automatically and registers the plugin in
`config('admin.plugins')`. The migrations create `admin_boards` and
`admin_board_batches`; to copy them into the application instead:

```bash
php artisan vendor:publish --tag=admin-board-migrations
```

## 2. Grant the permissions

The keys appear in the panel's role editor under **Boards**:

| Key | For |
|---|---|
| `admin.boards.view` | everyone who looks at boards |
| `admin.boards.edit` | everyone who draws on them |
| `admin.boards.create`, `admin.boards.update`, `admin.boards.delete` | whoever manages the list |

A super admin (`*`) has them all.

## 3. Open the panel

**Boards** lists the boards. **Create** asks for a title; a click on the
title opens the board. Several people can draw at once; each sees the
others' cursors.

## 4. Optional: the config

```bash
php artisan vendor:publish --tag=admin-board-config
```

| Key | Default | What |
|---|---|---|
| `limits.max_ops` | 500 | operations per batch; more is refused with 422 |
| `limits.max_objects` | 10 000 | objects per board |
| `keep` | 10 000 | batches kept per board for clients that come back |
| `poll_limit` | 1 000 | batches in one poll answer |
| `throttle` | 1800,1 | the board routes' own request limit (requests,minutes), apart from `admin.api.throttle`; null turns it off |
| `presence.ttl` | 30 | seconds a client stays in the presence list |
| `presence.store` | null | the cache store for presence (null — the default) |
| `poll_interval` | 1500 | ms between polls without realtime |
| `height` | 640 | the board's height on a page, px |
| `realtime.enabled` | null | null — on when laravel-realtime is installed; false — off |
| `realtime.ticket_url` | null | where the browser gets a realtime ticket; null — the `realtime.ticket` route |
| `widget.board` | null | the board the dashboard widget shows; null — no widget |
| `widget.title` | Доска | the widget's title |

## 5. Optional: realtime

Install and run [`dskripchenko/laravel-realtime`](https://github.com/dskripchenko/laravel-realtime),
and the boards use it with no further setup: changes and presence arrive over
the WebSocket, and the board polls only to catch up after a reconnect. See
[Usage → Realtime](usage.md#realtime) for the guard the ticket needs.
