<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Notion block rendering
 * Tests notionToHtml() function for all block types
 */
class BlockRenderingTest extends TestCase
{
    private $tempCacheDir;
    private $apiKey = 'test_api_key';
    private $cacheDurations = [
        'content' => 3600,
        'pagedata' => 7200,
        'subpages' => 86400,
        'mentions' => 604800
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempCacheDir = sys_get_temp_dir() . '/phpunit_cache_' . uniqid() . '/';
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
     * Test paragraph block rendering
     */
    public function testParagraphBlockRendering()
    {
        $content = [
            'results' => [
                [
                    'type' => 'paragraph',
                    'paragraph' => [
                        'rich_text' => [
                            ['type' => 'text', 'plain_text' => 'This is a paragraph.']
                        ]
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('<p>This is a paragraph.</p>', $html);
    }

    /**
     * Test empty paragraph rendering
     */
    public function testEmptyParagraphRendering()
    {
        $content = [
            'results' => [
                [
                    'type' => 'paragraph',
                    'paragraph' => [
                        'rich_text' => []
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('<p></p>', $html);
    }

    /**
     * Test heading_1 block rendering
     */
    public function testHeading1BlockRendering()
    {
        $content = [
            'results' => [
                [
                    'type' => 'heading_1',
                    'heading_1' => [
                        'rich_text' => [
                            ['type' => 'text', 'plain_text' => 'Heading 1']
                        ]
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('<h1>Heading 1</h1>', $html);
    }

    /**
     * Test heading_2 block rendering
     */
    public function testHeading2BlockRendering()
    {
        $content = [
            'results' => [
                [
                    'type' => 'heading_2',
                    'heading_2' => [
                        'rich_text' => [
                            ['type' => 'text', 'plain_text' => 'Heading 2']
                        ]
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('<h2>Heading 2</h2>', $html);
    }

    /**
     * Test heading_3 block rendering
     */
    public function testHeading3BlockRendering()
    {
        $content = [
            'results' => [
                [
                    'type' => 'heading_3',
                    'heading_3' => [
                        'rich_text' => [
                            ['type' => 'text', 'plain_text' => 'Heading 3']
                        ]
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('<h3>Heading 3</h3>', $html);
    }

    /**
     * Test bulleted list item rendering
     */
    public function testBulletedListItemRendering()
    {
        $content = [
            'results' => [
                [
                    'type' => 'bulleted_list_item',
                    'bulleted_list_item' => [
                        'rich_text' => [
                            ['type' => 'text', 'plain_text' => 'First item']
                        ]
                    ]
                ],
                [
                    'type' => 'bulleted_list_item',
                    'bulleted_list_item' => [
                        'rich_text' => [
                            ['type' => 'text', 'plain_text' => 'Second item']
                        ]
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>First item</li>', $html);
        $this->assertStringContainsString('<li>Second item</li>', $html);
        $this->assertStringContainsString('</ul>', $html);
    }

    /**
     * Test numbered list item rendering
     */
    public function testNumberedListItemRendering()
    {
        $content = [
            'results' => [
                [
                    'type' => 'numbered_list_item',
                    'numbered_list_item' => [
                        'rich_text' => [
                            ['type' => 'text', 'plain_text' => 'First']
                        ]
                    ]
                ],
                [
                    'type' => 'numbered_list_item',
                    'numbered_list_item' => [
                        'rich_text' => [
                            ['type' => 'text', 'plain_text' => 'Second']
                        ]
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('<ol>', $html);
        $this->assertStringContainsString('<li>First</li>', $html);
        $this->assertStringContainsString('<li>Second</li>', $html);
        $this->assertStringContainsString('</ol>', $html);
    }

    /**
     * Test mixed list types are properly closed and reopened
     */
    public function testMixedListTypesHandling()
    {
        $content = [
            'results' => [
                [
                    'type' => 'bulleted_list_item',
                    'bulleted_list_item' => [
                        'rich_text' => [['type' => 'text', 'plain_text' => 'Bullet']]
                    ]
                ],
                [
                    'type' => 'numbered_list_item',
                    'numbered_list_item' => [
                        'rich_text' => [['type' => 'text', 'plain_text' => 'Number']]
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        // Should close <ul> before opening <ol>
        $this->assertStringContainsString('</ul>', $html);
        $this->assertStringContainsString('<ol>', $html);
    }

    /**
     * Test to-do block rendering (unchecked)
     */
    public function testToDoBlockUnchecked()
    {
        $content = [
            'results' => [
                [
                    'type' => 'to_do',
                    'to_do' => [
                        'rich_text' => [
                            ['type' => 'text', 'plain_text' => 'Task to do']
                        ],
                        'checked' => false
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('todo-item', $html);
        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringNotContainsString('checked', $html);
        $this->assertStringContainsString('Task to do', $html);
    }

    /**
     * Test to-do block rendering (checked)
     */
    public function testToDoBlockChecked()
    {
        $content = [
            'results' => [
                [
                    'type' => 'to_do',
                    'to_do' => [
                        'rich_text' => [
                            ['type' => 'text', 'plain_text' => 'Completed task']
                        ],
                        'checked' => true
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('checked', $html);
    }

    /**
     * Test toggle block rendering
     */
    public function testToggleBlockRendering()
    {
        $content = [
            'results' => [
                [
                    'type' => 'toggle',
                    'toggle' => [
                        'rich_text' => [
                            ['type' => 'text', 'plain_text' => 'Click to expand']
                        ]
                    ],
                    'has_children' => false
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('<details', $html);
        $this->assertStringContainsString('notion-toggle', $html);
        $this->assertStringContainsString('<summary>Click to expand</summary>', $html);
        $this->assertStringContainsString('</details>', $html);
    }

    /**
     * Test code block rendering
     */
    public function testCodeBlockRendering()
    {
        $content = [
            'results' => [
                [
                    'type' => 'code',
                    'code' => [
                        'rich_text' => [
                            ['type' => 'text', 'plain_text' => 'const x = 42;']
                        ],
                        'language' => 'javascript'
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('<pre>', $html);
        $this->assertStringContainsString('<code', $html);
        $this->assertStringContainsString('class="language-javascript"', $html);
        $this->assertStringContainsString('const x = 42;', $html);
        $this->assertStringContainsString('</code></pre>', $html);
    }

    /**
     * Test code block without language defaults to plaintext
     */
    public function testCodeBlockWithoutLanguage()
    {
        $content = [
            'results' => [
                [
                    'type' => 'code',
                    'code' => [
                        'rich_text' => [
                            ['type' => 'text', 'plain_text' => 'plain text code']
                        ]
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('class="language-plaintext"', $html);
    }

    /**
     * Test quote block rendering
     */
    public function testQuoteBlockRendering()
    {
        $content = [
            'results' => [
                [
                    'type' => 'quote',
                    'quote' => [
                        'rich_text' => [
                            ['type' => 'text', 'plain_text' => 'This is a quote']
                        ]
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('<blockquote>This is a quote</blockquote>', $html);
    }

    /**
     * Test divider block rendering
     */
    public function testDividerBlockRendering()
    {
        $content = [
            'results' => [
                [
                    'type' => 'divider',
                    'divider' => []
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('<hr>', $html);
    }

    /**
     * Test callout block rendering with emoji
     */
    public function testCalloutBlockWithEmoji()
    {
        $content = [
            'results' => [
                [
                    'type' => 'callout',
                    'callout' => [
                        'rich_text' => [
                            ['type' => 'text', 'plain_text' => 'Important note']
                        ],
                        'icon' => [
                            'emoji' => '💡'
                        ]
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('class="callout"', $html);
        $this->assertStringContainsString('callout-emoji', $html);
        $this->assertStringContainsString('💡', $html);
        $this->assertStringContainsString('Important note', $html);
    }

    /**
     * Test callout block rendering with external icon
     */
    public function testCalloutBlockWithExternalIcon()
    {
        $content = [
            'results' => [
                [
                    'type' => 'callout',
                    'callout' => [
                        'rich_text' => [
                            ['type' => 'text', 'plain_text' => 'Note with icon']
                        ],
                        'icon' => [
                            'external' => [
                                'url' => 'https://example.com/icon.png'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('callout-icon-external', $html);
        $this->assertStringContainsString('https://example.com/icon.png', $html);
    }

    /**
     * Test image block rendering with file URL
     */
    public function testImageBlockWithFileUrl()
    {
        $content = [
            'results' => [
                [
                    'type' => 'image',
                    'image' => [
                        'type' => 'file',
                        'file' => [
                            'url' => 'https://notion.so/image123.png'
                        ],
                        'caption' => []
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('<figure>', $html);
        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('src="https://notion.so/image123.png"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('</figure>', $html);
    }

    /**
     * Test image block rendering with external URL
     */
    public function testImageBlockWithExternalUrl()
    {
        $content = [
            'results' => [
                [
                    'type' => 'image',
                    'image' => [
                        'type' => 'external',
                        'external' => [
                            'url' => 'https://example.com/photo.jpg'
                        ],
                        'caption' => []
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('src="https://example.com/photo.jpg"', $html);
    }

    /**
     * Test image block with caption
     */
    public function testImageBlockWithCaption()
    {
        $content = [
            'results' => [
                [
                    'type' => 'image',
                    'image' => [
                        'type' => 'file',
                        'file' => [
                            'url' => 'https://example.com/image.png'
                        ],
                        'caption' => [
                            ['type' => 'text', 'plain_text' => 'Image caption']
                        ]
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('<figcaption>Image caption</figcaption>', $html);
    }

    /**
     * Test child_page block rendering
     */
    public function testChildPageBlockRendering()
    {
        $content = [
            'results' => [
                [
                    'type' => 'child_page',
                    'child_page' => [
                        'title' => 'Subpage Title'
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('child-page-link', $html);
        $this->assertStringContainsString('<a href="/subpage-title">', $html);
        $this->assertStringContainsString('📄', $html);
        $this->assertStringContainsString('Subpage Title', $html);
    }

    /**
     * Test bookmark block rendering
     */
    public function testBookmarkBlockRendering()
    {
        $content = [
            'results' => [
                [
                    'type' => 'bookmark',
                    'bookmark' => [
                        'url' => 'https://example.com',
                        'caption' => [
                            ['type' => 'text', 'plain_text' => 'Example website']
                        ]
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('notion-bookmark', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('Example website', $html);
    }

    /**
     * Test equation block rendering
     */
    public function testEquationBlockRendering()
    {
        $content = [
            'results' => [
                [
                    'type' => 'equation',
                    'equation' => [
                        'expression' => 'E = mc^2'
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('notion-equation', $html);
        $this->assertStringContainsString('\\[E = mc^2\\]', $html);
    }

    /**
     * Test table_of_contents block rendering
     */
    public function testTableOfContentsBlockRendering()
    {
        $content = [
            'results' => [
                [
                    'type' => 'table_of_contents',
                    'table_of_contents' => [
                        'color' => 'default'
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('notion-table-of-contents-placeholder', $html);
        $this->assertStringContainsString('data-color="default"', $html);
    }

    /**
     * Test video block rendering with external URL
     */
    public function testVideoBlockRendering()
    {
        $content = [
            'results' => [
                [
                    'type' => 'video',
                    'video' => [
                        'type' => 'external',
                        'external' => [
                            'url' => 'https://example.com/video.mp4'
                        ],
                        'caption' => []
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('notion-video', $html);
        $this->assertStringContainsString('<video', $html);
        $this->assertStringContainsString('controls', $html);
        $this->assertStringContainsString('src="https://example.com/video.mp4"', $html);
    }

    /**
     * Test file block rendering
     */
    public function testFileBlockRendering()
    {
        $content = [
            'results' => [
                [
                    'type' => 'file',
                    'file' => [
                        'type' => 'external',
                        'external' => [
                            'url' => 'https://example.com/document.pdf'
                        ],
                        'name' => 'Document.pdf',
                        'caption' => []
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('notion-file', $html);
        $this->assertStringContainsString('href="https://example.com/document.pdf"', $html);
        $this->assertStringContainsString('download="Document.pdf"', $html);
        $this->assertStringContainsString('📎', $html);
    }

    /**
     * Test embed block with YouTube URL
     */
    public function testEmbedBlockWithYouTube()
    {
        $content = [
            'results' => [
                [
                    'type' => 'embed',
                    'embed' => [
                        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'caption' => []
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('notion-embed', $html);
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('youtube.com/embed/dQw4w9WgXcQ', $html);
    }

    /**
     * Test embed block with Vimeo URL
     */
    public function testEmbedBlockWithVimeo()
    {
        $content = [
            'results' => [
                [
                    'type' => 'embed',
                    'embed' => [
                        'url' => 'https://vimeo.com/123456789',
                        'caption' => []
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('player.vimeo.com/video/123456789', $html);
    }

    /**
     * Test error handling for invalid content
     */
    public function testErrorHandlingForApiError()
    {
        $content = [
            'error' => 'Failed to fetch',
            'response_code' => 500
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('error-message', $html);
        $this->assertStringContainsString('Failed to fetch', $html);
    }

    /**
     * Test 404 error handling
     */
    public function testErrorHandlingFor404()
    {
        $content = [
            'error' => 'Not found',
            'response_code' => 404
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('error-message', $html);
        $this->assertStringContainsString('Nie znaleziono strony Notion', $html);
    }

    /**
     * Test empty content handling
     */
    public function testEmptyContentHandling()
    {
        $content = [
            'results' => []
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('info-message', $html);
        $this->assertStringContainsString('nie zawiera jeszcze treści', $html);
    }

    /**
     * Test unsupported block types are ignored gracefully
     */
    public function testUnsupportedBlockTypesIgnored()
    {
        $content = [
            'results' => [
                [
                    'type' => 'unsupported_block_type',
                    'unsupported_block_type' => []
                ],
                [
                    'type' => 'paragraph',
                    'paragraph' => [
                        'rich_text' => [
                            ['type' => 'text', 'plain_text' => 'Valid paragraph']
                        ]
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        // Should contain the valid paragraph
        $this->assertStringContainsString('Valid paragraph', $html);
        // Should not throw an error for unsupported type
    }

    /**
     * Test audio block rendering
     */
    public function testAudioBlockRendering()
    {
        $content = [
            'results' => [
                [
                    'type' => 'audio',
                    'audio' => [
                        'type' => 'external',
                        'external' => [
                            'url' => 'https://example.com/audio.mp3'
                        ],
                        'caption' => [
                            ['type' => 'text', 'plain_text' => 'Audio caption']
                        ]
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('notion-audio', $html);
        $this->assertStringContainsString('<audio', $html);
        $this->assertStringContainsString('controls', $html);
        $this->assertStringContainsString('https://example.com/audio.mp3', $html);
        $this->assertStringContainsString('Audio caption', $html);
    }

    /**
     * Test breadcrumb block rendering
     */
    public function testBreadcrumbBlockRendering()
    {
        $content = [
            'results' => [
                [
                    'type' => 'breadcrumb',
                    'breadcrumb' => []
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('notion-breadcrumb', $html);
        $this->assertStringContainsString('<nav', $html);
    }

    /**
     * Test paragraph with background color
     */
    public function testParagraphWithBackgroundColor()
    {
        $content = [
            'results' => [
                [
                    'type' => 'paragraph',
                    'paragraph' => [
                        'rich_text' => [
                            ['type' => 'text', 'plain_text' => 'Colored paragraph']
                        ],
                        'color' => 'blue_background'
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('notion-blue-background', $html);
        $this->assertStringContainsString('Colored paragraph', $html);
    }

    /**
     * Test heading with background color
     */
    public function testHeadingWithBackgroundColor()
    {
        $content = [
            'results' => [
                [
                    'type' => 'heading_2',
                    'heading_2' => [
                        'rich_text' => [
                            ['type' => 'text', 'plain_text' => 'Colored heading']
                        ],
                        'color' => 'yellow_background'
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('notion-yellow-background', $html);
        $this->assertStringContainsString('<h2', $html);
        $this->assertStringContainsString('Colored heading', $html);
    }

    /**
     * Test quote with background color
     */
    public function testQuoteWithBackgroundColor()
    {
        $content = [
            'results' => [
                [
                    'type' => 'quote',
                    'quote' => [
                        'rich_text' => [
                            ['type' => 'text', 'plain_text' => 'Colored quote']
                        ],
                        'color' => 'red_background'
                    ]
                ]
            ]
        ];

        $html = notionToHtml($content, $this->apiKey, $this->tempCacheDir, $this->cacheDurations);

        $this->assertStringContainsString('notion-red-background', $html);
        $this->assertStringContainsString('<blockquote', $html);
        $this->assertStringContainsString('Colored quote', $html);
    }
}
