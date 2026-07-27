# Usage

> The plugin uses Symfony [Messenger][symfony_messenger] to decouple Sylius domain events from the actual
> Mailchimp API calls. A minimum knowledge of Messenger is useful to understand how synchronization is
> processed and how to configure it for production.

The plugin keeps five Mailchimp resources in sync with their Sylius counterpart:

| Mailchimp resource | Sylius counterpart |
|---|---|
| List Member | Customer |
| Store | Channel (+ Audience) |
| Product (and variants) | Product |
| Cart | Order in cart state |
| Order | Completed order |

Each resource follows the same flow: an **EventSubscriber** reacts to a Sylius event, an **Enqueuer** decides
whether the resource should be created, updated or removed on Mailchimp and dispatches a Messenger message,
and a **MessageHandler** performs the actual Mailchimp API call, persisting the returned Mailchimp id (and any
error) back on the Sylius entity. See [Architecture](architecture.md) for the full picture.

## List Member (newsletter contact)

The Mailchimp List Member is the equivalent of the Sylius Customer, kept in the Channel's configured Audience.

- Enqueued on `sylius.customer.post_register` and `sylius.customer.post_update`, and re-enqueued whenever one
  of the customer's addresses changes (`sylius.address.post_create/post_update/post_delete`).
- **Sync is gated by newsletter consent**: a Member is only created if the customer
  `isSubscribedToNewsletter()`; if the customer already has a `mailchimpId`, updates are still sent even if
  they later unsubscribe (so the "unsubscribed" status itself can be pushed to Mailchimp).
- New members are subscribed with the status configured via `member_default_status` (`subscribed` for single
  opt-in, `pending` for double opt-in — see [Configuration reference](#configuration-reference)).
- **Email change**: since a Mailchimp List Member id is derived from the email address, changing a customer's
  email triggers the removal of the old Member and the creation of a new one, plus an
  `EcommerceCustomerEmailChange` message (see below — the Ecommerce Customer id is immutable and must be
  recreated too).
- **Merge fields** are resolved through `MergeFieldsResolverInterface`, itself a pipeline of
  `MergeFieldsProviderInterface` implementations (name, contact info, address by default). Add your own
  provider and tag it to contribute custom merge fields without decorating the whole resolver.
- **Tags**: `TagsResolverInterface` is an extension point (the bundled `TagsResolver` returns `[]`). Implement
  it in your application to resolve a list of tag names to apply to each Member; the plugin takes care of
  resolving/creating the tag ids on Mailchimp.
- **Newsletter subscription form** (shop checkout + footer) calls `NewsletterSubscriber` **synchronously**,
  bypassing Messenger entirely, so the customer gets an accurate success/failure response instead of a
  "successfully enqueued" one. See [ADR 0002](adr/0002-newsletter-subscribe-is-synchronous-in-process.md).
  A Mailchimp "compliance state" rejection (a previously unsubscribed/complained address) is surfaced to the
  shopper as a 422 with a resubscribe URL, instead of a generic error.
- Bulk sync command: `webgriffe:sylius-mailchimp:sync-members`.

## Store

The Mailchimp e-commerce Store links a Sylius Channel to a Mailchimp Audience, and is the parent resource for
that channel's Products, Carts and Orders on Mailchimp.

- The Audience id is set per-channel from the admin Channel edit form (`mailchimpAudienceId` field).
- The Store id is **not** the bare channel code: it's composed as `sanitize("{channelCode}-{audienceId}")`, so
  that a channel using different audiences per locale (via a custom `AudienceProviderInterface`, see
  [Locale-aware Audience provider](locale-aware-audience-provider.md)) still gets one distinct Store per
  (channel, audience) pair. See [ADR 0004](adr/0004-mailchimp-store-id-composition.md) for the full reasoning.
- Bulk sync command: `webgriffe:sylius-mailchimp:sync-stores`.

## Product

- Enqueued on `sylius.product.post_create`, `sylius.product.post_update` and `sylius.product.pre_delete`, only
  for channels that have a Mailchimp Audience configured.
- The product image sent to Mailchimp is resolved via the `mapper.product.image_type` /
  `mapper.product.image_filter` configuration (defaults: `main` Sylius image type, `sylius_medium` Liip Imagine
  filter — set `image_filter: ~` to use the original image URL).
- Bulk sync command: `webgriffe:sylius-mailchimp:sync-products [--channel-code=] [--updated-last-days=N]`.

## Cart (abandoned cart)

- Enqueued on cart mutation (`SyliusCartEvents::CART_CHANGE`, `CART_ITEM_ADD`, `CART_ITEM_REMOVE`,
  `CART_CLEAR`), and on `sylius.order.post_update` while the order is still in `STATE_NEW`, if
  `send_unpaid_orders_as_carts` is enabled (default).
- Sylius only fires cart events **before** the database flush, so a synchronous handler can observe stale
  in-memory state. This has real consequences for how you should configure Messenger for this message type —
  read [ADR 0003](adr/0003-cart-synchronization-strategy.md) before going to production. In short: on an async
  transport, dispatch with a delay so the worker reads post-flush state; on a sync transport, rely on the
  scheduled `sync-carts` command running every 1–5 minutes as the real sync path.
- When a cart is converted into a completed order, the Order payload sets Mailchimp's `cart_id` to the Sylius
  order id, telling Mailchimp the tracked cart was purchased (instead of deleting it).
- Bulk sync command: `webgriffe:sylius-mailchimp:sync-carts [--updated-last-days=N]`.

## Order

- Enqueued on `sylius.order.post_complete`.
- Bulk sync command: `webgriffe:sylius-mailchimp:sync-orders [--updated-last-days=N] [--create-only]`
  (`--create-only` skips orders already synced, syncing only never-synced ones).

## Ecommerce Customer

Mailchimp's Ecommerce Customer (the Contact ↔ Store association carrying order history) has no dedicated
Sylius resource or command: it rides along with the Cart/Order payload, built by `EcommerceCustomerMapper`.
Its `opt_in_status` field reflects `isSubscribedToNewsletter()`, so Mailchimp is informed of the real marketing
consent even for customers who don't have a full List Member record. Because its Mailchimp id is immutable, an
email change triggers a dedicated recreation (`EcommerceCustomerEmailChange` message).

## Webhook

Configure a webhook in Mailchimp pointing to the admin route (`/admin/mailchimp/webhook`), optionally with the
`?secret=...` query string configured via `webhook_secret`, and set `webhook_signing_secret` to verify the
`X-Mailchimp-Signature` HMAC header. Incoming `subscribe` / `unsubscribe` / `cleaned` / `profile` / `upemail` /
`campaign` events are applied to the matching local Customer by `MemberSubscriptionStatusUpdater`.

> **Caveat**: an `unsubscribe`/`cleaned` webhook clears the local `mailchimpId` pointer so the Member won't be
> mistakenly re-subscribed on the next sync, but it does not flip the Customer's own
> `isSubscribedToNewsletter` flag back off. Review whether this matches your compliance requirements.

## First sync and scheduled commands

Right after installing the plugin (or when reconciling an already-populated Mailchimp account) run:

```shell
php bin/console webgriffe:sylius-mailchimp:sync-all
```

This runs, in order: members, stores, products, carts, orders — stopping at the first command that fails.
Each command also accepts being run on its own (see above for their specific options); this is what you should
schedule on a cron for ongoing reconciliation, especially for carts (see [ADR 0003](adr/0003-cart-synchronization-strategy.md)).

All commands (except `sync-all`) are wrapped with Symfony's `LockableTrait`, controlled by the
`command_lock_enable` configuration flag, to prevent overlapping runs from cron.

## Synchronous vs asynchronous processing

The plugin ships **no Messenger routing configuration**: by default, every message dispatched by an Enqueuer
is handled synchronously, in the same request/process that triggered it. Every Enqueuer wraps its dispatch in
a `try/catch`, so a Mailchimp failure never breaks the storefront request — but on a sync transport a
transient error (Mailchimp 5xx, timeout, rate limit) is only logged, not retried and not persisted on the
entity. Message handlers throw on transient errors and record permanent ones, so that hosts routing these
messages to an **async** transport get full Messenger retry/failure-transport semantics for free, with zero
extra configuration. See [ADR 0001](adr/0001-handle-mailchimp-errors-at-the-dispatch-boundary.md) for the full
design and [ADR 0003](adr/0003-cart-synchronization-strategy.md) for the cart-specific caveat around event
timing.

## Configuration reference

```yaml
webgriffe_sylius_mailchimp:
    api_key: ~                          # required — Mailchimp API key, e.g. "abc123...-us21"
    member_default_status: subscribed   # subscribed|pending — opt-in status for newly created List Members
    webhook_secret: ''                  # query-string secret required to call the webhook route
    webhook_signing_secret: ''          # HMAC secret to verify Mailchimp's X-Mailchimp-Signature header
    send_unpaid_orders_as_carts: true   # sync NEW/pending orders to Mailchimp as abandoned carts
    command_lock_enable: true           # guard sync-* commands with a lock to avoid overlapping cron runs
    mapper:
        product:
            image_type: main            # Sylius product image type used as the Mailchimp product image
            image_filter: sylius_medium  # Liip Imagine filter applied to the image (~ for the original URL)
```

## Extension points

- [Locale-aware Audience provider](locale-aware-audience-provider.md) — sync subscribers to different
  Mailchimp audiences based on the customer's locale.
- `MergeFieldsProviderInterface` — contribute additional Mailchimp merge fields for a List Member.
- `TagsResolverInterface` — resolve the list of tags to apply to a List Member (no-op by default).
- `AudienceProviderInterface` — resolve the Mailchimp Audience for a channel (+ locale).
- `StoreMapper` — change how the Mailchimp Store id/payload is built.

## Is this plugin compliant with privacy regulations?

The plugin does not check consent before syncing **transactional** data (Products, Carts, Orders): these are
sent regardless of the customer's newsletter subscription status, as is typical for data necessary to fulfill
a contract (order processing, abandoned cart recovery). **List Member sync, on the other hand, is gated by
`isSubscribedToNewsletter()`** — a contact is only created on Mailchimp's Audience if the customer has opted
in, and you control single vs double opt-in via `member_default_status`.

This still leaves choices up to you: which channels/customers to enable the plugin for, how consent is
collected in your storefront, and how to react to the webhook caveat described above.
**Webgriffe does not take any responsibility for incorrect use of this integration or for not respecting the
wishes of users of your e-commerce/website.**

[symfony_messenger]: https://symfony.com/doc/current/messenger.html
