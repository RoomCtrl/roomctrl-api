# 🏢 RoomCtrl API

<div align="center">
  <img src="https://img.shields.io/badge/symfony-%23000000.svg?style=for-the-badge&logo=symfony&logoColor=white" alt="Symfony"/>
  <img src="https://img.shields.io/badge/JWT-black?style=for-the-badge&logo=JSON%20web%20tokens" alt="JWT"/>
  <img src="https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP"/>
  <img src="https://img.shields.io/badge/REST%20API-005571?style=for-the-badge" alt="REST API"/>
</div>

## 📋 Spis treści

- [🔐 JWT Autentykacja](#-jwt-autentykacja)
- [🌐 Dostępne endpointy](#-dostępne-endpointy)
- [📚 Dokumentacja API](#-dokumentacja-api)
- [💻 Przydatne komendy](#-przydatne-komendy)

---

## 🔐 JWT Autentykacja

Aplikacja korzysta z **JWT** (JSON Web Token) do autoryzacji użytkowników.

### 🔑 Logowanie i uzyskanie tokenu

Aby uzyskać token JWT, należy wysłać żądanie POST na endpoint `/api/v1/login_check` z danymi logowania:

```json
{
  "username": "testuser1",
  "password": "password1"
}
```

#### Przykład z użyciem curl:

```bash
curl -X POST -H "Content-Type: application/json" http://localhost:8000/api/v1/login_check \
  -d '{"username":"testuser1","password":"password1"}'
```

#### W odpowiedzi otrzymasz token JWT:

```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
}
```

### 🔓 Używanie tokenu do autoryzacji

Aby uzyskać dostęp do chronionych zasobów API, należy dodać token do nagłówka `Authorization` z prefixem `Bearer`:

```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
```

#### Przykład z użyciem curl:

```bash
curl -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..." \
  http://localhost:8000/api/protected
```

---

## 🌐 Dostępne endpointy

### 🔓 Publiczne

| Metoda | Endpoint           | Opis                              |
|--------|--------------------|------------------------------------|
| GET    | `/api/public`      | Dostępny dla wszystkich            |
| POST   | `/api/v1/login_check` | Logowanie i uzyskanie tokenu JWT   |
| GET    | `/api/doc`         | Dokumentacja API Swagger           |

### 🔒 Chronione (wymagają JWT)

| Metoda | Endpoint           | Opis                              | Wymagane role |
|--------|--------------------|------------------------------------|---------------|
| GET    | `/api/protected`   | Dostępny dla zalogowanych         | Wszystkie     |
| GET    | `/api/secured`     | Dostępny dla zalogowanych         | Wszystkie     |
| GET    | `/api/admin`       | Dostępny tylko dla administratorów | ROLE_ADMIN    |

---

## 📚 Dokumentacja API

Dokumentacja API jest dostępna pod adresem `/api/doc` i zawiera:
- Wszystkie endpointy z ich opisami 
- Formularz do logowania (przycisk Authorize)
- Możliwość testowania API bezpośrednio z interfejsu Swagger

---

## 💻 Przydatne komendy

| Komenda | Opis |
|---------|------|
| `php bin/console app:import-database` | Wykonuje polecenia z pliku database.sql |
| `php bin/console doctrine:migrations:diff` | Generuje nową migrację po zmianach w encjach |
| `php bin/console doctrine:migrations:migrate` | Stosuje nowe migracje do bazy danych | 