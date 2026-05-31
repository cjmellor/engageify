<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('view_buckets', function (Blueprint $table): void {
            $table->id();
            $table->morphs(name: 'viewable');
            $table->date(column: 'date');
            $table->unsignedBigInteger(column: 'count')->default(0);
            $table->timestamps();

            $table->unique(columns: ['viewable_type', 'viewable_id', 'date']);
            $table->index(columns: ['viewable_type', 'date']);
        });
    }
};
