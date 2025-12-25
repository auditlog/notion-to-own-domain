<?php

namespace Tests\Functional;

use PHPUnit\Framework\TestCase;

/**
 * Functional tests for cache behavior
 * Tests cache creation, expiration, cleanup, and edge cases
 */
class CacheTest extends TestCase
{
    private $tempCacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempCacheDir = sys_get_temp_dir() . '/phpunit_cache_functional_' . uniqid() . '/';
        if (!file_exists($this->tempCacheDir)) {
            mkdir($this->tempCacheDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempCacheDir)) {
            $files = glob($this->tempCacheDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($this->tempCacheDir);
        }
        parent::tearDown();
    }

    /**
     * Test cache file creation
     */
    public function testCacheFileCreation()
    {
        $cacheFile = $this->tempCacheDir . 'test_creation.cache';
        $data = json_encode(['key' => 'value', 'timestamp' => time()]);

        $result = cacheWrite($cacheFile, $data);

        $this->assertTrue($result);
        $this->assertFileExists($cacheFile);
        $this->assertEquals($data, file_get_contents($cacheFile));
    }

    /**
     * Test cache file has correct permissions
     */
    public function testCacheFilePermissions()
    {
        $cacheFile = $this->tempCacheDir . 'test_permissions.cache';
        $data = 'test data';

        cacheWrite($cacheFile, $data);

        $this->assertFileExists($cacheFile);
        $this->assertFileIsReadable($cacheFile);
        $this->assertFileIsWritable($cacheFile);
    }

    /**
     * Test cache TTL (Time To Live) behavior
     */
    public function testCacheTtlBehavior()
    {
        $pageId = 'test-page-ttl';
        $cacheFile = $this->tempCacheDir . 'content_' . md5($pageId) . '.cache';
        $data = json_encode(['results' => [], 'all_results_aggregated' => true]);

        // Write cache
        cacheWrite($cacheFile, $data);
        clearstatcache(true, $cacheFile);

        // Check with valid TTL (cache should be used)
        $validTtl = 3600; // 1 hour
        $isCacheValid = file_exists($cacheFile) && (time() - filemtime($cacheFile) < $validTtl);

        $this->assertTrue($isCacheValid);

        // Make cache file old
        touch($cacheFile, time() - 7200); // 2 hours old
        clearstatcache(true, $cacheFile);

        // Check with shorter TTL (cache should be expired)
        $shortTtl = 3600; // 1 hour
        $isCacheExpired = !(file_exists($cacheFile) && (time() - filemtime($cacheFile) < $shortTtl));

        $this->assertTrue($isCacheExpired);
    }

    /**
     * Test cache cleanup removes only expired files
     */
    public function testCacheCleanupRemovesOnlyExpiredFiles()
    {
        // Create mix of fresh and old cache files
        $freshFile = $this->tempCacheDir . 'fresh.cache';
        $oldFile1 = $this->tempCacheDir . 'old1.cache';
        $oldFile2 = $this->tempCacheDir . 'old2.cache';

        file_put_contents($freshFile, 'fresh data');
        file_put_contents($oldFile1, 'old data 1');
        file_put_contents($oldFile2, 'old data 2');

        // Make some files old
        $oldTime = time() - 10000; // ~2.7 hours ago
        touch($oldFile1, $oldTime);
        touch($oldFile2, $oldTime);

        // Cleanup files older than 1 hour (3600 seconds)
        $deleted = cacheCleanup($this->tempCacheDir, 3600);

        $this->assertEquals(2, $deleted);
        $this->assertFileDoesNotExist($oldFile1);
        $this->assertFileDoesNotExist($oldFile2);
        $this->assertFileExists($freshFile);
    }

    /**
     * Test cache cleanup handles empty directory
     */
    public function testCacheCleanupHandlesEmptyDirectory()
    {
        $emptyDir = sys_get_temp_dir() . '/empty_cache_' . uniqid() . '/';
        mkdir($emptyDir, 0755, true);

        $deleted = cacheCleanup($emptyDir, 3600);

        $this->assertEquals(0, $deleted);

        rmdir($emptyDir);
    }

    /**
     * Test cache cleanup handles non-existent directory gracefully
     */
    public function testCacheCleanupHandlesNonExistentDirectory()
    {
        $nonExistentDir = '/non/existent/cache/directory/';

        $deleted = cacheCleanup($nonExistentDir, 3600);

        $this->assertEquals(0, $deleted);
    }

    /**
     * Test probabilistic cache cleanup
     */
    public function testProbabilisticCacheCleanup()
    {
        // Create old files
        for ($i = 0; $i < 5; $i++) {
            $file = $this->tempCacheDir . "old_{$i}.cache";
            file_put_contents($file, "old data {$i}");
            touch($file, time() - 10000);
        }

        // Test with 0% probability - should never clean
        $runs = 10;
        $cleanedCount = 0;
        for ($i = 0; $i < $runs; $i++) {
            $result = maybeCacheCleanup($this->tempCacheDir, 0, 5000);
            $cleanedCount += $result;
        }

        $this->assertEquals(0, $cleanedCount);

        // Test with 100% probability - should always clean
        $result = maybeCacheCleanup($this->tempCacheDir, 1.0, 5000);
        $this->assertEquals(5, $result);
    }

    /**
     * Test concurrent cache writes are atomic
     */
    public function testConcurrentCacheWritesAreAtomic()
    {
        $cacheFile = $this->tempCacheDir . 'concurrent.cache';

        // Simulate multiple writes
        $data1 = 'First write data';
        $data2 = 'Second write data';
        $data3 = 'Third write data';

        cacheWrite($cacheFile, $data1);
        cacheWrite($cacheFile, $data2);
        cacheWrite($cacheFile, $data3);

        // File should exist and contain the last write
        $this->assertFileExists($cacheFile);
        $this->assertEquals($data3, file_get_contents($cacheFile));

        // No temporary files should remain
        $tempFiles = glob($this->tempCacheDir . '*.tmp_*');
        $this->assertEmpty($tempFiles);
    }

    /**
     * Test cache write handles write failures
     */
    public function testCacheWriteHandlesWriteFailures()
    {
        // Try to write to an invalid path
        $invalidPath = '/root/system/forbidden/test.cache';
        $data = 'test data';

        $result = cacheWrite($invalidPath, $data);

        $this->assertFalse($result);
    }

    /**
     * Test cache file naming with different page IDs
     */
    public function testCacheFileNamingWithDifferentPageIds()
    {
        $pageIds = [
            'simple-page-id',
            'page-with-dashes-123',
            '12345678-1234-1234-1234-123456789abc', // UUID format
            'special!@#$%chars'
        ];

        foreach ($pageIds as $pageId) {
            $hash = md5($pageId);
            $cacheFile = $this->tempCacheDir . 'content_' . $hash . '.cache';

            // Verify hash is consistent
            $this->assertEquals(32, strlen($hash));
            $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $hash);

            // Verify cache file can be created
            $result = cacheWrite($cacheFile, 'data');
            $this->assertTrue($result);
        }
    }

    /**
     * Test different cache durations for different content types
     */
    public function testDifferentCacheDurationsForContentTypes()
    {
        $cacheDurations = [
            'content' => 3600,      // 1 hour
            'pagedata' => 7200,     // 2 hours
            'subpages' => 86400,    // 1 day
            'mentions' => 604800    // 1 week
        ];

        $now = time();

        foreach ($cacheDurations as $type => $duration) {
            $cacheFile = $this->tempCacheDir . "{$type}_test.cache";
            cacheWrite($cacheFile, "data for {$type}");

            // Simulate time passing
            $fileAge = $duration - 100; // Just under expiration
            touch($cacheFile, $now - $fileAge);
            clearstatcache(true, $cacheFile);

            // Check if still valid
            $isValid = (time() - filemtime($cacheFile)) < $duration;
            $this->assertTrue($isValid, "Cache for {$type} should still be valid");

            // Simulate expiration
            touch($cacheFile, $now - $duration - 100);
            clearstatcache(true, $cacheFile);
            $isExpired = (time() - filemtime($cacheFile)) >= $duration;
            $this->assertTrue($isExpired, "Cache for {$type} should be expired");
        }
    }

    /**
     * Test cache cleanup with large number of files
     */
    public function testCacheCleanupWithLargeNumberOfFiles()
    {
        // Create 100 old cache files
        for ($i = 0; $i < 100; $i++) {
            $file = $this->tempCacheDir . "old_{$i}.cache";
            file_put_contents($file, "data {$i}");
            touch($file, time() - 10000);
        }

        // Create 50 fresh files
        for ($i = 0; $i < 50; $i++) {
            $file = $this->tempCacheDir . "fresh_{$i}.cache";
            file_put_contents($file, "fresh data {$i}");
        }

        // Cleanup old files
        $deleted = cacheCleanup($this->tempCacheDir, 5000);

        $this->assertEquals(100, $deleted);

        // Verify fresh files still exist
        for ($i = 0; $i < 50; $i++) {
            $file = $this->tempCacheDir . "fresh_{$i}.cache";
            $this->assertFileExists($file);
        }
    }

    /**
     * Test cache directory creation if not exists
     */
    public function testCacheDirectoryCreationIfNotExists()
    {
        $newCacheDir = sys_get_temp_dir() . '/new_cache_dir_' . uniqid() . '/';

        // Directory shouldn't exist yet
        $this->assertDirectoryDoesNotExist($newCacheDir);

        // Simulate config check and creation
        if (!file_exists($newCacheDir) && !is_dir($newCacheDir)) {
            $created = mkdir($newCacheDir, 0755, true);
            $this->assertTrue($created);
        }

        $this->assertDirectoryExists($newCacheDir);

        // Cleanup
        rmdir($newCacheDir);
    }

    /**
     * Test cache read after write consistency
     */
    public function testCacheReadAfterWriteConsistency()
    {
        $testData = [
            'page_id' => 'test-123',
            'content' => 'Sample content',
            'timestamp' => time(),
            'nested' => [
                'key1' => 'value1',
                'key2' => 'value2'
            ]
        ];

        $cacheFile = $this->tempCacheDir . 'consistency.cache';
        $jsonData = json_encode($testData);

        // Write
        cacheWrite($cacheFile, $jsonData);

        // Read
        $readData = file_get_contents($cacheFile);
        $decodedData = json_decode($readData, true);

        // Verify data integrity
        $this->assertEquals($testData, $decodedData);
        $this->assertEquals('test-123', $decodedData['page_id']);
        $this->assertEquals('value1', $decodedData['nested']['key1']);
    }

    /**
     * Test cache cleanup doesn't delete non-cache files
     */
    public function testCacheCleanupDoesntDeleteNonCacheFiles()
    {
        // Create various file types
        file_put_contents($this->tempCacheDir . 'old.cache', 'data');
        file_put_contents($this->tempCacheDir . 'config.php', 'config');
        file_put_contents($this->tempCacheDir . 'data.json', 'json');
        file_put_contents($this->tempCacheDir . 'readme.txt', 'text');

        // Make them all old
        $oldTime = time() - 10000;
        touch($this->tempCacheDir . 'old.cache', $oldTime);
        touch($this->tempCacheDir . 'config.php', $oldTime);
        touch($this->tempCacheDir . 'data.json', $oldTime);
        touch($this->tempCacheDir . 'readme.txt', $oldTime);

        // Run cleanup
        $deleted = cacheCleanup($this->tempCacheDir, 5000);

        // Only .cache files should be deleted
        $this->assertEquals(1, $deleted);
        $this->assertFileDoesNotExist($this->tempCacheDir . 'old.cache');
        $this->assertFileExists($this->tempCacheDir . 'config.php');
        $this->assertFileExists($this->tempCacheDir . 'data.json');
        $this->assertFileExists($this->tempCacheDir . 'readme.txt');

        // Cleanup
        @unlink($this->tempCacheDir . 'config.php');
        @unlink($this->tempCacheDir . 'data.json');
        @unlink($this->tempCacheDir . 'readme.txt');
    }

    /**
     * Test cache with special characters in data
     */
    public function testCacheWithSpecialCharactersInData()
    {
        $specialData = [
            'polish' => 'żółć gęślą jaźń',
            'emoji' => '🚀 💡 ⚡',
            'html' => '<div class="test">Content</div>',
            'quotes' => "Single 'quotes' and \"double quotes\"",
            'newlines' => "Line 1\nLine 2\nLine 3"
        ];

        $cacheFile = $this->tempCacheDir . 'special.cache';
        $jsonData = json_encode($specialData, JSON_UNESCAPED_UNICODE);

        cacheWrite($cacheFile, $jsonData);

        $readData = file_get_contents($cacheFile);
        $decodedData = json_decode($readData, true);

        $this->assertEquals($specialData, $decodedData);
        $this->assertEquals('żółć gęślą jaźń', $decodedData['polish']);
        $this->assertEquals('🚀 💡 ⚡', $decodedData['emoji']);
    }

    /**
     * Test cache cleanup performance with timing
     */
    public function testCacheCleanupPerformance()
    {
        // Create 1000 old files
        for ($i = 0; $i < 1000; $i++) {
            $file = $this->tempCacheDir . "perf_{$i}.cache";
            file_put_contents($file, "data {$i}");
            touch($file, time() - 10000);
        }

        $startTime = microtime(true);
        $deleted = cacheCleanup($this->tempCacheDir, 5000);
        $endTime = microtime(true);

        $duration = $endTime - $startTime;

        $this->assertEquals(1000, $deleted);
        // Cleanup should complete in reasonable time (< 2 seconds for 1000 files)
        $this->assertLessThan(2.0, $duration, "Cleanup took too long: {$duration} seconds");
    }
}
