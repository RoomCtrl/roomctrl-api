# Docker - Deployment

## Przegląd

Projekt RoomCtrl API zawiera kompletną konfigurację Docker dla środowisk deweloperskiego i produkcyjnego.

### Komponenty

| Komponent | Obraz | Opis |
|-----------|-------|------|
| **app** | php:8.4-apache | Aplikacja Symfony + Apache |
| **db** | postgres:18-alpine | Baza danych PostgreSQL |
| **mailhog** | mailhog/mailhog | SMTP testing (tylko dev) |

## Architektura

```
┌─────────────────────────────────────────┐
│         Docker Network (bridge)         │
│                                         │
│  ┌───────────┐      ┌───────────┐     │
│  │    app    │─────▶│    db     │     │
│  │ PHP 8.4   │      │PostgreSQL │     │
│  │ Apache    │      │    18     │     │
│  └─────┬─────┘      └───────────┘     │
│        │                               │
│        │ (dev only)                    │
│        │                               │
│  ┌─────▼─────┐                        │
│  │  mailhog  │                        │
│  │SMTP tester│                        │
│  └───────────┘                        │
└─────────────────────────────────────────┘
```

## Pliki konfiguracyjne

```
.
├── Dockerfile                  # Multi-stage (dev + prod)
├── docker-compose.yml          # Development
├── docker-compose.prod.yml     # Production
├── .dockerignore              # Ignorowane pliki
├── .env.docker                # Przykładowe zmienne
├── docker-setup.sh            # Skrypt setup
└── docker/
    └── apache/
        └── vhost.conf         # Konfiguracja Apache VirtualHost
```

---

## Development (Dev)

### Szybki start

```bash
# 1. Skopiuj przykładowy .env
cp .env.docker .env

# 2. Edytuj .env i ustaw:
#    - APP_SECRET (losowy ciąg)
#    - JWT_PASSPHRASE (hasło do kluczy JWT)

# 3. Uruchom automatyczny setup
chmod +x docker-setup.sh
./docker-setup.sh
```

### Ręczny setup (krok po kroku)

#### 1. Przygotowanie środowiska

```bash
# Skopiuj .env
cp .env.docker .env
# Edytuj wartości w .env
nano .env
```

#### 2. Build i uruchomienie

```bash
# Build kontenerów
docker-compose build

# Uruchom w tle
docker-compose up -d

# Sprawdź status
docker-compose ps
```

#### 3. Generowanie kluczy JWT

```bash
docker-compose exec app bash -c '
  mkdir -p config/jwt
  openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:${JWT_PASSPHRASE}
  openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:${JWT_PASSPHRASE}
  chown www-data:www-data config/jwt/*.pem
  chmod 600 config/jwt/private.pem
  chmod 644 config/jwt/public.pem
'
```

#### 4. Instalacja zależności

```bash
docker-compose exec app composer install
```

#### 5. Baza danych

```bash
# Utworzenie bazy
docker-compose exec app php bin/console doctrine:database:create

# Migracje
docker-compose exec app php bin/console doctrine:migrations:migrate --no-interaction

# Fixtures (opcjonalnie)
docker-compose exec app php bin/console doctrine:fixtures:load --no-interaction
```

#### 6. Uprawnienia

```bash
docker-compose exec app bash -c '
  mkdir -p public/uploads/rooms
  chown -R www-data:www-data var/ public/uploads/
  chmod -R 775 var/ public/uploads/
'
```

### Dostęp do usług (Development)

| Usługa | URL | Opis |
|--------|-----|------|
| **API** | http://localhost:8000 | Główne API |
| **Swagger UI** | http://localhost:8000/api/doc | Dokumentacja interaktywna |
| **MailHog UI** | http://localhost:8025 | Podgląd wysłanych emaili |

### Komendy Docker (Development)

```bash
# Sprawdź status kontenerów
docker-compose ps

# Logi
docker-compose logs -f                  # Wszystkie
docker-compose logs -f app             # Tylko app
docker-compose logs -f db              # Tylko baza

# Wejdź do kontenera
docker-compose exec app bash           # Shell w app
docker-compose exec db psql -U roomctrl -d roomctrl_dev  # PostgreSQL CLI

# Restart usług
docker-compose restart app
docker-compose restart db

# Stop
docker-compose stop

# Stop i usuń kontenery
docker-compose down

# Stop, usuń kontenery i volumes (USUWA DANE!)
docker-compose down -v

# Rebuild po zmianach w Dockerfile
docker-compose build --no-cache
docker-compose up -d
```

### Symfony Commands w Docker

```bash
# Cache
docker-compose exec app php bin/console cache:clear

# Migracje
docker-compose exec app php bin/console doctrine:migrations:diff
docker-compose exec app php bin/console doctrine:migrations:migrate

# Fixtures
docker-compose exec app php bin/console doctrine:fixtures:load

# Routing
docker-compose exec app php bin/console debug:router

# Composer
docker-compose exec app composer install
docker-compose exec app composer update
docker-compose exec app composer require package/name
```

### Uruchamianie testów w Docker

```bash
# Wszystkie testy
docker-compose exec php composer test

# Z coverage (generuje HTML w coverage/)
docker-compose exec php composer test:coverage

# Konkretny plik testowy
docker-compose exec php vendor/bin/phpunit tests/Feature/Organization/Service/OrganizationServiceTest.php

# Konkretny test
docker-compose exec php vendor/bin/phpunit --filter testDeleteOrganizationSucceedsWhenNoUsers

# Z verbose output
docker-compose exec php vendor/bin/phpunit --verbose

# PHP CodeSniffer - sprawdzenie kodu
docker-compose exec php composer phpcs

# Automatyczne naprawianie kodu
docker-compose exec php composer phpcs:fix

# Stop on failure
docker-compose exec php vendor/bin/phpunit --stop-on-failure
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
docker-compose -f docker-compose.test.yml up -d

# Przygotuj bazę testową
docker-compose -f docker-compose.test.yml exec php-test php bin/console doctrine:database:create --if-not-exists
docker-compose -f docker-compose.test.yml exec php-test php bin/console doctrine:migrations:migrate --no-interaction

# Uruchom testy
docker-compose -f docker-compose.test.yml exec php-test composer test

# Z coverage
docker-compose -f docker-compose.test.yml exec php-test composer test:coverage

# Cleanup
docker-compose -f docker-compose.test.yml down -v
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
docker-compose -f docker-compose.prod.yml build --no-cache
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
docker-compose -f docker-compose.prod.yml up -d
```

#### 5. Migracje bazy danych

```bash
docker-compose -f docker-compose.prod.yml exec php php bin/console doctrine:migrations:migrate --no-interaction
```

#### 6. Uprawnienia dla katalogów (jeśli potrzebne)

```bash
# Upewnij się, że katalog uploads ma odpowiednie uprawnienia
docker-compose -f docker-compose.prod.yml exec php chown -R www-data:www-data /var/www/html/public/uploads
docker-compose -f docker-compose.prod.yml exec php chmod -R 775 /var/www/html/public/uploads
```

**WAŻNE:** 
- Katalog `public/uploads/rooms` jest automatycznie tworzony przez Dockerfile
- Volume `/public/uploads` jest montowany z hosta do kontenera PHP i nginx
- Dzięki temu uploadowane zdjęcia są trwale przechowywane poza kontenerem
- Nginx ma dostęp do plików w trybie read-write dla poprawnego serwowania uploadów

#### 7. Sprawdź health

```bash
# Status kontenerów
docker-compose -f docker-compose.prod.yml ps

# Health check API
curl http://localhost/api/doc

# Logi
docker-compose -f docker-compose.prod.yml logs -f app
```

### Produkcyjne optimizacje

**Dockerfile production stage:**
- ✅ Brak Xdebug
- ✅ OPcache włączony
- ✅ `display_errors = Off`
- ✅ Composer install `--no-dev`
- ✅ Symfony cache pre-warmed
- ✅ Classmap authoritative
- ✅ Usunięte niepotrzebne pliki (.git, tests, docker-compose)

**Apache:**
- ✅ Security headers (X-Frame-Options, X-Content-Type-Options, X-XSS-Protection)
- ✅ Rewrite rules dla Symfony
- ✅ Optimized PHP settings

**PostgreSQL:**
- ✅ Health checks
- ✅ Persistent volumes
- ✅ Backups volume

### Backup bazy danych (Production)

#### Ręczny backup

```bash
# Backup do pliku
docker-compose -f docker-compose.prod.yml exec db pg_dump -U roomctrl_prod -d roomctrl_prod -F c -f /backups/backup_$(date +%Y%m%d_%H%M%S).dump

# Skopiuj backup z kontenera
docker cp roomctrl_db_prod:/backups/backup_20260103_120000.dump ./backups/
```

#### Automatyczny backup (service backup)

```bash
# Uruchom backup service
docker-compose -f docker-compose.prod.yml --profile backup run db-backup
```

Możesz dodać to do crona:
```cron
# Backup codziennie o 2:00 AM
0 2 * * * cd /path/to/app && docker-compose -f docker-compose.prod.yml --profile backup run db-backup
```

#### Restore z backupu

```bash
# Skopiuj backup do kontenera
docker cp ./backups/backup_20260103_120000.dump roomctrl_db_prod:/tmp/

# Restore
docker-compose -f docker-compose.prod.yml exec db pg_restore -U roomctrl_prod -d roomctrl_prod -c /tmp/backup_20260103_120000.dump
```

### Monitoring (Production)

#### Logi

```bash
# Real-time logs
docker-compose -f docker-compose.prod.yml logs -f app

# Logi z ostatnich 100 linii
docker-compose -f docker-compose.prod.yml logs --tail=100 app

# Tylko błędy
docker-compose -f docker-compose.prod.yml logs app | grep ERROR
```

#### Health checks

```bash
# Status all services
docker-compose -f docker-compose.prod.yml ps

# Szczegółowy health check
docker inspect roomctrl_app_prod | jq '.[0].State.Health'
```

#### Resource usage

```bash
# Stats w czasie rzeczywistym
docker stats

# Zużycie konkretnego kontenera
docker stats roomctrl_app_prod
```

### Aktualizacja aplikacji (Production)

```bash
# 1. Pull nowego kodu
git pull origin main

# 2. Rebuild obrazu
docker-compose -f docker-compose.prod.yml build --no-cache

# 3. Stop starych kontenerów
docker-compose -f docker-compose.prod.yml down

# 4. Uruchom nowe
docker-compose -f docker-compose.prod.yml up -d

# 5. Migracje (jeśli są)
docker-compose -f docker-compose.prod.yml exec app php bin/console doctrine:migrations:migrate --no-interaction

# 6. Cache
docker-compose -f docker-compose.prod.yml exec app php bin/console cache:clear --env=prod

# 7. Sprawdź logi
docker-compose -f docker-compose.prod.yml logs -f app
```

### Zero-downtime deployment

Dla zero-downtime deployment użyj strategii Blue-Green:

1. Uruchom nową wersję na innym porcie
2. Przełącz reverse proxy (nginx) na nowy port
3. Zatrzymaj starą wersję

Lub użyj orchestratora jak Kubernetes.

---

## Volumes

### Development volumes

| Volume | Opis | Persistentny |
|--------|------|--------------|
| `postgres-data` | Dane PostgreSQL | ✅ Tak |
| `vendor` | Zależności Composer | ✅ Tak (przyspieszenie) |
| `var-cache` | Symfony cache | ✅ Tak |
| `var-log` | Symfony logs | ✅ Tak |
| `.` (bind mount) | Kod źródłowy | ✅ Tak (live reload) |

### Production volumes

| Volume | Opis | Backup |
|--------|------|--------|
| `postgres-data` | Dane PostgreSQL | ⚠️ Wymagany |
| `postgres-backups` | Backupy bazy | ⚠️ Wymagany |
| `uploads` | Uploaded pliki | ⚠️ Wymagany |
| `app-logs` | Logi aplikacji | ℹ️ Opcjonalny |

### Zarządzanie volumes

```bash
# Lista volumes
docker volume ls

# Inspekcja volume
docker volume inspect roomctrl-api_postgres-data

# Usuń wszystkie nieużywane volumes (OSTROŻNIE!)
docker volume prune

# Backup volume
docker run --rm -v roomctrl-api_postgres-data:/data -v $(pwd):/backup alpine tar czf /backup/postgres-data-backup.tar.gz -C /data .

# Restore volume
docker run --rm -v roomctrl-api_postgres-data:/data -v $(pwd):/backup alpine tar xzf /backup/postgres-data-backup.tar.gz -C /data
```

---

## Networking

### Network name
- Development: `roomctrl-api_roomctrl-network`
- Production: `roomctrl-api_roomctrl-network`

### Komunikacja między kontenerami

Kontenery komunikują się przez nazwy serwisów:
- `app` → `db:5432` (PostgreSQL)
- `app` → `mailhog:1025` (SMTP w dev)

### External access

**Development:**
- App: `localhost:8000`
- DB: `localhost:5432`
- MailHog UI: `localhost:8025`

**Production:**
- App: `localhost:80` (lub port z APP_PORT)
- DB: Nie wystawiony na zewnątrz (bezpieczeństwo)

---

## Troubleshooting Docker

### Problem: Kontenery nie startują

```bash
# Sprawdź logi
docker-compose logs

# Sprawdź szczegóły kontenera
docker inspect roomctrl_app_dev
```

### Problem: "Port already in use"

```bash
# Znajdź proces używający portu
lsof -i :8000
# lub
netstat -tuln | grep 8000

# Zmień port w .env
APP_PORT=8001

# Lub zabij proces
kill -9 <PID>
```

### Problem: "Permission denied" w kontenerze

```bash
# Fix uprawnień
docker-compose exec app bash -c '
  chown -R www-data:www-data var/ public/uploads/
  chmod -R 775 var/ public/uploads/
'
```

### Problem: Baza danych nie odpowiada

```bash
# Sprawdź czy działa
docker-compose exec db pg_isready -U roomctrl

# Sprawdź logi
docker-compose logs db

# Restart bazy
docker-compose restart db
```

### Problem: Zmiany w kodzie nie są widoczne

```bash
# Wyczyść cache Symfony
docker-compose exec app php bin/console cache:clear

# Lub restart kontenera
docker-compose restart app
```

### Problem: "Cannot connect to database"

```bash
# Sprawdź czy kontener db jest healthy
docker-compose ps

# Sprawdź connection string w .env
# DATABASE_URL powinno wskazywać na "db" jako host, nie "localhost"

# Test połączenia z kontenera app
docker-compose exec app php bin/console doctrine:query:sql "SELECT 1"
```

### Czyszczenie wszystkiego (Nuclear option)

```bash
# UWAGA: To usuwa WSZYSTKO - kontenery, volumes, images!
docker-compose down -v
docker system prune -a --volumes
```

---

## Best Practices

### Development
- ✅ Używaj bind mounts dla live reload
- ✅ Włącz Xdebug
- ✅ Używaj MailHog do testowania emaili
- ✅ Regularnie rób `composer update`
- ✅ Commituj `composer.lock`

### Production
- ✅ Używaj tylko named volumes
- ✅ Wyłącz Xdebug
- ✅ Włącz OPcache
- ✅ Ustaw `APP_DEBUG=0`
- ✅ Używaj silnych haseł
- ✅ Regularnie rób backupy bazy
- ✅ Monitoruj logi
- ✅ Używaj HTTPS (np. przez reverse proxy nginx + Let's Encrypt)
- ✅ Nie wystawiaj PostgreSQL na zewnątrz
- ✅ Używaj secrets dla wrażliwych danych (Docker Swarm/Kubernetes)

### Security
- ✅ Nie commituj `.env` do repozytorium
- ✅ Używaj `.env.example` jako template
- ✅ Skanuj obrazy: `docker scan roomctrl-api_app`
- ✅ Aktualizuj base images regularnie
- ✅ Używaj konkretnych wersji obrazów (nie `latest`)
- ✅ Minimalizuj warstwy w Dockerfile
- ✅ Używaj multi-stage builds
- ✅ Run containers as non-root user

---

## CI/CD Integration

Przykładowa pipeline dla GitHub Actions z testami:

```yaml
name: Build, Test and Deploy

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:18-alpine
        env:
          POSTGRES_DB: roomctrl_test
          POSTGRES_USER: test
          POSTGRES_PASSWORD: test
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
        ports:
          - 5432:5432

    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: pdo, pdo_pgsql, intl
          coverage: xdebug
      
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
      
      - name: Run PHPUnit tests
        env:
          DATABASE_URL: postgresql://test:test@localhost:5432/roomctrl_test
          APP_ENV: test
        run: |
          php bin/console doctrine:database:create --if-not-exists --env=test
          php bin/console doctrine:migrations:migrate --no-interaction --env=test
          composer test
      
      - name: Run PHP CodeSniffer
        run: composer phpcs
      
      - name: Generate coverage report
        run: composer test:coverage
      
      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage/clover.xml
          fail_ci_if_error: true

  build:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    steps:
      - uses: actions/checkout@v3
      
      - name: Build Docker image
        run: docker build --target production -t roomctrl-api:${{ github.sha }} .
      
      - name: Push to registry
        run: |
          echo ${{ secrets.DOCKER_PASSWORD }} | docker login registry.example.com -u ${{ secrets.DOCKER_USERNAME }} --password-stdin
          docker tag roomctrl-api:${{ github.sha }} registry.example.com/roomctrl-api:latest
          docker tag roomctrl-api:${{ github.sha }} registry.example.com/roomctrl-api:${{ github.sha }}
          docker push registry.example.com/roomctrl-api:latest
          docker push registry.example.com/roomctrl-api:${{ github.sha }}
      
      - name: Deploy to production
        run: |
          ssh user@production-server "
            cd /opt/roomctrl-api &&
            docker-compose -f docker-compose.prod.yml pull &&
            docker-compose -f docker-compose.prod.yml up -d &&
            docker-compose -f docker-compose.prod.yml exec -T app php bin/console doctrine:migrations:migrate --no-interaction
          "
```

**Pipeline składa się z:**
1. **Test job** - uruchamia testy, code sniffer i generuje coverage
2. **Build job** - buduje i deployuje tylko jeśli testy przejdą

**Dla GitLab CI/CD:**

```yaml
stages:
  - test
  - build
  - deploy

variables:
  POSTGRES_DB: roomctrl_test
  POSTGRES_USER: test
  POSTGRES_PASSWORD: test
  DATABASE_URL: postgresql://test:test@postgres:5432/roomctrl_test

test:
  stage: test
  image: php:8.4-cli
  services:
    - postgres:18-alpine
  before_script:
    - apt-get update && apt-get install -y libpq-dev git unzip
    - docker-php-ext-install pdo pdo_pgsql
    - curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    - composer install --prefer-dist --no-progress
  script:
    - php bin/console doctrine:database:create --if-not-exists --env=test
    - php bin/console doctrine:migrations:migrate --no-interaction --env=test
    - composer test
    - composer phpcs
  coverage: '/Lines:\s*(\d+\.\d+)%/'
  artifacts:
    reports:
      coverage_report:
        coverage_format: cobertura
        path: coverage/cobertura.xml

build:
  stage: build
  image: docker:latest
  services:
    - docker:dind
  only:
    - main
  script:
    - docker build -t $CI_REGISTRY_IMAGE:$CI_COMMIT_SHA .
    - docker push $CI_REGISTRY_IMAGE:$CI_COMMIT_SHA

deploy:
  stage: deploy
  only:
    - main
  script:
    - ssh user@production-server "cd /opt/roomctrl-api && docker-compose -f docker-compose.prod.yml pull && docker-compose -f docker-compose.prod.yml up -d"
```

---

## Podsumowanie

Docker setup dla RoomCtrl API zapewnia:
- ✅ Łatwą konfigurację development i production
- ✅ Izolację środowisk
- ✅ Replikowalność
- ✅ Szybki onboarding dla nowych deweloperów
- ✅ Consistent environment między dev, staging i prod
- ✅ Łatwe skalowanie (dodanie np. Redis, Elasticsearch)

**Quick reference:**
```bash
# Development
./docker-setup.sh                          # Automatyczny setup
docker-compose up -d                       # Start
docker-compose logs -f app                 # Logi
docker-compose exec app php bin/console    # Symfony CLI
docker-compose down                        # Stop

# Production  
docker-compose -f docker-compose.prod.yml build --no-cache
docker-compose -f docker-compose.prod.yml up -d
docker-compose -f docker-compose.prod.yml logs -f app
```
