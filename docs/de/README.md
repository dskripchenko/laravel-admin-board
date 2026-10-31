# dskripchenko/laravel-admin-board

> 🌐 [English](../../README.md) · [Русский](../ru/README.md) · **Deutsch** · [中文](../zh/README.md)

Gemeinsame Boards für das Admin-Panel: ein eigenständiges Whiteboard oder
eines pro Datensatz, das mehrere Personen gleichzeitig bearbeiten. Der Server
legt die Reihenfolge jeder Änderung fest, sodass alle am Ende dasselbe Board
sehen; die Anwesenheit zeigt, wer gerade da ist und wo sein Cursor steht. Ist
[`dskripchenko/laravel-realtime`](https://github.com/dskripchenko/laravel-realtime)
installiert, kommen Änderungen über WebSockets; ohne das Paket fragt das Board
den Server regelmäßig ab.

Ein Sister-Pack für [`dskripchenko/laravel-admin`](https://github.com/dskripchenko/laravel-admin).
Der Editor selbst ist `BoardEditor` aus
[`@dskripchenko/board`](https://www.npmjs.com/package/@dskripchenko/board), gezeichnet vom
`board`-Layout des Kerns; dieses Paket speichert die Boards und stellt ihre API bereit.

[![Packagist](https://img.shields.io/packagist/v/dskripchenko/laravel-admin-board)](https://packagist.org/packages/dskripchenko/laravel-admin-board)
[![License](https://img.shields.io/packagist/l/dskripchenko/laravel-admin-board)](../../LICENSE)

## Installation

```bash
composer require dskripchenko/laravel-admin-board
php artisan migrate
```

Der Provider registriert sich selbst. Vergeben Sie die Berechtigungen an die
Rollen, die sie brauchen.

## Was Sie bekommen

| Wo | Was |
|---|---|
| **Boards** | die Liste der Boards: Titel, Anzahl der Änderungen, gesperrt oder nicht; Anlegen mit Titel, Umbenennen, Löschen. Ein Klick auf den Titel öffnet das Board. |
| **Board-Seite** (`/screens/board?id=…`) | das Board; ohne `admin.boards.edit` nur lesend. |
| **Board eines Datensatzes** | `HasBoard` an einem beliebigen Model: `$project->boardLayout()` auf seinen Screens, das Board wird beim ersten Zugriff angelegt. |
| **Dashboard** | `BoardWidget` zeigt das in `admin-board.widget.board` gesetzte Board. |

## Berechtigungen

| Schlüssel | Erlaubt |
|---|---|
| `admin.boards.view` | Boards und die Liste öffnen, sehen, wer da ist |
| `admin.boards.edit` | auf Boards zeichnen (ohne ihn lehnt der Server Änderungen ab) |
| `admin.boards.create` / `.update` / `.delete` | die Liste pflegen: neue Boards, Umbenennen, Löschen |

Ein Host schränkt den Zugriff pro Board mit `BoardAccess::authorizeUsing()`
ein — etwa das Board eines Datensatzes nur für jene, die den Datensatz sehen dürfen.

## Dokumentation

- [Erste Schritte](getting-started.md)
- [Verwendung](usage.md): Datensätze, Zugriff, die API, Realtime, Limits

## Voraussetzungen

PHP 8.2–8.5, Laravel 11 / 12 / 13, `dskripchenko/laravel-admin` ^1.69.
Optional: `dskripchenko/laravel-realtime`.

## Lizenz

MIT
