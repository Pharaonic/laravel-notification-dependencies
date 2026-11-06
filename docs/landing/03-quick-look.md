---
view: components.packages.quick-look
title: A quick look
subtitle: Send a notification that depends on an order and a comment, then delete the comment.
file: app/Http/Controllers/OrderCommentController.php
language: php
code: |
  use App\Notifications\OrderCommented;

  $order->customer->notify(
      (new OrderCommented)->dependsOn($order, $comment)
  );

  $comment->delete(); // the customer's notification is deleted too

  $order->dependentNotifications()->count(); // notifications still about this order
---
