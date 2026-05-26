<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Resolver;

use Webgriffe\SyliusMailchimpPlugin\Util\IdSanitizer;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;

final class StoreIdentifierResolver implements StoreIdentifierResolverInterface
{
    #[\Override]
    public function resolve(Audience $audience): string
    {
        return IdSanitizer::sanitize(sprintf('%s-%s', (string) $audience->channel->getCode(), $audience->id));
    }
}
