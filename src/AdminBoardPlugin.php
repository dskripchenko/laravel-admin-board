<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard;

use Composer\InstalledVersions;
use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Permission\ItemPermission;
use Dskripchenko\LaravelAdmin\Plugin\AdminPlugin;
use Dskripchenko\LaravelAdminBoard\Http\BoardRoutes;
use Dskripchenko\LaravelAdminBoard\Realtime\BoardRealtime;
use Dskripchenko\LaravelAdminBoard\Resources\BoardResource;
use Dskripchenko\LaravelAdminBoard\Screens\BoardScreen;
use Dskripchenko\LaravelAdminBoard\Widgets\BoardWidget;

final class AdminBoardPlugin implements AdminPlugin
{
    public function name(): string
    {
        return 'board';
    }

    public function version(): string
    {
        return InstalledVersions::getPrettyVersion('dskripchenko/laravel-admin-board') ?? 'dev';
    }

    public function register(): void {}

    public function boot(Admin $admin): void
    {
        $admin->resources([BoardResource::class]);
        $admin->screen(BoardScreen::class);
        if (config('admin-board.widget.board') !== null) {
            $admin->widgets([BoardWidget::class]);
        }

        // Source strings: core translates them per request.
        $admin->permissions(ItemPermission::group('Доски')
            ->addPermission(Permissions::VIEW, 'Доски: просмотр')
            ->addPermission(Permissions::EDIT, 'Доски: рисование на доске')
            ->addPermission(Permissions::CREATE, 'Доски: создание')
            ->addPermission(Permissions::UPDATE, 'Доски: переименование')
            ->addPermission(Permissions::DELETE, 'Доски: удаление'));

        // The board API of the panel this plugin is registered in.
        BoardRoutes::register($admin->registrationPanel());

        app(BoardRealtime::class)->registerChannel();
    }
}
