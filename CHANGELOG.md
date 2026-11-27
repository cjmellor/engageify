# Changelog

## v2.2.0 - 2026-11-27

## Ranking that matches the docs

`hot()` and `top()` are documented as ranking by net score, but neither did.

`hot_score` was computed per Verb and `hot()` took the highest one, so downvotes never cooled an item: 50 up and 49 down ranked the same as 50 up and 0 down. It is now computed from the item's net score across its weighted (`HasWeight`) Verbs.

- **Ratings no longer count as votes.** `hot()` and `top()` leave `Rateable` Verbs out, so a five-star rating is not five upvotes.
- **Engaged items with a net score of zero rank by age.** An item with only likes, or with as many downvotes as upvotes, used to score `0` and sink below everything.
- **Items with no engagements score `0`.** That covers an item whose engagements were all withdrawn and one never engaged at all; `hot()` now sorts both the same on every database.

Engaging is also cheaper. The counter update reads the item's counter rows once and writes once: a like goes from 5 queries to 4, and a vote from 7 to 5.

### Added

- **Reading impressions.** `HasViews` gains `impressionCount()` and an `orderByMostImpressions()` scope. Impressions were recorded but there was no way to read them.

  ```php
  $thread->impressionCount();
  Thread::query()->orderByMostImpressions()->get();
  ```

### Fixed

- **`engageify:recount` no longer wipes view and impression counts.** It deleted every counter row and rebuilt from `engagements`, but views and impressions have no rows there, so running the documented command zeroed them for good.
- **`engageify.users.foreign_key` is honoured everywhere.** The migration created the configured column, but every read and write used `user_id`, so any other value broke every engage call.
- **Impressions are deduplicated per signed-in user.** The impression endpoint has no session, so signed-in viewers were deduplicated by IP and user agent. The viewer's id is now embedded in the signed token when `@impression` renders. Guests still fall back to the IP and user agent hash.
- **`EngagementFactory` names a misconfigured user model.** If `engageify.users.model` has no `factory()`, it throws a `LogicException` naming the config key instead of a fatal "undefined method".

### Upgrading

Stored `hot_score` values were computed per Verb, so rebuild them once after updating:

```bash
php artisan engageify:recount
```

There is no migration to run, and the command now keeps your view and impression counts.

Things to check in your own code:

- **Ranking order changes.** Anything that depends on the old `hot()` or `top()` order will see a different one.
- **`ImpressionToken::verify()` returns a third key, `viewer`.** Code comparing its result to an exact array needs updating. Tokens rendered before the upgrade still verify, as guests.
- **Do not full-page cache `@impression` markup for signed-in users.** The token names the viewer it was rendered for, so a cached copy served to someone else is counted as the original viewer.
- **An engage now locks all of the item's counter rows**, not only the row for its Verb, so simultaneous engagements on one item with different Verbs wait on each other.

### Maintenance

- Rector, PHPStan, type coverage and 100% line coverage now run in CI.
- Development dependencies and GitHub Actions were updated. These are development-only; the package still requires only `illuminate/database` and `illuminate/support`.

MySQL, MariaDB and PostgreSQL are not in the CI matrix; the suite runs on SQLite.

**Full Changelog**: https://github.com/cjmellor/engageify/compare/v2.1.2...v2.2.0

## v2.1.2 - 2026-09-22

### MySQL and MariaDB support

Engageify v2.0.0 through v2.1.1 could not be installed on MySQL or MariaDB at all. The unique index on `engagement_counters` was left unnamed, and the name Laravel generates for it — `engagement_counters_engagementable_type_engagementable_id_type_unique` — is 69 characters against MySQL's 64-character identifier cap, so creating the table failed outright. The index is now given an explicit name and the table builds on every supported engine.

### Fixed

- **Emoji Verb values no longer collapse into one counter on MySQL.** A backed enum may use an emoji as its value, which lands in the `type` column of `engagements` and `engagement_counters`. `utf8mb4_unicode_ci` gives most emoji an identical sort weight, so the unique index over that column treated an entire emoji set as a single value — one row and one counter for all of them. The column is now pinned to `utf8mb4_bin` on MySQL and MariaDB; other engines already sort emoji apart and are left alone.

### Upgrading

Re-publish the migrations and run them:

```bash
php artisan vendor:publish --tag="engageify-migrations"
php artisan migrate

```
This picks up `pin_engagement_type_collation`, which converts the `type` column on both tables in place. It is a no-op on any driver other than MySQL and MariaDB. No counter rebuild is needed: because no v2 release could be installed on MySQL, there is no existing installation whose counters could have been collapsed.

One behavioural note for MySQL users: `utf8mb4_bin` compares byte-exactly, so `type` is now case-sensitive. The package always writes and matches enum values verbatim, so nothing inside Engageify changes — but a query written directly against `engagements.type` with differently-cased text will no longer match.

MySQL and MariaDB are not in the CI matrix; the suite runs on SQLite.

**Full Changelog**: https://github.com/cjmellor/engageify/compare/v2.1.1...v2.1.2

## v2.1.1 - 2026-07-27

A patch release fixing two correctness bugs in `HasEngagements`. No API or migration changes.

### Fixed

- **`Disengaged` is no longer dispatched when nothing was removed.** `disengage()` guarded the delete and the counter adjustment behind a non-empty check but fired the event unconditionally, so disengaging something that was never engaged emitted a phantom event. This brings it in line with `flipExclusive()`, which only ever emitted the event for rows it actually deleted. Listeners can now treat every `Disengaged` as a real deletion.
- **Closed a check-then-insert race in `engage()`.** With `allow_multiple_engagements = false` the duplicate guard ran outside the transaction, so concurrent requests could both pass it, both insert, and double-count the counter. The check now runs inside the transaction under `lockForUpdate()`, matching `rate()` and `flipExclusive()`. Note that gap-locking prevents the phantom insert on MySQL and SQLite serialises writes, but PostgreSQL does not gap-lock a `SELECT ... FOR UPDATE` matching zero rows.

**Full Changelog**: https://github.com/cjmellor/engageify/compare/v2.1.0...v2.1.1

## v2.1.0 - 2026-07-27

### Engaging as a specific actor

Engagements previously resolved the acting user from `auth()` alone, so applications whose authenticated principal is not the model that owns engagements — multi-tenant setups, impersonation, queued jobs, seeders — could not use the trait's mutators at all.

`engage()`, `disengage()` and the convenience verbs (`like`, `dislike`, `upvote`, `downvote`, `unlike`, `toggleLike`) now take an optional trailing `actor`, defaulting to the authenticated user as before:

```php
$post->engage(Reaction::Bookmark, actor: $user);
$post->like(actor: $user);
$post->unlike(actor: $user);



```
The resolved actor is what gets written to `user_id`, what scopes the duplicate-engagement guard, the exclusive-group flip and the rating upsert, and what the `Engaged`/`Disengaged` events carry.

#### Fixed

Exclusive-group flips dispatched events reporting `auth()->user()` while deleting rows keyed on `auth()->id()`. Those are now consistently the same actor — a correctness fix that applies even when no explicit actor is passed.

Engaging with no actor and nobody authenticated now throws `UserCannotEngageException` with a clear message, rather than failing deeper in the stack.

#### Upgrading

Nothing to do. The actor parameter is a new optional trailing argument throughout, so this is a drop-in upgrade from v2.0.0 with no migration and no config change.

`withUserEngagement()` keeps its existing `$user` parameter name rather than being renamed to `$actor`, so named-argument callers are unaffected. It takes the same model you would pass as `actor:`.

#### Maintenance

The development dependency lock was refreshed onto current releases, clearing the open Dependabot alerts against `composer.lock`. These were all development-only transitive dependencies pulled in through `orchestra/testbench` — the package requires only `illuminate/database` and `illuminate/support`, and consumers resolve their own dependencies, so no installation of engageify was affected.

**Full Changelog**: https://github.com/cjmellor/engageify/compare/v2.0.0...v2.1.0

## v2.0.0 - 2026-06-03

A major release adding ratings, view & impression tracking, Hot/Top ranking, weighted and custom engagement Verbs, and actor-side queries. **Read [`UPGRADING.md`](UPGRADING.md) before upgrading from v1** — there are breaking changes.

### Added

- **Custom & multiple Verb enums.** Define your own engagement Verbs by implementing the `EngagementType` contract; `engageify.types` now accepts a single enum or an array of enums.
- **Weighted Verbs and engagement values** via a new `value` column on `engagements`, for score-based engagement.
- **Exclusive groups** — vote-style Verbs where engaging with one option (e.g. `upvote`) automatically clears the others in its group.
- **Ratings** on a configurable scale via the `Rateable` contract, with Bayesian-average ranking (`engageify.bayesian_minimum`).
- **Hot / Top ranking** backed by a denormalised `engagement_counters` table, with a Reddit-style `hot()` score and time-windowed `top()` queries.
- **Actor-side queries** — the `EngagesWith` trait and `withUserEngagement()` scope query and eager-load a user's own engagements without N+1s.
- **View tracking** via the `HasViews` trait, plus opt-in time-windowed views backed by the `view_buckets` table.
- **Impression tracking** — a signed-token viewport impression endpoint, an `@impression` Blade directive, and an optional injectable browser tracker (`engageify.impressions.*`).
- **`engageify:recount` command** to (re)build the engagement counters from the `engagements` table.
- **Generic `Engaged` and `Disengaged` events** carrying the actor, engageable, and Verb (and, for `Engaged`, the engagement row).

### Changed

- **Counts and scores are now read from a denormalised `engagement_counters` table** instead of being counted from the `engagements` table on the fly. Existing v1 installs **must run `php artisan engageify:recount` once** (after publishing and running the new migrations) to backfill the counters — until then, counts, scores, and ranking queries read as `0` for pre-existing engagements. See [Upgrading from v1](README.md#upgrading-from-v1) and [`UPGRADING.md`](UPGRADING.md).
- **Raised the minimum requirements to PHP `^8.3` and Laravel `^12.0|^13.0`.**

### Removed

- **The `Engageify` facade and its `Engageify` alias** — engagement is driven through the `HasEngagements` trait on your models.
- **The five reaction-specific events** (`ModelLikedEvent`, `ModelDislikedEvent`, `ModelUpvotedEvent`, `ModelDownvotedEvent`, `ModelDisengagedEvent`) — replaced by the generic `Engaged` / `Disengaged` events.
- **The in-memory cache layer.** The `allow_caching` and `cache_duration` config keys no longer exist; counts are kept fresh inside the same transaction as each engage/disengage, so there is nothing to invalidate.

## v0.0.1 - 2023-10-08

### What's Changed

- build(deps): Bump dependabot/fetch-metadata from 1.3.6 to 1.6.0 by @dependabot in https://github.com/cjmellor/engageify/pull/1

### New Contributors

- @dependabot made their first contribution in https://github.com/cjmellor/engageify/pull/1

**Full Changelog**: https://github.com/cjmellor/engageify/commits/v0.0.1
