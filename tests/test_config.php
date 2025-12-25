<?php
/**
 * Test configuration file
 * Contains test-specific constants and settings
 */

// Test API key (not a real key)
define('TEST_NOTION_API_KEY', 'test_key_12345_not_real');

// Test page IDs (UUIDs for testing)
define('TEST_PAGE_ID_MAIN', '12345678-1234-1234-1234-123456789abc');
define('TEST_PAGE_ID_SUBPAGE', '87654321-4321-4321-4321-cba987654321');

// Test cache settings
define('TEST_CACHE_TTL', 3600); // 1 hour

// Test content password
define('TEST_CONTENT_PASSWORD', 'test_password_123');

// Helper function to get temp directory for tests
function getTestTempDir($prefix = 'phpunit_test_')
{
    return sys_get_temp_dir() . '/' . $prefix . uniqid() . '/';
}

// Helper function to create test cache directory
function createTestCacheDir()
{
    $dir = getTestTempDir('cache_');
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

// Helper function to cleanup test directory
function cleanupTestDir($dir)
{
    if (!is_dir($dir)) {
        return;
    }

    $files = glob($dir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    @rmdir($dir);
}
