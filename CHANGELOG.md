# Changelog

## v2.1.1 - 2026-07-27

A patch release fixing two correctness bugs in `HasEngagements`. No API or migration changes.

### Fixed

- **`Disengaged` is no longer dispatched when nothing was removed.** `disengage()` guarded the delete and the counter adjustment behind a non-empty check but fired the event unconditionally, so disengaging something that was never engaged emitted a phantom event. This brings it in line with `flipExclusive()`, which only ever emitted the event for rows it actually deleted. Listeners can now treat every `Disengaged` as a real deletion.
- **Closed a check-then-insert race in `engage()`.** With `allow_multiple_engagements = false` the duplicate guard ran outside the transaction, so concurrent requests could both pass it, both insert, and double-count the counter. The check now runs inside the transaction under `lockForUpdate()`, matching `rate()` and `flipExclusive()`. Note that gap-locking prevents the phantom insert on MySQL and SQLite serialises writes, but PostgreSQL does not gap-lock a `SELECT ... FOR UPDATE` matching zero rows.

## v2.1.0 - 2026-07-27

### Added

- **An explicit actor on every engagement path.** `engage()`, `disengage()`, and the Verb helpers now take an optional trailing `actor`, defaulting to the authenticated user. Applications whose authenticated principal is not the model that owns engagements could not use the trait at all before this. The resolved actor is what is written to `user_id`, what scopes the duplicate guard, the exclusive-group flip and the rating upsert, and what the `Engaged` / `Disengaged` events carry — previously the events reported `auth()->user()` even where the rows were keyed on `auth()->id()`.

### Changed

- **Refreshed `composer.lock` onto patched releases**, resolving the open Dependabot alerts. These are development-only transitive dependencies pulled in through Testbench — the package itself requires only `illuminate/database` and `illuminate/support` — so consumers were never exposed.

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
