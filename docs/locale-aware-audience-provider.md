# Locale-Aware Audience Provider

By default, the plugin uses `ChannelAudienceProvider`, which returns a single Mailchimp audience per
channel (configured via `mailchimpAudienceId` on the channel entity). This is sufficient for most
single-language shops.

For shops with multiple locales per channel that need to sync subscribers to **different Mailchimp
audiences based on the customer's locale**, you can decorate or replace the `AudienceProviderInterface`.

## How it works

`AudienceProviderInterface::getAudience(ChannelInterface $channel, ?string $localeCode = null): Audience`

The `$localeCode` parameter is passed by all sync handlers and enqueuer classes. The default
implementation ignores it. A custom implementation can use it to return a different `Audience` per locale.

## Example implementation

```php
<?php

declare(strict_types=1);

use Sylius\Component\Core\Model\ChannelInterface;
use Webgriffe\SyliusMailchimpPlugin\Exception\AudienceNotFoundException;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;

final class LocaleAwareAudienceProvider implements AudienceProviderInterface
{
    /**
     * @param array<string, string> $localeToAudienceMap e.g. ['it_IT' => 'abc123', 'en_US' => 'def456']
     */
    public function __construct(
        private readonly AudienceProviderInterface $inner,
        private readonly array $localeToAudienceMap,
    ) {
    }

    public function getAudience(ChannelInterface $channel, ?string $localeCode = null): Audience
    {
        if ($localeCode !== null && isset($this->localeToAudienceMap[$localeCode])) {
            return new Audience($this->localeToAudienceMap[$localeCode], $channel);
        }

        // fall back to channel-level audience
        return $this->inner->getAudience($channel, $localeCode);
    }
}
```

Register the decorator in your `config/services.php`:

```php
$services->set(LocaleAwareAudienceProvider::class)
    ->decorate(AudienceProviderInterface::class)
    ->arg('$inner', service('.inner'))
    ->arg('$localeToAudienceMap', [
        'it_IT' => 'your-italian-audience-id',
        'en_US' => 'your-english-audience-id',
    ]);
```

## Important limitation: store creation

The `StoreEnqueuer` creates one Mailchimp store per channel without a locale context. If your
`AudienceProviderInterface` returns different audiences per locale, you will have multiple logical stores
(one per channel+locale combination), but the plugin will only create one store per channel.

In this scenario you must also customize store creation by implementing a custom `StoreEnqueuerInterface`
that iterates channel locales and dispatches a `StoreCreate` message for each (channel, locale) pair.
Your custom `StoreMapper` (implementing `StoreMapperInterface`) should then receive the `Audience` built
with the locale-specific audience ID, which will produce the correct store ID and `listId`.

## Store ID stability

A Mailchimp store ID is derived from `channelCode-audienceId` (sanitized). If you change the audience
ID returned for a given locale, the store ID will change and a new Mailchimp store will be created.
Ensure audience IDs are stable across deployments.
