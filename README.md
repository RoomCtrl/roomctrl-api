# RoomCtrl API

REST API do zarządzania rezerwacjami sal konferencyjnych zbudowany w Symfony 7.4 i PHP 8.4.

## Dokumentacja

Pełna dokumentacja techniczna znajduje się w folderze [docs/](docs/README.md):

- [Przegląd projektu](docs/01-overview.md)
- [Instalacja](docs/02-installation.md)
- [Architektura](docs/03-architecture.md)
- [Model danych](docs/04-entities.md)
- [API Endpoints](docs/05-api-endpoints.md)
- [Bezpieczeństwo](docs/06-security.md)
- [Moduły](docs/07-features.md)
- [Rozwój & Testowanie](docs/08-development.md)
- [Docker](docs/09-docker.md)
- [Automatyczna aktualizacja statusów rezerwacji](docs/10-booking-status-update.md)

## Commands

### Aktualizacja statusów rezerwacji

System automatycznie aktualizuje statusy rezerwacji na dwa sposoby:

1. **Event Listener (Real-time)** - Automatycznie przy każdym GET do `/api/bookings*`
2. **Symfony Command (Background)** - Regularnie przez cron

```bash
php bin/console app:booking:update-status
```

Zmienia status rezerwacji z `active` na `completed` po zakończeniu czasu rezerwacji.

**Konfiguracja cron (opcjonalna, rekomendowana dla produkcji):**
```cron
*/5 * * * * cd /sciezka/do/projektu && php bin/console app:booking:update-status
```

**Docker cron** jest już skonfigurowany w `docker-compose.yml` i `docker-compose.prod.yml`.
