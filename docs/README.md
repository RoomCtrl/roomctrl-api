# RoomCtrl API - Dokumentacja Techniczna

## Spis treści

1. [Przegląd projektu](01-overview.md)
2. [Instalacja i konfiguracja](02-installation.md)
3. [Architektura systemu](03-architecture.md)
4. [Model danych (Entities)](04-entities.md)
5. [Endpointy API](05-api-endpoints.md)
6. [Uprawnienia i bezpieczeństwo](06-security.md)
7. [Moduły funkcjonalne](07-features.md)
8. [Testowanie i rozwój](08-development.md)
9. [Docker](09-docker.md)
10. [Automatyczna aktualizacja statusów rezerwacji](10-booking-status-update.md)
11. [Testowanie aktualizacji statusów](11-booking-status-update-testing.md)

## Wprowadzenie

RoomCtrl API to REST API zbudowane w oparciu o Symfony 7.4 i PHP 8.4, służące do zarządzania rezerwacjami sal konferencyjnych, zgłaszania usterek oraz administracji użytkownikami w organizacjach.

### Kluczowe technologie

- **PHP**: 8.4+
- **Framework**: Symfony 7.4
- **Baza danych**: PostgreSQL 18
- **ORM**: Doctrine 3.5
- **Uwierzytelnianie**: JWT (lexik/jwt-authentication-bundle)
- **Dokumentacja API**: OpenAPI 3.0 (nelmio/api-doc-bundle)
- **Mailer**: Symfony Mailer + Twig

### Główne funkcjonalności

- 🔐 System uwierzytelniania JWT z rolami
- 🏢 Zarządzanie organizacjami i użytkownikami
- 🏠 Katalog sal konferencyjnych z wyposażeniem
- 📅 Rezerwacje sal z powiadomieniami
- 🔧 System zgłaszania i śledzenia usterek
- 📧 System powiadomień email
- 📄 Upload i zarządzanie plikami
- 🔍 Filtrowanie i wyszukiwanie zasobów

### Struktura dokumentacji

Każdy dokument zawiera szczegółowe informacje na temat konkretnego aspektu systemu. Rozpocznij od przeglądu projektu, a następnie przejdź do pozostałych sekcji w zależności od potrzeb.
