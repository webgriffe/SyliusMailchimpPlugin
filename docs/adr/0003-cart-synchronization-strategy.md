# ADR 0003 — Cart synchronization strategy

## Status

Accepted (2026-05-26)

## Context

### Problem to solve

Keep Mailchimp carts in sync with Sylius carts to enable abandoned cart campaigns.

### Context

Sylius dispatches cart-related events (`SyliusCartEvents`) only **before** the database is flushed. There are no "after" equivalents. This means that when a handler reacts synchronously to an event (e.g. `CART_ITEM_REMOVE`), the entity's in-memory state may not yet reflect what will ultimately be persisted — for example, the removed item is still present in the Doctrine identity map at the time the handler runs.

Additionally, the `SyliusCartEvents::CART_ITEM_ADD` event carries an `AddToCartCommandInterface` subject (not the order directly), and new carts may not have a database ID at the time the event fires.

### Decision and reasoning

#### Event subscriber

`CartSubscriber` listens to `SyliusCartEvents` and dispatches Symfony Messenger messages (`CartCreate`, `CartUpdate`, `CartRemove`) via `CartEnqueuer`. It **skips carts with no ID** (brand-new carts not yet flushed) — these are covered by the scheduled command described below.

#### Async setup (recommended)

When the application uses an **async** Symfony Messenger transport, all cart messages are dispatched with a `DelayStamp(60_000)` (1-minute delay). By the time the worker consumes the message, Doctrine has already flushed the correct state to the database and the handler reads a consistent snapshot.

In this setup it is still advisable to run `webgriffe:sylius-mailchimp:sync-carts --updated-last-days=1` on a frequent cron schedule (e.g. every 5 minutes) as an additional safety net.

#### Sync setup

When the application uses a **synchronous** transport (`sync://`), the `DelayStamp` is ignored and messages are handled inline. Because Sylius events fire before the flush, the handler may observe stale in-memory state. The subscriber's dispatches are therefore best treated as no-ops in this configuration, and the correct approach is to run the scheduled command frequently:

```
webgriffe:sylius-mailchimp:sync-carts --updated-last-days=1
```

This command queries the database directly and always reads the persisted state, so it is immune to the event-timing problem. Running it every 1–5 minutes provides sufficient near-real-time synchronisation for most use cases.

#### Cart removal

`CartSubscriber` handles `CART_ITEM_REMOVE` (item removal) as a `CartUpdate` — the handler re-fetches the order and sends the updated line items to Mailchimp. The `CART_CLEAR` event triggers a `CartRemove` message, which deletes the cart from Mailchimp entirely. `enqueueRemoval` requires a `mailchimpCartId` to be present on the order, so the cart must have been synced at least once before it can be removed.

#### Order conversion

When a cart is converted to a completed order, the order sync handler sets the `cart_id` field on the Mailchimp order payload to the Sylius order ID. This tells Mailchimp that the previously tracked abandoned cart has been purchased, closing the loop without deleting the cart record explicitly.
