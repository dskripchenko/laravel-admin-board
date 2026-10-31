---
title: Verwendung
locale: de
status: stable
---

# Verwendung

## Das Board eines Datensatzes

Fügen Sie einem Model `HasBoard` hinzu, und seine Screens können das Board des
Datensatzes zeigen:

```php
use Dskripchenko\LaravelAdminBoard\Concerns\HasBoard;

class Project extends Model
{
    use HasBoard;

    // Optional: der Titel eines neuen Boards.
    public function boardTitle(): string
    {
        return 'Projekt-Board: '.$this->name;
    }
}

// layout() eines Screens
public function layout(): array
{
    return [$this->project->boardLayout()];
}
```

Das Board wird beim ersten Zugriff angelegt. Ein Datensatz hat ein Board
(`board()` ist ein `morphOne`; ein Unique-Index verhindert, dass zwei erste
Aufrufe zwei anlegen).

## Ein Board an beliebiger Stelle

`BoardLayout::for()` baut das Layout für ein beliebiges Board:

```php
use Dskripchenko\LaravelAdminBoard\Support\BoardLayout;

return [BoardLayout::for($board, title: 'Retro', height: 480)];
```

Es verwendet `Layout::board()` des Kerns, wenn der Kern es hat, und sonst ein
`board`-Layout mit denselben Props — der Kern zeichnet beide.
`BoardLayout::props()` liefert nur die Props:

| Prop | Was |
|---|---|
| `endpoint` | der API-Pfad des Boards relativ zum API-Client des Panels, `/boards/42` |
| `readonly` | true ohne Bearbeitungsrecht (der Server prüft es trotzdem) |
| `user` | `{ id, name, color }` — die Farbe wird aus der id abgeleitet und ist pro Benutzer stabil |
| `realtime` | `{ ticketUrl, channel }` oder null ohne Realtime |
| `pollInterval` | ms zwischen Abfragen ohne Realtime |
| `title`, `height` | wie bei anderen Layouts |

## Auf einem Dashboard

Setzen Sie das Board, und das Widget erscheint unter den Widgets des Dashboards:

```php
// config/admin-board.php
'widget' => ['board' => 42, 'title' => 'Team-Board'],
```

Um ein anderes Board zu zeigen — etwa eines pro Team —, erweitern Sie
`BoardWidget`, überschreiben `board()` und registrieren Ihre Klasse mit
`$admin->widgets([...])`.

## Zugriff

Zuerst zählt die Berechtigung: `admin.boards.view` zum Ansehen, dazu
`admin.boards.edit` zum Zeichnen. Ein Host schränkt sie pro Board ein, etwa in
einem Service-Provider:

```php
use Dskripchenko\LaravelAdminBoard\Access\BoardAccess;
use Dskripchenko\LaravelAdminBoard\Models\Board;
use Illuminate\Support\Facades\Gate;

BoardAccess::authorizeUsing(function (object $user, Board $board, string $ability): ?bool {
    $owner = $board->owner;              // der Datensatz eines HasBoard-Boards

    return $owner === null
        ? null                           // ein eigenständiges Board: die Berechtigung entscheidet
        : Gate::forUser($user)->allows($ability === 'edit' ? 'update' : 'view', $owner);
});

// Die Board-Liste: etwa nur die eigenständigen Boards.
BoardAccess::scopeUsing(fn ($boards, object $user) => $boards->whereNull('owner_type'));
```

Der Callback gibt false zurück, um abzulehnen; true oder null lassen die
Antwort der Berechtigung stehen. Er schützt auch den Realtime-Kanal, der
außerhalb jeder Anfrage läuft; er muss daher den übergebenen `$user`
verwenden, nicht `auth()`.

## Die API

Das Board-Layout des Kerns spricht diese Endpunkte über den API-Client des
Panels an — POST JSON, der Umschlag des Panels `{ success, payload }`:

| Anfrage | Body | Payload |
|---|---|---|
| `{endpoint}/snapshot` | — | `{ board, seq }` |
| `{endpoint}/batches` | `{ client, cseq, ops }` | `{ seq }`; eine Wiederholung bekommt dieselbe `seq`; 403 ohne Bearbeitungsrecht; 422 über den Limits oder fehlerhaft |
| `{endpoint}/poll` | `{ after }` | `{ batches }` mit `seq > after`; 410, wenn `after` älter als das Log ist (der Client lädt den Snapshot neu) |
| `{endpoint}/presence` | `{ client, cursor?, selection? }` | `{ presence }` — alle, die in den letzten 30 s gesehen wurden |

Mit dem Standardpräfix lauten die URLs `/api/admin/boards/{id}/{action}`; für
ein anderes Panel `/api/{panel}/boards/{id}/{action}`. Es sind eigene Routen
neben denen von laravel-api (dessen Muster `{controller}/{action}` hat keinen
Platz für eine id), und sie durchlaufen denselben Stack wie die eigene API des
Panels: die Gruppe von laravel-api mit der Panel-Middleware (Session, CSRF,
Locale, Authentifizierung am Guard des Panels), dann die globale Middleware der
API-Klasse des Panels (Throttle, Demo-Schutz, Ergänzungen des Panels wie eine
Mandantenschicht).

Von wem eine Anwesenheit oder ein Batch stammt, wird der Session entnommen;
ein `user` im Body wird ignoriert.

### Semantik

Wie beim Referenz-`BoardServer` aus `@dskripchenko/board`: der Server legt die
Reihenfolge fest; `set` ist der letzte Schreibvorgang in dieser Reihenfolge;
`delete` gewinnt; ein gesperrtes Board (`meta.locked`) behält nur
`meta`-Operationen (damit es entsperrt werden kann) und verwirft die übrigen
für alle Clients gleich; höchstens 500 Operationen pro Batch und 10 000 Objekte
pro Board. Jeder Batch wird in einer Transaktion angenommen, die die
Zeilensperre des Boards hält, sodass die Nummern bei jeder Nebenläufigkeit
lückenlos aufeinander folgen.

Der einzige Unterschied zur Referenz: die letzte Batch-Nummer eines Clients
(zum Erkennen von Wiederholungen) bleibt bekannt, solange das Log einen Batch
dieses Clients enthält — standardmäßig 10 000 Batches.

## Realtime

Ist `dskripchenko/laravel-realtime` installiert, veröffentlicht der Server nach
einem neuen Batch das Ereignis `batch` (der Batch mit seiner `seq`) und nach
einer Anwesenheitsmeldung das Ereignis `presence` (die ganze Liste) im privaten
Kanal `admin-board.{id}`. Der Kanal wird mit derselben Ansichtsprüfung
autorisiert wie die API. Das Layout bekommt `realtime: { ticketUrl, channel }`,
und der Browser abonniert mit `RealtimeClient` aus `@dskripchenko/realtime`.

Das Ticket wird für den Benutzer von `realtime.ticket.guard` ausgestellt.
Panel-Benutzer melden sich am Guard des Panels an (standardmäßig `admin`),
also richten Sie das Ticket dorthin aus:

```dotenv
REALTIME_GUARD=admin
```

und geben Sie der Ticket-Route die Session des Panels
(`realtime.routes.middleware`, z. B. `['web', 'auth:admin']`).
`admin-board.realtime.ticket_url` überschreibt die URL;
`admin-board.realtime.enabled = false` schaltet Realtime ab. Ein Ausfall des
Brokers lässt keine Änderung scheitern: sie ist gespeichert, und die Clients
holen per Abfrage auf.

## Limits und Last

Ohne Realtime fragt jedes offene Board alle `poll_interval` ms ab und meldet
seine Anwesenheit; beides zählt gegen das Throttle der Panel-API
(`admin.api.throttle`, standardmäßig 240 pro Minute). Eine Abfrage ist eine
einzige indizierte Query; Long Polling wird nicht verwendet, weil es
PHP-Worker blockieren würde.
