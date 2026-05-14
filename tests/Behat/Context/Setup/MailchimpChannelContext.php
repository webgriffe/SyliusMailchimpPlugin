<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;

final class MailchimpChannelContext implements Context
{
    public function __construct(
        private readonly SharedStorageInterface $sharedStorage,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @Given the channel is configured with Mailchimp audience :audienceId
     */
    public function theChannelIsConfiguredWithMailchimpAudience(string $audienceId): void
    {
        /** @var ChannelMailchimpAwareInterface $channel */
        $channel = $this->sharedStorage->get('channel');
        $channel->setMailchimpAudienceId($audienceId);

        $this->entityManager->flush();
    }
}
