# ADR 0002 — The newsletter subscribe endpoint runs synchronously, in-process

## Status

Accepted (2026-07-24)

## Context

`NewsletterController::subscribeAction` is an AJAX endpoint: the shopper types an email
into the footer form, JS posts it, and the JSON response drives the inline success/error
message. Unlike every other sync path in this plugin (cart, order, product, member),
there is no background job here the shopper can check later — the response **is** the
only feedback they will ever get about whether they are actually subscribed on Mailchimp.

The endpoint originally dispatched a `NewsletterSubscribe` Messenger message and read the
outcome back via `HandlerFailedException` (per [ADR 0001](0001-handle-mailchimp-errors-at-the-dispatch-boundary.md)).
This happened to give correct feedback only because the plugin ships no Messenger routing
configuration, so the default transport is synchronous. A store owner who configures an
async transport for this plugin's messages — the normal production setup ADR 0001 itself
recommends — would silently break this: the controller would receive success as soon as
the message was **enqueued**, before the handler ever ran, and return
`{"success": true}` regardless of whether Mailchimp actually accepted the subscription.

This was found via real usage: an invalid-looking email (`user@example.con`, a typo)
still showed "You have been successfully subscribed to our newsletter!" because Mailchimp
does not reject on delivery/MX grounds at subscribe time — but the deeper problem is that
the message shown was never guaranteed to reflect the real outcome in the first place,
for any host that async-routes plugin messages.

Validating deliverability (MX records, catch-all detection, etc.) is out of scope for
this plugin — Mailchimp itself does not do this synchronously, and building it would
duplicate a whole class of email-verification services. What is in scope is making sure
the message we show is never a lie about whether Mailchimp actually has the subscriber.

## Decision

The newsletter subscribe flow does not use Messenger at all. `NewsletterController` calls
`Webgriffe\SyliusMailchimpPlugin\Updater\NewsletterSubscriber::subscribe()` directly — a
plain service, not a message handler — which calls the Mailchimp client in-process and
returns/throws based on the real API result. The controller catches:

- `ComplianceStateException` → 422 with the compliance message and resubscribe URL;
- any other `\Throwable` → 500 with a generic "temporarily unavailable" message.

Only on a normal return does the controller report `{"success": true}` — which now
always means "Mailchimp accepted this member", independent of whatever Messenger
transport configuration the host application uses for every other plugin message.

This intentionally diverges from every other sync path in the plugin (event subscriber →
enqueuer → dispatch → handler), which stays on Messenger because those flows are
fire-and-forget from the shopper's perspective (cart/order/member background sync) and
benefit from async retries. The newsletter subscribe endpoint is the one place with a
human waiting synchronously for a real yes/no answer, so it opts out of that pattern
entirely rather than trying to make Messenger behave synchronously through configuration
that a host could always override.

## Consequences

- The success/error message on the newsletter form is always accurate, regardless of how
  a host application configures Messenger transports.
- No Messenger retry/failure-transport semantics apply to this flow. A transient
  Mailchimp 5xx during subscribe is surfaced to the shopper as "temporarily unavailable"
  immediately, with no automatic retry — acceptable because the shopper is present and
  can just try again.
- `NewsletterSubscriber` still persists `mailchimpId` / `mailchimpSyncedAt` /
  `mailchimpError` on the matching `Customer` (when one exists), same as the other
  member-sync handlers, so the admin contact detail page reflects the outcome too.
- Email deliverability (typos, MX records, disposable domains) is explicitly not
  validated by this plugin. A syntactically valid but undeliverable address will be
  reported as a successful subscription, because that is what Mailchimp itself reports
  at subscribe time. Hosts that need stronger validation should add it in the
  `NewsletterSubscribeType` form (e.g. a custom constraint) — that is outside this ADR.
