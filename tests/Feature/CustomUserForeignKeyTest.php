<?php

declare(strict_types=1);

use Cjmellor\Engageify\Enums\EngagementTypes;
use Cjmellor\Engageify\Exceptions\UserCannotEngageException;
use Cjmellor\Engageify\Models\Engagement;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Ballot;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Rating;
use Cjmellor\Engageify\Tests\Fixtures\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config(['engageify.users.foreign_key' => 'member_id', 'engageify.users.model' => User::class]);

    Schema::table('engagements', function (Blueprint $table): void {
        $table->renameColumn(from: 'user_id', to: 'member_id');
    });

    $this->post = User::factory()->createOne();
    $this->other = User::factory()->createOne();

    $this->actingAs($this->user);
});

test('binary Verbs read and write the configured user foreign key', function (): void {
    $this->post->like();
    $this->post->like(actor: $this->other);

    expect(fn () => $this->post->like())->toThrow(UserCannotEngageException::class)
        ->and($this->post->likes(showUsers: true)->pluck('id')->all())->toBe([$this->user->id, $this->other->id])
        ->and($this->user->authoredEngagements()->count())->toBe(1)
        ->and($this->user->hasEngaged(target: $this->post, type: EngagementTypes::Like))->toBeTrue()
        ->and($this->other->hasEngaged(target: $this->user, type: EngagementTypes::Like))->toBeFalse()
        ->and(User::query()->withUserEngagement(EngagementTypes::Like)->findOrFail($this->post->id)->is_engaged)->toBeTrue()
        ->and(User::query()->withUserEngagement(EngagementTypes::Like)->findOrFail($this->other->id)->is_engaged)->toBeFalse();

    $this->post->unlike();

    expect($this->post->likes(showUsers: true)->pluck('id')->all())->toBe([$this->other->id]);
});

test('exclusive flips and re-rating are scoped by the configured user foreign key', function (): void {
    config(['engageify.types' => [Ballot::class, Rating::class]]);

    $this->post->engage(Ballot::Down, actor: $this->other);
    $this->post->engage(Ballot::Down);
    $this->post->engage(Ballot::Up);

    $this->post->engage(Rating::Stars, 4, actor: $this->other);
    $this->post->engage(Rating::Stars, 1);
    $this->post->engage(Rating::Stars, 2);

    expect($this->post->breakdown('ballot'))->toBe([Ballot::Down->value => 1, Ballot::Up->value => 1])
        ->and($this->post->ratingCount(Rating::Stars))->toBe(2)
        ->and($this->post->score(Rating::Stars))->toBe(6.0);
});

test('the factory fills the configured user foreign key', function (): void {
    $engagement = Engagement::factory()->like()->createOne();

    expect(User::query()->whereKey($engagement->member_id)->exists())->toBeTrue();
});
