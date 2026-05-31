<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $count
 */
class ViewBucket extends Model
{
    protected $guarded = [];

    public static function record(Model $viewable, DateTimeInterface $date): void
    {
        $bucket = static::query()->firstOrCreate([
            'viewable_type' => $viewable->getMorphClass(),
            'viewable_id' => $viewable->getKey(),
            'date' => $date,
        ]);

        $bucket->increment(column: 'count');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'count' => 'integer',
        ];
    }
}
