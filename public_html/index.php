<?php
// Włącz wyświetlanie wszystkich błędów PHP (tylko do celów deweloperskich)
error_reporting(E_ALL);
ini_set('display_errors', 1);
// --- START SESJI ---
// Musi być na samym początku pliku
session_start(); 
// --- KONIEC START SESJI ---

// Include configuration (outside public directory)
require_once '../private/config.php';
// Include Notion helper functions
require_once '../private/notion_utils.php';
// Include security headers
require_once '../private/security_headers.php';
// Set security headers
setSecurityHeaders();

// --- PASSWORD VERIFICATION HANDLING ---
$passwordVerified = $_SESSION['password_verified'] ?? false;
$passwordError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content_password'])) {
    // Password validation - limit length and special characters
    $submittedPassword = $_POST['content_password'];

    // Check password length (max 100 characters to prevent DoS attacks)
    if (strlen($submittedPassword) > 100) {
        $passwordError = true;
    } else {
        // Constant-time comparison (protection against timing attacks)
        if (hash_equals($contentPassword, $submittedPassword)) {
            $_SESSION['password_verified'] = true;
            $passwordVerified = true;

            // Redirect to avoid form resubmission on refresh
            // Sanitize URI before redirecting
            $redirectURI = filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL);
            header('Location: ' . $redirectURI);
            exit;
        } else {
            $passwordError = true;
        }
    }
}
// --- END OF PASSWORD VERIFICATION HANDLING ---

// Main application logic

// Read path from GET parameter added by .htaccess
$requestPath = $_GET['path'] ?? '';

// Path validation - remove potentially dangerous characters
$requestPath = filter_var($requestPath, FILTER_SANITIZE_URL);
// Additional protection against path traversal
$requestPath = str_replace(['../', './'], '', $requestPath);
$requestPath = trim($requestPath, '/'); // Remove trailing slashes

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

// Determine full URL (basic version - may need server-specific adjustments)
if (isset($_SERVER['HTTP_HOST'])) {
    // Sanitize host before using
    $httpHost = filter_var($_SERVER['HTTP_HOST'], FILTER_SANITIZE_URL);

    // Validate host format (only allowed characters)
    if (!preg_match('/^[a-zA-Z0-9\-\.]+(\:[0-9]+)?$/', $httpHost)) {
        $httpHost = 'localhost'; // Default value for invalid host
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ||
                ($_SERVER['SERVER_PORT'] ?? null) == 443) ? "https://" : "http://";

    $currentUrlPath = empty($requestPath) ? '/' : '/' . $requestPath;
    $currentUrl = $protocol . $httpHost . $currentUrlPath;
}

// Processing based on the found ID
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
    $metaDescription = substr($metaDescription, 0, 160); 

    // Pobierz treść strony
    $notionData = getNotionContent($currentPageId, $notionApiKey, $cacheDir, $cacheDurations['content']);
    $notionContent = json_decode($notionData, true);

    if (isset($notionContent['error'])) {
        // Sanityzacja komunikatów błędów
        $errorMessage = htmlspecialchars($notionContent['error']);
        if (isset($notionContent['message'])) {
            $errorMessage .= ': ' . htmlspecialchars($notionContent['message']);
        }
        // Jeśli Notion zwróciło 404 dla podanego ID, traktuj to jako błąd serwera lub konfiguracji
        if (($notionContent['response_code'] ?? null) === 404) {
             http_response_code(500);
             // Bezpieczne pokazywanie ID
             $safeCurrentPageId = htmlspecialchars($currentPageId);
             $errorMessage = "Błąd konfiguracji: Nie można znaleźć strony Notion o podanym ID ({$safeCurrentPageId}). Sprawdź ID w konfiguracji lub czy strona nie została usunięta.";
             $pageTitle = 'Błąd konfiguracji';
             $ogTitle = $pageTitle;
             $metaDescription = 'Wystąpił błąd podczas próby załadowania strony z Notion.';
        }
    } else {
        // Renderuj zawartość do HTML
        $htmlContent = notionToHtml($notionContent, $notionApiKey, $cacheDir, $cacheDurations, $requestPath);

        // --- IMPROVED LINE: Removing blocks with HTML entities ---
        $htmlContent = preg_replace('/&lt;hide&gt;.*?&lt;\/hide&gt;/si', '', $htmlContent);

        // --- NEW LINE: Processing <pass> tags ---
        // Pass the password verification result ($passwordVerified) and any error ($passwordError)
        $htmlContent = processPasswordTags($htmlContent, $passwordVerified, $passwordError);

        // Additional protection against XSS from untrusted sources
        // Remove potentially dangerous JavaScript scripts
        $htmlContent = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $htmlContent);
        // Remove potentially dangerous attributes like onclick, onerror, etc.
        $htmlContent = preg_replace('/\s+on\w+\s*=\s*(["\']).*?\1/i', '', $htmlContent);
        // Remove dangerous javascript: links
        $htmlContent = preg_replace('/href\s*=\s*(["\'])javascript:.*?\1/i', 'href="#"', $htmlContent);
        // --- END OF NEW LINE ---
    }
} else {
    // Emergency situation - should not happen with correct logic
    http_response_code(500);
    $errorMessage = "Wystąpił nieoczekiwany błąd przy określaniu strony do wyświetlenia.";
    $pageTitle = 'Błąd serwera'; 
    $ogTitle = $pageTitle;
    $metaDescription = 'Wystąpił wewnętrzny błąd serwera.';
}

// Dołącz szablon HTML
require '../private/views/main_template.php';

?>