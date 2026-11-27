<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Models;

use Cjmellor\Engageify\Support\HotScore;
use Cjmellor\Engageify\Support\ImpressionRecorder;
use Cjmellor\Engageify\Support\TypeResolver;
use Cjmellor\Engageify\Support\ViewRecorder;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

/**
 * @property string $engagementable_type
 * @property int|string $engagementable_id
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
            $item = [
                'engagementable_type' => $engageable->getMorphClass(),
                'engagementable_id' => $engageable->getKey(),
            ];

            static::query()->firstOrCreate([...$item, 'type' => $type]);

            $ranked = $type !== ViewRecorder::TYPE;

            $counters = static::query()
                ->where($item)
                ->unless($ranked, fn (Builder $query): Builder => $query->where('type', $type))
                ->lockForUpdate()
                ->get();

            $counter = $counters->firstOrFail('type', $type);

            $deltas = ['count' => $countDelta, 'sum_value' => $valueDelta];

            if (! $ranked) {
                static::query()->whereKey($counter->getKey())->incrementEach($deltas);

                return;
            }

            $counter->count += $countDelta;
            $counter->sum_value = (string) ((float) $counter->sum_value + $valueDelta);

            $hotScore = self::hotScoreFrom(counters: $counters, engageable: $engageable);

            static::query()->whereKey($counter->getKey())->incrementEach($deltas, ['hot_score' => $hotScore]);

            $stale = $counters->contains(fn (self $other): bool => ! $other->is($counter) && (float) $other->hot_score !== (float) $hotScore);

            if ($stale) {
                static::query()->where($item)->update(['hot_score' => $hotScore]);
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
        static::query()->whereNotIn('type', [ViewRecorder::TYPE, ImpressionRecorder::TYPE])->delete();
        static::query()->update(['hot_score' => 0]);

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

        static::query()
            ->whereNotIn('type', [ViewRecorder::TYPE, ImpressionRecorder::TYPE])
            ->with('engagementable')
            ->get()
            ->groupBy(fn (self $counter): string => $counter->engagementable_type.':'.$counter->engagementable_id)
            ->each(function (EloquentCollection $counters): void {
                $counter = $counters->firstOrFail();
                $engageable = $counter->engagementable;

                if ($engageable instanceof Model) {
                    static::query()
                        ->where('engagementable_type', $counter->engagementable_type)
                        ->where('engagementable_id', $counter->engagementable_id)
                        ->update(['hot_score' => self::hotScoreFrom(counters: $counters, engageable: $engageable)]);
                }
            });
    }

    public function engagementable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  EloquentCollection<int, static>  $counters
     */
    protected static function hotScoreFrom(EloquentCollection $counters, Model $engageable): float|int
    {
        $engagements = $counters->whereNotIn('type', [ViewRecorder::TYPE, ImpressionRecorder::TYPE])->sum('count');

        if ($engagements === 0) {
            return 0;
        }

        $netScore = (float) $counters
            ->whereIn('type', TypeResolver::weightedValues())
            ->sum(fn (self $counter): float => (float) $counter->sum_value);

        return self::hotScoreFor(sum: $netScore, engageable: $engageable);
    }

    protected static function hotScoreFor(float $sum, Model $engageable): float|int
    {
        $createdAt = $engageable->getAttribute($engageable->getCreatedAtColumn() ?? 'created_at');

        return $createdAt instanceof DateTimeInterface
            ? HotScore::calculate(score: $sum, createdAt: $createdAt)
            : 0;
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
