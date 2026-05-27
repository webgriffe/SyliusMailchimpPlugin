# Guida alla struttura dei test

Questa guida descrive come sono organizzati e scritti i test nel progetto, con l'obiettivo di servire come riferimento per la creazione o il refactor di test in altri progetti **Symfony/Sylius** — sia store completi che **plugin Sylius**.

> **Nota sugli esempi**: tutti i nomi di file, cartelle, classi, servizi e namespace usati in questa guida sono estratti dal progetto di riferimento (uno store Sylius) e vanno intesi come **esempi illustrativi**, non come strutture da riprodurre letteralmente. In un plugin o in un altro progetto i nomi di dominio, le cartelle e i namespace cambieranno: ciò che rimane invariato sono i **pattern**, le **convenzioni** e le **regole di scelta** tra i diversi tipi di test.

> **Plugin Sylius**: le stesse regole si applicano. Le differenze principali rispetto a uno store sono:
> - Il namespace radice sarà quello del plugin (es. `Acme\MyPlugin\Tests\...`) invece di `App\Tests\...`
> - Il kernel di test è quello del plugin (es. `Tests\Application\Kernel`) con una app di test minimale in `tests/Application/`
> - Il service container usato negli Integration test è quello dell'applicazione di test del plugin
> - I Behat context usano il namespace del plugin, ma la struttura `Setup/`, `Ui/`, `Console/`, `Transform/` rimane identica

---

## Panoramica delle tre tipologie

| Tipologia | Cartella sorgente | Framework | DB | Kernel | Quando usarli |
|---|---|---|---|---|---|
| **Unit** | `tests/Unit/` | PHPUnit `TestCase` | ❌ | ❌ | Logica isolata, poche dipendenze |
| **Integration** | `tests/Integration/` | PHPUnit `KernelTestCase` | ✅ | ✅ | Servizi con DB, handler, command, email |
| **Feature / E2E** | `features/` + `tests/Behat/` | Behat + Mink | ✅ | ✅ | Flussi utente end-to-end via browser o CLI |

---

## 1. Test Unitari (`tests/Unit/`)

### Quando usarli

- Classe con **pochissime dipendenze** (0-2 esterne reali)
- Logica **puramente in-memory**: calcoli, trasformazioni, validazioni, normalizzatori
- Entità con logica di business (es. calcolo del balance, metodi custom)
- Nessun bisogno di persistenza o container Symfony

Se una classe ha **3+ dipendenze nel costruttore**, preferire il test di integrazione.

### Estensione base

```php
use PHPUnit\Framework\TestCase;

final class MyServiceTest extends TestCase
```

**Eccezione**: i validator Symfony estendono `ConstraintValidatorTestCase`:

```php
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class PostcodeValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): PostcodeConstraintValidator
    {
        return new PostcodeConstraintValidator(new NullLogger());
    }
}
```

### Struttura interna

La cartella `tests/Unit/` rispecchia la struttura di `src/`: ogni classe ha il suo `*Test.php` nello stesso path relativo.

```
tests/Unit/
├── <Concetto>/               ← es. Calculator/, Entity/, Serializer/, Parser/...
│   └── <SottoConCetto>/      ← es. Loyalty/, Addressing/...
│       └── MyClassTest.php
└── MyDataSetTrait.php        ← trait di supporto condiviso tra più test Unit
```

> **Esempio** (dal progetto di riferimento): `Calculator/Loyalty/`, `Entity/Loyalty/`, `Validator/Constraints/Addressing/`, ecc. I nomi riflettono il dominio specifico di quel progetto.

### Fixture: entità reali PHP, niente DB

Non si usano file YAML né Alice. I dati di test vengono costruiti **inline in PHP** usando le entità reali con i loro setter:

```php
// ✅ Si usa `new Entity()` con setter
$account = new LoyaltyPointsAccount();
$account->setAcceptedTermsAt(new \DateTimeImmutable());
$account->setLoyaltyTier(LoyaltyTier::GROUPIE);

$customer = new Customer();
$customer->setLoyaltyPointsAccount($account);
```

Quando la costruzione di dati di test è **riutilizzata da più classi di test**, si estrae in un **trait** condiviso. Il nome del trait è libero e segue il dominio del progetto:

```php
// tests/Unit/MyEntityDataSetTrait.php  ← nome esemplificativo
trait MyEntityDataSetTrait
{
    private function buildCompleteOrder(): OrderInterface { ... }
    private function buildCustomer(): Customer { ... }

    // Utility per settare ID privati senza DB (pattern fisso, indipendente dal dominio)
    public function setIdOnObject($object, int $id): void
    {
        $reflectionClass = new ReflectionClass($object::class);
        $idProperty = $reflectionClass->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($object, $id);
    }
}
```

### Mock: solo per dipendenze esterne reali

Si usano mock **solo** per dipendenze che non possono essere istanziate in-memory (repository, HTTP client, interfacce con I/O esterno):

```php
protected function setUp(): void
{
    // Mock solo del repository (dipendenza esterna)
    $this->loyaltyPurchaseRepository = $this->createMock(LoyaltyPurchaseRepositoryInterface::class);
    $this->purchaseToPromotionResolver = $this->createMock(PurchaseToPromotionResolverInterface::class);

    // Il service sotto test viene istanziato con i mock come dipendenze
    $this->provider = new CustomerBenefitsProvider(
        $this->loyaltyPurchaseRepository,
        $this->purchaseToPromotionResolver,
    );
}

public function test_it_categorizes_a_purchasable_benefit(): void
{
    [$customer, $channel] = $this->makeSubscribedCustomerAndChannel(LoyaltyTier::GROUPIE, 500);
    $purchase = $this->makePurchase(300, LoyaltyTier::GROUPIE);

    // Configurazione del mock nel singolo test
    $this->loyaltyPurchaseRepository->method('findAllEnabledAndInChannel')->willReturn([$purchase]);
    $this->purchaseToPromotionResolver->method('resolveActive')->willReturn(null);

    $result = $this->provider->categorize($customer, $channel);

    self::assertSame([$purchase], $result->purchasable);
}
```

**Regola**: le entità del dominio (`Customer`, `Order`, `LoyaltyPointsAccount`, ecc.) si istanziano **sempre** con `new`, non si mockano mai.

### Naming dei test

```php
// Forma snake_case (preferita)
public function test_it_calculates_points_for_order_with_only_full_price_products(): void

// Oppure con annotazione @test
/**
 * @test
 */
public function it_does_normalize_full_order(): void
```

### Data provider

```php
public function provideTransactions(): array
{
    return [
        [ 3000, [ $this->getTransaction(500, Types::INCOME), ... ] ],
        [ 2500, [ $this->getTransaction(500, Types::INCOME, 500), ... ] ],
    ];
}

/** @dataProvider provideTransactions */
public function test_it_calculates_balance_properly(int $balance, array $transactions): void { ... }
```

---

## 2. Test di Integrazione (`tests/Integration/`)

### Quando usarli

- La classe **interagisce col database** (repository, entity manager)
- Il service è **registrato nel container** Symfony
- Si tratta di **command handler**, **command console**, **event listener**, **exporter**, **email manager**
- La classe ha 3+ dipendenze o orchestra più servizi
- Si vuole verificare side-effect reali: stato del DB, email inviate, output su file

### Estensione base

```php
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class BuyLoyaltyPurchaseHandlerTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        // Sempre: il service sotto test si prende dal container
        $this->service = self::getContainer()->get('app.command_handler.loyalty.buy_loyalty_purchase');

        // Sempre: il loader Alice si prende dal container
        $this->fixtureLoader = self::getContainer()->get('fidry_alice_data_fixtures.doctrine.loader');
    }
}
```

### Struttura interna

La cartella `tests/Integration/` rispecchia la struttura di `src/`. I nomi delle sottocartelle seguono il dominio del progetto.

```
tests/Integration/
├── <TipoClasse>/             ← es. Command/, Repository/, EventListener/...
│   └── <SottoConCetto>/      ← es. Loyalty/, Addressing/...
│       └── MyClassTest.php
└── <Dominio>/
    ├── MyDomainTestTrait.php  ← trait di supporto condiviso tra test dello stesso dominio
    └── MyDomainTest.php
```

> **Esempio** (dal progetto di riferimento): `Command/Loyalty/`, `CommandHandler/Loyalty/`, `Repository/`, `EventListener/Loyalty/`, `ErpIntegration/ProductImporter/`, ecc.

La struttura **rispecchia `src/`**.

### Fixture Alice: YAML per classe e per test

Le fixture per i test di integrazione sono file YAML letti da AliceDataFixtures (via `fidry_alice_data_fixtures`). Ogni classe di test ha la **propria sottocartella**, che rispecchia il path della classe test stessa.

La struttura generale è:

```
tests/DataFixtures/ORM/resources/
├── <TipoClasse>/
│   └── <MyClassTest>/          ← una cartella per ogni classe di test
│       ├── shared_data.yaml    ← dati condivisi tra più test della classe (es. canali, locali)
│       └── specific_case.yaml  ← dati specifici per uno o più metodi di test
└── shared_base_data.yaml       ← fixture condivise globalmente tra più classi di test
```

> **Esempio** (dal progetto di riferimento): `Repository/OrderRepositoryTest/orders_to_export.yaml`, `EventListener/Loyalty/TierTransitionListenerTest/customers.yaml`, ecc. Tutti nomi specifici di quel dominio.

**Convenzioni fixture YAML**:

```yaml
# Struttura base: FullyQualifiedEntityName → alias → proprietà
App\Entity\Order\Order:                  # nome FQCN dell'entità (varia per progetto)
    myOrderAlias:
        number: '000000001'
        state: new
        createdAt: '<(new DateTime())>'   # espressioni PHP inline supportate da Alice
    anotherOrderAlias:
        number: '000000002'
        createdAt: '<(new DateTime("-3 month"))>'
```

**Pattern di caricamento** (il path `FIXTURE_BASE_DIR` varia per ogni classe, ma la convenzione è sempre una costante):

```php
// Il path è relativo alla classe test — si adatta al progetto
private const FIXTURE_BASE_DIR = __DIR__ . '/../../DataFixtures/ORM/resources/Repository/MyClassTest';

public function test_it_finds_items_ready_for_export(): void
{
    $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/items_to_export.yaml']);

    $items = $this->myRepository->findReadyForExport();

    self::assertCount(2, $items);
}
```

Alcuni test caricano **più file** (dati di base + dati specifici per il test):

```php
$this->fixtureLoader->load([
    self::FIXTURE_BASE_DIR . '/account_with_transactions.yaml',
    self::FIXTURE_BASE_DIR . '/channels_and_locales.yaml',
]);
```

Per **evitare dipendenze tra test** (dato che il DB è condiviso), alcuni setUp() chiamano il loader con lista vuota per purgare:

```php
protected function setUp(): void
{
    // ...
    $this->fixtureLoader->load([]); // purga il DB senza caricare dati
}
```

Alcuni test usano **fixture con nome pari al metodo di test** per comodità:

```php
public function test_it_applies_exclusive_promotion(): void
{
    $this->fixtureLoader->load([
        self::FIXTURE_BASE_DIR . '/order.yaml',
        self::FIXTURE_BASE_DIR . '/channel_and_locale.yaml',
        self::FIXTURE_BASE_DIR . '/' . $this->getName() . '.yaml', // es. test_it_applies_exclusive_promotion.yaml
    ]);
```

> **Regola**: Il setup dello stato del DB va **sempre** fatto tramite fixture YAML (Alice). NON creare mai entità a DB manualmente tramite PHP/EntityManager nei test. L'unica eccezione è nei casi davvero particolari dove la fixture è impossibile da costruire (es. dipendenze complesse con servizi non supportati da Alice).

### Assertion sugli effetti reali

Dopo aver eseguito il service/command, si verifica lo stato reale del DB tramite repository:

```php
// Verifica stato DB
$this->entityManager->clear(); // svuota l'identity map per leggere dati freschi
$promotions = $this->promotionRepository->findAll();
self::assertCount(4, $promotions);
self::assertSame('2025-02-20 00:00:00', $promotions[3]->getEndsAt()->format('Y-m-d H:i:s'));

// Verifica email inviate
$this->emailChecker = self::getContainer()->get('sylius.behat.email_checker');
self::assertSame(1, $this->emailChecker->countMessagesTo('oliver.queen@star-city.com'));
```

### Test di Command (console)

```php
protected function setUp(): void
{
    parent::setUp();
    self::bootKernel();

    $application = new Application(static::$kernel);
    $this->command = $application->find('app:cancel-unpaid-orders');
    $this->commandTester = new CommandTester($this->command);
    $this->fixtureLoader = self::getContainer()->get('fidry_alice_data_fixtures.doctrine.loader');
}

public function test_it_cancel_unpaid_orders(): void
{
    $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/orders.yaml']);

    $this->commandTester->execute([]);

    $order = $this->orderRepository->findOneByNumber('000000003');
    self::assertEquals(OrderInterface::STATE_CANCELLED, $order->getState());
}
```

### Test con HTTP fittizio: Stub e VCR

Per servizi che fanno chiamate HTTP, esistono due pattern:

**1. Stubs personalizzati** (`tests/Stub/`): implementazioni in-memory di client HTTP o servizi esterni complessi. La cartella e i nomi riflettono i servizi esterni del progetto specifico:

```
tests/Stub/
└── <ExternalService>/          ← es. ErpIntegration/, PaymentGateway/, CmsClient/...
    ├── HttpClient.php           ← sostituisce il client HTTP reale
    └── Server.php               ← server HTTP in-memory (se necessario)
```

> **Esempio** (dal progetto di riferimento): `ErpIntegration/InMemoryErp/`, `PayPal/Api/`, `Contentful/ClientStub.php`. I nomi dipendono dai servizi esterni del progetto.

**2. VCR (Video Cassette Recorder)**: fixture JSON di richieste/risposte HTTP pre-registrate:

```php
protected function setUp(): void
{
    VCR::turnOn();
    VCR::insertCassette('Command/ItalianCitiesImportCommandTest/' . $this->getName() . '.json');
}
```

Le cassette sono in `tests/DataFixtures/VCR/`.

### Gestione del tempo

Per test sensibili alla data/ora si usa Carbon:

```php
protected function setUp(): void
{
    Carbon::setTestNow('2024-11-22 06:00:00');
    // ...
}
```

### Trait di supporto condivisi

Quando setup logic è comune a più test di integrazione:

```php
// tests/Integration/ErpIntegration/ErpIntegrationTestTrait.php
trait ErpIntegrationTestTrait
{
    private function generateColor(string $id, ?string $itDescr = null): Color { ... }
    private function generatePrice(string $type, string $value): Price { ... }
}
```

---

## 3. Feature / E2E Test (`features/` + `tests/Behat/`)

### Quando usarli

- Flussi utente completi: registrazione, checkout, loyalty, resi
- Verifica dell'integrazione di più layer: UI, controller, servizi, DB
- Test che richiedono un **browser** (Mink/Chrome) o CLI end-to-end

### Struttura delle feature

Le feature sono organizzate per dominio funzionale. I nomi delle cartelle rispecchiano le funzionalità del progetto specifico.

```
features/
├── <dominio>/                  ← es. account/, cart/, checkout/, loyalty/...
│   ├── scenario_name.feature
│   └── <sotto-dominio>/
│       └── another_scenario.feature
```

> **Esempio** (dal progetto di riferimento): `loyalty/`, `checkout/addressing_order/`, `account/`, ecc. In un plugin Sylius le feature saranno organizzate attorno alle funzionalità del plugin stesso.

Ogni feature è un file Gherkin con tag che determinano la suite di esecuzione:

```gherkin
@app_loyalty
Feature: Earning loyalty points from an order

    Background:
        Given the store ships to "Italy" ...
        And the store has a product "Buick Regal" priced at "€500.00"
        And I am a logged in customer

    @ui
    Scenario: Earning points with full price products
        Given I bought and paid for 3 "Buick Regal" products
        And this order has already been shipped 31 days ago with shipping method "DHL"
        When Points are assigned by periodical procedure
        Then I should see 1600 loyalty points
```

**Tag principali**:
- `@ui` — test con browser
- `@cli` / `@console` — test che usano solo comandi console (senza browser)

### Struttura dei context Behat

La struttura interna di `tests/Behat/` segue pattern fissi indipendenti dal progetto. I **nomi** dei singoli file riflettono il dominio specifico.

```
tests/Behat/
├── Context/
│   ├── Setup/                      ← @Given: costruisce lo stato del DB prima degli scenari
│   │   └── MyDomainContext.php      ← es. LoyaltyContext.php, OrderContext.php, ProductContext.php
│   ├── Ui/
│   │   ├── Shop/                   ← @When/@Then che interagiscono col browser (frontend)
│   │   │   ├── Account/
│   │   │   │   └── MyFeatureContext.php
│   │   │   └── MyPageContext.php
│   │   └── Admin/                  ← @When/@Then per il pannello admin
│   │       └── MyAdminContext.php
│   ├── Console/                    ← @When/@Then che eseguono comandi CLI (no browser)
│   │   └── MyCommandContext.php
│   └── Transform/                  ← trasformatori di argomenti Gherkin → oggetti PHP
│       └── MyEntityTransformContext.php
├── Page/                           ← Page Object: una classe per ogni pagina dell'applicazione
│   ├── Shop/
│   │   ├── <Area>/
│   │   │   ├── MyPage.php
│   │   │   └── MyPageInterface.php  ← sempre interfaccia + implementazione
│   │   └── ...
│   └── Admin/
│       └── ...
├── Resources/
│   ├── config/
│   │   ├── suites/                 ← una YAML per ogni gruppo di feature
│   │   │   └── ui/<dominio>/my_feature.yaml
│   │   └── services/               ← dichiarazione context come servizi pubblici
│   │       ├── contexts/
│   │       │   ├── setup.yml
│   │       │   └── ui.yml
│   │       └── pages/
│   │           ├── shop.yml
│   │           └── admin.yml
│   └── suites.yaml                 ← importa tutte le suite
└── Service/                        ← factory/helper specifici per i test Behat
    └── MyTestSupportService.php
```

> **Esempio** (dal progetto di riferimento): i context si chiamano `LoyaltyContext`, `OrderContext`, `ReturnRequestContext` ecc. In un plugin i nomi seguiranno le funzionalità del plugin.

> **Plugin Sylius**: per i plugin il namespace sarà `Acme\MyPlugin\Tests\Behat\...` invece di `App\Tests\Behat\...`, ma la struttura delle cartelle rimane identica.

### Pattern Suite YAML

Ogni gruppo di feature ha un file di configurazione che dichiara esplicitamente i context attivi. I nomi delle suite, i tag e i context sono specifici del progetto, ma la struttura del file è sempre la stessa:

```yaml
# tests/Behat/Resources/config/suites/ui/<dominio>/my_feature.yaml
# I nomi (ui_app_loyalty, @app_loyalty, context classes) variano per progetto/plugin
default:
    suites:
        ui_my_feature:                       ← nome suite: libero, convenzionalmente ui_<tag>
            contexts:
                # hook DB (sempre presente)
                - sylius.behat.context.hook.doctrine_orm
                # trasformatori argomenti (quelli necessari allo scenario)
                - sylius.behat.context.transform.product
                - sylius.behat.context.transform.customer
                # setup context (@Given) — Sylius built-in + quelli del progetto/plugin
                - sylius.behat.context.setup.channel
                - sylius.behat.context.setup.product
                - App\Tests\Behat\Context\Setup\MyDomainContext
                # UI context (@When/@Then) — Sylius built-in + quelli del progetto/plugin
                - sylius.behat.context.ui.shop.checkout.complete
                - App\Tests\Behat\Context\Ui\Shop\MyFeatureContext
                # Console context — solo se lo scenario usa comandi CLI
                - App\Tests\Behat\Context\Console\MyCommandContext
            filters:
                tags: "@my_feature_tag&&@ui"   ← combina il tag del dominio con @ui o @cli
```

> **Esempio** (dal progetto di riferimento): la suite `ui_app_loyalty` usa il tag `@app_loyalty&&@ui` e dichiara i context `LoyaltyContext`, `OrderContext`, ecc.

### Page Object Pattern

Ogni pagina ha interfaccia + implementazione:

```php
// IndexPageInterface.php
interface IndexPageInterface extends PageInterface
{
    public function getLoyaltyPoints(): int;
    public function isOpen(): bool;
}

// IndexPage.php
class IndexPage extends SymfonyPage implements IndexPageInterface
{
    public function getLoyaltyPoints(): int
    {
        return (int) $this->getElement('loyalty_points')->getText();
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'loyalty_points' => '[data-testid="loyalty-points"]',
        ]);
    }
}
```

### Fixture in Behat: nessun file YAML

A differenza dei test di integrazione, i test Behat **non usano Alice**. Lo stato del DB viene costruito **programmaticamente** nei context di Setup, usando le stesse factory/repository che usa l'applicazione. Il pattern è fisso; i nomi di classi e metodi cambiano per progetto:

```php
// tests/Behat/Context/Setup/MyDomainContext.php — nome esemplificativo
final readonly class MyDomainContext implements Context
{
    public function __construct(
        private FactoryInterface $myEntityFactory,          // factory Sylius/Resource
        private RepositoryInterface $myEntityRepository,   // repository Sylius/Resource
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @Given /^there is a "([^"]+)" entity with code "([^"]+)"$/
     */
    public function thereIsAnEntityWithCode(string $name, string $code): void
    {
        $entity = $this->myEntityFactory->createNew();
        $entity->setCode($code);
        $entity->setName($name);
        $this->myEntityRepository->add($entity);
    }

    /**
     * @Given /^(this customer) has (\d+) points$/
     */
    public function thisCustomerHasPoints(CustomerInterface $customer, int $points): void
    {
        // usa servizi reali dell'applicazione, non mock
        $account = $this->accountProvider->provide($customer);
        $this->transactionLogger->addLog($account, 'income', $points);
        $this->entityManager->flush();
    }
}
```

> **Esempio** (dal progetto di riferimento): `LoyaltyContext::thereIsALoyaltyRule()`, `LoyaltyContext::thisCustomerHasLoyaltyPoints()`, ecc.

### Servizi Behat: configurazione YAML

I context vengono configurati come servizi pubblici (necessario per Behat). I nomi di argomenti e servizi sono specifici del progetto:

```yaml
# tests/Behat/Resources/config/services/contexts/setup.yml
services:
    _defaults:
        public: true

    # Ogni context Setup viene dichiarato con le sue dipendenze
    App\Tests\Behat\Context\Setup\MyDomainContext:     ← FQCN del context (varia)
        arguments:
            - '@my_plugin.factory.my_entity'           ← servizi del progetto/plugin
            - '@my_plugin.repository.my_entity'
            - '@doctrine.orm.entity_manager'
```

> **Plugin Sylius**: il namespace sarà `Acme\MyPlugin\Tests\Behat\Context\Setup\...` e i servizi iniettati saranno quelli del plugin.

---

## 4. File di supporto trasversali

### `tests/Adapter/`
Adattatori di supporto per test che necessitano di I/O (es. filesystem virtuale per test che scrivono file). La presenza e i contenuti dipendono dal progetto.

### `tests/Stub/`
Implementazioni fittizie di servizi complessi (non mock PHPUnit), usate nei test di integrazione. Organizzate per servizio esterno simulato:

```
tests/Stub/
└── <ExternalServiceName>/      ← es. MyERP/, PaymentGateway/, CmsClient/...
    ├── HttpClient.php           ← implementa l'interfaccia del client reale
    └── InMemoryServer.php       ← simula il server esterno (se necessario)
```

> **Esempio** (dal progetto di riferimento): `ErpIntegration/InMemoryErp/`, `PayPal/Api/`, `Contentful/ClientStub.php`, `KnpSnappy/PdfGenerator.php`. I nomi riflettono i servizi esterni di quel progetto.

Gli stub **si differenziano dai mock** perché:
- Sono classi complete con logica reale (anche se semplificata)
- Simulano comportamenti complessi che `createMock()` non potrebbe gestire
- Vengono registrati nel container di test al posto dei servizi reali

---

## 5. Riepilogo delle scelte: mock vs reale vs Alice

| Scenario | Cosa usare |
|---|---|
| Entità del dominio come dati di test (Unit) | `new Entity()` + setter in PHP |
| Dipendenza esterna senza I/O (Unit) | `new ConcreteService()` se semplice |
| Dipendenza con I/O esterno (Unit) | `$this->createMock(InterfaceClass::class)` |
| Dati relazionali complessi in DB (Integration) | File YAML Alice |
| Servizio HTTP esterno (Integration) | Stub in `tests/Stub/` o VCR cassette |
| Stato del DB in Behat | Factory/repository reali nei Setup context |
| Navigazione browser in Behat | Page Object + Mink |
| Comandi CLI in Behat | `Console\Context` + `CommandTester` |

---

## 6. Convenzioni generali

- **Namespace**: `App\Tests\Unit\...`, `App\Tests\Integration\...`, `App\Tests\Behat\...` (per uno store); `Acme\MyPlugin\Tests\...` (per un plugin)
- **Naming metodi**: `test_it_does_something_when_condition(): void`
- **Un assert per concetto**, ma più assert per scenario sono accettabili
- **`setUp()`**: sempre per boot kernel, loader Alice e risoluzione servizi
- **`Carbon::setTestNow()`**: obbligatorio nei test sensibili alla data
- **`$this->entityManager->clear()`**: da chiamare dopo operazioni che modificano il DB, prima di rileggere
- **Costante `FIXTURE_BASE_DIR`**: ogni classe Integration definisce `private const FIXTURE_BASE_DIR = __DIR__ . '/path/to/DataFixtures/...'`
- Le fixture **condivise** (canali, locali, configurazioni base) vengono caricate insieme alle fixture specifiche del test
- I test **non si aspettano ordini particolari** di esecuzione: ogni test carica le sue fixture e il loader purga prima di inserire
- **Una classe di test per classe di produzione**: Ogni classe di test deve riferirsi ad **una sola** classe di produzione. Se la classe di produzione si chiama `CartCreateHandler`, la classe di test deve chiamarsi `CartCreateHandlerTest`. Non sono ammesse classi di test che testano più classi di produzione (es. `CartHandlersTest` che testa sia `CartCreateHandler` che `CartUpdateHandler`).

