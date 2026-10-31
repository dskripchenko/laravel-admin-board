# dskripchenko/laravel-admin-board

> 🌐 [English](../../README.md) · [Русский](../ru/README.md) · [Deutsch](../de/README.md) · **中文**

管理面板的协作白板：可以是独立的白板，也可以是每条记录各自的白板，多人可同时编辑。
所有更改的顺序由服务器决定，因此每个人最终看到的都是同一块白板；在线状态显示谁在白板上以及其光标位置。
安装了 [`dskripchenko/laravel-realtime`](https://github.com/dskripchenko/laravel-realtime)
时，更改通过 WebSocket 推送；未安装时，白板定时轮询服务器。

[`dskripchenko/laravel-admin`](https://github.com/dskripchenko/laravel-admin) 的姊妹包。
编辑器本身是 [`@dskripchenko/board`](https://www.npmjs.com/package/@dskripchenko/board)
的 `BoardEditor`，由核心的 `board` 布局绘制；本包负责存储白板并提供其 API。

[![Packagist](https://img.shields.io/packagist/v/dskripchenko/laravel-admin-board)](https://packagist.org/packages/dskripchenko/laravel-admin-board)
[![License](https://img.shields.io/packagist/l/dskripchenko/laravel-admin-board)](../../LICENSE)

## 安装

```bash
composer require dskripchenko/laravel-admin-board
php artisan migrate
```

服务提供者会自动注册。把权限授予需要的角色即可。

## 功能

| 位置 | 内容 |
|---|---|
| **白板** | 白板列表：标题、更改次数、是否锁定；按标题创建、重命名、删除。点击标题打开白板。 |
| **白板页面**（`/screens/board?id=…`） | 白板；没有 `admin.boards.edit` 时只读。 |
| **记录的白板** | 任意模型使用 `HasBoard`：在其页面上调用 `$project->boardLayout()`，首次访问时创建白板。 |
| **仪表盘** | `BoardWidget` 显示 `admin-board.widget.board` 中设置的白板。 |

## 权限

| 键 | 允许 |
|---|---|
| `admin.boards.view` | 打开白板和列表，查看谁在线 |
| `admin.boards.edit` | 在白板上绘制（没有该权限时服务器拒绝更改） |
| `admin.boards.create` / `.update` / `.delete` | 管理列表：新建、重命名、删除 |

宿主应用可通过 `BoardAccess::authorizeUsing()` 按白板收窄访问——例如，记录的白板只对能查看该记录的人可见。

## 文档

- [快速开始](getting-started.md)
- [使用](usage.md)：记录、访问控制、API、实时、限制

## 要求

PHP 8.2–8.5，Laravel 11 / 12 / 13，`dskripchenko/laravel-admin` ^1.68。
可选：`dskripchenko/laravel-realtime`。

## 许可证

MIT
