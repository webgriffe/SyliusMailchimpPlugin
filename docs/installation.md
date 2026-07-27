# Installation

1. Run

    ```shell
    composer require webgriffe/sylius-mailchimp-plugin
    ```

2. Add `Webgriffe\SyliusMailchimpPlugin\WebgriffeSyliusMailchimpPlugin::class => ['all' => true]` to your `config/bundles.php`.

3. Import the plugin configuration and set your Mailchimp API key by creating `config/packages/webgriffe_sylius_mailchimp.yaml`:

    ```yaml
    imports:
        - { resource: "@WebgriffeSyliusMailchimpPlugin/config/config.yaml" }

    webgriffe_sylius_mailchimp:
        api_key: '%env(MAILCHIMP_API_KEY)%'
    ```

   and set the variable in your `.env.local` file:

    ```dotenv
    MAILCHIMP_API_KEY="your-mailchimp-api-key"
    ```

   See [Configuration reference](usage.md#configuration-reference) for every available option (webhook secrets, opt-in status, product image mapping, command locking, ...).

4. Import the plugin routes in `config/routes.yaml`:

    ```yaml
    webgriffe_sylius_mailchimp_shop:
        resource: "@WebgriffeSyliusMailchimpPlugin/config/routes/shop.yaml"

    webgriffe_sylius_mailchimp_admin:
        resource: "@WebgriffeSyliusMailchimpPlugin/config/routes/admin.yaml"
    ```

   The shop routes expose the newsletter subscribe endpoint and the abandoned-cart recovery link. The admin routes expose the Mailchimp webhook endpoint and the admin "Mailchimp contact" pages.

5. Extend your Sylius entities. The plugin stores its own sync state (Mailchimp id, last sync date, last error) directly on `Customer`, `Channel` and `Order` — there is no extra association entity to create.

   Your `Customer` entity must implement `Webgriffe\SyliusMailchimpPlugin\Model\MailchimpAwareInterface`, using `Webgriffe\SyliusMailchimpPlugin\Model\MailchimpAwareTrait`:

    ```php
    <?php

    declare(strict_types=1);

    namespace App\Entity\Customer;

    use Doctrine\ORM\Mapping as ORM;
    use Sylius\Component\Core\Model\Customer as BaseCustomer;
    use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpAwareInterface;
    use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpAwareTrait;

    #[ORM\Entity]
    #[ORM\Table(name: 'sylius_customer')]
    class Customer extends BaseCustomer implements MailchimpAwareInterface
    {
        use MailchimpAwareTrait;
    }
    ```

   Your `Channel` entity must implement `Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface`, using `Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareTrait` (this adds the `mailchimpAudienceId` field, editable from the admin channel form):

    ```php
    <?php

    declare(strict_types=1);

    namespace App\Entity\Channel;

    use Doctrine\ORM\Mapping as ORM;
    use Sylius\Component\Core\Model\Channel as BaseChannel;
    use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
    use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareTrait;

    #[ORM\Entity]
    #[ORM\Table(name: 'sylius_channel')]
    class Channel extends BaseChannel implements ChannelMailchimpAwareInterface
    {
        use ChannelMailchimpAwareTrait;
    }
    ```

   Your `Order` entity must implement `Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareInterface`, using `Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareTrait`:

    ```php
    <?php

    declare(strict_types=1);

    namespace App\Entity\Order;

    use Doctrine\ORM\Mapping as ORM;
    use Sylius\Component\Core\Model\Order as BaseOrder;
    use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareInterface;
    use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareTrait;

    #[ORM\Entity]
    #[ORM\Table(name: 'sylius_order')]
    class Order extends BaseOrder implements MailchimpOrderAwareInterface
    {
        use MailchimpOrderAwareTrait;
    }
    ```

6. Extend your repositories, so the bulk `sync-*` commands (and the admin dashboard) can query which resources still need syncing.

   Your `CustomerRepository` must implement `Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpCustomerRepositoryInterface`, using `Webgriffe\SyliusMailchimpPlugin\Doctrine\ORM\CustomerRepositoryTrait`. The same pattern applies to `ProductRepository` / `MailchimpProductRepositoryInterface` / `ProductRepositoryTrait`, and to `OrderRepository` / `MailchimpOrderRepositoryInterface` / `OrderRepositoryTrait`:

    ```php
    <?php

    declare(strict_types=1);

    namespace App\Repository;

    use Sylius\Bundle\CoreBundle\Doctrine\ORM\CustomerRepository as BaseCustomerRepository;
    use Webgriffe\SyliusMailchimpPlugin\Doctrine\ORM\CustomerRepositoryTrait;
    use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpCustomerRepositoryInterface;

    final class CustomerRepository extends BaseCustomerRepository implements MailchimpCustomerRepositoryInterface
    {
        use CustomerRepositoryTrait;
    }
    ```

7. Register the extended entities/repositories in your Sylius resource configuration, e.g. in `config/packages/_sylius.yaml`:

    ```yaml
    sylius_channel:
        resources:
            channel:
                classes:
                    model: App\Entity\Channel\Channel

    sylius_customer:
        resources:
            customer:
                classes:
                    model: App\Entity\Customer\Customer
                    repository: App\Repository\CustomerRepository

    sylius_product:
        resources:
            product:
                classes:
                    repository: App\Repository\ProductRepository

    sylius_order:
        resources:
            order:
                classes:
                    model: App\Entity\Order\Order
                    repository: App\Repository\OrderRepository
    ```

   Take a look at `tests/TestApplication/config/config.yaml` and `tests/TestApplication/src` for a complete, working example.

8. Generate and run the Doctrine migration for the new columns:

    ```shell
    bin/console cache:clear
    bin/console doctrine:migrations:diff
    bin/console doctrine:migrations:migrate
    ```

9. Set your Channel's Mailchimp Audience id from the Sylius admin (Channel edit form), then run an initial full sync (see [First sync](usage.md#first-sync-and-scheduled-commands)).

10. (Optional) Configure a Mailchimp webhook pointing to your admin webhook route (`/admin/mailchimp/webhook`) to keep subscription status changes made directly in Mailchimp (unsubscribe, cleaned, profile update, ...) in sync with Sylius. See [Webhook](usage.md#webhook).

11. (Optional) Route the plugin's Messenger messages to an async transport in production. The plugin ships no routing configuration of its own, so by default every sync happens synchronously in the same request that triggered it. See [Synchronous vs asynchronous processing](usage.md#synchronous-vs-asynchronous-processing) for the tradeoffs, especially for carts.
