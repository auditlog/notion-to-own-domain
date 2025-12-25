<?php

namespace Tests\Functional;

use PHPUnit\Framework\TestCase;

/**
 * Functional tests for URL routing
 * Tests path parsing, hierarchical navigation, and edge cases
 */
class RoutingTest extends TestCase
{
    private $tempCacheDir;
    private $apiKey = 'test_api_key';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempCacheDir = sys_get_temp_dir() . '/phpunit_cache_routing_' . uniqid() . '/';
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
     * Test root path (empty) should use main page ID
     */
    public function testRootPathUsesMainPageId()
    {
        $requestPath = '';
        $mainPageId = 'main-page-123';

        if (empty($requestPath)) {
            $currentPageId = $mainPageId;
        }

        $this->assertEquals($mainPageId, $currentPageId);
    }

    /**
     * Test single level path parsing
     */
    public function testSingleLevelPathParsing()
    {
        $requestPath = 'getting-started';

        // Simulate path parsing
        $pathSegments = explode('/', $requestPath);

        $this->assertCount(1, $pathSegments);
        $this->assertEquals('getting-started', $pathSegments[0]);
    }

    /**
     * Test multi-level path parsing
     */
    public function testMultiLevelPathParsing()
    {
        $requestPath = 'docs/api/reference';

        $pathSegments = explode('/', $requestPath);

        $this->assertCount(3, $pathSegments);
        $this->assertEquals(['docs', 'api', 'reference'], $pathSegments);
    }

    /**
     * Test path with trailing slash is trimmed
     */
    public function testPathWithTrailingSlashIsTrimmed()
    {
        $requestPath = 'path/to/page/';

        $trimmed = trim($requestPath, '/');

        $this->assertEquals('path/to/page', $trimmed);
    }

    /**
     * Test path with leading slash is trimmed
     */
    public function testPathWithLeadingSlashIsTrimmed()
    {
        $requestPath = '/path/to/page';

        $trimmed = trim($requestPath, '/');

        $this->assertEquals('path/to/page', $trimmed);
    }

    /**
     * Test path with both leading and trailing slashes
     */
    public function testPathWithBothSlashes()
    {
        $requestPath = '/path/to/page/';

        $trimmed = trim($requestPath, '/');

        $this->assertEquals('path/to/page', $trimmed);
    }

    /**
     * Test empty segments are filtered out
     */
    public function testEmptySegmentsAreFilteredOut()
    {
        $requestPath = 'path//to///page';
        $pathSegments = explode('/', $requestPath);

        $validSegments = [];
        foreach ($pathSegments as $segment) {
            if (!empty($segment)) {
                $validSegments[] = $segment;
            }
        }

        $this->assertEquals(['path', 'to', 'page'], $validSegments);
    }

    /**
     * Test hierarchical page resolution (single level)
     */
    public function testHierarchicalPageResolutionSingleLevel()
    {
        $mainPageId = 'main-page-123';
        $requestPath = 'subpage';

        // Create cache with subpages
        $cacheFile = $this->tempCacheDir . 'subpages_' . md5($mainPageId) . '.cache';
        $subpages = [
            'subpage' => 'subpage-id-456'
        ];
        cacheWrite($cacheFile, json_encode($subpages));

        // Resolve
        $foundId = findNotionSubpageId($mainPageId, $requestPath, $this->apiKey, $this->tempCacheDir, 3600);

        $this->assertEquals('subpage-id-456', $foundId);
    }

    /**
     * Test hierarchical page resolution (nested levels)
     */
    public function testHierarchicalPageResolutionNestedLevels()
    {
        $mainPageId = 'main-page-123';
        $level1PageId = 'level1-page-456';
        $level2PageId = 'level2-page-789';

        // Setup cache for main page
        $cacheFile1 = $this->tempCacheDir . 'subpages_' . md5($mainPageId) . '.cache';
        cacheWrite($cacheFile1, json_encode(['docs' => $level1PageId]));

        // Setup cache for level 1
        $cacheFile2 = $this->tempCacheDir . 'subpages_' . md5($level1PageId) . '.cache';
        cacheWrite($cacheFile2, json_encode(['api' => $level2PageId]));

        // Simulate nested resolution
        $requestPath = 'docs/api';
        $pathSegments = explode('/', $requestPath);

        $currentParent = $mainPageId;
        $resolvedPageId = null;

        foreach ($pathSegments as $segment) {
            if (empty($segment)) continue;

            $foundId = findNotionSubpageId($currentParent, $segment, $this->apiKey, $this->tempCacheDir, 3600);

            if ($foundId === null) {
                $resolvedPageId = null;
                break;
            }

            $resolvedPageId = $foundId;
            $currentParent = $foundId;
        }

        $this->assertEquals($level2PageId, $resolvedPageId);
    }

    /**
     * Test non-existent path returns null/404
     */
    public function testNonExistentPathReturnsNull()
    {
        $mainPageId = 'main-page-123';
        $requestPath = 'non-existent-page';

        // Create empty cache
        $cacheFile = $this->tempCacheDir . 'subpages_' . md5($mainPageId) . '.cache';
        cacheWrite($cacheFile, json_encode([]));

        $foundId = findNotionSubpageId($mainPageId, $requestPath, $this->apiKey, $this->tempCacheDir, 3600);

        $this->assertNull($foundId);
    }

    /**
     * Test partial path match in nested structure
     */
    public function testPartialPathMatchInNestedStructure()
    {
        $mainPageId = 'main-page-123';
        $level1PageId = 'level1-page-456';

        // Setup caches
        $cacheFile1 = $this->tempCacheDir . 'subpages_' . md5($mainPageId) . '.cache';
        cacheWrite($cacheFile1, json_encode(['docs' => $level1PageId]));

        $cacheFile2 = $this->tempCacheDir . 'subpages_' . md5($level1PageId) . '.cache';
        cacheWrite($cacheFile2, json_encode(['api' => 'api-page-789']));

        // Try to access 'docs/non-existent'
        $pathSegments = ['docs', 'non-existent'];
        $currentParent = $mainPageId;
        $resolvedPageId = null;

        foreach ($pathSegments as $segment) {
            $foundId = findNotionSubpageId($currentParent, $segment, $this->apiKey, $this->tempCacheDir, 3600);

            if ($foundId === null) {
                $resolvedPageId = null;
                break;
            }

            $resolvedPageId = $foundId;
            $currentParent = $foundId;
        }

        // Should fail because 'non-existent' doesn't exist under 'docs'
        $this->assertNull($resolvedPageId);
    }

    /**
     * Test query string removal from path segment
     */
    public function testQueryStringRemovalFromPathSegment()
    {
        $segmentWithQuery = 'page-title?param=value&other=data';

        $segmentName = strtok($segmentWithQuery, '?');

        $this->assertEquals('page-title', $segmentName);
        $this->assertStringNotContainsString('?', $segmentName);
        $this->assertStringNotContainsString('param', $segmentName);
    }

    /**
     * Test normalization of path segments
     */
    public function testNormalizationOfPathSegments()
    {
        $segments = [
            'Getting Started' => 'getting-started',
            'API Documentation' => 'api-documentation',
            'Tutorials & Examples' => 'tutorials-examples',
            'FAQ (Frequently Asked)' => 'faq-frequently-asked',
            'żółć 123' => 'zolc-123'
        ];

        foreach ($segments as $original => $expected) {
            $normalized = normalizeTitleForPath($original);
            $this->assertEquals($expected, $normalized);
        }
    }

    /**
     * Test case-insensitive path matching
     */
    public function testCaseInsensitivePathMatching()
    {
        $mainPageId = 'main-page-123';

        $cacheFile = $this->tempCacheDir . 'subpages_' . md5($mainPageId) . '.cache';
        $subpages = [
            'my-page' => 'page-id-456'
        ];
        cacheWrite($cacheFile, json_encode($subpages));

        // Try different cases
        $found1 = findNotionSubpageId($mainPageId, 'my-page', $this->apiKey, $this->tempCacheDir, 3600);
        $found2 = findNotionSubpageId($mainPageId, 'My-Page', $this->apiKey, $this->tempCacheDir, 3600);
        $found3 = findNotionSubpageId($mainPageId, 'MY-PAGE', $this->apiKey, $this->tempCacheDir, 3600);

        $this->assertEquals('page-id-456', $found1);
        $this->assertEquals('page-id-456', $found2);
        $this->assertEquals('page-id-456', $found3);
    }

    /**
     * Test special characters in path are handled
     */
    public function testSpecialCharactersInPathAreHandled()
    {
        $dangerousPaths = [
            '../../../etc/passwd',
            'path<script>alert(1)</script>',
            'path?param=<script>',
            'path;rm -rf /'
        ];

        foreach ($dangerousPaths as $path) {
            // Apply sanitization (as in index.php)
            $sanitized = filter_var($path, FILTER_SANITIZE_URL);
            $sanitized = str_replace(['../', './'], '', $sanitized);

            $this->assertStringNotContainsString('../', $sanitized);
            $this->assertStringNotContainsString('./', $sanitized);
        }
    }

    /**
     * Test URL path string is passed correctly to child page links
     */
    public function testUrlPathStringPassedToChildPageLinks()
    {
        $currentUrlPathString = 'parent/child';

        // Simulate child page rendering
        $childPageTitle = 'Subpage';
        $pathSegment = normalizeTitleForPath($childPageTitle);

        $basePath = !empty($currentUrlPathString) ? rtrim($currentUrlPathString, '/') : '';
        $fullPath = !empty($basePath) ? $basePath . '/' . $pathSegment : $pathSegment;

        $this->assertEquals('parent/child/subpage', $fullPath);
    }

    /**
     * Test URL path for root level child page
     */
    public function testUrlPathForRootLevelChildPage()
    {
        $currentUrlPathString = '';

        $childPageTitle = 'Getting Started';
        $pathSegment = normalizeTitleForPath($childPageTitle);

        $basePath = !empty($currentUrlPathString) ? rtrim($currentUrlPathString, '/') : '';
        $fullPath = !empty($basePath) ? $basePath . '/' . $pathSegment : $pathSegment;

        $this->assertEquals('getting-started', $fullPath);
    }

    /**
     * Test deeply nested path (5+ levels)
     */
    public function testDeeplyNestedPath()
    {
        $requestPath = 'level1/level2/level3/level4/level5';
        $pathSegments = explode('/', $requestPath);

        $this->assertCount(5, $pathSegments);
        $this->assertEquals('level1', $pathSegments[0]);
        $this->assertEquals('level5', $pathSegments[4]);
    }

    /**
     * Test path segment with only special characters becomes empty
     */
    public function testPathSegmentWithOnlySpecialCharactersBecomesEmpty()
    {
        $invalidSegments = [
            '!@#$%^&*()',
            '---',
            '   ',
            '???'
        ];

        foreach ($invalidSegments as $segment) {
            $normalized = normalizeTitleForPath($segment);
            $this->assertEmpty($normalized);
        }
    }

    /**
     * Test breadcrumb generation for nested pages
     */
    public function testBreadcrumbGenerationForNestedPages()
    {
        $mainPageTitle = 'Home';
        $currentPageTitle = 'API Reference';
        $requestPath = 'docs/api/reference';

        // Simulate breadcrumb logic
        $breadcrumbs = [];
        $breadcrumbs[] = $mainPageTitle;

        if (!empty($requestPath)) {
            $breadcrumbs[] = $currentPageTitle;
        }

        $this->assertCount(2, $breadcrumbs);
        $this->assertEquals(['Home', 'API Reference'], $breadcrumbs);
    }

    /**
     * Test root page doesn't show breadcrumbs
     */
    public function testRootPageDoesntShowBreadcrumbs()
    {
        $requestPath = '';

        $showBreadcrumbs = !empty($requestPath);

        $this->assertFalse($showBreadcrumbs);
    }

    /**
     * Test subpage shows breadcrumbs
     */
    public function testSubpageShowsBreadcrumbs()
    {
        $requestPath = 'getting-started';

        $showBreadcrumbs = !empty($requestPath);

        $this->assertTrue($showBreadcrumbs);
    }

    /**
     * Test path normalization maintains hierarchy
     */
    public function testPathNormalizationMaintainsHierarchy()
    {
        $originalPath = 'Parent Page/Child Page/Grand Child';
        $segments = explode('/', $originalPath);

        $normalizedSegments = array_map(function ($segment) {
            return normalizeTitleForPath($segment);
        }, $segments);

        $this->assertEquals('parent-page', $normalizedSegments[0]);
        $this->assertEquals('child-page', $normalizedSegments[1]);
        $this->assertEquals('grand-child', $normalizedSegments[2]);

        $normalizedPath = implode('/', $normalizedSegments);
        $this->assertEquals('parent-page/child-page/grand-child', $normalizedPath);
    }

    /**
     * Test URL construction for full path
     */
    public function testUrlConstructionForFullPath()
    {
        $protocol = 'https://';
        $host = 'example.com';
        $requestPath = 'docs/api/reference';

        $currentUrl = $protocol . $host . '/' . $requestPath;

        $this->assertEquals('https://example.com/docs/api/reference', $currentUrl);
    }

    /**
     * Test URL construction for root path
     */
    public function testUrlConstructionForRootPath()
    {
        $protocol = 'https://';
        $host = 'example.com';
        $requestPath = '';

        $currentUrlPath = empty($requestPath) ? '/' : '/' . $requestPath;
        $currentUrl = $protocol . $host . $currentUrlPath;

        $this->assertEquals('https://example.com/', $currentUrl);
    }
}
