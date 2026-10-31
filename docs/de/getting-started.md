---
title: Erste Schritte
locale: de
status: stable
---

# Erste Schritte

## 1. Installation

```bash
composer require dskripchenko/laravel-admin-board
php artisan migrate
```

Der Service-Provider wird automatisch erkannt und registriert das Plugin in
`config('admin.plugins')`. Die Migrationen legen `admin_boards` und
`admin_board_batches` an; um sie stattdessen in die Anwendung zu kopieren:

```bash
php artisan vendor:publish --tag=admin-board-migrations
```

## 2. Berechtigungen vergeben

Die Schlüssel erscheinen im Rolleneditor des Panels unter **Boards**:

| Schlüssel | Für |
|---|---|
| `admin.boards.view` | alle, die Boards ansehen |
| `admin.boards.edit` | alle, die darauf zeichnen |
| `admin.boards.create`, `admin.boards.update`, `admin.boards.delete` | wer die Liste pflegt |

Ein Super-Admin (`*`) hat alle.

## 3. Das Panel öffnen

**Boards** listet die Boards auf. **Anlegen** fragt nach einem Titel; ein Klick
auf den Titel öffnet das Board. Mehrere Personen können gleichzeitig zeichnen;
jede sieht die Cursor der anderen.

## 4. Optional: die Konfiguration

```bash
php artisan vendor:publish --tag=admin-board-config
```

| Schlüssel | Standard | Was |
|---|---|---|
| `limits.max_ops` | 500 | Operationen pro Batch; mehr wird mit 422 abgelehnt |
| `limits.max_objects` | 10 000 | Objekte pro Board |
| `keep` | 10 000 | pro Board aufbewahrte Batches für zurückkehrende Clients |
| `poll_limit` | 1 000 | Batches in einer Poll-Antwort |
| `throttle` | 1800,1 | eigenes Anfragelimit der Board-Routen (Anfragen,Minuten), getrennt von `admin.api.throttle`; null schaltet es ab |
| `presence.ttl` | 30 | Sekunden, die ein Client in der Anwesenheitsliste bleibt |
| `presence.store` | null | der Cache-Store für die Anwesenheit (null — der Standard) |
| `poll_interval` | 1500 | ms zwischen Abfragen ohne Realtime |
| `height` | 640 | Höhe des Boards auf einer Seite, px |
| `realtime.enabled` | null | null — an, wenn laravel-realtime installiert ist; false — aus |
| `realtime.ticket_url` | null | wo der Browser ein Realtime-Ticket holt; null — die Route `realtime.ticket` |
| `widget.board` | null | das Board des Dashboard-Widgets; null — kein Widget |
| `widget.title` | Доска | der Titel des Widgets |

## 5. Optional: Realtime

Installieren und starten Sie [`dskripchenko/laravel-realtime`](https://github.com/dskripchenko/laravel-realtime),
und die Boards nutzen es ohne weitere Einrichtung: Änderungen und Anwesenheit
kommen über den WebSocket, und das Board fragt nur noch ab, um nach einem
Reconnect aufzuholen. Welchen Guard das Ticket braucht, steht in
[Verwendung → Realtime](usage.md#realtime).
