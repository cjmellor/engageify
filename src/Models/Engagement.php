<?php

namespace Cjmellor\Engageify\Models;

use Cjmellor\Engageify\Database\Factories\EngagementFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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

//    public function newFactory(): Factory
//    {
//        return EngagementFactory::new();
//    }
}
