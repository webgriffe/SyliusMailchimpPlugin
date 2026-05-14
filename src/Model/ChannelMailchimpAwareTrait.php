<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Model;

use Doctrine\ORM\Mapping as ORM;

trait ChannelMailchimpAwareTrait
{
    #[ORM\Column(name: 'mailchimp_audience_id', type: 'string', length: 50, nullable: true)]
    private ?string $mailchimpAudienceId = null;

    public function getMailchimpAudienceId(): ?string
    {
        return $this->mailchimpAudienceId;
    }

    public function setMailchimpAudienceId(?string $mailchimpAudienceId): void
    {
        $this->mailchimpAudienceId = $mailchimpAudienceId;
    }
}
