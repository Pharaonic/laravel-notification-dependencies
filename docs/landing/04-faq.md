---
view: components.home.faq
badge: FAQ
title: "{package.name}"
highlight: Questions
subtitle: "Quick answers about installing and using {package.name}."
---

## What is {package.name}?

{card.description} It's a free, open-source {technology.name} package by Pharaonic.

## How do I install {package.name}?

Run `composer require {package.composer}` in your project's root directory, then `php artisan migrate`. Your application needs Laravel's `notifications` table first (`php artisan notifications:table`).

## What does {package.name} require?

The latest release requires {package.requiresText}.

## Does it delete mail or broadcast notifications?

No. Only rows stored by the `database` channel are linked and deleted, so a mail or broadcast that was sent stays sent. A notification whose dependency is already gone when it is sent is not sent on any channel.

## Does it work with queued notifications?

Yes. `dependsOn()` keeps only each model's type and key, so a queued notification carries no extra models, and a dependency deleted while the job waits cancels the notification on every channel.

## What happens on a soft delete?

By default, soft deleting a dependency deletes its notifications. Set `delete_on_soft_delete` to `false` to keep them until the model is force deleted.

## Do I have to add a trait to every model?

By default, yes: dependencies use `HasDependentNotifications`. Turn on `all_models` to accept any model, at the cost of one indexed lookup on every model deletion.

## Is {package.name} free to use?

Yes. {package.name} is open source under the {package.license} license, so you can use it in personal and commercial projects.

## Where can I find the {package.name} documentation?

Read the [{package.name} documentation]({package.docsUrl}) for setup, configuration, and usage examples.

## How do I report a bug or contribute to {package.name}?

Open an issue or a pull request on [GitHub]({package.githubUrl}), ask in [GitHub Discussions](https://github.com/orgs/Pharaonic/discussions), or chat with us on [Discord](https://discord.gg/XQG9RhvEvf).
