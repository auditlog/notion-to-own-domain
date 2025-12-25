<?php
// www_notion/private/notion_utils.php

// =============================================================================
// Cache Helper Functions
// =============================================================================

/**
 * Atomic cache write - prevents file corruption during concurrent access
 * Writes to a temporary file first, then renames (atomic operation)
 */
function cacheWrite($cacheFile, $data) {
    $tempFile = $cacheFile . '.' . uniqid('tmp_', true);

    if (@file_put_contents($tempFile, $data, LOCK_EX) === false) {
        @unlink($tempFile);
        return false;
    }

    if (!rename($tempFile, $cacheFile)) {
        @unlink($tempFile);
        return false;
    }

    return true;
}

/**
 * Clean up expired cache files
 * Should be called periodically (e.g., on every Nth request or via cron)
 */
function cacheCleanup($cacheDir, $maxAge = 604800) {
    if (!is_dir($cacheDir)) {
        return 0;
    }

    $deleted = 0;
    $now = time();

    $files = glob($cacheDir . '*.cache');
    if ($files === false) {
        return 0;
    }

    foreach ($files as $file) {
        if (is_file($file) && ($now - filemtime($file)) > $maxAge) {
            if (@unlink($file)) {
                $deleted++;
            }
        }
    }

    return $deleted;
}

/**
 * Probabilistic cache cleanup - runs cleanup with given probability
 * Avoids running cleanup on every request
 */
function maybeCacheCleanup($cacheDir, $probability = 0.01, $maxAge = 604800) {
    if (mt_rand(1, 1000) <= ($probability * 1000)) {
        return cacheCleanup($cacheDir, $maxAge);
    }
    return 0;
}

// =============================================================================
// Notion API Functions
// =============================================================================

// Funkcja pobierająca zawartość z Notion
function getNotionContent($pageId, $apiKey, $cacheDir, $specificCacheExpiration) {
    $cacheFile = $cacheDir . 'content_' . md5($pageId) . '.cache';

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $specificCacheExpiration)) {
        // Zwróć zdeserializowane dane, aby pętla działała poprawnie z cachem
        $cachedContent = file_get_contents($cacheFile);
        $decodedCachedContent = json_decode($cachedContent, true);
        if (isset($decodedCachedContent['all_results_aggregated'])) {
            return $cachedContent;
        }
    }

    $allResults = [];
    $nextCursor = null;
    $errorData = null;

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
            'Notion-Version: 2025-09-03'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode != 200) {
            $errorData = [
                'error' => 'Nie można pobrać zawartości z Notion. Kod: ' . $httpCode,
                'message' => $curlError ?: 'Brak dodatkowych informacji o błędzie cURL.',
                'response_code' => $httpCode
            ];
            break;
        }

        $data = json_decode($response, true);

        if (isset($data['results']) && is_array($data['results'])) {
            $allResults = array_merge($allResults, $data['results']);
        } else {
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

    if ($errorData) {
        return json_encode($errorData);
    }

    $finalResponseData = [
        'object' => 'list',
        'results' => $allResults,
        'has_more' => false,
        'next_cursor' => null,
        'all_results_aggregated' => true
    ];
    
    $finalJsonResponse = json_encode($finalResponseData);
    cacheWrite($cacheFile, $finalJsonResponse);
    return $finalJsonResponse;
}

function fetchAndRenderChildren($blockId, $apiKey, $cacheDir, $specificContentCacheExpiration, $currentUrlPathString = '') {
    $childrenData = getNotionContent($blockId, $apiKey, $cacheDir, $specificContentCacheExpiration);
    $childrenContent = json_decode($childrenData, true);
    global $cacheDurations;
    return notionToHtml($childrenContent, $apiKey, $cacheDir, $cacheDurations, $currentUrlPathString);
}

function normalizeTitleForPath($title) {
    // Use mb_strtolower to properly handle UTF-8 characters including Polish
    $path = mb_strtolower($title, 'UTF-8');

    // Transliterate Polish characters
    $polishChars = ['ą', 'ć', 'ę', 'ł', 'ń', 'ó', 'ś', 'ź', 'ż'];
    $latinChars  = ['a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z'];
    $path = str_replace($polishChars, $latinChars, $path);

    $path = str_replace(' ', '-', $path);
    $path = preg_replace('/[^a-z0-9\-]/', '', $path);
    $path = preg_replace('/-+/', '-', $path);
    $path = trim($path, '-');
    return $path;
}

function findNotionSubpageId($parentPageId, $subpagePath, $apiKey, $cacheDir, $specificSubpagesCacheExpiration) {
    $subpagePath = trim(strtolower($subpagePath), '/'); 
    if (empty($subpagePath)) return null; 

    $cacheFile = $cacheDir . 'subpages_' . md5($parentPageId) . '.cache';
    $subpages = [];

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $specificSubpagesCacheExpiration)) {
        $subpages = json_decode(file_get_contents($cacheFile), true);
         if (!is_array($subpages)) {
            $subpages = [];
            unlink($cacheFile); 
         }
    } else {
        $nextCursor = null;
        $hasMore = false;

        do {
            $url = "https://api.notion.com/v1/blocks/{$parentPageId}/children?page_size=100";
            if ($nextCursor) {
                $url .= "&start_cursor=" . urlencode($nextCursor);
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $apiKey,
                'Notion-Version: 2025-09-03'
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
                            $normalizedTitle = normalizeTitleForPath($title);
                            if (!empty($normalizedTitle)) {
                                $subpages[$normalizedTitle] = $block['id'];
                            }
                        }
                    }
                }
                $nextCursor = $data['next_cursor'] ?? null;
                $hasMore = $data['has_more'] ?? false;
            } else {
                error_log("Nie można pobrać listy podstron dla {$parentPageId}. Kod: {$httpCode}");
                break;
            }
        } while ($hasMore && $nextCursor);

        if (!empty($subpages)) {
            cacheWrite($cacheFile, json_encode($subpages));
        }
    }
    return $subpages[$subpagePath] ?? null;
}

function getNotionPageTitle($pageId, $apiKey, $cacheDir, $specificPagedataCacheExpiration) {
    $cacheFile = $cacheDir . 'pagedata_' . md5($pageId) . '.cache'; 
    $defaultTitle = 'Moja strona z zawartością Notion'; 
    $defaultResult = ['title' => $defaultTitle, 'coverUrl' => null];

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $specificPagedataCacheExpiration)) {
        $cachedData = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cachedData) && isset($cachedData['title'])) {
            return $cachedData;
        }
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.notion.com/v1/pages/{$pageId}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Notion-Version: 2025-09-03'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = $defaultResult;

    if ($httpCode == 200) {
        $data = json_decode($response, true);
        
        if (isset($data['properties']['title']['title'][0]['plain_text'])) {
            $result['title'] = $data['properties']['title']['title'][0]['plain_text'];
        } elseif (isset($data['properties']['Name']['title'][0]['plain_text'])) {
            $result['title'] = $data['properties']['Name']['title'][0]['plain_text'];
        } else {
             $result['title'] = $defaultTitle;
        }

        if (isset($data['cover'])) {
            if ($data['cover']['type'] === 'external' && isset($data['cover']['external']['url'])) {
                $result['coverUrl'] = $data['cover']['external']['url'];
            } elseif ($data['cover']['type'] === 'file' && isset($data['cover']['file']['url'])) {
                $result['coverUrl'] = $data['cover']['file']['url'];
            }
        }

        cacheWrite($cacheFile, json_encode($result));
    } else {
        error_log("Nie można pobrać danych strony Notion (tytuł/okładka) dla ID: {$pageId}. Kod: {$httpCode}");
    }
    return $result;
}

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
                $mentionedPageId = $richText['mention']['page']['id'];
                $mentionedPageTitle = 'Untitled';
                $fetchedPageData = getNotionPageTitle($mentionedPageId, $apiKey, $cacheDir, $specificPagedataCacheExpiration);
                $mentionedPageTitle = $fetchedPageData['title'] ?? $mentionedPageTitle;
                
                if (empty($mentionedPageTitle) || $mentionedPageTitle === 'Moja strona z zawartością Notion') { 
                    $mentionedPageTitle = $richText['plain_text'] ?: $mentionedPageId;
                    error_log("formatRichText: Nie udało się pobrać poprawnego tytułu dla strony ID: {$mentionedPageId}. Użyto: '{$mentionedPageTitle}'");
                }

                $path = normalizeTitleForPath($mentionedPageTitle); 
                if (!empty($path)) {
                    $basePath = !empty($currentUrlPathString) ? rtrim($currentUrlPathString, '/') : '';
                    $fullPath = !empty($basePath) ? $basePath . '/' . $path : $path;
                    $formattedText = '<a href="/' . htmlspecialchars(ltrim($fullPath, '/')) . '">' . htmlspecialchars($mentionedPageTitle) . '</a>';
                } else {
                    $formattedText = htmlspecialchars($mentionedPageTitle);
                }
            } else {
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
                if (isset($annotations['color']) && $annotations['color'] !== 'default') {
                    $colorClass = 'notion-' . str_replace('_background', '-bg', $annotations['color']);
                    $currentText = "<span class=\"" . htmlspecialchars($colorClass) . "\">{$currentText}</span>";
                }
             }
            if (isset($richText['href']) && $richText['href']) { 
                $currentText = "<a href=\"" . htmlspecialchars($richText['href']) . "\" target=\"_blank\">" . htmlspecialchars($currentText) . "</a>";
             }
            $formattedText = $currentText;
        } else if ($type === 'equation') {
            $expression = htmlspecialchars($richText['equation']['expression'] ?? '');
            $formattedText = '\\(' . $expression . '\\)';
        } else {
             $formattedText = htmlspecialchars($richText['plain_text'] ?? '');
        }
        $text .= $formattedText;
    }
    return $text;
}

function notionToHtml($content, $apiKey, $cacheDir, $cacheDurationsArray, $currentUrlPathString = '') {
    $html = '';
    $inList = false;
    $listType = '';

    if (isset($content['error'])) {
        $httpCode = $content['response_code'] ?? null;
        if ($httpCode === 404) {
             return "<div class=\"error-message\">Błąd: Nie znaleziono strony Notion (ID może być nieprawidłowy).</div>";
        }
        return "<div class=\"error-message\">Błąd pobierania danych z Notion: {$content['error']}</div>";
    }
    
    if (isset($content['results']) && is_array($content['results']) && !empty($content['results'])) {
        foreach ($content['results'] as $block) {
            $currentBlockType = $block['type'];
            $isListItem = in_array($currentBlockType, ['bulleted_list_item', 'numbered_list_item']);

            if ($inList && !$isListItem) {
                 $html .= "</{$listType}>\n";
                 $inList = false;
                 $listType = '';
            }

            switch ($currentBlockType) {
                case 'paragraph':
                    $text = formatRichText($block['paragraph']['rich_text'], $apiKey, $cacheDir, $cacheDurationsArray['pagedata'], $currentUrlPathString);
                    if (!empty($text) || (isset($block['paragraph']['rich_text']) && empty($block['paragraph']['rich_text']))) {
                        $html .= "<p>{$text}</p>\n";
                    } else {
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
                        $html .= $childrenHtml;
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
                        $html .= "<img src=\"{$imageUrl}\" alt=\"" . htmlspecialchars(strip_tags($captionText) ?: 'Obrazek') . "\" loading=\"lazy\">";
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
                    $langClass = !empty($language) ? " class=\"language-{$language}\"" : ' class=\"language-plaintext\"';
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
                            $iconHtml = "<img src=\"{$iconUrl}\" alt=\"ikona\" class=\"callout-icon-external\" loading=\"lazy\"> ";
                        }
                    }
                    $html .= "<div class=\"callout\">{$iconHtml}{$text}</div>\n";
                    break;
                case 'table':
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
                                    $tag = ($hasRowHeader && $cellIndex === 0 && !$hasColumnHeader) ? 'td' : 'th';
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
                           $basePath = !empty($currentUrlPathString) ? rtrim($currentUrlPathString, '/') : '';
                           $fullPath = !empty($basePath) ? $basePath . '/' . $pathSegment : $pathSegment;
                           $html .= "<p class=\"child-page-link\"><a href=\"/" . htmlspecialchars(ltrim($fullPath, '/')) . "\">📄 " . htmlspecialchars($title) . "</a></p>\n"; 
                        } else {
                           $html .= "<p class=\"child-page-link\"><em>(Podstrona bez popranego tytułu)</em></p>\n";
                        }
                    }
                    break;
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
                        if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match)) {
                            $embedUrl = "https://www.youtube.com/embed/" . htmlspecialchars($match[1]);
                            $html .= "<iframe src=\"{$embedUrl}\" frameborder=\"0\" allow=\"accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture\" allowfullscreen></iframe>";
                        } elseif (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/i', $url, $match)) {
                            $embedUrl = "https://player.vimeo.com/video/" . htmlspecialchars($match[1]);
                            $html .= "<iframe src=\"{$embedUrl}\" frameborder=\"0\" allow=\"autoplay; fullscreen; picture-in-picture\" allowfullscreen></iframe>";
                        } else {
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
                        $videoUrl = $block['video']['file']['url'];
                    }
                    $html .= "<div class=\"notion-video\">";
                    if (filter_var($videoUrl, FILTER_VALIDATE_URL)) {
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
                        $fileUrl = $block['file']['file']['url'];
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
                case 'equation':
                    $expression = htmlspecialchars($block['equation']['expression'] ?? '');
                    $html .= "<div class=\"notion-equation\">\\[" . $expression . "\\]</div>\n";
                    break;
                case 'table_of_contents':
                    $color = $block['table_of_contents']['color'] ?? 'default';
                    $html .= "<div class=\"notion-table-of-contents-placeholder\" data-color=\"" . htmlspecialchars($color) . "\">";
                    $html .= "</div>\n";
                    break;
                default:
                    break; 
            }
         }
        if ($inList) {
            $html .= "</{$listType}>\n";
        }
    } else if (!isset($content['error'])) {
        $html = "<div class=\"info-message\">Ta strona nie zawiera jeszcze treści.</div>";
    }
    return $html;
}

function processPasswordTags($html, $isVerified, $error, $csrfToken = '', $lockoutError = false) {
    return preg_replace_callback('/&lt;pass&gt;(.*?)&lt;\/pass&gt;/si', function($matches) use ($isVerified, $error, $csrfToken, $lockoutError) {
        if ($isVerified) {
            // Even for verified users, additional content cleaning
            // Remove potentially dangerous tags before returning content
            $content = $matches[1];
            // Convert back from HTML entities to properly format content
            return html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        } else {
            // Nie weryfikowano hasła - pokaż formularz
            $errorHtml = '';
            if ($lockoutError) {
                $errorHtml = '<div class="password-error">Zbyt wiele prób. Spróbuj ponownie za kilka minut.</div>';
            } elseif ($error) {
                $errorHtml = '<div class="password-error">Nieprawidłowe hasło</div>';
            }
            $csrfInput = $csrfToken ? '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">' : '';
            $disabledAttr = $lockoutError ? ' disabled' : '';
            return '
                <div class="password-protected">
                    <h3>Ta treść jest chroniona hasłem</h3>
                    <form method="post">
                        ' . $csrfInput . '
                        <input type="password" name="content_password" placeholder="Wprowadź hasło" required' . $disabledAttr . '>
                        <button type="submit"' . $disabledAttr . '>Odblokuj</button>
                    </form>
                    ' . $errorHtml . '
                </div>';
        }
    }, $html);
}

function processHideTags($html) {
    return preg_replace('/&lt;hide&gt;(.*?)&lt;\/hide&gt;/si', '', $html);
}

?>