<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Twig;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Twig\Extension\RuntimeExtensionInterface;
use Webgriffe\SyliusMailchimpPlugin\Form\Type\NewsletterSubscribeType;

final class NewsletterFormRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    public function createNewsletterFormView(): FormView
    {
        return $this->formFactory->create(NewsletterSubscribeType::class)->createView();
    }
}
