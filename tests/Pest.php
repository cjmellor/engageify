<?php

use Cjmellor\Engageify\Tests\Fixtures\User;
use Cjmellor\Engageify\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(fn () => $this->user = User::factory()->createOne())
    ->in(__DIR__);
