<?php
// Próba pobrania tokena z zmiennej środowiskowej (preferowane)
$notionApiKey = getenv('NOTION_API_KEY');

// Jeśli nie ma zmiennej środowiskowej, użyj zdefiniowanej wartości
if (!$notionApiKey) {
    // Tylko do celów rozwojowych - w produkcji użyj zmiennych środowiskowych!
    $notionApiKey = 'secret_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'; // Wpisz tutaj swój prawdziwy token API Notion
}

// ID strony Notion, którą chcesz wyświetlić
$notionPageId = 'XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX';

// Parametry konfiguracji cache
$cacheDurations = [
    'content' => 3600,     // Zawartość strony: 1 godzina
    'pagedata' => 7200,    // Metadane strony: 2 godziny
    'subpages' => 86400,   // Lista podstron: 1 dzień
    'mentions' => 604800   // Wzmianki stron: 1 tydzień
];

// Domyślny czas wygaśnięcia cache w sekundach
$cacheExpiration = $cacheDurations['content']; // 3600 = 1 godzina

// Katalog cache
$cacheDir = __DIR__ . '/cache/';

// --- NOWA ZMIENNA: Hasło do ukrytej treści ---
$contentPassword = 'your_secret_password_here'; // Zmień na silne hasło! Używane przez tagi <pass>
// --- KONIEC NOWEJ ZMIENNEJ ---

// Upewnij się, że katalog cache istnieje
if (!file_exists($cacheDir) && !is_dir($cacheDir)) {
    if (!mkdir($cacheDir, 0755, true)) {
        die('Nie można utworzyć katalogu cache. Sprawdź uprawnienia.');
    }
}
?>