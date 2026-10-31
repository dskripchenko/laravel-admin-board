---
title: 使用
locale: zh
status: stable
---

# 使用

## 记录的白板

给模型加上 `HasBoard`，它的页面就可以显示该记录的白板：

```php
use Dskripchenko\LaravelAdminBoard\Concerns\HasBoard;

class Project extends Model
{
    use HasBoard;

    // 可选：新白板的标题。
    public function boardTitle(): string
    {
        return '项目白板：'.$this->name;
    }
}

// 页面的 layout()
public function layout(): array
{
    return [$this->project->boardLayout()];
}
```

白板在首次访问时创建。每条记录只有一块白板（`board()` 是 `morphOne`；唯一索引防止两次首次访问创建两块）。

## 在任意位置使用白板

`BoardLayout::for()` 为任意白板构建布局：

```php
use Dskripchenko\LaravelAdminBoard\Support\BoardLayout;

return [BoardLayout::for($board, title: '回顾', height: 480)];
```

如果核心提供了 `Layout::board()` 就使用它，否则使用属性相同的 `board` 类型布局——核心都能绘制。
`BoardLayout::props()` 只返回属性：

| 属性 | 说明 |
|---|---|
| `endpoint` | 相对于面板 API 客户端的白板 API 路径，如 `/boards/42` |
| `readonly` | 没有编辑权限时为 true（服务器仍会校验） |
| `user` | `{ id, name, color }`——颜色由 id 推导，对同一用户保持不变 |
| `realtime` | `{ ticketUrl, channel }`，无实时连接时为 null |
| `pollInterval` | 无实时连接时的轮询间隔（毫秒） |
| `title`、`height` | 与其他布局相同 |

## 在仪表盘上

设置白板后，该小部件会出现在仪表盘的小部件中：

```php
// config/admin-board.php
'widget' => ['board' => 42, 'title' => '团队白板'],
```

如需显示其他白板（例如每个团队一块），继承 `BoardWidget`，重写 `board()`，并通过
`$admin->widgets([...])` 注册你的类。

## 访问控制

首先看权限：查看需要 `admin.boards.view`，绘制还需要 `admin.boards.edit`。宿主应用可以按白板进一步收窄，例如在服务提供者中：

```php
use Dskripchenko\LaravelAdminBoard\Access\BoardAccess;
use Dskripchenko\LaravelAdminBoard\Models\Board;
use Illuminate\Support\Facades\Gate;

BoardAccess::authorizeUsing(function (object $user, Board $board, string $ability): ?bool {
    $owner = $board->owner;              // HasBoard 白板所属的记录

    return $owner === null
        ? null                           // 独立白板：由权限决定
        : Gate::forUser($user)->allows($ability === 'edit' ? 'update' : 'view', $owner);
});

// 白板列表：例如只显示独立白板。
BoardAccess::scopeUsing(fn ($boards, object $user) => $boards->whereNull('owner_type'));
```

回调返回 false 表示拒绝；返回 true 或 null 则沿用权限的结果。它同样保护实时频道，而频道授权在请求之外运行，因此必须使用传入的 `$user`，而不是 `auth()`。

## API

核心的白板布局通过面板的 API 客户端调用这些端点——POST JSON，使用面板的响应信封 `{ success, payload }`：

| 请求 | 请求体 | 响应 |
|---|---|---|
| `{endpoint}/snapshot` | — | `{ board, seq }` |
| `{endpoint}/batches` | `{ client, cseq, ops }` | `{ seq }`；重发得到相同的 `seq`；无编辑权限返回 403；超出限制或格式错误返回 422 |
| `{endpoint}/poll` | `{ after }` | `{ batches }`，其中 `seq > after`；当 `after` 早于日志时返回 410（客户端重新加载快照） |
| `{endpoint}/presence` | `{ client, cursor?, selection? }` | `{ presence }`——30 秒内出现过的所有人 |

使用默认前缀时，URL 为 `/api/admin/boards/{id}/{action}`；其他面板为
`/api/{panel}/boards/{id}/{action}`。它们是与 laravel-api 路由并列的独立路由（laravel-api 的
`{controller}/{action}` 模式无法容纳 id），并经过与面板自身 API 相同的中间件栈：laravel-api 的中间件组及面板中间件（会话、CSRF、语言、面板 guard 上的认证），然后是面板 API 类的全局中间件（限流、演示模式保护、面板的附加中间件，例如租户层）。

在线状态或批次来自谁，取自会话；请求体中的 `user` 会被忽略。

### 语义

与 `@dskripchenko/board` 的参考实现 `BoardServer` 相同：顺序由服务器决定；`set` 以该顺序中的最后一次写入为准；`delete` 优先；锁定的白板（`meta.locked`）只保留 `meta` 操作（以便解锁），其余操作对所有客户端一律丢弃；每个批次最多 500 个操作，每块白板最多 10 000 个对象。每个批次都在持有白板行锁的事务中接收，因此无论并发如何，编号都是连续的。

与参考实现唯一的不同：客户端最后的批次编号（用于识别重发）只在日志中仍保留该客户端的批次时被记住——默认是 10 000 个批次。

## 实时

安装了 `dskripchenko/laravel-realtime` 时，服务器在接收新批次后向私有频道 `admin-board.{id}` 发布 `batch` 事件（带 `seq` 的批次），在收到在线状态后发布 `presence` 事件（完整列表）。该频道使用与 API 相同的查看权限检查进行授权。布局得到 `realtime: { ticketUrl, channel }`，浏览器通过 `@dskripchenko/realtime` 的 `RealtimeClient` 订阅。

票据签发给 `realtime.ticket.guard` 的用户。面板用户登录在面板的 guard 上（默认 `admin`），因此请把票据指向该 guard：

```dotenv
REALTIME_GUARD=admin
```

并让票据路由使用面板的会话（`realtime.routes.middleware`，例如 `['web', 'auth:admin']`）。
`admin-board.realtime.ticket_url` 可覆盖地址；`admin-board.realtime.enabled = false` 关闭实时。消息代理故障不会导致更改失败：更改已保存，客户端会通过轮询补齐。

## 限制与负载

没有实时连接时，每块打开的白板每隔 `poll_interval` 毫秒轮询一次并上报在线状态；两者都计入面板 API 的限流（`admin.api.throttle`，默认每分钟 240 次）。一次轮询只是一条走索引的查询；不使用长轮询，因为它会占用 PHP 工作进程。
