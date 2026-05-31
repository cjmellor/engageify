[![Latest Version on Packagist](https://img.shields.io/packagist/v/cjmellor/engageify?color=rgb%2856%20189%20248%29&label=release&style=for-the-badge)](https://packagist.org/packages/cjmellor/engageify)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/cjmellor/engageify/run-pest.yml?branch=main&label=tests&style=for-the-badge&color=rgb%28134%20239%20128%29)](https://github.com/cjmellor/engageify/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/cjmellor/engageify.svg?color=rgb%28249%20115%2022%29&style=for-the-badge)](https://packagist.org/packages/cjmellor/engageify)
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/cjmellor/engageify/php?color=rgb%28165%20180%20252%29&logo=php&logoColor=rgb%28165%20180%20252%29&style=for-the-badge)
![Laravel Version](https://img.shields.io/badge/laravel-^10-rgb(235%2068%2050)?style=for-the-badge&logo=laravel)

Engageify is a Laravel package that allows you to integrate engagement features like user reactions (likes, upvotes) to your models.

![Engagement Package GitHub Preview-2](https://github.com/cjmellor/engageify/assets/1848476/9519571c-43f1-4297-af5a-8390a4dd4b29)

## Installation

You can install the package via composer:

```bash
composer require cjmellor/engageify
```

Publish the config file (optional)

```bash
php artisan vendor:publish --tag="engageify-config"
```

The published config file allows you to customize table names, model relationships, and more.

## Usage

For Models you wish to have engagement features (likes/upvotes), use the Engageable trait.

```php
<?php

use Cjmellor\Engageify\Concerns\HasEngagements;

class BlogPost extends Model
{
    use HasEngagements;

    // ...
}
```

### Reactions

Allow Users to react to a Model.

```php
// Like
$post->like();

// Dislike
$post->dislike();

// Upvote
$post->upvote();

// Downvote
$post->downvote();
```

A generic `Engaged` event is dispatched on each reaction, carrying the actor, the engaged Model, and the engagement Verb. See [Events](#events).

#### Multiple Reactions

By default, a User can only react once to a Model. If you wish to allow multiple reactions, you can do so by setting the `engagement.allow_multiple_engagements` config value to `true`.

### Custom Engagement Types

The default Verbs (`like`, `dislike`, `upvote`, `downvote`) are cases of a string-backed enum. To add your own Verbs — without a migration — create an enum implementing `Cjmellor\Engageify\Contracts\EngagementType` and point the config at it:

```php
use Cjmellor\Engageify\Contracts\EngagementType;

enum Reaction: string implements EngagementType
{
    case Bookmark = 'bookmark';
    case Celebrate = 'celebrate';
}
```

```php
// config/engageify.php
'types' => App\Enums\Reaction::class,
```

Engage with a custom Verb by passing the enum case:

```php
$post->engage(Reaction::Bookmark);

$post->engagementCount(Reaction::Bookmark); // 1

$post->disengage(Reaction::Bookmark);
```

Passing a Verb that does not belong to the configured enum throws an `UnknownEngagementType` exception.

### Weighted Verbs & Engagement Values

Engagements can carry an optional numeric **value** (a nullable signed decimal column). A Verb opts in by implementing `Cjmellor\Engageify\Contracts\HasWeight`, which derives a fixed weight per case — for example an upvote is `+1` and a downvote `-1`:

```php
use Cjmellor\Engageify\Contracts\EngagementType;
use Cjmellor\Engageify\Contracts\HasWeight;

enum Vote: string implements EngagementType, HasWeight
{
    case Up = 'up';
    case Down = 'down';

    public function weight(): int
    {
        return match ($this) {
            self::Up => 1,
            self::Down => -1,
        };
    }
}
```

When a `HasWeight` Verb is engaged its weight is stored automatically. You cannot pass your own value to a `HasWeight` Verb (or to a binary Verb) — doing so throws an `EngagementValueException`. Binary Verbs store `null`.

```php
$post->engage(Vote::Up);   // stores the derived value 1
$post->engage(Vote::Down); // stores the derived value -1
```

Read the value back per Verb with `score()` (a `SUM`) and `averageOf()` (an `AVG`):

```php
$post->score(Vote::Up);     // total stored weight for upvotes
$post->averageOf(Vote::Up); // mean stored weight for upvotes
```

Both throw an `EngagementValueException` on a binary Verb, which carries no value to aggregate.

> Upgrading from v1? The `value` column ships as an additive migration — publish it with `php artisan vendor:publish --tag="engageify-migrations"` and run `php artisan migrate`.

### Exclusive Groups (vote-style)

For mutually-exclusive Verbs — upvote/downvote, or a single-choice reaction — implement `Cjmellor\Engageify\Contracts\Exclusive` and return a shared `group()` key. Several independent Groups can live in one enum:

```php
use Cjmellor\Engageify\Contracts\EngagementType;
use Cjmellor\Engageify\Contracts\Exclusive;
use Cjmellor\Engageify\Contracts\HasWeight;

enum Vote: string implements EngagementType, Exclusive, HasWeight
{
    case Up = 'up';
    case Down = 'down';

    public function group(): string
    {
        return 'vote';
    }

    public function weight(): int
    {
        return match ($this) {
            self::Up => 1,
            self::Down => -1,
        };
    }
}
```

Recording an Exclusive Verb atomically clears any existing engagement by the same user whose Verb shares the group, then records the new one — so a user can never hold two members of a group at once, even under concurrent requests:

```php
$post->engage(Vote::Down); // down
$post->engage(Vote::Up);   // switches: down removed, up recorded (one transaction)
$post->engage(Vote::Up);   // re-recording the active member toggles it off
```

Switching fires a `Disengaged` event for the cleared member and an `Engaged` event for the new one. Read a Group with `netScore()` (summed weights — a Reddit-style score) and `breakdown()` (per-member counts — handy for a reaction bar):

```php
$post->netScore('vote');   // e.g. 42
$post->breakdown('vote');  // ['up' => 50, 'down' => 8]
```

### Ratings

For star-style or scored ratings, implement `Cjmellor\Engageify\Contracts\Rateable` with a `min()`, `max()` and `step()` (return `null` for a continuous scale). A `Rateable` Verb takes a **caller-supplied value validated against its scale**:

```php
use Cjmellor\Engageify\Contracts\EngagementType;
use Cjmellor\Engageify\Contracts\Rateable;

enum Rating: string implements EngagementType, Rateable
{
    case Stars = 'stars';

    public function min(): float
    {
        return 1.0;
    }

    public function max(): float
    {
        return 5.0;
    }

    public function step(): ?float
    {
        return 1.0;
    }
}
```

```php
$film->engage(Rating::Stars, 4);   // stored, validated against 1–5 step 1
$film->engage(Rating::Stars, 6);   // throws InvalidRatingException (out of range)
$film->engage(Rating::Stars, 2.5); // throws InvalidRatingException (off step)
```

Ratings are **scalar with upsert semantics**: at most one rating per user per target. Re-rating updates the existing row (and ignores `allow_multiple_engagements` entirely — re-rating is governed by the Verb, not config). Read them back with:

```php
$film->averageRating(Rating::Stars);     // mean rating
$film->ratingCount(Rating::Stars);       // number of ratings
$film->ratingDistribution(Rating::Stars); // ['5.00' => 12, '4.00' => 3, ...]
$film->bayesianAverage(Rating::Stars);    // mean pulled toward the global average for low-count items
```

`bayesianAverage()` seeds each target with `m` "average" votes (the `engageify.bayesian_minimum` config, overridable per call) so a single 5-star rating doesn't outrank a film with hundreds of high scores.

### "Like" Specific Reaction

The "like" reaction has some additional functionality. A "like" can be "unliked". This shouldn't be confused with a "dislike" as a "dislike" counts as an engagement, whereas an "unlike" is deleting the engagement.

```php
$comment->unlike();
```

When a Model is "unliked", a generic `Disengaged` event is fired.

There is also a convenient `toggle()` method that will toggle between "like" and "unlike".

```php
$comment->toggleLike();
```

### Fetch Engagements

Get the counts of the engagements.

```php
// Likes
$post->likes();

// Dislikes
$post->dislikes();

// Upvotes
$post->upvotes();

// Downvotes
$post->downvotes();
```

Counts are read from a denormalised `engagement_counters` table that is kept in step **inside the same database transaction** as every engage/disengage/flip — so they are always fresh and O(1) to read (no cache to invalidate, and no stale-count-after-unlike bug).

> The counters are maintained automatically. If you ever write engagement rows directly (bypassing the trait), rebuild them with `php artisan engageify:recount`.

### Ranking

Because counts live in the database, you can sort by them in SQL straight from the Engageable's query builder:

```php
// Most-liked posts first
Post::query()->orderByEngagementCount(EngagementTypes::Like)->get();

// Highest net vote score first
Post::query()->orderByScore('vote')->get();
```

For Reddit-style ranking:

```php
// Hot — net score balanced against age (stored, time-anchored; no cron)
Post::query()->hot()->get();

// Top — items created within a window, ranked by net score ('all' = no window)
Post::query()->top('week')->get();

// Bayesian — honest average that pulls low-count items toward the global mean
Film::query()->orderByBayesian(Rating::Stars)->get();
```

`hot_score` is stored on the counter and recomputed in the same transaction whenever an item's net score changes. All counter columns (`hot_score`, `sum_value`, `count`) plus the engageable's `created_at` are queryable, so a bespoke ranking (e.g. "controversial") is a normal one-line scope. All scopes accept a direction (`'desc'` by default).

### Actor-Side Queries

So far the API has been *engageable*-side ("how many likes does this post have?"). To answer *actor*-side questions ("what has this user engaged with?"), add the `EngagesWith` trait to your actor (usually the `User`) model:

```php
use Cjmellor\Engageify\Concerns\EngagesWith;

class User extends Authenticatable
{
    use EngagesWith;
}
```

```php
$user->hasEngaged($post, EngagementTypes::Like);     // bool
$user->engagementValueFor($post, Vote::Up);          // the stored value, or null
$user->ratingFor($film, Rating::Stars);              // alias for engagementValueFor
$user->engagements;                                  // every engagement this user authored
```

> If a model is **both** an actor and an engageable, both traits expose an `engagements()` relation — resolve the clash with PHP's trait syntax (`HasEngagements::engagements insteadof EngagesWith;` and alias the other).

#### Feed state without N+1

`withUserEngagement($type, ?$user = null)` attaches each engageable's state for a given user (defaulting to the auth user) as a typed `is_engaged` boolean — plus an `engagement_value` for `Rateable` Verbs — resolved for the whole result set in **one** query:

```php
$feed = Post::query()->withUserEngagement(EngagementTypes::Like)->get();

$feed->first()->is_engaged; // true/false — no extra query per row
```

#### Fetch Users' Who Engaged

Instead of just fetching the amount of engagements, you can fetch the Users who engaged.

```php
$post->likes(showUsers: true);
````

This will return a Collection of Users who liked the Model.

This works on all 4 fetch methods.

## Events

Two generic events are dispatched for every engagement, regardless of the Verb.

`Cjmellor\Engageify\Events\Engaged` is dispatched when a Model is engaged:

```php
public Model $actor,
public Model $engageable,
public EngagementType $type,
public Engagement $engagement,
```

`Cjmellor\Engageify\Events\Disengaged` is dispatched when an engagement is removed (e.g. an "unlike"):

```php
public Model $actor,
public Model $engageable,
public EngagementType $type,
```

# Testing

```
composer pest
```

# Changelog

Please see the [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

# License

The MIT Licence (MIT). Please see [LICENSE](LICENSE.md) for more information.
