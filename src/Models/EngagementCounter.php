<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Models;

use Cjmellor\Engageify\Support\HotScore;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

/**
 * @property string $type
 * @property int $count
 * @property string $sum_value
 * @property float $hot_score
 */
class EngagementCounter extends Model
{
    protected $guarded = [];

    public static function record(Model $engageable, string $type, int $countDelta, int|float $valueDelta): void
    {
        DB::transaction(function () use ($engageable, $type, $countDelta, $valueDelta): void {
            static::query()->firstOrCreate([
                'engagementable_type' => $engageable->getMorphClass(),
                'engagementable_id' => $engageable->getKey(),
                'type' => $type,
            ]);

            $counter = static::query()
                ->where('engagementable_type', $engageable->getMorphClass())
                ->where('engagementable_id', $engageable->getKey())
                ->where('type', $type)
                ->lockForUpdate()
                ->firstOrFail();

            $counter->increment(column: 'count', amount: $countDelta);
            $counter->increment(column: 'sum_value', amount: $valueDelta);

            if ((float) $valueDelta !== 0.0) {
                $counter->refresh();

                $sum = (float) $counter->sum_value;
                $createdAt = $engageable->getAttribute($engageable->getCreatedAtColumn() ?? 'created_at');

                $counter->hot_score = $sum !== 0.0 && $createdAt instanceof DateTimeInterface
                    ? HotScore::calculate(score: $sum, createdAt: $createdAt)
                    : 0;
                $counter->save();
            }
        });
    }

    public static function tally(string $engagementableType, int|string $engagementableId, string $type): void
    {
        static::query()->firstOrCreate([
            'engagementable_type' => $engagementableType,
            'engagementable_id' => $engagementableId,
            'type' => $type,
        ])->increment(column: 'count');
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
            'hot_score' => 'double',
        ];
    }
}
