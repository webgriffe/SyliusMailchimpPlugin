<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Webmozart\Assert\Assert;

final class WebgriffeSyliusMailchimpExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    /** @psalm-suppress UndefinedClass,MixedAssignment,MixedArgument,InvalidArgument */
    #[\Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        Assert::string($config['api_key']);
        Assert::string($config['member_default_status']);
        Assert::string($config['webhook_secret']);
        Assert::boolean($config['send_unpaid_orders_as_carts']);
        Assert::isArray($config['mapper']);
        Assert::isArray($config['mapper']['product']);
        Assert::string($config['mapper']['product']['image_type']);
        Assert::string($config['mapper']['product']['image_filter']);

        $container->setParameter('webgriffe_sylius_mailchimp.api_key', $config['api_key']);
        $container->setParameter('webgriffe_sylius_mailchimp.member_default_status', $config['member_default_status']);
        $container->setParameter('webgriffe_sylius_mailchimp.webhook_secret', $config['webhook_secret']);
        $container->setParameter('webgriffe_sylius_mailchimp.send_unpaid_orders_as_carts', $config['send_unpaid_orders_as_carts']);
        $container->setParameter('webgriffe_sylius_mailchimp.command_lock_enable', $config['command_lock_enable']);
        $container->setParameter('webgriffe_sylius_mailchimp.mapper.product.image_type', $config['mapper']['product']['image_type']);
        $container->setParameter('webgriffe_sylius_mailchimp.mapper.product.image_filter', $config['mapper']['product']['image_filter']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.php');
    }

    #[\Override]
    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrineMigrations($container);
        $this->prependMonologChannel($container);
        $this->prependGrid($container);
        $this->prependTwig($container);
    }

    #[\Override]
    protected function getMigrationsNamespace(): string
    {
        return 'DoctrineMigrations';
    }

    #[\Override]
    protected function getMigrationsDirectory(): string
    {
        return '@WebgriffeSyliusMailchimpPlugin/src/Migrations';
    }

    #[\Override]
    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }

    private function prependMonologChannel(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('monolog')) {
            return;
        }

        $container->prependExtensionConfig('monolog', [
            'channels' => ['mailchimp'],
        ]);
    }

    private function prependGrid(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('sylius_grid')) {
            return;
        }

        $container->prependExtensionConfig('sylius_grid', [
            'grids' => [
                'webgriffe_sylius_mailchimp_contact' => [
                    'extends' => 'sylius_admin_customer',
                    'fields' => [
                        'mailchimpSyncedAt' => [
                            'type' => 'twig',
                            'label' => 'webgriffe_sylius_mailchimp.ui.synced_at',
                            'path' => 'mailchimpSyncedAt',
                            'sortable' => null,
                            'options' => [
                                'template' => '@SyliusAdmin/shared/grid/field/date.html.twig',
                            ],
                        ],
                        'mailchimpError' => [
                            'type' => 'string',
                            'label' => 'webgriffe_sylius_mailchimp.ui.sync_error',
                            'path' => 'mailchimpError',
                        ],
                    ],
                    'actions' => [
                        'main' => [
                            'create' => ['type' => 'create', 'enabled' => false],
                        ],
                        'item' => [
                            'show_orders' => ['type' => 'show', 'enabled' => false],
                            'update' => ['type' => 'update', 'enabled' => false],
                            'show' => [
                                'type' => 'show',
                                'options' => [
                                    'link' => [
                                        'route' => 'webgriffe_sylius_mailchimp_contact_show',
                                        'parameters' => ['id' => 'resource.id'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function prependTwig(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('twig')) {
            return;
        }

        $container->prependExtensionConfig('twig', [
            'paths' => [
                \dirname(__DIR__, 2) . '/templates' => 'WebgriffeSyliusMailchimp',
            ],
        ]);
    }
}
