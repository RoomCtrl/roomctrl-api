# Przegląd projektu RoomCtrl API

## Cel projektu

RoomCtrl API to system backendowy do zarządzania salami konferencyjnymi w organizacjach. Umożliwia:
- Zarządzanie katalogiem sal konferencyjnych
- Rezerwacje sal przez użytkowników
- Zgłaszanie i śledzenie usterek
- Administrację użytkownikami i organizacjami
- Zarządzanie powiadomieniami email

## Wersje technologii

| Technologia | Wersja | Uwagi |
|------------|--------|-------|
| PHP | ≥8.4 | Wymagana wersja minimalna |
| Symfony | 7.4.* | Framework aplikacji |
| PostgreSQL | 18 | Baza danych |
| Doctrine ORM | 3.5 | Warstwa dostępu do danych |
| Doctrine DBAL | 4.3 | Abstrakcja bazy danych |
| Lexik JWT Bundle | 3.1 | Uwierzytelnianie JWT |
| Nelmio API Doc | 5.4 | Dokumentacja OpenAPI |
| Nelmio CORS | 2.5 | Obsługa CORS |

## Wymagania systemowe

### PHP Extensions
- `ext-ctype`
- `ext-iconv`
- `ext-pdo_pgsql`
- `ext-fileinfo`

### Narzędzia deweloperskie (opcjonalnie)
- Composer 2.x
- PHP CodeSniffer (phpcs/phpcbf)
- Faker PHP (do fixtures)
- Symfony Maker Bundle (do generowania kodu)

## Architektura wysokiego poziomu

```
┌─────────────────────────────────────────────┐
│           Client Applications               │
│    (Web, Mobile iOS/Android)                │
└──────────────┬──────────────────────────────┘
               │ HTTP/JSON + JWT
               ▼
┌─────────────────────────────────────────────┐
│          RoomCtrl API (Symfony)             │
│  ┌─────────────────────────────────────┐    │
│  │    Security Layer (JWT Auth)        │    │
│  └──────────────┬──────────────────────┘    │
│                 ▼                           │
│  ┌─────────────────────────────────────┐    │
│  │       Feature Modules               │    │
│  │  • Auth    • Rooms    • Bookings    │    │
│  │  • Users   • Issues   • Orgs        │    │
│  │  • Mail    • Download               │    │
│  └──────────────┬──────────────────────┘    │
│                 ▼                           │
│  ┌─────────────────────────────────────┐    │
│  │    Doctrine ORM / Repositories      │    │
│  └──────────────┬──────────────────────┘    │
└─────────────────┼───────────────────────────┘
                  ▼
         ┌───────────────────┐
         │   PostgreSQL 18   │
         └───────────────────┘
```

## Struktura katalogów

```
roomctrl-api/
├── bin/                    # Skrypty wykonywalne (console)
├── config/                 # Konfiguracja aplikacji
│   ├── jwt/               # Klucze JWT
│   ├── packages/          # Konfiguracja bundli
│   └── routes/            # Routing
├── migrations/            # Migracje bazy danych
├── public/                # Punkt wejścia (index.php)
│   ├── uploads/          # Uploaded pliki
│   └── android/ios/      # Pliki mobilne
├── src/                   # Kod źródłowy
│   ├── Common/           # Wspólne utility
│   ├── Feature/          # Moduły funkcjonalne
│   │   ├── Auth/
│   │   ├── Booking/
│   │   ├── Download/
│   │   ├── Issue/
│   │   ├── Mail/
│   │   ├── Organization/
│   │   ├── Room/
│   │   └── User/
│   └── Kernel.php        # Kernel aplikacji
├── templates/             # Szablony Twig (emaile)
├── tests/                 # Testy
├── var/                   # Pliki tymczasowe (cache, logs)
├── vendor/                # Zależności Composer
├── .env.example          # Przykładowa konfiguracja
├── composer.json         # Zależności PHP
└── database.sh           # Skrypt resetowania bazy
```

## Moduły funkcjonalne (Features)

Projekt wykorzystuje architekturę opartą na modułach funkcjonalnych (Feature-based architecture):

| Moduł | Opis | Główne funkcje |
|-------|------|---------------|
| **Auth** | Uwierzytelnianie | Login, token refresh, informacje o użytkowniku |
| **User** | Zarządzanie użytkownikami | CRUD użytkowników, reset hasła, ustawienia powiadomień |
| **Organization** | Zarządzanie organizacjami | CRUD organizacji |
| **Room** | Zarządzanie salami | CRUD sal, wyposażenie, statusy, ulubione, upload zdjęć |
| **Booking** | Rezerwacje | Tworzenie, edycja, anulowanie rezerwacji, powiadomienia |
| **Issue** | Usterki | Zgłaszanie, śledzenie, notatki, historia zmian |
| **Mail** | Powiadomienia | Wysyłka emaili, formularze kontaktowe |
| **Download** | Pobieranie plików | Udostępnianie plików mobilnych |

Każdy moduł zawiera:
- **Controller/** - Kontrolery (endpointy API)
- **Entity/** - Encje Doctrine
- **Repository/** - Repozytoria do zapytań
- **Service/** - Logika biznesowa
- **DTO/** - Data Transfer Objects (request/response)
- **EventListener/** (opcjonalnie) - Listenery zdarzeń
- **DataFixtures/** (opcjonalnie) - Dane testowe

## Model uprawnień

System wykorzystuje rolowy model dostępu (RBAC):

- **PUBLIC_ACCESS** - Dostępne bez uwierzytelniania (login, register, reset hasła)
- **IS_AUTHENTICATED_FULLY** - Wymaga zalogowania (większość endpointów)
- **ROLE_ADMIN** - Wymaga roli administratora (zarządzanie zasobami)

Szczegóły w dokumencie [06-security.md](06-security.md).

## Przepływ danych

1. **Request** → Client wysyła żądanie HTTP z JWT w nagłówku `Authorization: Bearer <token>`
2. **Authentication** → Symfony Security weryfikuje token JWT
3. **Authorization** → Sprawdzane są uprawnienia użytkownika (#[IsGranted])
4. **Controller** → Odbiera request, waliduje dane (DTO + Validator)
5. **Service** → Przetwarza logikę biznesową
6. **Repository** → Wykonuje operacje na bazie danych
7. **Response** → Zwraca JSON z danymi lub kodem błędu

## Standardy kodowania

- **PSR-12** - Standard kodowania PHP
- **PHPDoc** - Dokumentacja kodu
- **Type hints** - Ścisłe typowanie
- **Strict types** - `declare(strict_types=1)`
- **Code style** - Sprawdzany przez PHP_CodeSniffer (phpcs.xml)

## Konwencje nazewnictwa

- **Klasy**: PascalCase (np. `UserController`, `BookingService`)
- **Metody**: camelCase (np. `getAllUsers`, `createBooking`)
- **Zmienne**: camelCase (np. `$userId`, `$bookingData`)
- **Tabele bazy**: snake_case (np. `users`, `room_issues`)
- **Kolumny bazy**: snake_case (np. `first_name`, `created_at`)
- **Routes**: snake_case (np. `users_list`, `bookings_create`)
