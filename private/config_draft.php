<?php
// Próba pobrania tokena z zmiennej środowiskowej (preferowane)
$notionApiKey = getenv('NOTION_API_KEY');

// Jeśli nie ma zmiennej środowiskowej, użyj zdefiniowanej wartości
if (!$notionApiKey) {
    // Tylko do celów rozwojowych - w produkcji użyj zmiennych środowiskowych!
    $notionApiKey = 'xxxx'; // Wpisz tutaj swój prawdziwy token API Notion
}

// ID strony Notion, którą chcesz wyświetlić
$notionPageId = 'xxxx';

// Czas wygaśnięcia cache w sekundach (np. 3600 = 1 godzina)
$cacheExpiration = 5;

// Katalog cache
$cacheDir = __DIR__ . '/cache/';

// Upewnij się, że katalog cache istnieje
if (!file_exists($cacheDir) && !is_dir($cacheDir)) {
    if (!mkdir($cacheDir, 0755, true)) {
        die('Nie można utworzyć katalogu cache. Sprawdź uprawnienia.');
    }
}
?>