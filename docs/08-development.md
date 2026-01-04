# Testowanie i rozwój

## Narzędzia deweloperskie

### Dostępne narzędzia

| Narzędzie | Opis | Komenda |
|-----------|------|---------|
| **Symfony Console** | CLI do zarządzania aplikacją | `php bin/console` |
| **PHP CodeSniffer** | Sprawdzanie standardów kodowania | `composer phpcs` |
| **PHPCBF** | Automatyczne naprawianie kodu | `composer phpcs:fix` |
| **Doctrine Migrations** | Zarządzanie migracjami | `php bin/console doctrine:migrations:*` |
| **Doctrine Fixtures** | Ładowanie danych testowych | `php bin/console doctrine:fixtures:load` |
| **Symfony Profiler** | Profiler webowy (dev) | `http://localhost:8000/_profiler` |
| **API Documentation** | Swagger UI | `http://localhost:8000/api/doc` |

## Środowiska

### APP_ENV

| Środowisko | Plik | Opis | Użycie |
|------------|------|------|--------|
| **dev** | `.env.local` | Deweloperskie | Lokalny development |
| **test** | `.env.test` | Testowe | Testy automatyczne |
| **prod** | `.env` | Produkcyjne | Serwer produkcyjny |

### Przełączanie środowisk

```bash
# Development
export APP_ENV=dev
export APP_DEBUG=1

# Production
export APP_ENV=prod
export APP_DEBUG=0
```

## Symfony Console

### Najczęściej używane komendy

#### Informacje o aplikacji
```bash
# Informacje o Symfony
php bin/console about

# Lista wszystkich komend
php bin/console list

# Pomoc dla komendy
php bin/console help doctrine:migrations:migrate
```

#### Cache
```bash
# Wyczyszczenie cache
php bin/console cache:clear

# Podgrzanie cache
php bin/console cache:warmup

# Wyczyszczenie konkretnego pool'a
php bin/console cache:pool:clear cache.app
```

#### Baza danych
```bash
# Utworzenie bazy
php bin/console doctrine:database:create

# Usunięcie bazy
php bin/console doctrine:database:drop --force

# Generowanie migracji
php bin/console doctrine:migrations:diff

# Wykonanie migracji
php bin/console doctrine:migrations:migrate

# Status migracji
php bin/console doctrine:migrations:status

# Cofnięcie ostatniej migracji
php bin/console doctrine:migrations:migrate prev

# Załadowanie fixtures
php bin/console doctrine:fixtures:load --no-interaction
```

#### Routing
```bash
# Lista wszystkich route'ów
php bin/console debug:router

# Szczegóły konkretnego route'a
php bin/console debug:router bookings_create

# Testowanie matchowania URL
php bin/console router:match /api/rooms
```

#### Security
```bash
# Sprawdzenie konfiguracji security
php bin/console debug:firewall

# Lista wszystkich voters
php bin/console debug:security
```

#### Mailer
```bash
# Test wysyłki emaila (mailhog w dev)
php bin/console mailer:test recipient@example.com
```

## PHP CodeSniffer

### Konfiguracja

**Plik:** `phpcs.xml`

```xml
<?xml version="1.0"?>
<ruleset name="RoomCtrl">
    <description>Coding standards for RoomCtrl API</description>
    
    <file>src</file>
    
    <rule ref="PSR12"/>
    
    <arg name="colors"/>
    <arg value="sp"/>
</ruleset>
```

### Użycie

```bash
# Sprawdzenie kodu
composer phpcs

# lub bezpośrednio
vendor/bin/phpcs --standard=phpcs.xml src/

# Automatyczne naprawianie
composer phpcs:fix

# lub bezpośrednio
vendor/bin/phpcbf --standard=phpcs.xml src/
```

## Testowanie PHPUnit

### Konfiguracja

**Plik:** `phpunit.xml.dist`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.5/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         cacheDirectory=".phpunit.cache"
         executionOrder="depends,defects">
    <php>
        <server name="APP_ENV" value="test" force="true"/>
        <server name="SHELL_VERBOSITY" value="-1"/>
        <server name="SYMFONY_PHPUNIT_VERSION" value="10.5"/>
    </php>

    <testsuites>
        <testsuite name="Project Test Suite">
            <directory>tests</directory>
        </testsuite>
    </testsuites>

    <source>
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </source>
</phpunit>
```

### Uruchamianie testów

```bash
# Wszystkie testy
composer test

# Lub bezpośrednio
vendor/bin/phpunit

# Konkretny plik testowy
vendor/bin/phpunit tests/Feature/Organization/Service/OrganizationServiceTest.php

# Konkretny test
vendor/bin/phpunit --filter testDeleteOrganizationSucceedsWhenNoUsers

# Z verbose output
vendor/bin/phpunit --verbose

# Z coverage (generuje HTML w coverage/)
composer test:coverage

# Lub
vendor/bin/phpunit --coverage-html coverage/
```

### Struktura testów

Projekt używa struktury testów zgodnej z organizacją features:

```
tests/
├── bootstrap.php
├── Common/
│   └── Utility/
└── Feature/
    ├── Booking/
    ├── Organization/
    │   └── Service/
    │       └── OrganizationServiceTest.php
    └── Room/
```

### Przykład testu jednostkowego

```php
namespace App\Tests\Feature\Organization\Service;

use App\Feature\Organization\Entity\Organization;
use App\Feature\Organization\Repository\OrganizationRepository;
use App\Feature\Organization\Service\OrganizationService;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

class OrganizationServiceTest extends TestCase
{
    private OrganizationService $organizationService;
    private OrganizationRepository $organizationRepository;

    protected function setUp(): void
    {
        // Przygotowanie mocków
        $this->organizationRepository = $this->createMock(OrganizationRepository::class);
        $this->organizationService = new OrganizationService(
            $this->organizationRepository
        );
    }

    public function testDeleteOrganizationSucceedsWhenNoUsers(): void
    {
        // Arrange
        $organization = $this->createMock(Organization::class);
        $users = new ArrayCollection();

        $organization
            ->expects($this->once())
            ->method('getUsers')
            ->willReturn($users);

        $this->organizationRepository
            ->expects($this->once())
            ->method('remove')
            ->with($organization, true);

        // Act
        $result = $this->organizationService->deleteOrganization($organization);

        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(200, $result->getCode());
    }
}
```

### Best practices testowania

#### 1. Nazewnictwo
```php
// ✅ Dobre - jasno określa co jest testowane
public function testDeleteOrganizationReturnsConflictWhenHasUsers(): void

// ❌ Złe - niejasne
public function testDelete(): void
```

#### 2. AAA Pattern (Arrange-Act-Assert)
```php
public function testSomething(): void
{
    // Arrange - przygotuj dane
    $organization = new Organization();
    $organization->setName('Test');
    
    // Act - wykonaj akcję
    $result = $this->service->process($organization);
    
    // Assert - sprawdź wyniki
    $this->assertTrue($result->isSuccess());
}
```

#### 3. Używanie Data Providers
```php
/**
 * @dataProvider invalidEmailProvider
 */
public function testValidationFailsForInvalidEmail(string $email): void
{
    $this->expectException(ValidationException::class);
    $this->validator->validateEmail($email);
}

public static function invalidEmailProvider(): array
{
    return [
        ['invalid'],
        ['@example.com'],
        ['user@'],
        [''],
    ];
}
```

#### 4. Mockowanie zależności
```php
// Mock repositorium
$repository = $this->createMock(UserRepository::class);
$repository
    ->expects($this->once())
    ->method('find')
    ->with(1)
    ->willReturn($user);

// Mock z wyjątkiem
$repository
    ->method('save')
    ->willThrowException(new \RuntimeException('Database error'));
```

### Testy w Docker

```bash
# Development
docker-compose exec php composer test
docker-compose exec php composer test:coverage

# Konkretny plik
docker-compose exec php vendor/bin/phpunit tests/Feature/Organization/Service/OrganizationServiceTest.php

# Z filtrem
docker-compose exec php vendor/bin/phpunit --filter testDeleteOrganization
```

### Continuous Integration

Przykład konfiguracji dla GitHub Actions:

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: pdo, pdo_pgsql
          coverage: xdebug
      
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
      
      - name: Run tests
        run: composer test
      
      - name: Generate coverage
        run: composer test:coverage
      
      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage/clover.xml
```

### Debugging testów

```bash
# Pokaż więcej informacji
vendor/bin/phpunit --verbose --debug

# Stop on failure
vendor/bin/phpunit --stop-on-failure

# Stop on error
vendor/bin/phpunit --stop-on-error

# Test tylko z określoną grupą
vendor/bin/phpunit --group=integration

# Exclude grupę
vendor/bin/phpunit --exclude-group=slow
```

### Metryki jakości

**Target coverage:** 80%+

```bash
# Sprawdź coverage
composer test:coverage
open coverage/index.html

# W terminalu
vendor/bin/phpunit --coverage-text
```

**Typy testów:**
- Unit tests - 90%+ pokrycia logiki biznesowej
- Integration tests - kluczowe scenariusze
- Feature tests - główne flow aplikacji

## Data Fixtures

### Ładowanie fixtures

```bash
# Załaduj wszystkie fixtures (usunie obecne dane!)
php bin/console doctrine:fixtures:load

# Bez potwierdzenia
php bin/console doctrine:fixtures:load --no-interaction

# Append (nie usuwa danych)
php bin/console doctrine:fixtures:load --append
```

### Przykład fixtures

```php
namespace App\Feature\Organization\DataFixtures;

use App\Feature\Organization\Entity\Organization;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class OrganizationFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $organization = new Organization();
        $organization->setRegon('123456789');
        $organization->setName('Example Corp');
        $organization->setEmail('contact@example.com');
        
        $manager->persist($organization);
        
        // Reference dla innych fixtures
        $this->addReference('org-example', $organization);
        
        $manager->flush();
    }
}
```

### Używanie referencji między fixtures

```php
class UserFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Pobierz organizację z referencji
        $organization = $this->getReference('org-example');
        
        $user = new User();
        $user->setOrganization($organization);
        // ...
        
        $manager->persist($user);
        $manager->flush();
    }
    
    public function getDependencies(): array
    {
        return [
            OrganizationFixtures::class,
        ];
    }
}
```

## Skrypt database.sh

Szybkie resetowanie bazy danych:

```bash
#!/bin/sh
php bin/console doctrine:database:drop --force
rm -f migrations/*.php
php bin/console doctrine:database:create
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
```

Użycie:
```bash
chmod +x database.sh
./database.sh
```

**UWAGA:** To usuwa całą bazę danych!

## Debugging

### Symfony Profiler

W środowisku deweloperskim dostępny jest Web Profiler:

```
http://localhost:8000/_profiler
```

Zawiera informacje o:
- Timeline żądania
- Database queries
- Route matching
- Security
- Cache
- Mailer
- Events

### Dump and Die

```php
use Symfony\Component\VarDumper\VarDumper;

// Dump zmiennej
VarDumper::dump($variable);

// Dump and die
dd($variable);

// Multiple dumps
dump($var1, $var2, $var3);
```

### Logowanie

```php
use Psr\Log\LoggerInterface;

class MyService
{
    public function __construct(
        private LoggerInterface $logger
    ) {}
    
    public function doSomething(): void
    {
        $this->logger->info('Doing something', [
            'userId' => $user->getId(),
            'action' => 'create_booking'
        ]);
        
        $this->logger->error('Something went wrong', [
            'exception' => $e->getMessage()
        ]);
    }
}
```

Logi znajdują się w: `var/log/dev.log` (dev) lub `var/log/prod.log` (prod)

### SQL Queries Debug

```bash
# Pokaż wszystkie zapytania wykonane podczas żądania
# W Symfony Profiler → Doctrine → Queries
```

W kodzie:
```php
use Doctrine\ORM\EntityManagerInterface;

$query = $em->createQuery('SELECT u FROM App\Entity\User u');
echo $query->getSQL(); // Zobacz surowe SQL
```

## Testowanie API

### cURL

```bash
# Login
curl -X POST http://localhost:8000/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'

# Użyj token w kolejnym żądaniu
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGc..."

# Authenticated request
curl -X GET http://localhost:8000/api/me \
  -H "Authorization: Bearer $TOKEN"

# POST z danymi
curl -X POST http://localhost:8000/api/rooms \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "roomName": "Test Room",
    "capacity": 10,
    "size": 50.0,
    "location": "Floor 1",
    "access": "Card"
  }'
```

### HTTPie (przyjazniejszy niż cURL)

```bash
# Instalacja
brew install httpie  # macOS
apt install httpie   # Ubuntu

# Login
http POST :8000/api/login_check username=admin password=admin123

# Authenticated request
http GET :8000/api/me Authorization:"Bearer $TOKEN"

# POST
http POST :8000/api/rooms \
  Authorization:"Bearer $TOKEN" \
  roomName="Test" capacity:=10 size:=50.0
```

### Postman / Insomnia

Zaimportuj kolekcję z OpenAPI:
1. Otwórz `http://localhost:8000/api/doc.json`
2. Zapisz JSON
3. Zaimportuj do Postman/Insomnia

### Swagger UI

Najłatwiejszy sposób testowania:
```
http://localhost:8000/api/doc
```

- Przeglądaj wszystkie endpointy
- Testuj bezpośrednio z przeglądarki
- Automatyczne schematy request/response

## Troubleshooting

### Typowe problemy

#### 1. "Cannot autowire argument..."

**Problem:** Symfony nie może znaleźć serwisu do wstrzyknięcia

**Rozwiązanie:**
```bash
# Sprawdź czy serwis jest zarejestrowany
php bin/console debug:container MyServiceInterface

# Wyczyść cache
php bin/console cache:clear
```

#### 2. "No route found for..."

**Problem:** Route nie istnieje lub źle dopasowany

**Rozwiązanie:**
```bash
# Lista wszystkich route'ów
php bin/console debug:router

# Sprawdź dopasowanie
php bin/console router:match /api/rooms
```

#### 3. "Class not found"

**Problem:** Autoloader nie widzi klasy

**Rozwiązanie:**
```bash
# Przebuduj autoloader
composer dump-autoload
```

#### 4. "Access denied" / 403

**Problem:** Brak uprawnień

**Rozwiązanie:**
- Sprawdź role użytkownika w JWT
- Sprawdź `#[IsGranted]` na metodzie
- Sprawdź `access_control` w security.yaml

```bash
# Debug security
php bin/console debug:firewall
```

#### 5. Migracje nie działają

**Problem:** Błędy podczas migracji

**Rozwiązanie:**
```bash
# Status migracji
php bin/console doctrine:migrations:status

# Sync metadata
php bin/console doctrine:migrations:sync-metadata-storage

# Ręczne wykonanie SQL
php bin/console doctrine:migrations:execute --up VERSION
```

#### 6. "Too many connections" PostgreSQL

**Problem:** Zbyt wiele otwartych połączeń do bazy

**Rozwiązanie:**
```bash
# Sprawdź połączenia
psql -U roomctrl -d roomctrl_dev
SELECT count(*) FROM pg_stat_activity;

# Zamknij połączenia
SELECT pg_terminate_backend(pid) 
FROM pg_stat_activity 
WHERE datname = 'roomctrl_dev' AND pid <> pg_backend_pid();
```

## Best Practices

### 1. Używaj type hints
```php
// ✅ DOBRE
public function createUser(CreateUserDTO $dto): User

// ❌ ZŁE
public function createUser($dto)
```

### 2. Używaj dependency injection
```php
// ✅ DOBRE
public function __construct(
    private UserRepository $userRepository
) {}

// ❌ ZŁE
public function getUser() {
    $repo = new UserRepository();
}
```

### 3. Waliduj dane wejściowe
```php
// ✅ DOBRE
$errors = $this->validator->validate($dto);
if (count($errors) > 0) {
    throw new ValidationException();
}

// ❌ ZŁE
// Bezpośrednie użycie danych z requestu
```

### 4. Używaj transactions dla atomowości
```php
// ✅ DOBRE
$this->entityManager->beginTransaction();
try {
    $this->entityManager->persist($booking);
    $this->mailService->send($booking);
    $this->entityManager->flush();
    $this->entityManager->commit();
} catch (\Exception $e) {
    $this->entityManager->rollback();
    throw $e;
}
```

### 5. Loguj istotne operacje
```php
// ✅ DOBRE
$this->logger->info('Booking created', [
    'bookingId' => $booking->getId(),
    'userId' => $user->getId(),
    'roomId' => $room->getId()
]);
```

### 6. Używaj DTO dla request/response
```php
// ✅ DOBRE
public function create(Request $request): JsonResponse
{
    $dto = new CreateBookingDTO();
    // map request data to DTO
    $booking = $this->service->create($dto);
    return $this->json(new BookingResponseDTO($booking));
}
```

## Workflow Development

### Dodawanie nowego feature

1. **Utwórz strukturę katalogów**
```bash
src/Feature/NewFeature/
├── Controller/
├── Service/
├── Entity/
├── Repository/
└── DTO/
```

2. **Utwórz Entity**
```php
namespace App\Feature\NewFeature\Entity;

#[ORM\Entity]
class NewEntity
{
    // ...
}
```

3. **Wygeneruj migrację**
```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

4. **Utwórz Repository**
```php
namespace App\Feature\NewFeature\Repository;

class NewEntityRepository extends ServiceEntityRepository
{
    // ...
}
```

5. **Utwórz Service**
```php
namespace App\Feature\NewFeature\Service;

interface NewFeatureServiceInterface {}

class NewFeatureService implements NewFeatureServiceInterface
{
    // ...
}
```

6. **Utwórz Controller**
```php
namespace App\Feature\NewFeature\Controller;

#[Route('/new-feature')]
class NewFeatureController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function list(): Response
    {
        // ...
    }
}
```

7. **Dodaj dokumentację OpenAPI**
```php
#[OA\Get(
    path: '/api/new-feature',
    summary: 'Get list',
    // ...
)]
```

8. **Testuj**
- Sprawdź w Swagger UI
- Przetestuj z cURL/Postman
- Sprawdź logi

## Deployment

### Przygotowanie do produkcji

```bash
# 1. Ustaw środowisko
export APP_ENV=prod
export APP_DEBUG=0

# 2. Zainstaluj tylko produkcyjne zależności
composer install --no-dev --optimize-autoloader

# 3. Wyczyść cache
php bin/console cache:clear --env=prod

# 4. Podgrzej cache
php bin/console cache:warmup --env=prod

# 5. Wykonaj migracje
php bin/console doctrine:migrations:migrate --no-interaction

# 6. Sprawdź bezpieczeństwo
symfony security:check
composer audit
```

### Checklist produkcyjny

- [ ] `APP_ENV=prod`
- [ ] `APP_DEBUG=0`
- [ ] Silne `APP_SECRET`
- [ ] Bezpieczne dane dostępowe do bazy
- [ ] HTTPS włączony
- [ ] JWT klucze z odpowiednimi uprawnieniami (600)
- [ ] CORS skonfigurowany dla właściwej domeny
- [ ] Sprawdzona konfiguracja mailera
- [ ] Katalog `var/` z odpowiednimi uprawnieniami
- [ ] Katalog `public/uploads/` z odpowiednimi uprawnieniami
- [ ] `.env` nie w repozytorium (w .gitignore)
- [ ] Backupy bazy danych skonfigurowane

## Monitoring produkcyjny

### Logi

```bash
# Tail logs w czasie rzeczywistym
tail -f var/log/prod.log

# Filtrowanie błędów
grep ERROR var/log/prod.log

# Statystyki
cat var/log/prod.log | grep ERROR | wc -l
```

### Metryki

Zalecane narzędzia:
- **Sentry** - Error tracking
- **New Relic** / **Datadog** - APM
- **Prometheus** + **Grafana** - Metryki
- **ELK Stack** - Log aggregation

## Podsumowanie

RoomCtrl API zapewnia kompleksowe narzędzia do:
- ✅ Rozwoju lokalnego
- ✅ Debugowania
- ✅ Testowania
- ✅ Wdrożenia na produkcję
- ✅ Monitoringu

Postępuj zgodnie z best practices i konwencjami projektu dla spójności kodu.
