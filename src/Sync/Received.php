<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Sync;

use stdClass;

/**
 * What receiving a batch gave: the batch in the server's order (`{ client,
 * cseq, ops, user?, seq }`) and whether it is new — false for a resend of a
 * batch already accepted, which is not applied again.
 */
final class Received
{
    public function __construct(
        public readonly int $seq,
        public readonly bool $fresh,
        public readonly ?stdClass $batch = null,
    ) {}
}
