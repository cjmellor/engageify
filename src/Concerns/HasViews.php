<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Concerns;

use Cjmellor\Engageify\Exceptions\InvalidViewPeriodException;
use Cjmellor\Engageify\Models\EngagementCounter;
use Cjmellor\Engageify\Models\ViewBucket;
use Cjmellor\Engageify\Support\ViewRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

trait HasViews
{
    /**
     * @var array<string, int>
     */
    protected const array VIEW_WINDOW_DAYS = [
        'day' => 1,
        'week' => 7,
        'month' => 30,
        'year' => 365,
    ];

    public function recordView(): void
    {
        ViewRecorder::record(viewable: $this);
    }

    public function viewCount(): int
    {
        return (int) EngagementCounter::query()
            ->where('engagementable_type', $this->getMorphClass())
            ->where('engagementable_id', $this->getKey())
            ->where('type', ViewRecorder::TYPE)
            ->sum(column: 'count');
    }

    public function viewsInLast(int $days): int
    {
        return (int) ViewBucket::query()
            ->where('viewable_type', $this->getMorphClass())
            ->where('viewable_id', $this->getKey())
            ->where('date', '>=', $this->viewWindowFloor($days))
            ->sum(column: 'count');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeOrderByMostViewed(Builder $query, ?string $period = null, string $direction = 'desc'): Builder
    {
        $model = $query->getModel();

        if ($period === null) {
            return $query
                ->leftJoinSub(
                    EngagementCounter::query()
                        ->select(['engagementable_id', 'count'])
                        ->where('engagementable_type', $model->getMorphClass())
                        ->where('type', ViewRecorder::TYPE),
                    'view_totals',
                    'view_totals.engagementable_id',
                    '=',
                    $model->getQualifiedKeyName(),
                )
                ->orderBy('view_totals.count', $direction)
                ->select("{$model->getTable()}.*");
        }

        return $query
            ->leftJoinSub(
                ViewBucket::query()
                    ->selectRaw('viewable_id, sum(count) as views')
                    ->where('viewable_type', $model->getMorphClass())
                    ->where('date', '>=', $this->viewWindowFloor($this->periodToDays($period)))
                    ->groupBy('viewable_id'),
                'view_window',
                'view_window.viewable_id',
                '=',
                $model->getQualifiedKeyName(),
            )
            ->orderBy('view_window.views', $direction)
            ->select("{$model->getTable()}.*");
    }

    protected function viewWindowFloor(int $days): Carbon
    {
        return today()->subDays($days - 1);
    }

    protected function periodToDays(string $period): int
    {
        return self::VIEW_WINDOW_DAYS[$period]
            ?? throw InvalidViewPeriodException::unsupported($period, array_keys(self::VIEW_WINDOW_DAYS));
    }
}
