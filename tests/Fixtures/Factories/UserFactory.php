<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Tests\Fixtures\Factories;

use Cjmellor\Engageify\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @template TModel of \Cjmellor\Engageify\Tests\Fixtures\User
 *
 * @extends Factory<TModel>
 */
class UserFactory extends Factory
{
    /**
     * @var class-string<TModel>
     */
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make(value: 'password'),
        ];
    }

    public function verified(): self
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => now(),
        ]);
    }
}
