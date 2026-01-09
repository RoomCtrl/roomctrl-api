# Automatyczna aktualizacja statusów rezerwacji

## Problem
Rezerwacje po zakończeniu (gdy `endedAt < now`) pozostają ze statusem `active` zamiast automatycznie zmienić się na `completed`.

## Rozwiązanie
System dwutorowy zapewniający aktualne dane:
1. **Event Listener** - Automatyczna aktualizacja przed każdym GET na endpointy bookingów
2. **Symfony Command + Cron** - Regularna aktualizacja co 5 minut w tle

### 1. Event Listener (Real-time)

Event Listener automatycznie aktualizuje statusy **przed każdym requestem GET do /api/bookings***.

**Zalety:**
- ✅ Użytkownik zawsze dostaje aktualne dane
- ✅ Działa natychmiast bez czekania na cron
- ✅ Nie wymaga dodatkowej konfiguracji serwera

**Plik:** `src/Feature/Booking/EventListener/BookingStatusUpdateListener.php`

Listener działa automatycznie dla wszystkich endpointów:
- `GET /api/bookings` - lista rezerwacji
- `GET /api/bookings/{id}` - szczegóły rezerwacji
- `GET /api/bookings/room/{id}` - rezerwacje pokoju
- `GET /api/bookings/user` - rezerwacje użytkownika

### 2. Symfony Command + Cron (Background)

## Command

### Uruchomienie ręczne
```bash
php bin/console app:booking:update-status
```

### Opis
Command znajduje wszystkie rezerwacje ze statusem `active`, których czas zakończenia (`endedAt`) jest wcześniejszy niż aktualny czas i zmienia ich status na `completed`.

## Konfiguracja Cron Job

**WAŻNE:** Cron job musi być skonfigurowany na serwerze/środowisku gdzie działa aplikacja!

### Wybór metody w zależności od środowiska:

#### 🐳 Docker (REKOMENDOWANE dla tego projektu)

Dodaj serwis `cron` do pliku `docker-compose.yml` lub `docker-compose.prod.yml`:

```yaml
services:
  php:
    # ... istniejąca konfiguracja ...

  cron:
    build:
      context: .
      dockerfile: Dockerfile.prod
    container_name: roomctrl-cron
    depends_on:
      - db
      - php
    volumes:
      - .:/var/www/html
    environment:
      - APP_ENV=prod
      - DATABASE_URL=${DATABASE_URL}
    command: >
      sh -c "
        echo '*/5 * * * * cd /var/www/html && php bin/console app:booking:update-status >> /var/log/cron.log 2>&1' | crontab - &&
        cron -f
      "
    networks:
      - roomctrl-network
```

Następnie uruchom:
```bash
docker-compose up -d cron
```

Sprawdź logi:
```bash
docker-compose logs -f cron
docker-compose exec cron tail -f /var/log/cron.log
```

#### 🖥️ Serwer VPS/Dedykowany (Linux)

#### 🖥️ Serwer VPS/Dedykowany (Linux)

Zaloguj się na serwer przez SSH i edytuj crontab:
```bash
ssh user@your-server.com
crontab -e
```

Dodaj jedną z poniższych linii (w zależności od częstotliwości):

**Co 5 minut:**
```cron
*/5 * * * * cd /var/www/roomctrl-api && php bin/console app:booking:update-status >> /var/log/booking-update.log 2>&1
```

**Co 10 minut:**
```cron
*/10 * * * * cd /var/www/roomctrl-api && php bin/console app:booking:update-status >> /var/log/booking-update.log 2>&1
```

Sprawdź czy działa:
```bash
tail -f /var/log/booking-update.log
```

#### 🌐 Hosting współdzielony (cPanel, Plesk)

1. Zaloguj się do panelu administracyjnego hostingu
2. Znajdź sekcję **"Cron Jobs"** lub **"Zaplanowane zadania"**
3. Utwórz nowe zadanie:
   - **Częstotliwość:** Co 5 minut (*/5 * * * *)
   - **Komenda:** `/usr/bin/php /home/username/public_html/bin/console app:booking:update-status`
   - **Email powiadomień:** Twój email (opcjonalnie)

#### ☁️ Cloud Platforms

**AWS:**
- Użyj **EventBridge (CloudWatch Events)** + Lambda lub ECS Scheduled Tasks
- Lub dodaj cron do EC2 instance (jak VPS powyżej)

**Google Cloud:**
- Użyj **Cloud Scheduler** do wywoływania Cloud Run/Cloud Functions

**Azure:**
- Użyj **Azure Functions** z Timer Trigger

**Heroku:**
- Użyj dodatku **Heroku Scheduler**
- Komenda: `php bin/console app:booking:update-status`

#### 🔄 Bez dostępu do serwera?

Jeśli **nie masz dostępu do crona** na serwerze, użyj zewnętrznego serwisu:

1. **EasyCron.com** - darmowy do 1000 wywołań/miesiąc
2. **Cron-job.org** - darmowy
3. **SetCronJob.com** - darmowy

Musisz wtedy stworzyć endpoint w aplikacji, który będzie wywoływany przez zewnętrzny serwis:

```php
// src/Feature/Booking/Controller/BookingController.php
#[Route('/webhook/update-booking-status', methods: ['POST'])]
public function webhookUpdateStatus(Request $request): JsonResponse
{
    // Zabezpiecz przed nieautoryzowanym dostępem!
    $secret = $request->headers->get('X-Webhook-Secret');
    if ($secret !== $_ENV['WEBHOOK_SECRET']) {
        return new JsonResponse(['error' => 'Unauthorized'], 403);
    }

    $count = $this->bookingService->updateExpiredBookingStatuses();
    
    return new JsonResponse([
        'success' => true,
        'updated' => $count
    ]);
}
```

---

### Docker

Jeśli używasz Dockera, dodaj do `docker-compose.yml` osobny serwis dla crona lub użyj istniejącego kontenera PHP z cron:

```yaml
services:
  cron:
    build:
      context: .
      dockerfile: Dockerfile.prod
    volumes:
      - .:/var/www/html
    command: >
      sh -c "
        echo '*/5 * * * * cd /var/www/html && php bin/console app:booking:update-status >> /var/log/booking-update.log 2>&1' | crontab - &&
        cron -f
      "
```

### Windows Task Scheduler

#### 💻 Windows (Task Scheduler)

1. Otwórz Task Scheduler
2. Utwórz nowe zadanie (Create Task)
3. W zakładce "Triggers" ustaw częstotliwość (np. co 5 minut)
4. W zakładce "Actions" dodaj akcję:
   - Program: `php.exe`
   - Arguments: `bin/console app:booking:update-status`
   - Start in: `C:\sciezka\do\projektu`

---

## 🎯 Które rozwiązanie wybrać?

| Środowisko | Rekomendacja |
|------------|--------------|
| **Development lokalnie** | Docker cron lub uruchamiaj ręcznie |
| **Serwer VPS** | Crontab na serwerze |
| **Hosting współdzielony** | Panel hostingu (cPanel/Plesk) |
| **Docker Production** | Kontener cron w docker-compose |
| **Cloud (AWS/GCP/Azure)** | Natywne narzędzia cloud (CloudWatch, Cloud Scheduler) |
| **Brak dostępu do crona** | Zewnętrzny serwis + webhook |

---

## Testowanie

### Test ręczny

1. Utwórz rezerwację z czasem zakończenia w przeszłości (np. poprzez bazę danych):
```sql
UPDATE bookings 
SET ended_at = NOW() - INTERVAL 1 HOUR 
WHERE id = 'your-booking-id';
```

2. Uruchom command:
```bash
php bin/console app:booking:update-status
```

3. Sprawdź czy status zmienił się na `completed`:
```sql
SELECT id, title, ended_at, status FROM bookings WHERE id = 'your-booking-id';
```

### Monitorowanie logów

Możesz sprawdzić logi cron job:
```bash
tail -f /var/log/booking-update.log
```

## Częstotliwość uruchamiania

Zalecana częstotliwość to **5-10 minut**. Nie ma sensu uruchamiać częściej (np. co minutę), ponieważ:
- Większość rezerwacji trwa co najmniej 30-60 minut
- Rzadko zdarza się, że ktoś potrzebuje natychmiastowej aktualizacji statusu
- Częstsze uruchamianie niepotrzebnie obciąża bazę danych

## Alternatywne rozwiązanie

Jeśli nie możesz skonfigurować cron, możesz:

1. Używać Symfony Messenger z Message Scheduler
2. Uruchamiać command z zewnętrznego serwisu (np. GitHub Actions, webhook)
3. Zaimplementować "lazy update" - aktualizować status przy pobieraniu danych

## Testowanie

Szczegółowe procedury testowania znajdziesz w [dokumentacji testowej](11-booking-status-update-testing.md).

## Implementacja w kodzie

### Event Listener
- Plik: `src/Feature/Booking/EventListener/BookingStatusUpdateListener.php`
- Namespace: `App\Feature\Booking\EventListener`

### Command
- Plik: `src/Feature/Booking/Command/UpdateBookingStatusCommand.php`
- Namespace: `App\Feature\Booking\Command`

### Service
- Metoda: `BookingService::updateExpiredBookingStatuses()`
- Zwraca: liczbę zaktualizowanych rezerwacji

### Interfejs
- Metoda: `BookingServiceInterface::updateExpiredBookingStatuses()`
