<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engagement_counters', function (Blueprint $table): void {
            $table->decimal(column: 'hot_score', total: 16, places: 7)->default(0);

            $table->index(columns: 'hot_score');
        });
    }
};
