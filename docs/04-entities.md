# Model danych (Entities)

## Przegląd encji

System RoomCtrl API składa się z 9 głównych encji:

| Encja | Tabela | Opis | Relacje |
|-------|--------|------|---------|
| **User** | users | Użytkownicy systemu | ManyToOne → Organization, ManyToMany → Room (favorites) |
| **Organization** | organizations | Organizacje | OneToMany → User |
| **Room** | rooms | Sale konferencyjne | ManyToOne → Organization, OneToMany → Equipment, OneToOne → RoomStatus |
| **RoomStatus** | room_status | Status dostępności sali | OneToOne → Room |
| **Equipment** | equipment | Wyposażenie sal | ManyToOne → Room |
| **Booking** | bookings | Rezerwacje sal | ManyToOne → Room, ManyToOne → User, ManyToMany → User (participants) |
| **RoomIssue** | room_issues | Zgłoszenia usterek | ManyToOne → Room, ManyToOne → User, ManyToOne → Organization |
| **IssueNote** | issue_notes | Notatki do usterek | ManyToOne → RoomIssue, ManyToOne → User |
| **IssueHistory** | issue_history | Historia zmian usterek | ManyToOne → RoomIssue, ManyToOne → User |

## Diagram relacji ERD

```
┌──────────────────┐         ┌──────────────────┐
│  Organization    │◄────────┤      User        │
│                  │ 1     * │                  │
│  - id (uuid)     │         │  - id (uuid)     │
│  - regon         │         │  - username      │
│  - name          │         │  - password      │
│  - email         │         │  - roles[]       │
└────────┬─────────┘         │  - firstName     │
         │                   │  - lastName      │
         │ *                 │  - email         │
         │                   │  - phone         │
         │                   │  - isActive      │
         │                   │  - organization  │
         │                   └─────┬──────┬─────┘
         │                         │      │
         │                         │      │ * (favorites)
         │                         │      │
         │                         │      │
         │                         │  ┌───▼──────────┐
         │                         │  │     Room     │
         │                         │  │              │
         │                       * │  │ - id (uuid)  │
    ┌────▼───────────┐            │  │ - roomName   │
    │   RoomIssue    │            │  │ - capacity   │
    │                │            │  │ - size       │
    │ - id (uuid)    │◄───────────┘  │ - location   │
    │ - category     │ 1          *  │ - access     │
    │ - description  │               │ - ...        │
    │ - status       │               └──┬────┬──────┘
    │ - priority     │                  │    │
    │ - reporter     │                  │    │ 1
    │ - organization │                  │    │
    └────┬───────────┘                  │    │
         │                              │ *  │
         │ 1                            │    │
         │                              │  ┌─▼───────────┐
         │                              │  │ RoomStatus  │
         │                              │  │             │
    ┌────▼───────────┐            ┌────▼──┤ - id        │
    │  IssueNote     │            │Equip. │ - status    │
    │                │            │       │ - room      │
    │ - id (uuid)    │            │- name │ - ...       │
    │ - content      │            │- cat. │             │
    │ - author       │            │- qty  │             │
    │ - issue        │            │- room │             │
    └────────────────┘            └───────┘             │
                                                         │
    ┌────────────────┐                                  │
    │ IssueHistory   │                                  │
    │                │                                  │
    │ - id (uuid)    │                               *  │
    │ - issue        │                          ┌───────▼────────┐
    │ - changedBy    │                          │    Booking     │
    │ - fieldChanged │                          │                │
    │ - oldValue     │                          │ - id (uuid)    │
    │ - newValue     │                          │ - title        │
    │ - createdAt    │                          │ - startedAt    │
    └────────────────┘                          │ - endedAt      │
                                                │ - status       │
                                                │ - room         │
                                                │ - user         │
                                                │ - participants │
                                                └────────────────┘
```

## Szczegółowe opisy encji

### 1. User (Użytkownik)

**Lokalizacja:** `src/Feature/User/Entity/User.php`  
**Tabela:** `users`

Reprezentuje użytkowników systemu z pełną funkcjonalnością uwierzytelniania Symfony Security.

#### Pola:

| Pole | Typ | Opis | Wymagane | Unikalne |
|------|-----|------|----------|----------|
| id | UUID | Identyfikator | Auto | Tak |
| username | string(180) | Nazwa użytkownika | Tak | Tak |
| password | string | Zahashowane hasło | Tak | Nie |
| roles | json | Role użytkownika | Tak | Nie |
| firstName | string(255) | Imię | Tak | Nie |
| lastName | string(255) | Nazwisko | Tak | Nie |
| email | string(100) | Email | Tak | Tak |
| phone | string(20) | Telefon | Tak | Tak |
| isActive | boolean | Czy aktywny | Tak | Nie |
| emailNotificationsEnabled | boolean | Powiadomienia email | Tak | Nie |
| resetToken | string(64) | Token resetowania hasła | Nie | Nie |
| resetTokenExpiresAt | datetime_immutable | Wygaśnięcie tokenu | Nie | Nie |
| organization | Organization | Organizacja | Tak | Nie |
| favoriteRooms | Collection<Room> | Ulubione sale | Nie | Nie |

#### Interfejsy:
- `UserInterface` - Symfony Security
- `PasswordAuthenticatedUserInterface` - Symfony Security

#### Relacje:
- **ManyToOne** → Organization (nullable: false)
- **ManyToMany** → Room (favoriteRooms, inversedBy: favoritedByUsers)

#### Constraints:
```php
#[UniqueEntity(fields: ['username'])]
#[UniqueEntity(fields: ['email'])]
#[UniqueEntity(fields: ['phone'])]
```

#### Domyślne wartości:
- `isActive`: true
- `emailNotificationsEnabled`: true
- `roles`: [] (pusty array)

---

### 2. Organization (Organizacja)

**Lokalizacja:** `src/Feature/Organization/Entity/Organization.php`  
**Tabela:** `organizations`

Reprezentuje organizacje/firmy korzystające z systemu.

#### Pola:

| Pole | Typ | Opis | Wymagane | Unikalne |
|------|-----|------|----------|----------|
| id | UUID | Identyfikator | Auto | Tak |
| regon | string(255) | REGON | Tak | Tak |
| name | string(255) | Nazwa organizacji | Tak | Nie |
| email | string(255) | Email organizacji | Tak | Tak |
| users | Collection<User> | Użytkownicy | Nie | Nie |

#### Relacje:
- **OneToMany** → User (mappedBy: organization)

#### Constraints:
```php
#[UniqueEntity(fields: ['regon'])]
#[UniqueEntity(fields: ['email'])]
```

---

### 3. Room (Sala konferencyjna)

**Lokalizacja:** `src/Feature/Room/Entity/Room.php`  
**Tabela:** `rooms`

Reprezentuje sale konferencyjne dostępne do rezerwacji.

#### Pola:

| Pole | Typ | Opis | Wymagane | Unikalne |
|------|-----|------|----------|----------|
| id | UUID | Identyfikator | Auto | Tak |
| roomName | string(100) | Nazwa sali | Tak | Nie |
| capacity | integer | Pojemność (osoby) | Tak | Nie |
| size | float | Powierzchnia (m²) | Tak | Nie |
| location | string(255) | Lokalizacja | Tak | Nie |
| access | string(50) | Typ dostępu | Tak | Nie |
| description | text | Opis | Nie | Nie |
| lighting | string(100) | Oświetlenie | Nie | Nie |
| airConditioning | json | Klimatyzacja (min/max) | Nie | Nie |
| imagePaths | json | Ścieżki do zdjęć | Nie | Nie |
| organization | Organization | Organizacja | Tak | Nie |
| equipment | Collection<Equipment> | Wyposażenie | Nie | Nie |
| roomStatus | RoomStatus | Status | Nie | Nie |
| favoritedByUsers | Collection<User> | Polubienia | Nie | Nie |
| bookings | Collection<Booking> | Rezerwacje | Nie | Nie |
| issues | Collection<RoomIssue> | Usterki | Nie | Nie |

#### Relacje:
- **ManyToOne** → Organization (nullable: false)
- **OneToMany** → Equipment (mappedBy: room, cascade: persist/remove, orphanRemoval: true)
- **OneToOne** → RoomStatus (mappedBy: room, cascade: persist/remove, orphanRemoval: true)
- **ManyToMany** → User (mappedBy: favoriteRooms)
- **OneToMany** → Booking (mappedBy: room)
- **OneToMany** → RoomIssue (mappedBy: room)

#### Struktura JSON airConditioning:
```json
{
  "min": 18.0,
  "max": 26.0
}
```

#### Struktura JSON imagePaths:
```json
[
  "uploads/rooms/123e4567-89ab-12d3-a456-426614174000_image1.jpg",
  "uploads/rooms/123e4567-89ab-12d3-a456-426614174000_image2.jpg"
]
```

---

### 4. RoomStatus (Status sali)

**Lokalizacja:** `src/Feature/Room/Entity/RoomStatus.php`  
**Tabela:** `room_status`

Reprezentuje aktualny status dostępności sali.

#### Pola:

| Pole | Typ | Opis | Wymagane | Unikalne |
|------|-----|------|----------|----------|
| id | UUID | Identyfikator | Auto | Tak |
| status | string(20) | Status | Tak | Nie |
| room | Room | Sala | Tak | Nie |

#### Wartości status:
- `available` - Dostępna
- `out_of_use` - Niedostępna

#### Relacje:
- **OneToOne** → Room (inversedBy: roomStatus, nullable: false)

---

### 5. Equipment (Wyposażenie)

**Lokalizacja:** `src/Feature/Room/Entity/Equipment.php`  
**Tabela:** `equipment`

Reprezentuje wyposażenie dostępne w sali.

#### Pola:

| Pole | Typ | Opis | Wymagane | Unikalne |
|------|-----|------|----------|----------|
| id | UUID | Identyfikator | Auto | Tak |
| name | string(100) | Nazwa | Tak | Nie |
| category | string(50) | Kategoria | Tak | Nie |
| quantity | integer | Ilość | Tak | Nie |
| room | Room | Sala | Tak | Nie |

#### Wartości category:
- `video` - Sprzęt wideo (projektor, TV)
- `audio` - Sprzęt audio (nagłośnienie, mikrofony)
- `computer` - Sprzęt komputerowy
- `accessory` - Akcesoria (markery, flipchart)
- `furniture` - Meble

#### Relacje:
- **ManyToOne** → Room (inversedBy: equipment, nullable: false)

---

### 6. Booking (Rezerwacja)

**Lokalizacja:** `src/Feature/Booking/Entity/Booking.php`  
**Tabela:** `bookings`

Reprezentuje rezerwacje sal konferencyjnych.

#### Pola:

| Pole | Typ | Opis | Wymagane | Unikalne |
|------|-----|------|----------|----------|
| id | UUID | Identyfikator | Auto | Tak |
| title | string(255) | Tytuł rezerwacji | Tak | Nie |
| startedAt | datetime_immutable | Początek | Tak | Nie |
| endedAt | datetime_immutable | Koniec | Tak | Nie |
| participantsCount | integer | Liczba uczestników | Tak | Nie |
| isPrivate | boolean | Czy prywatna | Tak | Nie |
| status | string(20) | Status | Tak | Nie |
| room | Room | Sala | Nie | Nie |
| user | User | Twórca rezerwacji | Nie | Nie |
| participants | Collection<User> | Uczestnicy | Nie | Nie |
| createdAt | datetime_immutable | Data utworzenia | Auto | Nie |

#### Wartości status:
- `active` - Aktywna
- `cancelled` - Anulowana
- `completed` - Zakończona

#### Relacje:
- **ManyToOne** → Room (nullable: true, onDelete: SET NULL)
- **ManyToOne** → User (nullable: true, onDelete: SET NULL)
- **ManyToMany** → User (participants, JoinTable: booking_participants)

#### Domyślne wartości:
- `isPrivate`: false
- `status`: 'active'
- `createdAt`: new DateTimeImmutable()

---

### 7. RoomIssue (Usterka)

**Lokalizacja:** `src/Feature/Issue/Entity/RoomIssue.php`  
**Tabela:** `room_issues`

Reprezentuje zgłoszone usterki w salach.

#### Pola:

| Pole | Typ | Opis | Wymagane | Unikalne |
|------|-----|------|----------|----------|
| id | UUID | Identyfikator | Auto | Tak |
| room | Room | Sala | Tak | Nie |
| reporter | User | Zgłaszający | Tak | Nie |
| organization | Organization | Organizacja | Tak | Nie |
| category | string(50) | Kategoria | Tak | Nie |
| description | text | Opis usterki | Tak | Nie |
| status | string(20) | Status | Tak | Nie |
| priority | string(20) | Priorytet | Tak | Nie |
| reportedAt | datetime_immutable | Data zgłoszenia | Auto | Nie |
| closedAt | datetime_immutable | Data zamknięcia | Nie | Nie |
| notes | Collection<IssueNote> | Notatki | Nie | Nie |
| history | Collection<IssueHistory> | Historia | Nie | Nie |

#### Wartości category:
- `equipment` - Sprzęt
- `infrastructure` - Infrastruktura
- `furniture` - Meble

#### Wartości status:
- `pending` - Oczekująca
- `in_progress` - W trakcie
- `closed` - Zamknięta

#### Wartości priority:
- `low` - Niski
- `medium` - Średni
- `high` - Wysoki
- `critical` - Krytyczny

#### Relacje:
- **ManyToOne** → Room (nullable: false)
- **ManyToOne** → User (reporter, nullable: false)
- **ManyToOne** → Organization (nullable: false)
- **OneToMany** → IssueNote (mappedBy: issue, cascade: persist/remove, orphanRemoval: true)
- **OneToMany** → IssueHistory (mappedBy: issue, cascade: persist/remove, orphanRemoval: true, orderBy: createdAt DESC)

#### Domyślne wartości:
- `status`: 'pending'
- `priority`: 'medium'
- `reportedAt`: new DateTimeImmutable()

---

### 8. IssueNote (Notatka do usterki)

**Lokalizacja:** `src/Feature/Issue/Entity/IssueNote.php`  
**Tabela:** `issue_notes`

Reprezentuje notatki/komentarze do usterek.

#### Pola:

| Pole | Typ | Opis | Wymagane | Unikalne |
|------|-----|------|----------|----------|
| id | UUID | Identyfikator | Auto | Tak |
| issue | RoomIssue | Usterka | Tak | Nie |
| author | User | Autor | Tak | Nie |
| content | text | Treść | Tak | Nie |
| createdAt | datetime_immutable | Data dodania | Auto | Nie |

#### Relacje:
- **ManyToOne** → RoomIssue (inversedBy: notes, nullable: false)
- **ManyToOne** → User (nullable: false)

---

### 9. IssueHistory (Historia zmian usterki)

**Lokalizacja:** `src/Feature/Issue/Entity/IssueHistory.php`  
**Tabela:** `issue_history`

Reprezentuje historię zmian w usterkach (audit log).

#### Pola:

| Pole | Typ | Opis | Wymagane | Unikalne |
|------|-----|------|----------|----------|
| id | UUID | Identyfikator | Auto | Tak |
| issue | RoomIssue | Usterka | Tak | Nie |
| changedBy | User | Kto zmienił | Tak | Nie |
| fieldChanged | string(50) | Zmienione pole | Tak | Nie |
| oldValue | string(255) | Stara wartość | Nie | Nie |
| newValue | string(255) | Nowa wartość | Nie | Nie |
| createdAt | datetime_immutable | Data zmiany | Auto | Nie |

#### Relacje:
- **ManyToOne** → RoomIssue (inversedBy: history, nullable: false)
- **ManyToOne** → User (nullable: false)

#### Przykładowe wartości fieldChanged:
- `status` - Zmiana statusu
- `priority` - Zmiana priorytetu
- `category` - Zmiana kategorii
- `description` - Zmiana opisu

---

## Tabele pomocnicze (Join Tables)

### user_favorite_rooms
Tabela łącząca użytkowników z ulubionymi salami (ManyToMany).

| Kolumna | Typ | Opis |
|---------|-----|------|
| user_id | UUID | FK → users.id |
| room_id | UUID | FK → rooms.id |

### booking_participants
Tabela łącząca rezerwacje z uczestnikami (ManyToMany).

| Kolumna | Typ | Opis |
|---------|-----|------|
| booking_id | UUID | FK → bookings.id |
| user_id | UUID | FK → users.id |

---

## Konwencje

### Nazewnictwo tabel i kolumn
- Tabele: snake_case, liczba mnoga (np. `users`, `room_issues`)
- Kolumny: snake_case (np. `first_name`, `created_at`)
- Foreign keys: `{entity}_id` (np. `organization_id`, `room_id`)

### UUID jako Primary Key
Wszystkie encje używają UUID v7 jako klucza głównego:

```php
#[ORM\Id]
#[ORM\Column(type: 'uuid', unique: true)]
#[ORM\GeneratedValue(strategy: 'CUSTOM')]
#[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
private ?Uuid $id = null;
```

### Timestampy
- `createdAt`: DateTimeImmutable - automatyczne ustawienie w konstruktorze
- `updatedAt`: DateTimeImmutable - opcjonalne, można dodać przez lifecycle callbacks
- `closedAt`, `reportedAt`: DateTimeImmutable - specyficzne dla encji

### Soft Delete
System nie używa soft delete - rekordy są fizycznie usuwane z bazy.

### Cascade Operations
- **persist**: Automatyczne zapisywanie powiązanych encji
- **remove**: Automatyczne usuwanie powiązanych encji
- **orphanRemoval**: Usuwanie encji "osieroconych"

Przykład:
```php
#[ORM\OneToMany(
    mappedBy: 'room',
    targetEntity: Equipment::class,
    cascade: ['persist', 'remove'],
    orphanRemoval: true
)]
private Collection $equipment;
```

## Podsumowanie

Model danych RoomCtrl API:
- **9 głównych encji** reprezentujących domenę biznesową
- **Relacje 1:N i M:N** między encjami
- **UUID** jako identyfikatory
- **Immutable timestamps** dla audytu
- **Cascade operations** dla spójności danych
- **Unique constraints** dla integralności
