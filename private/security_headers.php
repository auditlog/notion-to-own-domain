<?php
/**
 * Security headers implementation for the Notion website
 * Optimized for HTTPS environment
 */

/**
 * Set all security headers
 * Call this function at the beginning of the page load
 */
function setSecurityHeaders() {
    // Set Content Security Policy (CSP)
    setContentSecurityPolicy();
    
    // Set other security headers
    setBasicSecurityHeaders();
    
    // Set HSTS header (only on HTTPS)
    setHSTSHeader();
}

/**
 * Set Content Security Policy (CSP) header
 * Based on the resources used in the application
 */
function setContentSecurityPolicy() {
    // Define trusted sources
    $sources = [
        'default-src' => ["'self'"],
        'script-src' => [
            "'self'", 
            "https://cdn.jsdelivr.net",
            "'unsafe-inline'"  // Required for KaTeX inline scripts
        ],
        'style-src' => [
            "'self'", 
            "https://cdn.jsdelivr.net", 
            "'unsafe-inline'"  // Required for inline styles
        ],
        'img-src' => ["'self'", "data:", "https:"],  // Allow Notion images
        'font-src' => ["'self'", "https://cdn.jsdelivr.net"],
        'connect-src' => ["'self'"],
        'frame-src' => ["'none'"],  // Disallow frames
        'object-src' => ["'none'"],  // Disallow plugins
        'base-uri' => ["'self'"],
        'form-action' => ["'self'"],
        'upgrade-insecure-requests' => [] // Force HTTPS for all resources
    ];
    
    // Build CSP string
    $csp = '';
    foreach ($sources as $directive => $values) {
        if (empty($values)) {
            $csp .= $directive . '; ';
        } else {
            $csp .= $directive . ' ' . implode(' ', $values) . '; ';
        }
    }
    
    // Send CSP header
    header("Content-Security-Policy: $csp");
}

/**
 * Set basic security headers
 * - X-Content-Type-Options
 * - X-Frame-Options
 * - X-XSS-Protection
 * - Referrer-Policy
 */
function setBasicSecurityHeaders() {
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Prevent embedding in frames (clickjacking protection)
    header('X-Frame-Options: DENY');
    
    // Enable browser XSS protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Control referrer information
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Prevent browsers from detecting the application framework
    header_remove('X-Powered-By');
    
    // Permissions Policy (formerly Feature Policy)
    // Restrict access to browser features
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
}

/**
 * Set HTTP Strict Transport Security (HSTS) header
 * Forces HTTPS usage
 */
function setHSTSHeader() {
    // Set HSTS header with 1 year expiry (31536000 seconds)
    // includeSubdomains ensures all subdomains use HTTPS
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
?>