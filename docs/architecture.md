# Architecture

## Overview

The plugin synchronizes Sylius store data (customers, orders, abandoned carts, products, channels) to
**Mailchimp**. The Mailchimp resources managed are:

| Mailchimp resource | Sylius resource |
|---|---|
| List Member | Customer |
| Store | Channel + Audience |
| Product (+ variants) | Product |
| Cart | Order (cart state) |
| Order | Order (completed) |
| Ecommerce Customer | Customer × Channel (embedded in Cart/Order payload) |
| Webhook | — (inbound only) |

## General flow

```
Sylius event
    │
    ▼
EventSubscriber          ← listens to Sylius resource/cart events
    │
    ▼
Enqueuer                 ← decides create/update/remove, resolves audience/store id,
    │                       wraps the dispatch in try/catch (see ADR 0001)
    ▼
Symfony Messenger        ← a lightweight message DTO
    │
    ▼
MessageHandler           ← calls the Mailchimp API, persists the returned id/error,
    │                       classifies failures as permanent vs transient (ADR 0001)
    ▼
Mapper                   ← builds the Mailchimp payload from the Sylius entity
    │
    ▼
MailchimpClientInterface ← thin wrapper around Mailchimp's REST API
    │
    ▼
Mailchimp API
```

## Layers

### EventSubscriber

**Namespace:** `Webgriffe\SyliusMailchimpPlugin\EventSubscriber`

Listens to Sylius domain events and cart events:

| Subscriber | Events |
|---|---|
| `CustomerSubscriber` | `sylius.customer.post_register`, `sylius.customer.pre_update` (captures the original email before flush), `sylius.customer.post_update` |
| `AddressSubscriber` | `sylius.address.post_create/post_update/post_delete` (re-enqueues the address owner) |
| `OrderSubscriber` | `sylius.order.post_update` (as cart, while `STATE_NEW`), `sylius.order.post_complete` |
| `ProductSubscriber` | `sylius.product.post_create/post_update/pre_delete` |
| `CartSubscriber` | `SyliusCartEvents::CART_CHANGE/CART_ITEM_ADD/CART_ITEM_REMOVE/CART_CLEAR` |

### Enqueuer

**Namespace:** `Webgriffe\SyliusMailchimpPlugin\Enqueuer`

One enqueuer per resource (`Member`, `Store`, `Product`, `Cart`, `Order`). Each enqueuer decides whether the
resource needs to be created or updated on Mailchimp (typically based on whether a `mailchimpId` is already
persisted locally), resolves the Audience/Store id, and dispatches the matching Messenger message. Every
`dispatch()` call is wrapped in `try/catch (\Throwable)` so that a failure here can never propagate into the
storefront request — see [ADR 0001](adr/0001-handle-mailchimp-errors-at-the-dispatch-boundary.md).

### Message / Message Bus

**Namespace:** `Webgriffe\SyliusMailchimpPlugin\Message`

Plain immutable DTOs dispatched through Symfony Messenger, grouped by resource (e.g. `MemberCreate`,
`MemberUpdate`, `CartCreate`, `CartUpdate`, `CartRemove`, `OrderCreate`, `OrderUpdate`,
`EcommerceCustomerEmailChange`, ...). The plugin ships no transport routing configuration: by default every
message is handled synchronously in the same request. See [Usage — synchronous vs asynchronous
processing](usage.md#synchronous-vs-asynchronous-processing).

### MessageHandler

**Namespace:** `Webgriffe\SyliusMailchimpPlugin\MessageHandler`

Each handler calls the corresponding `Mapper`, invokes `MailchimpClientInterface`, and persists the outcome
(`mailchimpId`/`mailchimpSyncedAt`/error field) back on the Sylius entity. Handlers classify failures via
`Util\MailchimpErrorClassifier`: permanent errors are logged and recorded on the entity (handler returns
normally); transient errors are logged and rethrown, so Messenger can retry on an async transport. Remove
handlers and the webhook-driven subscription updater always rethrow, since retrying is safe (idempotent) or
required by the caller (Mailchimp retries failed webhook deliveries on non-2xx responses).

### Mapper

**Namespace:** `Webgriffe\SyliusMailchimpPlugin\Mapper`

Pure transformation of Sylius entities into Mailchimp API payloads: `MemberMapper`, `StoreMapper`,
`ProductMapper`/`ProductVariantMapper`, `CartMapper`, `OrderMapper`, `EcommerceCustomerMapper`. Each mapper
implements an interface so applications can decorate or replace the mapping logic via the DI container.
`MemberMapper` dispatches a `MemberMappedEvent` after mapping, letting host applications add custom merge
fields without decorating the whole mapper.

### Client

**Namespace:** `Webgriffe\SyliusMailchimpPlugin\Client`

`MailchimpClientInterface`/`MailchimpClient` is a thin wrapper around Mailchimp's REST API (members, stores,
products, carts, orders, ecommerce customers, tags, ping, list audiences). `Client/Exception/` holds
`ClientException` (generic HTTP error, carries the status code), `NotFoundException` and
`ComplianceStateException` (a 400 response meaning the contact previously unsubscribed/complained and can't be
silently re-subscribed).

### Model & domain interfaces

**Namespace:** `Webgriffe\SyliusMailchimpPlugin\Model`

Interfaces/traits the host application applies to its own entities to store sync state:

| Interface/Trait | Applied to | Adds |
|---|---|---|
| `MailchimpAwareInterface` / `MailchimpAwareTrait` | Customer | `mailchimpId`, `mailchimpSyncedAt`, `mailchimpError` |
| `ChannelMailchimpAwareInterface` / `ChannelMailchimpAwareTrait` | Channel | `mailchimpAudienceId` |
| `MailchimpOrderAwareInterface` / `MailchimpOrderAwareTrait` | Order | `mailchimpOrderId`, `mailchimpCartId`, `mailchimpOrderError`, `mailchimpCartError` |

Unlike some other Webgriffe marketing-platform plugins, there is no separate Customer × Channel association
entity: everything the plugin needs lives directly on these three entities. See [Installation](installation.md).

### Repository

**Namespace:** `Webgriffe\SyliusMailchimpPlugin\Repository` (interfaces) and
`Webgriffe\SyliusMailchimpPlugin\Doctrine\ORM` (traits)

`MailchimpCustomerRepositoryInterface`, `MailchimpProductRepositoryInterface`,
`MailchimpOrderRepositoryInterface` add the finder/counter methods used by the `sync-*` commands and the admin
dashboard (e.g. "find customers needing a Mailchimp sync", "count synced/errored/never-synced"). The matching
`CustomerRepositoryTrait`, `ProductRepositoryTrait`, `OrderRepositoryTrait` implement the query logic to be
`use`d in the host application's own Doctrine repositories.

### Provider & Resolver

**Namespace:** `Webgriffe\SyliusMailchimpPlugin\Provider`, `Webgriffe\SyliusMailchimpPlugin\Resolver`

- `AudienceProviderInterface` (`ChannelAudienceProvider` by default) resolves a Mailchimp `Audience` value
  object for a given Channel (+ optional locale). This is the main extension point for
  multi-audience-per-channel setups — see [Locale-aware Audience provider](locale-aware-audience-provider.md).
- `AudienceContextInterface` resolves the *current* audience for the storefront newsletter form.
- `StoreIdentifierResolver` composes the Mailchimp Store id from channel code + audience id (see
  [ADR 0004](adr/0004-mailchimp-store-id-composition.md)).
- `MemberStatusResolver` maps a customer to a Mailchimp subscription status (subscribed/unsubscribed/pending).
- `MergeFieldsResolver` + `*MergeFieldsProvider` (name, contact info, address) build the Member's merge
  fields as an extensible pipeline.
- `TagsResolverInterface` resolves the tags to add to a Member (no-op by default, meant to be implemented by
  the host application).

### Command

**Namespace:** `Webgriffe\SyliusMailchimpPlugin\Command`

`SyncAllCommand`, `SyncMembersCommand`, `SyncStoresCommand`, `SyncProductsCommand`, `SyncCartsCommand`,
`SyncOrdersCommand` — bulk/scheduled reconciliation, see [Usage — first sync and scheduled
commands](usage.md#first-sync-and-scheduled-commands).

### Controller

**Namespace:** `Webgriffe\SyliusMailchimpPlugin\Controller`

`NewsletterController` (synchronous shop subscribe endpoint, see
[ADR 0002](adr/0002-newsletter-subscribe-is-synchronous-in-process.md)), `CartRecoveryController` (magic-link
cart restore from an abandoned-cart email), `WebhookController` (inbound Mailchimp webhook),
`Controller/Admin/ContactController` (admin "Mailchimp contact" pages).

## Related design decisions

- [ADR 0001 — Handle Mailchimp errors at the dispatch boundary](adr/0001-handle-mailchimp-errors-at-the-dispatch-boundary.md)
- [ADR 0002 — Newsletter subscribe is synchronous in-process](adr/0002-newsletter-subscribe-is-synchronous-in-process.md)
- [ADR 0003 — Cart synchronization strategy](adr/0003-cart-synchronization-strategy.md)
- [ADR 0004 — Mailchimp store id composition](adr/0004-mailchimp-store-id-composition.md)
