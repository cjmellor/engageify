# Upgrading

## From v1 to v2

v2 reworks how engagement counts are stored and read, and removes the cache
layer. Follow these steps once when upgrading an existing v1 installation.

### 1. Update the dependency

```bash
composer require cjmellor/engageify:^2.0
```

### 2. Publish and run the new migrations

v2 adds a `value` column to the `engagements` table and introduces the
denormalised `engagement_counters` table (plus the opt-in `view_buckets` table
that powers time-windowed views):

```bash
php artisan vendor:publish --tag="engageify-migrations"
php artisan migrate
```

### 3. Backfill the counters (required)

Counts and scores are now read from `engagement_counters`, which starts empty.
Rebuild it from your existing `engagements` rows:

```bash
php artisan engageify:recount
```

**Until you run this, all counts, scores, and ranking queries return `0` for
pre-existing engagements.** The engagement rows themselves are untouched — only
the counter table needs building.

### Other breaking changes

- **Raised minimum requirements.** v2 requires **PHP `^8.3`** and **Laravel `^12.0|^13.0`** (Laravel 10/11 and PHP 8.2 are no longer supported). Bump your own constraints before upgrading.

- **The `Engageify` facade has been removed.** The `Cjmellor\Engageify\Facades\Engageify` class and its `Engageify` alias no longer exist. Engagement is driven entirely through the `HasEngagements` trait on your models (`$model->like()`, `$model->engage(...)`, etc.) — replace any facade calls with the equivalent model methods.

- **The five reaction-specific events were replaced by two generic events.** `ModelLikedEvent`, `ModelDislikedEvent`, `ModelUpvotedEvent`, `ModelDownvotedEvent`, and `ModelDisengagedEvent` are gone. Every engage now fires `Cjmellor\Engageify\Events\Engaged` and every disengage fires `Cjmellor\Engageify\Events\Disengaged`. Update your listeners to subscribe to the new events and branch on the payload:

  ```php
  // Engaged: public Model $actor, Model $engageable, EngagementType $type, Engagement $engagement
  // Disengaged: public Model $actor, Model $engageable, EngagementType $type

  public function handle(Engaged $event): void
  {
      if ($event->type === EngagementTypes::Like) {
          // ... what ModelLikedEvent used to do
      }
  }
  ```

### Notes

- **The cache layer has been removed.** The `allow_caching` and `cache_duration`
  config keys no longer exist. Counts are maintained inside the same database
  transaction as each engage/disengage, so they are always fresh — there is
  nothing to invalidate.
- If you keep a published config file, re-publish it
  (`php artisan vendor:publish --tag="engageify-config"`) to pick up the new
  `views` and `impressions` sections.
