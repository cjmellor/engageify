<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Database\Factories;

use Cjmellor\Engageify\Models\Engagement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Cjmellor\Engageify\Models\Engagement>
 */
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

        throw_unless(method_exists($engageable, 'factory'), LogicException::class, "The engageify.users.model [{$engageable}] must use the HasFactory trait.");

        return [
            'engagementable_id' => $engageable::factory(),
            'engagementable_type' => (new $engageable)->getMorphClass(),
            Engagement::userForeignKey() => $engageable::factory(),
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
