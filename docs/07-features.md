# Moduły funkcjonalne (Features)

## Przegląd modułów

Projekt wykorzystuje architekturę feature-based, gdzie każdy moduł reprezentuje kompletną funkcjonalność biznesową.

## 1. Auth (Uwierzytelnianie)

**Lokalizacja:** `src/Feature/Auth/`

### Odpowiedzialność
- Logowanie użytkowników
- Generowanie tokenów JWT
- Odświeżanie tokenów
- Rejestracja nowych użytkowników
- Informacje o zalogowanym użytkowniku

### Struktura
```
Auth/
├── Controller/
│   └── AuthController.php
├── Service/
│   ├── AuthServiceInterface.php
│   └── AuthService.php
├── DTO/
│   ├── RegisterRequestDTO.php
│   └── RegisterResponseDTO.php
├── EventListener/
│   └── CheckAuthenticationDataListener.php
└── Repository/
    (używa UserRepository z modułu User)
```

### Kluczowe funkcje

#### AuthController
- `POST /api/login_check` - Logowanie (obsługiwane przez Lexik JWT)
- `GET /api/me` - Dane zalogowanego użytkownika
- `GET /api/token_refresh` - Odświeżenie tokena
- `POST /api/register` - Rejestracja nowego użytkownika

#### AuthService
```php
public function getCurrentUserInfo(User $user, bool $withOrganization): array
{
    $data = [
        'id' => $user->getId(),
        'username' => $user->getUsername(),
        'roles' => $user->getRoles(),
        'firstName' => $user->getFirstName(),
        'lastName' => $user->getLastName(),
        'email' => $user->getEmail(),
        'phone' => $user->getPhone(),
    ];
    
    if ($withOrganization) {
        $data['organization'] = /* dane organizacji */;
    }
    
    return $data;
}

public function registerUser(RegisterRequestDTO $dto): User
{
    // Walidacja, tworzenie użytkownika, wysyłka powitalnego emaila
}
```

#### CheckAuthenticationDataListener
Event listener sprawdzający poprawność danych uwierzytelniających przed procesem logowania.

---

## 2. User (Użytkownicy)

**Lokalizacja:** `src/Feature/User/`

### Odpowiedzialność
- Zarządzanie kontami użytkowników (CRUD)
- Reset hasła
- Ustawienia powiadomień email
- Zarządzanie profilem użytkownika

### Struktura
```
User/
├── Controller/
│   └── UserController.php
├── Service/
│   ├── UserServiceInterface.php
│   └── UserService.php
├── Entity/
│   └── User.php
├── Repository/
│   └── UserRepository.php
├── Security/
│   └── UserChecker.php
└── DTO/
    ├── CreateUserDTO.php
    ├── UpdateUserDTO.php
    ├── PasswordResetRequestDTO.php
    ├── PasswordResetConfirmDTO.php
    ├── UpdateNotificationSettingsDTO.php
    └── ...
```

### Kluczowe funkcje

#### UserController
- `GET /api/users` - Lista użytkowników (IS_AUTHENTICATED_FULLY)
- `GET /api/users/{id}` - Szczegóły użytkownika
- `POST /api/users` - Utworzenie użytkownika (ROLE_ADMIN)
- `PATCH /api/users/{id}` - Aktualizacja użytkownika (ROLE_ADMIN)
- `DELETE /api/users/{id}` - Usunięcie użytkownika (ROLE_ADMIN)
- `POST /api/users/password_reset` - Żądanie resetu hasła
- `POST /api/users/password_reset/confirm` - Potwierdzenie resetu hasła
- `GET /api/users/settings/notifications` - Pobierz ustawienia powiadomień
- `PATCH /api/users/settings/notifications` - Zmień ustawienia powiadomień

#### UserService
```php
public function getAllUsers(bool $withDetails, Organization $organization): array
{
    // Pobiera użytkowników z organizacji z opcjonalnymi szczegółami
}

public function createUser(CreateUserDTO $dto): User
{
    // Hashowanie hasła, przypisanie organizacji, wysyłka powitalnego emaila
}

public function requestPasswordReset(string $email): void
{
    // Generowanie tokenu resetującego, wysyłka emaila
}

public function confirmPasswordReset(string $token, string $newPassword): void
{
    // Walidacja tokenu, zmiana hasła
}
```

#### UserChecker
Implementacja `UserCheckerInterface` sprawdzająca czy użytkownik jest aktywny:
```php
public function checkPreAuth(UserInterface $user): void
{
    if (!$user->isActive()) {
        throw new CustomUserMessageAccountStatusException(
            'Your account is inactive'
        );
    }
}
```

---

## 3. Organization (Organizacje)

**Lokalizacja:** `src/Feature/Organization/`

### Odpowiedzialność
- Zarządzanie organizacjami/firmami
- CRUD organizacji
- Izolacja danych między organizacjami

### Struktura
```
Organization/
├── Controller/
│   └── OrganizationController.php
├── Service/
│   ├── OrganizationServiceInterface.php
│   └── OrganizationService.php
├── Entity/
│   └── Organization.php
├── Repository/
│   └── OrganizationRepository.php
├── DataFixtures/
│   └── OrganizationFixtures.php
└── DTO/
    ├── CreateOrganizationDTO.php
    ├── UpdateOrganizationDTO.php
    └── ...
```

### Kluczowe funkcje

#### OrganizationController
- `GET /api/organizations` - Lista organizacji (ROLE_ADMIN)
- `GET /api/organizations/{id}` - Szczegóły organizacji (ROLE_ADMIN)
- `POST /api/organizations` - Utworzenie organizacji (ROLE_ADMIN)
- `PATCH /api/organizations/{id}` - Aktualizacja organizacji (ROLE_ADMIN)
- `DELETE /api/organizations/{id}` - Usunięcie organizacji (ROLE_ADMIN)

#### OrganizationService
```php
public function getAllOrganizations(): array
{
    return $this->organizationRepository->findAll();
}

public function createOrganization(CreateOrganizationDTO $dto): Organization
{
    $organization = new Organization();
    $organization->setRegon($dto->regon);
    $organization->setName($dto->name);
    $organization->setEmail($dto->email);
    
    $this->entityManager->persist($organization);
    $this->entityManager->flush();
    
    return $organization;
}
```

---

## 4. Room (Sale konferencyjne)

**Lokalizacja:** `src/Feature/Room/`

### Odpowiedzialność
- Zarządzanie salami konferencyjnymi
- Wyposażenie sal
- Statusy dostępności
- Ulubione sale użytkowników
- Upload zdjęć sal (JPG, PNG, PDF)

### Struktura
```
Room/
├── Controller/
│   └── RoomController.php
├── Service/
│   ├── RoomServiceInterface.php
│   ├── RoomService.php
│   └── FileUploadService.php
├── Entity/
│   ├── Room.php
│   ├── RoomStatus.php
│   └── Equipment.php
├── Repository/
│   ├── RoomRepository.php
│   ├── RoomStatusRepository.php
│   └── EquipmentRepository.php
└── DTO/
    ├── CreateRoomRequest.php
    ├── UpdateRoomRequest.php
    ├── ImageUploadResponseDTO.php
    └── ...
```

### Kluczowe funkcje

#### RoomController
- `GET /api/rooms` - Lista sal z opcjonalnymi filtrami
- `GET /api/rooms/{id}` - Szczegóły sali
- `POST /api/rooms` - Utworzenie sali (ROLE_ADMIN)
- `PATCH /api/rooms/{id}` - Aktualizacja sali (ROLE_ADMIN)
- `DELETE /api/rooms/{id}` - Usunięcie sali (ROLE_ADMIN)
- `POST /api/rooms/{id}/images` - Upload zdjęcia (ROLE_ADMIN)
- `DELETE /api/rooms/{roomId}/images/{imagePath}` - Usunięcie zdjęcia (ROLE_ADMIN)
- `GET /api/rooms/{roomId}/images` - Lista zdjęć
- `POST /api/rooms/{id}/favorite` - Dodaj do ulubionych
- `DELETE /api/rooms/{id}/favorite` - Usuń z ulubionych
- `GET /api/rooms/favorites` - Lista ulubionych sal

#### RoomService
```php
public function getAllRooms(
    User $user,
    ?string $status = null,
    bool $withBookings = false
): array
{
    $rooms = $this->roomRepository->findByOrganization(
        $user->getOrganization(),
        $status
    );
    
    if ($withBookings) {
        // Dodaj informacje o bieżących i przyszłych rezerwacjach
    }
    
    return $rooms;
}

public function createRoom(CreateRoomRequest $dto, User $user): Room
{
    // Walidacja, tworzenie sali, wyposażenia, statusu
}

public function toggleFavorite(Room $room, User $user): bool
{
    if ($user->getFavoriteRooms()->contains($room)) {
        $user->removeFavoriteRoom($room);
        return false;
    } else {
        $user->addFavoriteRoom($room);
        return true;
    }
}
```

#### FileUploadService

Obsługuje upload i usuwanie plików (zdjęcia sal).

**Obsługiwane typy MIME:**
- `image/jpeg`, `image/jpg` → `.jpg`
- `image/png` → `.png`
- `application/pdf` → `.pdf`

**Metody:**

```php
public function isValidFileType(UploadedFile $file): bool
{
    // Sprawdza czy plik jest dozwolonym typem
}

public function uploadFiles(array $files, string $identifier): array
{
    // Wysyła pliki, zwraca tablicę względnych ścieżek:
    // ['/uploads/rooms/roomId_timestamp_uniqid.jpg']
}

public function deleteFile(string $relativePath, string $projectDir): bool
{
    // Usuwa plik z filesystem'u
    // $relativePath np. '/uploads/rooms/file.jpg'
}
```

**Struktura folderów:**
```
public/
├── uploads/
│   └── rooms/          # Wszystkie zdjęcia sal
│       ├── room-uuid_time_id.jpg
│       ├── room-uuid_time_id.png
│       └── ...
├── android/            # Aplikacja Android (APK)
└── ios/                # Aplikacja iOS (IPA)
```

**Konwencja nazewnictwa plików:**
```
{roomId}_{timestamp}_{uniqid}.{extension}
```

---

## 5. Booking (Rezerwacje)

**Lokalizacja:** `src/Feature/Booking/`

### Odpowiedzialność
- Zarządzanie rezerwacjami sal
- Tworzenie pojedynczych rezerwacji
- Tworzenie cyklicznych rezerwacji (sprzątanie/konserwacja)
- Sprawdzanie dostępności sal
- Powiadomienia email o rezerwacjach
- Zarządzanie uczestnikami rezerwacji

### Struktura
```
Booking/
├── Controller/
│   └── BookingController.php
├── Service/
│   ├── BookingServiceInterface.php
│   └── BookingService.php
├── Entity/
│   └── Booking.php
├── Repository/
│   └── BookingRepository.php
└── DTO/
    ├── CreateBookingDTO.php
    ├── UpdateBookingDTO.php
    ├── CreateRecurringBookingDTO.php
    ├── BookingResponseDTO.php
    └── ...
```

### Kluczowe funkcje

#### BookingController
- `GET /api/bookings` - Lista wszystkich rezerwacji (query param: ?myBookings=true dla własnych)
- `GET /api/bookings/{id}` - Szczegóły rezerwacji
- `POST /api/bookings` - Utworzenie rezerwacji
- `POST /api/bookings/recurring` - Utworzenie cyklicznych rezerwacji sprzątania/konserwacji (ADMIN)
- `PATCH /api/bookings/{id}` - Aktualizacja rezerwacji (tylko twórca)
- `DELETE /api/bookings/{id}` - Anulowanie rezerwacji (tylko twórca lub admin)

#### BookingService
```php
public function createBooking(CreateBookingDTO $dto, User $user): Booking
{
    // Walidacja czasów
    $this->validateBookingTime($dto);
    
    // Sprawdzenie dostępności sali
    if (!$this->isRoomAvailable($dto->roomId, $dto->startedAt, $dto->endedAt)) {
        throw new ConflictException('Room is already booked for this time');
    }
    
    // Tworzenie rezerwacji
    $booking = new Booking();
    $booking->setTitle($dto->title);
    $booking->setRoom($room);
    $booking->setUser($user);
    // ...
    
    // Dodanie uczestników
    foreach ($dto->participantIds as $participantId) {
        $participant = $this->userRepository->find($participantId);
        $booking->addParticipant($participant);
    }
    
    $this->entityManager->persist($booking);
    $this->entityManager->flush();
    
    // Wysyłka powiadomień
    $this->mailService->sendBookingConfirmation($booking);
    $this->mailService->sendParticipantInvitations($booking);
    
    return $booking;
}

public function createRecurringBooking(
    Room $room,
    User $user,
    string $type,
    string $startTime,
    string $endTime,
    array $daysOfWeek,
    int $weeksAhead
): RecurringBookingResultDTO
{
    $bookings = [];
    $now = new DateTimeImmutable();
    
    // Tytuł na podstawie typu
    $title = $type === 'cleaning' ? 'Sprzątanie' : 'Konserwacja';
    
    // Tworzenie rezerwacji dla każdego dnia tygodnia przez określoną liczbę tygodni
    for ($week = 0; $week < $weeksAhead; $week++) {
        foreach ($daysOfWeek as $dayOfWeek) {
            $date = $this->calculateDate($now, $week, $dayOfWeek);
            $startedAt = $date->modify($startTime);
            $endedAt = $date->modify($endTime);
            
            // Sprawdzenie dostępności
            if ($this->isRoomAvailable($room->getId(), $startedAt, $endedAt)) {
                $booking = $this->createSingleBooking(
                    $room,
                    $user,
                    $title,
                    $startedAt,
                    $endedAt
                );
                $bookings[] = $booking;
            }
        }
    }
    
    return new RecurringBookingResultDTO(
        count($bookings),
        array_map(fn($b) => $b->getId()->toRfc4122(), $bookings)
    );
}

private function isRoomAvailable(
    string $roomId,
    string $startedAt,
    string $endedAt,
    ?string $excludeBookingId = null
): bool
{
    return $this->bookingRepository->checkAvailability(
        $roomId,
        $startedAt,
        $endedAt,
        $excludeBookingId
    );
}
```

#### BookingRepository
```php
public function checkAvailability(
    string $roomId,
    string $startedAt,
    string $endedAt,
    ?string $excludeBookingId = null
): bool
{
    $qb = $this->createQueryBuilder('b')
        ->where('b.room = :roomId')
        ->andWhere('b.status = :status')
        ->andWhere(
            '(b.startedAt < :endedAt AND b.endedAt > :startedAt)'
        )
        ->setParameter('roomId', $roomId)
        ->setParameter('status', 'active')
        ->setParameter('startedAt', new DateTimeImmutable($startedAt))
        ->setParameter('endedAt', new DateTimeImmutable($endedAt));
    
    if ($excludeBookingId) {
        $qb->andWhere('b.id != :excludeId')
           ->setParameter('excludeId', $excludeBookingId);
    }
    
    $conflicts = $qb->getQuery()->getResult();
    
    return count($conflicts) === 0;
}
```

### Automatyczna aktualizacja statusów

System zawiera mechanizm automatycznej aktualizacji statusów rezerwacji z `active` na `completed` po zakończeniu czasu rezerwacji.

#### Command
```bash
php bin/console app:booking:update-status
```

Znajduje wszystkie rezerwacje ze statusem `active`, których `endedAt < now` i zmienia ich status na `completed`.

**Konfiguracja cron (co 5 minut):**
```cron
*/5 * * * * cd /sciezka/do/projektu && php bin/console app:booking:update-status
```

**Więcej informacji:** Zobacz [dokumentację aktualizacji statusów](10-booking-status-update.md)

---

## 6. Issue (Usterki)

**Lokalizacja:** `src/Feature/Issue/`

### Odpowiedzialność
- Zgłaszanie usterek w salach
- Śledzenie statusu usterek
- Notatki do usterek
- Historia zmian (audit log)
- Zarządzanie priorytetami

### Struktura
```
Issue/
├── Controller/
│   └── IssueController.php
├── Service/
│   ├── IssueServiceInterface.php
│   └── IssueService.php
├── Entity/
│   ├── RoomIssue.php
│   ├── IssueNote.php
│   └── IssueHistory.php
├── Repository/
│   ├── RoomIssueRepository.php
│   ├── IssueNoteRepository.php
│   └── IssueHistoryRepository.php
├── DataFixtures/
│   └── IssueFixtures.php
└── DTO/
    ├── CreateIssueDTO.php
    ├── UpdateIssueDTO.php
    ├── CreateNoteDTO.php
    └── ...
```

### Kluczowe funkcje

#### IssueController
- `GET /api/issues` - Lista usterek (ROLE_ADMIN)
- `GET /api/issues/{id}` - Szczegóły usterki
- `POST /api/issues` - Zgłoszenie usterki
- `PATCH /api/issues/{id}` - Aktualizacja usterki (ROLE_ADMIN)
- `DELETE /api/issues/{id}` - Usunięcie usterki (ROLE_ADMIN)
- `POST /api/issues/{id}/notes` - Dodanie notatki

#### IssueService
```php
public function createIssue(CreateIssueDTO $dto, User $user): RoomIssue
{
    $issue = new RoomIssue();
    $issue->setRoom($room);
    $issue->setReporter($user);
    $issue->setOrganization($user->getOrganization());
    $issue->setCategory($dto->category);
    $issue->setDescription($dto->description);
    $issue->setPriority($dto->priority);
    $issue->setStatus('pending');
    
    $this->entityManager->persist($issue);
    $this->entityManager->flush();
    
    // Powiadomienie adminów
    $this->mailService->notifyAdminsAboutIssue($issue);
    
    return $issue;
}

public function updateIssue(
    RoomIssue $issue,
    UpdateIssueDTO $dto,
    User $user
): void
{
    $changes = [];
    
    if ($dto->status && $dto->status !== $issue->getStatus()) {
        $changes[] = [
            'field' => 'status',
            'old' => $issue->getStatus(),
            'new' => $dto->status
        ];
        $issue->setStatus($dto->status);
        
        if ($dto->status === 'closed') {
            $issue->setClosedAt(new DateTimeImmutable());
        }
    }
    
    if ($dto->priority && $dto->priority !== $issue->getPriority()) {
        $changes[] = [
            'field' => 'priority',
            'old' => $issue->getPriority(),
            'new' => $dto->priority
        ];
        $issue->setPriority($dto->priority);
    }
    
    $this->entityManager->flush();
    
    // Zapisz historię zmian
    foreach ($changes as $change) {
        $this->recordHistory($issue, $user, $change);
    }
}

public function addNote(RoomIssue $issue, string $content, User $author): IssueNote
{
    $note = new IssueNote();
    $note->setIssue($issue);
    $note->setAuthor($author);
    $note->setContent($content);
    
    $this->entityManager->persist($note);
    $this->entityManager->flush();
    
    return $note;
}

private function recordHistory(
    RoomIssue $issue,
    User $user,
    array $change
): void
{
    $history = new IssueHistory();
    $history->setIssue($issue);
    $history->setChangedBy($user);
    $history->setFieldChanged($change['field']);
    $history->setOldValue($change['old']);
    $history->setNewValue($change['new']);
    
    $this->entityManager->persist($history);
}
```

---

## 7. Mail (Powiadomienia)

**Lokalizacja:** `src/Feature/Mail/`

### Odpowiedzialność
- Wysyłka powiadomień email
- Szablony email (Twig)
- Formularze kontaktowe
- Potwierdzenia rezerwacji
- Zaproszenia uczestników
- Reset hasła
- Powitalny email

### Struktura
```
Mail/
├── Controller/
│   └── MailController.php
└── Service/
    ├── MailServiceInterface.php
    └── MailService.php
```

Szablony email: `templates/emails/`
- `booking_confirmation.html.twig`
- `participant_invitation.html.twig`
- `password_reset.html.twig`
- `welcome_email.html.twig`
- `standard_email.html.twig`

### Kluczowe funkcje

#### MailController
- `POST /api/send_mail` - Wysyłka dowolnego emaila (PUBLIC)
- `POST /api/contact_mail` - Formularz kontaktowy (PUBLIC)

#### MailService

Obsługuje wysyłkę emaili z walidacją i sanityzacją danych.

**Metody publiczne:**

```php
public function validateMailFields(
    array $data,
    array $requiredFields,
    string $emailField = 'email'
): ?array
{
    // Sprawdza wymagane pola i format email
    // Zwraca null jeśli OK, lub array z błędem
}

public function sendEmail(array $data): MailSentResponseDTO
{
    // Wysyła email bez szablonu
    // Wymaga: to, subject, content
}

public function sendContactFormEmail(array $data): ContactFormSentResponseDTO
{
    // Wysyła email z formularza kontaktowego
    // Wymaga: subject, content
    // Wysyła na: roomctrlinfo@gmail.com
}
```

**Response DTOs:**

```php
class MailSentResponseDTO
{
    public int $code = 200;
    public string $message = 'Email sent successfully';
}

class ContactFormSentResponseDTO
{
    public int $code = 200;
    public string $message = 'Contact form submitted successfully';
}

class FileNotFoundResponseDTO
{
    public int $code = 404;
    public string $message;
    
    public function __construct(string $message)
    {
        $this->message = $message;
    }
}
```

**Wewnętrzne funkcje:**

```php
private function sanitizeMailField(string $value): string
{
    // Sanityzuje dane email'a (strip_tags, trim, htmlspecialchars)
}

private function renderEmailTemplate(
    string $content,
    string $subject
): string
{
    // Renderuje szablon Twig dla zawartości emaila
}

private function formatContactMessage(array $data): string
{
    // Formatuje wiadomość z formularza kontaktowego
}
```

**Szablony email'i (Twig):**

Wszystkie szablony znajdują się w `templates/emails/`:
- `booking_confirmation.html.twig` - Potwierdzenie rezerwacji
- `participant_invitation.html.twig` - Zaproszenie dla uczestnika
- `password_reset.html.twig` - Link do resetowania hasła
- `welcome_email.html.twig` - Email powitalny dla nowego użytkownika
- `standard_email.html.twig` - Szablon standardowy

---

## 8. Download (Pobieranie plików)

**Lokalizacja:** `src/Feature/Download/`

### Odpowiedzialność
- Udostępnianie plików mobilnych (APK, IPA)
- Download aplikacji Android
- Download aplikacji iOS
- Obsługa błędów (brak pliku)

### Struktura
```
Download/
├── Controller/
│   └── DownloadController.php
├── Service/
│   ├── DownloadServiceInterface.php
│   └── DownloadService.php
└── DTO/
    └── FileNotFoundResponseDTO.php
```

### Kluczowe funkcje

#### DownloadController (PUBLIC endpoints)
- `GET /api/download/android` - Pobierz APK
- `GET /api/download/ios` - Pobierz IPA

**Odpowiedzi:**
- **200 OK** - Zwraca plik binarny (BinaryFileResponse)
- **404 Not Found** - JSON z informacją o błędzie (FileNotFoundResponseDTO)

#### DownloadService

```php
public function getAndroidFile(): BinaryFileResponse
{
    // Wyszukuje pierwszy plik w katalogu public/android/
    // Jeśli nie znaleziony -> InvalidArgumentException
    // BinaryFileResponse zawiera nagłówki Cache-Control i Pragma
}

public function getIosFile(): BinaryFileResponse
{
    // Wyszukuje pierwszy plik w katalogu public/ios/
    // Jeśli nie znaleziony -> InvalidArgumentException
    // BinaryFileResponse zawiera nagłówki Cache-Control i Pragma
}

private function findFileInDirectory(string $directory): string
{
    // Szuka dowolnego pliku w katalogu
    // Zwraca ścieżkę do pierwszego znalezionego pliku
}
```

**Struktura katalogów dla aplikacji:**
```
public/
├── android/
│   ├── roomctrl-latest.apk     # Najnowsza wersja (lub inny format)
│   └── roomctrl-1.0.0.apk      # Wersjonowane (opcjonalnie)
└── ios/
    ├── roomctrl-latest.ipa      # Najnowsza wersja (lub inny format)
    └── roomctrl-1.0.0.ipa       # Wersjonowane (opcjonalnie)
```

**Notatka:** System pobiera **pierwszy plik** znaleziony w katalogu, niezależnie od wersji czy nazwy.

---

## Moduł Common (Wspólne komponenty)

**Lokalizacja:** `src/Common/`

### EventListener/ExceptionListener.php
Globalny listener konwertujący wyjątki na odpowiedzi JSON:

```php
public function onKernelException(ExceptionEvent $event): void
{
    $exception = $event->getThrowable();
    
    $statusCode = $this->getStatusCode($exception);
    
    $response = new JsonResponse([
        'code' => $statusCode,
        'message' => $exception->getMessage()
    ], $statusCode);
    
    $event->setResponse($response);
}
```

### Utility/ValidationErrorFormatter.php
Formatowanie błędów walidacji Symfony Validator:

```php
public static function format(ConstraintViolationListInterface $errors): array
{
    $formattedErrors = [];
    
    foreach ($errors as $error) {
        $formattedErrors[$error->getPropertyPath()] = $error->getMessage();
    }
    
    return [
        'code' => Response::HTTP_BAD_REQUEST,
        'message' => 'Validation failed',
        'errors' => $formattedErrors
    ];
}
```

---

## Data Fixtures

Moduły zawierają fixtures do załadowania danych testowych:

- `Organization/DataFixtures/OrganizationFixtures.php`
- `Issue/DataFixtures/IssueFixtures.php`

Fixtures są ładowane za pomocą:
```bash
php bin/console doctrine:fixtures:load
```

---

## Podsumowanie zależności między modułami

```
Auth
├── używa: User, Organization, Mail
└── używany przez: -

User
├── używa: Organization, Mail
└── używany przez: Auth, Room, Booking, Issue

Organization
├── używa: -
└── używany przez: User, Room, Issue

Room
├── używa: Organization, User, Booking, Issue
└── używany przez: Booking, Issue

Booking
├── używa: Room, User, Mail
└── używany przez: -

Issue
├── używa: Room, User, Organization, Mail
└── używany przez: -

Mail
├── używa: -
└── używany przez: Auth, User, Booking, Issue

Download
├── używa: -
└── używany przez: -
```

Moduły są luźno powiązane poprzez interfejsy serwisów, co ułatwia testowanie i rozbudowę.
