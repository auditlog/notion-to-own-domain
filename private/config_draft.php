<?php
// Próba pobrania tokena z zmiennej środowiskowej (preferowane)
$notionApiKey = getenv('NOTION_API_KEY');

// Jeśli nie ma zmiennej środowiskowej, użyj zdefiniowanej wartości
if (!$notionApiKey) {
    // PAMIĘTAJ: W środowisku produkcyjnym zalecane jest użycie zmiennych środowiskowych dla klucza API.
    // Poniższa wartość jest tylko przykładem i powinna zostać zastąpiona Twoim rzeczywistym kluczem API Notion.
    $notionApiKey = 'YOUR_NOTION_API_KEY_HERE'; // Wpisz tutaj swój prawdziwy token API Notion
}

// ID strony Notion, którą chcesz wyświetlić
$notionPageId = 'YOUR_NOTION_PAGE_ID_HERE'; // Wpisz tutaj ID głównej strony Notion

// --- Konfiguracja czasów życia pamięci podręcznej (w sekundach) ---
// Pozwala na różne czasy dla różnych typów danych dla optymalizacji.
$cacheDurations = [
    // Zawartość bloków strony (np. paragrafy, obrazy, tekst)
    // Częściej odświeżane, jeśli zawartość strony często się zmienia.
    'content'  => 3600,     // Przykładowo: 1 godzina (3600s)

    // Metadane strony (tytuł, URL okładki) oraz dane dla wzmianek (@page)
    // Zazwyczaj zmieniają się rzadziej niż treść bloków.
    'pagedata' => 7200,     // Przykładowo: 2 godziny (7200s)

    // Lista podstron dla danej strony rodzica (używane do routingu)
    // Odświeżane, gdy dodasz/usuniesz podstronę lub zmienisz jej tytuł.
    'subpages' => 86400     // Przykładowo: 1 dzień (86400s)
];

// Domyślny czas wygaśnięcia cache w sekundach.
// Używany, jeśli index.php nie zostałby w pełni dostosowany do $cacheDurations
// lub jako fallback. Zaleca się, aby index.php używał wartości z $cacheDurations.
$cacheExpiration = $cacheDurations['content']; // Przykładowo, domyślnie jak dla zawartości
// --- Koniec konfiguracji czasów życia pamięci podręcznej ---


// Katalog cache (domyślnie: katalog `cache` w tym samym folderze co ten plik)
$cacheDir = __DIR__ . '/cache/';

// --- Hasło do treści chronionej tagami <pass> ---
// Jeśli nie używasz tej funkcji, możesz zostawić domyślną wartość lub ją usunąć.
$contentPassword = 'twoje_sekretne_haslo'; // Zmień na silne hasło, jeśli używasz tej funkcji!
// --- KONIEC Hasła do treści chronionej ---

// Upewnij się, że katalog cache istnieje i ma odpowiednie uprawnienia
// Skrypt spróbuje utworzyć katalog cache, jeśli nie istnieje.
if (!file_exists($cacheDir) && !is_dir($cacheDir)) {
    // 0755 to typowe uprawnienia: właściciel ma pełne prawa, grupa i inni mogą czytać i wykonywać.
    if (!mkdir($cacheDir, 0755, true)) {
        // Jeśli tworzenie katalogu się nie powiedzie, skrypt zakończy działanie.
        // Upewnij się, że serwer WWW ma uprawnienia do zapisu w katalogu nadrzędnym (private/).
        die('Nie można utworzyć katalogu cache. Sprawdź uprawnienia do katalogu `private/`.');
    }
}
?>