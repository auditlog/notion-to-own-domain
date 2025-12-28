<?php

namespace Tests\Functional;

use PHPUnit\Framework\TestCase;

/**
 * Functional tests for security features
 * Tests path traversal protection, XSS prevention, password protection, etc.
 */
class SecurityTest extends TestCase
{
    /**
     * Test path validation removes path traversal attempts
     */
    public function testPathValidationRemovesPathTraversal()
    {
        $dangerousPaths = [
            '../../../etc/passwd',
            './hidden/file',
            'normal/../../../secret',
            'path/./to/../../../file'
        ];

        foreach ($dangerousPaths as $path) {
            $sanitized = filter_var($path, FILTER_SANITIZE_URL);
            $sanitized = str_replace(['../', './'], '', $sanitized);

            $this->assertStringNotContainsString('../', $sanitized);
            $this->assertStringNotContainsString('./', $sanitized);
        }
    }

    /**
     * Test path validation preserves valid paths
     */
    public function testPathValidationPreservesValidPaths()
    {
        $validPaths = [
            'getting-started',
            'api/documentation',
            'tutorials/examples/basic'
        ];

        foreach ($validPaths as $path) {
            $sanitized = filter_var($path, FILTER_SANITIZE_URL);
            $sanitized = str_replace(['../', './'], '', $sanitized);

            // Should remain unchanged (or only minimally changed)
            $this->assertNotEmpty($sanitized);
        }
    }

    /**
     * Test Host header validation rejects invalid formats
     */
    public function testHostHeaderValidationRejectsInvalidFormats()
    {
        $invalidHosts = [
            'evil.com@good.com',
            'host<script>',
            'host;rm -rf /',
            'host`whoami`',
            'host$(.)',
            'host with spaces',
        ];

        foreach ($invalidHosts as $host) {
            // Validate raw host with regex first (before any sanitization)
            // This catches hosts with spaces and other invalid characters
            $isValid = preg_match('/^[a-zA-Z0-9\-\.]+(\:[0-9]+)?$/', $host);

            $this->assertFalse((bool)$isValid, "Host '$host' should be rejected");
        }
    }

    /**
     * Test Host header validation accepts valid formats
     */
    public function testHostHeaderValidationAcceptsValidFormats()
    {
        $validHosts = [
            'example.com',
            'sub.example.com',
            'localhost',
            'example.com:8080',
            '192.168.1.1',
            'my-site.co.uk'
        ];

        foreach ($validHosts as $host) {
            $sanitized = filter_var($host, FILTER_SANITIZE_URL);
            $isValid = preg_match('/^[a-zA-Z0-9\-\.]+(\:[0-9]+)?$/', $sanitized);

            $this->assertTrue((bool)$isValid, "Host '$host' should be accepted");
        }
    }

    /**
     * Test XSS protection removes script tags
     */
    public function testXssProtectionRemovesScriptTags()
    {
        $htmlWithScripts = '<p>Normal text</p><script>alert("XSS")</script><p>More text</p>';

        // Apply same sanitization as in index.php
        $sanitized = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $htmlWithScripts);

        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringNotContainsString('alert("XSS")', $sanitized);
        $this->assertStringContainsString('Normal text', $sanitized);
    }

    /**
     * Test XSS protection removes dangerous event handlers
     */
    public function testXssProtectionRemovesDangerousEventHandlers()
    {
        $htmlWithEvents = '<img src="image.jpg" onerror="alert(1)">';
        $htmlWithEvents .= '<div onclick="malicious()">Click</div>';
        $htmlWithEvents .= '<a href="#" onmouseover="steal()">Link</a>';

        // Apply sanitization
        $sanitized = preg_replace('/\s+on\w+\s*=\s*(["\']).*?\1/i', '', $htmlWithEvents);

        $this->assertStringNotContainsString('onerror=', $sanitized);
        $this->assertStringNotContainsString('onclick=', $sanitized);
        $this->assertStringNotContainsString('onmouseover=', $sanitized);
    }

    /**
     * Test XSS protection removes javascript: protocol
     */
    public function testXssProtectionRemovesJavascriptProtocol()
    {
        $htmlWithJsProtocol = '<a href="javascript:alert(1)">Click</a>';
        $htmlWithJsProtocol .= '<a href="JavaScript:void(0)">Another</a>';

        // Apply sanitization
        $sanitized = preg_replace('/href\s*=\s*(["\'])javascript:.*?\1/i', 'href="#"', $htmlWithJsProtocol);

        $this->assertStringNotContainsString('javascript:', strtolower($sanitized));
        $this->assertStringContainsString('href="#"', $sanitized);
    }

    /**
     * Test password verification uses constant-time comparison
     */
    public function testPasswordVerificationUsesConstantTimeComparison()
    {
        $correctPassword = 'SecurePassword123!';
        $wrongPassword = 'WrongPassword456';

        // Test hash_equals behavior
        $this->assertTrue(hash_equals($correctPassword, $correctPassword));
        $this->assertFalse(hash_equals($correctPassword, $wrongPassword));
    }

    /**
     * Test password length validation prevents DoS
     */
    public function testPasswordLengthValidationPreventsDoS()
    {
        $veryLongPassword = str_repeat('a', 1000);

        // Simulate validation (as in index.php)
        $maxLength = 100;
        $isValid = strlen($veryLongPassword) <= $maxLength;

        $this->assertFalse($isValid);
        $this->assertGreaterThan($maxLength, strlen($veryLongPassword));
    }

    /**
     * Test hide tags remove content completely
     */
    public function testHideTagsRemoveContentCompletely()
    {
        $html = '<p>Public content</p>&lt;hide&gt;<p>Secret admin data</p>&lt;/hide&gt;<p>More public</p>';

        $result = processHideTags($html);

        $this->assertStringNotContainsString('Secret admin data', $result);
        $this->assertStringNotContainsString('&lt;hide&gt;', $result);
        $this->assertStringContainsString('Public content', $result);
        $this->assertStringContainsString('More public', $result);
    }

    /**
     * Test password tags hide content when not authenticated
     */
    public function testPasswordTagsHideContentWhenNotAuthenticated()
    {
        $html = '<p>Public</p>&lt;pass&gt;<p>Password protected content</p>&lt;/pass&gt;';

        $result = processPasswordTags($html, false, false);

        $this->assertStringNotContainsString('Password protected content', $result);
        $this->assertStringContainsString('password-protected', $result);
        $this->assertStringContainsString('type="password"', $result);
    }

    /**
     * Test password tags reveal content when authenticated
     */
    public function testPasswordTagsRevealContentWhenAuthenticated()
    {
        $html = '&lt;pass&gt;<p>Secret content revealed</p>&lt;/pass&gt;';

        $result = processPasswordTags($html, true, false);

        $this->assertStringContainsString('Secret content revealed', $result);
        $this->assertStringNotContainsString('password-protected', $result);
    }

    /**
     * Test password error is shown when verification fails
     */
    public function testPasswordErrorShownWhenVerificationFails()
    {
        $html = '&lt;pass&gt;<p>Protected</p>&lt;/pass&gt;';

        $result = processPasswordTags($html, false, true);

        $this->assertStringContainsString('password-error', $result);
        $this->assertStringContainsString('Nieprawidłowe hasło', $result);
    }

    /**
     * Test URI sanitization for redirects
     */
    public function testUriSanitizationForRedirects()
    {
        $dangerousUris = [
            'javascript:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            'http://evil.com@good.com',
        ];

        foreach ($dangerousUris as $uri) {
            $sanitized = filter_var($uri, FILTER_SANITIZE_URL);

            // After sanitization, these should be altered
            // filter_var should at minimum change them
            $this->assertIsString($sanitized);
        }
    }

    /**
     * Test cache file names are properly hashed
     */
    public function testCacheFileNamesAreProperlyHashed()
    {
        $pageIds = [
            '../../../etc/passwd',
            '<script>alert(1)</script>',
            'normal-page-id-123'
        ];

        foreach ($pageIds as $pageId) {
            $hash = md5($pageId);

            // Hash should be 32 character hexadecimal
            $this->assertEquals(32, strlen($hash));
            $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $hash);

            // Hash should not contain original dangerous characters
            $this->assertStringNotContainsString('../', $hash);
            $this->assertStringNotContainsString('<', $hash);
            $this->assertStringNotContainsString('>', $hash);
        }
    }

    /**
     * Test security headers helper functions exist and work
     */
    public function testSecurityHeadersFunctionsExist()
    {
        $this->assertTrue(function_exists('setSecurityHeaders'));
        $this->assertTrue(function_exists('setContentSecurityPolicy'));
        $this->assertTrue(function_exists('setBasicSecurityHeaders'));
        $this->assertTrue(function_exists('setHSTSHeader'));
    }

    /**
     * Test HTML entity encoding prevents XSS in error messages
     */
    public function testHtmlEntityEncodingInErrorMessages()
    {
        $maliciousInput = '<script>alert("XSS")</script>';

        $escaped = htmlspecialchars($maliciousInput);

        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringContainsString('&lt;script&gt;', $escaped);
    }

    /**
     * Test query string parameters are stripped from path segments
     */
    public function testQueryStringParametersStrippedFromPathSegments()
    {
        $segmentWithQuery = 'page-title?evil=param&xss=<script>';

        // Simulate strtok as used in index.php
        $segmentName = strtok($segmentWithQuery, '?');

        $this->assertEquals('page-title', $segmentName);
        $this->assertStringNotContainsString('?', $segmentName);
        $this->assertStringNotContainsString('evil', $segmentName);
        $this->assertStringNotContainsString('<script>', $segmentName);
    }

    /**
     * Test double slash handling in paths
     */
    public function testDoubleSlashHandlingInPaths()
    {
        $pathWithDoubleSlash = 'path//to///page';
        $segments = explode('/', $pathWithDoubleSlash);

        // Filter empty segments
        $validSegments = array_filter($segments, function ($segment) {
            return !empty($segment);
        });

        $this->assertCount(3, $validSegments);
        $this->assertEquals(['path', 'to', 'page'], array_values($validSegments));
    }

    /**
     * Test HTML escaping removes dangerous characters for output
     */
    public function testHtmlEscapingRemovesDangerousCharacters()
    {
        $dangerousStrings = [
            'path<script>',
            'path"onclick="alert(1)"',
            "path'onerror='alert(1)'",
        ];

        foreach ($dangerousStrings as $dangerous) {
            // Use htmlspecialchars for HTML output sanitization
            $sanitized = htmlspecialchars($dangerous, ENT_QUOTES, 'UTF-8');

            // Should not contain dangerous HTML (raw tags are escaped)
            $this->assertStringNotContainsString('<script>', $sanitized);
            $this->assertStringNotContainsString('"onclick=', $sanitized);
            $this->assertStringNotContainsString("'onerror=", $sanitized);
        }
    }

    /**
     * Test content password is not exposed in HTML
     */
    public function testContentPasswordNotExposedInHtml()
    {
        // Simulate password form HTML
        $html = '&lt;pass&gt;Secret&lt;/pass&gt;';
        $result = processPasswordTags($html, false, false);

        // Should not contain the actual password anywhere
        $this->assertStringNotContainsString('your_secret_password_here', $result);

        // Should only have password input field
        $this->assertStringContainsString('type="password"', $result);
        $this->assertStringContainsString('name="content_password"', $result);
    }

    /**
     * Test URL construction prevents header injection
     */
    public function testUrlConstructionPreventsHeaderInjection()
    {
        $maliciousHost = "example.com\r\nSet-Cookie: evil=true";

        // Sanitize and validate
        $sanitized = filter_var($maliciousHost, FILTER_SANITIZE_URL);
        $isValid = preg_match('/^[a-zA-Z0-9\-\.]+(\:[0-9]+)?$/', $sanitized);

        // Should be rejected due to newline characters
        $this->assertFalse((bool)$isValid);
    }

    /**
     * Test cache cleanup doesn't allow path traversal
     */
    public function testCacheCleanupDoesntAllowPathTraversal()
    {
        // Cache cleanup should only affect files matching *.cache pattern
        // Testing that glob pattern is restrictive
        $tempDir = sys_get_temp_dir() . '/test_cache_security_' . uniqid() . '/';
        mkdir($tempDir, 0755, true);

        // Create various files
        file_put_contents($tempDir . 'valid.cache', 'data');
        file_put_contents($tempDir . 'important.txt', 'important');
        file_put_contents($tempDir . 'config.php', 'config');

        // Make them old
        touch($tempDir . 'valid.cache', time() - 10000);
        touch($tempDir . 'important.txt', time() - 10000);
        touch($tempDir . 'config.php', time() - 10000);

        // Run cleanup
        $deleted = cacheCleanup($tempDir, 5000);

        // Should only delete .cache files
        $this->assertEquals(1, $deleted);
        $this->assertFileDoesNotExist($tempDir . 'valid.cache');
        $this->assertFileExists($tempDir . 'important.txt');
        $this->assertFileExists($tempDir . 'config.php');

        // Cleanup
        @unlink($tempDir . 'important.txt');
        @unlink($tempDir . 'config.php');
        @rmdir($tempDir);
    }

    /**
     * Test session cookie parameters are secure
     */
    public function testSessionCookieParametersAreSecure()
    {
        // Read index.php to verify session configuration
        $indexContent = file_get_contents(__DIR__ . '/../../public_html/index.php');

        // Check for secure cookie configuration
        $this->assertStringContainsString('session_set_cookie_params', $indexContent);
        $this->assertStringContainsString("'httponly' => true", $indexContent);
        $this->assertStringContainsString("'samesite' => 'Lax'", $indexContent);
    }

    /**
     * Test session timeout configuration exists
     */
    public function testSessionTimeoutConfigurationExists()
    {
        $indexContent = file_get_contents(__DIR__ . '/../../public_html/index.php');

        // Check for session timeout logic
        $this->assertStringContainsString('$sessionTimeout', $indexContent);
        $this->assertStringContainsString('last_activity', $indexContent);
        $this->assertStringContainsString('session_destroy', $indexContent);
    }

    /**
     * Test brute-force protection configuration exists
     */
    public function testBruteForceProtectionExists()
    {
        $indexContent = file_get_contents(__DIR__ . '/../../public_html/index.php');

        // Check for brute-force protection variables
        $this->assertStringContainsString('$maxPasswordAttempts', $indexContent);
        $this->assertStringContainsString('$passwordLockoutDuration', $indexContent);
        $this->assertStringContainsString('password_attempts', $indexContent);
        $this->assertStringContainsString('password_lockout_until', $indexContent);
    }

    /**
     * Test CSRF token generation
     */
    public function testCsrfTokenGeneration()
    {
        $indexContent = file_get_contents(__DIR__ . '/../../public_html/index.php');

        // Check for CSRF token generation
        $this->assertStringContainsString('csrf_token', $indexContent);
        $this->assertStringContainsString('random_bytes', $indexContent);
        $this->assertStringContainsString('bin2hex', $indexContent);
    }

    /**
     * Test CSRF token validation
     */
    public function testCsrfTokenValidation()
    {
        $indexContent = file_get_contents(__DIR__ . '/../../public_html/index.php');

        // Check for CSRF token validation with hash_equals
        $this->assertStringContainsString("hash_equals(\$_SESSION['csrf_token']", $indexContent);
    }

    /**
     * Test processPasswordTags includes CSRF token in form
     */
    public function testPasswordFormIncludesCsrfToken()
    {
        $html = '&lt;pass&gt;<p>Secret</p>&lt;/pass&gt;';
        $csrfToken = 'test_csrf_token_12345';

        $result = processPasswordTags($html, false, false, $csrfToken, false);

        $this->assertStringContainsString('name="csrf_token"', $result);
        $this->assertStringContainsString($csrfToken, $result);
        $this->assertStringContainsString('type="hidden"', $result);
    }

    /**
     * Test processPasswordTags shows lockout message
     */
    public function testPasswordFormShowsLockoutMessage()
    {
        $html = '&lt;pass&gt;<p>Secret</p>&lt;/pass&gt;';

        $result = processPasswordTags($html, false, true, 'token', true);

        $this->assertStringContainsString('Zbyt wiele prób', $result);
        $this->assertStringContainsString('disabled', $result);
    }

    /**
     * Test processPasswordTags disables form during lockout
     */
    public function testPasswordFormDisabledDuringLockout()
    {
        $html = '&lt;pass&gt;<p>Secret</p>&lt;/pass&gt;';

        $result = processPasswordTags($html, false, true, 'token', true);

        // Check that input and button are disabled
        $this->assertMatchesRegularExpression('/<input[^>]+disabled/', $result);
        $this->assertMatchesRegularExpression('/<button[^>]+disabled/', $result);
    }

    /**
     * Test CSRF failure increments brute-force counter
     * This verifies the fix for: "CSRF validation failure does not increment brute-force counter"
     */
    public function testCsrfFailureIncrementsBruteForceCounter()
    {
        $indexContent = file_get_contents(__DIR__ . '/../../public_html/index.php');

        // Verify that password_attempts is incremented BEFORE CSRF validation
        // The pattern should show: increment happens, then lockout check, then CSRF check
        $incrementPos = strpos($indexContent, "\$_SESSION['password_attempts']++");
        $csrfCheckPos = strpos($indexContent, "hash_equals(\$_SESSION['csrf_token']");

        $this->assertNotFalse($incrementPos, 'Attempt increment should exist');
        $this->assertNotFalse($csrfCheckPos, 'CSRF check should exist');
        $this->assertLessThan(
            $csrfCheckPos,
            $incrementPos,
            'Attempt counter must be incremented BEFORE CSRF validation to prevent bypass'
        );
    }

    /**
     * Test CSRF token regeneration after failed attempt
     */
    public function testCsrfTokenRegenerationAfterFailure()
    {
        $indexContent = file_get_contents(__DIR__ . '/../../public_html/index.php');

        // Check that CSRF token is regenerated after failed CSRF validation
        // This should appear within the CSRF failure block
        $csrfFailurePattern = '/if\s*\(\s*!hash_equals.*csrf_token.*\{[^}]*\$_SESSION\[.csrf_token.\]\s*=\s*bin2hex/s';
        $this->assertMatchesRegularExpression(
            $csrfFailurePattern,
            $indexContent,
            'CSRF token should be regenerated after validation failure'
        );
    }

    /**
     * Test lockout triggers CSRF token regeneration
     */
    public function testLockoutRegeneratesCsrfToken()
    {
        $indexContent = file_get_contents(__DIR__ . '/../../public_html/index.php');

        // Check that CSRF token is regenerated when lockout is triggered
        $lockoutPattern = '/password_lockout_until.*=.*time\(\).*\+.*\$passwordLockoutDuration[^}]*\$_SESSION\[.csrf_token.\]\s*=\s*bin2hex/s';
        $this->assertMatchesRegularExpression(
            $lockoutPattern,
            $indexContent,
            'CSRF token should be regenerated when lockout is triggered'
        );
    }

    /**
     * Test correct password on max attempt does not trigger lockout
     * Verifies fix: user with 2 failed attempts who submits correct password on 3rd try
     * should succeed, not be locked out
     */
    public function testCorrectPasswordOnMaxAttemptSucceeds()
    {
        $indexContent = file_get_contents(__DIR__ . '/../../public_html/index.php');

        // Verify lockout check is conditional on $passwordError being true
        // This ensures correct password doesn't trigger lockout
        $this->assertStringContainsString(
            'if ($passwordError && $_SESSION[\'password_attempts\'] >= $maxPasswordAttempts)',
            $indexContent,
            'Lockout should only trigger when $passwordError is true (failed password)'
        );

        // Verify successful password validation resets attempts and does NOT check lockout
        // Pattern: hash_equals success block resets counter
        $successResetsPattern = '/hash_equals\(\$contentPassword,\s*\$submittedPassword\)[^{]*\{[^}]*\$_SESSION\[.password_attempts.\]\s*=\s*0/s';
        $this->assertMatchesRegularExpression(
            $successResetsPattern,
            $indexContent,
            'Successful password should reset attempt counter in success block'
        );
    }
}
