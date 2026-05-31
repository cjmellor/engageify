<?php

declare(strict_types=1);

test('Arch tests')
    ->expect(['dd', 'ddd', 'dump'])
    ->each
    ->not
    ->toBeUsed();

test('shipped factories do not depend on test fixtures')
    ->expect('Cjmellor\Engageify\Database\Factories')
    ->not
    ->toUse('Cjmellor\Engageify\Tests');
