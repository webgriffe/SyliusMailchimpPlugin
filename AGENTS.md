# Copilot Instructions for SyliusMailchimpPlugin

## What this is

A Sylius 2.x plugin that integrates Mailchimp into the Sylius e-commerce platform. Built as a standard Symfony bundle following Sylius plugin conventions.

- Plugin namespace: `Webgriffe\SyliusMailchimpPlugin`
- Test namespace: `Tests\Webgriffe\SyliusMailchimpPlugin`
- Requires PHP 8.2+, Sylius ^2.0, Symfony ^7.4

## Commands

### Linting & Static Analysis
```bash
vendor/bin/ecs check                                  # coding standard (fix: add --fix)
vendor/bin/phpstan analyse -c phpstan.neon -l max src/
vendor/bin/psalm
```

### Tests
```bash
vendor/bin/phpunit                                    # all PHPUnit tests
vendor/bin/phpunit --testsuite=unit                   # unit only
vendor/bin/phpunit --testsuite=integration
vendor/bin/phpunit --testsuite=non-unit               # functional + integration
vendor/bin/phpunit --filter TestClassName             # single test class
vendor/bin/phpunit tests/path/to/TestFile.php         # single test file
vendor/bin/phpspec run
vendor/bin/behat --strict --tags="~@javascript&&~@mink:chromedriver"   # non-JS Behat
vendor/bin/behat --strict --tags="@javascript,@mink:chromedriver"      # JS Behat
```

`vendor/bin/console` is a symlink created automatically by `bin/create_console_symlink.php` (runs post-install/update).

## Architecture

```
src/                          Plugin source (loaded via WebgriffeSyliusMailchimpExtension)
  DependencyInjection/        Bundle extension + configuration tree
  Migrations/                 Doctrine migrations (run after Sylius core migrations)

config/
  services.php                Auto-imports all files in config/services/*.php
  config.yaml                 Imports all config/twig_hooks/**/*.yaml
  services/                   Individual service definition files (PHP)
  twig_hooks/                 Twig hooks YAML configs

tests/
  TestApplication/            Full Symfony app used by PHPUnit and Behat
    config/bundles.php        Registers only this plugin bundle
    config/config.yaml        Imports @WebgriffeSyliusMailchimpPlugin/config/config.yaml
  Unit/                       PHPUnit unit tests
  Integration/                PHPUnit integration tests
  Functional/                 PHPUnit functional tests
  Behat/                      Behat contexts, pages, and feature files
```

The `WebgriffeSyliusMailchimpExtension` extends `AbstractResourceExtension` (Sylius pattern) and uses `PrependDoctrineMigrationsTrait` to ensure plugin migrations run after Sylius core migrations.

Migrations are namespaced as `DoctrineMigrations` in `src/Migrations/`.

## Key Conventions

### Language
All code must be in English — class names, method names, variable names, comments, log messages, and exception messages. Italian is only acceptable in user-facing content (Twig templates, translation files, AI prompts intended to produce Italian output).

### PHP
- All PHP files: `declare(strict_types=1);` — no exceptions.
- Classes default to `final` unless extension is explicitly needed.
- Do not align assignment operators with extra spaces — ECS enforces this and will revert any vertical alignment automatically.
- Only add inline comments when the implementation logic is genuinely complex and requires explanation. Keep code self-documenting through clear naming.

### Static Analysis & Style
- PHPStan at max level + Psalm at error level 1. `src/DependencyInjection/Configuration.php` is excluded from PHPStan (causes a crash).
- Coding standard: `sylius-labs/coding-standard` ECS ruleset applied to `src/`, `tests/Behat/`, `tests/Integration/`, and `ecs.php`.

### Tests
- Every PHP class should have associated tests:
  - **Unit tests** (`--testsuite=unit`) for isolated business logic.
  - **Integration tests** (`--testsuite=integration`) for classes that require a database, external dependencies, or have too many collaborators to test in isolation.
- Mirror the source namespace structure in the test directory (e.g., `src/Foo/Bar.php` → `tests/Unit/Foo/BarTest.php`).

#### Mocking rules
- **Mock only services** — i.e., collaborators of the class under test that are injected as dependencies (repositories, message bus, API clients, mapper interfaces, etc.).
- **Never mock entities or models** — always instantiate real objects and populate them via setters/adders. Use `ReflectionProperty` to set protected fields that have no public setter (e.g., auto-generated `$id`, `$quantity`).
- The only acceptable exception is when a test specifically needs an object that does **not** implement a certain interface (e.g., testing a guard that skips non-`MailchimpAwareInterface` customers) — in that case a targeted mock is justified.
- Concrete entity classes available for tests:
  - `Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer`
  - `Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order`
  - `Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel`
  - `Sylius\Component\Core\Model\{Address, OrderItem, Product, ProductVariant, ProductTranslation, ChannelPricing}`
  - `Sylius\Component\Order\Model\Adjustment`
  - `Sylius\Component\Locale\Model\Locale`

### Commands
Long-running Symfony commands use `LockableTrait` with an environment toggle:
```php
if ($this->commandLockEnable && !$this->lock()) {
    return Command::FAILURE;
}
```
The `$commandLockEnable` flag is controlled via the `app_command_lock_enable` parameter defined in `config/services.yaml`.

### Configuration & Services
- New services go in a dedicated PHP file under `config/services/`.
- New Twig hooks go in a dedicated PHP file under `config/twig_hooks/`.

### Git
- CI also runs `roave/backward-compatibility-check` — avoid breaking public API without a major version bump.
