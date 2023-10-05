<?php

use Cjmellor\Engageify\Tests\Fixtures\User;
use Cjmellor\Engageify\Tests\TestCase;

uses(TestCase::class)
    ->beforeEach(fn () => $this->user = User::factory()->createOne())
    ->in(__DIR__);
