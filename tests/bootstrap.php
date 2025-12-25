<?php
/**
 * PHPUnit Bootstrap File
 * Loads required files and sets up test environment
 */

// Define that we're in testing mode
define('PHPUNIT_TESTING', true);

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load application functions
require_once __DIR__ . '/../private/notion_utils.php';
require_once __DIR__ . '/../private/security_headers.php';

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Suppress headers during tests (prevent "headers already sent" errors)
if (!function_exists('header_remove')) {
    function header_remove($name = null) {
        // Mock function for testing
        return true;
    }
}

// Create a simple mock for header() if needed in tests
// This prevents "Cannot modify header information" errors during testing
