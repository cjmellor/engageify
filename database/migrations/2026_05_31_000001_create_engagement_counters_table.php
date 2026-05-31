<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagement_counters', function (Blueprint $table): void {
            $table->id();
            $table->morphs(name: 'engagementable');
            $table->string(column: 'type');
            $table->bigInteger(column: 'count')->default(0);
            $table->decimal(column: 'sum_value', total: 8, places: 2)->default(0);
            $table->timestamps();

            $table->unique(columns: ['engagementable_type', 'engagementable_id', 'type']);
        });

        Schema::table('engagements', function (Blueprint $table): void {
            $table->index(columns: ['engagementable_type', 'engagementable_id', 'type']);
        });
    }
};
