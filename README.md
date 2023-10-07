[![Latest Version on Packagist](https://img.shields.io/packagist/v/cjmellor/engageify?color=rgb%2856%20189%20248%29&label=release&style=for-the-badge)](https://packagist.org/packages/cjmellor/engageify)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/cjmellor/engageify/run-pest.yml?branch=main&label=tests&style=for-the-badge&color=rgb%28134%20239%20128%29)](https://github.com/cjmellor/engageify/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/cjmellor/engageify.svg?color=rgb%28249%20115%2022%29&style=for-the-badge)](https://packagist.org/packages/cjmellor/engageify)
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/cjmellor/engageify/php?color=rgb%28165%20180%20252%29&logo=php&logoColor=rgb%28165%20180%20252%29&style=for-the-badge)
![Laravel Version](https://img.shields.io/badge/laravel-^10-rgb(235%2068%2050)?style=for-the-badge&logo=laravel)

Engageify is a Laravel package that allows you to integrate engagement features like user reactions (likes, upvotes) to your models.

![](https://banners.beyondco.de/Engageify.png?theme=light&packageManager=composer+require&packageName=cjmellor%2Fengageify&pattern=brickWall&style=style_2&description=Integrate+user+engagement+to+your+models&md=1&showWatermark=0&fontSize=100px&images=thumbs-up&widths=300&heights=300)

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

An **Event** is run on each reaction occurrence.

- `ModelLikedEvent`
- `ModelDislikedEvent`
- `ModelUpvotedEvent`
- `ModelDownvotedEvent`
- 
#### Multiple Reactions

By default, a User can only react once to a Model. If you wish to allow multiple reactions, you can do so by setting the `engagement.allow_multiple_engagements` config value to `true`.

### "Like" Specific Reaction

The "like" reaction has some additional functionality. A "like" can be "unliked". This shouldn't be confused with a "dislike" as a "dislike" counts as an engagement, whereas an "unlike" is deleting the engagement.

```php
$comment->unlike();
```

When a Model is "unliked", an **Event** is fired.

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

### Caching Engagement Counts

A caching feature is available, which is off by default but can be changed in the config file, or by adding it to your `.env` file

```text
ENGAGEIFY_ALLOW_CACHING=true
ENGAGEIFY_CACHE_DURATION=3600
```

When an engagement is retrieved, it is cached, and further requests will retireve the data from the cache.

On each new engagement, the cache will be cleared.

#### Fetch Users' Who Engaged

Instead of just fetching the amount of engagements, you can fetch the Users who engaged.

```php
$post->likes(showUsers: true);
````

This will return a Collection of Users who liked the Model.

This works on all 4 fetch methods.

## Events

Each engagement has an event that is fired when it occurs.

Here is an example of an Event when a Model is "liked". Each Event will return the same data

```php
public Model $user,
public Model $engageable,
public Engagement $engagement,
```

When a Model is "unliked", a `ModelDisengagedEvent` is fired.

```php
public Model $user,
public Model $engageable,
```

# Testing

```
composer pest
```

# Changelog

Please see the [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

# License

The MIT Licence (MIT). Please see [LICENSE](LICENSE.md) for more information.
