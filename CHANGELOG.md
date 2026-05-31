# Changelog

## Unreleased (v2)

### Breaking changes

- **Counts and scores are now read from a denormalised `engagement_counters` table** instead of being counted from the `engagements` table on the fly. Existing v1 installs **must run `php artisan engageify:recount` once** (after publishing and running the new migrations) to backfill the counters — until then, counts, scores, and ranking queries read as `0` for pre-existing engagements. See [Upgrading from v1](README.md#upgrading-from-v1) and [`UPGRADING.md`](UPGRADING.md).
- **The in-memory cache layer has been removed.** The `allow_caching` and `cache_duration` config keys no longer exist; counts are kept fresh inside the same transaction as each engage/disengage, so there is nothing to invalidate.

## v0.0.1 - 2023-10-08

### What's Changed

- build(deps): Bump dependabot/fetch-metadata from 1.3.6 to 1.6.0 by @dependabot in https://github.com/cjmellor/engageify/pull/1

### New Contributors

- @dependabot made their first contribution in https://github.com/cjmellor/engageify/pull/1

**Full Changelog**: https://github.com/cjmellor/engageify/commits/v0.0.1
