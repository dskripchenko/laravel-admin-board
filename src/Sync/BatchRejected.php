<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Sync;

use RuntimeException;

/**
 * A batch the server refuses: malformed, or over the limits (HTTP 422).
 */
final class BatchRejected extends RuntimeException {}
