<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Models;

use Cjmellor\Engageify\Contracts\EngagementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $type
 * @property int $count
 * @property string $sum_value
 */
class EngagementCounter extends Model
{
    protected $guarded = [];

    public static function record(Model $engageable, EngagementType $type, int $countDelta, int|float $valueDelta): void
    {
        $counter = static::query()->firstOrCreate([
            'engagementable_type' => $engageable->getMorphClass(),
            'engagementable_id' => $engageable->getKey(),
            'type' => $type->value,
        ]);

        $counter->increment(column: 'count', amount: $countDelta);
        $counter->increment(column: 'sum_value', amount: $valueDelta);
    }

    public static function rebuild(): void
    {
        static::query()->delete();

        Engagement::query()
            ->toBase()
            ->selectRaw('engagementable_type, engagementable_id, type, count(*) as aggregate_count, coalesce(sum(value), 0) as aggregate_sum')
            ->groupBy('engagementable_type', 'engagementable_id', 'type')
            ->get()
            ->each(function (object $row): void {
                static::query()->create([
                    'engagementable_type' => $row->engagementable_type,
                    'engagementable_id' => $row->engagementable_id,
                    'type' => $row->type,
                    'count' => (int) $row->aggregate_count,
                    'sum_value' => (float) $row->aggregate_sum,
                ]);
            });
    }

    public function engagementable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sum_value' => 'decimal:2',
        ];
    }
}
