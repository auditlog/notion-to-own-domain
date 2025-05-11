<?php
// --- START SESJI ---
// Musi być na samym początku pliku
session_start(); 
// --- KONIEC START SESJI ---

// Dołączenie konfiguracji (poza katalogiem publicznym)
require_once '../private/config.php';

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

// Funkcja pobierająca zawartość z Notion
function getNotionContent($pageId, $apiKey, $cacheDir, $specificCacheExpiration) {
    $cacheFile = $cacheDir . 'content_' . md5($pageId) . '.cache';

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $specificCacheExpiration)) {
        // Zwróć zdeserializowane dane, aby pętla działała poprawnie z cachem
        $cachedContent = file_get_contents($cacheFile);
        $decodedCachedContent = json_decode($cachedContent, true);
        // Sprawdź, czy cache zawiera już zagregowane wyniki (np. po kluczu 'all_results')
        // lub czy to stary format cache'u (tylko jedna strona)
        // Na potrzeby tego przykładu zakładamy, że cache przechowuje już zagregowane wyniki.
        // Jeśli nie, logika cachowania też musi być dostosowana.
        if (isset($decodedCachedContent['all_results_aggregated'])) { // Wprowadźmy flagę/strukturę
            return $cachedContent; // Zwróć oryginalny JSON, jeśli cache jest już kompletny
        }
        // Jeśli cache jest stary, można go zignorować i pobrać od nowa, lub próbować dopełnić.
        // Dla uproszczenia, przy starym cache, pobierzemy od nowa.
    }

    $allResults = [];
    $nextCursor = null;
    $errorData = null; // Do przechowywania informacji o błędzie

    do {
        $url = "https://api.notion.com/v1/blocks/{$pageId}/children?page_size=100";
        if ($nextCursor) {
            $url .= "&start_cursor=" . urlencode($nextCursor);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Notion-Version: 2022-06-28'
        ]);
        // Upewnij się, że masz tu konfigurację SSL (np. curl.cainfo w php.ini)
        // lub jeśli to konieczne (TYLKO DLA TESTÓW LOKALNYCH):
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode != 200) {
            $errorData = [ // Zapisz dane błędu
                'error' => 'Nie można pobrać zawartości z Notion. Kod: ' . $httpCode,
                'message' => $curlError ?: 'Brak dodatkowych informacji o błędzie cURL.',
                'response_code' => $httpCode
            ];
            break; // Przerwij pętlę w przypadku błędu
        }

        $data = json_decode($response, true);

        if (isset($data['results']) && is_array($data['results'])) {
            $allResults = array_merge($allResults, $data['results']);
        } else {
            // Jeśli 'results' nie ma, to może być błąd w odpowiedzi JSON od Notion
            $errorData = [
                'error' => 'Nieprawidłowa odpowiedź z API Notion.',
                'message' => 'Brak klucza "results" w odpowiedzi.',
                'response_code' => $httpCode
            ];
            break; 
        }

        $nextCursor = $data['next_cursor'] ?? null;
        $hasMore = $data['has_more'] ?? false;

    } while ($hasMore && $nextCursor);

    if ($errorData) { // Jeśli wystąpił błąd w pętli
        return json_encode($errorData);
    }

    // Zbuduj ostateczną odpowiedź w formacie oczekiwanym przez resztę kodu
    // (czyli z kluczem 'results' zawierającym wszystkie połączone wyniki)
    $finalResponseData = [
        'object' => 'list', // Typowy dla odpowiedzi z listą bloków
        'results' => $allResults,
        'has_more' => false, // Ponieważ pobraliśmy wszystko
        'next_cursor' => null,
        'all_results_aggregated' => true // Nasza flaga dla logiki cache
    ];
    
    $finalJsonResponse = json_encode($finalResponseData);
    file_put_contents($cacheFile, $finalJsonResponse);
    return $finalJsonResponse;
}

// Funkcja do obsługi zagnieżdżonych bloków (do przyszłej implementacji)
function fetchAndRenderChildren($blockId, $apiKey, $cacheDir, $specificContentCacheExpiration, $currentUrlPathString = '') {
    $childrenData = getNotionContent($blockId, $apiKey, $cacheDir, $specificContentCacheExpiration);
    $childrenContent = json_decode($childrenData, true);
    global $cacheDurations; // Dostęp do globalnej tablicy
    return notionToHtml($childrenContent, $apiKey, $cacheDir, $cacheDurations, $currentUrlPathString);
}

// --- NOWA FUNKCJA POMOCNICZA: Normalizuje tytuł na potrzeby ścieżki URL ---
function normalizeTitleForPath($title) {
    $path = strtolower($title);
    $path = str_replace(' ', '-', $path); 
    // Usuń znaki inne niż litery, cyfry i myślniki
    $path = preg_replace('/[^a-z0-9\-]/', '', $path); 
    // Usuń wielokrotne myślniki
    $path = preg_replace('/-+/', '-', $path); 
    $path = trim($path, '-');
    return $path;
}

// --- Zaktualizuj funkcję findNotionSubpageId, aby używała nowej funkcji pomocniczej ---
function findNotionSubpageId($parentPageId, $subpagePath, $apiKey, $cacheDir, $specificSubpagesCacheExpiration) {
    $subpagePath = trim(strtolower($subpagePath), '/'); 
    if (empty($subpagePath)) return null; 

    $cacheFile = $cacheDir . 'subpages_' . md5($parentPageId) . '.cache';
    $subpages = [];

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $specificSubpagesCacheExpiration)) {
        $subpages = json_decode(file_get_contents($cacheFile), true);
         // Sprawdź, czy $subpages jest tablicą po dekodowaniu
         if (!is_array($subpages)) {
            $subpages = []; // Zainicjuj jako pustą tablicę w razie błędu dekodowania
            // Opcjonalnie: usuń uszkodzony plik cache
            unlink($cacheFile); 
         }
    } else {
        // Pobierz bloki potomne strony nadrzędnej
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.notion.com/v1/blocks/{$parentPageId}/children?page_size=100");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Notion-Version: 2022-06-28'
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200) {
            $data = json_decode($response, true);
            if (isset($data['results'])) {
                foreach ($data['results'] as $block) {
                    if ($block['type'] === 'child_page' && isset($block['child_page']['title'])) {
                        $title = $block['child_page']['title'];
                        // Użyj nowej funkcji do normalizacji
                        $normalizedTitle = normalizeTitleForPath($title); 
                        if (!empty($normalizedTitle)) { // Upewnij się, że ścieżka nie jest pusta
                           $subpages[$normalizedTitle] = $block['id'];
                        }
                    }
                }
                file_put_contents($cacheFile, json_encode($subpages));
            }
        } else {
            error_log("Nie można pobrać listy podstron dla {$parentPageId}. Kod: {$httpCode}");
        }
    }
    
    // Zwróć ID strony lub null
    return $subpages[$subpagePath] ?? null;
}

// --- ZMODYFIKOWANA FUNKCJA: Pobiera tytuł i URL okładki strony Notion ---
// Zwraca: ['title' => string, 'coverUrl' => string|null]
function getNotionPageTitle($pageId, $apiKey, $cacheDir, $specificPagedataCacheExpiration) {
    // Zmieniono nazwę cache - przechowuje teraz obiekt/tablicę
    $cacheFile = $cacheDir . 'pagedata_' . md5($pageId) . '.cache'; 
    $defaultTitle = 'Moja strona z zawartością Notion'; 
    $defaultResult = ['title' => $defaultTitle, 'coverUrl' => null];

    // Sprawdź cache
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $specificPagedataCacheExpiration)) {
        $cachedData = json_decode(file_get_contents($cacheFile), true);
        // Zwróć dane z cache, jeśli są poprawne (tablica z kluczem 'title')
        if (is_array($cachedData) && isset($cachedData['title'])) {
            return $cachedData;
        }
    }

    // Jeśli nie ma w cache, pobierz z API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.notion.com/v1/pages/{$pageId}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Notion-Version: 2022-06-28'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = $defaultResult; // Ustaw domyślny wynik

    if ($httpCode == 200) {
        $data = json_decode($response, true);
        
        // Pobierz tytuł (jak poprzednio)
        if (isset($data['properties']['title']['title'][0]['plain_text'])) {
            $result['title'] = $data['properties']['title']['title'][0]['plain_text'];
        } elseif (isset($data['properties']['Name']['title'][0]['plain_text'])) {
            $result['title'] = $data['properties']['Name']['title'][0]['plain_text'];
        } else {
             $result['title'] = $defaultTitle; // Użyj domyślnego jeśli nie znaleziono
        }

        // Pobierz URL okładki
        if (isset($data['cover'])) {
            if ($data['cover']['type'] === 'external' && isset($data['cover']['external']['url'])) {
                $result['coverUrl'] = $data['cover']['external']['url'];
            } elseif ($data['cover']['type'] === 'file' && isset($data['cover']['file']['url'])) {
                $result['coverUrl'] = $data['cover']['file']['url'];
            }
        }
        
        // Zapisz cały wynik (tablicę) do cache jako JSON
        file_put_contents($cacheFile, json_encode($result));

    } else {
        error_log("Nie można pobrać danych strony Notion (tytuł/okładka) dla ID: {$pageId}. Kod: {$httpCode}");
        // Nie zapisuj cache w przypadku błędu, zwracamy $defaultResult
    }

    return $result;
}

// --- ZAKTUALIZOWANA Funkcja formatRichText (z pobieraniem tytułu dla wzmianek) ---
// Dodano parametry $apiKey, $cacheDir, $cacheExpiration
function formatRichText($richTextArray, $apiKey, $cacheDir, $specificPagedataCacheExpiration, $currentUrlPathString = '') {
    $text = '';
    
    if (!is_array($richTextArray)) {
        return ''; 
    }

    foreach ($richTextArray as $richText) {
        $formattedText = ''; 
        $type = $richText['type'] ?? 'text'; 

        if ($type === 'mention') {
            if (isset($richText['mention']['type']) && $richText['mention']['type'] === 'page' && isset($richText['mention']['page']['id'])) {
                // --- Pobieranie tytułu na podstawie ID strony ---
                $mentionedPageId = $richText['mention']['page']['id'];
                $mentionedPageTitle = 'Untitled'; // Domyślny tytuł na wypadek błędu

                // Spróbuj pobrać prawdziwy tytuł strony za pomocą istniejącej funkcji
                $fetchedPageData = getNotionPageTitle($mentionedPageId, $apiKey, $cacheDir, $specificPagedataCacheExpiration);
                $mentionedPageTitle = $fetchedPageData['title'] ?? $mentionedPageTitle;
                
                // Użyj pobranego tytułu, jeśli nie jest pusty i różni się od domyślnego z getNotionPageTitle
                if (empty($mentionedPageTitle) || $mentionedPageTitle === 'Moja strona z zawartością Notion') { 
                    // Jeśli pobieranie się nie powiodło lub zwróciło domyślny tytuł, użyj ID jako fallback
                    $mentionedPageTitle = $richText['plain_text'] ?: $mentionedPageId; // Użyj plain_text jeśli jest, inaczej ID
                    error_log("formatRichText: Nie udało się pobrać poprawnego tytułu dla strony ID: {$mentionedPageId}. Użyto: '{$mentionedPageTitle}'");
                }

                // Zawsze próbuj wygenerować ścieżkę na podstawie (najlepiej pobranego) tytułu
                $path = normalizeTitleForPath($mentionedPageTitle); 

                if (!empty($path)) {
                    // Poprawione tworzenie pełnej ścieżki
                    $basePath = !empty($currentUrlPathString) ? rtrim($currentUrlPathString, '/') : '';
                    // Sprawdź, czy $path nie jest już pełną ścieżką zagnieżdżoną (np. po kliknięciu na link do pod-podstrony)
                    // Ta logika jest bardziej skomplikowana, na razie upraszczamy do prostego łączenia,
                    // zakładając, że wspomniana strona jest bezpośrednią podstroną bieżącego kontekstu.
                    // Dla bardziej zaawansowanej logiki, można by sprawdzać, czy $path już zawiera slashe
                    // i czy $currentUrlPathString nie jest już jego częścią.
                    // Na razie: jeśli jesteśmy na "strona-a", a wzmianka to "strona-b", link to "/strona-a/strona-b"
                    // Jeśli jesteśmy na "/", a wzmianka to "strona-a", link to "/strona-a"
                    $fullPath = !empty($basePath) ? $basePath . '/' . $path : $path;
                    $formattedText = '<a href="/' . htmlspecialchars(ltrim($fullPath, '/')) . '">' . htmlspecialchars($mentionedPageTitle) . '</a>';
                } else {
                    // Jeśli ścieżka jest pusta, wyświetl sam tekst (tytuł lub ID)
                    $formattedText = htmlspecialchars($mentionedPageTitle);
                }

            } else {
                 // Inne typy wzmianek (np. data, użytkownik)
                 $formattedText = htmlspecialchars($richText['plain_text'] ?? '');
            }

        } else if ($type === 'text') {
            $currentText = htmlspecialchars($richText['plain_text'] ?? ''); 
            if (isset($richText['annotations'])) { 
                $annotations = $richText['annotations'];
                if ($annotations['bold']) { $currentText = "<strong>{$currentText}</strong>"; }
                if ($annotations['italic']) { $currentText = "<em>{$currentText}</em>"; }
                if ($annotations['strikethrough']) { $currentText = "<del>{$currentText}</del>"; }
                if ($annotations['underline']) { $currentText = "<u>{$currentText}</u>"; }
                if ($annotations['code']) { $currentText = "<code>{$currentText}</code>"; }
                // Obsługa kolorów
                if (isset($annotations['color']) && $annotations['color'] !== 'default') {
                    // Użyj klas CSS dla kolorów dla lepszej stylizacji i możliwości dostosowania
                    // np. notion-color-blue, notion-bg-yellow
                    $colorClass = 'notion-' . str_replace('_background', '-bg', $annotations['color']);
                    $currentText = "<span class=\"" . htmlspecialchars($colorClass) . "\">{$currentText}</span>";
                }
             }
            if (isset($richText['href']) && $richText['href']) { 
                $currentText = "<a href=\"" . htmlspecialchars($richText['href']) . "\" target=\"_blank\">" . htmlspecialchars($currentText) . "</a>";
             }
            $formattedText = $currentText;
        // --- NOWA OBSŁUGA: Równanie w linii ---
        } else if ($type === 'equation') {
            $expression = htmlspecialchars($richText['equation']['expression'] ?? '');
            // Dla KaTeX, użyj odpowiednich ograniczników lub klas.
            // Standardowe auto-renderowanie KaTeX szuka \( ... \) dla równań w linii.
            $formattedText = '\\(' . $expression . '\\)'; // Poprawione escapowanie dla KaTeX
            // Alternatywnie, użyj klasy i pozwól skryptowi KaTeX znaleźć to:
            // $formattedText = "<span class=\"math-inline\">{$expression}</span>";
        // --- KONIEC NOWEJ OBSŁUGI ---
        } else {
             $formattedText = htmlspecialchars($richText['plain_text'] ?? '');
        }
        
        $text .= $formattedText;
    }

    return $text;
}

// Konwersja z formatu Notion na HTML (rozszerzona implementacja)
function notionToHtml($content, $apiKey, $cacheDir, $cacheDurationsArray, $currentUrlPathString = '') {
    $html = '';
    $inList = false;
    $listType = ''; // 'ul' lub 'ol'

    if (isset($content['error'])) {
        $httpCode = $content['response_code'] ?? null;
        if ($httpCode === 404) {
             return "<div class=\"error-message\">Błąd: Nie znaleziono strony Notion (ID może być nieprawidłowy).</div>";
        }
        return "<div class=\"error-message\">Błąd pobierania danych z Notion: {$content['error']}</div>";
    }
    
    if (isset($content['results']) && is_array($content['results'])) {
        foreach ($content['results'] as $block) {
            $currentBlockType = $block['type'];
            $isListItem = in_array($currentBlockType, ['bulleted_list_item', 'numbered_list_item']);

            // Zarządzanie zamykaniem listy, gdy przechodzimy do elementu niebędącego elementem listy
            if ($inList && !$isListItem) {
                 $html .= "</{$listType}>\n";
                 $inList = false;
                 $listType = '';
            }

            switch ($currentBlockType) {
                case 'paragraph':
                    $text = formatRichText($block['paragraph']['rich_text'], $apiKey, $cacheDir, $cacheDurationsArray['pagedata'], $currentUrlPathString);
                    if (!empty($text) || (isset($block['paragraph']['rich_text']) && empty($block['paragraph']['rich_text']))) { // Renderuj <p> nawet dla pustych tekstów, jeśli blok istnieje
                        $html .= "<p>{$text}</p>\n";
                    } else { // Kiedyś było &nbsp; ale Notion czasem zwraca puste paragrafy
                        $html .= "<p></p>\n"; 
                    }
                    break;
                    
                case 'heading_1':
                case 'heading_2':
                case 'heading_3':
                    $key = $currentBlockType; 
                    $level = substr($key, -1); 
                    
                    if (is_numeric($level) && $level >= 1 && $level <= 6) { 
                        $tagName = 'h' . $level; 
                        $text = formatRichText($block[$key]['rich_text'], $apiKey, $cacheDir, $cacheDurationsArray['pagedata'], $currentUrlPathString);
                        $html .= "<{$tagName}>{$text}</{$tagName}>\n";
                    } else {
                        error_log("Nieoczekiwany lub niepoprawny typ nagłówka w notionToHtml: " . $key);
                        $text = formatRichText($block[$key]['rich_text'], $apiKey, $cacheDir, $cacheDurationsArray['pagedata'], $currentUrlPathString);
                        $html .= "<p><strong>(Błąd nagłówka: {$key})</strong> {$text}</p>\n";
                    }
                    break; 
                    
                case 'bulleted_list_item':
                case 'numbered_list_item':
                    $itemKey = $currentBlockType;
                    $itemBlock = $block[$itemKey];
                    $itemText = formatRichText($itemBlock['rich_text'], $apiKey, $cacheDir, $cacheDurationsArray['pagedata'], $currentUrlPathString);
                    $expectedListTag = ($currentBlockType === 'bulleted_list_item') ? 'ul' : 'ol';

                    if (!$inList || $listType !== $expectedListTag) {
                        if ($inList) { 
                            $html .= "</{$listType}>\n"; 
                        }
                        $html .= "<{$expectedListTag}>\n"; 
                        $inList = true;
                        $listType = $expectedListTag;
                    }

                    $html .= "<li>{$itemText}"; 

                    if (isset($block['has_children']) && $block['has_children']) {
                        $childrenHtml = fetchAndRenderChildren($block['id'], $apiKey, $cacheDir, $cacheDurationsArray['content'], $currentUrlPathString);
                        $html .= $childrenHtml; // Dzieci są renderowane wewnątrz <li>
                    }
                    $html .= "</li>\n"; 
                    break;
                    
                case 'to_do':
                    $text = formatRichText($block['to_do']['rich_text'], $apiKey, $cacheDir, $cacheDurationsArray['pagedata'], $currentUrlPathString);
                    $checked = $block['to_do']['checked'] ? ' checked' : '';
                    $html .= "<div class=\"todo-item\"><label><input type=\"checkbox\"{$checked} disabled> {$text}</label></div>\n";
                    break;
                    
                case 'image':
                    $captionText = '';
                    if (isset($block['image']['caption']) && !empty($block['image']['caption'])) {
                        $captionText = formatRichText($block['image']['caption'], $apiKey, $cacheDir, $cacheDurationsArray['pagedata'], $currentUrlPathString);
                    }
                    
                    $imageUrl = '';
                    if (isset($block['image']['file']['url'])) {
                        $imageUrl = $block['image']['file']['url'];
                    } elseif (isset($block['image']['external']['url'])) {
                        $imageUrl = $block['image']['external']['url'];
                    }
                    
                    if ($imageUrl) {
                        $html .= "<figure>";
                        $html .= "<img src=\"{$imageUrl}\" alt=\"" . htmlspecialchars(strip_tags($captionText) ?: 'Obrazek') . "\">"; // strip_tags dla alt
                        if ($captionText) {
                            $html .= "<figcaption>{$captionText}</figcaption>";
                        }
                        $html .= "</figure>\n";
                    }
                    break;
                    
                case 'divider':
                    $html .= "<hr>\n";
                    break;
                    
                case 'code':
                    $language = isset($block['code']['language']) ? htmlspecialchars($block['code']['language']) : 'plaintext'; 
                    $codeContent = formatRichText($block['code']['rich_text'], $apiKey, $cacheDir, $cacheDurationsArray['pagedata'], $currentUrlPathString);
                    $langClass = !empty($language) ? " class=\"language-{$language}\"" : ' class=\"language-plaintext\"'; // Zawsze dodaj klasę
                    $html .= "<pre><code{$langClass}>{$codeContent}</code></pre>\n"; 
                    break;
                    
                case 'quote':
                    $text = formatRichText($block['quote']['rich_text'], $apiKey, $cacheDir, $cacheDurationsArray['pagedata'], $currentUrlPathString);
                    $html .= "<blockquote>{$text}</blockquote>\n";
                    break;
                    
                case 'callout':
                    $text = formatRichText($block['callout']['rich_text'], $apiKey, $cacheDir, $cacheDurationsArray['pagedata'], $currentUrlPathString);
                    $iconHtml = '';
                    if (isset($block['callout']['icon'])) {
                        if (isset($block['callout']['icon']['emoji'])) {
                            $iconHtml = "<span class=\"callout-emoji\">" . htmlspecialchars($block['callout']['icon']['emoji']) . "</span> ";
                        } elseif (isset($block['callout']['icon']['external']['url'])) {
                            $iconUrl = $block['callout']['icon']['external']['url'];
                            $iconHtml = "<img src=\"{$iconUrl}\" alt=\"ikona\" class=\"callout-icon-external\"> ";
                        }
                    }
                    $html .= "<div class=\"callout\">{$iconHtml}{$text}</div>\n";
                    break;
                    
                case 'table':
                    // ... (istniejąca logika tabeli pozostaje bez zmian, zakładając, że działa poprawnie)
                    // Należy upewnić się, że formatRichText jest wywoływany dla komórek
                    $tableBlockId = $block['id'];
                    $hasColumnHeader = $block['table']['has_column_header'] ?? false;
                    $hasRowHeader = $block['table']['has_row_header'] ?? false;

                    $tableRowsData = getNotionContent($tableBlockId, $apiKey, $cacheDir, $cacheDurationsArray['content']);
                    $tableRowsContent = json_decode($tableRowsData, true);

                    if (isset($tableRowsContent['results']) && is_array($tableRowsContent['results'])) {
                        $html .= "<div class=\"table-wrapper\"><table class=\"notion-table\">\n";
                        $rows = $tableRowsContent['results'];
                        
                        if ($hasColumnHeader && !empty($rows)) {
                            $headerRowBlock = array_shift($rows); 
                            if (isset($headerRowBlock['table_row']['cells'])) {
                                $html .= "<thead><tr>\n";
                                $cellIndex = 0;
                                foreach ($headerRowBlock['table_row']['cells'] as $cellRichTextArray) {
                                    $tag = ($hasRowHeader && $cellIndex === 0 && !$hasColumnHeader) ? 'td' : 'th'; // Specjalny przypadek dla pierwszej komórki bez nagłówka kolumn, ale z nagłówkiem wiersza
                                    $cellContent = formatRichText($cellRichTextArray, $apiKey, $cacheDir, $cacheDurationsArray['pagedata'], $currentUrlPathString);
                                    $html .= "<{$tag}>{$cellContent}</{$tag}>\n";
                                    $cellIndex++;
                                }
                                $html .= "</tr></thead>\n";
                            }
                        }

                        $html .= "<tbody>\n";
                        foreach ($rows as $rowBlock) {
                           if (isset($rowBlock['table_row']['cells'])) {
                                $html .= "<tr>\n";
                                $cellIndex = 0;
                                foreach ($rowBlock['table_row']['cells'] as $cellRichTextArray) {
                                    $tag = ($hasRowHeader && $cellIndex === 0) ? 'th' : 'td'; 
                                    $cellContent = formatRichText($cellRichTextArray, $apiKey, $cacheDir, $cacheDurationsArray['pagedata'], $currentUrlPathString);
                                    $html .= "<{$tag}>{$cellContent}</{$tag}>\n";
                                    $cellIndex++;
                                }
                                $html .= "</tr>\n";
                           }
                        }
                        $html .= "</tbody>\n";
                        $html .= "</table></div>\n";
                    } else {
                         $html .= "<div class=\"table-placeholder\">Nie można załadować zawartości tabeli.</div>\n";
                         if(isset($tableRowsContent['error'])) {
                              error_log("Błąd pobierania wierszy tabeli ({$tableBlockId}): " . $tableRowsContent['error']);
                         }
                    }
                    break;
                
                case 'child_page':
                    if (isset($block['child_page']['title'])) {
                        $title = $block['child_page']['title'];
                        $pathSegment = normalizeTitleForPath($title); 
                        if (!empty($pathSegment)) {
                           // Poprawione tworzenie pełnej ścieżki dla child_page
                           $basePath = !empty($currentUrlPathString) ? rtrim($currentUrlPathString, '/') : '';
                           $fullPath = !empty($basePath) ? $basePath . '/' . $pathSegment : $pathSegment;
                           $html .= "<p class=\"child-page-link\"><a href=\"/" . htmlspecialchars(ltrim($fullPath, '/')) . "\">📄 " . htmlspecialchars($title) . "</a></p>\n"; 
                        } else {
                           $html .= "<p class=\"child-page-link\"><em>(Podstrona bez popranego tytułu)</em></p>\n";
                        }
                    }
                    break;

                // --- NOWE TYPY BLOKÓW ---
                case 'toggle':
                    $summaryText = formatRichText($block['toggle']['rich_text'], $apiKey, $cacheDir, $cacheDurationsArray['pagedata'], $currentUrlPathString);
                    $html .= "<details class=\"notion-toggle\"><summary>{$summaryText}</summary>";
                    if (isset($block['has_children']) && $block['has_children']) {
                        $html .= "<div class=\"notion-toggle-content\">";
                        $html .= fetchAndRenderChildren($block['id'], $apiKey, $cacheDir, $cacheDurationsArray['content'], $currentUrlPathString);
                        $html .= "</div>";
                    }
                    $html .= "</details>\n";
                    break;

                case 'bookmark':
                    $url = $block['bookmark']['url'] ?? '#';
                    $captionArray = $block['bookmark']['caption'] ?? [];
                    $captionText = formatRichText($captionArray, $apiKey, $cacheDir, $cacheDurationsArray['pagedata'], $currentUrlPathString);
                    // Notion API nie dostarcza metadanych (tytuł, opis, ikona) dla zakładek.
                    // Można by je pobrać serwerowo (wolne) lub zaimplementować po stronie klienta.
                    // Na razie prosty link.
                    $html .= "<div class=\"notion-bookmark\">";
                    $html .= "<a href=\"{$url}\" target=\"_blank\" rel=\"noopener noreferrer\">" . htmlspecialchars($url) . "</a>";
                    if (!empty($captionText)) {
                        $html .= "<div class=\"caption\">{$captionText}</div>";
                    }
                    $html .= "</div>\n";
                    break;

                case 'embed':
                    $url = $block['embed']['url'] ?? '';
                    $captionArray = $block['embed']['caption'] ?? [];
                    $captionText = formatRichText($captionArray, $apiKey, $cacheDir, $cacheDurationsArray['pagedata'], $currentUrlPathString);
                    $html .= "<div class=\"notion-embed\">";
                    if (filter_var($url, FILTER_VALIDATE_URL)) {
                        // Prosta logika dla YouTube i Vimeo, można rozbudować
                        if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match)) {
                            $embedUrl = "https://www.youtube.com/embed/" . htmlspecialchars($match[1]);
                            $html .= "<iframe src=\"{$embedUrl}\" frameborder=\"0\" allow=\"accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture\" allowfullscreen></iframe>";
                        } elseif (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/i', $url, $match)) {
                            $embedUrl = "https://player.vimeo.com/video/" . htmlspecialchars($match[1]);
                            $html .= "<iframe src=\"{$embedUrl}\" frameborder=\"0\" allow=\"autoplay; fullscreen; picture-in-picture\" allowfullscreen></iframe>";
                        } else {
                            // Ogólny iframe dla innych źródeł (może nie działać przez X-Frame-Options)
                            $html .= "<iframe src=\"" . htmlspecialchars($url) . "\" frameborder=\"0\" allowfullscreen></iframe>";
                        }
                    } else {
                        $html .= "<p>Nieprawidłowy URL dla osadzenia: " . htmlspecialchars($url) . "</p>";
                    }
                    if (!empty($captionText)) {
                        $html .= "<div class=\"caption\">{$captionText}</div>";
                    }
                    $html .= "</div>\n";
                    break;
                
                case 'video':
                    $videoUrl = '';
                    $captionArray = $block['video']['caption'] ?? [];
                    $captionText = formatRichText($captionArray, $apiKey, $cacheDir, $cacheDurationsArray['pagedata'], $currentUrlPathString);
                    $videoType = $block['video']['type'] ?? null;

                    if ($videoType === 'external' && isset($block['video']['external']['url'])) {
                        $videoUrl = $block['video']['external']['url'];
                    } elseif ($videoType === 'file' && isset($block['video']['file']['url'])) {
                        $videoUrl = $block['video']['file']['url']; // Pamiętaj, że te URL są tymczasowe
                    }

                    $html .= "<div class=\"notion-video\">";
                    if (filter_var($videoUrl, FILTER_VALIDATE_URL)) {
                         // Podobnie jak embed, można tu dodać logikę dla YouTube/Vimeo, jeśli nie użyto bloku embed
                        $html .= "<video controls src=\"{$videoUrl}\" style=\"width:100%; max-width: 600px;\">Twoja przeglądarka nie obsługuje tagu video.</video>";
                    } else {
                        $html .= "<p>Nieprawidłowy URL wideo.</p>";
                    }
                     if (!empty($captionText)) {
                        $html .= "<div class=\"caption\">{$captionText}</div>";
                    }
                    $html .= "</div>\n";
                    break;

                case 'file':
                    $fileUrl = '';
                    $fileName = 'Plik'; 
                    $captionArray = $block['file']['caption'] ?? [];
                    $captionText = formatRichText($captionArray, $apiKey, $cacheDir, $cacheDurationsArray['pagedata'], $currentUrlPathString);
                    $fileType = $block['file']['type'] ?? null;

                    if ($fileType === 'external' && isset($block['file']['external']['url'])) {
                        $fileUrl = $block['file']['external']['url'];
                        $fileName = htmlspecialchars($block['file']['name'] ?? $fileName);
                    } elseif ($fileType === 'file' && isset($block['file']['url'])) {
                        $fileUrl = $block['file']['file']['url']; // URL tymczasowy
                        $fileName = htmlspecialchars($block['file']['name'] ?? $fileName);
                    }

                    if ($fileUrl) {
                        $html .= "<div class=\"notion-file\"><p><a href=\"{$fileUrl}\" target=\"_blank\" download=\"{$fileName}\">📎 {$fileName}</a></p>";
                        if (!empty($captionText)) {
                            $html .= "<div class=\"caption\">{$captionText}</div>";
                        }
                        $html .= "</div>\n";
                    }
                    break;

                case 'equation': // Blok równania
                    $expression = htmlspecialchars($block['equation']['expression'] ?? '');
                    // Dla KaTeX, użyj odpowiednich ograniczników lub klas dla bloków.
                    // Standardowe auto-renderowanie KaTeX szuka \[ ... \] dla bloków.
                    $html .= "<div class=\"notion-equation\">\\[" . $expression . "\\]</div>\n"; // Poprawione dla KaTeX blokowego
                    // Alternatywnie: $html .= "<div class=\"math-display\">{$expression}</div>\n";
                    break;
                
                case 'table_of_contents':
                    // Notion API nie zwraca elementów ToC, tylko informację, że ma być.
                    // Generowanie ToC odbywa się po stronie klienta (przez Twój main.js).
                    // Można dodać placeholder lub klasę, aby JS wiedział, gdzie umieścić ToC, jeśli ten blok jest obecny.
                    $color = $block['table_of_contents']['color'] ?? 'default';
                    $html .= "<div class=\"notion-table-of-contents-placeholder\" data-color=\"" . htmlspecialchars($color) . "\">";
                    // $html .= "<!-- Spis treści zostanie wygenerowany tutaj przez JavaScript -->";
                    $html .= "</div>\n";
                    break;

                default:
                    // Można zostawić puste, aby ignorować nieobsługiwane bloki,
                    // lub dodać komunikat diagnostyczny:
                    // $html .= "<div class=\"unsupported-block\"><p>Nieobsługiwany typ bloku: " . htmlspecialchars($block['type']) . "</p></div>\n";
                    break; 
            }
         }
        
        // Zamknij listę na końcu, jeśli była otwarta
        if ($inList) {
            $html .= "</{$listType}>\n";
        }

    } else if (!isset($content['error'])) {
        $html = "<div class=\"info-message\">Ta strona nie zawiera jeszcze treści.</div>";
    }
    
    return $html;
}

// --- NOWA FUNKCJA: Przetwarza znaczniki <pass> ---
function processPasswordTags($html, $isVerified, $error) {
    // Szukaj &lt;pass&gt; ... &lt;/pass&gt; (po przetworzeniu przez htmlspecialchars)
    return preg_replace_callback('/&lt;pass&gt;(.*?)&lt;\/pass&gt;/si', function($matches) use ($isVerified, $error) {
        if ($isVerified) {
            // Zwróć wewnętrzną treść (bez tagów)
            return $matches[1]; 
        } else {
            // Zwróć formularz hasła
            $form = '<div class="password-protected-content">';
            $form .= '<h4>Ta treść jest chroniona hasłem</h4>';
            if ($error) {
                $form .= '<p style="color: red;">Nieprawidłowe hasło.</p>';
            }
            $form .= '<form method="post" action="' . htmlspecialchars($_SERVER['REQUEST_URI']) . '">'; // Użyj bieżącego URI
            $form .= '<label for="content_password">Wprowadź hasło:</label> ';
            $form .= '<input type="password" name="content_password" id="content_password" required> ';
            $form .= '<button type="submit">Odblokuj</button>';
            $form .= '</form>';
            $form .= '</div>';
            return $form;
        }
    }, $html);
}

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

?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Dynamiczny tytuł strony -->
    <title><?php echo htmlspecialchars($pageTitle) . ($mainPageTitle !== $pageTitle && !empty($requestPath) ? ' - ' . htmlspecialchars($mainPageTitle) : ''); ?></title> 
    
    <!-- Meta tagi SEO i dla robotów -->
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <meta name="robots" content="noindex, nofollow"> 
    <!-- Dodatkowa dyrektywa dla Google (choć robots.txt jest ważniejszy dla AI) -->
    <meta name="googlebot" content="noindex, nofollow"> 

    <!-- Meta tagi Open Graph dla social media -->
    <meta property="og:title" content="<?php echo htmlspecialchars($ogTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <meta property="og:type" content="website">
    <?php if (!empty($currentUrl)): ?>
    <meta property="og:url" content="<?php echo htmlspecialchars($currentUrl); ?>">
    <?php endif; ?>
    <!-- Możesz dodać og:image jeśli masz stałe logo lub sposób na pobranie obrazka strony -->
    <!-- <meta property="og:image" content="URL_DO_OBRAZKA"> -->

    <link rel="stylesheet" href="/css/style.css"> 
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs@1.24.1/themes/prism.css">
    <!-- Dodaj link do KaTeX CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.0/dist/katex.min.css" integrity="sha384-Xi8rHCmBmhbuyyhbI88391ZKP2dmfnOl4rT9ZfRI7mLTdk1wblIUnrIq35nqwEvC" crossorigin="anonymous">

    <!-- Dodatkowe styles dla formularza hasła (opcjonalnie) -->
    <style>
        .password-protected-content {
            border: 1px solid #ccc;
            padding: 15px;
            margin: 15px 0;
            background-color: #f9f9f9;
        }
        .password-protected-content h4 {
            margin-top: 0;
        }
        .password-protected-content label {
            margin-right: 5px;
        }
        .password-protected-content input[type="password"] {
            padding: 5px;
        }
        .password-protected-content button {
            padding: 5px 10px;
            cursor: pointer;
        }
        .notion-toggle summary { cursor: pointer; font-weight: bold; margin-bottom: 5px;}
        .notion-toggle-content { margin-left: 20px; border-left: 2px solid #eee; padding-left: 10px; }
        .notion-bookmark, .notion-embed, .notion-video, .notion-file, .notion-equation, .callout, .notion-table-of-contents-placeholder { margin: 1em 0; padding: 1em; border: 1px solid #eee; border-radius: 4px; background-color: #f9f9f9; }
        .notion-embed iframe, .notion-video video { max-width: 100%; display: block; margin: 0 auto; }
        .caption { font-size: 0.9em; color: #555; text-align: center; margin-top: 0.5em; }
        .callout-emoji { font-size: 1.2em; margin-right: 0.5em; }
        .callout-icon-external { width: 1.5em; height: 1.5em; vertical-align: middle; margin-right: 0.5em; }
        .todo-item label { display: flex; align-items: center; }
        .todo-item input[type="checkbox"] { margin-right: 8px; }
        /* Przykładowe styles dla kolorów tekstu/tła Notion (dodaj więcej wg potrzeb) */
        .notion-gray { color: gray; } .notion-gray-bg { background-color: #f1f1f1; padding: 0.1em 0.3em; border-radius: 3px;}
        .notion-brown { color: brown; } .notion-brown-bg { background-color: #f3e9e2; padding: 0.1em 0.3em; border-radius: 3px;}
        .notion-orange { color: orange; } .notion-orange-bg { background-color: #fce9d7; padding: 0.1em 0.3em; border-radius: 3px;}
        .notion-yellow { color: #c38f00; } .notion-yellow-bg { background-color: #fdf4bf; padding: 0.1em 0.3em; border-radius: 3px;} /* Ciemniejszy żółty dla tekstu */
        .notion-green { color: green; } .notion-green-bg { background-color: #e2f2e4; padding: 0.1em 0.3em; border-radius: 3px;}
        .notion-blue { color: blue; } .notion-blue-bg { background-color: #ddebf1; padding: 0.1em 0.3em; border-radius: 3px;}
        .notion-purple { color: purple; } .notion-purple-bg { background-color: #ebe4f2; padding: 0.1em 0.3em; border-radius: 3px;}
        .notion-pink { color: pink; } .notion-pink-bg { background-color: #f8e4ec; padding: 0.1em 0.3em; border-radius: 3px;}
        .notion-red { color: red; } .notion-red-bg { background-color: #f8e4e4; padding: 0.1em 0.3em; border-radius: 3px;}
        .notion-equation { text-align: center; } /* Aby wyśrodkować blokowe równania KaTeX */
    </style>

</head>
<body>

    <?php // --- OKŁADKA PRZENIESIONA TUTAJ (nad kontenerem) --- ?>
    <?php if ($pageCoverUrl): ?>
        <div class="page-cover-fullwidth-wrapper">
            <img src="<?php echo htmlspecialchars($pageCoverUrl); ?>" alt="Okładka strony: <?php echo htmlspecialchars($pageTitle); ?>" class="page-cover-fullwidth-image">
        </div>
    <?php endif; ?>
    <?php // --- KONIEC BLOKU OKŁADKI --- ?>

    <div class="container">
        <header>
             <h1><a href="/" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars($pageTitle); ?></a></h1>
             <?php if (!empty($requestPath) && !$pageNotFound && $currentPageId !== $notionPageId): ?>
                <nav aria-label="breadcrumb">
                    <ol style="list-style: none; padding: 0; margin: 10px 0 0 0;">
                        <li style="display: inline;"><a href="/"><?php echo htmlspecialchars($mainPageTitle); ?></a> / </li>
                        <li style="display: inline;"><?php echo htmlspecialchars($pageTitle); ?></li>
                    </ol>
                </nav>
             <?php endif; ?>
        </header>
        
        <main class="content">
            <?php if ($errorMessage): ?>
                <div class="error-message">
                    <p><?php echo htmlspecialchars($errorMessage); ?></p>
                    <?php if ($pageNotFound): ?>
                        <p><a href="/">Wróć do strony głównej</a></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
            <?php echo $htmlContent; ?>
            <?php endif; ?>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> podstawy.ai (Artur Kurasiński & Przemek Jurgiel-Żyła)</p>
        </footer>
    </div>
    
    <script src="/js/main.js"></script> 
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.24.1/prism.min.js"></script>
    <!-- Dodaj skrypty KaTeX -->
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.0/dist/katex.min.js" integrity="sha384-X/XCfMm41VSsqRNwNEypKSlVKGgBzu/+1G9lM2YtKkQ2A/v81rMvG0jM2o_n_D3p" crossorigin="anonymous"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.0/dist/contrib/auto-render.min.js" integrity="sha384-+XBljXPPpF+B/2ucxMgMKLRePsE_rP9wF_T_LW3H3_lRjM1jYkK+F1VqB_Y6V3M4" crossorigin="anonymous"
        onload="renderMathInElement(document.body, {
            delimiters: [
                {left: '\\[', right: '\\]', display: true}, // dla bloków równań
                {left: '\\(', right: '\\)', display: false} // dla równań w linii (poprawione dla JS stringa w PHP)
            ],
            throwOnError : false
        });"></script>
</body>
</html>