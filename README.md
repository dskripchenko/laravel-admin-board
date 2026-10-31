# dskripchenko/laravel-admin-board

> 🌐 **English** · [Русский](docs/ru/README.md) · [Deutsch](docs/de/README.md) · [中文](docs/zh/README.md)

Collaborative boards for the admin panel: a whiteboard of its own or one per
record, edited by several people at once. The server orders every change, so
all of them end up with the same board; presence shows who is there and
where their cursor is. With
[`dskripchenko/laravel-realtime`](https://github.com/dskripchenko/laravel-realtime)
installed, changes arrive over WebSockets; without it, the board polls.

A sister-pack for [`dskripchenko/laravel-admin`](https://github.com/dskripchenko/laravel-admin).
The editor itself is `BoardEditor` of
[`@dskripchenko/board`](https://www.npmjs.com/package/@dskripchenko/board), drawn by the
core's `board` layout; this pack stores the boards and serves its API.

[![Packagist](https://img.shields.io/packagist/v/dskripchenko/laravel-admin-board)](https://packagist.org/packages/dskripchenko/laravel-admin-board)
[![License](https://img.shields.io/packagist/l/dskripchenko/laravel-admin-board)](LICENSE)

## Install

```bash
composer require dskripchenko/laravel-admin-board
php artisan migrate
```

The provider registers itself. Grant the permissions to the roles that need
them.

## What you get

| Where | What |
|---|---|
| **Boards** | the list of boards: title, number of changes, locked or not; create with a title, rename, delete. A title opens the board. |
| **Board page** (`/screens/board?id=…`) | the board, read-only without `admin.boards.edit`. |
| **A record's board** | `HasBoard` on any model: `$project->boardLayout()` on its screens, the board created on first access. |
| **Dashboard** | `BoardWidget` shows the board set in `admin-board.widget.board`. |

## Permissions

| Key | Allows |
|---|---|
| `admin.boards.view` | open boards and the list, see who is there |
| `admin.boards.edit` | draw on boards (the server refuses changes without it) |
| `admin.boards.create` / `.update` / `.delete` | manage the list: new boards, renaming, removing |

A host narrows access per board with `BoardAccess::authorizeUsing()` — for
example, a record's board only for those who may view the record.

## Documentation

- [Getting started](docs/en/getting-started.md)
- [Usage](docs/en/usage.md): records, access, the API, realtime, limits

## Requirements

PHP 8.2–8.5, Laravel 11 / 12 / 13, `dskripchenko/laravel-admin` ^1.68.
Optional: `dskripchenko/laravel-realtime`.

## License

MIT
