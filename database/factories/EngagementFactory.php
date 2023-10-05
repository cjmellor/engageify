<?php

namespace Cjmellor\Engageify\Database\Factories;

use Cjmellor\Engageify\Models\Engagement;
use Cjmellor\Engageify\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EngagementFactory extends Factory
{
    protected $model = Engagement::class;

    public function definition(): array
    {
        return [
            'engagementable_id' => User::factory()->createOne()->id,
            'engagementable_type' => User::class,
            'user_id' => User::factory(),
        ];
    }

    public function like(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'like',
        ]);
    }

    public function dislike(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'dislike',
        ]);
    }

    public function upvote(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'upvote',
        ]);
    }

    public function downvote(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'downvote',
        ]);
    }
}
