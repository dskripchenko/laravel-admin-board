<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    | A batch with more operations, or one that would take a board past
    | `max_objects`, is refused with 422 — the limits of the reference server
    | of @dskripchenko/board.
    */
    'limits' => [
        'max_ops' => 500,
        'max_objects' => 10_000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Batch log
    |--------------------------------------------------------------------------
    | `keep` — batches kept per board for clients that resume; older ones live
    | only in the snapshot, and a client further behind reloads it (410).
    | `poll_limit` — the most batches one poll answer carries; the client asks
    | again from the last one.
    */
    'keep' => 10_000,
    'poll_limit' => 1_000,

    /*
    |--------------------------------------------------------------------------
    | Presence
    |--------------------------------------------------------------------------
    | Seconds a client stays in the presence list after its last report. Kept
    | in the application cache (`store` — null for the default store).
    */
    'presence' => [
        'ttl' => 30,
        'store' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Layout defaults
    |--------------------------------------------------------------------------
    | `poll_interval` — ms between polls when realtime is off; `height` — the
    | board's height on a screen, px.
    */
    'poll_interval' => 1500,
    'height' => 640,

    /*
    |--------------------------------------------------------------------------
    | Realtime (dskripchenko/laravel-realtime, optional)
    |--------------------------------------------------------------------------
    | `enabled` — null detects the package; false turns it off; true requires
    | it. `ticket_url` — where the browser gets a ticket; null takes the
    | package's own `realtime.ticket` route.
    */
    'realtime' => [
        'enabled' => null,
        'ticket_url' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard widget
    |--------------------------------------------------------------------------
    | The id of the board the dashboard widget shows; null leaves the widget
    | out of the dashboard.
    */
    'widget' => [
        'board' => null,
        'title' => 'Доска',
    ],
];
