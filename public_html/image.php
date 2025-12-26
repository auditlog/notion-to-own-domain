<?php
/**
 * Image Proxy with Local Cache
 *
 * Fetches images from Notion CDN and caches them locally to handle
 * expiring Notion URLs (~1 hour) with a longer cache TTL (7 days).
 */

// Validate URL parameter
$url = $_GET['url'] ?? '';
if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    exit('Invalid URL');
}

// Security: Only allow Notion-related domains
$allowedDomains = [
    'prod-files-secure.s3.us-west-2.amazonaws.com',
    'www.notion.so',
    's3.us-west-2.amazonaws.com',
    's3-us-west-2.amazonaws.com',
    'images.unsplash.com', // Notion uses Unsplash for some covers
    'lh3.googleusercontent.com', // Google Drive images
];

$host = parse_url($url, PHP_URL_HOST);
if (!$host || !in_array($host, $allowedDomains)) {
    http_response_code(403);
    exit('Domain not allowed');
}

// Cache configuration
$cacheDir = __DIR__ . '/../private/cache/images/';
$cacheFile = $cacheDir . md5($url);
$cacheTTL = 604800; // 7 days

// Create cache directory if needed
if (!is_dir($cacheDir)) {
    if (!mkdir($cacheDir, 0755, true)) {
        http_response_code(500);
        exit('Cannot create cache directory');
    }
}

// Check cache - return cached file if valid
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTTL)) {
    $mimeType = @mime_content_type($cacheFile);
    if (!$mimeType) {
        $mimeType = 'image/jpeg'; // Default fallback
    }

    header('Content-Type: ' . $mimeType);
    header('Cache-Control: public, max-age=86400'); // Browser cache 1 day
    header('X-Cache: HIT');
    readfile($cacheFile);
    exit;
}

// Fetch image from original URL
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; NotionProxy/1.0)',
    CURLOPT_SSL_VERIFYPEER => true,
]);

$imageData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$curlError = curl_error($ch);
curl_close($ch);

// Handle fetch errors
if ($httpCode !== 200 || empty($imageData)) {
    // If we have an old cached version, serve it as fallback
    if (file_exists($cacheFile)) {
        $mimeType = @mime_content_type($cacheFile) ?: 'image/jpeg';
        header('Content-Type: ' . $mimeType);
        header('Cache-Control: public, max-age=3600'); // Shorter cache for stale
        header('X-Cache: STALE');
        readfile($cacheFile);
        exit;
    }

    http_response_code(502);
    exit('Failed to fetch image');
}

// Validate content type is an image
if (!$contentType || strpos($contentType, 'image/') !== 0) {
    http_response_code(400);
    exit('Not an image');
}

// Save to cache (atomic write)
$tempFile = $cacheFile . '.' . uniqid('tmp_', true);
if (file_put_contents($tempFile, $imageData) !== false) {
    rename($tempFile, $cacheFile);
}

// Output image
header('Content-Type: ' . $contentType);
header('Cache-Control: public, max-age=86400'); // Browser cache 1 day
header('X-Cache: MISS');
echo $imageData;
