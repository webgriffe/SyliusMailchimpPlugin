<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

final class NewsletterSubscribeType extends AbstractType
{
    /** @param array<string, mixed> $options */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'webgriffe_sylius_mailchimp.form.newsletter.email',
            'constraints' => [
                new NotBlank(),
                new Email(),
            ],
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
