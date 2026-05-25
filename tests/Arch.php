<?php

declare(strict_types=1);

test('Arch tests')
    ->expect(['dd', 'ddd', 'dump'])
    ->each
    ->not
    ->toBeUsed();
