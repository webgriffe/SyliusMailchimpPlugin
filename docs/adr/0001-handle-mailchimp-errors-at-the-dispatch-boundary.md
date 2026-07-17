# ADR 0001 — Handle Mailchimp errors at the dispatch boundary

## Status

Accepted (2026-07-17)

## Context

The plugin synchronizes Sylius data (carts, orders, products, stores, customers/members)
to Mailchimp through Symfony Messenger: event subscribers react to storefront events
(add-to-cart, checkout, profile update, ...), call an *enqueuer* which dispatches a
message, and a *message handler* performs the actual Mailchimp API call.

Two facts create tension:

1. **The plugin ships no Messenger routing configuration.** With a default Symfony setup
   every message is handled **synchronously, inside the same HTTP request** that
   dispatched it. Any exception thrown by a handler therefore propagates into the
   storefront request. This was observed in production: a Mailchimp 400 during a cart
   sync turned an add-to-cart action into a 500 error page. A Mailchimp sync is a
   non-critical marketing side effect and must never break the shopping flow.

2. **This is a public plugin.** Host applications will route these messages to an
   asynchronous transport and rely on Messenger's standard error machinery: automatic
   retries with backoff and the failure transport. That machinery only works if the
   handler **throws** on failure. A handler that catches everything reports "handled" to
   Messenger even when the sync failed, silently disabling retries and the failure queue
   for every async host, with no way to opt out.

A first implementation used catch-all blocks inside every handler ("never throw").
It solved problem 1 but caused problem 2, so it was rejected and reworked into the
decision below.

### Options considered

- **A. Catch everything in the handlers ("swallow always").** Simple, storefront-safe
  out of the box. Rejected: breaks retries/failure transport on async transports for all
  hosts, imposes a non-configurable policy, and is surprising behavior for a reusable
  plugin.
- **B. Configuration flag (`throw_on_handler_error`).** Swallow by default, rethrow when
  enabled. Rejected: pushes an infrastructure decision onto every integrator and keeps
  the wrong default semantics for async hosts that forget to enable it.
- **C. Catch at the dispatch boundary, classify errors in the handlers.** Chosen — see
  below. Idiomatic Messenger, correct in both sync and async setups, zero configuration.

## Decision

Error handling is split across two layers with distinct responsibilities.

### 1. Message handlers: throw transient errors, record permanent ones

Handlers classify every failure with `Webgriffe\SyliusMailchimpPlugin\Util\MailchimpErrorClassifier::isPermanent()`:

- **Permanent** — retrying can never succeed, so rethrowing is pointless:
  - `ClientException` with HTTP status 400–499, **except** 408 (request timeout) and
    429 (rate limit);
  - `NotFoundException`, `AudienceNotFoundException`, `MissingCustomerEmailException`,
    `\InvalidArgumentException` (missing configuration or invalid data).

  The handler logs the error and, where the entity has a dedicated field
  (`mailchimpCartError`, `mailchimpOrderError`, `mailchimpError`), persists the message
  so the existing `Sync*` commands and the admin can detect and re-drive the sync.
  The handler then returns normally.

- **Transient** — retrying makes sense (Mailchimp 5xx, 408, 429, network/transport
  errors, anything unclassified): the handler logs and **rethrows**. On an async
  transport Messenger retries the message and eventually routes it to the failure
  transport; on the sync transport the exception is contained by layer 2.

The `*RemoveHandler` classes keep their original log-and-rethrow behavior (removals are
idempotent, retrying is always safe). `MemberSubscriptionUpdateHandler` also still
throws: it is driven by the Mailchimp webhook, where a 500 response correctly makes
Mailchimp retry the delivery.

### 2. Enqueuers (dispatch boundary): never let a sync failure escape into the request

Every `MessageBusInterface::dispatch()` call in the `src/Enqueuer/*` classes (Cart,
Order, Product, Store, Member) is wrapped in `try/catch (\Throwable)` + error log on the
`mailchimp` channel. The wrap also covers the audience/store resolution done inline by
some enqueuers.

- **Sync transport (default):** a transient handler exception surfaces at the dispatch
  call, is caught here, and the storefront request completes normally.
- **Async transport:** `dispatch()` only enqueues and does not throw handler exceptions;
  the catch is inert and the worker gets native retry semantics.

`NewsletterController` is its own dispatch boundary with richer handling, because the
newsletter AJAX flow must give user feedback: it catches `HandlerFailedException`,
returns 422 with the compliance message (and resubscribe URL) for
`ComplianceStateException`, and a generic 500 JSON for anything else. The CLI `Sync*`
commands intentionally keep no boundary: visible failures are desirable there.

## Consequences

- A Mailchimp outage or API error can no longer break add-to-cart, checkout, profile
  update or registration, regardless of transport configuration.
- Hosts using an async transport get full Messenger retry + failure-transport semantics
  for transient errors, with no plugin configuration required.
- Permanent errors are recorded on the affected entity where a field exists and are
  visible in the logs; they do not consume pointless retries.
- On the sync transport, transient failures are logged but **not** persisted on the
  entity (the exception aborts the handler before any error-field flush survives);
  recovery relies on the logs and the `Sync*` commands. This is accepted.
- New sync paths must follow the same split: classify in the handler, wrap the dispatch
  in the enqueuer. Do not add catch-all blocks inside handlers.
- The classification lives in one place (`MailchimpErrorClassifier`); changing what
  counts as permanent (e.g. treating 429 differently) is a one-line policy change.
