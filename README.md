# RoomCtrl API

## JWT Autentykacja

Aplikacja korzysta z JWT (JSON Web Token) do autoryzacji użytkowników.

### Logowanie i uzyskanie tokenu

Aby uzyskać token JWT, należy wysłać żądanie POST na endpoint `/api/login_check` z danymi logowania:

```json
{
  "username": "user",
  "password": "password"
}
```

Przykład z użyciem curl:

```bash
curl -X POST -H "Content-Type: application/json" http://localhost:8000/api/login_check -d '{"username":"user","password":"password"}'
```

W odpowiedzi otrzymasz token JWT:

```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
}
```

### Używanie tokenu do autoryzacji

Aby uzyskać dostęp do chronionych zasobów API, należy dodać token do nagłówka `Authorization` z prefixem `Bearer`:

```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
```

Przykład z użyciem curl:

```bash
curl -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..." http://localhost:8000/api/protected
```

## Dostępne endpointy

### Publiczne

- `GET /api/public` - dostępny dla wszystkich
- `POST /api/login_check` - logowanie i uzyskanie tokenu JWT
- `GET /api/doc` - dokumentacja API Swagger

### Chronione (wymagają JWT)

- `GET /api/protected` - dostępny dla zalogowanych użytkowników
- `GET /api/secured` - dostępny dla zalogowanych użytkowników
- `GET /api/admin` - dostępny tylko dla użytkowników z rolą ROLE_ADMIN

## Dokumentacja API

Dokumentacja API jest dostępna pod adresem `/api/doc` i zawiera wszystkie endpointy z ich opisami oraz formularz do logowania (Authorize).
