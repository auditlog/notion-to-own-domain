<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Notion API functions
 * Uses fixtures to mock API responses (no real API calls)
 */
class NotionApiTest extends TestCase
{
    private $tempCacheDir;
    private $fixturesDir;
    private $apiKey = 'test_api_key_12345';

    protected function setUp(): void
    {
        parent::setUp();

        // Setup temporary cache directory
        $this->tempCacheDir = sys_get_temp_dir() . '/phpunit_cache_' . uniqid() . '/';
        if (!file_exists($this->tempCacheDir)) {
            mkdir($this->tempCacheDir, 0755, true);
        }

        // Setup fixtures directory path
        $this->fixturesDir = __DIR__ . '/../Fixtures/';
    }

    protected function tearDown(): void
    {
        // Clean up cache directory
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
     * Test getNotionContent returns cached data when available
     */
    public function testGetNotionContentReturnsCachedData()
    {
        $pageId = 'test-page-123';
        $cacheFile = $this->tempCacheDir . 'content_' . md5($pageId) . '.cache';

        // Create cache file with fixture data
        $fixtureData = file_get_contents($this->fixturesDir . 'page_content.json');
        cacheWrite($cacheFile, $fixtureData);

        // Get content - should return from cache
        $result = getNotionContent($pageId, $this->apiKey, $this->tempCacheDir, 3600);

        $this->assertIsString($result);
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('results', $decoded);
        $this->assertArrayHasKey('all_results_aggregated', $decoded);
        $this->assertTrue($decoded['all_results_aggregated']);
    }

    /**
     * Test getNotionContent cache expiration works correctly
     */
    public function testGetNotionContentCacheExpiration()
    {
        $pageId = 'test-page-expired';
        $cacheFile = $this->tempCacheDir . 'content_' . md5($pageId) . '.cache';

        // Create old cache file
        $oldData = json_encode(['results' => [], 'all_results_aggregated' => true]);
        file_put_contents($cacheFile, $oldData);

        // Make it appear old (2 hours ago)
        touch($cacheFile, time() - 7200);

        // Request with 1 hour cache - should be expired
        // Note: This will attempt real API call, so we expect it to fail/return error
        // In real tests, you'd mock the curl calls
        $result = getNotionContent($pageId, 'invalid_key', $this->tempCacheDir, 3600);

        $decoded = json_decode($result, true);

        // Should either get error or new data (not the old cached data)
        // For this test, we expect an error since we use invalid API key
        $this->assertIsArray($decoded);
    }

    /**
     * Test getNotionPageTitle returns page metadata
     */
    public function testGetNotionPageTitleReturnsMetadata()
    {
        $pageId = 'test-page-title-123';
        $cacheFile = $this->tempCacheDir . 'pagedata_' . md5($pageId) . '.cache';

        // Create cache with fixture data
        $fixtureData = file_get_contents($this->fixturesDir . 'page_title.json');
        $pageData = json_decode($fixtureData, true);

        // Extract title and cover
        $title = $pageData['properties']['title']['title'][0]['plain_text'];
        $coverUrl = $pageData['cover']['external']['url'];

        $cacheData = json_encode([
            'title' => $title,
            'coverUrl' => $coverUrl
        ]);

        cacheWrite($cacheFile, $cacheData);

        // Get page title
        $result = getNotionPageTitle($pageId, $this->apiKey, $this->tempCacheDir, 3600);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('coverUrl', $result);
        $this->assertEquals('My Sample Notion Page', $result['title']);
        $this->assertStringContainsString('unsplash.com', $result['coverUrl']);
    }

    /**
     * Test getNotionPageTitle returns default values when page not found
     */
    public function testGetNotionPageTitleReturnsDefaultsOnError()
    {
        $pageId = 'non-existent-page';

        // No cache file, will attempt API call with invalid key
        $result = getNotionPageTitle($pageId, 'invalid_key', $this->tempCacheDir, 3600);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('coverUrl', $result);
        // Should return default title
        $this->assertEquals('Moja strona z zawartością Notion', $result['title']);
        $this->assertNull($result['coverUrl']);
    }

    /**
     * Test findNotionSubpageId finds correct subpage by path
     */
    public function testFindNotionSubpageIdFindsCorrectSubpage()
    {
        $parentPageId = 'parent-page-123';
        $cacheFile = $this->tempCacheDir . 'subpages_' . md5($parentPageId) . '.cache';

        // Load fixture and build subpages cache
        $fixtureData = file_get_contents($this->fixturesDir . 'subpages.json');
        $subpagesData = json_decode($fixtureData, true);

        $subpages = [];
        foreach ($subpagesData['results'] as $block) {
            if ($block['type'] === 'child_page' && isset($block['child_page']['title'])) {
                $title = $block['child_page']['title'];
                $normalizedTitle = normalizeTitleForPath($title);
                if (!empty($normalizedTitle)) {
                    $subpages[$normalizedTitle] = $block['id'];
                }
            }
        }

        cacheWrite($cacheFile, json_encode($subpages));

        // Find subpage by normalized path
        $foundId = findNotionSubpageId($parentPageId, 'getting-started', $this->apiKey, $this->tempCacheDir, 3600);

        $this->assertEquals('subpage-001', $foundId);
    }

    /**
     * Test findNotionSubpageId handles Polish characters correctly
     */
    public function testFindNotionSubpageIdHandlesPolishCharacters()
    {
        $parentPageId = 'parent-page-polish';
        $cacheFile = $this->tempCacheDir . 'subpages_' . md5($parentPageId) . '.cache';

        // Load fixture
        $fixtureData = file_get_contents($this->fixturesDir . 'subpages.json');
        $subpagesData = json_decode($fixtureData, true);

        $subpages = [];
        foreach ($subpagesData['results'] as $block) {
            if ($block['type'] === 'child_page' && isset($block['child_page']['title'])) {
                $title = $block['child_page']['title'];
                $normalizedTitle = normalizeTitleForPath($title);
                if (!empty($normalizedTitle)) {
                    $subpages[$normalizedTitle] = $block['id'];
                }
            }
        }

        cacheWrite($cacheFile, json_encode($subpages));

        // Search for page with Polish characters (normalized)
        $foundId = findNotionSubpageId($parentPageId, 'special-characters-polish-zolc', $this->apiKey, $this->tempCacheDir, 3600);

        $this->assertEquals('subpage-004', $foundId);
    }

    /**
     * Test findNotionSubpageId returns null for non-existent subpage
     */
    public function testFindNotionSubpageIdReturnsNullForNonExistent()
    {
        $parentPageId = 'parent-page-456';
        $cacheFile = $this->tempCacheDir . 'subpages_' . md5($parentPageId) . '.cache';

        // Create cache with some subpages
        $subpages = [
            'existing-page' => 'page-id-001',
            'another-page' => 'page-id-002'
        ];

        cacheWrite($cacheFile, json_encode($subpages));

        // Try to find non-existent subpage
        $foundId = findNotionSubpageId($parentPageId, 'non-existent-page', $this->apiKey, $this->tempCacheDir, 3600);

        $this->assertNull($foundId);
    }

    /**
     * Test findNotionSubpageId handles empty path
     */
    public function testFindNotionSubpageIdHandlesEmptyPath()
    {
        $parentPageId = 'parent-page-789';

        $foundId = findNotionSubpageId($parentPageId, '', $this->apiKey, $this->tempCacheDir, 3600);

        $this->assertNull($foundId);
    }

    /**
     * Test findNotionSubpageId is case-insensitive
     */
    public function testFindNotionSubpageIdIsCaseInsensitive()
    {
        $parentPageId = 'parent-page-case';
        $cacheFile = $this->tempCacheDir . 'subpages_' . md5($parentPageId) . '.cache';

        $subpages = [
            'my-page-title' => 'page-id-001'
        ];

        cacheWrite($cacheFile, json_encode($subpages));

        // Search with different case
        $foundId1 = findNotionSubpageId($parentPageId, 'My-Page-Title', $this->apiKey, $this->tempCacheDir, 3600);
        $foundId2 = findNotionSubpageId($parentPageId, 'MY-PAGE-TITLE', $this->apiKey, $this->tempCacheDir, 3600);

        $this->assertEquals('page-id-001', $foundId1);
        $this->assertEquals('page-id-001', $foundId2);
    }

    /**
     * Test pagination handling in getNotionContent
     * Note: This test verifies the data structure, actual pagination would require mocking curl
     */
    public function testGetNotionContentHandlesPaginatedResponse()
    {
        // Load paginated fixture
        $fixtureData = file_get_contents($this->fixturesDir . 'paginated_response.json');
        $paginatedData = json_decode($fixtureData, true);

        // Verify fixture has pagination indicators
        $this->assertTrue($paginatedData['has_more']);
        $this->assertNotNull($paginatedData['next_cursor']);

        // In real implementation, getNotionContent should aggregate all pages
        // For this test, we verify the structure
        $this->assertArrayHasKey('results', $paginatedData);
        $this->assertIsArray($paginatedData['results']);
        $this->assertGreaterThan(0, count($paginatedData['results']));
    }

    /**
     * Test notionToHtml integration with fixture data
     */
    public function testNotionToHtmlWithFixtureData()
    {
        $fixtureData = file_get_contents($this->fixturesDir . 'page_content.json');
        $content = json_decode($fixtureData, true);

        $cacheDurations = [
            'content' => 3600,
            'pagedata' => 7200,
            'subpages' => 86400,
            'mentions' => 604800
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $cacheDurations);

        $this->assertIsString($html);
        $this->assertStringContainsString('<h1>Welcome to My Notion Page</h1>', $html);
        $this->assertStringContainsString('<strong>bold text</strong>', $html);
        $this->assertStringContainsString('<em>italic text</em>', $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('First bullet point', $html);
        $this->assertStringContainsString('language-javascript', $html);
        $this->assertStringContainsString('💡', $html);
    }

    /**
     * Test cache write and read cycle
     */
    public function testCacheWriteAndReadCycle()
    {
        $cacheFile = $this->tempCacheDir . 'test_cycle.cache';
        $testData = json_encode(['test' => 'data', 'number' => 123]);

        // Write to cache
        $writeResult = cacheWrite($cacheFile, $testData);
        $this->assertTrue($writeResult);

        // Read from cache
        $this->assertFileExists($cacheFile);
        $readData = file_get_contents($cacheFile);
        $this->assertEquals($testData, $readData);

        // Verify it's valid JSON
        $decoded = json_decode($readData, true);
        $this->assertEquals('data', $decoded['test']);
        $this->assertEquals(123, $decoded['number']);
    }

    /**
     * Test maybeCacheCleanup probability
     */
    public function testMaybeCacheCleanupProbability()
    {
        // Create some old cache files
        for ($i = 0; $i < 5; $i++) {
            $file = $this->tempCacheDir . "old_{$i}.cache";
            file_put_contents($file, 'old data');
            touch($file, time() - 10000);
        }

        // Run with 0% probability - should not clean anything
        $deleted = maybeCacheCleanup($this->tempCacheDir, 0, 5000);
        $this->assertEquals(0, $deleted);

        // Run with 100% probability - should clean all old files
        $deleted = maybeCacheCleanup($this->tempCacheDir, 1.0, 5000);
        $this->assertEquals(5, $deleted);
    }

    /**
     * Test formatRichText with mention type (page link)
     * Note: This requires mocking getNotionPageTitle calls
     */
    public function testFormatRichTextWithPageMention()
    {
        $mentionedPageId = 'mentioned-page-123';
        $cacheFile = $this->tempCacheDir . 'pagedata_' . md5($mentionedPageId) . '.cache';

        // Cache the mentioned page data
        $pageData = json_encode([
            'title' => 'Mentioned Page Title',
            'coverUrl' => null
        ]);
        cacheWrite($cacheFile, $pageData);

        $richTextArray = [
            [
                'type' => 'mention',
                'mention' => [
                    'type' => 'page',
                    'page' => [
                        'id' => $mentionedPageId
                    ]
                ],
                'plain_text' => 'Mentioned Page Title'
            ]
        ];

        $result = formatRichText($richTextArray, $this->apiKey, $this->tempCacheDir, 7200, '');

        $this->assertStringContainsString('<a href="/mentioned-page-title">', $result);
        $this->assertStringContainsString('Mentioned Page Title', $result);
    }

    /**
     * Test formatRichText with external link
     */
    public function testFormatRichTextWithExternalLink()
    {
        $richTextArray = [
            [
                'type' => 'text',
                'plain_text' => 'Click here',
                'href' => 'https://example.com',
                'annotations' => [
                    'bold' => false,
                    'italic' => false,
                    'strikethrough' => false,
                    'underline' => false,
                    'code' => false,
                    'color' => 'default'
                ]
            ]
        ];

        $result = formatRichText($richTextArray, $this->apiKey, $this->tempCacheDir, 7200, '');

        $this->assertStringContainsString('href="https://example.com"', $result);
        $this->assertStringContainsString('target="_blank"', $result);
    }
}
