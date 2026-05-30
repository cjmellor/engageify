<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Models;

use Cjmellor\Engageify\Contracts\EngagementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property EngagementType $type
 * @property string|null $value
 */
class Engagement extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function engagementable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config(key: 'engageify.users.model'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => config(key: 'engageify.types'),
            'value' => 'decimal:2',
        ];
    }
}
