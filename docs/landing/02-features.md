---
view: components.packages.features
variant: compact
badge: Key Features
title: Everything you need to keep notifications relevant
subtitle: Two traits and one call when you send, and stale notifications clean themselves up.
items:
  - icon: paper-clip
    title: Attach to Models
    text: Chain `dependsOn($order, $comment)` when you send, or declare `dependencies()` in the notification class.
  - icon: bolt
    title: Automatic Cleanup
    text: Deleting any dependency deletes every stored notification that depends on it, in set-based batches.
  - icon: grid
    title: Many Dependencies
    text: One notification can depend on several models of different types, with integer, UUID, or ULID keys.
  - icon: clock
    title: Queue Safe
    text: Only identities travel through the queue, and a dependency deleted while the job waits still wins.
  - icon: switch
    title: Soft Delete Aware
    text: Remove notifications on soft delete, or keep them until `forceDelete()` with one setting.
  - icon: search
    title: Queryable Relation
    text: Load, count, and filter `dependentNotifications` like any Eloquent relation.
---
