# PHPUnit Tests for Notion Page Viewer

This directory contains comprehensive test suite for the Notion Page Viewer PHP application.

## Test Structure

```
tests/
├── Unit/                   # Unit tests for individual functions
│   ├── NotionUtilsTest.php        # Tests for utility functions
│   └── BlockRenderingTest.php     # Tests for Notion block rendering
├── Integration/            # Integration tests (with mocked API)
│   └── NotionApiTest.php          # Tests for Notion API integration
├── Functional/             # Functional tests for complete features
│   ├── RoutingTest.php            # URL routing and path resolution
│   ├── CacheTest.php              # Cache behavior and lifecycle
│   └── SecurityTest.php           # Security features and XSS protection
└── Fixtures/               # Mock API responses
    ├── page_content.json
    ├── page_title.json
    ├── subpages.json
    ├── paginated_response.json
    └── paginated_response_page2.json
```

## Prerequisites

1. PHP 7.4 or higher
2. Composer installed
3. PHPUnit (installed via Composer)

## Installation

```bash
# Install dependencies
composer install
```

## Running Tests

### Run all tests
```bash
./vendor/bin/phpunit
```

### Run specific test suite
```bash
# Unit tests only
./vendor/bin/phpunit --testsuite Unit

# Integration tests only
./vendor/bin/phpunit --testsuite Integration

# Functional tests only
./vendor/bin/phpunit --testsuite Functional
```

### Run specific test file
```bash
./vendor/bin/phpunit tests/Unit/NotionUtilsTest.php
```

### Run with coverage report (requires Xdebug)
```bash
./vendor/bin/phpunit --coverage-html coverage/
```

## Test Coverage

### Unit Tests (tests/Unit/)

#### NotionUtilsTest.php
- ✅ `normalizeTitleForPath()` - URL slug generation
  - Polish character removal (ą, ę, ś, ł, ń, ć, ź, ż)
  - Space to hyphen conversion
  - Special character removal
  - Lowercase conversion
  - Multiple hyphen handling
  - Empty input handling

- ✅ `formatRichText()` - Text formatting
  - Bold, italic, strikethrough, underline, code
  - Text colors and background colors
  - Combined annotations
  - HTML escaping
  - Empty/null input handling

- ✅ `cacheWrite()` - Atomic cache writing
  - File creation
  - Atomic operations (temp file + rename)
  - Write failure handling

- ✅ `cacheCleanup()` - Cache maintenance
  - Expired file removal
  - Fresh file preservation
  - Non-existent directory handling

- ✅ `processPasswordTags()` - Content protection
  - Form display when not verified
  - Error message on wrong password
  - Content reveal when verified
  - Multiple password blocks

- ✅ `processHideTags()` - Content hiding
  - Hidden content removal
  - Multiple hide blocks

#### BlockRenderingTest.php
Tests for all Notion block types:
- ✅ Paragraph (normal and empty)
- ✅ Headings (h1, h2, h3)
- ✅ Lists (bulleted, numbered, mixed)
- ✅ To-do items (checked/unchecked)
- ✅ Toggle blocks
- ✅ Code blocks (with/without language)
- ✅ Quotes
- ✅ Dividers
- ✅ Callouts (emoji and external icon)
- ✅ Images (file and external URL, with caption)
- ✅ Child pages
- ✅ Bookmarks
- ✅ Equations
- ✅ Table of contents
- ✅ Videos
- ✅ Files
- ✅ Embeds (YouTube, Vimeo)
- ✅ Error handling (404, empty content, unsupported blocks)

### Integration Tests (tests/Integration/)

#### NotionApiTest.php
- ✅ `getNotionContent()` with cache
- ✅ Cache expiration handling
- ✅ `getNotionPageTitle()` metadata retrieval
- ✅ Default values on errors
- ✅ `findNotionSubpageId()` path resolution
- ✅ Polish character handling
- ✅ Non-existent subpage handling
- ✅ Case-insensitive matching
- ✅ Pagination support
- ✅ Full HTML rendering with fixtures
- ✅ Cache write/read cycle
- ✅ Probabilistic cleanup
- ✅ Rich text with page mentions
- ✅ External links

### Functional Tests (tests/Functional/)

#### SecurityTest.php
- ✅ Path traversal protection (../, ./)
- ✅ Valid path preservation
- ✅ Host header validation (reject invalid, accept valid)
- ✅ XSS protection (script tags, event handlers, javascript: protocol)
- ✅ Password verification (constant-time comparison)
- ✅ Password length validation (DoS prevention)
- ✅ Hide/pass tag processing
- ✅ URI sanitization
- ✅ Cache file name hashing
- ✅ Security header functions
- ✅ HTML entity encoding
- ✅ Query string stripping
- ✅ Double slash handling
- ✅ FILTER_SANITIZE_URL testing
- ✅ Password non-exposure
- ✅ Header injection prevention
- ✅ Cache cleanup path traversal protection

#### CacheTest.php
- ✅ Cache file creation and permissions
- ✅ TTL (Time To Live) behavior
- ✅ Selective cleanup (expired only)
- ✅ Empty/non-existent directory handling
- ✅ Probabilistic cleanup
- ✅ Atomic concurrent writes
- ✅ Write failure handling
- ✅ File naming with different page IDs
- ✅ Different cache durations per type
- ✅ Large file cleanup (100+ files)
- ✅ Directory auto-creation
- ✅ Read-after-write consistency
- ✅ Non-cache file preservation
- ✅ Special characters in data (Polish, emoji, HTML)
- ✅ Performance testing (1000 files)

#### RoutingTest.php
- ✅ Root path handling
- ✅ Single/multi-level path parsing
- ✅ Leading/trailing slash trimming
- ✅ Empty segment filtering
- ✅ Hierarchical page resolution (single and nested)
- ✅ Non-existent path handling
- ✅ Partial path matching
- ✅ Query string removal
- ✅ Path normalization
- ✅ Case-insensitive matching
- ✅ Special character handling
- ✅ URL path construction for child pages
- ✅ Deep nesting (5+ levels)
- ✅ Invalid segment handling
- ✅ Breadcrumb generation
- ✅ URL construction (full and root paths)

## Fixtures

Mock API responses are stored in `tests/Fixtures/`:

- **page_content.json** - Sample page with various block types
- **page_title.json** - Page metadata with title and cover
- **subpages.json** - List of child pages
- **paginated_response.json** - First page of paginated results
- **paginated_response_page2.json** - Second page of paginated results

## Writing New Tests

### Test Naming Convention
```php
public function testFunctionDoesSpecificThing()
{
    // Arrange
    $input = 'test data';

    // Act
    $result = functionUnderTest($input);

    // Assert
    $this->assertEquals('expected', $result);
}
```

### Using Data Providers
```php
/**
 * @dataProvider providerName
 */
public function testWithMultipleInputs($input, $expected)
{
    $result = functionUnderTest($input);
    $this->assertEquals($expected, $result);
}

public function providerName()
{
    return [
        'case 1' => ['input1', 'expected1'],
        'case 2' => ['input2', 'expected2'],
    ];
}
```

## Notes

- All tests use temporary directories for cache operations
- No real API calls are made (fixtures and mocks are used)
- Tests clean up after themselves (setUp/tearDown)
- Follow PSR-4 namespace convention: `Tests\Unit`, `Tests\Integration`, `Tests\Functional`
- All test methods must be public and start with `test`
- Use descriptive test names that explain what is being tested

## Continuous Integration

To integrate with CI/CD:

```yaml
# Example GitHub Actions
- name: Run tests
  run: ./vendor/bin/phpunit --coverage-text
```

## Troubleshooting

### Issue: "Class not found"
Solution: Run `composer dump-autoload`

### Issue: "Failed to write cache file"
Solution: Check permissions on temporary directory

### Issue: "No code coverage driver available"
Solution: Install Xdebug or PCOV extension

## Contributing

When adding new features:
1. Write tests first (TDD approach)
2. Ensure all tests pass before committing
3. Maintain test coverage above 80%
4. Update this README if adding new test suites
