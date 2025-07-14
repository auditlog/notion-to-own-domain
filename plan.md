# Plan naprawy i rozwoju projektu Notion Page Viewer PHP

## Błędy składniowe i logiczne
- [ ] **index.php, linia 93-94**: Poprawić użycie niezdefiniowanej zmiennej `$ch` w komunikacie błędu
- [ ] **index.php, linie 248-324**: Naprawić niepoprawną strukturę warunkową if/else if/else w funkcji `formatRichText()`
- [ ] **index.php, linie 405-406**: Poprawić logikę dla elementów listy (obecnie funkcja tworzy zawsze `ul` zamiast odpowiednich typów list)
- [ ] **index.php, linia 557**: Dodać brakujący parametr `$mentionCache` w wywołaniu `formatRichText()` dla komórek tabeli

## Problemy z konfiguracją
- [ ] Plik `config_draft.php` zawiera placeholdery "xxxx" zamiast rzeczywistych wartości
- [ ] Dodać zmienne środowiskowe w `.htaccess`, które są zalecane w README
- [ ] Ujednolicić nazwę pliku konfiguracyjnego (zmienić `config_draft.php` na `config.php`)
- [ ] Stworzyć folder `private/cache/` jeśli nie istnieje

## Problemy z bezpieczeństwem
- [ ] Przenieść hasło do chronionej zawartości do zmiennej środowiskowej
- [ ] Dodać mechanizm CSRF w formularzu hasła
- [ ] Usunąć potencjalne wycieki informacji w komunikatach błędów
- [ ] Zmienić uprawnienia dla katalogu cache (z 0755 na bardziej restrykcyjne)
- [ ] Dodać filtrowanie danych wejściowych we wszystkich formularzach

## Problemy z obsługą błędów
- [ ] Dodać rozróżnienie różnych kodów HTTP błędów API
- [ ] Zaimplementować mechanizm retry dla tymczasowych błędów
- [ ] Ujednolicić system logowania błędów
- [ ] Dodać limit prób wpisania hasła oraz mechanizm timeout/wygaśnięcia sesji
- [ ] Rozszerzyć error.php o obsługę większej liczby kodów błędów

## Problemy z kompatybilnością PHP
- [ ] Zaktualizować przestarzałe dyrektywy w `.htaccess` (Order allow,deny)
- [ ] Dodać sprawdzenie, czy plik istnieje przed użyciem funkcji `unlink()`
- [ ] Wprowadzić deklaracje typów zwracanych przez funkcje
- [ ] Doprecyzować wymagania dotyczące rozszerzeń PHP w dokumentacji
- [ ] Dodać sprawdzanie wersji PHP w konfiguracji

## Optymalizacje
- [ ] Poprawić formatowanie i wcięcia kodu dla lepszej czytelności
- [ ] Przenieść style lightboxa z JS do pliku CSS
- [ ] Usunąć console.log z kodu produkcyjnego
- [ ] Wprowadzić spójny system komentarzy w kodzie
- [ ] Zaimplementować bardziej wydajne mechanizmy cache dla API

## Zadania bezpieczeństwa

### Walidacja danych wejściowych
- [x] Dodać walidację wszystkich parametrów URL
- [x] Dodać filtrowanie XSS dla zmiennych `$requestPath` i innych danych wejściowych
- [x] Dodać walidację formularza hasła z limitami długości pola

### Nagłówki bezpieczeństwa
- [x] Dodać Content Security Policy (CSP)
- [x] Dodać X-Content-Type-Options: nosniff
- [x] Dodać X-Frame-Options: DENY
- [x] Dodać X-XSS-Protection: 1; mode=block
- [x] Dodać Strict-Transport-Security (HSTS)

### Sesje i uwierzytelnianie
- [ ] Dodać automatyczne wygasanie sesji po okresie nieaktywności
- [ ] Wdrożyć bezpieczne zarządzanie cookies (flagi HttpOnly i Secure)
- [ ] Dodać zabezpieczenia przed atakami CSRF (tokeny formularzy)
- [ ] Rozważyć silniejsze mechanizmy uwierzytelniania dla treści chronionej

### Logowanie i monitorowanie
- [ ] Dodać logowanie nieudanych prób dostępu do treści chronionej hasłem
- [ ] Dodać logowanie błędów API
- [ ] Dodać mechanizm alertów o podejrzanej aktywności

### Zabezpieczenie API
- [ ] Dodać mechanizm rate limiting dla wywołań API Notion
- [ ] Wdrożyć backoff strategy przy nieudanych żądaniach API
- [ ] Zapewnić obsługę wygaśnięcia tokenów API

### Zarządzanie danymi
- [ ] Przegląd i czyszczenie starych plików cache
- [ ] Dodać szyfrowanie danych wrażliwych w cache
- [ ] Wdrożyć bezpieczne usuwanie danych sesji

### Zgodność i audyt
- [ ] Przeprowadzić skanowanie podatności na zagrożenia
- [ ] Sprawdzić zgodność z RODO/GDPR dla europejskich użytkowników
- [ ] Dokumentacja praktyk bezpieczeństwa

### Testy bezpieczeństwa
- [ ] Dodać testy penetracyjne
- [ ] Przeprowadzić testy odporności na częste ataki (SQL injection, XSS)
- [ ] Testy obciążeniowe dla oceny odporności na ataki DoS

### Komunikacja
- [ ] Dodać stronę z polityką prywatności
- [ ] Przygotować procedury zgłaszania problemów bezpieczeństwa