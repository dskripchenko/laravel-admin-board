<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Support;

use Dskripchenko\LaravelAdmin\Layout\Layout;

/**
 * The `board` layout for a core that does not ship `Layout\Board` yet: the
 * same type and props, so the page works once the core draws it. It goes
 * away when the pack requires a core with `Layout::board()`.
 */
final class FallbackBoardLayout extends Layout
{
    /**
     * @param  array<string, mixed>  $props
     */
    public function __construct(array $props)
    {
        $this->props = $props;
    }

    public function type(): string
    {
        return 'board';
    }
}
