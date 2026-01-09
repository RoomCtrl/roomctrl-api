# Testowanie aktualizacji statusów rezerwacji

## Metody aktualizacji

System oferuje **dwie metody** aktualizacji:

### 1. Event Listener (Automatyczna - Real-time)
✅ **Działa automatycznie** przy każdym GET do `/api/bookings*`
- Nie wymaga żadnej akcji
- Gwarantuje aktualne dane dla użytkownika

### 2. Symfony Command (Manualna/Cron)
⚙️ **Wymaga uruchomienia** ręcznie lub przez cron

## Uruchomienie command

### Lokalnie (bez Dockera)
```bash
php bin/console app:booking:update-status
```

### W Dockerze
```bash
docker-compose exec php php bin/console app:booking:update-status
```

lub (dla produkcji):
```bash
docker-compose -f docker-compose.prod.yml exec php php bin/console app:booking:update-status
```

## Test Event Listenera (Real-time update)

### 1. Utwórz rezerwację z przeszłą datą (przez SQL)

Wejdź do bazy danych:
```bash
docker-compose exec db psql -U postgres roomctrl_db
```

Utwórz testową rezerwację:
```sql
-- Znajdź użytkownika i salę
SELECT id, username FROM users LIMIT 1;
SELECT id, room_name FROM rooms LIMIT 1;

-- Utwórz rezerwację która się już zakończyła
INSERT INTO bookings (
    id, 
    title, 
    room_id, 
    user_id, 
    started_at, 
    ended_at, 
    participants_count, 
    is_private, 
    status, 
    created_at
) VALUES (
    gen_random_uuid(),
    'Test - Event Listener',
    'YOUR_ROOM_ID',  -- ID sali
    'YOUR_USER_ID',  -- ID użytkownika
    NOW() - INTERVAL '2 hours',
    NOW() - INTERVAL '1 hour',
    5,
    false,
    'active',
    NOW()
);

-- Sprawdź czy została utworzona ze statusem 'active'
SELECT id, title, ended_at, status 
FROM bookings 
WHERE title = 'Test - Event Listener';
```

### 2. Wykonaj GET request do API

**BEZ logowania do bazy!** Po prostu wywołaj endpoint:

```bash
curl -X GET http://localhost:8000/api/bookings \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

Lub otwórz w przeglądarce Swagger UI: `http://localhost:8000/api/doc`

### 3. Sprawdź logi

Event Listener powinien automatycznie zaktualizować status:

```bash
docker-compose logs php | grep "Booking statuses updated"
```

Powinieneś zobaczyć:
```
[INFO] Booking statuses updated automatically on GET request {"path":"/api/bookings","updated_count":1}
```

### 4. Zweryfikuj w bazie danych

```sql
SELECT id, title, ended_at, status 
FROM bookings 
WHERE title = 'Test - Event Listener';
```

Status powinien być `completed` (zmieniony automatycznie przez Event Listener).

---

## Test Symfony Command (Manual/Cron)

### 1. Utwórz testową rezerwację przez SQL

```bash
docker-compose exec db psql -U postgres roomctrl_db
```

```sql
-- Utwórz rezerwację dla testu command
INSERT INTO bookings (
    id, 
    title, 
    room_id, 
    user_id, 
    started_at, 
    ended_at, 
    participants_count, 
    is_private, 
    status, 
    created_at
) VALUES (
    gen_random_uuid(),
    'Test - Command Update',
    'YOUR_ROOM_ID',
    'YOUR_USER_ID',
    NOW() - INTERVAL '2 hours',
    NOW() - INTERVAL '1 hour',
    3,
    false,
    'active',
    NOW()
);

SELECT id, title, ended_at, status 
FROM bookings 
WHERE title = 'Test - Command Update';
```

### 2. Uruchom command aktualizacji

```bash
docker-compose exec php php bin/console app:booking:update-status
```

Output:
```
Updating booking statuses
=========================

Current time: 2026-01-09 16:30:45

[OK] Successfully updated 1 booking(s) to completed status.
```

### 3. Zweryfikuj w bazie

```sql
SELECT id, title, ended_at, status 
FROM bookings 
WHERE title = 'Test - Command Update';
```

Status powinien być teraz `completed` zamiast `active`.

### 4. Sprawdź poprzez API

Możesz też zweryfikować przez API:
```bash
curl -X GET "http://localhost:8000/api/bookings" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  | jq '.[] | select(.title | contains("Test"))'
```

## Automatyczne uruchamianie

### Konfiguracja cron w Docker

Dodaj do `docker-compose.yml` lub `docker-compose.prod.yml`:

```yaml
services:
  cron:
    build:
      context: .
      dockerfile: Dockerfile.prod
    depends_on:
      - db
    volumes:
      - .:/var/www/html
    command: >
      sh -c "
        echo '*/5 * * * * cd /var/www/html && php bin/console app:booking:update-status >> /var/log/cron.log 2>&1' | crontab - &&
        cron -f
      "
    networks:
      - roomctrl-network
```

Następnie:
```bash
docker-compose up -d cron
```

### Monitorowanie

Sprawdź logi crona:
```bash
docker-compose exec cron tail -f /var/log/cron.log
```

## Cleanup

Usuń testową rezerwację:
```sql
DELETE FROM bookings WHERE title = 'Test - Zakończona rezerwacja';
```
