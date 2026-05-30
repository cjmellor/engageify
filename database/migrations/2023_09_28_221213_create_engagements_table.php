<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagements', function (Blueprint $table): void {
            $table->id();
            $table->morphs(name: 'engagementable');
            $table->foreignId(column: $config = config(key: 'engageify.users.foreign_key', default: 'user_id'))->constrained(config(key: 'engageify.users.table'))->cascadeOnDelete();
            $table->string(column: 'type');
            $table->timestamps();

            $table->index(columns: 'type');
            $table->index(columns: $config);
        });
    }
};
