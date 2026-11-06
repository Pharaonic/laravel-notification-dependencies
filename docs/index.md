---
name: Notification Dependencies

action:
  label: View on Packagist
  href: "{package.packagistUrl}"

views: components.packages

breadcrumbs:
  - label: Home
    href: route:home
  - label: Packages
    href: route:packages.index
  - label: "{technology.name} Packages"
    href: "url:/packages/{technology.slug}"
  - label: "{package.name}"

card:
  topic: database
  icon: paper-clip
  tags: notifications database-notifications eloquent dependencies cleanup delete soft-deletes queue
  description: Attach Laravel database notifications to Eloquent models and delete them automatically as soon as any of those models is deleted.

seo:
  title: "{package.fullName} - Auto-Delete Database Notifications"
  description: "{package.name} is a Laravel package that attaches database notifications to Eloquent models and deletes them when any of those models is deleted. {package.downloadsShort}+ downloads, {package.license} licensed."
  keywords: laravel notifications, database notifications, delete notifications, eloquent, notification cleanup, laravel package
  author: Pharaonic
  images:
    - "{package.cover}"
  openGraph:
    type: website
    siteName: Pharaonic
  twitter:
    card: summary_large_image

schema:
  "@type": SoftwareSourceCode
  name: "{package.name}"
  description: "{package.name} is a Laravel package that attaches database notifications to Eloquent models and deletes them when any of those models is deleted."
  image: "{package.cover}"
  codeRepository: "{package.githubUrl}"
  programmingLanguage: PHP
  runtimePlatform: "{technology.name}"
  version: "{package.version}"
  datePublished: "{package.publishedAt}"
  dateModified: "{package.updatedAt}"
  license: "https://opensource.org/licenses/{package.license}"
  isAccessibleForFree: true
  sameAs:
    - "{package.githubUrl}"
    - "{package.packagistUrl}"
  author:
    "@id": url:/#organization
  publisher:
    "@id": url:/#organization
  interactionStatistic:
    "@type": InteractionCounter
    interactionType: https://schema.org/DownloadAction
    userInteractionCount: "{package.downloads}"
---
