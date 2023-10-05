<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagements', function (Blueprint $table) {
            $table->id();
            $table->morphs(name: 'engagementable');
            $table->foreignId(column: $config = config(key: 'engageify.users.foreign_key', default: 'user_id'))->constrained(config(key: 'engageify.users.table'))->cascadeOnDelete();
            $table->enum(column: 'type', allowed: ['like', 'dislike', 'upvote', 'downvote']);
            $table->timestamps();

            // Indexes
            $table->index(columns: 'type');
            $table->index(columns: $config);
        });
    }
};
