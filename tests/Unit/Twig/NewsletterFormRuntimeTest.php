<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Webgriffe\SyliusMailchimpPlugin\Form\Type\NewsletterSubscribeType;
use Webgriffe\SyliusMailchimpPlugin\Twig\NewsletterFormRuntime;

final class NewsletterFormRuntimeTest extends TestCase
{
    public function test_it_creates_the_newsletter_subscribe_form_view(): void
    {
        $view = new FormView();
        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn($view);
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->once())
            ->method('create')
            ->with(NewsletterSubscribeType::class)
            ->willReturn($form);

        $runtime = new NewsletterFormRuntime($formFactory);

        self::assertSame($view, $runtime->createNewsletterFormView());
    }
}
