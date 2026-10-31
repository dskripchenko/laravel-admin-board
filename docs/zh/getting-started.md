---
title: 快速开始
locale: zh
status: stable
---

# 快速开始

## 1. 安装

```bash
composer require dskripchenko/laravel-admin-board
php artisan migrate
```

服务提供者会被自动发现，并把插件注册到 `config('admin.plugins')`。迁移会创建
`admin_boards` 和 `admin_board_batches`；如需把迁移复制到应用中：

```bash
php artisan vendor:publish --tag=admin-board-migrations
```

## 2. 授予权限

这些键会出现在面板角色编辑器的 **白板** 分组下：

| 键 | 适用于 |
|---|---|
| `admin.boards.view` | 所有查看白板的人 |
| `admin.boards.edit` | 所有在白板上绘制的人 |
| `admin.boards.create`、`admin.boards.update`、`admin.boards.delete` | 管理列表的人 |

超级管理员（`*`）拥有全部权限。

## 3. 打开面板

**白板** 列出所有白板。**创建** 只需填写标题；点击标题即可打开白板。多人可同时绘制，每个人都能看到其他人的光标。

## 4. 可选：配置

```bash
php artisan vendor:publish --tag=admin-board-config
```

| 键 | 默认值 | 说明 |
|---|---|---|
| `limits.max_ops` | 500 | 每个批次的操作数；超过时以 422 拒绝 |
| `limits.max_objects` | 10 000 | 每块白板的对象数 |
| `keep` | 10 000 | 每块白板为重新连接的客户端保留的批次数 |
| `poll_limit` | 1 000 | 一次轮询响应中的批次数 |
| `presence.ttl` | 30 | 客户端在在线列表中保留的秒数 |
| `presence.store` | null | 在线状态使用的缓存存储（null 为默认存储） |
| `poll_interval` | 1500 | 无实时连接时的轮询间隔（毫秒） |
| `height` | 640 | 白板在页面上的高度（像素） |
| `realtime.enabled` | null | null：安装了 laravel-realtime 时启用；false：关闭 |
| `realtime.ticket_url` | null | 浏览器获取实时票据的地址；null 时使用 `realtime.ticket` 路由 |
| `widget.board` | null | 仪表盘小部件显示的白板；null 表示不显示小部件 |
| `widget.title` | Доска | 小部件标题 |

## 5. 可选：实时

安装并运行 [`dskripchenko/laravel-realtime`](https://github.com/dskripchenko/laravel-realtime)
后，白板无需进一步配置即可使用它：更改和在线状态通过 WebSocket 到达，只有在重新连接后才通过轮询补齐遗漏。
票据需要的 guard 见 [使用 → 实时](usage.md#实时)。
