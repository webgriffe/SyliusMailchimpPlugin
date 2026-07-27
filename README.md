<p align="center">
    <a href="https://sylius.com" target="_blank">
        <picture>
          <source media="(prefers-color-scheme: dark)" srcset="https://media.sylius.com/sylius-logo-800-dark.png">
          <source media="(prefers-color-scheme: light)" srcset="https://media.sylius.com/sylius-logo-800.png">
          <img alt="Sylius Logo." src="https://media.sylius.com/sylius-logo-800.png">
        </picture>
    </a>
</p>

<h1 align="center">Mailchimp Plugin</h1>
<p align="center">Sylius plugin to integrate Mailchimp: newsletter, abandoned carts, e-commerce sync</p>
<p align="center"><a href="https://github.com/webgriffe/SyliusMailchimpPlugin/actions/workflows/build.yml"><img src="https://github.com/webgriffe/SyliusMailchimpPlugin/actions/workflows/build.yml/badge.svg" alt="Build Status" /></a></p>

<hr />

## What does this plugin do?

The _SyliusMailchimpPlugin_ keeps a Sylius store's newsletter contacts, products, carts and orders in sync
with **Mailchimp**: it creates/updates Mailchimp List Members from Sylius Customers, sets up a Mailchimp
e-commerce Store per Channel, and syncs Products, abandoned Carts and completed Orders to it so you can run
Mailchimp's abandoned-cart and e-commerce automations. It also exposes a synchronous newsletter subscribe
endpoint and listens to Mailchimp webhooks to keep subscription status changes made on Mailchimp's side (e.g.
manual unsubscribe) reflected in Sylius.

## Who is this plugin for?

Sylius stores that want to run Mailchimp campaigns and automations (abandoned cart recovery, product
recommendations, newsletter) using their real, live store data instead of manual list exports/imports.

## How can I install the plugin on my Sylius store?

See [`docs/installation.md`](docs/installation.md).

## Is this plugin compliant with privacy?

Transactional data (products, carts, orders) is synced regardless of newsletter consent, as it's necessary to
run the store's e-commerce automations. Newsletter contact sync, on the other hand, **is gated by the
customer's own newsletter subscription flag** and you choose single vs. double opt-in via configuration.
**Webgriffe does not take any responsibility for incorrect use of this integration or for not respecting the
wishes of the users of your e-commerce/website** — see [`docs/usage.md`](docs/usage.md#is-this-plugin-compliant-with-privacy-regulations)
for the full picture.

## Where do I start?

Read the [documentation](docs/README.md): [Requirements](docs/requirements.md),
[Installation](docs/installation.md), [Usage](docs/usage.md) and [Architecture](docs/architecture.md).

## Local development

1. Install dependencies and set up the test application:

    ```shell
    composer install
    composer test-app-init
    ```

   Set `MAILCHIMP_API_KEY` (and any other variable referenced in `tests/TestApplication/.env.test`) before
   running the test application or the test suites.

2. Run your local server:

    ```shell
    symfony server:ca:install
    symfony server:start -d
    ```

### Running the test suite

```shell
composer ecs      # coding standard (add --fix to auto-fix)
composer phpstan
composer psalm
composer phpunit
composer phpspec
composer behat
composer suite     # all of the above
```

See [`AGENTS.md`](AGENTS.md) for conventions and the full command reference, and
[`docs/ai/testing-guide.md`](docs/ai/testing-guide.md) for the in-depth testing guide.

## Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md).
