<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Database\Factories;

use Cjmellor\Engageify\Models\Engagement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

class EngagementFactory extends Factory
{
    protected $model = Engagement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var class-string<Model> $engageable */
        $engageable = config(key: 'engageify.users.model');

        return [
            'engagementable_id' => $engageable::factory(), // @phpstan-ignore staticMethod.notFound
            'engagementable_type' => (new $engageable)->getMorphClass(),
            'user_id' => $engageable::factory(), // @phpstan-ignore staticMethod.notFound
        ];
    }

    public function like(): Factory
    {
        return $this->state(fn (): array => [
            'type' => 'like',
        ]);
    }

    public function dislike(): Factory
    {
        return $this->state(fn (): array => [
            'type' => 'dislike',
        ]);
    }

    public function upvote(): Factory
    {
        return $this->state(fn (): array => [
            'type' => 'upvote',
        ]);
    }

    public function downvote(): Factory
    {
        return $this->state(fn (): array => [
            'type' => 'downvote',
        ]);
    }
}
