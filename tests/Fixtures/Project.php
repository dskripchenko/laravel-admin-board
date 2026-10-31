<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Tests\Fixtures;

use Dskripchenko\LaravelAdminBoard\Concerns\HasBoard;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 */
final class Project extends Model
{
    use HasBoard;

    protected $table = 'projects';

    protected $guarded = [];

    public function boardTitle(): string
    {
        return 'Board of '.$this->name;
    }
}
