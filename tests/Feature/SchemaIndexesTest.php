<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

test('the counter table is indexed for type-filtered aggregates', function (): void {
    $indexes = collect(Schema::getIndexes('engagement_counters'))
        ->map(fn (array $index): array => $index['columns']);

    expect($indexes->contains(['type']))->toBeTrue();
});

test('the bucket table is indexed for the viewable-type/date window scan', function (): void {
    $indexes = collect(Schema::getIndexes('view_buckets'))
        ->map(fn (array $index): array => $index['columns']);

    expect($indexes->contains(['viewable_type', 'date']))->toBeTrue();
});
