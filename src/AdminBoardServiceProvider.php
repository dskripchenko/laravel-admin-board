<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard;

use Dskripchenko\LaravelAdmin\Plugin\Concerns\RegistersAdminPlugin;
use Dskripchenko\LaravelAdminBoard\Realtime\BoardPublisher;
use Dskripchenko\LaravelAdminBoard\Realtime\BoardRealtime;
use Dskripchenko\LaravelAdminBoard\Realtime\RealtimePublisher;
use Illuminate\Support\ServiceProvider;

/**
 * Merges the config, loads the migrations and the JSON translations, binds
 * the realtime publisher when dskripchenko/laravel-realtime is installed and
 * registers the plugin in config('admin.plugins').
 */
final class AdminBoardServiceProvider extends ServiceProvider
{
    use RegistersAdminPlugin;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/admin-board.php', 'admin-board');

        // Registered here rather than in boot(): the translator caches a
        // locale's JSON lines on first use.
        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang');

        $this->app->singleton(BoardRealtime::class);
        if (RealtimePublisher::available() && ! $this->app->bound(BoardPublisher::class)) {
            $this->app->singleton(BoardPublisher::class, RealtimePublisher::class);
        }

        $this->registerAdminPlugin(AdminBoardPlugin::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/admin-board.php' => config_path('admin-board.php'),
        ], 'admin-board-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'admin-board-migrations');
    }
}
