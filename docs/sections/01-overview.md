:::badges
- Laravel Package {color=blue}
- {release.label} {color=green}
- {package.license} License {color=purple}
:::

# Notification Dependencies

Notification Dependencies attaches Laravel database notifications to the Eloquent models they are about, and deletes them as soon as any one of those models is deleted. A "new comment on your order" notification disappears when the order or the comment is gone, so your users never open a notification that points at nothing.

:::features
### Attach to Models {icon="paper-clip"}
Name the models a notification depends on with dependsOn(), at send time or inside the notification class.

### Automatic Cleanup {icon="bolt"}
Deleting any dependency deletes every stored notification that depends on it, for every recipient.

### Several Dependencies {icon="grid"}
One notification can depend on many models of different types, and is deleted when the first of them goes.

### Queue Safe {icon="clock"}
Only each model's type and key travel through the queue, and a dependency deleted while the job waits still deletes the notification.

### Soft Delete Aware {icon="switch"}
Choose whether a soft delete removes the notifications or only a force delete does.

### Queryable {icon="search"}
Every dependable model gets a dependentNotifications relation you can load, count, and filter.
:::

:::info Quick Tip
Only the database channel is affected. Mail, broadcast, and every other channel of the same notification are sent exactly as before.
:::
