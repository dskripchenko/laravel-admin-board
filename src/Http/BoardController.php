<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Http;

use Dskripchenko\LaravelAdmin\Panel\Panels;
use Dskripchenko\LaravelAdminBoard\Access\BoardAccess;
use Dskripchenko\LaravelAdminBoard\Models\Board;
use Dskripchenko\LaravelAdminBoard\Realtime\BoardRealtime;
use Dskripchenko\LaravelAdminBoard\Support\BoardUser;
use Dskripchenko\LaravelAdminBoard\Sync\BatchRejected;
use Dskripchenko\LaravelAdminBoard\Sync\BoardServer;
use Dskripchenko\LaravelAdminBoard\Sync\PresenceStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use stdClass;

/**
 * The board API of the contract between the core's board layout and this
 * pack — POST JSON through the panel's API client, answers in the panel's
 * envelope `{ success, payload }`:
 *
 * | Action     | Body                                  | Payload                                   |
 * |------------|---------------------------------------|-------------------------------------------|
 * | `snapshot` | —                                     | `{ board, seq }`                          |
 * | `batches`  | `{ client, cseq, ops }`               | `{ seq }`; 403 without edit; 422 limits   |
 * | `poll`     | `{ after }`                           | `{ batches }`; 410 when `after` is too old |
 * | `presence` | `{ client, user, cursor?, selection? }` | `{ presence }`                          |
 */
final class BoardController
{
    public function snapshot(string $board): JsonResponse
    {
        $model = $this->board($board, edit: false);
        if ($model instanceof JsonResponse) {
            return $model;
        }

        return self::ok(BoardServer::fromConfig()->snapshot($model));
    }

    public function batches(Request $request, string $board, BoardRealtime $realtime): JsonResponse
    {
        $model = $this->board($board, edit: true);
        if ($model instanceof JsonResponse) {
            return $model;
        }
        $body = self::body($request);
        $client = $body->client ?? null;
        $cseq = $body->cseq ?? null;
        if (! is_string($client) || ! is_int($cseq)) {
            return self::error('invalid_batch', 'A batch is { client, cseq, ops }', 422);
        }

        try {
            $received = BoardServer::fromConfig()->receive($model, $client, $cseq, $body->ops ?? null, BoardUser::of(self::user())['id']);
        } catch (BatchRejected $e) {
            return self::error('batch_rejected', $e->getMessage(), 422);
        }
        if ($received->fresh) {
            $realtime->batch($model, $received->batch);
        }

        return self::ok(['seq' => $received->seq]);
    }

    public function poll(Request $request, string $board): JsonResponse
    {
        $model = $this->board($board, edit: false);
        if ($model instanceof JsonResponse) {
            return $model;
        }
        $after = self::body($request)->after ?? 0;
        if (! is_int($after)) {
            return self::error('invalid_poll', 'after is the number of the last batch the client has', 422);
        }
        $batches = BoardServer::fromConfig()->since($model, $after);
        if ($batches === null) {
            return self::error('too_old', 'The batches after this number are no longer kept: reload the board', 410);
        }

        return self::ok(['batches' => $batches]);
    }

    public function presence(Request $request, string $board, BoardRealtime $realtime): JsonResponse
    {
        $model = $this->board($board, edit: false);
        if ($model instanceof JsonResponse) {
            return $model;
        }
        $body = self::body($request);
        $client = $body->client ?? null;
        if (! is_string($client) || $client === '' || strlen($client) > 64) {
            return self::error('invalid_presence', 'Presence is { client, user, cursor?, selection? }', 422);
        }

        // Who it is comes from the session, not from the body.
        $user = BoardUser::of(self::user());
        $entry = ['client' => $client, 'user' => $user['id'], 'name' => $user['name'], 'color' => $user['color']];
        $cursor = $body->cursor ?? null;
        if ($cursor instanceof stdClass && is_numeric($cursor->x ?? null) && is_numeric($cursor->y ?? null)) {
            $entry['cursor'] = ['x' => $cursor->x + 0, 'y' => $cursor->y + 0];
        } elseif (property_exists($body, 'cursor') && $cursor === null) {
            $entry['cursor'] = null;
        }
        $selection = $body->selection ?? null;
        if (is_array($selection)) {
            $entry['selection'] = array_values(array_slice(array_filter($selection, static fn (mixed $id): bool => is_string($id) && strlen($id) <= 128), 0, 1000));
        }

        $presence = PresenceStore::fromConfig()->touch($model, $entry);
        $realtime->presence($model, $presence);

        return self::ok(['presence' => $presence]);
    }

    /**
     * The board, if the user may see it (or draw on it, with `$edit`).
     */
    private function board(string $id, bool $edit): Board|JsonResponse
    {
        $board = Board::query()->find((int) $id, ['id', 'title', 'owner_type', 'owner_id', 'seq', 'locked']);
        if ($board === null) {
            return self::error('not_found', __('Доска не найдена'), 404);
        }
        $user = self::user();
        if (! BoardAccess::canView($user, $board)) {
            return self::error('forbidden', __('Нет доступа к доске'), 403);
        }
        if ($edit && ! BoardAccess::canEdit($user, $board)) {
            return self::error('forbidden', __('Нет права изменять доску'), 403);
        }

        return $board;
    }

    private static function user(): ?object
    {
        return Auth::guard(Panels::currentGuard())->user();
    }

    private static function body(Request $request): stdClass
    {
        $body = json_decode((string) $request->getContent(), false);

        return $body instanceof stdClass ? $body : new stdClass;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function ok(array $payload): JsonResponse
    {
        return new JsonResponse(['success' => true, 'payload' => $payload]);
    }

    private static function error(string $key, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['success' => false, 'payload' => ['errorKey' => $key, 'message' => $message]], $status);
    }
}
