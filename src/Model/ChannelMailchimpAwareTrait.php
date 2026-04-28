<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Model;

use Doctrine\ORM\Mapping as ORM;

trait ChannelMailchimpAwareTrait
{
    #[ORM\Column(name: 'mailchimp_audience_id', type: 'string', length: 50, nullable: true)]
    private ?string $mailchimpAudienceId = null;

    /** @var string[] */
    #[ORM\Column(name: 'mailchimp_newsletter_positions', type: 'json', nullable: true)]
    private array $mailchimpNewsletterPositions = [];

    public function getMailchimpAudienceId(): ?string
    {
        return $this->mailchimpAudienceId;
    }

    public function setMailchimpAudienceId(?string $mailchimpAudienceId): void
    {
        $this->mailchimpAudienceId = $mailchimpAudienceId;
    }

    /** @return string[] */
    public function getMailchimpNewsletterPositions(): array
    {
        return $this->mailchimpNewsletterPositions;
    }

    /** @param string[] $mailchimpNewsletterPositions */
    public function setMailchimpNewsletterPositions(array $mailchimpNewsletterPositions): void
    {
        $this->mailchimpNewsletterPositions = $mailchimpNewsletterPositions;
    }
}
