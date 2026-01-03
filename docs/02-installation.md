# Instalacja i konfiguracja RoomCtrl API

## Wymagania wstępne

Przed rozpoczęciem instalacji upewnij się, że masz zainstalowane:

1. **PHP 8.4+** z wymaganymi rozszerzeniami
2. **Composer** (najnowsza wersja)
3. **PostgreSQL 18** (lub kompatybilna wersja)
4. **OpenSSL** (do generowania kluczy JWT)

## Instalacja krok po kroku

### 1. Klonowanie repozytorium

```bash
git clone <repository-url> roomctrl-api
cd roomctrl-api
```

### 2. Instalacja zależności

```bash
composer install
```

### 3. Konfiguracja środowiska

Skopiuj przykładowy plik konfiguracyjny:

```bash
cp .env.example .env
```

Edytuj plik `.env` i dostosuj następujące zmienne:

#### Konfiguracja Symfony

```env
APP_ENV=dev                # prod dla produkcji
APP_DEBUG=1                # 0 dla produkcji
APP_SECRET=<losowy-ciąg>   # Wygeneruj bezpieczny secret
```

#### Konfiguracja bazy danych PostgreSQL

```env
DATABASE_URL="postgresql://roomctrl:roomctrl123@localhost:5432/roomctrl_dev?serverVersion=18&charset=utf8"
POSTGRES_DB=roomctrl_db
POSTGRES_USER=roomctrl
POSTGRES_PASSWORD=roomctrl123
```

Dostosuj dane dostępowe do swojego środowiska.

#### Konfiguracja mailera

```env
# Dla rozwoju (lokalny mailhog lub mailtrap)
MAILER_DSN=smtp://localhost:1025

# Dla produkcji (np. SMTP, Gmail, SendGrid)
# MAILER_DSN=smtp://user:pass@smtp.example.com:587

MAIL_FROM_ADDRESS=noreply@roomctrl.local
```

#### Konfiguracja CORS

```env
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
```

Dla produkcji ustaw właściwą domenę frontendową.

### 4. Tworzenie bazy danych

```bash
php bin/console doctrine:database:create
```

### 5. Generowanie kluczy JWT

Wygeneruj klucze prywatny i publiczny dla JWT:

```bash
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
```

Podczas generowania zostaniesz poproszony o ustawienie passphrase. Wpisz wygenerowaną wartość z `.env`:

```env
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=<twoja-passphrase>
JWT_TOKEN_TTL=3600  # Czas życia tokena w sekundach (1h)
```

### 6. Migracje bazy danych

Wygeneruj i wykonaj migracje:

```bash
# Automatyczne generowanie migracji na podstawie encji
php bin/console doctrine:migrations:diff

# Wykonanie migracji
php bin/console doctrine:migrations:migrate
```

### 7. Załadowanie danych testowych (opcjonalnie)

Jeśli chcesz załadować przykładowe dane (fixtures):

```bash
php bin/console doctrine:fixtures:load
```

**Uwaga:** To polecenie czyści bazę danych i ładuje dane od nowa!

### 8. Tworzenie katalogów dla uploadów

Upewnij się, że katalog na uploady istnieje i ma odpowiednie uprawnienia:

```bash
mkdir -p public/uploads/rooms
chmod -R 775 public/uploads
```

### 9. Czyszczenie cache

```bash
php bin/console cache:clear
```

## Uruchamianie serwera deweloperskiego

### Wbudowany serwer PHP

```bash
php -S localhost:8000 -t public
```

API będzie dostępne pod adresem: `http://localhost:8000`

### Symfony CLI (zalecane)

Jeśli masz zainstalowane Symfony CLI:

```bash
symfony server:start
```

### Docker (opcjonalnie)

Jeśli wolisz Docker, możesz stworzyć `docker-compose.yml`:

```yaml
version: '3.8'
services:
  php:
    image: php:8.4-fpm
    volumes:
      - .:/var/www/html
    depends_on:
      - db
      
  db:
    image: postgres:18-alpine
    environment:
      POSTGRES_DB: roomctrl_dev
      POSTGRES_USER: roomctrl
      POSTGRES_PASSWORD: roomctrl123
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data
      
  nginx:
    image: nginx:alpine
    ports:
      - "8000:80"
    volumes:
      - .:/var/www/html
      - ./docker/nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - php

volumes:
  postgres_data:
```

## Weryfikacja instalacji

### 1. Sprawdź status aplikacji

```bash
php bin/console about
```

### 2. Sprawdź dokumentację API

Otwórz w przeglądarce:
```
http://localhost:8000/api/doc
```

Powinieneś zobaczyć interaktywną dokumentację Swagger UI.

### 3. Testowy request

```bash
# Rejestracja użytkownika
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "testuser",
    "password": "Test123!",
    "email": "test@example.com",
    "firstName": "Jan",
    "lastName": "Kowalski",
    "phone": "+48123456789",
    "organizationRegon": "123456789"
  }'

# Login
curl -X POST http://localhost:8000/api/login_check \
  -H "Content-Type: application/json" \
  -d '{
    "username": "testuser",
    "password": "Test123!"
  }'
```

## Skrypt resetowania bazy danych

Projekt zawiera skrypt `database.sh` do szybkiego resetowania bazy:

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

**Ostrzeżenie:** To usuwa całą bazę danych i tworzy ją od nowa!

## Konfiguracja dla produkcji

### 1. Zmienne środowiskowe

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<silny-losowy-secret>
DATABASE_URL="postgresql://user:pass@host:5432/dbname?serverVersion=18"
MAILER_DSN=smtp://user:pass@smtp.provider.com:587
MAIL_FROM_ADDRESS=noreply@yourdomain.com
CORS_ALLOW_ORIGIN='^https://yourdomain\.com$'
```

### 2. Optymalizacja

```bash
# Zainstaluj tylko produkcyjne zależności
composer install --no-dev --optimize-autoloader

# Wygeneruj cache
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# Wykonaj migracje
php bin/console doctrine:migrations:migrate --no-interaction
```

### 3. Uprawnienia

```bash
chmod -R 755 var/
chmod -R 755 public/uploads/
```

### 4. Web server (Nginx example)

```nginx
server {
    listen 80;
    server_name api.yourdomain.com;
    root /var/www/roomctrl-api/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }
}
```

## Troubleshooting

### Błąd: "Unable to connect to database"
- Sprawdź czy PostgreSQL jest uruchomione
- Zweryfikuj dane dostępowe w `DATABASE_URL`
- Sprawdź czy użytkownik ma odpowiednie uprawnienia

### Błąd: "JWT Token not found"
- Sprawdź czy klucze JWT zostały wygenerowane
- Zweryfikuj ścieżki w `.env` (JWT_SECRET_KEY, JWT_PUBLIC_KEY)
- Sprawdź uprawnienia do plików kluczy (readable)

### Błąd: "Failed to open stream: Permission denied" w katalogach var/cache lub var/log
```bash
chmod -R 777 var/
```

### Problemy z CORS
- Upewnij się, że `CORS_ALLOW_ORIGIN` w `.env` pasuje do domeny frontendu
- Sprawdź konfigurację w `config/packages/nelmio_cors.yaml`

## Kolejne kroki

Po zakończeniu instalacji:
1. Zapoznaj się z [Architekturą systemu](03-architecture.md)
2. Przejrzyj [Model danych](04-entities.md)
3. Sprawdź dostępne [Endpointy API](05-api-endpoints.md)
