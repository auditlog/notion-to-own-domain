<?php
// Dołączenie konfiguracji (poza katalogiem publicznym)
require_once '../private/config.php';

// Funkcja pobierająca zawartość z Notion
function getNotionContent($pageId, $apiKey, $cacheDir, $cacheExpiration) {
    // Sprawdź czy istnieje ważny plik cache
    $cacheFile = $cacheDir . 'content_' . md5($pageId) . '.cache';
    
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheExpiration)) {
        return file_get_contents($cacheFile);
    }
    
    // Jeśli nie ma cache, pobierz z API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.notion.com/v1/blocks/{$pageId}/children?page_size=100");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Notion-Version: 2022-06-28'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($httpCode != 200) {
        $error = curl_error($ch);
        curl_close($ch);
        return json_encode([
            'error' => 'Nie można pobrać zawartości z Notion. Kod: ' . $httpCode,
            'message' => $error,
            'response_code' => $httpCode
        ]);
    }
    
    curl_close($ch);
    
    // Zapisz wynik do cache
    file_put_contents($cacheFile, $response);
    return $response;
}

// Funkcja do obsługi zagnieżdżonych bloków (do przyszłej implementacji)
function fetchAndRenderChildren($blockId, $apiKey, $cacheDir, $cacheExpiration) {
    $childrenData = getNotionContent($blockId, $apiKey, $cacheDir, $cacheExpiration);
    $childrenContent = json_decode($childrenData, true);
    return notionToHtml($childrenContent, $apiKey, $cacheDir, $cacheExpiration);
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
function findNotionSubpageId($parentPageId, $subpagePath, $apiKey, $cacheDir, $cacheExpiration) {
    $subpagePath = trim(strtolower($subpagePath), '/'); 
    if (empty($subpagePath)) return null; 

    $cacheFile = $cacheDir . 'subpages_' . md5($parentPageId) . '.cache';
    $subpages = [];

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheExpiration)) {
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

// --- NOWA FUNKCJA: Pobiera tytuł strony Notion ---
function getNotionPageTitle($pageId, $apiKey, $cacheDir, $cacheExpiration) {
    $cacheFile = $cacheDir . 'title_' . md5($pageId) . '.cache';
    $defaultTitle = 'Moja strona z zawartością Notion'; // Tytuł domyślny w razie problemów

    // Sprawdź cache
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheExpiration)) {
        $cachedTitle = file_get_contents($cacheFile);
        // Zwróć z cache, jeśli nie jest pusty
        return !empty($cachedTitle) ? $cachedTitle : $defaultTitle; 
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
    
    $pageTitle = $defaultTitle; // Ustaw domyślny na start

    if ($httpCode == 200) {
        $data = json_decode($response, true);
        // Szukamy tytułu w strukturze odpowiedzi API v1
        if (isset($data['properties']['title']['title'][0]['plain_text'])) {
            $pageTitle = $data['properties']['title']['title'][0]['plain_text'];
        } elseif (isset($data['properties']['Name']['title'][0]['plain_text'])) {
            // Czasami główna właściwość tytułowa nazywa się "Name"
             $pageTitle = $data['properties']['Name']['title'][0]['plain_text'];
        }
         // Zapisz tytuł do cache (nawet jeśli jest pusty, aby uniknąć ponownych prób w krótkim czasie)
         file_put_contents($cacheFile, $pageTitle);
    } else {
        error_log("Nie można pobrać tytułu strony Notion dla ID: {$pageId}. Kod: {$httpCode}");
        // Nie zapisuj cache w przypadku błędu, aby spróbować ponownie później
        // Zwracamy $defaultTitle
    }

    return $pageTitle;
}

// --- ZAKTUALIZOWANA Funkcja formatRichText (z pobieraniem tytułu dla wzmianek) ---
// Dodano parametry $apiKey, $cacheDir, $cacheExpiration
function formatRichText($richTextArray, $apiKey, $cacheDir, $cacheExpiration) {
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
                $fetchedTitle = getNotionPageTitle($mentionedPageId, $apiKey, $cacheDir, $cacheExpiration);
                
                // Użyj pobranego tytułu, jeśli nie jest pusty i różni się od domyślnego z getNotionPageTitle
                if (!empty($fetchedTitle) && $fetchedTitle !== 'Moja strona z zawartością Notion') { 
                    $mentionedPageTitle = $fetchedTitle;
                } else {
                    // Jeśli pobieranie się nie powiodło lub zwróciło domyślny tytuł, użyj ID jako fallback
                    // Można też użyć $richText['plain_text'] jako ostateczności, jeśli $fetchedTitle jest pusty
                    $mentionedPageTitle = $richText['plain_text'] ?: $mentionedPageId; // Użyj plain_text jeśli jest, inaczej ID
                    error_log("formatRichText: Nie udało się pobrać poprawnego tytułu dla strony ID: {$mentionedPageId}. Użyto: '{$mentionedPageTitle}'");
                }

                // Zawsze próbuj wygenerować ścieżkę na podstawie (najlepiej pobranego) tytułu
                $path = normalizeTitleForPath($mentionedPageTitle); 

                if (!empty($path)) {
                    $formattedText = "<a href=\"/" . htmlspecialchars($path) . "\">" . htmlspecialchars($mentionedPageTitle) . "</a>";
                } else {
                    // Jeśli ścieżka jest pusta, wyświetl sam tekst (tytuł lub ID)
                    $formattedText = htmlspecialchars($mentionedPageTitle);
                }

            } else {
                 // Inne typy wzmianek (np. data, użytkownik)
                 $formattedText = htmlspecialchars($richText['plain_text'] ?? '');
            }

        } else if ($type === 'text') {
            // Obsługa zwykłego tekstu (bez zmian)
            $formattedText = htmlspecialchars($richText['plain_text'] ?? ''); 
            if (isset($richText['annotations'])) { 
                if ($richText['annotations']['bold']) { $formattedText = "<strong>{$formattedText}</strong>"; }
                if ($richText['annotations']['italic']) { $formattedText = "<em>{$formattedText}</em>"; }
                if ($richText['annotations']['strikethrough']) { $formattedText = "<del>{$formattedText}</del>"; }
                if ($richText['annotations']['underline']) { $formattedText = "<u>{$formattedText}</u>"; }
                if ($richText['annotations']['code']) { $formattedText = "<code>{$formattedText}</code>"; }
             }
            if (isset($richText['href']) && $richText['href']) { 
                $formattedText = "<a href=\"" . htmlspecialchars($richText['href']) . "\" target=\"_blank\">{$formattedText}</a>";
             }

    } else {
             $formattedText = htmlspecialchars($richText['plain_text'] ?? '');
    }
        
        $text .= $formattedText;
}

    return $text;
}

// Konwersja z formatu Notion na HTML (rozszerzona implementacja)
function notionToHtml($content, $apiKey, $cacheDir, $cacheExpiration) {
    $html = '';
    $inList = false;
    $listType = ''; // 'ul' lub 'ol'

    if (isset($content['error'])) {
        $httpCode = $content['response_code'] ?? null;
        if ($httpCode === 404) {
             // Specjalna obsługa dla 404 od Notion API (np. zły ID strony)
             return "<div class=\"error-message\">Błąd: Nie znaleziono strony Notion (ID może być nieprawidłowy).</div>";
        }
        return "<div class=\"error-message\">Błąd pobierania danych z Notion: {$content['error']}</div>";
    }
    
    if (isset($content['results']) && is_array($content['results'])) {
        foreach ($content['results'] as $block) {
            $currentBlockType = $block['type'];
            $isListItem = in_array($currentBlockType, ['bulleted_list_item', 'numbered_list_item']);

            // Zarządzanie zamykaniem listy
            if ($inList && !$isListItem && $currentBlockType !== 'child_page') { // Dodano warunek dla child_page
                 $html .= "</{$listType}>\n";
                 $inList = false;
                 $listType = '';
            } else if ($inList && $isListItem) {
                // Sprawdź, czy typ listy się zmienił
                $newListType = ($currentBlockType === 'bulleted_list_item') ? 'ul' : 'ol';
                if ($newListType !== $listType) {
                    $html .= "</{$listType}>\n"; // Zamknij starą listę
                    $html .= "<{$newListType}>\n"; // Otwórz nową listę
                    $listType = $newListType;
                }
            } else if ($inList && $currentBlockType === 'child_page') { // Zamknij listę przed linkiem do podstrony
                 $html .= "</{$listType}>\n";
                 $inList = false;
                 $listType = '';
            }

            switch ($currentBlockType) {
                case 'paragraph':
                    // Przekaż parametry do formatRichText
                    $text = formatRichText($block['paragraph']['rich_text'], $apiKey, $cacheDir, $cacheExpiration); 
                    if (!empty($text)) {
                        $html .= "<p>{$text}</p>\n";
                    } else {
                        $html .= "<p>&nbsp;</p>\n"; // Pusty paragraf
                    }
                    break;
                    
                case 'heading_1':
                case 'heading_2':
                case 'heading_3':
                    // --- POPRAWIONA LOGIKA GENEROWANIA TAGÓW H1/H2/H3 ---
                    $key = $currentBlockType; // np. 'heading_1'
                    $level = substr($key, -1); // Pobierz ostatni znak ('1', '2', lub '3')
                    
                    // Sprawdź, czy poziom jest poprawną cyfrą
                    if (is_numeric($level) && $level >= 1 && $level <= 6) { 
                        $tagName = 'h' . $level; // Utwórz poprawny tag np. 'h1'
                        // Pobierz i sformatuj tekst nagłówka
                        $text = formatRichText($block[$key]['rich_text'], $apiKey, $cacheDir, $cacheExpiration);
                        // Wygeneruj poprawny HTML
                        $html .= "<{$tagName}>{$text}</{$tagName}>\n";
                    } else {
                        // Logowanie błędu, jeśli typ nagłówka jest nieoczekiwany
                        error_log("Nieoczekiwany lub niepoprawny typ nagłówka w notionToHtml: " . $key);
                        // Można opcjonalnie wyświetlić tekst w paragrafie jako fallback
                        $text = formatRichText($block[$key]['rich_text'], $apiKey, $cacheDir, $cacheExpiration);
                        $html .= "<p><strong>(Błąd nagłówka: {$key})</strong> {$text}</p>\n";
                    }
                    break; // Koniec przypadku dla nagłówków
                    
                case 'bulleted_list_item':
                case 'numbered_list_item':
                    // Przekaż parametry do formatRichText
                    $key = $currentBlockType;
                    $text = formatRichText($block[$key]['rich_text'], $apiKey, $cacheDir, $cacheExpiration);
                    if (!$inList || $listType !== 'ul') {
                        if($inList) $html .= "</{$listType}>\n"; // Zamknij jeśli była inna lista
                        $html .= "<ul>\n";
                        $inList = true;
                        $listType = 'ul';
                    }
                    $html .= "<li>{$text}</li>\n";
                    break;
                    
                case 'to_do':
                    // Przekaż parametry do formatRichText
                    $text = formatRichText($block['to_do']['rich_text'], $apiKey, $cacheDir, $cacheExpiration);
                    $checked = $block['to_do']['checked'] ? ' checked' : '';
                    
                    if ($inList) {
                        $html .= "</ul>\n";
                        $inList = false;
                    }
                    
                    $html .= "<div class=\"todo-item\"><input type=\"checkbox\"{$checked} disabled> {$text}</div>\n";
                    break;
                    
                case 'image':
                    if ($inList) {
                        $html .= "</ul>\n";
                        $inList = false;
                    }
                    
                    $caption = '';
                    if (isset($block['image']['caption']) && !empty($block['image']['caption'])) {
                        // Przekaż parametry do formatRichText
                        $caption = formatRichText($block['image']['caption'], $apiKey, $cacheDir, $cacheExpiration);
                    }
                    
                    $imageUrl = '';
                    if (isset($block['image']['file']) && isset($block['image']['file']['url'])) {
                        $imageUrl = $block['image']['file']['url'];
                    } elseif (isset($block['image']['external']) && isset($block['image']['external']['url'])) {
                        $imageUrl = $block['image']['external']['url'];
                    }
                    
                    if ($imageUrl) {
                        $html .= "<figure>";
                        $html .= "<img src=\"" . htmlspecialchars($imageUrl) . "\" alt=\"" . ($caption ?: 'Obrazek') . "\">";
                        if ($caption) {
                            $html .= "<figcaption>{$caption}</figcaption>";
                        }
                        $html .= "</figure>\n";
                    }
                    break;
                    
                case 'divider':
                    if ($inList) {
                        $html .= "</ul>\n";
                        $inList = false;
                    }
                    
                    $html .= "<hr>\n";
                    break;
                    
                case 'code':
                    if ($inList) {
                        $html .= "</ul>\n";
                        $inList = false;
                    }
                    
                    $language = isset($block['code']['language']) ? htmlspecialchars($block['code']['language']) : ''; // Zabezpiecz język
                    // formatRichText zwraca już HTML (np. z <strong>), nie należy go dodatkowo escapować htmlspecialchars
                    $codeContent = formatRichText($block['code']['rich_text'], $apiKey, $cacheDir, $cacheExpiration); 
                    // Dodaj klasę dla PrismJS (jeśli język jest znany)
                    $langClass = !empty($language) ? " class=\"language-{$language}\"" : '';
                    $html .= "<pre><code{$langClass}>{$codeContent}</code></pre>\n"; 
                    break;
                    
                case 'quote':
                    if ($inList) {
                        $html .= "</ul>\n";
                        $inList = false;
                    }
                    
                    $text = formatRichText($block['quote']['rich_text'], $apiKey, $cacheDir, $cacheExpiration);
                    $html .= "<blockquote>{$text}</blockquote>\n";
                    break;
                    
                case 'callout':
                    if ($inList) {
                        $html .= "</ul>\n";
                        $inList = false;
                    }
                    
                    $text = formatRichText($block['callout']['rich_text'], $apiKey, $cacheDir, $cacheExpiration);
                    $icon = '';
                    
                    if (isset($block['callout']['icon'])) {
                        if (isset($block['callout']['icon']['emoji'])) {
                            $icon = $block['callout']['icon']['emoji'];
                        } elseif (isset($block['callout']['icon']['external']) && isset($block['callout']['icon']['external']['url'])) {
                            $iconUrl = $block['callout']['icon']['external']['url'];
                            $icon = "<img src=\"" . htmlspecialchars($iconUrl) . "\" alt=\"ikona\" class=\"callout-icon\">";
                        }
                    }
                    
                    $html .= "<div class=\"callout\">{$icon} {$text}</div>\n";
                    break;
                    
                case 'table':
                    if ($inList) {
                        $html .= "</{$listType}>\n";
                        $inList = false;
                        $listType = '';
                    }

                    $tableBlockId = $block['id'];
                    $hasColumnHeader = $block['table']['has_column_header'] ?? false;
                    $hasRowHeader = $block['table']['has_row_header'] ?? false; // Rzadziej używane, ale można uwzględnić

                    // Pobierz wiersze tabeli (jako bloki potomne bloku tabeli)
                    $tableRowsData = getNotionContent($tableBlockId, $apiKey, $cacheDir, $cacheExpiration);
                    $tableRowsContent = json_decode($tableRowsData, true);

                    if (isset($tableRowsContent['results']) && is_array($tableRowsContent['results'])) {
                        $html .= "<div class=\"table-wrapper\"><table class=\"notion-table\">\n";
                        
                        $rows = $tableRowsContent['results'];
                        
                        // Obsługa nagłówka kolumn
                        if ($hasColumnHeader && !empty($rows)) {
                            $headerRow = array_shift($rows); // Pierwszy wiersz to nagłówek
                            if (isset($headerRow['table_row']['cells'])) {
                                $html .= "<thead><tr>\n";
                                $cellIndex = 0;
                                foreach ($headerRow['table_row']['cells'] as $cell) {
                                    $tag = ($hasRowHeader && $cellIndex === 0) ? 'th' : 'th'; // Pierwsza komórka nagłówka może być pusta lub specjalna
                                    // Przekaż parametry do formatRichText dla komórki nagłówka
                                    $cellContent = formatRichText($cell, $apiKey, $cacheDir, $cacheExpiration);
                                    $html .= "<{$tag}>{$cellContent}</{$tag}>\n";
                                    $cellIndex++;
                                }
                                $html .= "</tr></thead>\n";
                            }
                        }

                        // Obsługa ciała tabeli
                        $html .= "<tbody>\n";
                        foreach ($rows as $row) {
                           if (isset($row['table_row']['cells'])) {
                                $html .= "<tr>\n";
                                $cellIndex = 0;
                                foreach ($row['table_row']['cells'] as $cell) {
                                    // Użyj <th> dla pierwszej komórki, jeśli wiersz ma nagłówek
                                    $tag = ($hasRowHeader && $cellIndex === 0) ? 'th' : 'td'; 
                                    // Przekaż parametry do formatRichText dla komórki danych
                                    $cellContent = formatRichText($cell, $apiKey, $cacheDir, $cacheExpiration);
                                    $html .= "<{$tag}>{$cellContent}</{$tag}>\n";
                                    $cellIndex++;
                                }
                                $html .= "</tr>\n";
                           }
                        }
                        $html .= "</tbody>\n";
                        $html .= "</table></div>\n";

                    } else {
                         // Błąd podczas pobierania wierszy lub brak wierszy
                         $html .= "<div class=\"table-placeholder\">Nie można załadować zawartości tabeli.</div>\n";
                         if(isset($tableRowsContent['error'])) {
                              error_log("Błąd pobierania wierszy tabeli ({$tableBlockId}): " . $tableRowsContent['error']);
                         }
                    }
                    break;
                    
                // --- NOWY PRZYPADEK: Obsługa bloku child_page ---
                case 'child_page':
                    if (isset($block['child_page']['title'])) {
                        $title = $block['child_page']['title'];
                        // Użyj funkcji pomocniczej do stworzenia ścieżki
                        $path = normalizeTitleForPath($title); 
                        if (!empty($path)) {
                           // Wyświetl tytuł jako link do podstrony
                           $html .= "<p class=\"child-page-link\"><a href=\"/" . htmlspecialchars($path) . "\">📄 " . htmlspecialchars($title) . "</a></p>\n"; 
                        } else {
                           // Jeśli tytuł jest pusty lub składa się tylko ze znaków specjalnych
                           $html .= "<p class=\"child-page-link\"><em>(Podstrona bez popranego tytułu)</em></p>\n";
                        }
                    }
                    break;
                    
                default:
                    // Domyślna obsługa nieznanych bloków (można ją usunąć, jeśli nie chcemy ich widzieć)
                    // $html .= "<div class=\"unsupported-block\">Nieobsługiwany typ bloku: {$block['type']}</div>\n"; 
                    // Zdecydowałem się zakomentować, aby nie wyświetlać nic dla innych nieobsługiwanych typów
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

// Główna logika aplikacji

// Odczytaj ścieżkę z parametru GET dodanego przez .htaccess
$requestPath = $_GET['path'] ?? '';
$requestPath = trim($requestPath, '/'); // Usuń skrajne slashe

$currentPageId = null;
$pageNotFound = false;
$pageTitle = 'Moja strona z zawartością Notion'; // Domyślny tytuł

if (empty($requestPath)) {
    // Strona główna
    $currentPageId = $notionPageId; // Użyj ID z config.php
} else {
    // Podstrona - spróbuj znaleźć jej ID
    $currentPageId = findNotionSubpageId($notionPageId, $requestPath, $notionApiKey, $cacheDir, $cacheExpiration);
    if ($currentPageId === null) {
        $pageNotFound = true; // Nie znaleziono podstrony
    }
}

$htmlContent = '';
$errorMessage = '';

if ($pageNotFound) {
    // Ustaw kod odpowiedzi HTTP na 404
    http_response_code(404);
    $errorMessage = "Nie znaleziono strony dla ścieżki: /" . htmlspecialchars($requestPath);
    $pageTitle = 'Nie znaleziono strony'; // Zaktualizuj tytuł dla 404
} elseif ($currentPageId) {
    // --- Pobierz tytuł strony ---
    $pageTitle = getNotionPageTitle($currentPageId, $notionApiKey, $cacheDir, $cacheExpiration);

    // Pobierz zawartość dla znalezionego ID (głównej lub podstrony)
    $notionData = getNotionContent($currentPageId, $notionApiKey, $cacheDir, $cacheExpiration);
    $notionContent = json_decode($notionData, true);

    // Sprawdź, czy samo pobieranie z Notion nie zwróciło błędu
    if (isset($notionContent['error'])) {
        $errorMessage = $notionContent['error'];
        if (isset($notionContent['message'])) {
            $errorMessage .= ': ' . $notionContent['message'];
        }
        // Jeśli Notion zwróciło 404 dla podanego ID, traktuj to jako błąd serwera lub konfiguracji
        if (($notionContent['response_code'] ?? null) === 404) {
             http_response_code(500); // Błąd wewnętrzny, bo ID strony powinno być poprawne
             $errorMessage = "Błąd konfiguracji: Nie można znaleźć strony Notion o podanym ID ({$currentPageId}). Sprawdź ID w konfiguracji lub czy strona nie została usunięta.";
             $pageTitle = 'Błąd konfiguracji'; // Zaktualizuj tytuł dla błędu
        }
    } else {
        // Renderuj zawartość do HTML
        $htmlContent = notionToHtml($notionContent, $notionApiKey, $cacheDir, $cacheExpiration);
    }
} else {
    // Sytuacja awaryjna - nie powinno się zdarzyć przy poprawnej logice
    http_response_code(500);
    $errorMessage = "Wystąpił nieoczekiwany błąd przy określaniu strony do wyświetlenia.";
    $pageTitle = 'Błąd serwera'; // Zaktualizuj tytuł dla błędu 500
}

?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Użyj dynamicznego $pageTitle dla tagu <title> -->
    <title><?php echo htmlspecialchars($pageTitle); ?> - Moja strona z Notion</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs@1.24.1/themes/prism.css">
</head>
<body>
    <div class="container">
        <header>
             <!-- Użyj dynamicznego $pageTitle w nagłówku H1 -->
            <h1><a href="/" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars($pageTitle); ?></a></h1>
             <?php if (!empty($requestPath) && !$pageNotFound && $currentPageId !== $notionPageId): // Dodano warunek, aby nie pokazywać breadcrumbs dla strony głównej ?>
                <nav aria-label="breadcrumb">
                    <ol style="list-style: none; padding: 0; margin: 10px 0 0 0;">
                        <!-- Link do strony głównej, pobierz jej tytuł -->
                        <?php $mainPageTitle = getNotionPageTitle($notionPageId, $notionApiKey, $cacheDir, $cacheExpiration); ?>
                        <li style="display: inline;"><a href="/"><?php echo htmlspecialchars($mainPageTitle); ?></a> / </li>
                        <li style="display: inline;"><?php echo htmlspecialchars($pageTitle); // Tytuł bieżącej podstrony ?></li>
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
</body>
</html>