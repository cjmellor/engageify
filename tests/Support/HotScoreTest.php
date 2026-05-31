<?php

declare(strict_types=1);

use Cjmellor\Engageify\Support\HotScore;

test('HotScore applies the time-anchored Reddit formula', function (): void {
    $createdAt = Illuminate\Support\Facades\Date::createFromTimestamp(1134028003 + 45000);

    expect(HotScore::calculate(0, $createdAt))->toBe(1.0)
        ->and(HotScore::calculate(10, $createdAt))->toBe(2.0)
        ->and(HotScore::calculate(-10, $createdAt))->toBe(0.0);
});

test('HotScore rewards a higher score and a later creation time', function (): void {
    $at = Illuminate\Support\Facades\Date::createFromTimestamp(1134028003 + 45000);
    $later = Illuminate\Support\Facades\Date::createFromTimestamp(1134028003 + 90000);

    expect(HotScore::calculate(100, $at))->toBeGreaterThan(HotScore::calculate(10, $at))
        ->and(HotScore::calculate(10, $later))->toBeGreaterThan(HotScore::calculate(10, $at));
});
