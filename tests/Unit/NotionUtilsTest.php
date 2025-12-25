<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Notion utility functions
 * Tests core functionality of notion_utils.php
 */
class NotionUtilsTest extends TestCase
{
    private $tempCacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        // Create temporary cache directory for tests
        $this->tempCacheDir = sys_get_temp_dir() . '/phpunit_cache_' . uniqid() . '/';
        if (!file_exists($this->tempCacheDir)) {
            mkdir($this->tempCacheDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up temporary cache directory
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
     * Test normalizeTitleForPath removes Polish characters (lowercase)
     */
    public function testNormalizeTitleRemovesPolishCharacters()
    {
        $this->assertEquals('zolw', normalizeTitleForPath('żółw'));
        $this->assertEquals('ae', normalizeTitleForPath('ąę'));
        $this->assertEquals('s', normalizeTitleForPath('ś'));
        $this->assertEquals('l', normalizeTitleForPath('ł'));
        $this->assertEquals('n', normalizeTitleForPath('ń'));
        $this->assertEquals('c', normalizeTitleForPath('ć'));
        $this->assertEquals('z', normalizeTitleForPath('ź'));
        $this->assertEquals('z', normalizeTitleForPath('ż'));
    }

    /**
     * Test normalizeTitleForPath handles uppercase Polish characters
     */
    public function testNormalizeTitleHandlesUppercasePolishCharacters()
    {
        $this->assertEquals('zolw', normalizeTitleForPath('ŻÓŁW'));
        $this->assertEquals('zolc', normalizeTitleForPath('ŻÓŁĆ'));
        $this->assertEquals('aecln', normalizeTitleForPath('ĄĘĆŁŃ'));
        $this->assertEquals('oszzz', normalizeTitleForPath('ÓŚŹŻŻ'));
    }

    /**
     * Test normalizeTitleForPath converts spaces to hyphens
     */
    public function testNormalizeTitleConvertesSpacesToHyphens()
    {
        $this->assertEquals('hello-world', normalizeTitleForPath('Hello World'));
        $this->assertEquals('multiple-spaces-here', normalizeTitleForPath('Multiple   Spaces   Here'));
    }

    /**
     * Test normalizeTitleForPath removes special characters
     */
    public function testNormalizeTitleRemovesSpecialCharacters()
    {
        $this->assertEquals('helloworld', normalizeTitleForPath('Hello@World!'));
        $this->assertEquals('test123', normalizeTitleForPath('Test#$%123'));
        $this->assertEquals('ab-cd', normalizeTitleForPath('A&B C*D'));
    }

    /**
     * Test normalizeTitleForPath converts to lowercase
     */
    public function testNormalizeTitleConvertsToLowercase()
    {
        $this->assertEquals('hello', normalizeTitleForPath('HELLO'));
        $this->assertEquals('mixed-case', normalizeTitleForPath('MiXeD CaSe'));
    }

    /**
     * Test normalizeTitleForPath handles multiple consecutive hyphens
     */
    public function testNormalizeTitleHandlesMultipleHyphens()
    {
        $this->assertEquals('a-b-c', normalizeTitleForPath('a---b---c'));
        $this->assertEquals('test', normalizeTitleForPath('--test--'));
    }

    /**
     * Test normalizeTitleForPath returns empty string for invalid input
     */
    public function testNormalizeTitleHandlesEmptyAndInvalidInput()
    {
        $this->assertEquals('', normalizeTitleForPath(''));
        $this->assertEquals('', normalizeTitleForPath('!@#$%^&*()'));
        $this->assertEquals('', normalizeTitleForPath('---'));
    }

    /**
     * Data provider for formatRichText tests
     */
    public function richTextProvider()
    {
        return [
            'plain text' => [
                [['type' => 'text', 'plain_text' => 'Hello']],
                'Hello'
            ],
            'bold text' => [
                [['type' => 'text', 'plain_text' => 'Bold', 'annotations' => ['bold' => true, 'italic' => false, 'strikethrough' => false, 'underline' => false, 'code' => false, 'color' => 'default']]],
                '<strong>Bold</strong>'
            ],
            'italic text' => [
                [['type' => 'text', 'plain_text' => 'Italic', 'annotations' => ['bold' => false, 'italic' => true, 'strikethrough' => false, 'underline' => false, 'code' => false, 'color' => 'default']]],
                '<em>Italic</em>'
            ],
            'strikethrough text' => [
                [['type' => 'text', 'plain_text' => 'Strike', 'annotations' => ['bold' => false, 'italic' => false, 'strikethrough' => true, 'underline' => false, 'code' => false, 'color' => 'default']]],
                '<del>Strike</del>'
            ],
            'underlined text' => [
                [['type' => 'text', 'plain_text' => 'Underline', 'annotations' => ['bold' => false, 'italic' => false, 'strikethrough' => false, 'underline' => true, 'code' => false, 'color' => 'default']]],
                '<u>Underline</u>'
            ],
            'code text' => [
                [['type' => 'text', 'plain_text' => 'code', 'annotations' => ['bold' => false, 'italic' => false, 'strikethrough' => false, 'underline' => false, 'code' => true, 'color' => 'default']]],
                '<code>code</code>'
            ],
        ];
    }

    /**
     * Test formatRichText handles various text annotations
     * @dataProvider richTextProvider
     */
    public function testFormatRichTextHandlesAnnotations($richTextArray, $expected)
    {
        $cacheDir = $this->tempCacheDir;
        $apiKey = 'test_key';
        $cacheDuration = 3600;

        $result = formatRichText($richTextArray, $apiKey, $cacheDir, $cacheDuration);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test formatRichText handles colored text
     */
    public function testFormatRichTextHandlesColors()
    {
        $richTextArray = [[
            'type' => 'text',
            'plain_text' => 'Colored',
            'annotations' => [
                'bold' => false,
                'italic' => false,
                'strikethrough' => false,
                'underline' => false,
                'code' => false,
                'color' => 'red'
            ]
        ]];

        $result = formatRichText($richTextArray, 'test_key', $this->tempCacheDir, 3600);
        $this->assertStringContainsString('class="notion-red"', $result);
        $this->assertStringContainsString('Colored', $result);
    }

    /**
     * Test formatRichText handles background colors
     */
    public function testFormatRichTextHandlesBackgroundColors()
    {
        $richTextArray = [[
            'type' => 'text',
            'plain_text' => 'BG Color',
            'annotations' => [
                'bold' => false,
                'italic' => false,
                'strikethrough' => false,
                'underline' => false,
                'code' => false,
                'color' => 'blue_background'
            ]
        ]];

        $result = formatRichText($richTextArray, 'test_key', $this->tempCacheDir, 3600);
        $this->assertStringContainsString('class="notion-blue-bg"', $result);
    }

    /**
     * Test formatRichText handles combined annotations (bold + italic)
     */
    public function testFormatRichTextHandlesCombinedAnnotations()
    {
        $richTextArray = [[
            'type' => 'text',
            'plain_text' => 'Combined',
            'annotations' => [
                'bold' => true,
                'italic' => true,
                'strikethrough' => false,
                'underline' => false,
                'code' => false,
                'color' => 'default'
            ]
        ]];

        $result = formatRichText($richTextArray, 'test_key', $this->tempCacheDir, 3600);
        $this->assertStringContainsString('<strong>', $result);
        $this->assertStringContainsString('<em>', $result);
        $this->assertStringContainsString('Combined', $result);
    }

    /**
     * Test formatRichText escapes HTML in plain text
     */
    public function testFormatRichTextEscapesHtml()
    {
        $richTextArray = [[
            'type' => 'text',
            'plain_text' => '<script>alert("XSS")</script>'
        ]];

        $result = formatRichText($richTextArray, 'test_key', $this->tempCacheDir, 3600);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    /**
     * Test formatRichText returns empty string for empty array
     */
    public function testFormatRichTextHandlesEmptyArray()
    {
        $this->assertEquals('', formatRichText([], 'test_key', $this->tempCacheDir, 3600));
    }

    /**
     * Test formatRichText returns empty string for null input
     */
    public function testFormatRichTextHandlesNullInput()
    {
        $this->assertEquals('', formatRichText(null, 'test_key', $this->tempCacheDir, 3600));
    }

    /**
     * Test cacheWrite creates cache file successfully
     */
    public function testCacheWriteCreatesFile()
    {
        $cacheFile = $this->tempCacheDir . 'test.cache';
        $data = 'Test cache data';

        $result = cacheWrite($cacheFile, $data);

        $this->assertTrue($result);
        $this->assertFileExists($cacheFile);
        $this->assertEquals($data, file_get_contents($cacheFile));
    }

    /**
     * Test cacheWrite is atomic (creates temp file then renames)
     */
    public function testCacheWriteIsAtomic()
    {
        $cacheFile = $this->tempCacheDir . 'atomic.cache';
        $data = 'Atomic write test';

        $result = cacheWrite($cacheFile, $data);

        $this->assertTrue($result);
        // Verify no temporary files left behind
        $tempFiles = glob($this->tempCacheDir . '*.tmp_*');
        $this->assertEmpty($tempFiles);
    }

    /**
     * Test cacheWrite handles write failures gracefully
     */
    public function testCacheWriteHandlesFailures()
    {
        // Try to write to an invalid/non-writable location
        $invalidPath = '/invalid/path/that/does/not/exist/test.cache';
        $data = 'Test data';

        $result = cacheWrite($invalidPath, $data);

        $this->assertFalse($result);
    }

    /**
     * Test cacheCleanup removes expired files
     */
    public function testCacheCleanupRemovesExpiredFiles()
    {
        // Create old cache files
        $oldFile1 = $this->tempCacheDir . 'old1.cache';
        $oldFile2 = $this->tempCacheDir . 'old2.cache';
        $newFile = $this->tempCacheDir . 'new.cache';

        file_put_contents($oldFile1, 'old data 1');
        file_put_contents($oldFile2, 'old data 2');
        file_put_contents($newFile, 'new data');

        // Make old files appear old (touch with past timestamp)
        $oldTime = time() - 10000; // 10000 seconds ago
        touch($oldFile1, $oldTime);
        touch($oldFile2, $oldTime);

        // Run cleanup with maxAge of 5000 seconds
        $deleted = cacheCleanup($this->tempCacheDir, 5000);

        $this->assertEquals(2, $deleted);
        $this->assertFileDoesNotExist($oldFile1);
        $this->assertFileDoesNotExist($oldFile2);
        $this->assertFileExists($newFile);
    }

    /**
     * Test cacheCleanup keeps fresh files
     */
    public function testCacheCleanupKeepsFreshFiles()
    {
        $freshFile = $this->tempCacheDir . 'fresh.cache';
        file_put_contents($freshFile, 'fresh data');

        $deleted = cacheCleanup($this->tempCacheDir, 3600);

        $this->assertEquals(0, $deleted);
        $this->assertFileExists($freshFile);
    }

    /**
     * Test cacheCleanup handles non-existent directory
     */
    public function testCacheCleanupHandlesNonExistentDirectory()
    {
        $deleted = cacheCleanup('/non/existent/directory/', 3600);
        $this->assertEquals(0, $deleted);
    }

    /**
     * Test processPasswordTags shows form when not verified
     */
    public function testProcessPasswordTagsShowsFormWhenNotVerified()
    {
        $html = '<p>Public content</p>&lt;pass&gt;<p>Secret content</p>&lt;/pass&gt;<p>More public</p>';
        $isVerified = false;
        $error = false;

        $result = processPasswordTags($html, $isVerified, $error);

        $this->assertStringContainsString('password-protected', $result);
        $this->assertStringContainsString('<form method="post">', $result);
        $this->assertStringContainsString('type="password"', $result);
        $this->assertStringNotContainsString('Secret content', $result);
    }

    /**
     * Test processPasswordTags shows error message when password is wrong
     */
    public function testProcessPasswordTagsShowsErrorWhenPasswordWrong()
    {
        $html = '&lt;pass&gt;<p>Secret</p>&lt;/pass&gt;';
        $isVerified = false;
        $error = true;

        $result = processPasswordTags($html, $isVerified, $error);

        $this->assertStringContainsString('password-error', $result);
        $this->assertStringContainsString('Nieprawidłowe hasło', $result);
    }

    /**
     * Test processPasswordTags reveals content when verified
     */
    public function testProcessPasswordTagsRevealsContentWhenVerified()
    {
        $html = '<p>Public</p>&lt;pass&gt;<p>Secret content</p>&lt;/pass&gt;<p>Public2</p>';
        $isVerified = true;
        $error = false;

        $result = processPasswordTags($html, $isVerified, $error);

        $this->assertStringContainsString('Secret content', $result);
        $this->assertStringNotContainsString('password-protected', $result);
        $this->assertStringNotContainsString('<form', $result);
    }

    /**
     * Test processPasswordTags handles multiple password blocks
     */
    public function testProcessPasswordTagsHandlesMultipleBlocks()
    {
        $html = '&lt;pass&gt;Secret1&lt;/pass&gt; Middle &lt;pass&gt;Secret2&lt;/pass&gt;';

        $resultNotVerified = processPasswordTags($html, false, false);
        $this->assertEquals(2, substr_count($resultNotVerified, 'password-protected'));

        $resultVerified = processPasswordTags($html, true, false);
        $this->assertStringContainsString('Secret1', $resultVerified);
        $this->assertStringContainsString('Secret2', $resultVerified);
    }

    /**
     * Test processHideTags removes hidden content
     */
    public function testProcessHideTagsRemovesHiddenContent()
    {
        $html = '<p>Visible</p>&lt;hide&gt;<p>Hidden content</p>&lt;/hide&gt;<p>Also visible</p>';

        $result = processHideTags($html);

        $this->assertStringContainsString('Visible', $result);
        $this->assertStringContainsString('Also visible', $result);
        $this->assertStringNotContainsString('Hidden content', $result);
    }

    /**
     * Test processHideTags handles multiple hide blocks
     */
    public function testProcessHideTagsHandlesMultipleBlocks()
    {
        $html = '&lt;hide&gt;Hidden1&lt;/hide&gt; Visible &lt;hide&gt;Hidden2&lt;/hide&gt;';

        $result = processHideTags($html);

        $this->assertStringContainsString('Visible', $result);
        $this->assertStringNotContainsString('Hidden1', $result);
        $this->assertStringNotContainsString('Hidden2', $result);
    }
}
