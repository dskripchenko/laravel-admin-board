<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who a panel user is on a board: `{ id, name, color }`, the colour stable
 * per user (derived from the id), so cursors keep their colour.
 */
final class BoardUser
{
    /** Distinct, readable on white and on the dark theme. */
    public const COLORS = [
        '#e11d48', '#ea580c', '#d97706', '#65a30d', '#059669', '#0891b2',
        '#2563eb', '#4f46e5', '#7c3aed', '#c026d3', '#db2777', '#0d9488',
    ];

    /**
     * @return array{id: string, name: string, color: string}
     */
    public static function of(?object $user): array
    {
        $id = $user instanceof Authenticatable ? (string) $user->getAuthIdentifier() : '';
        $name = '';
        foreach (['name', 'email'] as $attribute) {
            $value = $user !== null && isset($user->{$attribute}) ? $user->{$attribute} : null;
            if (is_string($value) && $value !== '') {
                $name = $value;
                break;
            }
        }

        return ['id' => $id, 'name' => $name !== '' ? $name : $id, 'color' => self::color($id)];
    }

    public static function color(string $id): string
    {
        return self::COLORS[crc32($id) % count(self::COLORS)];
    }
}
