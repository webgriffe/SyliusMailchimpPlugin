<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Form\Extension;

use Sylius\Bundle\ChannelBundle\Form\Type\ChannelType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class ChannelTypeExtension extends AbstractTypeExtension
{
    /** @param array<string, mixed> $options */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('mailchimpAudienceId', TextType::class, [
            'label' => 'webgriffe_sylius_mailchimp.form.channel.mailchimp_audience_id',
            'required' => false,
        ]);
    }

    /** @return iterable<class-string<ChannelType>> */
    #[\Override]
    public static function getExtendedTypes(): iterable
    {
        yield ChannelType::class;
    }
}
