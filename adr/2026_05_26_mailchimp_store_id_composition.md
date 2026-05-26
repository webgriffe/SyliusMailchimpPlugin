# Mailchimp Store ID Composition

* Date: 2026-05-26

## Description

### Problem to solve

Define a stable, unique identifier for a Mailchimp e-commerce store that correctly models the relationship between a Sylius channel and a Mailchimp audience.

### Context

The Mailchimp e-commerce API requires each store to have a globally unique `id`. The naive approach is to use the Sylius channel code directly as the store ID. However, this breaks down in a common real-world scenario:

A single Sylius channel may serve customers in multiple locales, and it is standard practice on Mailchimp to maintain **separate audiences per locale** (e.g. one audience for Italian customers, another for English-speaking customers). The plugin supports this via the `MailchimpAudienceProviderInterface`, which can return a different audience ID depending on the channel, locale, or any other context.

If the store ID were based solely on the channel code, all locale-specific audiences of the same channel would share a single Mailchimp store, making it impossible to correctly associate orders and carts with the right audience.

### Decision and reasoning

The Mailchimp store ID is composed as:

```
sanitize("{channelCode}-{audienceId}")
```

Where:
- `channelCode` is the Sylius channel code (e.g. `FASHION_WEB`)
- `audienceId` is the Mailchimp audience ID associated with that channel (e.g. `abc123def`)
- `IdSanitizer::sanitize()` normalises the result to a safe ASCII string

This guarantees a distinct Mailchimp store for every `(channel, audience)` pair, which covers all configurations:

| Scenario | Channel | Audience | Store ID |
|---|---|---|---|
| Single channel, single audience | `WEB` | `aaa` | `WEB-aaa` |
| Single channel, audience per locale | `WEB` | `aaa` (IT), `bbb` (EN) | `WEB-aaa`, `WEB-bbb` |
| Multiple channels | `WEB`, `MOBILE` | `aaa` | `WEB-aaa`, `MOBILE-aaa` |

The same composition is applied wherever a store ID must be computed outside of `StoreMapper` (e.g. `CartEnqueuer::enqueueRemoval()`), ensuring consistency across the entire plugin.

Integrators who need a different ID scheme can replace `StoreMapper` with their own implementation.
