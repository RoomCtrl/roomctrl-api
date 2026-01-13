# Architektura systemu RoomCtrl API

## Wzorce architektoniczne

RoomCtrl API wykorzystuje następujące wzorce projektowe:

### 1. Feature-based Architecture (Architektura modułowa)

Projekt jest podzielony na niezależne moduły funkcjonalne (Features), z których każdy enkapsuluje kompletną funkcjonalność:

```
src/Feature/
├── Auth/                   # Uwierzytelnianie
├── User/                   # Użytkownicy
├── Organization/           # Organizacje
├── Room/                   # Sale
├── Booking/               # Rezerwacje
├── Issue/                 # Usterki
├── Mail/                  # Powiadomienia
└── Download/              # Pobieranie plików
```

Każdy moduł zawiera kompletny "vertical slice":
- **Controller** - Endpoint API
- **Service** - Logika biznesowa
- **Repository** - Zapytania do bazy
- **Entity** - Model danych
- **DTO** - Obiekty transferu danych
- **EventListener** - Obsługa zdarzeń (opcjonalnie)

### 2. Layered Architecture (Architektura warstwowa)

```
┌─────────────────────────────────────┐
│     Presentation Layer              │  Controllers, DTOs
│  (HTTP Request/Response handling)   │
├─────────────────────────────────────┤
│     Business Logic Layer            │  Services, Event Listeners
│    (Domain logic & validation)      │
├─────────────────────────────────────┤
│     Data Access Layer               │  Repositories, Doctrine ORM
│    (Database queries)               │
├─────────────────────────────────────┤
│     Persistence Layer               │  Entities, Database
│       (Data storage)                │
└─────────────────────────────────────┘
```

### 3. Repository Pattern

Wszystkie zapytania do bazy danych są enkapsulowane w repozytoriach:

```php
// Przykład: UserRepository
class UserRepository extends ServiceEntityRepository
{
    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }
}
```

### 4. Service Layer Pattern

Logika biznesowa jest izolowana w serwisach:

```php
// Przykład: BookingService
class BookingService implements BookingServiceInterface
{
    public function createBooking(CreateBookingDTO $dto, User $user): Booking
    {
        // Walidacja biznesowa
        $this->validateBookingTime($dto);
        $this->checkRoomAvailability($dto);
        
        // Tworzenie rezerwacji
        $booking = new Booking();
        // ... logika tworzenia
        
        // Wysyłka powiadomień
        $this->sendBookingNotifications($booking);
        
        return $booking;
    }
}
```

### 5. DTO Pattern

Dane wejściowe i wyjściowe są przekazywane przez Data Transfer Objects:

```php
// Request DTO
class CreateBookingDTO
{
    #[Assert\NotBlank]
    public string $title;
    
    #[Assert\Uuid]
    public string $roomId;
    
    #[Assert\NotBlank]
    public string $startedAt;
}

// Response DTO
class BookingResponseDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public string $startedAt,
        public string $endedAt
    ) {}
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'startedAt' => $this->startedAt,
            'endedAt' => $this->endedAt
        ];
    }
}
```

## Struktura katalogów Feature

Każdy moduł feature ma standardową strukturę:

```
Feature/Example/
├── Controller/
│   └── ExampleController.php     # Endpointy REST API
├── Service/
│   ├── ExampleServiceInterface.php
│   └── ExampleService.php        # Logika biznesowa
├── Repository/
│   └── ExampleRepository.php     # Zapytania do DB
├── Entity/
│   └── Example.php               # Model Doctrine
├── DTO/
│   ├── CreateExampleDTO.php      # Request DTOs
│   └── ExampleResponseDTO.php    # Response DTOs
├── EventListener/                # (opcjonalnie)
│   └── ExampleListener.php
└── DataFixtures/                 # (opcjonalnie)
    └── ExampleFixtures.php
```

## Wspólne komponenty (Common)

Elementy współdzielone między modułami znajdują się w `src/Common/`:

```
src/Common/
├── EventListener/
│   ├── ExceptionListener.php      # Globalna obsługa wyjątków
└── Utility/
    └── ValidationErrorFormatter.php  # Formatowanie błędów walidacji
```

## Przepływ żądania (Request Flow)

### 1. Typowe żądanie API

```
┌──────────┐
│  Client  │
└────┬─────┘
     │ 1. HTTP Request + JWT Token
     ▼
┌─────────────────────────────────┐
│   Symfony Security Firewall    │
│  • Weryfikacja JWT              │
│  • Załadowanie User entity      │
└────────┬────────────────────────┘
         │ 2. Authenticated Request
         ▼
┌─────────────────────────────────┐
│      Access Control             │
│  • Sprawdzenie uprawnień        │
│    (#[IsGranted])               │
└────────┬────────────────────────┘
         │ 3. Authorized Request
         ▼
┌─────────────────────────────────┐
│       Controller                │
│  • Deserializacja JSON          │
│  • Walidacja DTO (Validator)    │
│  • Wywołanie Service            │
└────────┬────────────────────────┘
         │ 4. Call business logic
         ▼
┌─────────────────────────────────┐
│        Service                  │
│  • Logika biznesowa             │
│  • Walidacja reguł domenowych   │
│  • Wywołanie Repository         │
│  • Wywołanie innych serwisów    │
└────────┬────────────────────────┘
         │ 5. Database operations
         ▼
┌─────────────────────────────────┐
│      Repository                 │
│  • QueryBuilder                 │
│  • Custom queries               │
│  • Doctrine ORM                 │
└────────┬────────────────────────┘
         │ 6. Entity/Data
         ▼
┌─────────────────────────────────┐
│      PostgreSQL DB              │
└────────┬────────────────────────┘
         │ 7. Result
         ▼
    (powrót przez stos)
         │
         ▼
┌─────────────────────────────────┐
│      Controller                 │
│  • Utworzenie Response DTO      │
│  • Serializacja do JSON         │
│  • JsonResponse                 │
└────────┬────────────────────────┘
         │ 8. HTTP Response (JSON)
         ▼
┌──────────┐
│  Client  │
└──────────┘
```

### 2. Przykład kodu z kontrolera

```php
#[Route('/bookings', name: 'bookings_create', methods: ['POST'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
public function create(
    Request $request,
    BookingServiceInterface $bookingService,
    ValidatorInterface $validator
): JsonResponse {
    // 1. Deserializacja JSON do DTO
    $data = json_decode($request->getContent(), true);
    $dto = new CreateBookingDTO();
    // ... mapowanie danych
    
    // 2. Walidacja DTO
    $errors = $validator->validate($dto);
    if (count($errors) > 0) {
        return $this->json(
            ValidationErrorFormatter::format($errors),
            Response::HTTP_BAD_REQUEST
        );
    }
    
    // 3. Pobranie zalogowanego użytkownika
    /** @var User $user */
    $user = $this->getUser();
    
    // 4. Wywołanie logiki biznesowej
    try {
        $booking = $bookingService->createBooking($dto, $user);
        $response = new BookingResponseDTO($booking);
        
        return $this->json(
            $response->toArray(),
            Response::HTTP_CREATED
        );
    } catch (InvalidArgumentException $e) {
        return $this->json([
            'code' => Response::HTTP_BAD_REQUEST,
            'message' => $e->getMessage()
        ], Response::HTTP_BAD_REQUEST);
    }
}
```

## Dependency Injection

Symfony automatycznie wstrzykuje zależności przez konstruktor (autowiring):

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true      # Auto-wiring
        autoconfigure: true # Auto-tagging
    
    App\:
        resource: '../src/'
        exclude:
            - '../src/Entity/'
            - '../src/Kernel.php'
```

Przykład:
```php
class BookingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BookingRepository $bookingRepository,
        private readonly MailService $mailService
    ) {}
    // Wszystkie zależności są auto-wstrzykiwane
}
```

## Event System

System wykorzystuje Symfony Kernel Events do obsługi zdarzeń. Projekt używa **dwóch sposobów** rejestracji event listenerów:

### 1. Ręczna rejestracja w services.yaml

```php
// src/Feature/Auth/EventListener/CheckAuthenticationDataListener.php
class CheckAuthenticationDataListener
{
    public function onKernelRequest(RequestEvent $event): void
    {
        // Walidacja danych uwierzytelniających przed logowaniem
    }
}

// src/Common/EventListener/ExceptionListener.php
class ExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        // Globalna obsługa wyjątków
        // Formatowanie odpowiedzi błędów jako JSON
    }
}
```

Rejestracja w `config/services.yaml`:
```yaml
App\Feature\Auth\EventListener\CheckAuthenticationDataListener:
    tags:
        - { name: kernel.event_listener, event: kernel.request, priority: 30 }

App\Common\EventListener\ExceptionListener:
    tags:
        - { name: kernel.event_listener, event: kernel.exception, priority: 10 }
```

### 2. Atrybuty PHP 8 (autoconfigure)

```php
// src/Feature/Booking/EventListener/BookingStatusUpdateListener.php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 10)]
class BookingStatusUpdateListener
{
    public function onKernelController(ControllerEvent $event): void
    {
        // Automatyczna aktualizacja statusów rezerwacji
    }
}
```

Dzięki `autoconfigure: true` w services.yaml, listenery z atrybutem są automatycznie rejestrowane.

## Walidacja

### 1. Walidacja na poziomie DTO (Symfony Validator)

```php
use Symfony\Component\Validator\Constraints as Assert;

class CreateUserDTO
{
    #[Assert\NotBlank(message: 'Username is required')]
    #[Assert\Length(min: 3, max: 50)]
    public string $username;
    
    #[Assert\Email]
    public string $email;
    
    #[Assert\Regex(
        pattern: '/^(?=.*[A-Z])(?=.*[0-9]).{8,}$/',
        message: 'Password must contain uppercase and number'
    )]
    public string $password;
}
```

### 2. Walidacja na poziomie Entity

```php
#[ORM\Entity]
#[UniqueEntity(fields: ['username'], message: 'This username is already taken')]
#[UniqueEntity(fields: ['email'], message: 'This email is already in use')]
class User implements UserInterface
{
    #[ORM\Column(type: 'string', unique: true)]
    private string $username;
}
```

### 3. Walidacja biznesowa w Service

```php
class BookingService
{
    private function validateBookingTime(CreateBookingDTO $dto): void
    {
        $start = new DateTimeImmutable($dto->startedAt);
        $end = new DateTimeImmutable($dto->endedAt);
        
        if ($start >= $end) {
            throw new InvalidArgumentException(
                'End time must be after start time'
            );
        }
        
        if ($start < new DateTimeImmutable()) {
            throw new InvalidArgumentException(
                'Cannot create booking in the past'
            );
        }
    }
}
```

## Obsługa błędów

### ExceptionListener

Centralny listener konwertuje wszystkie wyjątki na spójne odpowiedzi JSON:

```php
class ExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        
        $response = new JsonResponse([
            'code' => $this->getStatusCode($exception),
            'message' => $exception->getMessage()
        ], $this->getStatusCode($exception));
        
        $event->setResponse($response);
    }
    
    private function getStatusCode(Throwable $exception): int
    {
        return match (true) {
            $exception instanceof NotFoundHttpException => Response::HTTP_NOT_FOUND,
            $exception instanceof AccessDeniedException => Response::HTTP_FORBIDDEN,
            $exception instanceof InvalidArgumentException => Response::HTTP_BAD_REQUEST,
            default => Response::HTTP_INTERNAL_SERVER_ERROR
        };
    }
}
```

## Cache Strategy

Symfony cache jest konfigurowany w `config/packages/cache.yaml`:

```yaml
framework:
    cache:
        app: cache.adapter.filesystem
        system: cache.adapter.system
```

Doctrine używa osobnych pool'i cache:
```yaml
doctrine:
    orm:
        metadata_cache_driver:
            type: pool
            pool: doctrine.system_cache_pool
        result_cache_driver:
            type: pool
            pool: doctrine.result_cache_pool
```

## Database Migrations

Migracje są zarządzane przez Doctrine Migrations:

```bash
# Generowanie migracji
php bin/console doctrine:migrations:diff

# Wykonanie migracji
php bin/console doctrine:migrations:migrate

# Status migracji
php bin/console doctrine:migrations:status
```

Pliki migracji są przechowywane w `migrations/`.

## Routing

Routing jest oparty na atrybutach PHP 8:

```php
#[Route('/api/bookings', name: 'bookings_list', methods: ['GET'])]
#[Route('/api/bookings', name: 'bookings_create', methods: ['POST'])]
#[Route('/api/bookings/{id}', name: 'bookings_show', methods: ['GET'])]
```

Konfiguracja w `config/routes.yaml`:
```yaml
controllers:
    resource:
        path: ../src/Feature/
        namespace: App\Feature
    type: attribute
    prefix: /api
```

Wszystkie kontrolery z Features są automatycznie dostępne pod `/api`.

## Podsumowanie

Architektura RoomCtrl API jest:
- **Modularna** - Łatwa rozbudowa przez dodawanie nowych Features
- **Testwalna** - Separacja warstw ułatwia testowanie
- **Skalowalna** - Niezależne moduły można rozwijać równolegle
- **Maintainable** - Konwencje i standardy ułatwiają utrzymanie kodu
