<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Menu;

use Knp\Menu\ItemInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Webgriffe\SyliusMailchimpPlugin\Menu\AdminMenuListener;

final class AdminMenuListenerTest extends TestCase
{
    private AdminMenuListener $listener;

    protected function setUp(): void
    {
        $this->listener = new AdminMenuListener();
    }

    public function test_it_adds_mailchimp_contacts_under_marketing(): void
    {
        $contactsItem = $this->createMock(ItemInterface::class);
        $contactsItem->expects($this->once())->method('setLabel')
            ->with('webgriffe_sylius_mailchimp.menu.admin.mailchimp_contacts')
            ->willReturnSelf();
        $contactsItem->expects($this->once())->method('setLabelAttribute')
            ->with('icon', 'tabler:mail')
            ->willReturnSelf();

        $marketing = $this->createMock(ItemInterface::class);
        $marketing->expects($this->once())->method('addChild')
            ->with('mailchimp_contacts', ['route' => 'webgriffe_sylius_mailchimp_contact_index'])
            ->willReturn($contactsItem);

        $menu = $this->createMock(ItemInterface::class);
        $menu->method('getChild')->with('marketing')->willReturn($marketing);

        $event = $this->createMenuBuilderEvent($menu);

        $this->listener->addMailchimpMenuItems($event);
    }

    public function test_it_does_nothing_when_marketing_menu_item_is_missing(): void
    {
        $menu = $this->createMock(ItemInterface::class);
        $menu->method('getChild')->with('marketing')->willReturn(null);

        $event = $this->createMenuBuilderEvent($menu);

        // Should not throw and should not add any menu item
        $this->listener->addMailchimpMenuItems($event);
        $this->addToAssertionCount(1);
    }

    private function createMenuBuilderEvent(ItemInterface $menu): MenuBuilderEvent
    {
        /** @var MockObject&MenuBuilderEvent $event */
        $event = $this->createMock(MenuBuilderEvent::class);
        $event->method('getMenu')->willReturn($menu);

        return $event;
    }
}
