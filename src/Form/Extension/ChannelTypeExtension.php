<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Form\Extension;

use Sylius\Bundle\ChannelBundle\Form\Type\ChannelType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;

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

        $builder->add('mailchimpNewsletterPositions', ChoiceType::class, [
            'label' => 'webgriffe_sylius_mailchimp.form.channel.mailchimp_newsletter_positions',
            'required' => false,
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->buildNewsletterPositionChoices(),
        ]);
    }

    /** @return iterable<class-string<ChannelType>> */
    #[\Override]
    public static function getExtendedTypes(): iterable
    {
        yield ChannelType::class;
    }

    /** @return array<string, string> */
    private function buildNewsletterPositionChoices(): array
    {
        $choices = [];
        foreach (ChannelMailchimpAwareInterface::NEWSLETTER_POSITIONS as $position) {
            $label = 'webgriffe_sylius_mailchimp.form.channel.newsletter_position.' . $position;
            $choices[$label] = $position;
        }

        return $choices;
    }
}
