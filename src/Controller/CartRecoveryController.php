<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Controller;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Core\Storage\CartStorageInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CartRecoveryController
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CartStorageInterface $cartStorage,
        private readonly ChannelContextInterface $channelContext,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function restoreAction(string $tokenValue): Response
    {
        $order = $this->orderRepository->findCartByTokenValue($tokenValue);
        if (!$order instanceof OrderInterface) {
            return new RedirectResponse($this->urlGenerator->generate('sylius_shop_homepage'));
        }

        $channel = $this->channelContext->getChannel();
        if (!$channel instanceof ChannelInterface) {
            return new RedirectResponse($this->urlGenerator->generate('sylius_shop_homepage'));
        }

        $this->cartStorage->setForChannel($channel, $order);

        return new RedirectResponse($this->urlGenerator->generate('sylius_shop_cart_summary'));
    }
}
