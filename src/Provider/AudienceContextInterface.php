<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Provider;

interface AudienceContextInterface
{
    public function getAudienceId(): string;
}
