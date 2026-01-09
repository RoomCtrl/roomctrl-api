# Endpointy API

## Adres bazowy

```
http://localhost:8000/api
```

Wszystkie endpointy są dostępne pod prefixem `/api`.

## Uwierzytelnianie

Większość endpointów wymaga uwierzytelniania JWT. Token należy przekazać w nagłówku:

```
Authorization: Bearer <jwt_token>
```

## Dokumentacja interaktywna (Swagger UI)

```
http://localhost:8000/api/doc
```

Pełna dokumentacja OpenAPI 3.0 z możliwością testowania endpointów.

## Przegląd modułów API

| Moduł | Prefix | Opis | Autoryzacja |
|-------|--------|------|-------------|
| Auth | /api | Uwierzytelnianie | Mixed |
| Users | /api/users | Zarządzanie użytkownikami | JWT + Admin |
| Organizations | /api/organizations | Zarządzanie organizacjami | JWT + Admin |
| Rooms | /api/rooms | Zarządzanie salami | JWT + Admin |
| Bookings | /api/bookings | Rezerwacje sal | JWT |
| Issues | /api/issues | Usterki | JWT + Admin |
| Mail | /api | Powiadomienia email | Public/JWT |
| Download | /api/download | Pobieranie plików | Public |

---

## 🔐 Authentication (Auth)

### POST /api/login_check
**Logowanie i otrzymanie tokena JWT**

**Request:**
```json
{
  "username": "john.doe",
  "password": "SecurePassword123"
}
```

**Response 200:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

**Response 401:**
```json
{
  "code": 401,
  "message": "Invalid credentials"
}
```

---

### GET /api/me
**Informacje o zalogowanym użytkowniku**

**Auth:** Bearer token  
**Query params:**
- `withOrganization` (boolean, optional) - Dołącz dane organizacji

**Response 200:**
```json
{
  "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
  "username": "john.doe",
  "roles": ["ROLE_USER"],
  "firstName": "John",
  "lastName": "Doe",
  "email": "john.doe@example.com",
  "phone": "+48123456789",
  "organization": {
    "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
    "regon": "123456789",
    "name": "Example Corp",
    "email": "contact@example.com"
  }
}
```

---

### GET /api/token_refresh
**Odświeżenie tokena JWT**

**Auth:** Bearer token (obecny)

**Response 200:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

---

### POST /api/register
**Rejestracja nowego użytkownika**

**Auth:** Public

**Request:**
```json
{
  "username": "john.doe",
  "password": "SecurePass123!",
  "email": "john.doe@example.com",
  "firstName": "John",
  "lastName": "Doe",
  "phone": "+48123456789",
  "organizationRegon": "123456789"
}
```

**Response 201:**
```json
{
  "code": 201,
  "message": "User registered successfully",
  "userId": "019afaf8-7edc-7935-9afc-d94a15e0e7ed"
}
```

**Response 400:** (błędy walidacji)
```json
{
  "code": 400,
  "message": "Validation failed",
  "errors": {
    "password": "Password must contain at least one uppercase letter and one number",
    "email": "This email is already in use"
  }
}
```

---

## 👤 Users

### GET /api/users
**Lista wszystkich użytkowników**

**Auth:** JWT (IS_AUTHENTICATED_FULLY)  
**Query params:**
- `withDetails` (boolean, optional) - Dołącz szczegóły organizacji

**Response 200:**
```json
[
  {
    "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
    "username": "john.doe",
    "firstName": "John",
    "lastName": "Doe",
    "email": "john.doe@example.com",
    "phone": "+48123456789",
    "roles": ["ROLE_USER"],
    "isActive": true,
    "emailNotificationsEnabled": true,
    "organization": {
      "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
      "regon": "123456789",
      "name": "Example Corp",
      "email": "contact@example.com"
    }
  }
]
```

---

### GET /api/users/{id}
**Szczegóły użytkownika**

**Auth:** JWT  
**Path params:**
- `id` (uuid) - ID użytkownika

**Response 200:**
```json
{
  "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
  "username": "john.doe",
  "firstName": "John",
  "lastName": "Doe",
  "email": "john.doe@example.com",
  "phone": "+48123456789",
  "roles": ["ROLE_USER"],
  "isActive": true,
  "organization": {
    "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
    "name": "Example Corp"
  }
}
```

---

### POST /api/users
**Utworzenie nowego użytkownika**

**Auth:** ROLE_ADMIN

**Request:**
```json
{
  "username": "jane.smith",
  "password": "SecurePass123!",
  "email": "jane.smith@example.com",
  "firstName": "Jane",
  "lastName": "Smith",
  "phone": "+48987654321",
  "organizationId": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
  "roles": ["ROLE_USER"],
  "isActive": true
}
```

**Response 201:**
```json
{
  "code": 201,
  "message": "User created successfully",
  "userId": "019afaf8-7edc-7935-9afc-d94a15e0e7ed"
}
```

---

### PATCH /api/users/{id}
**Aktualizacja użytkownika**

**Auth:** ROLE_ADMIN  
**Path params:**
- `id` (uuid) - ID użytkownika

**Request:** (wszystkie pola opcjonalne)
```json
{
  "firstName": "Jane",
  "lastName": "Smith-Updated",
  "email": "jane.new@example.com",
  "phone": "+48111222333",
  "isActive": false,
  "roles": ["ROLE_ADMIN"]
}
```

**Response 200:**
```json
{
  "code": 200,
  "message": "User updated successfully"
}
```

---

### DELETE /api/users/{id}
**Usunięcie użytkownika**

**Auth:** ROLE_ADMIN  
**Path params:**
- `id` (uuid) - ID użytkownika

**Response 200:**
```json
{
  "code": 200,
  "message": "User deleted successfully"
}
```

---

### POST /api/users/password_reset
**Żądanie resetu hasła (wysyłka emaila)**

**Auth:** Public

**Request:**
```json
{
  "email": "john.doe@example.com"
}
```

**Response 200:**
```json
{
  "code": 200,
  "message": "Password reset email has been sent"
}
```

---

### POST /api/users/password_reset/confirm
**Potwierdzenie resetu hasła**

**Auth:** Public

**Request:**
```json
{
  "token": "abc123def456...",
  "newPassword": "NewSecurePass123!"
}
```

**Response 200:**
```json
{
  "code": 200,
  "message": "Password has been reset successfully"
}
```

---

### GET /api/users/settings/notifications
**Pobierz ustawienia powiadomień**

**Auth:** JWT

**Response 200:**
```json
{
  "emailNotificationsEnabled": true
}
```

---

### PATCH /api/users/settings/notifications
**Zmień ustawienia powiadomień**

**Auth:** JWT

**Request:**
```json
{
  "emailNotificationsEnabled": false
}
```

**Response 200:**
```json
{
  "code": 200,
  "message": "Notification settings updated successfully"
}
```

---

## 🏢 Organizations

### GET /api/organizations
**Lista wszystkich organizacji**

**Auth:** ROLE_ADMIN  
**Query params:**
- `withUsers` (boolean, optional) - Dołącz liczbę użytkowników

**Response 200:**
```json
[
  {
    "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
    "regon": "123456789",
    "name": "Example Corp",
    "email": "contact@example.com",
    "usersCount": 15
  }
]
```

---

### GET /api/organizations/{id}
**Szczegóły organizacji**

**Auth:** ROLE_ADMIN  
**Path params:**
- `id` (uuid) - ID organizacji

**Response 200:**
```json
{
  "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
  "regon": "123456789",
  "name": "Example Corp",
  "email": "contact@example.com"
}
```

---

### POST /api/organizations
**Utworzenie nowej organizacji**

**Auth:** ROLE_ADMIN

**Request:**
```json
{
  "regon": "987654321",
  "name": "New Organization",
  "email": "contact@neworg.com"
}
```

**Response 201:**
```json
{
  "code": 201,
  "message": "Organization created successfully",
  "organizationId": "019afaf8-7edc-7935-9afc-d94a15e0e7ed"
}
```

---

### PATCH /api/organizations/{id}
**Aktualizacja organizacji**

**Auth:** ROLE_ADMIN  
**Path params:**
- `id` (uuid) - ID organizacji

**Request:** (wszystkie pola opcjonalne)
```json
{
  "name": "Updated Organization Name",
  "email": "newemail@org.com"
}
```

**Response 200:**
```json
{
  "code": 200,
  "message": "Organization updated successfully"
}
```

---

### DELETE /api/organizations/{id}
**Usunięcie organizacji**

**Auth:** ROLE_ADMIN  
**Path params:**
- `id` (uuid) - ID organizacji

**Response 200:**
```json
{
  "code": 200,
  "message": "Organization deleted successfully"
}
```

---

## 🏠 Rooms

### GET /api/rooms
**Lista wszystkich sal**

**Auth:** JWT  
**Query params:**
- `status` (string, optional) - Filtruj po statusie: available, out_of_use
- `withBookings` (boolean, optional) - Dołącz informacje o rezerwacjach

**Response 200:**
```json
[
  {
    "roomId": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
    "roomName": "Sala Konferencyjna 201",
    "status": "available",
    "capacity": 12,
    "size": 45.5,
    "location": "Piętro 2, Skrzydło A",
    "access": "Karta magnetyczna",
    "description": "Przestronna sala z naturalnym oświetleniem",
    "lighting": "Naturalne + LED",
    "airConditioning": {
      "min": 18,
      "max": 26
    },
    "imagePaths": [
      "uploads/rooms/123_photo1.jpg",
      "uploads/rooms/123_photo2.jpg"
    ],
    "equipment": [
      {
        "name": "Projektor Full HD",
        "category": "video",
        "quantity": 1
      },
      {
        "name": "Krzesła konferencyjne",
        "category": "furniture",
        "quantity": 12
      }
    ],
    "currentBooking": {
      "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
      "title": "Team Meeting",
      "startedAt": "2026-01-03T10:00:00+00:00",
      "endedAt": "2026-01-03T11:00:00+00:00",
      "participants": 5,
      "isPrivate": false
    },
    "nextBookings": []
  }
]
```

---

### GET /api/rooms/{id}
**Szczegóły sali**

**Auth:** JWT  
**Path params:**
- `id` (uuid) - ID sali

**Response 200:**
```json
{
  "roomId": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
  "roomName": "Sala Konferencyjna 201",
  "status": "available",
  "capacity": 12,
  "size": 45.5,
  "location": "Piętro 2, Skrzydło A",
  "access": "Karta magnetyczna",
  "description": "Przestronna sala konferencyjna",
  "lighting": "Naturalne + LED",
  "airConditioning": {
    "min": 18,
    "max": 26
  },
  "imagePaths": [],
  "equipment": []
}
```

---

### POST /api/rooms
**Utworzenie nowej sali**

**Auth:** ROLE_ADMIN

**Request:**
```json
{
  "roomName": "Sala 301",
  "capacity": 20,
  "size": 60.0,
  "location": "Piętro 3",
  "access": "Kod PIN",
  "description": "Duża sala szkoleniowa",
  "lighting": "LED",
  "airConditioning": {
    "min": 18,
    "max": 24
  },
  "equipment": [
    {
      "name": "Projektor 4K",
      "category": "video",
      "quantity": 1
    }
  ]
}
```

**Response 201:**
```json
{
  "code": 201,
  "message": "Room created successfully",
  "roomId": "019afaf8-7edc-7935-9afc-d94a15e0e7ed"
}
```

---

### PATCH /api/rooms/{id}
**Aktualizacja sali**

**Auth:** ROLE_ADMIN  
**Path params:**
- `id` (uuid) - ID sali

**Request:** (wszystkie pola opcjonalne)
```json
{
  "roomName": "Sala 301 - Zaktualizowana",
  "capacity": 25,
  "description": "Nowy opis",
  "equipment": [
    {
      "name": "Projektor 4K",
      "category": "video",
      "quantity": 1
    },
    {
      "name": "Krzesła biurowe",
      "category": "furniture",
      "quantity": 25
    }
  ]
}
```

**Response 200:**
```json
{
  "code": 200,
  "message": "Room updated successfully"
}
```

---

### DELETE /api/rooms/{id}
**Usunięcie sali**

**Auth:** ROLE_ADMIN  
**Path params:**
- `id` (uuid) - ID sali

**Response 200:**
```json
{
  "code": 200,
  "message": "Room deleted successfully"
}
```

---

### POST /api/rooms/{id}/images
**Upload zdjęcia sali**

**Auth:** ROLE_ADMIN  
**Path params:**
- `id` (uuid) - ID sali  
**Content-Type:** multipart/form-data  
**Form data:**
- `file` (file) - Plik graficzny (JPG, PNG, PDF)

**Response 200:**
```json
{
  "code": 200,
  "message": "Image uploaded successfully",
  "imagePath": "uploads/rooms/019afaf8_photo.jpg"
}
```

---

### DELETE /api/rooms/{roomId}/images/{imagePath}
**Usunięcie zdjęcia sali**

**Auth:** ROLE_ADMIN  
**Path params:**
- `roomId` (uuid) - ID sali
- `imagePath` (string) - Ścieżka do pliku (URL encoded)

**Response 200:**
```json
{
  "code": 200,
  "message": "Image deleted successfully"
}
```

---

### POST /api/rooms/{id}/favorite
**Dodaj salę do ulubionych**

**Auth:** JWT  
**Path params:**
- `id` (uuid) - ID sali

**Response 200:**
```json
{
  "code": 200,
  "message": "Room added to favorites",
  "isFavorite": true
}
```

---

### DELETE /api/rooms/{id}/favorite
**Usuń salę z ulubionych**

**Auth:** JWT  
**Path params:**
- `id` (uuid) - ID sali

**Response 200:**
```json
{
  "code": 200,
  "message": "Room removed from favorites",
  "isFavorite": false
}
```

---

### GET /api/rooms/favorites
**Lista ulubionych sal**

**Auth:** JWT

**Response 200:**
```json
[
  {
    "roomId": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
    "roomName": "Sala 201",
    "capacity": 12,
    "location": "Piętro 2"
  }
]
```

---

### GET /api/rooms/recent
**Ostatnio przeglądane sale**

**Auth:** JWT  
**Query params:**
- `limit` (integer, optional, default: 5) - Limit wyników

**Response 200:**
```json
[
  {
    "roomId": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
    "roomName": "Sala 201",
    "viewedAt": "2026-01-03T14:30:00+00:00"
  }
]
```

---

## 📅 Bookings

### GET /api/bookings
**Lista wszystkich rezerwacji**

**Auth:** JWT

**Response 200:**
```json
[
  {
    "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
    "title": "Team Meeting",
    "startedAt": "2026-01-05T10:00:00+00:00",
    "endedAt": "2026-01-05T11:00:00+00:00",
    "participantsCount": 5,
    "participants": [],
    "isPrivate": false,
    "status": "active",
    "room": {
      "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
      "roomName": "Sala 201",
      "location": "Piętro 2"
    },
    "user": {
      "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
      "username": "john.doe",
      "firstName": "John",
      "lastName": "Doe"
    },
    "createdAt": "2026-01-03T09:00:00+00:00"
  }
]
```

---

### GET /api/bookings/{id}
**Szczegóły rezerwacji**

**Auth:** JWT  
**Path params:**
- `id` (uuid) - ID rezerwacji

**Response 200:**
```json
{
  "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
  "title": "Team Meeting",
  "startedAt": "2026-01-05T10:00:00+00:00",
  "endedAt": "2026-01-05T11:00:00+00:00",
  "participantsCount": 5,
  "participants": [
    {
      "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
      "username": "jane.smith",
      "email": "jane@example.com"
    }
  ],
  "isPrivate": false,
  "status": "active",
  "room": {
    "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
    "roomName": "Sala 201"
  },
  "user": {
    "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
    "username": "john.doe"
  }
}
```

---

### POST /api/bookings
**Utworzenie rezerwacji**

**Auth:** JWT

**Request:**
```json
{
  "title": "Team Standup",
  "roomId": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
  "startedAt": "2026-01-10T09:00:00+00:00",
  "endedAt": "2026-01-10T09:30:00+00:00",
  "participantsCount": 8,
  "isPrivate": false,
  "participantIds": [
    "019afaf8-7edc-7935-9afc-d94a15e0e7ed"
  ]
}
```

**Response 201:**
```json
{
  "code": 201,
  "message": "Booking created successfully",
  "bookingId": "019afaf8-7edc-7935-9afc-d94a15e0e7ed"
}
```

---

### POST /api/bookings/recurring
**Utworzenie cyklicznej rezerwacji**

**Auth:** JWT

**Request:**
```json
{
  "title": "Weekly Standup",
  "roomId": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
  "startedAt": "2026-01-06T09:00:00+00:00",
  "endedAt": "2026-01-06T09:30:00+00:00",
  "participantsCount": 8,
  "recurrenceType": "weekly",
  "recurrenceEndDate": "2026-03-31",
  "isPrivate": false
}
```

**recurrenceType values:**
- `daily` - Codziennie
- `weekly` - Co tydzień
- `monthly` - Co miesiąc

**Response 201:**
```json
{
  "code": 201,
  "message": "Recurring bookings created successfully",
  "bookingsCreated": 12
}
```

---

### PATCH /api/bookings/{id}
**Aktualizacja rezerwacji**

**Auth:** JWT (tylko twórca)  
**Path params:**
- `id` (uuid) - ID rezerwacji

**Request:** (wszystkie pola opcjonalne)
```json
{
  "title": "Updated Meeting Title",
  "startedAt": "2026-01-10T10:00:00+00:00",
  "endedAt": "2026-01-10T11:00:00+00:00"
}
```

**Response 200:**
```json
{
  "code": 200,
  "message": "Booking updated successfully"
}
```

---

### DELETE /api/bookings/{id}
**Anulowanie rezerwacji**

**Auth:** JWT (tylko twórca lub admin)  
**Path params:**
- `id` (uuid) - ID rezerwacji

**Response 200:**
```json
{
  "code": 200,
  "message": "Booking cancelled successfully"
}
```

---

### GET /api/bookings/my
**Moje rezerwacje**

**Auth:** JWT

**Response 200:**
```json
[
  {
    "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
    "title": "My Meeting",
    "startedAt": "2026-01-10T10:00:00+00:00",
    "endedAt": "2026-01-10T11:00:00+00:00",
    "status": "active",
    "room": {
      "roomName": "Sala 201"
    }
  }
]
```

---

## 🔧 Issues (Usterki)

### GET /api/issues
**Lista wszystkich usterek**

**Auth:** ROLE_ADMIN  
**Query params:**
- `status` (string, optional) - Filtruj po statusie: pending, in_progress, closed

**Response 200:**
```json
[
  {
    "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
    "roomId": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
    "roomName": "Sala 201",
    "reporterId": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
    "reporterName": "John Doe",
    "category": "equipment",
    "description": "Projektor nie działa",
    "status": "pending",
    "priority": "high",
    "reportedAt": "2026-01-03T10:00:00+00:00",
    "closedAt": null
  }
]
```

---

### GET /api/issues/{id}
**Szczegóły usterki**

**Auth:** JWT  
**Path params:**
- `id` (uuid) - ID usterki

**Response 200:**
```json
{
  "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
  "room": {
    "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
    "roomName": "Sala 201"
  },
  "reporter": {
    "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
    "username": "john.doe"
  },
  "category": "equipment",
  "description": "Projektor nie wyświetla obrazu",
  "status": "in_progress",
  "priority": "high",
  "reportedAt": "2026-01-03T10:00:00+00:00",
  "closedAt": null,
  "notes": [
    {
      "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
      "content": "Serwis został powiadomiony",
      "author": "admin",
      "createdAt": "2026-01-03T11:00:00+00:00"
    }
  ],
  "history": [
    {
      "id": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
      "fieldChanged": "status",
      "oldValue": "pending",
      "newValue": "in_progress",
      "changedBy": "admin",
      "createdAt": "2026-01-03T10:30:00+00:00"
    }
  ]
}
```

---

### POST /api/issues
**Zgłoszenie nowej usterki**

**Auth:** JWT

**Request:**
```json
{
  "roomId": "019afaf8-7edc-7935-9afc-d94a15e0e7ed",
  "category": "equipment",
  "description": "Projektor nie działa - brak obrazu",
  "priority": "high"
}
```

**category values:**
- `equipment` - Sprzęt
- `infrastructure` - Infrastruktura
- `furniture` - Meble

**priority values:**
- `low` - Niski
- `medium` - Średni
- `high` - Wysoki
- `critical` - Krytyczny

**Response 201:**
```json
{
  "code": 201,
  "message": "Issue created successfully",
  "issueId": "019afaf8-7edc-7935-9afc-d94a15e0e7ed"
}
```

---

### PATCH /api/issues/{id}
**Aktualizacja usterki**

**Auth:** ROLE_ADMIN  
**Path params:**
- `id` (uuid) - ID usterki

**Request:** (wszystkie pola opcjonalne)
```json
{
  "status": "in_progress",
  "priority": "critical",
  "description": "Zaktualizowany opis"
}
```

**Response 200:**
```json
{
  "code": 200,
  "message": "Issue updated successfully"
}
```

---

### DELETE /api/issues/{id}
**Usunięcie usterki**

**Auth:** ROLE_ADMIN  
**Path params:**
- `id` (uuid) - ID usterki

**Response 200:**
```json
{
  "code": 200,
  "message": "Issue deleted successfully"
}
```

---

### POST /api/issues/{id}/notes
**Dodanie notatki do usterki**

**Auth:** JWT  
**Path params:**
- `id` (uuid) - ID usterki

**Request:**
```json
{
  "content": "Serwisant przyjedzie jutro o 10:00"
}
```

**Response 201:**
```json
{
  "code": 201,
  "message": "Note added successfully",
  "noteId": "019afaf8-7edc-7935-9afc-d94a15e0e7ed"
}
```

---

## 📧 Mail

### POST /api/send_mail
**Wysyłka dowolnego emaila**

**Auth:** Public

**Request:**
```json
{
  "to": "recipient@example.com",
  "subject": "Test Email",
  "content": "This is test email content"
}
```

**Response 200:**
```json
{
  "code": 200,
  "message": "Email has been sent successfully"
}
```

---

### POST /api/contact_mail
**Wysyłka wiadomości z formularza kontaktowego**

**Auth:** Public

**Request:**
```json
{
  "name": "John Smith",
  "email": "john.smith@example.com",
  "subject": "Service inquiry",
  "message": "Hello, I would like to inquire about your services..."
}
```

**Response 200:**
```json
{
  "code": 200,
  "message": "Contact message sent successfully"
}
```

---

## 📥 Download

### GET /api/download/android/{version}
**Pobierz aplikację Android**

**Auth:** Public  
**Path params:**
- `version` (string) - Wersja (np. "latest")

**Response:** Binary file (APK)

---

### GET /api/download/ios/{version}
**Pobierz aplikację iOS**

**Auth:** Public  
**Path params:**
- `version` (string) - Wersja (np. "latest")

**Response:** Binary file (IPA)

---

## Kody odpowiedzi HTTP

| Kod | Znaczenie | Użycie |
|-----|-----------|--------|
| 200 | OK | Sukces zapytania GET/PATCH/DELETE |
| 201 | Created | Sukces utworzenia zasobu (POST) |
| 400 | Bad Request | Błędy walidacji, nieprawidłowe dane |
| 401 | Unauthorized | Brak tokena JWT lub nieważny token |
| 403 | Forbidden | Brak uprawnień do zasobu |
| 404 | Not Found | Zasób nie istnieje |
| 409 | Conflict | Konflikt (np. pokrywające się rezerwacje) |
| 500 | Internal Server Error | Błąd serwera |

## Format błędów walidacji

```json
{
  "code": 400,
  "message": "Validation failed",
  "errors": {
    "fieldName": "Error message for this field",
    "anotherField": "Another error message"
  }
}
```

## Paginacja

Aktualnie API nie implementuje paginacji. Wszystkie listy zwracają pełne zestawy danych.

## Sortowanie

Aktualnie API nie implementuje sortowania. Dane są zwracane w domyślnej kolejności (zazwyczaj według ID lub daty utworzenia).
