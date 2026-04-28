<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Menu;

use Knp\Menu\ItemInterface;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListener
{
    public function addMailchimpMenuItems(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();
        $marketing = $menu->getChild('marketing');

        if (!$marketing instanceof ItemInterface) {
            return;
        }

        $marketing
            ->addChild('mailchimp_contacts', ['route' => 'webgriffe_sylius_mailchimp_contact_index'])
            ->setLabel('webgriffe_sylius_mailchimp.menu.admin.mailchimp_contacts')
            ->setLabelAttribute('icon', 'tabler:mail')
        ;
    }
}
