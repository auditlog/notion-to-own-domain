<?php
// Włącz wyświetlanie wszystkich błędów PHP (tylko do celów deweloperskich)
error_reporting(E_ALL);
ini_set('display_errors', 1);
// --- START SESJI ---
// Musi być na samym początku pliku
session_start(); 
// --- KONIEC START SESJI ---

// Dołączenie konfiguracji (poza katalogiem publicznym)
require_once '../private/config.php';
// Dołączenie funkcji pomocniczych Notion
require_once '../private/notion_utils.php';

// --- OBSŁUGA WERYFIKACJI HASŁA ---
$passwordVerified = $_SESSION['password_verified'] ?? false;
$passwordError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content_password'])) {
    if ($_POST['content_password'] === $contentPassword) {
        $_SESSION['password_verified'] = true;
        $passwordVerified = true;
        // Przekieruj, aby uniknąć ponownego wysłania formularza przy odświeżeniu
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } else {
        $passwordError = true;
    }
}
// --- KONIEC OBSŁUGI WERYFIKACJI HASŁA ---

// Główna logika aplikacji

// Odczytaj ścieżkę z parametru GET dodanego przez .htaccess
$requestPath = $_GET['path'] ?? '';
$requestPath = trim($requestPath, '/'); // Usuń skrajne slashe

$currentPageId = null;
$pageNotFound = false;
$defaultTitle = 'Moja strona z zawartością Notion'; // Zachowaj domyślny
$pageTitle = $defaultTitle; 
$mainPageTitle = $defaultTitle; // Zainicjuj tytuł strony głównej
$ogTitle = $defaultTitle; 
$metaDescription = 'Zawartość strony wyświetlana z Notion.'; 
$currentUrl = ''; 
$pageCoverUrl = null; // Zmienna na URL okładki
$errorMessage = null; // Zainicjuj $errorMessage
$htmlContent = ''; // Zainicjuj $htmlContent

// Określ ID bieżącej strony
if (empty($requestPath)) {
    $currentPageId = $notionPageId; 
} else {
    // --- NOWA LOGIKA DLA ZAGNIEŻDŻONYCH ŚCIEŻEK ---
    $pathSegments = explode('/', $requestPath);
    $currentParentIdToSearch = $notionPageId; // Zacznij od strony głównej
    $resolvedPageId = null;

    foreach ($pathSegments as $segment) {
        if (empty($segment)) { // Pomiń puste segmenty (np. przy podwójnym slashu //)
            continue;
        }
        // Usuń potencjalne query stringi z segmentu, np. title?param=val -> title
        $segmentName = strtok($segment, '?');

        $foundSubpageId = findNotionSubpageId($currentParentIdToSearch, $segmentName, $notionApiKey, $cacheDir, $cacheDurations['subpages']);
        
        if ($foundSubpageId === null) {
            $resolvedPageId = null; // Segment nie został znaleziony, przerwij
            break;
        }
        $resolvedPageId = $foundSubpageId;
        $currentParentIdToSearch = $foundSubpageId; // Następne wyszukiwanie będzie w tej znalezionej podstronie
    }

    if ($resolvedPageId !== null) {
        $currentPageId = $resolvedPageId;
    } else {
        $pageNotFound = true; 
    }
    // --- KONIEC NOWEJ LOGIKI ---
}

// Ustal pełny URL (podstawowa wersja - może wymagać dostosowania do serwera)
if (isset($_SERVER['HTTP_HOST'])) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $currentUrlPath = empty($requestPath) ? '/' : '/' . $requestPath;
    $currentUrl = $protocol . $_SERVER['HTTP_HOST'] . $currentUrlPath;
}

// Procesowanie w zależności od znalezionego ID
if ($pageNotFound) {
    http_response_code(404);
    $errorMessage = "Nie znaleziono strony dla ścieżki: /" . htmlspecialchars($requestPath);
    $pageTitle = 'Nie znaleziono strony'; 
    $ogTitle = $pageTitle;
    $metaDescription = 'Żądana strona nie została znaleziona.';

} elseif ($currentPageId) {
    // --- Pobierz dane strony (tytuł i okładkę) ---
    $pageData = getNotionPageTitle($currentPageId, $notionApiKey, $cacheDir, $cacheDurations['pagedata']);
    $pageTitle = $pageData['title'];
    $pageCoverUrl = $pageData['coverUrl']; // Zapisz URL okładki

    // Ustal tytuł dla Open Graph i tytuł strony głównej (jak poprzednio)
    if (empty($requestPath)) { 
        $ogTitle = $pageTitle;
        $mainPageTitle = $pageTitle; 
    } else { 
        // Pobierz dane strony głównej (potrzebne dla og:title i breadcrumbs)
        $mainPageData = getNotionPageTitle($notionPageId, $notionApiKey, $cacheDir, $cacheDurations['pagedata']);
        $mainPageTitle = $mainPageData['title'];
        $ogTitle = $mainPageTitle . ' - ' . $pageTitle; 
    }
    
    // Ustal opis (można go ulepszyć, np. biorąc fragment tekstu)
    $metaDescription = "Zobacz zawartość strony: " . $pageTitle . ($mainPageTitle !== $pageTitle ? " (część: " . $mainPageTitle . ")" : "") . ". Wyświetlane z Notion.";
    // Ogranicz długość opisu dla bezpieczeństwa
    $metaDescription = mb_substr($metaDescription, 0, 160); 

    // Pobierz treść strony
    $notionData = getNotionContent($currentPageId, $notionApiKey, $cacheDir, $cacheDurations['content']);
    $notionContent = json_decode($notionData, true);

    if (isset($notionContent['error'])) {
        $errorMessage = $notionContent['error'];
        if (isset($notionContent['message'])) {
            $errorMessage .= ': ' . $notionContent['message'];
        }
        // Jeśli Notion zwróciło 404 dla podanego ID, traktuj to jako błąd serwera lub konfiguracji
        if (($notionContent['response_code'] ?? null) === 404) {
             http_response_code(500); 
             $errorMessage = "Błąd konfiguracji: Nie można znaleźć strony Notion o podanym ID ({$currentPageId}). Sprawdź ID w konfiguracji lub czy strona nie została usunięta.";
             $pageTitle = 'Błąd konfiguracji'; 
             $ogTitle = $pageTitle;
             $metaDescription = 'Wystąpił błąd podczas próby załadowania strony z Notion.';
        }
    } else {
        // Renderuj zawartość do HTML
        $htmlContent = notionToHtml($notionContent, $notionApiKey, $cacheDir, $cacheDurations, $requestPath);

        // --- POPRAWIONA LINIA: Usuwanie bloków z encjami HTML ---
        $htmlContent = preg_replace('/&lt;hide&gt;.*?&lt;\/hide&gt;/si', '', $htmlContent);
        
        // --- NOWA LINIA: Przetwarzanie znaczników <pass> ---
        // Przekazujemy wynik weryfikacji hasła ($passwordVerified) i ewentualny błąd ($passwordError)
        $htmlContent = processPasswordTags($htmlContent, $passwordVerified, $passwordError);
        // --- KONIEC NOWEJ LINII ---
    }
} else {
    // Sytuacja awaryjna - nie powinno się zdarzyć przy poprawnej logice
    http_response_code(500);
    $errorMessage = "Wystąpił nieoczekiwany błąd przy określaniu strony do wyświetlenia.";
    $pageTitle = 'Błąd serwera'; 
    $ogTitle = $pageTitle;
    $metaDescription = 'Wystąpił wewnętrzny błąd serwera.';
}

// Dołącz szablon HTML
require '../private/views/main_template.php';

?>