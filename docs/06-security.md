# Uprawnienia i bezpieczeństwo

## Model uprawnień

RoomCtrl API wykorzystuje rolowy model kontroli dostępu (RBAC - Role-Based Access Control) zaimplementowany przez Symfony Security.

### Role w systemie

| Rola | Opis | Uprawnienia |
|------|------|-------------|
| **PUBLIC_ACCESS** | Dostęp publiczny | Login, rejestracja, reset hasła, pobieranie plików, formularze kontaktowe |
| **IS_AUTHENTICATED_FULLY** | Zalogowany użytkownik | Przeglądanie sal, tworzenie rezerwacji, zgłaszanie usterek, zarządzanie swoim profilem |
| **ROLE_USER** | Standardowy użytkownik | To samo co IS_AUTHENTICATED_FULLY (domyślna rola) |
| **ROLE_ADMIN** | Administrator | Pełne uprawnienia - zarządzanie wszystkimi zasobami (użytkownicy, organizacje, sale, usterki) |

### Hierarchia ról

```
ROLE_ADMIN
    └── ROLE_USER
            └── IS_AUTHENTICATED_FULLY
                    └── PUBLIC_ACCESS
```

ROLE_ADMIN automatycznie dziedziczy wszystkie uprawnienia ROLE_USER.

## Konfiguracja Security

### security.yaml

**Lokalizacja:** `config/packages/security.yaml`

```yaml
security:
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'
    
    providers:
        app_user_provider:
            entity:
                class: App\Feature\User\Entity\User
                property: username

    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false
        
        login:
            pattern: ^/api/login
            stateless: true
            user_checker: App\Feature\User\Security\UserChecker
            json_login:
                check_path: /api/login_check
                success_handler: lexik_jwt_authentication.handler.authentication_success
                failure_handler: lexik_jwt_authentication.handler.authentication_failure
        
        send_mail:
            pattern: ^/api/send_mail
            security: false

        contact_mail:
            pattern: ^/api/contact_mail
            security: false

        download:
            pattern: ^/api/download
            security: false

        password_reset:
            pattern: ^/api/users/password_reset
            security: false

        register:
            pattern: ^/api/register
            security: false

        api:
            pattern: ^/api
            stateless: true
            user_checker: App\Feature\User\Security\UserChecker
            jwt: ~

    access_control:
        - { path: ^/api/login, roles: PUBLIC_ACCESS }
        - { path: ^/api/register, roles: PUBLIC_ACCESS }
        - { path: ^/api/send_mail, roles: PUBLIC_ACCESS }
        - { path: ^/api/contact_mail, roles: PUBLIC_ACCESS }
        - { path: ^/api/download, roles: PUBLIC_ACCESS }
        - { path: ^/api/users/password_reset, roles: PUBLIC_ACCESS }
        - { path: ^/api/doc, roles: PUBLIC_ACCESS }
        - { path: ^/api/public, roles: PUBLIC_ACCESS }
        - { path: ^/api, roles: IS_AUTHENTICATED_FULLY }
```

### Wyjaśnienie konfiguracji

#### Firewalls (Zapory)

1. **dev** - Wyłączona ochrona dla narzędzi developerskich
2. **login** - Dedykowany firewall dla logowania JWT
3. **send_mail, contact_mail** - Publiczne endpointy mailowe
4. **download** - Publiczne pobieranie plików
5. **password_reset** - Publiczny reset hasła
6. **register** - Publiczna rejestracja
7. **api** - Główny firewall z JWT dla reszty API

#### Access Control

Zasady są sprawdzane w kolejności:
1. Najpierw sprawdzane są ścieżki publiczne
2. Następnie wymóg uwierzytelnienia dla całego `/api`

## JWT Authentication

### Konfiguracja

**Lokalizacja:** `config/packages/lexik_jwt_authentication.yaml`

```yaml
lexik_jwt_authentication:
    secret_key: '%env(resolve:JWT_SECRET_KEY)%'
    public_key: '%env(resolve:JWT_PUBLIC_KEY)%'
    pass_phrase: '%env(JWT_PASSPHRASE)%'
    token_ttl: '%env(JWT_TOKEN_TTL)%'
```

### Zmienne środowiskowe (.env)

```env
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your-passphrase-here
JWT_TOKEN_TTL=3600  # 1 godzina
```

### Proces uwierzytelniania

```
1. Login Request
   POST /api/login_check
   {username, password}
          ↓
2. UserChecker
   - Sprawdza czy użytkownik jest aktywny
          ↓
3. Password Verification
   - Symfony Security weryfikuje hasło
          ↓
4. JWT Token Generation
   - Lexik JWT generuje token
          ↓
5. Response
   {token: "eyJ0eXAiOiJKV1QiLCJhbGc..."}
          ↓
6. Client Request with Token
   Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
          ↓
7. JWT Verification
   - Symfony Security weryfikuje token
   - Ładuje User entity
          ↓
8. Authorization Check
   - Sprawdzenie uprawnień (#[IsGranted])
          ↓
9. Controller Action
```

### Struktura JWT Token

Przykładowy payload JWT:
```json
{
  "iat": 1704283200,
  "exp": 1704286800,
  "roles": [
    "ROLE_USER"
  ],
  "username": "john.doe"
}
```

- **iat** (issued at) - Czas utworzenia tokena
- **exp** (expiration) - Czas wygaśnięcia tokena
- **roles** - Role użytkownika
- **username** - Nazwa użytkownika

## User Checker

**Lokalizacja:** `src/Feature/User/Security/UserChecker.php`

Sprawdza dodatkowe warunki użytkownika podczas uwierzytelniania:

```php
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Sprawdza czy użytkownik jest aktywny
        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException(
                'Your account is inactive. Please contact administrator.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Dodatkowe sprawdzenia po uwierzytelnieniu
    }
}
```

## Autoryzacja w kontrolerach

### Atrybut #[IsGranted]

```php
use Symfony\Component\Security\Http\Attribute\IsGranted;

// Wymaga zalogowania
#[IsGranted('IS_AUTHENTICATED_FULLY')]
public function myBookings(): Response
{
    // ...
}

// Wymaga roli ROLE_ADMIN
#[IsGranted('ROLE_ADMIN')]
public function deleteUser(string $id): Response
{
    // ...
}
```

### Programatyczna autoryzacja

```php
// W kontrolerze
if (!$this->isGranted('ROLE_ADMIN')) {
    throw $this->createAccessDeniedException('Access denied');
}

// Sprawdzenie właściciela zasobu
/** @var User $user */
$user = $this->getUser();
if ($booking->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
    throw $this->createAccessDeniedException('You can only edit your own bookings');
}
```

## Hashowanie haseł

Symfony automatycznie hashuje hasła używając algorytmu `auto` (domyślnie bcrypt):

```php
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function createUser(CreateUserDTO $dto): User
    {
        $user = new User();
        $user->setUsername($dto->username);
        
        // Hashowanie hasła
        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            $dto->password
        );
        $user->setPassword($hashedPassword);
        
        return $user;
    }
}
```

## Macierz uprawnień do endpointów

| Endpoint | Metoda | Wymagane uprawnienia | Dodatkowe warunki |
|----------|--------|---------------------|-------------------|
| `/api/login_check` | POST | PUBLIC_ACCESS | - |
| `/api/register` | POST | PUBLIC_ACCESS | - |
| `/api/me` | GET | IS_AUTHENTICATED_FULLY | - |
| `/api/token_refresh` | GET | IS_AUTHENTICATED_FULLY | - |
| `/api/users` | GET | IS_AUTHENTICATED_FULLY | - |
| `/api/users` | POST | ROLE_ADMIN | - |
| `/api/users/{id}` | GET | IS_AUTHENTICATED_FULLY | Własny profil lub ROLE_ADMIN |
| `/api/users/{id}` | PATCH | ROLE_ADMIN | - |
| `/api/users/{id}` | DELETE | ROLE_ADMIN | Nie można usunąć siebie |
| `/api/users/password_reset` | POST | PUBLIC_ACCESS | - |
| `/api/users/settings/notifications` | GET | IS_AUTHENTICATED_FULLY | Własne ustawienia |
| `/api/users/settings/notifications` | PATCH | IS_AUTHENTICATED_FULLY | Własne ustawienia |
| `/api/organizations` | GET | ROLE_ADMIN | - |
| `/api/organizations` | POST | ROLE_ADMIN | - |
| `/api/organizations/{id}` | GET | ROLE_ADMIN | - |
| `/api/organizations/{id}` | PATCH | ROLE_ADMIN | - |
| `/api/organizations/{id}` | DELETE | ROLE_ADMIN | - |
| `/api/rooms` | GET | IS_AUTHENTICATED_FULLY | Tylko sale z własnej organizacji |
| `/api/rooms` | POST | ROLE_ADMIN | - |
| `/api/rooms/{id}` | GET | IS_AUTHENTICATED_FULLY | Tylko sala z własnej organizacji |
| `/api/rooms/{id}` | PATCH | ROLE_ADMIN | Tylko sala z własnej organizacji |
| `/api/rooms/{id}` | DELETE | ROLE_ADMIN | Tylko sala z własnej organizacji |
| `/api/rooms/{id}/images` | POST | ROLE_ADMIN | Tylko sala z własnej organizacji |
| `/api/rooms/{id}/images/{path}` | DELETE | ROLE_ADMIN | Tylko sala z własnej organizacji |
| `/api/rooms/{id}/favorite` | POST | IS_AUTHENTICATED_FULLY | - |
| `/api/rooms/{id}/favorite` | DELETE | IS_AUTHENTICATED_FULLY | - |
| `/api/rooms/favorites` | GET | IS_AUTHENTICATED_FULLY | - |
| `/api/rooms/recent` | GET | IS_AUTHENTICATED_FULLY | - |
| `/api/bookings` | GET | IS_AUTHENTICATED_FULLY | Tylko rezerwacje z własnej organizacji |
| `/api/bookings` | POST | IS_AUTHENTICATED_FULLY | - |
| `/api/bookings/{id}` | GET | IS_AUTHENTICATED_FULLY | Tylko rezerwacje z własnej organizacji |
| `/api/bookings/{id}` | PATCH | IS_AUTHENTICATED_FULLY | Tylko własne rezerwacje lub ROLE_ADMIN |
| `/api/bookings/{id}` | DELETE | IS_AUTHENTICATED_FULLY | Tylko własne rezerwacje lub ROLE_ADMIN |
| `/api/bookings/my` | GET | IS_AUTHENTICATED_FULLY | Własne rezerwacje |
| `/api/bookings/recurring` | POST | IS_AUTHENTICATED_FULLY | - |
| `/api/issues` | GET | ROLE_ADMIN | - |
| `/api/issues` | POST | IS_AUTHENTICATED_FULLY | - |
| `/api/issues/{id}` | GET | IS_AUTHENTICATED_FULLY | Tylko usterki z własnej organizacji |
| `/api/issues/{id}` | PATCH | ROLE_ADMIN | - |
| `/api/issues/{id}` | DELETE | ROLE_ADMIN | - |
| `/api/issues/{id}/notes` | POST | IS_AUTHENTICATED_FULLY | - |
| `/api/send_mail` | POST | PUBLIC_ACCESS | - |
| `/api/contact_mail` | POST | PUBLIC_ACCESS | - |
| `/api/download/*` | GET | PUBLIC_ACCESS | - |

## Izolacja organizacji (Multi-tenancy)

System implementuje izolację danych na poziomie organizacji:

### Automatyczne filtrowanie

Użytkownicy widzą tylko zasoby przypisane do swojej organizacji:

```php
// Przykład w RoomRepository
public function findByOrganization(Organization $organization): array
{
    return $this->createQueryBuilder('r')
        ->where('r.organization = :organization')
        ->setParameter('organization', $organization)
        ->getQuery()
        ->getResult();
}

// W RoomService
public function getAllRooms(User $user): array
{
    return $this->roomRepository->findByOrganization(
        $user->getOrganization()
    );
}
```

### Walidacja organizacji przy tworzeniu

```php
// W BookingService
public function createBooking(CreateBookingDTO $dto, User $user): Booking
{
    $room = $this->roomRepository->find($dto->roomId);
    
    // Sprawdź czy sala należy do organizacji użytkownika
    if ($room->getOrganization() !== $user->getOrganization()) {
        throw new AccessDeniedException(
            'You can only book rooms from your organization'
        );
    }
    
    // ... tworzenie rezerwacji
}
```

## CORS (Cross-Origin Resource Sharing)

**Lokalizacja:** `config/packages/nelmio_cors.yaml`

```yaml
nelmio_cors:
    defaults:
        origin_regex: true
        allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
        allow_methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']
        allow_headers: ['Content-Type', 'Authorization']
        expose_headers: ['Link']
        max_age: 3600
```

Konfiguracja w `.env`:
```env
# Development
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'

# Production
CORS_ALLOW_ORIGIN='^https://yourdomain\.com$'
```

## Resetowanie hasła

### Proces bezpiecznego resetowania

1. **Żądanie resetu** - Użytkownik podaje email
2. **Generowanie tokena** - Unikalny token zapisywany w bazie
3. **Wysyłka emaila** - Link z tokenem do użytkownika
4. **Walidacja tokena** - Sprawdzenie ważności tokena
5. **Zmiana hasła** - Ustawienie nowego hasła
6. **Usunięcie tokena** - Unieważnienie tokena po użyciu

```php
// Generowanie tokena
$resetToken = bin2hex(random_bytes(32));
$user->setResetToken($resetToken);
$user->setResetTokenExpiresAt(
    new DateTimeImmutable('+1 hour')
);

// Walidacja
if ($user->getResetTokenExpiresAt() < new DateTimeImmutable()) {
    throw new InvalidArgumentException('Reset token has expired');
}
```

## Najlepsze praktyki bezpieczeństwa

### 1. Nigdy nie loguj tokenów JWT
```php
// ❌ ZŁE
$this->logger->info('User token: ' . $jwt);

// ✅ DOBRE
$this->logger->info('User authenticated', ['userId' => $user->getId()]);
```

### 2. Waliduj dane wejściowe
```php
use Symfony\Component\Validator\Constraints as Assert;

class CreateUserDTO
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;
    
    #[Assert\Regex(
        pattern: '/^(?=.*[A-Z])(?=.*[0-9]).{8,}$/',
        message: 'Password must be at least 8 characters with uppercase and number'
    )]
    public string $password;
}
```

### 3. Użyj prepared statements (Doctrine ORM robi to automatycznie)
```php
// ✅ DOBRE - Doctrine używa prepared statements
$user = $this->userRepository->findOneBy(['username' => $username]);

// ✅ DOBRE - QueryBuilder używa parametrów
$query = $this->createQueryBuilder('u')
    ->where('u.username = :username')
    ->setParameter('username', $username)
    ->getQuery();
```

### 4. Sprawdzaj uprawnienia na wielu poziomach
```php
// Poziom 1: Firewall (security.yaml)
// Poziom 2: Access Control (security.yaml)
// Poziom 3: Controller attribute
#[IsGranted('ROLE_ADMIN')]
public function deleteUser(string $id): Response

// Poziom 4: Service logic
if (!$this->isOwner($user, $booking) && !$this->isAdmin($user)) {
    throw new AccessDeniedException();
}
```

### 5. Używaj HTTPOnly cookies dla sesji (jeśli używane)
JWT w nagłówku Authorization jest bezpieczniejszy niż cookie, ale jeśli używasz cookies:
```php
// W konfiguracji
framework:
    session:
        cookie_secure: true
        cookie_httponly: true
        cookie_samesite: 'lax'
```

## Rate Limiting

Aktualnie projekt nie implementuje rate limiting. Zalecane jest dodanie tego na poziomie:
- Reverse proxy (nginx)
- API Gateway
- Bundle Symfony (np. symfony/rate-limiter)

## Audyt bezpieczeństwa

### Zalecane narzędzia

1. **Symfony Security Checker**
```bash
symfony security:check
```

2. **Composer Audit**
```bash
composer audit
```

3. **PHP CodeSniffer**
```bash
composer phpcs
```

## Podsumowanie

RoomCtrl API implementuje wielowarstwowy model bezpieczeństwa:
- ✅ JWT authentication
- ✅ Role-based access control (RBAC)
- ✅ Organization isolation (multi-tenancy)
- ✅ Password hashing
- ✅ Input validation
- ✅ CORS protection
- ✅ Secure password reset
- ✅ User checker dla aktywności konta
