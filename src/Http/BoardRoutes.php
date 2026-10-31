<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Http;

use Dskripchenko\LaravelAdmin\Panel\PanelRegistry;
use Illuminate\Support\Facades\Route;

/**
 * The board API of a panel, beside the panel's own API:
 *
 *     POST /{panel api path}/boards/{board}/snapshot|batches|poll|presence
 *
 * — `/api/admin/boards/42/snapshot` by default, so the layout's `endpoint`
 * is `/boards/42`, relative to the panel's API client.
 *
 * laravel-api serves `{version}/{controller}/{action}` only, so these are
 * routes of their own; they run laravel-api's group (the panel stack:
 * session, CSRF, locale, AdminAuth on the panel's guard) and then the panel
 * API's global middleware ({@see RunPanelApiMiddleware}). The panel is fixed
 * by the route (`adminPanel`), as the core does for its own extra routes.
 */
final class BoardRoutes
{
    public const ACTIONS = ['snapshot', 'batches', 'poll', 'presence'];

    /** The middleware group laravel-api registers for the API routes. */
    public const API_GROUP = 'api-middleware-group';

    public static function register(string $panelId): void
    {
        $app = app();
        // Called once per application, from the plugin's boot().
        if ($app->routesAreCached()) {
            return;
        }
        $panel = $app->make(PanelRegistry::class)->get($panelId);
        if ($panel === null) {
            return;
        }

        $prefix = trim($panel->apiPath, '/');
        foreach (self::ACTIONS as $action) {
            Route::post(($prefix === '' ? '' : $prefix.'/').'boards/{board}/'.$action, [BoardController::class, $action])
                ->whereNumber('board')
                ->middleware([self::API_GROUP, RunPanelApiMiddleware::class])
                ->defaults('adminPanel', $panel->id)
                ->name($panel->id.'.boards.'.$action);
        }
    }
}
