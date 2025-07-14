# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Language Settings

- **User Communication**: Communicate with the user in Polish
- **Code Comments**: Write all code comments in English
- **Code Changes**: Keep all variable names, functions and other code elements in English
- **Commit Messages**: Write commit messages in English using the conventional commits format:
  - Use prefixes: `feat:` (new feature), `fix:` (bug fix), `refactor:` (code change that neither fixes a bug nor adds a feature), `docs:` (documentation only), `style:` (formatting changes), `perf:` (performance improvements), `chore:` (maintenance tasks)
  - Write in imperative mood ("Add feature" not "Added feature")
  - Keep the first line under 72 characters
  - Be specific and concise about what was changed and why

## Project Overview

This is a PHP application that fetches and displays Notion pages as a website. It connects to the Notion API, retrieves page content, and renders it as HTML with appropriate styling and formatting.

## Development Commands

- **Run PHP Development Server**: `php -S localhost:8000 -t public_html`
- **Check PHP Syntax**: `php -l file.php`
- **Clear Cache**: Manually delete files in `private/cache/` directory

## Code Architecture

### Main Components

1. **URL Routing** (`public_html/.htaccess`, `public_html/index.php`)
   - Captures all requests and routes them to index.php
   - Parses request path for hierarchical page navigation

2. **Notion API Integration** (`private/notion_utils.php`)
   - Connects to Notion API using authentication token 
   - Fetches page content, titles, and hierarchical structure
   - Handles pagination for large Notion pages

3. **HTML Rendering** (`private/notion_utils.php`, `private/views/main_template.php`)
   - Converts Notion blocks to HTML with proper formatting
   - Supports various block types (text, images, lists, tables, etc.)
   - Handles rich text formatting (bold, italics, colors, etc.)

4. **Caching System** (`private/notion_utils.php`, `private/config_draft.php`)
   - Implements server-side caching with granular expiration controls
   - Different cache durations for content, page metadata, and subpage lists
   - Stored in `private/cache/` directory

5. **Content Protection** (`public_html/index.php`, `private/notion_utils.php`)
   - Password protection via `<pass>` tags
   - Content hiding via `<hide>` tags
   - Session-based verification

### Data Flow

1. User Request → URL Parsing → Page ID Resolution
2. Check Cache → Fetch from Notion API if needed → Update Cache
3. Convert Notion Blocks to HTML → Apply Template → Serve Response

### Configuration

- API credentials and page IDs in `private/config.php` (from `config_draft.php` template)
- Cache durations configurable in `$cacheDurations` array
- Content password in `$contentPassword` variable

## Important Notes

- **Security**: API keys should be set via environment variables, not hardcoded
- **Cache Directory**: Must be writable by the web server
- **PHP Requirements**: Requires PHP 7.x/8.x with cURL extension and session support
- **Web Server**: Apache with mod_rewrite and mod_env is recommended