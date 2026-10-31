<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Http;

use Closure;
use Dskripchenko\LaravelAdmin\Panel\Panels;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Router;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs the global middleware of the panel's API class — the throttle, the
 * demo guard, a panel's own additions such as activating its tenant layer —
 * on the board routes.
 *
 * The panel stack (session, CSRF, locale, AdminAuth) comes from laravel-api's
 * group the routes share with the core's endpoints; the core's
 * RunActionMiddleware adds the API class's global middleware only for URLs
 * of the `{controller}/{action}` shape, which `/boards/{id}/{action}` is not.
 *
 * The panel's request limit is the exception: it is shared by everything a
 * user does in the panel, and a board open in a tab reports the cursor up to
 * ten times a second and polls — it would spend the panel's limit in a minute
 * and leave the rest of the panel answering 429. The board routes take a
 * limit of their own instead, `admin-board.throttle`, counted apart.
 */
final class RunPanelApiMiddleware
{
    public function __construct(private readonly Container $container, private readonly Router $router) {}

    public function handle(Request $request, Closure $next): Response
    {
        $apiClass = Panels::current()->apiClass;
        $methods = method_exists($apiClass, 'getPreparedMethods') ? (array) $apiClass::getPreparedMethods() : [];
        /** @var array<int, string> $stack */
        $stack = array_values(array_filter((array) ($methods['middleware'] ?? []), 'is_string'));
        $stack = array_values(array_filter($stack, static fn (string $m): bool => ! self::isThrottle($m)));
        $limit = config('admin-board.throttle', '1800,1');
        if (is_string($limit) && $limit !== '') {
            $stack[] = ThrottleRequests::class.':'.$limit.',admin-board-'.Panels::current()->id;
        }
        $stack = $this->router->resolveMiddleware($stack);

        if ($stack === []) {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        /** @var Response $response */
        $response = (new Pipeline($this->container))
            ->send($request)
            ->through($stack)
            ->then(static fn (Request $req): Response => $next($req));

        return $response;
    }

    /** A request limit: `throttle:…` or ThrottleRequests (or a subclass) with its parameters. */
    private static function isThrottle(string $middleware): bool
    {
        $name = explode(':', $middleware, 2)[0];

        return $name === 'throttle' || is_a($name, ThrottleRequests::class, true);
    }
}
