<?php

declare(strict_types=1);

use Cjmellor\Engageify\Support\EmojiCollation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! EmojiCollation::isRequired()) {
            return;
        }

        foreach (['engagements', 'engagement_counters'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                EmojiCollation::pin($blueprint->string(column: 'type'))->change();
            });
        }
    }
};
