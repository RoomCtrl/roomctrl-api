# Docker - Deployment

## Przegląd

Projekt RoomCtrl API zawiera kompletną konfigurację Docker dla środowisk deweloperskiego i produkcyjnego.

### Komponenty

| Komponent | Obraz | Opis |
|-----------|-------|------|
| **php** | php:8.4-fpm-alpine | Aplikacja Symfony (PHP-FPM) |
| **nginx** | nginx:alpine | Serwer WWW |
| **postgres** | postgres:18-alpine | Baza danych PostgreSQL |
| **mailhog** | mailhog/mailhog | SMTP testing (tylko dev) |
| **cron** | php:8.4-fpm-alpine | Cronjob dla aktualizacji statusów |

## Architektura

### Przepływ żądań

**Request → nginx → PHP-FPM → PostgreSQL/MailHog**

1. **Zewnętrzny request** → Port 8080 (dev) / 80 (prod)
2. **nginx** (serwer WWW) → Reverse proxy, obsługa statycznych plików
3. **php (PHP-FPM)** → Aplikacja Symfony, logika biznesowa
4. **postgres** → Przechowywanie danych (port 5432 w dev)
5. **mailhog** → Testowanie emaili (tylko dev, SMTP:1025, UI:8025)
6. **cron** → Automatyczna aktualizacja statusów rezerwacji

### Komunikacja między kontenerami

| Od | Do | Port | Cel |
|----|----|------|-----|
| nginx | php | 9000 | FastCGI (PHP-FPM) |
| php | postgres | 5432 | Połączenie z bazą danych |
| php | mailhog | 1025 | SMTP (wysyłka emaili w dev) |
| cron | postgres | 5432 | Aktualizacja statusów rezerwacji |
| Host | nginx | 8080 (dev) / 80 (prod) | HTTP requests |
| Host | postgres | 5432 (tylko dev) | Bezpośrednie połączenie DB |
| Host | mailhog | 8025 (tylko dev) | UI MailHog |

### Sieć Docker

- **Network name**: `roomctrl_network` (bridge driver)
- **Komunikacja wewnętrzna**: Przez nazwy serwisów (np. `php`, `postgres`)
- **Izolacja**: Kontenery widzą się tylko w obrębie tej samej sieci
- **DNS**: Docker automatycznie rozwiązuje nazwy serwisów na adresy IP

## Pliki konfiguracyjne

```
.
├── Dockerfile.dev             # Development
├── Dockerfile.prod            # Production
├── docker-compose.yml         # Development
├── docker-compose.prod.yml    # Production
├── .dockerignore              # Ignorowane pliki
├── .env.dev.example           # Przykładowe zmienne (dev)
├── .env.prod.example          # Przykładowe zmienne (prod)
├── start-dev.sh               # Skrypt setup dev
├── start-prod.sh              # Skrypt setup prod
├── database-dev.sh            # Skrypt bazy danych dev
├── database-prod.sh           # Skrypt bazy danych prod
└── docker/
    ├── entrypoint.prod.sh     # Entrypoint dla produkcji
    └── nginx/
        ├── dev.conf           # Nginx config dev
        └── prod.conf          # Nginx config prod
```

---

## Development (Dev)

### Szybki start

```bash
# 1. Skopiuj przykładowy .env
cp .env.dev.example .env

# 2. Edytuj .env i ustaw:
#    - APP_SECRET (losowy ciąg)
#    - JWT_PASSPHRASE (hasło do kluczy JWT)
#    - POSTGRES_PASSWORD (hasło do bazy)

# 3. Uruchom automatyczny setup
chmod +x start-dev.sh
./start-dev.sh
```

**Lub użyj skryptu database-dev.sh tylko do bazy:**
```bash
chmod +x database-dev.sh
./database-dev.sh
```

### Ręczny setup (krok po kroku)

#### 1. Przygotowanie środowiska

```bash
# Skopiuj .env
cp .env.dev.example .env
# Edytuj wartości w .env
nano .env
```

#### 2. Build i uruchomienie

```bash
# Build kontenerów
docker compose build

# Uruchom w tle
docker compose up -d

# Sprawdź status
docker compose ps
```

#### 3. Generowanie kluczy JWT

```bash
docker compose exec php bash -c '
  mkdir -p config/jwt
  openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:${JWT_PASSPHRASE}
  openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:${JWT_PASSPHRASE}
  chmod 600 config/jwt/private.pem
  chmod 644 config/jwt/public.pem
'
```

#### 4. Instalacja zależności

```bash
docker compose exec php composer install
```

#### 5. Baza danych

```bash
# Utworzenie bazy
docker compose exec php php bin/console doctrine:database:create

# Migracje
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# Fixtures (opcjonalnie)
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

#### 6. Uprawnienia

```bash
docker compose exec php bash -c '
  mkdir -p public/uploads/rooms
  chmod -R 775 var/ public/uploads/
'
```

### Dostęp do usług (Development)

| Usługa | URL | Opis |
|--------|-----|------|
| **API** | http://localhost:8080 | Główne API (nginx) |
| **Swagger UI** | http://localhost:8080/api/doc | Dokumentacja interaktywna |
| **MailHog UI** | http://localhost:8025 | Podgląd wysłanych emaili |
| **PostgreSQL** | localhost:5432 | Bezpośrednie połączenie z DB |

### Komendy Docker (Development)

```bash
# Sprawdź status kontenerów
docker compose ps

# Logi
docker compose logs -f                  # Wszystkie
docker compose logs -f php             # Tylko php
docker compose logs -f nginx           # Tylko nginx
docker compose logs -f postgres        # Tylko baza

# Wejdź do kontenera
docker compose exec php sh             # Shell w php
docker compose exec postgres psql -U roomctrl -d roomctrl_db  # PostgreSQL CLI

# Restart usług
docker compose restart php
docker compose restart postgres

# Stop
docker compose stop

# Stop i usuń kontenery
docker compose down

# Stop, usuń kontenery i volumes (USUWA DANE!)
docker compose down -v

# Rebuild po zmianach w Dockerfile
docker compose build --no-cache
docker compose up -d
```

### Symfony Commands w Docker

```bash
# Cache
docker compose exec php php bin/console cache:clear

# Migracje
docker compose exec php php bin/console doctrine:migrations:diff
docker compose exec php php bin/console doctrine:migrations:migrate

# Fixtures
docker compose exec php php bin/console doctrine:fixtures:load

# Routing
docker compose exec php php bin/console debug:router

# Composer
docker compose exec php composer install
docker compose exec php composer update
docker compose exec php composer require package/name
```

### Uruchamianie testów w Docker

```bash
# Wszystkie testy
docker compose exec php composer test

# Z coverage (generuje HTML w coverage/)
docker compose exec php composer test:coverage

# Konkretny plik testowy
docker compose exec php vendor/bin/phpunit tests/Feature/Organization/Service/OrganizationServiceTest.php

# Konkretny test
docker compose exec php vendor/bin/phpunit --filter testDeleteOrganizationSucceedsWhenNoUsers

# Z verbose output
docker compose exec php vendor/bin/phpunit --verbose

# PHP CodeSniffer - sprawdzenie kodu
docker compose exec php composer phpcs

# Automatyczne naprawianie kodu
docker compose exec php composer phpcs:fix

# Stop on failure
docker compose exec php vendor/bin/phpunit --stop-on-failure
```

**Wskazówki dotyczące testów:**
- Testy używają środowiska `APP_ENV=test` (konfiguracja w `phpunit.xml.dist`)
- Dla testów można stworzyć osobną bazę danych testową
- Coverage HTML generuje się w katalogu `coverage/`
- Użyj `--stop-on-failure` aby zatrzymać na pierwszym błędzie
- PHPUnit 10.5 jest używany w projekcie

### Środowisko testowe w Docker (opcjonalne)

Dla pełnej izolacji testów możesz stworzyć osobne kontenery:

**docker-compose.test.yml:**
```yaml
services:
  php-test:
    build:
      context: .
      dockerfile: Dockerfile.dev
    container_name: roomctrl_test
    environment:
      - APP_ENV=test
      - DATABASE_URL=postgresql://test:test@postgres-test:5432/roomctrl_test
    depends_on:
      - postgres-test
    volumes:
      - .:/var/www/html
    networks:
      - test_network

  postgres-test:
    image: postgres:18-alpine
    container_name: roomctrl_postgres_test
    environment:
      POSTGRES_DB: roomctrl_test
      POSTGRES_USER: test
      POSTGRES_PASSWORD: test
    networks:
      - test_network

networks:
  test_network:
    driver: bridge
```

**Uruchomienie środowiska testowego:**
```bash
# Build i start testowego środowiska
docker compose -f docker-compose.test.yml up -d

# Przygotuj bazę testową
docker compose -f docker-compose.test.yml exec php-test php bin/console doctrine:database:create --if-not-exists
docker compose -f docker-compose.test.yml exec php-test php bin/console doctrine:migrations:migrate --no-interaction

# Uruchom testy
docker compose -f docker-compose.test.yml exec php-test composer test

# Z coverage
docker compose -f docker-compose.test.yml exec php-test composer test:coverage

# Cleanup
docker compose -f docker-compose.test.yml down -v
```

### Xdebug (Development)

Xdebug jest dostępny w kontenerze dev na porcie 9003.

**Konfiguracja PHPStorm:**
1. Settings → PHP → Servers
2. Dodaj serwer:
   - Name: localhost
   - Host: localhost
   - Port: 8000
   - Debugger: Xdebug
   - ✓ Use path mappings
   - Map: `/your/local/path` → `/var/www/html`

3. Start Listening for PHP Debug Connections
4. Ustaw breakpoint
5. Odśwież stronę w przeglądarce

---

## Production (Prod)

### Konfiguracja

#### 1. Przygotuj .env dla produkcji

```bash
cp .env.docker .env.prod
nano .env.prod
```

**Wymagane zmiany:**
```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<silny-losowy-secret-64-znaki>

# Produkcyjna baza danych
POSTGRES_DB=roomctrl_prod
POSTGRES_USER=roomctrl_prod
POSTGRES_PASSWORD=<silne-hasło>

# Produkcyjny mailer (np. SMTP, SendGrid, Mailgun)
MAILER_DSN=smtp://username:password@smtp.example.com:587
MAIL_FROM_ADDRESS=noreply@yourdomain.com

# JWT
JWT_PASSPHRASE=<silna-passphrase>
JWT_TOKEN_TTL=3600

# CORS dla frontendu
CORS_ALLOW_ORIGIN='^https://yourdomain\.com$'

# Port dla produkcji (zazwyczaj 80 lub 443)
APP_PORT=80
```

#### 2. Build obrazu produkcyjnego

```bash
docker compose -f docker-compose.prod.yml build --no-cache
```

#### 3. Generowanie kluczy JWT (jednorazowo)

```bash
# Lokalnie lub w kontenerze tymczasowym
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
chmod 600 config/jwt/private.pem
chmod 644 config/jwt/public.pem
```

**WAŻNE:** Przenieś klucze na serwer produkcyjny w bezpieczny sposób!

#### 4. Uruchomienie na produkcji

```bash
# Załaduj zmienne z .env.prod
export $(cat .env.prod | xargs)

# Uruchom
docker compose -f docker-compose.prod.yml up -d
```

#### 5. Migracje bazy danych

```bash
docker compose -f docker-compose.prod.yml exec php php bin/console doctrine:migrations:migrate --no-interaction
```

#### 6. Uprawnienia dla katalogów (jeśli potrzebne)

```bash
# Upewnij się, że katalog uploads ma odpowiednie uprawnienia
docker compose -f docker-compose.prod.yml exec php chown -R www-data:www-data /var/www/html/public/uploads
docker compose -f docker-compose.prod.yml exec php chmod -R 775 /var/www/html/public/uploads
```

**WAŻNE:** 
- Katalog `public/uploads/rooms` jest automatycznie tworzony przez Dockerfile
- Volume `/public/uploads` jest montowany z hosta do kontenera PHP i nginx
- Dzięki temu uploadowane zdjęcia są trwale przechowywane poza kontenerem
- Nginx ma dostęp do plików w trybie read-write dla poprawnego serwowania uploadów

#### 7. Sprawdź health

```bash
# Status kontenerów
docker compose -f docker-compose.prod.yml ps

# Health check API
curl http://localhost/api/doc

# Logi
docker compose -f docker-compose.prod.yml logs -f php
```

### Produkcyjne optimizacje

**Dockerfile production stage:**
- Brak Xdebug
- OPcache włączony
- `display_errors = Off`
- Composer install `--no-dev`
- Symfony cache pre-warmed
- Classmap authoritative
- Usunięte niepotrzebne pliki (.git, tests, docker-compose)

**Apache:**
- Security headers (X-Frame-Options, X-Content-Type-Options, X-XSS-Protection)
- Rewrite rules dla Symfony
- Optimized PHP settings

**PostgreSQL:**
- Health checks
- Persistent volumes
- Backups volume

### Backup bazy danych (Production)

#### Ręczny backup

```bash
# Backup do pliku
docker compose -f docker-compose.prod.yml exec postgres pg_dump -U roomctrl_prod -d roomctrl_prod -F c -f /backups/backup_$(date +%Y%m%d_%H%M%S).dump

# Skopiuj backup z kontenera
docker cp roomctrl_postgres_prod:/backups/backup_20260103_120000.dump ./backups/
```

#### Automatyczny backup (service backup)

```bash
# Uruchom backup service
docker compose -f docker-compose.prod.yml --profile backup run db-backup
```

Możesz dodać to do crona:
```cron
# Backup codziennie o 2:00 AM
0 2 * * * cd /path/to/app && docker compose -f docker-compose.prod.yml --profile backup run db-backup
```

#### Restore z backupu

```bash
# Skopiuj backup do kontenera
docker cp ./backups/backup_20260103_120000.dump roomctrl_postgres_prod:/tmp/

# Restore
docker compose -f docker-compose.prod.yml exec postgres pg_restore -U roomctrl_prod -d roomctrl_prod -c /tmp/backup_20260103_120000.dump
```

### Monitoring (Production)

#### Logi

```bash
# Real-time logs
docker compose -f docker-compose.prod.yml logs -f php

# Logi z ostatnich 100 linii
docker compose -f docker-compose.prod.yml logs --tail=100 php

# Tylko błędy
docker compose -f docker-compose.prod.yml logs php | grep ERROR
```

#### Health checks

```bash
# Status all services
docker compose -f docker-compose.prod.yml ps

# Szczegółowy health check
docker inspect roomctrl_php_prod | jq '.[0].State.Health'
```

#### Resource usage

```bash
# Stats w czasie rzeczywistym
docker stats

# Zużycie konkretnego kontenera
docker stats roomctrl_php_prod
```

### Aktualizacja aplikacji (Production)

```bash
# 1. Pull nowego kodu
git pull origin main

# 2. Rebuild obrazu
docker compose -f docker-compose.prod.yml build --no-cache

# 3. Stop starych kontenerów
docker compose -f docker-compose.prod.yml down

# 4. Uruchom nowe
docker compose -f docker-compose.prod.yml up -d

# 5. Migracje (jeśli są)
docker compose -f docker-compose.prod.yml exec php php bin/console doctrine:migrations:migrate --no-interaction

# 6. Cache
docker compose -f docker-compose.prod.yml exec php php bin/console cache:clear --env=prod

# 7. Sprawdź logi
docker compose -f docker-compose.prod.yml logs -f php
```

## Volumes

### Development volumes

| Volume | Opis | Persistentny |
|--------|------|--------------|
| `postgres-data` | Dane PostgreSQL | Tak |
| `vendor` | Zależności Composer | Tak (przyspieszenie) |
| `var-cache` | Symfony cache | Tak |
| `var-log` | Symfony logs | Tak |
| `.` (bind mount) | Kod źródłowy | Tak (live reload) |

### Production volumes

| Volume | Opis | Backup |
|--------|------|--------|
| `postgres-data` | Dane PostgreSQL | Wymagany |
| `postgres-backups` | Backupy bazy | Wymagany |
| `uploads` | Uploaded pliki | Wymagany |
| `app-logs` | Logi aplikacji | Opcjonalny |

### Zarządzanie volumes

```bash
# Lista volumes
docker volume ls

# Inspekcja volume
docker volume inspect roomctrl-api_postgres_data

# Usuń wszystkie nieużywane volumes (OSTROŻNIE!)
docker volume prune

# Backup volume
docker run --rm -v roomctrl-api_postgres_data:/data -v $(pwd):/backup alpine tar czf /backup/postgres-data-backup.tar.gz -C /data .

# Restore volume
docker run --rm -v roomctrl-api_postgres_data:/data -v $(pwd):/backup alpine tar xzf /backup/postgres-data-backup.tar.gz -C /data
```

---

## Networking

### Network name
- Development: `roomctrl-api_roomctrl_network`
- Production: `roomctrl-api_roomctrl_network`

### Komunikacja między kontenerami

Kontenery komunikują się przez nazwy serwisów:
- `nginx` → `php:9000` (PHP-FPM)
- `php` → `postgres:5432` (PostgreSQL)
- `php` → `mailhog:1025` (SMTP w dev)
- `cron` → `postgres:5432` (PostgreSQL)

### External access
Nginx: `localhost:8080`
- PostgreSQL: `localhost:5432`
- MailHog UI: `localhost:8025`
- MailHog SMTP: `localhost:1025`

**Production:**
- Nginx: `localhost:80` (HTTP), `localhost:443` (HTTPS)
- PostgreSQLoduction:**
- App: `localhost:80` (lub port z APP_PORT)
- DB: Nie wystawiony na zewnątrz (bezpieczeństwo)

---

## Troubleshooting Docker

### Problem: Kontenery nie startują

```bash
# Sprawdź logi
docker compose logs

# Sprawdź szczegóły kontenera
docker inspect roomctrl_php_dev
```

### Problem: "Port already in use"

```bash
# Znajdź proces używający portu
lsof -i :8080
# lub
netstat -tuln | grep 8080

# Zmień port w .env
APP_PORT=8081

# Lub zabij proces
kill -9 <PID>
```

### Problem: "Permission denied" w kontenerze

```bash
# Fix uprawnień
docker compose exec php bash -c '
  chown -R www-data:www-data var/ public/uploads/
  chmod -R 775 var/ public/uploads/
'
```

### Problem: Baza danych nie odpowiada

```bash
# Sprawdź czy działa
docker compose exec postgres pg_isready -U roomctrl

# Sprawdź logi
docker compose logs postgres

# Restart bazy
docker compose restart postgres
```

### Problem: Zmiany w kodzie nie są widoczne

```bash
# Wyczyść cache Symfony
docker compose exec php php bin/console cache:clear

# Lub restart kontenera
docker compose restart php
```

### Problem: "Cannot connect to database"

```bash
# Sprawdź czy kontener postgres jest healthy
docker compose ps

# Sprawdź connection string w .env
# DATABASE_URL powinno wskazywać na "postgres" jako host, nie "localhost"

# Test połączenia z kontenera php
docker compose exec php php bin/console doctrine:query:sql "SELECT 1"
```

### Czyszczenie wszystkiego (Nuclear option)

```bash
# UWAGA: To usuwa WSZYSTKO - kontenery, volumes, images!
docker compose down -v
docker system prune -a --volumes
```

---

## Podsumowanie

Docker setup dla RoomCtrl API zapewnia:
- Łatwą konfigurację development i production
- Izolację środowisk
- Replikowalność
- Szybki onboarding dla nowych deweloperów
- Consistent environment między dev, staging i prod
- Łatwe skalowanie (dodanie np. Redis, Elasticsearch)

**Quick reference:**
```bash
# Development
./start-dev.sh                             # Automatyczny setup
docker compose up -d                       # Start
docker compose logs -f php                 # Logi
docker compose exec php php bin/console    # Symfony CLI
docker compose down                        # Stop

# Production  
./start-prod.sh                                          # Automatyczny setup
docker compose -f docker-compose.prod.yml build --no-cache
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml logs -f php
```
